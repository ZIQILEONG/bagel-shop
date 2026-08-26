<?php

include '../_base.php';
include '../config.php';

require_once '../PHPMailer-master/src/PHPMailer.php';
require_once '../PHPMailer-master/src/SMTP.php';
require_once '../PHPMailer-master/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;

$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $_err['email'] = 'Email is required';
    }
    else if (!is_email($email)) {
        $_err['email'] = 'Invalid email address';
    }

    if (!$_err) {

        $stmt = $_db->prepare("
            SELECT *
            FROM user
            WHERE email = ?
        ");

        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $_err['email'] = 'Email address was not found';
        }
        else {

            $token = bin2hex(random_bytes(50));

            $expire = date(
                'Y-m-d H:i:s',
                strtotime('+5 minutes')
            );

            $stmt = $_db->prepare("
                INSERT INTO token (user_id, token, expire)
                VALUES (?, ?, ?)
            ");

            $stmt->execute([
                $user->id,
                $token,
                $expire
            ]);

            $scheme = (
                !empty($_SERVER['HTTPS']) &&
                $_SERVER['HTTPS'] !== 'off'
            ) ? 'https' : 'http';

            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

            $link = $scheme . '://' . $host . app_url(
                'user/reset.php?token=' . urlencode($token)
            );

            $safe_name = htmlspecialchars(
                $user->name,
                ENT_QUOTES,
                'UTF-8'
            );

            $safe_link = htmlspecialchars(
                $link,
                ENT_QUOTES,
                'UTF-8'
            );

            try {

                $mail = new PHPMailer(true);

                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = SMTP_USERNAME;
                $mail->Password = SMTP_PASSWORD;
                $mail->SMTPSecure =
                    PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                $mail->setFrom(
                    SMTP_USERNAME,
                    'Pululu Bagel'
                );

                $mail->addAddress(
                    $email,
                    $user->name
                );

                $mail->isHTML(true);
                $mail->Subject =
                    'Reset Your Pululu Bagel Password';

                $mail->Body = "
                    <p>Dear {$safe_name},</p>

                    <p>
                        We received a request to reset your password.
                    </p>

                    <p>
                        <a href=\"{$safe_link}\">
                            Click here to reset your password
                        </a>
                    </p>

                    <p>
                        This link expires in 5 minutes.
                    </p>

                    <p>
                        If you did not request this, ignore this email.
                    </p>

                    <p>Pululu Bagel</p>
                ";

                $mail->AltBody =
                    "Reset your password here: {$link}";

                $mail->send();

                temp(
                    'info',
                    'Password reset link sent to your email.'
                );

                redirect('forgot_password.php');
            }
            catch (Throwable $error) {

                $_err['email'] =
                    'Email could not be sent. Please try again.';
            }
        }
    }
}

$_title = 'Forgot Password | Pululu Bagel';
$_body_class = 'pululu-auth-page';

include '../_head.php';
?>

<section class="pululu-forgot-page">

    <div class="pululu-forgot-card">

        <span class="pululu-forgot-label">
            ACCOUNT HELP
        </span>

        <h1>Forgot password?</h1>

        <p class="pululu-forgot-description">
            Enter your email address and we will send you
            a password reset link.
        </p>

        <form method="post" class="pululu-forgot-form">

            <div class="pululu-forgot-field">

                <label for="email">
                    Email address
                </label>

                <?= html_text(
                    'email',
                    'maxlength="100" autocomplete="email" required placeholder="E-mail"'
                ) ?>

            </div>

            <?= err('email') ?>

            <button type="submit">
                Send reset link
            </button>

        </form>

        <a
            class="pululu-back-login"
            href="../login.php"
        >
            Back to login
        </a>

        <p class="pululu-forgot-note">
            The reset link expires after 5 minutes.
        </p>

    </div>

</section>

<?php include '../_foot.php'; ?>