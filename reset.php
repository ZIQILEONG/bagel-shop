<?php
include '../_base.php';

// Get token from URL
$token = $_GET['token'] ?? '';

if (!$token) {
    die("Invalid reset link");
}


// Check token exists and not expired
$stm = $_db->prepare(
    "SELECT * 
     FROM password_reset_token 
     WHERE token = ? 
     AND expire > NOW()"
);

$stm->execute([$token]);

$reset = $stm->fetch();


if (!$reset) {
    die("Token expired or invalid");
}


// When user submits new password
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $password = $_POST['password'];

    // Update password in user table
    $stm = $_db->prepare(
        "UPDATE user 
         SET password = ?
         WHERE id = ?"
    );

    $stm->execute([
        password_hash($password, PASSWORD_DEFAULT),
        $reset->user_id
    ]);


    // Delete used token
    $stm = $_db->prepare(
        "DELETE FROM password_reset_token
         WHERE token = ?"
    );

    $stm->execute([$token]);


    echo "Password reset successful!";
}

?>


