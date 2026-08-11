<?php

include '../_base.php';

auth();

$user = $_user;



// Update Profile

if(is_post()){


    $name = post('name');
    $email = post('email');
    $phone_no = post('phone_no');
    $password = post('password');

    $photo = $user->photo;



    // ==========================
    // Upload Photo
    // ==========================

    if(isset($_FILES['photo']) && $_FILES['photo']['error'] == 0){


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



        if(in_array($ext, $allowed)){


            $photo = uniqid() . "." . $ext;


            $image_path = __DIR__ . "/../image";


if(!is_dir($image_path)){
    mkdir($image_path, 0777, true);
}


move_uploaded_file(
    $file['tmp_name'],
    $image_path . "/$photo"
);


        }

    }




    // ==========================
    // Update Database
    // ==========================


    if($password != ''){


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

    else{


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




    echo "Profile updated successfully!";



    // Reload user data

    $stm = $_db->prepare("
        SELECT *
        FROM user
        WHERE id = ?
    ");


    $stm->execute([
        $user->id
    ]);


    $user = $stm->fetch();


}




$_title = 'User | Profile';


include '../_head.php';

?>



<form method="post"
      class="form profile-form"
      enctype="multipart/form-data">



<!-- Photo -->

<label>
Photo
</label>


<div>


<img
src="../image/<?=encode($user->photo ?: 'default.png')?>"
width="120"
height="120"
style="object-fit:cover;border-radius:50%;">



<br><br>


<input
type="file"
name="photo"
accept="image/*">


</div>





<!-- Name -->

<label>
Name
</label>


<input
type="text"
name="name"
maxlength="50"
value="<?=encode($user->name)?>">





<!-- Email -->

<label>
Email
</label>


<input
type="email"
name="email"
maxlength="100"
value="<?=encode($user->email)?>">





<!-- Phone -->

<label>
Phone Number
</label>


<input
type="text"
name="phone_no"
maxlength="20"
value="<?=encode($user->phone_no)?>">





<!-- Role -->

<label>
Role
</label>


<p>
<?=encode($user->role)?>
</p>





<!-- Password -->

<label>
New Password
</label>


<input
type="password"
name="password"
maxlength="100"
placeholder="Leave blank if no change">





<section>

<button>
Update Profile
</button>


</section>



</form>



<?php

include '../_foot.php';

?>