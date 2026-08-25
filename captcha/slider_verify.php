<?php

include '../_base.php';
include '../config.php';

require_once '../PHPMailer-master/src/PHPMailer.php';
require_once '../PHPMailer-master/src/SMTP.php';
require_once '../PHPMailer-master/src/Exception.php';

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

function send_verification_email(
    string $name,
    string $email,
    string $token
): void {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        ? 'https'
        : 'http';

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $link = $scheme . '://' . $host . app_url(
        'user/verify_email.php?token=' . urlencode($token)
    );

    $safe_name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safe_link = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USERNAME;
    $mail->Password = SMTP_PASSWORD;
    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom(SMTP_USERNAME, 'Pululu Bagel');
    $mail->addAddress($email, $name);
    $mail->isHTML(true);
    $mail->Subject = 'Verify Your Email';
    $mail->Body = "
        <p>Dear {$safe_name},</p>
        <p>Click the link below to verify your Pululu Bagel account:</p>
        <p><a href=\"{$safe_link}\">Verify Email</a></p>
        <p>This link expires in 24 hours.</p>
    ";
    $mail->AltBody = "Verify your Pululu Bagel account: {$link}";
    $mail->send();
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
    $stmt = $_db->prepare(
        'SELECT * FROM user WHERE id = ? AND is_deleted = 0'
    );
    $stmt->execute([(int)($data['user_id'] ?? 0)]);
    $user = $stmt->fetch();

    if (!$user || (int)$user->email_verified !== 1) {
        clear_pending_auth();
        puzzle_response(
            false,
            'Please verify your email before logging in.',
            app_url('login.php')
        );
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

    $token = bin2hex(random_bytes(32));
    $token_hash = hash('sha256', $token);
    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

    try {
        $_db->beginTransaction();

        $stmt = $_db->prepare("
            INSERT INTO user (
                name,
                email,
                password,
                role,
                email_verified,
                verification_token,
                verification_expires
            )
            VALUES (?, ?, ?, ?, 0, ?, ?)
        ");

        $stmt->execute([
            $name,
            $email,
            $password_hash,
            'Member',
            $token_hash,
            $expires,
        ]);

        send_verification_email($name, $email, $token);
        $_db->commit();
    }
    catch (Throwable $error) {
        if ($_db->inTransaction()) {
            $_db->rollBack();
        }

        clear_pending_auth();
        temp('error', 'Verification email could not be sent. Please try again.');

        puzzle_response(
            false,
            'Verification email could not be sent.',
            app_url('user/register.php')
        );
    }

    clear_pending_auth();
    temp('info', 'Account created. Check your email to verify it.');

    puzzle_response(
        true,
        'Verification email sent.',
        app_url('login.php')
    );
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
      AND email_verified = 1
      AND is_deleted = 0
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
