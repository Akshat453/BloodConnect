<?php
require_once __DIR__ . '/db.php';

function find_user_by_email($email) {
    $sql = "SELECT id, name, email, password_hash, role, status FROM users WHERE email = ?";
    $st = db()->prepare($sql);
    $st->execute([$email]);
    return $st->fetch();
}

function create_user($name, $email, $password, $role = 'requester') {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (name, email, password_hash, role, status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())";
    $st = db()->prepare($sql);
    $st->execute([$name, $email, $hash, $role]);
    return db()->lastInsertId();
}

function login_user($user) {
    $_SESSION['user'] = [
        'id' => $user['id'],
        'name' => $user['name'] ?? '',
        'email' => $user['email'],
        'role' => $user['role'],
        'status' => $user['status'] ?? 'active',
    ];
}

function find_user_by_id($id) {
    $st = db()->prepare("SELECT id, name, email, role, status FROM users WHERE id = ?");
    $st->execute([$id]);
    return $st->fetch();
}
