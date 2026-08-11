```php
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

    // Keep old photo if no new photo is uploaded
    $photo = $user->photo;


    // =====================================================
    // UPLOAD PHOTO
    // =====================================================

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {

        $file = $_FILES['photo'];

        $ext = strtolower(
            pathinfo($file['name'], PATHINFO_EXTENSION)
        );

        $allowed = [
            'jpg',
            'jpeg',
            'png',
            'gif'
        ];

        if (in_array($ext, $allowed)) {

            // Generate unique file name
            $photo = uniqid() . "." . $ext;

            // IMPORTANT:
            // Use "images" consistently
            $image_path = __DIR__ . "/../images";


            // Create images folder if it does not exist
            if (!is_dir($image_path)) {
                mkdir($image_path, 0777, true);
            }


            // Move uploaded photo
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

    } else {

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


$_title = 'User | Profile';

include '../_head.php';

?>


<!-- =====================================================
     PROFILE FORM
     ===================================================== -->

<form
    method="post"
    class="form profile-form"
    enctype="multipart/form-data"
>


    <!-- Photo -->

    <label>
        Photo
    </label>

    <div>

        <img
            src="../images/<?= encode($user->photo ?: 'default.png') ?>"
            alt="Profile Photo"
            width="140"
            height="140"
        >

        <br><br>

        <input
            type="file"
            name="photo"
            accept="image/jpeg,image/png,image/gif"
        >

    </div>


    <!-- Name -->

    <label>
        Name
    </label>

    <input
        type="text"
        name="name"
        maxlength="50"
        value="<?= encode($user->name) ?>"
    >


    <!-- Email -->

    <label>
        Email
    </label>

    <input
        type="email"
        name="email"
        maxlength="100"
        value="<?= encode($user->email) ?>"
    >


    <!-- Phone -->

    <label>
        Phone Number
    </label>

    <input
        type="text"
        name="phone_no"
        maxlength="20"
        value="<?= encode($user->phone_no) ?>"
    >


    <!-- Role -->

    <label>
        Role
    </label>

    <p>
        <?= encode($user->role) ?>
    </p>


    <!-- Password -->

    <label>
        New Password
    </label>

    <input
        type="password"
        name="password"
        maxlength="100"
        placeholder="Leave blank if no change"
    >


    <!-- Update Button -->

    <section>

        <button type="submit">
            Update Profile
        </button>

    </section>


</form>


<?php

include '../_foot.php';

?>

