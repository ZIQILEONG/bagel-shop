<?php
include '../_base.php';
auth();
$currentUser = $_SESSION['user'];
$userDb = null;
$isCurrentPwdValid = false;
$_err = [];
$savedCurrentPwd = "";
// Read real user data from the database
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
    $savedCurrentPwd = $currentPwd;
    // Validate current password
    if ($action === "check_current") {
        if (!password_verify($currentPwd, $userDb->password)) {
            $_err['current'] = "Current Password Invalid. Please Try Again.";
            $isCurrentPwdValid = false;
        } else {
            $isCurrentPwdValid = true;
        }
    }
    // Fully update password
    if ($action === "update_pwd") {
        if (!password_verify($currentPwd, $userDb->password)) {
            $_err['current'] = "Current Password Invalid. Please Try Again.";
            $isCurrentPwdValid = false;
        } else {
            $isCurrentPwdValid = true;
            $pattern = '/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[!@#$%^&*()_\-+=]).{8,}$/';
            if (!preg_match($pattern, $newPwd)) {
                $_err['new'] = "New password need: uppercase, lowercase, number, special symbol, min 8 characters";
            } elseif ($newPwd !== $confirmPwd) {
                $_err['confirm'] = "New password and confirm password do not match";
            } else {
                $newHashPwd = password_hash($newPwd, PASSWORD_DEFAULT);
                $updateStmt = $_db->prepare("UPDATE user SET password = ? WHERE id = ?");
                $updateStmt->execute([$newHashPwd, $userDb->id]);

                $_SESSION = [];
                session_destroy();
                // Delete session cookie
                if (ini_get("session.use_cookies")) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000,
                        $params["path"], $params["domain"],
                        $params["secure"], $params["httponly"]
                    );
                }
                // Delete "Remember Me" cookies
                setcookie('remember', '', time() - 3600, '/');
                setcookie('remember_me', '', time() - 3600, '/');
                // Force redirect to login.php
                header("Location: ../login.php");
                exit;
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
.input-wrap{
    position:relative;
}
.input-wrap input{
    width:100%;
    box-sizing:border-box;
    padding:11px 44px 11px 14px;
    border:1.5px solid #ebdcd5;
    border-radius:12px;
    font-size:14px;
}
.form-group input:disabled{
    background:#f3f0ed;
    cursor:not-allowed;
    opacity:0.7;
}
.form-group input[readonly]{
    background:#f7f3f0;
    cursor:not-allowed;
}
.eye-btn{
    position:absolute;
    right:12px;
    top:50%;
    transform:translateY(-50%);
    background:none;
    border:none;
    font-size:18px;
    cursor:pointer;
    padding:0 4px;
    color:#666;
}
.tip-text{
    font-size:12px;
    color:#666666;
    margin-top:4px;
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
                <div class="input-wrap">
                    <input type="password" id="currentPwdInput" name="current_password" value="<?=encode($savedCurrentPwd)?>"
                        <?= $isCurrentPwdValid ? 'readonly' : '' ?> required>
                    <button type="button" class="eye-btn" onclick="togglePwd('currentPwdInput',this)">👁</button>
                </div>
                <?php if(isset($_err['current'])): ?>
                    <div class="err-text"><?=encode($_err['current'])?></div>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>New Password</label>
                <div class="input-wrap">
                    <input type="password" id="newPwdInput" name="new_password" <?= $isCurrentPwdValid ? '' : 'disabled' ?> required>
                    <button type="button" class="eye-btn" onclick="togglePwd('newPwdInput',this)">👁</button>
                </div>
                <div class="tip-text">Must contain uppercase, lowercase, number, special symbol</div>
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

<script>
function togglePwd(inputId, btn){
    const input = document.getElementById(inputId);
    if(input.type === 'password'){
        input.type = 'text';
        btn.textContent = '🙈';
    }else{
        input.type = 'password';
        btn.textContent = '🙈';
    }
}
</script>

<?php include '../_foot.php'; ?>
