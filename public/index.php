<?php
require_once __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../app/db.php';

$stats = ['donors'=>0,'cities'=>0,'requests'=>0];
$recent = [];
try {
  $pdo = db();
  // counts (no approval gate)
  $stats['donors'] = (int)$pdo->query("SELECT COUNT(*) c FROM donors")->fetch()['c'];
  $stats['cities']  = (int)$pdo->query("SELECT COUNT(DISTINCT city) c FROM donors")->fetch()['c'];
  $stats['requests'] = (int)$pdo->query("SELECT COUNT(*) c FROM requests WHERE status='open'")->fetch()['c'];

  $st = $pdo->query("SELECT id, patient_name, blood_group, city, urgency, units, created_at
                     FROM requests WHERE status='open'
                     ORDER BY created_at DESC LIMIT 5");
  $recent = $st->fetchAll();
} catch (Throwable $e) { /* DB not ready or tables missing */ }
?>

<!-- Hero -->
<section class="hero card">
  <h1 class="hero-title">Welcome to <?= e(APP_NAME) ?></h1>
  <p class="lead">Find life-saving blood donors quickly by filtering blood group and city.</p>
  <div class="cta-row">
    <a class="btn" href="register_donor.php">Become a Donor</a>
    <a class="btn btn-outline" href="request_blood.php">Request Blood</a>
  </div>
</section>

<!-- Search -->
<section class="card">
  <h3 class="section-title">Search Donors</h3>
  <form action="donors.php" method="get" class="row">
    <div class="col">
      <label>Blood Group</label>
      <select name="blood_group">
        <option value="">Any</option>
        <option>A+</option><option>A-</option>
        <option>B+</option><option>B-</option>
        <option>O+</option><option>O-</option>
        <option>AB+</option><option>AB-</option>
      </select>
    </div>
    <div class="col">
      <label>City</label>
      <input type="text" name="city" placeholder="e.g., Mumbai">
    </div>
    <div class="btn-col">
      <button type="submit" class="btn">Search Donors</button>
    </div>
  </form>
</section>

<!-- Quick stats -->
<section class="grid mt">
  <div class="stat card">
    <div class="stat-num"><?= e($stats['donors']) ?></div>
    <div class="stat-label">Donors</div>
  </div>
  <div class="stat card">
    <div class="stat-num"><?= e($stats['cities']) ?></div>
    <div class="stat-label">Cities Covered</div>
  </div>
  <div class="stat card">
    <div class="stat-num"><?= e($stats['requests']) ?></div>
    <div class="stat-label">Open Requests</div>
  </div>
</section>

<!-- ⭐️ Features (restored) -->
<section class="grid mt">
  <div class="feature card">
    <h4>Fast Search</h4>
    <p class="muted">Filter by blood group and city to find matching donors instantly.</p>
  </div>
  <div class="feature card">
    <h4>Verified Donors</h4>
    <p class="muted">Admin approval ensures only trusted donor profiles are listed.</p>
  </div>
  <div class="feature card">
    <h4>Privacy First</h4>
    <p class="muted">Phone numbers are masked for guests. Full details on login.</p>
  </div>
</section>

<!-- Recent requests -->
<?php if (!empty($recent)): ?>
<section class="card mt">
  <h3 class="section-title">Recent Requests</h3>
  <div class="list">
    <?php foreach ($recent as $r): ?>
      <div class="list-item">
        <div class="list-main">
          <span class="pill"><?= e($r['blood_group']) ?></span>
          <strong><?= e($r['patient_name']) ?></strong>
          <span class="muted">needs <?= e($r['units']) ?> unit(s) • <?= e($r['city']) ?></span>
        </div>
        <span class="badge <?= e($r['urgency']) ?>"><?= ucfirst($r['urgency']) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="right mt-s">
    <a class="btn btn-outline" href="request_blood.php">Create a Request</a>
  </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
