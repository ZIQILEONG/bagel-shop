<?php
include '../_base.php';
auth();
$user = $_user;

if (is_post()) {
    $name     = post('name');
    $email    = post('email');
    $phone_no = post('phone_no');
    $photo = $user->photo;

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $file = $_FILES['photo'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($ext, $allowed)) {
            $photo = uniqid() . "." . $ext;
            $image_path = __DIR__ . "/image";
            if (!is_dir($image_path)) {
                mkdir($image_path, 0777, true);
            }
            move_uploaded_file($file['tmp_name'], $image_path . "/" . $photo);
        }
    }

    $stm = $_db->prepare("UPDATE user SET name=?,email=?,phone_no=?,photo=? WHERE id=?");
    $stm->execute([$name,$email,$phone_no,$photo,$user->id]);

    // 🔴Key point: After updating the database, re-query the user; do not continue using the old `$user`.
    $stm = $_db->prepare("SELECT * FROM user WHERE id = ?");
    $stm->execute([$user->id]);
    $_SESSION['user'] = $stm->fetch(); // Refresh the user data in the session!
    temp('info', 'Profile updated successfully!');
    redirect('profile.php');
}

// Retrieve the latest user from the session on the page.
$user = $_SESSION['user'];

$_title = 'User | Profile';
include '../_head.php';
?>
<form method="post" class="form profile-form" enctype="multipart/form-data">
    <label>Photo</label>
    <div>
        <?php
        // Avatar path; singular 'image' folder
        $avatarFile = !empty($user->photo) ? $user->photo : 'photo.jpg';
        ?>
        <img id="avatarImg" src="image/<?=encode($avatarFile)?>" width="180" height="180" style="object‑fit:cover;border‑radius:60%;">
        <br><br>
        <input id="photoInput" type="file" name="photo" accept="image/jpeg,image/png,image/gif">
    </div>

    <label>Name</label>
    <input type="text" name="name" maxlength="50" value="<?=encode($user->name)?>">

    <label>Email</label>
    <input type="email" name="email" maxlength="100" value="<?=encode($user->email)?>">

    <label>Phone Number</label>
    <input type="text" name="phone_no" maxlength="20" value="<?=encode($user->phone_no)?>">

    <label>Role</label>
    <p><?=encode($user->role)?></p>

    <label>Shipping Address</label>
    <p>
        <a href="address‑list.php" style="text‑decoration:none;color:inherit;" onmouseover="this.style.color='#0000cc'" onmouseout="this.style.color='inherit'">
            📦 Manage My Shipping Address
        </a>
    </p>

    <section>
        <button type="submit">Update Profile</button>
    </section>
</form>

<script>
    const avatarImg = document.getElementById('avatarImg');
    const photoInput = document.getElementById('photoInput');
    photoInput.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function (e) {
            avatarImg.src = e.target.result;
        }
        reader.readAsDataURL(file);
    })
</script>
<?php
include '../_foot.php';
?>
