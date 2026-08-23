<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_title ?? 'Pululu Bagel' ?></title>
    <link rel="shortcut icon" href="/images/favicon.png">
    <link rel="stylesheet" href="/css/app.css">
    <link rel="stylesheet" href="/css/app-extra.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Lilita+One&display=swap" rel="stylesheet">

    <!-- SweetAlert2 (replaces native confirm() dialogs) -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- DataTables (admin listings: instant client-side search / sort / paging) -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.11/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.11/js/jquery.dataTables.min.js"></script>

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
                <a id="storyLink" href="login.php">
                <span class="flip">
                <span>Login</span>
                <span>Login</span>
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
                <?php if ($_user->role == 'Member'): ?>
                    <br>⭐ <?= $_user->points ?> points
                <?php endif ?>
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
                    $count = array_sum($cart);
                    if ($count) echo "($count)";
                ?>
            </a>
        <?php endif ?>

        <?php if ($_user?->role == 'Member'): ?>
            <a href="/order/history.php">Order History</a>
            <a href="/logout.php">Logout</a>
        <?php endif ?>

        <?php if ($_user?->role == 'Admin'): ?>
            <a href="/admin/top-selling.php">Top 5 Selling</a>
            <a href="/admin/order-list.php">Manage Orders</a>
            <a href="/admin/product-listing.php">Manage Products</a>
            <a href="/admin/user-listing.php">Manage Members</a>
            <a href="/logout.php">Logout</a>
        <?php endif ?> 

    </nav> 
    

    <main>