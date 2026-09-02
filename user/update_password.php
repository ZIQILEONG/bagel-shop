<?php
include '../_base.php';
auth();
include '../config.php';
require_once '../PHPMailer-master/src/PHPMailer.php';
require_once '../PHPMailer-master/src/SMTP.php';
require_once '../PHPMailer-master/src/Exception.php';
use PHPMailer\PHPMailer\PHPMailer;

// Currently logged-in user object
$currentUser = $_SESSION['user'];
$email = $currentUser->email;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Enforcement: Only the email address of the currently 
    // logged-in account may be used; the email address passed 
    // in the POST request is ignored (security hardening).
    $email = trim($currentUser->email);

    if ($email === '') {
        $_err['email'] = 'Your account email is empty';
    }
    else if (!is_email($email)) {
        $_err['email'] = 'Invalid account email address';
    }

    if (!$_err) {
        $stmt = $_db->prepare("SELECT * FROM user WHERE id = ?");
        $stmt->execute([$currentUser->id]);
        $user = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$user) {
            temp('error','User not found, please login again');
            redirect('../login.php');
        }
        else {
            // Generate a token with a 5-minute expiration, reusing the token table.
            $token = bin2hex(random_bytes(50));
            $expire = date('Y-m-d H:i:s', strtotime('+5 minutes'));

            $stmt = $_db->prepare("
                INSERT INTO token (user_id, token, expire)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $user->id,
                $token,
                $expire
            ]);

            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $link = $scheme . '://' . $host . app_url('user/reset.php?token=' . urlencode($token));

            $safe_name = htmlspecialchars($user->name, ENT_QUOTES, 'UTF-8');
            $safe_link = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');

            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = SMTP_USERNAME;
                $mail->Password = SMTP_PASSWORD;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                $mail->setFrom(SMTP_USERNAME, 'Pululu Bagel');
                $mail->addAddress($email, $user->name);
                $mail->isHTML(true);
                $mail->Subject = 'Change Your Pululu Bagel Account Password';

                $mail->Body = "
                    <p>Dear {$safe_name},</p>
                    <p>You have requested to change your account password.</p>
                    <p><a href=\"{$safe_link}\">Click here to change your password</a></p>
                    <p>This link expires in 5 minutes.</p>
                    <p>If you did not make this request, please ignore this email, your password will remain unchanged.</p>
                    <p>Pululu Bagel</p>
                ";
                $mail->AltBody = "Change your password here: {$link}";
                $mail->send();

                temp('info', 'Password change link has been sent to your account email.');
                redirect('update_password.php');
            }
            catch (Throwable $error) {
                $_err['email'] = 'Email could not be sent. Please try again.';
            }
        }
    }
}

$_title = 'Update Password | Pululu Bagel';
include '../_head.php';
?>
<style>
.pwd-change-page{
    max-width:540px;
    margin:60px auto;
    padding:0 20px;
}
.pwd-change-card{
    background:#ffffff;
    border:1px solid #ebdcd5;
    border-radius:20px;
    padding:34px;
    box-shadow:0 4px 20px rgba(62,38,25,0.04);
}
.pwd-change-card h1{
    font-size:23px;
    color:#3e2619;
    margin:0 0 10px;
}
.pwd-desc{
    color:#968377;
    font-size:14px;
    margin-bottom:24px;
}
.form-group{
    margin-bottom:18px;
}
.form-group label{
    display:block;
    font-size:12.5px;
    font-weight:700;
    color:#3e2619;
    margin-bottom:6px;
    text-transform:uppercase;
    letter-spacing:0.03em;
}
.form-group input[readonly]{
    background:#f7f3f0;
    color:#777;
}
.form-group input{
    width:100%;
    box-sizing:border-box;
    padding:11px 14px;
    border:1.5px solid #ebdcd5;
    border-radius:12px;
    font-size:14px;
}
.btn-submit{
    width:100%;
    background:#cf7953;
    color:white;
    border:none;
    border-radius:12px;
    padding:13px;
    font-weight:bold;
    cursor:pointer;
    font-size:15px;
}
.btn-submit:hover{
    background:#b86440;
}
.back-link{
    display:block;
    text-align:center;
    margin-top:20px;
    color:#cf7953;
    text-decoration:none;
    font-weight:600;
}
.note-text{
    font-size:13px;
    color:#968377;
    margin-top:14px;
    text-align:center;
}
</style>
<div class="pwd-change-page">
    <div class="pwd-change-card">
        <h1>Request Password Change Link</h1>
        <p class="pwd-desc">We will send a password change link to your registered account email.</p>

        <form method="post">
            <div class="form-group">
                <label>Your Account Email</label>
<!-- readonly: Users cannot modify this; they must use their login email address -->                <input type="email" value="<?= encode($email) ?>" readonly>
            </div>
            <?= err('email') ?>
            <button type="submit" class="btn-submit">Send Password Change Link</button>
        </form>
        <a href="profile.php" class="back-link">&larr; Back to My Profile</a>
        <p class="note-text">Link valid for 5 minutes only.</p>
    </div>
</div>
<?php include '../_foot.php'; ?>
