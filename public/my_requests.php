<?php
require_once __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../app/db.php';
require_login();

$u = user();
$st = db()->prepare("SELECT * FROM requests WHERE requester_user_id = ? ORDER BY created_at DESC");
$st->execute([$u['id']]);
$rows = $st->fetchAll();
?>
<h2>My Requests</h2>
<table>
  <thead><tr><th>Patient</th><th>Blood</th><th>Units</th><th>City</th><th>Urgency</th><th>Status</th><th>Created</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= e($r['patient_name']) ?></td>
      <td><?= e($r['blood_group']) ?></td>
      <td><?= e($r['units']) ?></td>
      <td><?= e($r['city']) ?></td>
      <td><?= e(ucfirst($r['urgency'])) ?></td>
      <td><?= e($r['status']) ?></td>
      <td><?= e($r['created_at']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
