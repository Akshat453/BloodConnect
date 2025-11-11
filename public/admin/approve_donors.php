<?php
require_once __DIR__ . '/../../partials/header.php';
require_admin();
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/csrf.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $user_id = (int)($_POST['user_id'] ?? 0);
    $action  = $_POST['action'] ?? '';

    if ($user_id && in_array($action, ['approve','deactivate','activate'])) {
        if ($action === 'approve') {
            $st = db()->prepare("UPDATE donors SET is_approved=1 WHERE user_id=?");
            $st->execute([$user_id]);
        } elseif ($action === 'deactivate') {
            $st = db()->prepare("UPDATE users SET status='inactive' WHERE id=?");
            $st->execute([$user_id]);
        } else {
            $st = db()->prepare("UPDATE users SET status='active' WHERE id=?");
            $st->execute([$user_id]);
        }
        flash('success', 'Action completed.');
        redirect('approve_donors.php');
    } else {
        flash('error','Invalid action.');
    }
}

$rows = db()->query("SELECT u.id as user_id, u.name, u.email, u.status, d.city, d.blood_group, d.is_approved
                     FROM donors d JOIN users u ON u.id=d.user_id
                     ORDER BY d.is_approved ASC, u.name ASC")->fetchAll();
?>
<h2>Approve Donors</h2>
<table>
  <thead><tr><th>Name</th><th>Email</th><th>City</th><th>Blood</th><th>Approved</th><th>Status</th><th>Action</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= e($r['name']) ?></td>
      <td><?= e($r['email']) ?></td>
      <td><?= e($r['city']) ?></td>
      <td><?= e($r['blood_group']) ?></td>
      <td><?= $r['is_approved'] ? 'Yes' : 'No' ?></td>
      <td><?= e($r['status']) ?></td>
      <td>
        <form method="post" style="display:inline">
            <?php csrf_field(); ?>
            <input type="hidden" name="user_id" value="<?= e($r['user_id']) ?>">
            <?php if (!$r['is_approved']): ?>
              <button name="action" value="approve">Approve</button>
            <?php endif; ?>
            <?php if ($r['status'] === 'active'): ?>
              <button name="action" value="deactivate">Deactivate</button>
            <?php else: ?>
              <button name="action" value="activate">Activate</button>
            <?php endif; ?>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
