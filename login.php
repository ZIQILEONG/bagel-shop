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
            AND is_deleted = 0
        ");

        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user->password)) {

            // Remember Me
            if (isset($_POST['remember'])) {

                // Generate secure random token
                $token = bin2hex(random_bytes(32));

                // Token expires after 30 days
                $expires = date(
                    'Y-m-d H:i:s',
                    time() + (30 * 24 * 60 * 60)
                );

                // Save token in database
                $stmt = $_db->prepare("
                    UPDATE user
                    SET remember_token = ?,
                        remember_expires = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $token,
                    $expires,
                    $user->id
                ]);

                // Save token in browser cookie
                setcookie(
                    'remember_token',
                    $token,
                    [
                        'expires' => time() + (30 * 24 * 60 * 60),
                        'path' => '/',
                        'httponly' => true,
                        'secure' => isset($_SERVER['HTTPS']),
                        'samesite' => 'Lax'
                    ]
                );
            }
            else {

                // Remove old Remember Me cookie
                setcookie(
                    'remember_token',
                    '',
                    time() - 3600,
                    '/'
                );

                // Remove old token from database
                $stmt = $_db->prepare("
                    UPDATE user
                    SET remember_token = NULL,
                        remember_expires = NULL
                    WHERE id = ?
                ");

                $stmt->execute([$user->id]);
            }

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

        <label>
            <input type="checkbox" name="remember" value="1">
            Remember me
        </label>

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