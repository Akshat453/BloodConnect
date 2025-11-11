<?php
require_once __DIR__ . '/../../partials/header.php';
require_admin();

// quick stats
require_once __DIR__ . '/../../app/db.php';
$totDonors = db()->query("SELECT COUNT(*) c FROM donors")->fetch()['c'] ?? 0;
$pending   = db()->query("SELECT COUNT(*) c FROM donors WHERE is_approved=0")->fetch()['c'] ?? 0;
$requests  = db()->query("SELECT COUNT(*) c FROM requests")->fetch()['c'] ?? 0;
?>
<h2>Admin Dashboard</h2>
<div class="row">
  <div class="col"><div class="flash">Total Donors: <b><?= e($totDonors) ?></b></div></div>
  <div class="col"><div class="flash">Pending Approvals: <b><?= e($pending) ?></b></div></div>
  <div class="col"><div class="flash">Requests: <b><?= e($requests) ?></b></div></div>
</div>
<p>
  <a href="approve_donors.php"><button>Approve Donors</button></a>
  <a href="requests.php"><button>Manage Requests</button></a>
</p>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
