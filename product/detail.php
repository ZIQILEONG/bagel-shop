<?php
include '../_base.php';

$id = req('id');
$product = null;

if ($id) {
    $statement = $_db->prepare('SELECT * FROM product WHERE id = ?');
    $statement->execute([$id]);
    $product = $statement->fetch();
}

if (!$product) {
    redirect('list.php');
}

$stock = max(0, (int) $product->stock);
$cart = get_cart();
$cart_unit = (int) ($cart[$product->id] ?? 0);
$max_units = min(10, $stock);
$is_sold_out = $stock === 0;

$_title = (string) $product->name . ' | Pululu Bagel';
$_body_class = 'pululu-product-page';
include '../_head.php';
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="<?= app_url('index.php') ?>">Home</a>
    <span>›</span>
    <a href="<?= app_url('product/list.php') ?>">Shop Bagels</a>
    <span>›</span>
    <span aria-current="page"><?= htmlspecialchars($product->name, ENT_QUOTES, 'UTF-8') ?></span>
</nav>

<section class="product-detail-layout">
    <div class="product-detail-image">
        <img src="<?= app_url('products/' . rawurlencode($product->photo)) ?>" alt="<?= htmlspecialchars($product->name, ENT_QUOTES, 'UTF-8') ?>">
        <span>Freshly baked</span>
    </div>

    <div class="product-detail-copy">
        <span class="section-eyebrow">Pululu bagel collection</span>
        <h1><?= htmlspecialchars($product->name, ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="product-detail-price">RM <?= number_format((float) $product->price, 2) ?></p>

        <div class="product-availability <?= $is_sold_out ? 'is-sold-out' : '' ?>">
            <span></span>
            <?= $is_sold_out ? 'Currently sold out' : $stock . ' available for ordering' ?>
        </div>

        <p class="product-detail-description">A warm, chewy Pululu bagel prepared in a small batch for a fresh and satisfying bite. Enjoy it on its own or with your favourite spread.</p>

        <ul class="product-detail-points">
            <li><span>✓</span> Baked in small batches</li>
            <li><span>✓</span> Clear availability before checkout</li>
            <li><span>✓</span> Member reward points available</li>
        </ul>

        <?php if ($_user?->role === 'Member'): ?>
            <form method="post" action="<?= app_url('product/list.php') ?>" class="product-detail-form">
                <input type="hidden" name="id" value="<?= htmlspecialchars((string) $product->id, ENT_QUOTES, 'UTF-8') ?>">
                <label for="detailQuantity">Quantity</label>
                <select id="detailQuantity" name="unit" <?= $is_sold_out ? 'disabled' : '' ?>>
                    <?php if ($cart_unit > 0): ?><option value="0">Remove from cart</option><?php endif ?>
                    <?php for ($quantity = 1; $quantity <= $max_units; $quantity++): ?>
                        <option value="<?= $quantity ?>" <?= $cart_unit === $quantity ? 'selected' : '' ?>><?= $quantity ?></option>
                    <?php endfor ?>
                </select>
                <button type="submit" <?= $is_sold_out ? 'disabled' : '' ?>><?= $cart_unit > 0 ? 'Update cart' : 'Add to cart' ?></button>
            </form>
        <?php elseif (!$_user): ?>
            <div class="product-login-panel">
                <p>Log in to add this bagel to your cart and earn reward points.</p>
                <a class="button button-primary" href="<?= app_url('login.php') ?>">Log in to order</a>
                <a class="button button-secondary" href="<?= app_url('user/register.php') ?>">Create account</a>
            </div>
        <?php endif ?>

        <a class="back-to-shop" href="<?= app_url('product/list.php') ?>">← Back to all bagels</a>
    </div>
</section>

<?php include '../_foot.php'; ?>