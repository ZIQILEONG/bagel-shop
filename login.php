<?php

include '_base.php';
include 'config.php';

$email = '';

if (is_post()) {
    $email = trim($_POST['email'] ?? '');
    $password_input = $_POST['password'] ?? '';
    $turnstile_token = $_POST['cf-turnstile-response'] ?? '';

    if ($email === '') {
        $_err['email'] = 'Required';
    }
    else if (!is_email($email)) {
        $_err['email'] = 'Invalid email';
    }

    if ($password_input === '') {
        $_err['password'] = 'Required';
    }

    if (!$_err) {
        $stmt = $_db->prepare("
            SELECT *
            FROM user
            WHERE email = ?
            AND is_deleted = 0
        ");

        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password_input, $user->password)) {
            $_err['password'] = 'Email or password is incorrect.';
        }
    }

    if (!$_err && !verify_turnstile($turnstile_token, 'login')) {
        $_err['captcha'] = 'Please complete the Turnstile verification.';
    }

    if (!$_err) {
        begin_pending_auth('login', [
            'user_id' => (int)$user->id,
            'remember' => isset($_POST['remember']),
        ]);

        redirect('captcha/puzzle.php');
    }
}

$_title = 'Login';
include '_head.php';
?>

<div class="login-container">

    <h2>Login</h2>

    <p class="login-subtitle">
        Welcome back! Please login to your account:
    </p>

    <form method="post" class="login-form">

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

        <label>
            <input type="checkbox" name="remember" value="1"
                <?= isset($_POST['remember']) ? 'checked' : '' ?>>
            Remember me
        </label>

        <?php
        $captcha_action = 'login';
        $captcha_web_path = 'captcha';
        include 'captcha/widget.php';
        ?>

        <button type="submit">LOGIN</button>

    </form>

    <p class="register-text">
        <a href="user/forgot_password.php">Forgot Password?</a><br>
        Don't have an account?
        <a href="user/register.php">Create Account</a>
    </p>

</div>

<?php include '_foot.php'; ?>
