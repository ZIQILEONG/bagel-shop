<?php
include '../_base.php';


if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if ($name == "" || $email == "" || $password == "") {
        echo "Please fill in all fields.";
    }
    else if (is_exists($email, 'user', 'email')) {
        echo "Email already exists.";
    }
    else {

        $stm = $_db->prepare("
            INSERT INTO user (name, email, password)
            VALUES (?, ?, SHA1(?))
        ");

        $stm->execute([$name, $email, $password]);

        echo "Register successful!";
    }
}

$_title = 'User | Register';
include '../_head.php';
?>

<h2>Register</h2>

<form method="post" class="form">
    <label for="name" required>Name</label>
    <?= html_text('name', 'maxlength="50"') ?>
    <?= err('name') ?>

    <label for="email">Email</label>
        <?= html_text('email', 'maxlength="100"') ?>
        <?= err('email') ?>

    <label for="password">Password</label>
    <?= html_password('password', 'maxlength="100"') ?>
    <?= err('password') ?>

    <section>
        <button>Register</button>
    </section>
</form>


<?php
include '../_foot.php';
?>