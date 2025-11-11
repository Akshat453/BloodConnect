<?php require_once __DIR__ . '/../config/config.php'; ?>
<?php require_once __DIR__ . '/../app/functions.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= e(APP_NAME) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- External frontend assets -->
  <link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/css/styles.css">
  <script defer src="<?= e(APP_URL) ?>/assets/js/main.js"></script>
</head>
<body>
<header>
  <nav class="nav-bar">
    <!-- LEFT -->
    <div class="nav-left">
      <a href="<?= e(APP_URL) ?>/index.php"><?= e(APP_NAME) ?></a>
      <a href="<?= e(APP_URL) ?>/donors.php">Find Donors</a>
      <a href="<?= e(APP_URL) ?>/request_blood.php">Request Blood</a>
      <a href="<?= e(APP_URL) ?>/register_donor.php">Become a Donor</a>
    </div>

    <!-- RIGHT -->
<div class="nav-right">
  <?php if (is_logged_in()): ?>
    <a class="btn-nav" href="<?= e(APP_URL) ?>/profile.php">My Profile</a>
    <?php if ((user()['role'] ?? '') === 'admin'): ?>
      <a class="btn-nav" href="<?= e(APP_URL) ?>/admin/index.php">Admin</a>
    <?php endif; ?>
    <a class="btn-nav logout" href="<?= e(APP_URL) ?>/logout.php">Logout</a>
  <?php else: ?>
    <a class="btn-nav" href="<?= e(APP_URL) ?>/register_user.php">Sign Up</a>
    <a class="btn-nav" href="<?= e(APP_URL) ?>/login.php">Login</a>
  <?php endif; ?>
</div>

  </nav>
</header>

<div class="container">
<?php if ($m = flash('error')): ?><div class="flash error"><?= e($m) ?></div><?php endif; ?>
<?php if ($m = flash('success')): ?><div class="flash success"><?= e($m) ?></div><?php endif; ?>
