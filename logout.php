<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (isset($_SESSION['admin_private_key'])) {
    $_SESSION['admin_private_key'] = str_repeat('0', strlen((string) $_SESSION['admin_private_key']));
    unset($_SESSION['admin_private_key']);
}

unset(
    $_SESSION['auth_mode'],
    $_SESSION['failed_crypto_meta'],
    $_SESSION['uploaded_key_name'],
    $_SESSION['login_time'],
    $_SESSION['key_fingerprint']
);
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_destroy();
header('Location: login.php?status=logged_out');
exit;