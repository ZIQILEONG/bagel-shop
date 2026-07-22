<?php

require '_base.php';


if(!isset($_SESSION['user'])){

    header("location:login.php");
    exit;

}


$user = $_SESSION['user'];



if(is_post()){


    $new_password = post('password');


    if($new_password!=""){


        $hash=password_hash(
            $new_password,
            PASSWORD_DEFAULT
        );


        $stm=$_db->prepare("
            UPDATE users
            SET password=?
            WHERE id=?
        ");


        $stm->execute([
            $hash,
            $user->id
        ]);



        echo "Password Updated";


    }


}


?>


<h2>
Profile
</h2>


<img 
src="images/<?= $user->photo ?>"
width="100">


<br>


Name:

<?= $user->name ?>


<br>


Email:

<?= $user->email ?>


<br>


Phone:

<?= $user->phone_no ?>


<br>


Role:

<?= $user->role ?>



<hr>


<h3>
Change Password
</h3>



<form method="post">


New Password:

<input 
type="password"
name="password">


<br>


<button>
Update Password
</button>


</form>