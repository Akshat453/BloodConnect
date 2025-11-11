<?php
error_reporting(E_ALL); ini_set('display_errors', 1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/auth.php';

require_login(); // must be logged in

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  redirect('donors.php');
}

csrf_verify();

$blood_group = trim($_POST['blood_group'] ?? '');
$qty         = (int)($_POST['qty'] ?? 0);
$request_id  = (int)($_POST['request_id'] ?? 0); // optional – set when coming from profile "Book Now"

$valid_groups = ['A+','A-','B+','B-','O+','O-','AB+','AB-'];

if (!in_array($blood_group, $valid_groups, true)) {
  flash('error', 'Invalid blood group.');
  redirect('donors.php');
}
if ($qty <= 0) {
  flash('error', 'Quantity must be at least 1.');
  redirect('donors.php?blood_group=' . urlencode($blood_group));
}

$pdo = db();

try {
  $pdo->beginTransaction();

  // If booking against a specific request, validate it and normalize bg/qty
  if ($request_id > 0) {
    // Lock the request row
    $rq = $pdo->prepare("SELECT * FROM requests WHERE id=? AND requester_user_id=? FOR UPDATE");
    $rq->execute([$request_id, user()['id']]);
    $request = $rq->fetch();

    if (!$request) {
      throw new Exception('Request not found or not yours.');
    }
    if ($request['status'] !== 'open') {
      throw new Exception('This request is not open anymore.');
    }

    // Force BG/qty to match the request to avoid mismatch from tampered form
    $blood_group = $request['blood_group'];
    $qty         = (int)$request['units'];
    if ($qty <= 0) {
      throw new Exception('Requested units must be positive.');
    }
  }

  // Lock N available donors of this group (1 donor = 1 unit)
  $sel = $pdo->prepare(
    "SELECT id
       FROM donors
      WHERE blood_group = ? AND availability = 1
      ORDER BY created_at ASC
      LIMIT $qty
      FOR UPDATE"
  );
  $sel->execute([$blood_group]);
  $donorIds = $sel->fetchAll(PDO::FETCH_COLUMN);

  if (count($donorIds) < $qty) {
    throw new Exception('Not enough available donors for ' . $blood_group . '.');
  }

  // Create booking (NOW() handled by DB default too, but explicit is fine)
  if ($request_id > 0) {
    $ins = $pdo->prepare(
      "INSERT INTO bookings (request_id, requester_user_id, blood_group, qty, status, created_at)
       VALUES (?, ?, ?, ?, 'reserved', NOW())"
    );
    $ins->execute([$request_id, user()['id'], $blood_group, $qty]);
  } else {
    $ins = $pdo->prepare(
      "INSERT INTO bookings (requester_user_id, blood_group, qty, status, created_at)
       VALUES (?, ?, ?, 'reserved', NOW())"
    );
    $ins->execute([user()['id'], $blood_group, $qty]);
  }
  $bookingId = (int)$pdo->lastInsertId();

  // Map donors to booking
  $bd = $pdo->prepare("INSERT INTO booking_donors (booking_id, donor_id) VALUES (?, ?)");
  foreach ($donorIds as $did) {
    $bd->execute([$bookingId, $did]);
  }

  // Mark those donors unavailable
  $placeholders = implode(',', array_fill(0, count($donorIds), '?'));
  $upd = $pdo->prepare("UPDATE donors SET availability = 0 WHERE id IN ($placeholders)");
  $upd->execute($donorIds);

  // If we booked for a specific request, mark it booked so it disappears from index "Recent Requests"
  if ($request_id > 0) {
    $pdo->prepare("UPDATE requests SET status='booked' WHERE id=?")->execute([$request_id]);
  }

  $pdo->commit();

  flash('success', "Booked $qty unit(s) of $blood_group successfully.");
  // If we came from a specific request, head back to profile; else stay on donors page with filter kept
  if ($request_id > 0) {
    redirect('profile.php');
  } else {
    redirect('donors.php?blood_group=' . urlencode($blood_group));
  }

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  // Surface a useful message in dev; generic in prod if you prefer
  flash('error', $e->getMessage() ?: 'Booking failed. Please try again.');
  if ($request_id > 0) {
    redirect('profile.php');
  } else {
    redirect('donors.php?blood_group=' . urlencode($blood_group));
  }
}
