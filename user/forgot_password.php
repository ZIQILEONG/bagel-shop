<?php

include '../_base.php';

require_once '../PHPMailer-master/src/PHPMailer.php';
require_once '../PHPMailer-master/src/SMTP.php';
require_once '../PHPMailer-master/src/Exception.php';
require_once '../config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    if ($email == '') {
        $_err['email'] = 'Please enter your email.';
    }
    else if (!is_email($email)) {
        $_err['email'] = 'Invalid email.';
    }
    if (!$_err) {
        $stm = $_db->prepare(
            "SELECT * FROM user WHERE email = ?"
        );
        $stm->execute([$email]);
        $user = $stm->fetch();
        if ($user) {
            // Generate reset token
            $token = bin2hex(random_bytes(50));

            // Token expires after 5 minutes
            $expire = date("Y-m-d H:i:s", strtotime("+5 minutes"));

            // Save token
            $stm = $_db->prepare(
                "INSERT INTO token (user_id, token, expire)
                 VALUES (?, ?, ?)"
            );
            $stm->execute([
                $user->id,
                $token,
                $expire
            ]);

            // Reset link
            $link = "http://localhost:8000/user/reset.php?token=" . $token;

            // Send email
            $mail = new PHPMailer(true);

            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom(
                'pululubagelshop@gmail.com',
                'Pululu Bagel'
            );

            $mail->addAddress($email);

            $mail->Subject = 'Password Reset';

            $mail->Body = "
            Dear {$user->name},
            We received a request to reset your password.
            Click the link below to reset your password:
            $link
            This link expires in 5 minutes.
            If you did not request this, please ignore this email.
            Pululu Bagel
            ";
            $mail->send();
            temp('info', 'Password reset link has been sent to your email.');
        }
        else {
            $_err['email'] = 'Email not found.';
        }
    }
}
        $_title = 'Forgot Password';
        include '../_head.php';
        ?>
        <div class="forgot-container">
            <h2>Forgot Password</h2>
            <p class="forgot-subtitle">
                Enter your email to receive a password reset link:
            </p>
            <form method="post" class="forgot-form">
                <div class="input-group">
                    <?= html_text('email', 'maxlength="100" required placeholder="E-mail"') ?>
                    <label for="email">E-mail</label>
                    <?= err('email') ?>
                </div>
                <button type="submit">
                    SEND RESET LINK
                </button>
            </form>
            <p class="back-login-text">
                Remember your password?
                <a href="../login.php">Login</a>
            </p>
        </div>

<?php
include '../_foot.php';
?>
