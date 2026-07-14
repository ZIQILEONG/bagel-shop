<?php
include '../_base.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $email = $_POST['email'];

    // 1. Check user email exists
    $stm = $_db->prepare(
        "SELECT * FROM user WHERE email = ?"
    );
    $stm->execute([$email]);

    $user = $stm->fetch();

    if ($user) {

        // 2. Generate reset token HERE
        $token = bin2hex(random_bytes(50));

        // 3. Set expiry time (example: 1 hour)
        $expire = date("Y-m-d H:i:s", strtotime("+30 minutes"));

        // 4. Save token into password_reset_token table
        $stm = $_db->prepare(
            "INSERT INTO token
            (user_id, token, expire)
            VALUES (?, ?, ?)"
        );

        $stm->execute([
            $user->id,
            $token,
            $expire
        ]);

        // 5. Create reset link
      $link = "http://localhost:8000/user/reset.php?token=" . $token;

        echo "Reset link: " . $link;

    } else {
        echo "Email not found";
    }
}


$_title = 'User | Password'; 
include '../_head.php';
?>

<form method="post">
    email:
    <input type="email" name="email"><br>

    <button>
        Send Reset Link
    </button>

</form>
<?php
include '../_foot.php';