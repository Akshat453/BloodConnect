<?php
error_reporting(E_ALL); ini_set('display_errors', 1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/auth.php';

require_login();                 // must be logged in
$u = user();                      // current user
$user_id = (int)$u['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $phone = trim($_POST['phone'] ?? '');
    $city  = trim($_POST['city'] ?? '');
    $bg    = trim($_POST['blood_group'] ?? '');

    $errors = validate_required($_POST, ['phone','city','blood_group']);
    if (!in_array($bg, ['A+','A-','B+','B-','O+','O-','AB+','AB-'])) $errors['blood_group']='Invalid group';

    if (empty($errors)) {
        $pdo = db();
        // upsert (one donor profile per user)
        $did = $pdo->prepare("SELECT id FROM donors WHERE user_id=?");
        $did->execute([$user_id]);
        if ($did->fetchColumn()) {
            $st = $pdo->prepare("UPDATE donors SET phone=?, city=?, blood_group=?, availability=1 WHERE user_id=?");
            $st->execute([$phone,$city,$bg,$user_id]);
            flash('success','Donor profile updated. Await admin approval if pending.');
        } else {
            $st = $pdo->prepare("INSERT INTO donors (user_id, phone, city, blood_group, availability, is_approved, created_at)
                                 VALUES (?, ?, ?, ?, 1, 0, NOW())");
            $st->execute([$user_id, $phone, $city, $bg]);
            flash('success','Donor profile created! Wait for admin approval.');
        }
        // optional: mark account as donor
        if (($u['role'] ?? 'requester') !== 'donor') {
            $pdo->prepare("UPDATE users SET role='donor' WHERE id=?")->execute([$user_id]);
        }
        redirect('profile.php'); // or donors.php
    } else {
        flash('error','Please fill all required fields correctly.');
        redirect('register_donor.php');
    }
}

// render after POST handling
require_once __DIR__ . '/../partials/header.php';
?>
<h2>Become a Donor</h2>
<p class="muted">Logged in as <strong><?= e($u['name'] ?? $u['email']) ?></strong></p>
<form method="post">
  <?php csrf_field(); ?>
  <label>Phone</label><input type="text" name="phone" required>
  <label>City</label><input type="text" name="city" required>
  <label>Blood Group</label>
  <select name="blood_group" required>
    <option value="">Select</option>
    <option>A+</option><option>A-</option>
    <option>B+</option><option>B-</option>
    <option>O+</option><option>O-</option>
    <option>AB+</option><option>AB-</option>
  </select>
  <div class="form-actions"><button type="submit">Save Donor Profile</button></div>
</form>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
