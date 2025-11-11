<?php
require_once __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/auth.php';

require_login();
$u   = user();          // session snapshot
$pdo = db();

// (optional) pull fresh user row for the latest name from DB
try {
    $st = $pdo->prepare("SELECT id, name, email, role, status FROM users WHERE id=?");
    $st->execute([$u['id']]);
    $u_row = $st->fetch() ?: $u;
} catch (Throwable $e) {
    $u_row = $u;
}

/* -------------------------------------------------------
   LOAD DONOR PROFILE (if user is a donor)
--------------------------------------------------------*/
$donor = null;
if (($u_row['role'] ?? '') === 'donor') {
    $st = $pdo->prepare("SELECT * FROM donors WHERE user_id = ?");
    $st->execute([$u_row['id']]);
    $donor = $st->fetch();
}

/* -------------------------------------------------------
   HANDLERS (POST)
--------------------------------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    // Save donor profile (also allow updating user's display name)
    if ($action === 'save_donor') {
        $name  = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $city  = trim($_POST['city'] ?? '');
        $avail = isset($_POST['availability']) ? 1 : 0;

        if (($u_row['role'] ?? '') === 'donor') {
            try {
                $pdo->beginTransaction();

                // Update donor record
                $st = $pdo->prepare("UPDATE donors SET phone=?, city=?, availability=? WHERE user_id=?");
                $st->execute([$phone, $city, $avail, $u_row['id']]);

                // If a name is provided, update users.name too
                if ($name !== '') {
                    $st2 = $pdo->prepare("UPDATE users SET name=? WHERE id=?");
                    $st2->execute([$name, $u_row['id']]);
                    // keep session in sync so the header & page reflect immediately
                    $_SESSION['user']['name'] = $name;
                    $u_row['name'] = $name;
                }

                $pdo->commit();
                flash('success','Donor profile updated.');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                flash('error','Could not save profile. '.$e->getMessage());
            }
        } else {
            flash('error','Only donors can update donor profile.');
        }
        redirect('profile.php');
    }

    // Delete an OPEN request (hard delete)
    if ($action === 'delete_open_request') {
        $req_id = (int)($_POST['request_id'] ?? 0);
        $st = $pdo->prepare("DELETE FROM requests WHERE id=? AND requester_user_id=? AND status='open'");
        $st->execute([$req_id, $u_row['id']]);
        if ($st->rowCount() > 0) {
            flash('success', 'Open request deleted.');
        } else {
            flash('error', 'Could not delete: request not found or not open.');
        }
        redirect('profile.php');
    }

    // Cancel a BOOKED request + booking (free donors, delete links, delete booking, delete request)
    if ($action === 'delete_booked_request') {
        $booking_id = (int)($_POST['booking_id'] ?? 0);
        $request_id = (int)($_POST['request_id'] ?? 0);

        try {
            $pdo->beginTransaction();

            // Ensure booking belongs to user
            $bk = $pdo->prepare("SELECT id, requester_user_id FROM bookings WHERE id=? FOR UPDATE");
            $bk->execute([$booking_id]);
            $booking = $bk->fetch();
            if (!$booking || (int)$booking['requester_user_id'] !== (int)$u_row['id']) {
                throw new Exception('Booking not found.');
            }

            // Fetch donor ids and free them
            $ids = $pdo->prepare("SELECT donor_id FROM booking_donors WHERE booking_id=?");
            $ids->execute([$booking_id]);
            $donorIds = $ids->fetchAll(PDO::FETCH_COLUMN);

            if ($donorIds) {
                $in = implode(',', array_fill(0, count($donorIds), '?'));
                $free = $pdo->prepare("UPDATE donors SET availability=1 WHERE id IN ($in)");
                $free->execute($donorIds);

                $delLinks = $pdo->prepare("DELETE FROM booking_donors WHERE booking_id=?");
                $delLinks->execute([$booking_id]);
            }

            // Delete booking
            $delBk = $pdo->prepare("DELETE FROM bookings WHERE id=?");
            $delBk->execute([$booking_id]);

            // Delete the request row (if present and belongs to the same user)
            if ($request_id > 0) {
                $delRq = $pdo->prepare("DELETE FROM requests WHERE id=? AND requester_user_id=?");
                $delRq->execute([$request_id, $u_row['id']]);
            }

            $pdo->commit();
            flash('success', 'Booking cancelled and request deleted.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('error', 'Could not cancel booking. ' . $e->getMessage());
        }
        redirect('profile.php');
    }
}

/* -------------------------------------------------------
   STATS + LISTS
--------------------------------------------------------*/
$stats = [
    'total_requests'  => 0,
    'open_requests'   => 0,
    'booked_requests' => 0,
];

$stats['total_requests'] = (int)$pdo
    ->query("SELECT COUNT(*) c FROM requests WHERE requester_user_id=".$pdo->quote($u_row['id']))
    ->fetch()['c'];

$stats['open_requests'] = (int)$pdo
    ->query("SELECT COUNT(*) c FROM requests WHERE requester_user_id=".$pdo->quote($u_row['id'])." AND status='open'")
    ->fetch()['c'];

$stats['booked_requests'] = (int)$pdo
    ->query("SELECT COUNT(*) c FROM requests WHERE requester_user_id=".$pdo->quote($u_row['id'])." AND status='booked'")
    ->fetch()['c'];

// Open requests (show Book Now → donors.php, plus Delete)
$st = $pdo->prepare("SELECT id, patient_name, blood_group, city, units, urgency, created_at
                     FROM requests
                     WHERE requester_user_id=? AND status='open'
                     ORDER BY created_at DESC");
$st->execute([$u_row['id']]);
$open = $st->fetchAll();

// Booked requests with donors reserved
$st = $pdo->prepare("
  SELECT
    b.id                               AS booking_id,
    b.created_at,
    b.qty,
    r.id                               AS request_id,
    r.patient_name,
    r.blood_group,
    r.units,
    r.city,
    COALESCE(GROUP_CONCAT(DISTINCT u2.name ORDER BY u2.name SEPARATOR ', '), '') AS donor_names
  FROM bookings b
  LEFT JOIN requests r      ON r.id = b.request_id
  LEFT JOIN booking_donors bd ON bd.booking_id = b.id
  LEFT JOIN donors d        ON d.id = bd.donor_id
  LEFT JOIN users  u2       ON u2.id = d.user_id
  WHERE b.requester_user_id = ?
  GROUP BY b.id
  ORDER BY b.created_at DESC
");
$st->execute([$u_row['id']]);
$booked = $st->fetchAll();
?>
<h2>My Profile — <?= e($u_row['name'] ?? $u['name']) ?></h2>

<!-- Stats row -->
<section class="grid mt">
  <div class="stat card">
    <div class="stat-num"><?= e($stats['total_requests']) ?></div>
    <div class="stat-label">Total Requests</div>
  </div>
  <div class="stat card">
    <div class="stat-num"><?= e($stats['open_requests']) ?></div>
    <div class="stat-label">Open Requests</div>
  </div>
  <div class="stat card">
    <div class="stat-num"><?= e($stats['booked_requests']) ?></div>
    <div class="stat-label">Booked</div>
  </div>
</section>

<!-- Donor profile edit (if donor) -->
<?php if (($u_row['role'] ?? '') === 'donor' && $donor): ?>
  <section class="card mt">
    <h3 class="section-title">Donor Profile</h3>
    <form method="post" class="row">
      <?php csrf_field(); ?>
      <input type="hidden" name="action" value="save_donor">

      <div class="col">
        <label>Your Name</label>
        <input type="text" name="name" value="<?= e($u_row['name'] ?? '') ?>" placeholder="Your full name">
      </div>

      <div class="col">
        <label>Phone</label>
        <input type="text" name="phone" value="<?= e($donor['phone']) ?>" required>
      </div>

      <div class="col">
        <label>City</label>
        <input type="text" name="city" value="<?= e($donor['city']) ?>" required>
      </div>

      <div class="col checkbox-inline">
  <label for="availability" class="inline">
    <input id="availability" type="checkbox" name="availability" <?= $donor['availability'] ? 'checked' : '' ?>>
    <span>Available to donate</span>
  </label>
</div>


      <div class="btn-col">
        <button type="submit" class="btn">Save</button>
      </div>
    </form>
  </section>
<?php endif; ?>

<!-- Open requests with Book Now + Delete -->
<section class="card mt">
  <h3 class="section-title">My Open Requests</h3>
  <?php if (!$open): ?>
    <p class="muted">You have no open requests.</p>
  <?php else: ?>
    <table class="donor-table">
      <colgroup>
        <col style="width:28%"><col style="width:12%"><col style="width:12%"><col style="width:28%"><col style="width:20%">
      </colgroup>
      <thead>
        <tr>
          <th>Patient</th>
          <th>Blood</th>
          <th>Units</th>
          <th>City</th>
          <th class="right">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($open as $r): ?>
          <tr>
            <td><?= e($r['patient_name']) ?></td>
            <td><?= e($r['blood_group']) ?></td>
            <td><?= (int)$r['units'] ?></td>
            <td><?= e($r['city']) ?></td>
            <td class="right" style="display:flex; gap:8px; justify-content:flex-end;">
              <a class="btn"
                 href="donors.php?blood_group=<?= urlencode($r['blood_group']) ?>&request_id=<?= (int)$r['id'] ?>&patient=<?= urlencode($r['patient_name']) ?>&units=<?= (int)$r['units'] ?>">
                 Book Now
              </a>
              <form method="post" onsubmit="return confirm('Delete this open request?');" style="display:inline">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="delete_open_request">
                <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                <button type="submit" class="btn btn-outline">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<!-- Booked summary (shows person, donors, and requester name) -->
<section class="card mt">
  <h3 class="section-title">My Booked Requests</h3>
  <?php if (!$booked): ?>
    <p class="muted">No bookings yet.</p>
  <?php else: ?>
    <table class="donor-table">
      <colgroup>
        <col style="width:26%"><col style="width:12%"><col style="width:10%"><col style="width:32%"><col style="width:20%">
      </colgroup>
      <thead>
        <tr>
          <th>Patient</th>
          <th>Blood</th>
          <th>Units</th>
          <th>Donors Reserved</th>
          <th class="right">Booked At / Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($booked as $b): ?>
          <tr>
            <td>
              <?= e($b['patient_name'] ?: '—') ?><br>
              <small class="muted">Requested by: <?= e($u_row['name']) ?></small>
            </td>
            <td><?= e($b['blood_group']) ?></td>
            <td><?= (int)($b['qty'] ?: $b['units']) ?></td>
            <td><?= $b['donor_names'] ? e($b['donor_names']) : '<span class="muted">Not recorded</span>' ?></td>
            <td class="right">
              <div><?= e($b['created_at']) ?></div>
              <form method="post" onsubmit="return confirm('Cancel booking and delete this request?');" style="margin-top:6px">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="delete_booked_request">
                <input type="hidden" name="booking_id" value="<?= (int)$b['booking_id'] ?>">
                <input type="hidden" name="request_id" value="<?= (int)($b['request_id'] ?? 0) ?>">
                <button type="submit" class="btn btn-outline">Cancel &amp; Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
