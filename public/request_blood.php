<?php
error_reporting(E_ALL); ini_set('display_errors', 1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/auth.php';

$guest = !is_logged_in();

/**
 * Handle POST before sending any HTML to the browser,
 * so redirects/flash headers work reliably.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($guest) {
        flash('error', 'Please login to submit a blood request.');
        redirect('login.php?next=request_blood.php');
    }
    csrf_verify();

    $patient_name  = trim($_POST['patient_name'] ?? '');
    $blood_group   = trim($_POST['blood_group'] ?? '');
    $units         = (int)($_POST['units'] ?? 0);
    $city          = trim($_POST['city'] ?? '');
    $urgency       = trim($_POST['urgency'] ?? 'normal');
    $contact_phone = trim($_POST['contact_phone'] ?? '');
    $note          = trim($_POST['note'] ?? '');

    $errors = validate_required($_POST, ['patient_name','blood_group','units','city','contact_phone']);
    if (!in_array($blood_group, ['A+','A-','B+','B-','O+','O-','AB+','AB-'])) $errors['blood_group']='Invalid group';
    if ($units <= 0) $errors['units'] = 'Units must be > 0';

    if (empty($errors)) {
        $st = db()->prepare("INSERT INTO requests
          (patient_name, blood_group, units, city, urgency, contact_phone, note, status, created_at, requester_user_id)
          VALUES (?, ?, ?, ?, ?, ?, ?, 'open', NOW(), ?)");
        $requester_id = user()['id'];
        $st->execute([$patient_name,$blood_group,$units,$city,$urgency,$contact_phone,$note,$requester_id]);

        // Build donors.php?blood_group=...&city=...
        $filters = array_filter([
            'blood_group' => $blood_group,
            'city'        => $city
        ], fn($v) => $v !== '' && $v !== null);

        flash('success','Request submitted! Showing matching donors.');
        redirect('donors.php' . ($filters ? ('?' . http_build_query($filters)) : ''));
    } else {
        flash('error','Please fill required fields.');
        redirect('request_blood.php');
    }
}

// ---- render page (GET) ----
require_once __DIR__ . '/../partials/header.php';
?>
<h2>Request Blood</h2>

<?php if ($guest): ?>
  <div class="flash error">You must <a href="login.php?next=request_blood.php" style="color:#a10000;text-decoration:underline">login</a> to submit a request.</div>
<?php endif; ?>

<form method="post">
  <?php csrf_field(); ?>
  <label>Patient Name</label>
  <input type="text" name="patient_name" required <?= $guest?'disabled':'' ?>>

  <label>Blood Group</label>
  <select name="blood_group" required <?= $guest?'disabled':'' ?>>
    <option value="">Select</option>
    <option>A+</option><option>A-</option>
    <option>B+</option><option>B-</option>
    <option>O+</option><option>O-</option>
    <option>AB+</option><option>AB-</option>
  </select>

  <label>Units Required</label>
  <input type="number" name="units" min="1" required <?= $guest?'disabled':'' ?>>

  <label>City</label>
  <input type="text" name="city" required <?= $guest?'disabled':'' ?>>

  <label>Urgency</label>
  <select name="urgency" <?= $guest?'disabled':'' ?>>
    <option value="normal">Normal</option>
    <option value="high">High</option>
    <option value="critical">Critical</option>
  </select>

  <label>Contact Phone</label>
  <input type="text" name="contact_phone" required <?= $guest?'disabled':'' ?>>

  <label>Notes (optional)</label>
  <textarea name="note" rows="3" <?= $guest?'disabled':'' ?>></textarea>

  <div class="form-actions">
    <button type="submit" <?= $guest?'disabled title="Login to submit"':'' ?>>Submit Request</button>
    <?php if ($guest): ?>
      <a class="btn btn-outline" href="login.php?next=request_blood.php" style="margin-left:8px">Login to submit</a>
    <?php endif; ?>
  </div>
</form>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
