<?php

include '../_base.php';
include '../config.php';

$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password_input = $_POST['password'] ?? '';
    $reenter_password_input = $_POST['reenter_password'] ?? '';
    $turnstile_token = $_POST['cf-turnstile-response'] ?? '';

    if ($name === '') {
        $_err['name'] = 'Required';
    }

    if ($email === '') {
        $_err['email'] = 'Required';
    }
    else if (!is_email($email)) {
        $_err['email'] = 'Invalid email';
    }

    if ($password_input === '') {
        $_err['password'] = 'Required';
    }

    if ($reenter_password_input === '') {
        $_err['reenter_password'] = 'Required';
    }
    else if ($password_input !== $reenter_password_input) {
        $_err['reenter_password'] = 'Passwords do not match';
    }

    if (!$_err && is_exists($email, 'user', 'email')) {
        $_err['email'] = 'Email already exists';
    }

    if (!$_err && !verify_turnstile($turnstile_token, 'register')) {
        $_err['captcha'] = 'Please complete the Turnstile verification.';
    }

    if (!$_err) {
        begin_pending_auth('register', [
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password_input, PASSWORD_DEFAULT),
        ]);

        redirect('../captcha/puzzle.php');
    }
}

$_title = 'User | Register';
include '../_head.php';
?>

<div class="register-container">

    <h2>Register</h2>

    <p class="register-subtitle">
        Please fill in the fields below:
    </p>

    <form method="post" class="register-form">

        <div class="input-group">
            <?= html_text('name', 'maxlength="50" required placeholder="Name"') ?>
            <label for="name">Name</label>
            <?= err('name') ?>
        </div>

        <div class="input-group">
            <?= html_text('email', 'maxlength="100" required placeholder="E-mail"') ?>
            <label for="email">E-mail</label>
            <?= err('email') ?>
        </div>

        <div class="input-group">
            <?= html_password('password', 'maxlength="100" required placeholder="Password"') ?>
            <label for="password">Password</label>
            <?= err('password') ?>
        </div>

        <div class="input-group">
            <?= html_password('reenter_password', 'maxlength="100" required placeholder="Re-enter Password"') ?>
            <label for="reenter_password">Re-enter Password</label>
            <?= err('reenter_password') ?>
        </div>

        <?php
        $captcha_action = 'register';
        $captcha_web_path = '../captcha';
        include '../captcha/widget.php';
        ?>

        <button type="submit">CREATE ACCOUNT</button>

    </form>

    <p class="login-text">
        Already have an account?
        <a href="../login.php">Login</a>
    </p>

</div>

<?php include '../_foot.php'; ?>
