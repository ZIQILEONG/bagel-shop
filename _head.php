<?php
$header_cart_count = array_sum(get_cart());
$header_role = $_user->role ?? null;
$header_name = $_user ? htmlspecialchars((string) $_user->name, ENT_QUOTES, 'UTF-8') : 'Account';
$header_path = str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');
$header_active = static function (string $section) use ($header_path): string {
    return str_contains($header_path, $section) ? ' class="is-active" aria-current="page"' : '';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($_title ?? 'Pululu Bagel', ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="Freshly baked Pululu bagels for pickup and delivery.">
    <link rel="shortcut icon" href="<?= app_url('images/favicon.png') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lilita+One&family=Nunito+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= app_url('css/app.css') ?>">
    <link rel="stylesheet" href="<?= app_url('css/navbar.css') ?>">
    <link rel="stylesheet" href="<?= app_url('css/inline-styles.css') ?>">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.11/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.11/js/jquery.dataTables.min.js"></script>
    <script src="<?= app_url('js/app.js') ?>"></script>
</head>
<body id="top" class="<?= htmlspecialchars($_body_class ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <div id="info" role="status" aria-live="polite"><?= temp('info') ?></div>
    <div class="pululu-announcement">
        <div class="pululu-announcement-inner">
            <span><b>Freshly baked daily</b> in small batches</span>
            <span class="announcement-divider" aria-hidden="true"></span>
            <span>Order now and enjoy disocunts with promo code: <b>BAGEL10 || WELCOME20</b></span>
        </div>
    </div>
    <header class="pululu-header">
        <div class="pululu-header-main">
            <a class="pululu-brand" href="<?= app_url('index.php') ?>" aria-label="Pululu Bagel home">
                <img src="<?= app_url('images/logo.jpeg') ?>" alt="Pululu bear holding a bagel">
                <span><b>Pululu</b><small>Bagel Bakery</small></span>
            </a>
            <button class="pululu-menu-toggle" type="button" aria-controls="pululuPrimaryNav" aria-expanded="false" aria-label="Open menu">
                <span></span><span></span><span></span>
            </button>
            <div class="pululu-actions">
                <details class="pululu-search">
                    <summary aria-label="Search products">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="11" cy="11" r="7"></circle>
                            <path d="m16.2 16.2 4.3 4.3"></path>
                        </svg>
                        <span>Search</span>
                    </summary>
                    <form action="<?= app_url('product/list.php') ?>" method="get">
                        <label for="navSearch">Find your next bagel</label>
                        <div>
                            <input id="navSearch" type="search" name="search" placeholder="Search by flavour…" required>
                            <button type="submit">Search</button>
                        </div>
                    </form>
                </details>
                <details class="pululu-account">
                <summary aria-label="Account menu">
                <?php if ($_user && !empty($_user->photo)): ?>
                <img class="pululu-avatar" src="<?= app_url('user/image/' . rawurlencode($_user->photo)) ?>" alt="">
                <?php else: ?>
                <svg viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="12" cy="8" r="4"></circle>
            <path d="M4.5 21c.7-4.1 3.2-6 7.5-6s6.8 1.9 7.5 6"></path>
        </svg>
    <?php endif ?>
    <span><?= $header_name ?></span>
</summary>


                    <div class="pululu-account-menu">
                        <?php if (!$_user): ?>
                            <strong>Welcome to Pululu</strong>
                            <span>Sign in to order and track purchases.</span>
                            <a href="<?= app_url('login.php') ?>">Log in</a>
                            <a href="<?= app_url('user/register.php') ?>">Create account</a>
                        <?php else: ?>
                            <strong><?= $header_name ?></strong>
                            <span><?= htmlspecialchars((string) $header_role, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if ($header_role === 'Member'): ?>
                                <span>⭐ <?= (int) ($_user->points ?? 0) ?> reward points</span>
                                <a href="<?= app_url('user/profile.php') ?>">My profile</a>
                                <a href="<?= app_url('order/history.php') ?>">Order history</a>
                            <?php endif ?>
                            <a href="<?= app_url('logout.php') ?>">Log out</a>
                        <?php endif ?>
                    </div>
                </details>
                <?php if ($header_role === 'Member'): ?>
                    <a class="pululu-cart" href="<?= app_url('order/cart.php') ?>" aria-label="Shopping cart with <?= (int) $header_cart_count ?> items">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M6.5 8h11l1 13h-13l1-13Z"></path>
                            <path d="M9 9V6a3 3 0 0 1 6 0v3"></path>
                        </svg>
                        <span>Cart</span>
                        <b><?= (int) $header_cart_count ?></b>
                    </a>
                <?php endif ?>
            </div>
        </div>
        <nav class="navbar" id="pululuPrimaryNav" aria-label="Primary navigation">
            <div class="navbar-inner">
                <a href="<?= app_url('index.php') ?>"<?= $header_active('/index.php') ?>>
                    <span class="flip"><span>Home</span><span>Home</span></span>
                </a>
                <a href="<?= app_url('product/list.php') ?>"<?= $header_active('/product/') ?>>
                    <span class="flip"><span>Shop Bagels</span><span>Shop Bagels</span></span>
                </a>
                <?php if ($header_role === 'Member'): ?>
                    <a href="<?= app_url('order/history.php') ?>"<?= $header_active('/order/history.php') ?>>
                        <span class="flip"><span>My Orders</span><span>My Orders</span></span>
                    </a>
                    <a href="<?= app_url('order/cart.php') ?>"<?= $header_active('/order/cart.php') ?>>
                        <span class="flip"><span>Shopping Cart</span><span>Shopping Cart</span></span>
                    </a>
                <?php elseif ($header_role === 'Admin'): ?>
                    <a href="<?= app_url('admin/order-list.php') ?>"<?= $header_active('/admin/order') ?>>
                        <span class="flip"><span>Manage Orders</span><span>Manage Orders</span></span>
                    </a>
                    <a href="<?= app_url('admin/product-listing.php') ?>"<?= $header_active('/admin/product') ?>>
                        <span class="flip"><span>Manage Products</span><span>Manage Products</span></span>
                    </a>
                    
                    <a href="<?= app_url('admin/user-listing.php') ?>"<?= $header_active('/admin/user') ?>>
                        <span class="flip"><span>Manage Members</span><span>Manage Members</span></span>
                    </a>
                    <a href="<?= app_url('admin/top-selling.php') ?>"<?= $header_active('/admin/top-selling.php') ?>>
                        <span class="flip"><span>Top Selling</span><span>Top Selling</span></span>
                    </a>
                <?php else: ?>
                    <a href="<?= app_url('login.php') ?>"<?= $header_active('/login.php') ?>>
                        <span class="flip"><span>Log In</span><span>Log In</span></span>
                    </a>
                    <a href="<?= app_url('user/register.php') ?>"<?= $header_active('/user/register.php') ?>>
                        <span class="flip"><span>Create Account</span><span>Create Account</span></span>
                    </a>
                <?php endif ?>
            </div>
        </nav>
    </header>
    <main>
