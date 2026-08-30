<?php
include '../_base.php';
auth();
$user = $_user;

if (is_post()) {
    $name     = post('name');
    $email    = post('email');
    $phone_no = post('phone_no');
    $photo    = $user->photo;

    // Handle photo upload
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $file    = $_FILES['photo'];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (in_array($ext, $allowed)) {
            $photo      = uniqid('usr_') . "." . $ext;
            $upload_dir = __DIR__ . "/image";

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            // Remove old uploaded photo if it is not the default
            if (!empty($user->photo) && $user->photo !== 'photo.jpg' && $user->photo !== 'default.jpg') {
                $old_file = $upload_dir . "/" . $user->photo;
                if (file_exists($old_file)) {
                    @unlink($old_file);
                }
            }

            move_uploaded_file($file['tmp_name'], $upload_dir . "/" . $photo);
        }
    }

    $stm = $_db->prepare("UPDATE user SET name = ?, email = ?, phone_no = ?, photo = ? WHERE id = ?");
    $stm->execute([$name, $email, $phone_no, $photo, $user->id]);

    // Refresh the user session with latest database row
    $stm = $_db->prepare("SELECT * FROM user WHERE id = ?");
    $stm->execute([$user->id]);
    $_SESSION['user'] = $stm->fetch();
    
    temp('info', 'Profile updated successfully!');
    redirect('profile.php');
}

// Retrieve the fresh user object
$user = $_SESSION['user'] ?? $_user;

$_title = 'User | Profile';
include '../_head.php';

// Safe avatar path with fallback
$avatarFile = !empty($user->photo) ? $user->photo : 'photo.jpg';
$avatarSrc  = "/user/image/" . encode($avatarFile);
?>

<style>
:root {
    --pl-primary: #cf7953;
    --pl-primary-hover: #b86440;
    --pl-brown-dark: #3e2619;
    --pl-border: #ebdcd5;
    --pl-card-bg: #ffffff;
    --pl-muted: #968377;
    --pl-accent: #fbf5ef;
}

.pl-profile-wrapper {
    max-width: 680px;
    margin: 30px auto 70px;
    padding: 0 20px;
}

.pl-profile-card {
    background: var(--pl-card-bg);
    border: 1px solid var(--pl-border);
    border-radius: 20px;
    padding: 36px;
    box-shadow: 0 4px 20px rgba(62, 38, 25, 0.04);
}

.pl-profile-header {
    text-align: center;
    margin-bottom: 28px;
    padding-bottom: 20px;
    border-bottom: 1px solid #f5ebe4;
}

.pl-profile-header h1 {
    font-size: 24px;
    font-weight: 800;
    color: var(--pl-brown-dark);
    margin: 0 0 6px;
}

.pl-avatar-section {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;
    margin-bottom: 28px;
}

.pl-avatar-wrap {
    position: relative;
    width: 140px;
    height: 140px;
    border-radius: 50%;
    border: 3px solid var(--pl-primary);
    box-shadow: 0 6px 18px rgba(207, 121, 83, 0.2);
    overflow: hidden;
    background: #fffdfc;
}

.pl-avatar-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.pl-file-upload-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 18px;
    background: #ffffff;
    border: 1.5px solid var(--pl-border);
    color: var(--pl-brown-dark);
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
}
.pl-file-upload-btn:hover {
    background: var(--pl-accent);
    border-color: var(--pl-primary);
    color: var(--pl-primary);
}

.pl-form-group {
    margin-bottom: 18px;
}

.pl-form-group label {
    display: block;
    font-size: 12.5px;
    font-weight: 700;
    color: var(--pl-brown-dark);
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.pl-form-group input {
    width: 100%;
    padding: 11px 14px;
    border: 1.5px solid var(--pl-border);
    border-radius: 12px;
    font-size: 14px;
    color: var(--pl-brown-dark);
    background: #fffdfc;
    box-sizing: border-box;
    outline: none;
    transition: all 0.2s ease;
}

.pl-form-group input:focus {
    border-color: var(--pl-primary);
    box-shadow: 0 0 0 3px rgba(207, 121, 83, 0.12);
}

.pl-role-badge {
    display: inline-block;
    background: #fbf0e8;
    color: #9c502b;
    font-size: 12px;
    font-weight: 700;
    padding: 4px 12px;
    border-radius: 6px;
    border: 1px solid #f3dacd;
}

.pl-manage-address-box {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--pl-accent);
    border: 1px solid var(--pl-border);
    padding: 14px 18px;
    border-radius: 12px;
    margin: 22px 0;
}

.pl-manage-address-box a {
    color: var(--pl-primary);
    font-weight: 700;
    font-size: 13px;
    text-decoration: none;
}
.pl-manage-address-box a:hover {
    text-decoration: underline;
}

.pl-btn-save-profile {
    width: 100%;
    background: var(--pl-primary);
    color: #ffffff;
    border: none;
    padding: 13px 20px;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 4px 14px rgba(207, 121, 83, 0.25);
}

.pl-btn-save-profile:hover {
    background: var(--pl-primary-hover);
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(207, 121, 83, 0.35);
}
</style>

<div class="pl-profile-wrapper">
    <div class="pl-profile-card">
        <div class="pl-profile-header">
            <h1>My Profile</h1>
            <p style="color: var(--pl-muted); font-size: 13.5px; margin: 0;">Update your photo and personal account details</p>
        </div>

        <form method="post" enctype="multipart/form-data">
            <div class="pl-avatar-section">
                <div class="pl-avatar-wrap">
                    <img id="avatarImg" 
                         src="<?= $avatarSrc ?>" 
                         alt="<?= encode($user->name) ?>" 
                         onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?= urlencode($user->name ?: 'User') ?>&background=ebdcd5&color=3e2619&size=140';">
                </div>

                <label for="photoInput" class="pl-file-upload-btn">
                    📷 Change Profile Photo
                </label>
                <input id="photoInput" type="file" name="photo" accept="image/jpeg,image/png,image/gif,image/webp" style="display: none;">
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

            <div class="pl-manage-address-box">
                <span style="font-size: 13.5px; font-weight: 600; color: var(--pl-brown-dark);">📦 Shipping Addresses</span>
                <a href="address-list.php">Manage Addresses &rarr;</a>
            </div>

            <button type="submit" class="pl-btn-save-profile">Save Changes</button>
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