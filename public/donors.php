<?php
require_once __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/csrf.php';

/* ====== Config: prices & surcharges ====== */
$valid_groups = ['A+','A-','B+','B-','O+','O-','AB+','AB-'];

/* Price per 1 unit (INR). Change as you like. */
$prices = [
  'A+' => 1500, 'A-' => 1600,
  'B+' => 1500, 'B-' => 1600,
  'O+' => 1400, 'O-' => 1700,
  'AB+' => 1800, 'AB-' => 1900,
];

/* Informational surcharges (not calculated here, just shown) */
$surcharges = [
  'normal'   => 0,   // %
  'high'     => 10,  // %
  'critical' => 20,  // %
];

$fmt = fn($n) => '₹' . number_format((float)$n);

/* ====== Read filters / prefill context ====== */
$bg          = trim($_GET['blood_group'] ?? '');
$city        = trim($_GET['city'] ?? '');
$request_id  = (int)($_GET['request_id'] ?? 0);
$patient     = trim($_GET['patient'] ?? '');
$prefill_qty = (int)($_GET['units'] ?? 1);
if ($prefill_qty < 1) $prefill_qty = 1;

/* ====== Availability summary (free units) ====== */
$counts = [];
try {
  $cst = db()->query("SELECT blood_group, COUNT(*) c
                      FROM donors
                      WHERE availability = 1
                      GROUP BY blood_group");
  foreach ($cst->fetchAll() as $row) {
    $counts[$row['blood_group']] = (int)$row['c'];
  }
} catch (Throwable $e) { /* table may not exist yet */ }

/* ====== Donor listing (filtered) ====== */
$sql = "SELECT u.name, d.city, d.phone, d.blood_group, d.availability
        FROM donors d
        JOIN users u ON u.id = d.user_id
        WHERE 1=1";
$params = [];

if ($bg !== '')  { $sql .= " AND d.blood_group = ?"; $params[] = $bg; }
if ($city !== ''){ $sql .= " AND d.city LIKE ?";     $params[] = "%$city%"; }

$sql .= " ORDER BY d.availability DESC, u.name ASC";

$st = db()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();
?>

<h2>Find Donors</h2>

<!-- Availability chips + price per unit -->
<div class="list mt-s" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:4px">
  <?php foreach ($valid_groups as $g): ?>
    <span class="pill" style="background:#fff0f0;border:1px solid #f2d9d9;color:#8a0000">
      <?= e($g) ?>: <?= (int)($counts[$g] ?? 0) ?> • <?= $fmt($prices[$g] ?? 0) ?>/unit
    </span>
  <?php endforeach; ?>
</div>

<!-- Extra charge note placed RIGHT AFTER chips -->
<div class="muted" style="margin-bottom:18px; font-size:14px">
  <strong>Extra charge by urgency:</strong>
  Normal <?= $surcharges['normal'] ?>%,
  High <?= $surcharges['high'] ?>%,
  Critical <?= $surcharges['critical'] ?>%
</div>


<!-- Booking form (pre-filled when coming from profile) -->
<form method="post" action="book.php" class="row" style="align-items:flex-end;border:1px dashed #e5a7a7;padding:12px;border-radius:8px;margin-bottom:16px">
  <?php csrf_field(); ?>

  <?php if ($request_id): ?>
    <input type="hidden" name="request_id" value="<?= (int)$request_id ?>">
  <?php endif; ?>

  <div class="col">
    <label>Blood Group to Book</label>
    <select name="blood_group" required>
      <option value="">Select</option>
      <?php foreach ($valid_groups as $g): ?>
        <option value="<?= e($g) ?>" <?= $bg===$g?'selected':'' ?>>
          <?= e($g) ?> — <?= $fmt($prices[$g] ?? 0) ?>/unit (available: <?= (int)($counts[$g] ?? 0) ?>)
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="col">
    <label>Quantity (units)</label>
    <input type="number" name="qty" min="1" value="<?= (int)$prefill_qty ?>" required>
  </div>

  <div class="col">
    <label>Booking For</label>
    <input type="text" name="patient" placeholder="Patient name" value="<?= e($patient) ?>">
  </div>

  <div class="col btn-col">
    <?php if (is_logged_in()): ?>
      <button type="submit">Book</button>
    <?php else: ?>
      <button type="button" disabled title="Login to book">Book</button>
      <a class="btn btn-outline" href="login.php?next=donors.php" style="margin-left:8px">Login to book</a>
    <?php endif; ?>
  </div>
</form>

<?php if ($patient || $request_id): ?>
  <div class="muted" style="margin-bottom:10px">
    <span class="pill" style="background:#eef9ff;border:1px solid #cbe7ff;color:#055160">
      Booking for: <strong><?= e($patient ?: 'Patient') ?></strong>
      <?= $request_id ? ' • Request #'.(int)$request_id : '' ?>
    </span>
  </div>
<?php endif; ?>

<!-- Search form -->
<form method="get" class="row">
  <div class="col">
    <label>Blood Group</label>
    <select name="blood_group">
      <option value="">Any</option>
      <?php foreach ($valid_groups as $g): ?>
        <option <?= $bg===$g?'selected':'' ?>><?= e($g) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="col">
    <label>City</label>
    <input type="text" name="city" value="<?= e($city) ?>">
  </div>

  <div class="col" style="align-self:end;">
    <button type="submit">Search</button>
  </div>
</form>

<?php if (!$rows): ?>
  <p class="muted">No donors found for the selected filters.</p>
<?php else: ?>
<table class="donor-table">
  <colgroup>
    <col style="width:24%">
    <col style="width:18%">
    <col style="width:14%">
    <col style="width:14%">
    <col style="width:14%">
    <col style="width:16%">
  </colgroup>
  <thead>
    <tr>
      <th class="left">Name</th>
      <th class="left">City</th>
      <th class="center">Blood Group</th>
      <th class="center">Availability</th>
      <th class="center">Unit Price</th>
      <th class="right">Phone</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td class="left"><?= e($r['name']) ?></td>
        <td class="left"><?= e($r['city']) ?></td>
        <td class="center"><?= e($r['blood_group']) ?></td>
        <td class="center"><?= $r['availability'] ? 'Available' : 'Unavailable' ?></td>
        <td class="center"><?= $fmt($prices[$r['blood_group']] ?? 0) ?></td>
        <td class="right">
          <?php if (is_logged_in()): ?>
            <?= e($r['phone']) ?>
          <?php else: ?>
            <?= e(mask_phone($r['phone'])) ?> <small class="muted">(login to view)</small>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
