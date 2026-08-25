<?php

include '../_base.php';
include '../config.php';

require_once '../PHPMailer-master/src/PHPMailer.php';
require_once '../PHPMailer-master/src/SMTP.php';
require_once '../PHPMailer-master/src/Exception.php';

$email = '';
$success = '';

function resend_verification_email(
    string $name,
    string $email,
    string $token
): void {

    $scheme = (!empty($_SERVER['HTTPS']) &&
               $_SERVER['HTTPS'] !== 'off')
        ? 'https'
        : 'http';

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $link = $scheme . '://' . $host . app_url(
        'user/verify_email.php?token=' . urlencode($token)
    );

    $safe_name = htmlspecialchars(
        $name,
        ENT_QUOTES,
        'UTF-8'
    );

    $safe_link = htmlspecialchars(
        $link,
        ENT_QUOTES,
        'UTF-8'
    );

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USERNAME;
    $mail->Password = SMTP_PASSWORD;
    $mail->SMTPSecure =
        \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom(
        SMTP_USERNAME,
        'Pululu Bagel'
    );

    $mail->addAddress($email, $name);

    $mail->isHTML(true);
    $mail->Subject = 'New Email Verification Link';

    $mail->Body = "
        <p>Dear {$safe_name},</p>
        <p>
            Click the link below to verify your
            Pululu Bagel account:
        </p>
        <p>
            <a href=\"{$safe_link}\">
                Verify Email
            </a>
        </p>
        <p>This link expires in 24 hours.</p>";

    $mail->AltBody =
        "Verify your Pululu Bagel account: {$link}";
    $mail->send();
}

if (is_post()) {
    $email = trim($_POST['email'] ?? '');
    $turnstile_token =
        $_POST['cf-turnstile-response'] ?? '';

    // Validate email
    if ($email === '') {
        $_err['email'] = 'Required';
    }
    else if (!is_email($email)) {
        $_err['email'] = 'Invalid email';
    }

    // Find user
    if (!$_err) {

        $stmt = $_db->prepare("
            SELECT *
            FROM user
            WHERE email = ?
              AND is_deleted = 0
        ");

        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $_err['email'] =
                'Account not found. Please register.';
        }
        else if ((int)$user->email_verified === 1) {
            $_err['email'] =
                'Email already verified. Please login.';
        }
    }

    // Check Turnstile
    if (!$_err &&
        !verify_turnstile(
            $turnstile_token,
            'resend'
        )) {
        $_err['captcha'] =
            'Please complete the Turnstile verification.';
    }

    // Send new verification email
    if (!$_err) {

        $token = bin2hex(random_bytes(32));
        $token_hash = hash(
            'sha256',
            $token
        );
        $expires = date(
            'Y-m-d H:i:s',
            strtotime('+24 hours')
        );
        try {
            $_db->beginTransaction();
            $stmt = $_db->prepare("
                UPDATE user
                SET verification_token = ?,
                    verification_expires = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $token_hash,
                $expires,
                $user->id
            ]);

            resend_verification_email(
                $user->name,
                $user->email,
                $token
            );

            $_db->commit();
            $success =
                'A new verification email has been sent.';
        }
        catch (Throwable $error) {

            if ($_db->inTransaction()) {
                $_db->rollBack();
            }

            $_err['email'] =
                'Email could not be sent. Please try again.';
        }
    }
}

$_title = 'Resend Verification Email';

include '../_head.php';
?>

<div class="forgot-container">

    <h2>Resend Verification Email</h2>

    <p class="forgot-subtitle">
        Enter the email used to register your account.
    </p>

    <?php if ($success): ?>

        <p><?= encode($success) ?></p>

    <?php endif; ?>

    <form method="post" class="forgot-form">

        <div class="input-group">

            <?= html_text(
                'email',
                'maxlength="100" required placeholder="E-mail"'
            ) ?>

            <label for="email">E-mail</label>

            <?= err('email') ?>

        </div>

        <?php
        $captcha_action = 'resend';
        $captcha_web_path = '../captcha';

        include '../captcha/widget.php';
        ?>

        <button type="submit">
            SEND NEW LINK
        </button>

    </form>

    <p class="back-login-text">

        <a href="../login.php">
            Back to Login
        </a>

    </p>

</div>

<?php include '../_foot.php'; ?>