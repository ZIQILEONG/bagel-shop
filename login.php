<?php

include '_base.php';

if (is_post()) {

    $email = req('email');
    $password = req('password');

    // Validate email
    if ($email == '') {
        $_err['email'] = 'Required';
    }
    else if (!is_email($email)) {
        $_err['email'] = 'Invalid email';
    }

    // Validate password
    if ($password == '') {
        $_err['password'] = 'Required';
    }

    // Login user
    if (!$_err) {
        $stmt = $_db->prepare("
            SELECT *
            FROM user
            WHERE email = ?
        ");

        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user->password)) {
            temp('info', 'Login successfully');
            login($user);
        }
        else {
            $_err['password'] = 'Password is incorrect, please try again!';
        }
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

        <button type="submit">LOGIN</button>

    </form>

    <p class="register-text">
        <a href="user/forgot_password.php">Forgot Password?</a> <br>
        Don't have an account?
        <a href="user/register.php">Create Account</a>
    </p>

</div>

<?php
include '_foot.php';
?>

