<?php
include '../_base.php';
auth();
$user = $_user;
$uid = $_SESSION['user']->id;
// Update personal profile (name, email, phone number, avatar)
if (is_post()) {
    $name     = post('name');
    $email    = post('email');
    $phone_no = post('phone_no');
    $photo    = $user->photo;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $file    = $_FILES['photo'];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $allowed)) {
            $photo      = uniqid('usr_') . "." . $ext;
            $upload_dir = __DIR__ . "/image";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            if (!empty($user->photo) && $user->photo !== 'photo.jpg' && $user->photo !== 'default.jpg') {
                $old_file = $upload_dir . "/" . $user->photo;
                if (file_exists($old_file)) @unlink($old_file);
            }
            move_uploaded_file($file['tmp_name'], $upload_dir . "/" . $photo);
        }
    }
    $stm = $_db->prepare("UPDATE user SET name = ?, email = ?, phone_no = ?, photo = ? WHERE id = ?");
    $stm->execute([$name, $email, $phone_no, $photo, $uid]);
    $stm = $_db->prepare("SELECT * FROM user WHERE id = ?");
    $stm->execute([$uid]);
    $_SESSION['user'] = $stm->fetch(PDO::FETCH_OBJ);
    $user = $_SESSION['user'];
    temp('info', 'Profile updated successfully!');
    redirect('profile.php');
}
$_title = 'User | Profile';
include '../_head.php';
$avatarFile = !empty($user->photo) ? $user->photo : 'photo.jpg';
$avatarSrc  = "/user/image/" . encode($avatarFile);
?>
<link rel="stylesheet" href="<?= app_url('css/user-profile.css') ?>">
<div class="pl-profile-wrapper">
    <div class="pl-profile-card">
        <div class="pl-profile-header">
            <h1>My Profile</h1>
            <p class="il-102-c73c14">Update your photo and personal account details</p>
        </div>
        <!-- Personal Profile Form -->
        <form method="post" enctype="multipart/form-data">
            <div class="pl-avatar-section">
                <div class="pl-avatar-wrap">
                    <img id="avatarImg" src="<?= $avatarSrc ?>" alt="<?= encode($user->name) ?>"
                         onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?=urlencode($user->name ?: 'User')?>&background=ebdcd5&color=3e2619&size=140';">
                </div>
                <label for="photoInput" class="pl-file-upload-btn">📷 Change Profile Photo</label>
                <input class="il-35-cb4589" id="photoInput" type="file" name="photo" accept="image/jpeg,image/png,image/gif,image/webp">
            </div>
            <div class="pl-form-group">
                <label>Full Name</label>
                <input type="text" name="name" maxlength="50" value="<?= encode($user->name) ?>" required>
            </div>
            <div class="pl-form-group">
                <label>Email Address</label>
                <input type="email" name="email" maxlength="100" value="<?= encode($user->email) ?>" required>
            </div>
            <div class="pl-form-group">
                <label>Phone Number</label>
                <input type="text" name="phone_no" maxlength="20" value="<?= encode($user->phone_no) ?>">
            </div>
            <div class="pl-form-group">
                <label>Account Role</label>
                <div><span class="pl-role-badge"><?= encode($user->role) ?></span></div>
            </div>
            <div class="pl-address-box">
                <span class="il-103-eeae22">📦 Shipping Addresses</span>
                <a href="address-list.php">Manage Addresses &rarr;</a>
            </div>
            <!-- Added redirect link for password -->
            <div class="pl-address-box">
                <span class="il-103-eeae22">🔐 Password</span>
                <a href="update_password.php">Request Password Change Link &rarr;</a>
            </div>

            <button type="submit" class="pl-btn-primary">Save Changes</button>
        </form>
    </div>
</div>
<script>

const avatarImg = document.getElementById('avatarImg');
const photoInput = document.getElementById('photoInput');
photoInput.addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function (e) {
        avatarImg.src = e.target.result;
    };
    reader.readAsDataURL(file);
});
</script>
<?php
include '../_foot.php';
?>
