<?php
require_once __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/csrf.php';
require_login();

$u = user();

// Fetch donor row if user is donor
$donor = null;
if (($u['role'] ?? '') === 'donor') {
    $st = db()->prepare("SELECT * FROM donors WHERE user_id = ?");
    $st->execute([$u['id']]);
    $donor = $st->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $phone = trim($_POST['phone'] ?? '');
    $city  = trim($_POST['city'] ?? '');
    $avail = isset($_POST['availability']) ? 1 : 0;

    if (($u['role'] ?? '') === 'donor') {
        $st = db()->prepare("UPDATE donors SET phone=?, city=?, availability=? WHERE user_id=?");
        $st->execute([$phone, $city, $avail, $u['id']]);
        flash('success','Profile updated.');
        redirect('profile.php');
    } else {
        flash('error','Only donors can update donor profile.');
    }
}
?>

<h2>My Profile</h2>
<?php if (($u['role'] ?? '') === 'donor' && $donor): ?>
  <form method="post">
    <?php csrf_field(); ?>
    <label>Phone</label>
    <input type="text" name="phone" value="<?= e($donor['phone']) ?>" required>
    <label>City</label>
    <input type="text" name="city" value="<?= e($donor['city']) ?>" required>
    <label><input type="checkbox" name="availability" <?= $donor['availability'] ? 'checked' : '' ?>> Available to donate</label>
    <br><br>
    <button type="submit">Save</button>
  </form>
  <p>Status: <?= $donor['is_approved'] ? 'Approved' : 'Pending approval' ?></p>
<?php else: ?>
  <p>No donor profile found or not a donor.</p>
<?php endif; ?>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
