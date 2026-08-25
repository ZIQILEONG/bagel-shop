<?php

include '../_base.php';
include '../config.php';

header('Content-Type: application/json; charset=utf-8');

function puzzle_response(
    bool $success,
    string $message,
    string $redirect_url = ''
): void {
    http_response_code($success ? 200 : 400);

    echo json_encode([
        'success' => $success,
        'message' => $message,
        'redirect' => $redirect_url,
    ]);

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    puzzle_response(false, 'POST request required.');
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    puzzle_response(false, 'Invalid puzzle request.');
}

$action = (string)($input['action'] ?? '');
$nonce = (string)($input['nonce'] ?? '');
$trail = $input['trail'] ?? [];
$position = $input['position'] ?? null;
$target = $input['target'] ?? null;
$pending = get_pending_auth($action);

if (!$pending ||
    !in_array($action, ['login', 'register', 'remember'], true)) {

    puzzle_response(
        false,
        'Verification expired. Please start again.',
        app_url('login.php')
    );
}

if (!validate_captcha_nonce($nonce, $action) ||
    !verify_slider_data($trail, $position, $target)) {

    puzzle_response(false, 'Puzzle verification failed. Try again.');
}

unset($_SESSION['captcha_nonces'][hash('sha256', $nonce)]);
$data = $pending['data'] ?? [];

if ($action === 'login') {
    $stmt = $_db->prepare('SELECT * FROM user WHERE id = ?');
    $stmt->execute([(int)($data['user_id'] ?? 0)]);
    $user = $stmt->fetch();

    if (!$user) {
        clear_pending_auth();
        puzzle_response(false, 'Account not found.', app_url('login.php'));
    }

    if (!empty($data['remember'])) {
        $token = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $token);
        $expires_at = time() + (30 * 24 * 60 * 60);
        $expires = date('Y-m-d H:i:s', $expires_at);

        $stmt = $_db->prepare("
            UPDATE user
            SET remember_token = ?,
                remember_expires = ?
            WHERE id = ?
        ");

        $stmt->execute([$token_hash, $expires, $user->id]);

        setcookie('remember_token', $token, [
            'expires' => $expires_at,
            'path' => '/',
            'httponly' => true,
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'samesite' => 'Lax',
        ]);
    }
    else {
        clear_remember_cookie();

        $stmt = $_db->prepare("
            UPDATE user
            SET remember_token = NULL,
                remember_expires = NULL
            WHERE id = ?
        ");

        $stmt->execute([$user->id]);
    }

    set_logged_in_user($user);
    temp('info', 'Login successfully');

    puzzle_response(true, 'Login successful.', app_url('index.php'));
}

if ($action === 'register') {
    $name = (string)($data['name'] ?? '');
    $email = (string)($data['email'] ?? '');
    $password_hash = (string)($data['password_hash'] ?? '');

    if ($name === '' || $email === '' || $password_hash === '') {
        clear_pending_auth();
        puzzle_response(
            false,
            'Registration expired. Please try again.',
            app_url('user/register.php')
        );
    }

    if (is_exists($email, 'user', 'email')) {
        clear_pending_auth();
        puzzle_response(
            false,
            'Email already exists.',
            app_url('user/register.php')
        );
    }

    $stmt = $_db->prepare("
        INSERT INTO user (name, email, password, role)
        VALUES (?, ?, ?, ?)
    ");

    $stmt->execute([$name, $email, $password_hash, 'Member']);

    clear_pending_auth();
    temp('info', 'Registration successful. Please login.');

    puzzle_response(true, 'Registration successful.', app_url('login.php'));
}

if (!isset($_COOKIE['remember_token'])) {
    clear_pending_auth();
    puzzle_response(
        false,
        'Remember Me expired. Please login again.',
        app_url('login.php')
    );
}

$token_hash = hash('sha256', $_COOKIE['remember_token']);

$stmt = $_db->prepare("
    SELECT *
    FROM user
    WHERE id = ?
      AND remember_token = ?
      AND remember_expires > NOW()
");

$stmt->execute([
    (int)($data['user_id'] ?? 0),
    $token_hash,
]);

$user = $stmt->fetch();

if (!$user) {
    clear_pending_auth();
    clear_remember_cookie();
    puzzle_response(
        false,
        'Remember Me expired. Please login again.',
        app_url('login.php')
    );
}

set_logged_in_user($user);
temp('info', 'Login successfully');

puzzle_response(true, 'Login successful.', app_url('index.php'));
