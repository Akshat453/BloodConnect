<?php

function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function redirect(string $to) {
    // If a relative path was passed, make it absolute to avoid header issues from subfolders
    if (strpos($to, 'http') !== 0) {
        $base = rtrim(APP_URL, '/'); // e.g. http://localhost/bloodconnect/public
        $to = $base . '/' . ltrim($to, '/');
    }
    header('Location: ' . $to);
    exit;
}


function flash($key, $msg = null) {
    if ($msg === null) {
        if (isset($_SESSION['flash'][$key])) {
            $m = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
            return $m;
        }
        return null;
    } else {
        $_SESSION['flash'][$key] = $msg;
    }
}

function is_logged_in() {
    return !empty($_SESSION['user']);
}

function user() {
    return $_SESSION['user'] ?? null;
}

function require_login() {
    if (!is_logged_in()) {
        flash('error', 'Please login first.');
        redirect('login.php');
    }
}

function require_admin() {
    require_login();
    if ((user()['role'] ?? '') !== 'admin') {
        http_response_code(403);
        exit('Forbidden');
    }
}

function valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validate_required($arr, $fields) {
    $errors = [];
    foreach ($fields as $f) {
        if (!isset($arr[$f]) || trim($arr[$f]) === '') {
            $errors[$f] = 'Required';
        }
    }
    return $errors;
}

function mask_phone($phone) {
    // Show last 3 digits
    $len = strlen($phone);
    if ($len <= 3) return $phone;
    return str_repeat('•', max(0, $len-3)) . substr($phone, -3);
}
