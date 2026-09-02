</main>

    <footer class="pululu-footer">
        <div class="pululu-footer-grid">
            <div class="footer-brand">
                <a href="<?= app_url('index.php') ?>">
                    <img src="<?= app_url('images/logo.jpeg') ?>" alt="">
                    <span>Pululu Bagel</span>
                </a>
                <p>Warm, chewy bagels baked in small batches for happier mornings.</p>
            </div>

            <div class="footer-links">
                <h2>Shop</h2>
                <a href="<?= app_url('product/list.php') ?>">All bagels</a>
                <?php if ($_user?->role === 'Member'): ?>
                    <a href="<?= app_url('order/cart.php') ?>">Shopping cart</a>
                    <a href="<?= app_url('order/history.php') ?>">Order history</a>
                <?php elseif ($_user?->role === 'Admin'): ?>
                    <a href="<?= app_url('admin/order-list.php') ?>">Manage orders</a>
                    <a href="<?= app_url('admin/product-listing.php') ?>">Manage products</a>
                <?php else: ?>
                    <a href="<?= app_url('login.php') ?>">Log in</a>
                    <a href="<?= app_url('user/register.php') ?>">Create account</a>
                <?php endif ?>
            </div>

            <div class="footer-links">
                <h2>Customer care</h2>
                <span><a href="<?= app_url('product/list.php') ?>">Freshly baked daily</a></span>
            </div>
        </div>

        <div class="pululu-footer-bottom">
            <span>&copy; <?= date('Y') ?> Pululu Bagel Shop. All rights reserved.</span>
            <a href="#top" data-back-to-top>Back to top ↑</a>
        </div>
    </footer>

    <script src="<?= app_url('js/navbar.js') ?>"></script>
</body>
</html>