<?php
include '../_base.php';
include '../config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $reenter_password = trim($_POST['reenter_password']);
    $captcha = $_POST['cf-turnstile-response'] ?? '';

    if ($name == "" || $email == "" || $password == "" || $reenter_password == "") {
        echo "Please fill in all fields.";
    }
    else if ($password != $reenter_password) {
        echo "Password do not match!";
    }
    else if ($captcha == '') {
        echo "Please complete the CAPTCHA.";
    }
    else if (is_exists($email, 'user', 'email')) {
        echo "Email already exists.";
    }
    else {

        $data = [
            'secret' => TURNSTILE_SECRET_KEY,
            'response' => $captcha
        ];

        $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);

        if (!$result['success']) {
            echo "CAPTCHA verification failed.";
        }
        else {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stm = $_db->prepare(
                "INSERT INTO user (name, email, password, role)
                 VALUES (?, ?, ?, ?)"
            );

            $stm->execute([$name, $email, $hash, 'Member']);

            echo "Register successful!";
        }
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

        <div class="cf-turnstile" data-sitekey="<?= TURNSTILE_SITE_KEY ?>"></div>

        <button type="submit">CREATE ACCOUNT</button>

    </form>

    <p class="login-text">
        Already have an account?
        <a href="../login.php">Login</a><br>
    </p>

</div>

<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

<?php
include '../_foot.php';
?>