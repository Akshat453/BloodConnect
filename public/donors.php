<?php
require_once __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php'; // for is_logged_in(), user()

$bg   = trim($_GET['blood_group'] ?? '');
$city = trim($_GET['city'] ?? '');

// Show all donors + apply filters if present
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

<form method="get" class="row">
  <div class="col">
    <label>Blood Group</label>
    <select name="blood_group">
      <option value="">Any</option>
      <?php foreach (['A+','A-','B+','B-','O+','O-','AB+','AB-'] as $g): ?>
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

<!-- ✅ TABLE WITH FIXED ALIGNMENT -->
<table class="donor-table">
  <colgroup>
    <col style="width:26%">
    <col style="width:22%">
    <col style="width:14%">
    <col style="width:18%">
    <col style="width:20%">
  </colgroup>

  <thead>
    <tr>
      <th class="left">Name</th>
      <th class="left">City</th>
      <th class="center">Blood Group</th>
      <th class="center">Availability</th>
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
