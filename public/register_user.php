<?php
error_reporting(E_ALL); 
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/auth.php';

// ---- Handle POST BEFORE output ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name   = trim($_POST['name'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $pass   = $_POST['password'] ?? '';
    $pass2  = $_POST['password_confirm'] ?? '';

    $errors = validate_required($_POST, ['name','email','password','password_confirm']);
    if (!valid_email($email)) $errors['email'] = 'Invalid email';
    if ($pass !== $pass2)     $errors['password'] = 'Passwords do not match';

    if (empty($errors)) {
        if (find_user_by_email($email)) {
            flash('error','Email already registered.');
            redirect('register_user.php');
        } else {
            create_user($name, $email, $pass, 'requester');

            flash('success','Account created successfully!');
            redirect('index.php');   // ✅ Redirect directly to homepage
        }
    } else {
        flash('error','Please correct the errors & try again.');
        redirect('register_user.php');
    }
}

// ---- Show Page ----
require_once __DIR__ . '/../partials/header.php';
?>

<h2>Sign Up</h2>
<form method="post">
  <?php csrf_field(); ?>
  <label>Name</label>
  <input type="text" name="name" required>

  <label>Email</label>
  <input type="email" name="email" required>

  <label>Password</label>
  <input type="password" name="password" required>

  <label>Confirm Password</label>
  <input type="password" name="password_confirm" required>

  <div class="form-actions">
    <button type="submit">Register</button>
  </div>
</form>

<p class="muted" style="margin-top:10px">
  Already have an account? <a href="login.php">Login</a>
</p>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
