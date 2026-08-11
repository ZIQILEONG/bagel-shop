<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_title ?? 'Pululu Bagel' ?></title>
    <link rel="shortcut icon" href="/images/favicon.png">
    <link rel="stylesheet" href="/css/app.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
   <link href="https://fonts.googleapis.com/css2?family=Lilita+One&display=swap" rel="stylesheet">
    <script src="/js/app.js"></script>
</head>
<body>
    <!-- Flash message -->
    <div id="info"><?= temp('info') ?></div>
    <header class="top-bar">
        <h1>Order now to get 10% off!</h1>
    </header>
    <header >
        <div class="logo-container">
            <img src="/images/logo.jpeg" class="logo" alt="Pululu Bagel Logo">
        </div>
        <h1><a href="/">PULULU BAGEL</a></h1>
        <nav class="navbar">
            <a href="/index.php">
                <span class="flip">
                <span>Home</span>
                <span>Home</span>
                </span>
            </a>
            <a id="storyLink" href="/user/register.php">
                <span class="flip">
                <span>Register</span>
                <span>Register</span>
                </span>
            </a>
            <a href="#">
                <span class="flip">
                <span>Order For Tomorrow</span>
                <span>Order For Tomorrow</span>
                </span>
            </a>
        </nav>
        <?php if ($_user): ?>
            <div>
                <?= $_user->name ?><br>
                <?= $_user->role ?>
            </div>
            <img src="/photos/<?= $_user->photo ?>">
        <?php endif ?>
        
    </header>

   <nav >
         <?php if ($_user?->role == 'Member'): ?>
            <a href="/product/list.php">Product List</a>
            <a href="/order/cart.php">
                Shopping Cart
                <?php
                    $cart = get_cart();
                    $count = count($cart);
                    if ($count) echo "($count)";
                ?>
            </a>
        <?php endif ?>

        <?php if ($_user?->role == 'Member'): ?>
            <a href="/order/history.php">Order History</a>
        <?php endif ?>

        <?php if ($_user?->role == 'Admin'): ?>
            <a href="/admin/order-list.php">Manage Orders</a>
            <a href="/admin/product-listing.php">Manage Products</a>
            <a href="/admin/user-listing.php">Manage Members</a>
        <?php endif ?> 

    </nav> 
    

    <main>
