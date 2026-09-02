<?php
include '../_base.php';
auth();

$currentUser = $_SESSION['user'];
$userDb = null;
$isCurrentPwdValid = false;
$_err = [];
$successPopup = false;

// 读取数据库真实用户数据
$stmt = $_db->prepare("SELECT * FROM user WHERE id = ?");
$stmt->execute([$currentUser->id]);
$userDb = $stmt->fetch(PDO::FETCH_OBJ);
if (!$userDb) {
    temp('error','User not found, please login again');
    redirect('../login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    $currentPwd = post('current_password');
    $newPwd = post('new_password');
    $confirmPwd = post('confirm_password');

    // 第一步：校验当前密码
    if ($action === "check_current") {
        if (!password_verify($currentPwd, $userDb->password)) {
            $_err['current'] = "Current password does not match our record.";
            $isCurrentPwdValid = false;
        } else {
            $isCurrentPwdValid = true;
        }
    }

    // 第二步：完整更新密码
    if ($action === "update_pwd") {
        // 再次后端校验当前密码（安全，防止前端绕过）
        if (!password_verify($currentPwd, $userDb->password)) {
            $_err['current'] = "Current password does not match our record.";
            $isCurrentPwdValid = false;
        } else {
            $isCurrentPwdValid = true;
            // 强密码正则：大写、小写、数字、特殊符号
            $pattern = '/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[!@#$%^&*()_\-+=]).{8,}$/';
            if (!preg_match($pattern, $newPwd)) {
                $_err['new'] = "New password need: uppercase, lowercase, number, special symbol, min 8 characters";
            } elseif ($newPwd !== $confirmPwd) {
                $_err['confirm'] = "New password and confirm password do not match";
            } else {
                // 哈希新密码
                $newHashPwd = password_hash($newPwd, PASSWORD_DEFAULT);
                $updateStmt = $_db->prepare("UPDATE user SET password = ? WHERE id = ?");
                $updateStmt->execute([$newHashPwd, $userDb->id]);

                // 密码更新成功：销毁session，强制登出，下次必须用新密码登录
                session_destroy();
                $successPopup = true;
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
.form-group input{
    width:100%;
    box-sizing:border-box;
    padding:11px 14px;
    border:1.5px solid #ebdcd5;
    border-radius:12px;
    font-size:14px;
}
.form-group input:disabled{
    background:#f3f0ed;
    cursor:not-allowed;
    opacity:0.7;
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
.err-text{
    color:#c82423;
    font-size:13px;
    margin-top:4px;
}
</style>

<div class="pwd-change-page">
    <div class="pwd-change-card">
        <h1>Update Password</h1>
        <p class="pwd-desc">Enter your current password first to unlock new password fields.</p>
        <form method="post">
            <input type="hidden" name="action" id="formAction" value="check_current">

            <div class="form-group">
                <label>Current Password</label>
                <input type="password" name="current_password" required>
                <?php if(isset($_err['current'])): ?>
                    <div class="err-text"><?=encode($_err['current'])?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" id="newPwd" <?= $isCurrentPwdValid ? '' : 'disabled' ?> required>
                <?php if(isset($_err['new'])): ?>
                    <div class="err-text"><?=encode($_err['new'])?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" id="confirmPwd" <?= $isCurrentPwdValid ? '' : 'disabled' ?> required>
                <?php if(isset($_err['confirm'])): ?>
                    <div class="err-text"><?=encode($_err['confirm'])?></div>
                <?php endif; ?>
            </div>

            <?php if($isCurrentPwdValid): ?>
                <button type="submit" class="btn-submit" onclick="document.getElementById('formAction').value='update_pwd'">Update Password</button>
            <?php else: ?>
                <button type="submit" class="btn-submit">Verify Current Password</button>
            <?php endif; ?>
        </form>
        <a href="profile.php" class="back-link">&larr; Back to My Profile</a>
    </div>
</div>

<?php if ($successPopup): ?>
<script>
alert("Password Update Successful");
window.location.href="../login.php";
</script>
<?php endif; ?>

<?php include '../_foot.php'; ?>
