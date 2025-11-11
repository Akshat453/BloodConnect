<?php
error_reporting(E_ALL); ini_set('display_errors', 1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/auth.php';

// Optional: support ?next=page.php so you can bounce back after login
$next = basename($_GET['next'] ?? $_POST['next'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if (!valid_email($email) || $pass === '') {
        flash('error','Invalid credentials.');
        redirect('login.php' . ($next ? '?next='.$next : ''));
    }

    $u = find_user_by_email($email);
    if (!$u || !password_verify($pass, $u['password_hash'])) {
        flash('error','Invalid credentials.');
        redirect('login.php' . ($next ? '?next='.$next : ''));
    }
    if (($u['status'] ?? 'active') !== 'active') {
        flash('error','Account is not active.');
        redirect('login.php' . ($next ? '?next='.$next : ''));
    }

    // success
    login_user($u);
    flash('success','Logged in!');

    // ⬇️ This sends to the home page (index.php). If you truly have index.html, change it here
    redirect($next ?: 'index.php');
}

require_once __DIR__ . '/../partials/header.php';
?>
<h2>Login</h2>
<form method="post">
  <?php csrf_field(); ?>
  <input type="hidden" name="next" value="<?= e($next) ?>">
  <label>Email</label>
  <input type="email" name="email" required>

  <label>Password</label>
  <input type="password" name="password" required>

  <div class="form-actions">
    <button type="submit">Login</button>
  </div>
</form>
<p class="muted" style="margin-top:10px">
  Don’t have an account? <a href="register_user.php<?= $next ? '?next='.e($next) : '' ?>">Sign up</a>
</p>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
