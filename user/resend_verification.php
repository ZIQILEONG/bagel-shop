<?php

include '../_base.php';
include '../config.php';

require_once '../PHPMailer-master/src/PHPMailer.php';
require_once '../PHPMailer-master/src/SMTP.php';
require_once '../PHPMailer-master/src/Exception.php';

$email = '';
$success = '';
$_err = [];

function resend_verification_email(
    string $name,
    string $email,
    string $token
): void {
    $scheme = (
        !empty($_SERVER['HTTPS']) &&
        $_SERVER['HTTPS'] !== 'off'
    ) ? 'https' : 'http';

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
    $mail->CharSet = 'UTF-8';

    $mail->Subject =
        'Verify Your Pululu Bagel Account';

    $mail->Body = "
    <div style='
        font-family: Arial, sans-serif;
        background-color: #fff8f0;
        padding: 30px;
    '>
        <div style='
            max-width: 600px;
            margin: auto;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 12px;
            border: 1px solid #ead8c8;
        '>

            <h2 style='
                color: #b5192b;
                text-align: center;
            '>
                Welcome to Pululu Bagel!
            </h2>

            <p>
                Dear <strong>{$safe_name}</strong>,
            </p>

            <p>
                Thank you for registering with Pululu Bagel.
                Please verify your email address by clicking the
                button below.
            </p>

            <p style='text-align: center; margin: 30px 0;'>
                <a href='{$safe_link}' style='
                    background-color: #b5192b;
                    color: #ffffff;
                    padding: 13px 25px;
                    text-decoration: none;
                    border-radius: 6px;
                    display: inline-block;
                    font-weight: bold;
                '>
                    Verify My Email
                </a>
            </p>

            <p>
                This verification link will expire in
                <strong>5 minutes</strong>.
            </p>

            <p>
                If you did not create a Pululu Bagel account,
                you can safely ignore this email.
            </p>

            <p style='
                color: #777777;
                font-size: 13px;
            '>
                For your security, please do not share this
                verification link with anyone.
            </p>

            <p>
                Thank you,<br>
                <strong>Pululu Bagel Team</strong>
            </p>

        </div>
    </div>
    ";

    $mail->AltBody =
        "Dear {$name},\n\n" .
        "Thank you for registering with Pululu Bagel.\n\n" .
        "Please verify your email using this link:\n" .
        "{$link}\n\n" .
        "This verification link will expire in 5 minutes.\n\n" .
        "Pululu Bagel Team";

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


    // Find unverified user
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
    if (
        !$_err &&
        !verify_turnstile(
            $turnstile_token,
            'resend'
        )
    ) {
        $_err['captcha'] =
            'Please complete the Turnstile verification.';
    }
    // Generate and send new verification email
    if (!$_err) {

        $token = bin2hex(
            random_bytes(32)
        );

        $token_hash = hash(
            'sha256',
            $token
        );

        // The verification link expires after 5 minutes
        $expires = date(
            'Y-m-d H:i:s',
            strtotime('+5 minutes')
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
                'A new verification email has been sent. '
                . 'Please check your inbox.';

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
        Enter the email address used to register your account.
    </p>

    <?php if ($success): ?>

        <p class="success-message">
            <?= encode($success) ?>
        </p>

    <?php endif; ?>

    <form method="post" class="forgot-form">

        <div class="input-group">

            <?= html_text(
                'email',
                'maxlength="100"
                 required
                 placeholder="E-mail"'
            ) ?>

            <label for="email">
                E-mail
            </label>

            <?= err('email') ?>

        </div>

        <?php

        $captcha_action = 'resend';
        $captcha_web_path = '../captcha';

        include '../captcha/widget.php';

        ?>

        <?= err('captcha') ?>

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