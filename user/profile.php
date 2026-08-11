<?php

include '../_base.php';

auth();

$user = $_user;


// =========================================================
// UPDATE PROFILE
// =========================================================

if (is_post()) {

    $name     = post('name');
    $email    = post('email');
    $phone_no = post('phone_no');
    $password = post('password');


    // Keep old photo if user does not upload a new photo
    $photo = $user->photo;


    // =====================================================
    // UPLOAD PHOTO
    // =====================================================

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {

        $file = $_FILES['photo'];

        // Get file extension
        $ext = strtolower(
            pathinfo($file['name'], PATHINFO_EXTENSION)
        );


        // Allowed image types
        $allowed = [
            'jpg',
            'jpeg',
            'png',
            'gif'
        ];


        // Check file type
        if (in_array($ext, $allowed)) {

            // Create a unique file name
            $photo = uniqid() . "." . $ext;


            // IMPORTANT:
            // Your photo folder is "images"
            // NOT "image"
            $image_path = __DIR__ . "/../images";


            // Create images folder if it does not exist
            if (!is_dir($image_path)) {
                mkdir($image_path, 0777, true);
            }


            // Save uploaded image
            move_uploaded_file(
                $file['tmp_name'],
                $image_path . "/" . $photo
            );
        }
    }


    // =====================================================
    // UPDATE DATABASE
    // =====================================================

    if ($password != '') {

        // Hash new password
        $hash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );


        $stm = $_db->prepare("
            UPDATE user
            SET
                name = ?,
                email = ?,
                phone_no = ?,
                password = ?,
                photo = ?
            WHERE id = ?
        ");


        $stm->execute([
            $name,
            $email,
            $phone_no,
            $hash,
            $photo,
            $user->id
        ]);

    }
    else {

        $stm = $_db->prepare("
            UPDATE user
            SET
                name = ?,
                email = ?,
                phone_no = ?,
                photo = ?
            WHERE id = ?
        ");


        $stm->execute([
            $name,
            $email,
            $phone_no,
            $photo,
            $user->id
        ]);
    }


    // =====================================================
    // RELOAD USER DATA
    // =====================================================

    $stm = $_db->prepare("
        SELECT *
        FROM user
        WHERE id = ?
    ");


    $stm->execute([
        $user->id
    ]);


    $user = $stm->fetch();


    echo "Profile updated successfully!";
}


// =========================================================
// PAGE
// =========================================================

$_title = 'User | Profile';

include '../_head.php';

?>


<!-- =====================================================
     USER PROFILE FORM
     ===================================================== -->

<form
    method="post"
    class="form profile-form"
    enctype="multipart/form-data"
>


    <!-- =================================================
         PHOTO
         ================================================= -->

    <label>
        Photo
    </label>


    <div>
        <!-- 给img加上id，方便js获取 -->
        <img
            id="avatarImg"
            src="../images/<?= encode($user->photo ?: 'photo.jpg') ?>"
            width="150"
            height="150"
            style="object-fit: cover; border-radius: 60%;"
        >


        <br><br>

        <!-- file input加上id -->
        <input
            id="photoInput"
            type="file"
            name="photo"
            accept="image/jpeg,image/png,image/gif"
        >

    </div>


    <!-- =================================================
         NAME
         ================================================= -->

    <label>
        Name
    </label>


    <input
        type="text"
        name="name"
        maxlength="50"
        value="<?= encode($user->name) ?>"
    >


    <!-- =================================================
         EMAIL
         ================================================= -->

    <label>
        Email
    </label>


    <input
        type="email"
        name="email"
        maxlength="100"
        value="<?= encode($user->email) ?>"
    >


    <!-- =================================================
         PHONE NUMBER
         ================================================= -->

    <label>
        Phone Number
    </label>


    <input
        type="text"
        name="phone_no"
        maxlength="20"
        value="<?= encode($user->phone_no) ?>"
    >


    <!-- =================================================
         ROLE
         ================================================= -->

    <label>
        Role
    </label>


    <p>
        <?= encode($user->role) ?>
    </p>


    <!-- =================================================
         NEW PASSWORD
         ================================================= -->

    <label>
        New Password
    </label>


    <input
        type="password"
        name="password"
        maxlength="100"
        placeholder="Leave blank if no change"
    >


    <!-- =================================================
         UPDATE BUTTON
         ================================================= -->

    <section>

        <button type="submit">
            Update Profile
        </button>

    </section>


</form>

<!-- ✅新增 JS：选择图片立刻预览 -->
<script>
    const avatarImg = document.getElementById('avatarImg');
    const photoInput = document.getElementById('photoInput');

    photoInput.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;

        // 本地即时预览图片，不需要传给后端
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
