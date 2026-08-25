<?php

include '../_base.php';
include '../config.php';

if (is_post() && isset($_POST['cancel_remember'])) {
    if (isset($_SESSION['remember_pending_user_id'], $_COOKIE['remember_token'])) {
        $stmt = $_db->prepare("
            UPDATE user
            SET remember_token = NULL,
                remember_expires = NULL
            WHERE id = ?
              AND remember_token = ?
        ");

        $stmt->execute([
            $_SESSION['remember_pending_user_id'],
            hash('sha256', $_COOKIE['remember_token']),
        ]);
    }

    clear_remember_cookie();
    redirect('../login.php');
}

if (!isset($_SESSION['remember_pending_user_id']) ||
    !isset($_COOKIE['remember_token'])) {

    redirect('../login.php');
}

if (is_post()) {
    $turnstile_token = $_POST['cf-turnstile-response'] ?? '';

    if (!verify_turnstile($turnstile_token, 'remember')) {
        $_err['captcha'] = 'Please complete the Turnstile verification.';
    }

    if (!$_err) {
        begin_pending_auth('remember', [
            'user_id' => (int)$_SESSION['remember_pending_user_id'],
        ]);

        redirect('../captcha/puzzle.php');
    }
}

$_title = 'Verify to Continue';
include '../_head.php';
?>

<div class="login-container">

    <h2>Verify to Continue</h2>

    <p class="login-subtitle">
        Your account is remembered. Complete Turnstile to continue.
    </p>

    <form method="post" class="login-form">

        <?php
        $captcha_action = 'remember';
        $captcha_web_path = '../captcha';
        include '../captcha/widget.php';
        ?>

        <button type="submit">CONTINUE</button>

    </form>

    <form method="post" class="register-text">
        <button type="submit" name="cancel_remember" value="1">
            Use another account
        </button>
    </form>

</div>

<?php include '../_foot.php'; ?>
