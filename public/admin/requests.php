<?php
require_once __DIR__ . '/../../partials/header.php';
require_admin();
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/csrf.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($id && in_array($action, ['fulfilled','open','cancelled'])) {
        $st = db()->prepare("UPDATE requests SET status=? WHERE id=?");
        $st->execute([$action, $id]);
        flash('success','Status updated.');
        redirect('requests.php');
    } else {
        flash('error','Invalid action.');
    }
}

$rows = db()->query("SELECT * FROM requests ORDER BY created_at DESC")->fetchAll();
?>
<h2>Manage Requests</h2>
<table>
  <thead><tr><th>ID</th><th>Patient</th><th>Blood</th><th>Units</th><th>City</th><th>Urgency</th><th>Contact</th><th>Status</th><th>Action</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= e($r['id']) ?></td>
      <td><?= e($r['patient_name']) ?></td>
      <td><?= e($r['blood_group']) ?></td>
      <td><?= e($r['units']) ?></td>
      <td><?= e($r['city']) ?></td>
      <td><?= e(ucfirst($r['urgency'])) ?></td>
      <td><?= e($r['contact_phone']) ?></td>
      <td><?= e($r['status']) ?></td>
      <td>
        <form method="post" style="display:inline">
          <?php csrf_field(); ?>
          <input type="hidden" name="id" value="<?= e($r['id']) ?>">
          <button name="action" value="open">Open</button>
          <button name="action" value="fulfilled">Fulfilled</button>
          <button name="action" value="cancelled">Cancel</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
