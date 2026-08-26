<?php
include '_base.php';

$featured_products = $_db
    ->query('SELECT * FROM product WHERE stock > 0 ORDER BY id LIMIT 4')
    ->fetchAll();

$_title = 'Freshly Baked Bagels | Pululu Bagel';
$_body_class = 'pululu-home-page';
include '_head.php';
?>
//testing
<section class="home-hero" aria-labelledby="home-title">
    <div class="home-hero-copy">
        <span class="section-eyebrow">Fresh from our oven</span>
        <h1 id="home-title">Better mornings begin with a warm Pululu bagel.</h1>
        <p>Small-batch bagels with a chewy centre, golden crust and flavours made for every kind of craving.</p>

        <div class="hero-actions">
            <a class="button button-primary" href="<?= app_url('product/list.php') ?>">Shop fresh bagels</a>
            <?php if ($_user?->role === 'Member'): ?>
                <a class="button button-secondary" href="<?= app_url('order/history.php') ?>">Track my orders</a>
            <?php else: ?>
                <a class="button button-secondary" href="<?= app_url('user/register.php') ?>">Create an account</a>
            <?php endif ?>
        </div>

        <ul class="hero-highlights" aria-label="Pululu benefits">
            <li><b>Baked daily</b><span>Never mass produced</span></li>
            <li><b>Easy ordering</b><span>Clear cart and checkout</span></li>
            <li><b>Reward points</b><span>Earn as a member</span></li>
        </ul>
    </div>

    <div class="home-hero-visual">
        <div class="hero-image-frame">
            <img src="<?= app_url('photos/products/bagelA.png') ?>" alt="A colourful selection of freshly baked Pululu bagels">
        </div>
        <div class="hero-sticker hero-sticker-top"><b>10% off</b><span>your next order</span></div>
        <div class="hero-sticker hero-sticker-bottom"><span>Made with care</span><b>Fresh every day</b></div>
    </div>
</section>

<section class="home-promise" aria-label="Our bakery promise">
    <div><span>01</span><p><b>Choose your favourites</b><small>Browse clear prices and availability.</small></p></div>
    <div><span>02</span><p><b>Order in a few clicks</b><small>Adjust quantities directly in your cart.</small></p></div>
    <div><span>03</span><p><b>Enjoy them fresh</b><small>Prepared for pickup or delivery.</small></p></div>
</section>

<section class="home-section" aria-labelledby="favourites-title">
    <div class="section-heading-row">
        <div>
            <span class="section-eyebrow">Customer favourites</span>
            <h2 id="favourites-title">Find your new favourite</h2>
            <p>Fresh choices from the Pululu menu, ready for your next order.</p>
        </div>
        <a class="text-link" href="<?= app_url('product/list.php') ?>">View the full menu <span>→</span></a>
    </div>

    <?php if ($featured_products): ?>
        <div class="featured-product-grid">
            <?php foreach ($featured_products as $featured): ?>
                <article class="featured-product-card">
                    <a class="featured-product-image" href="<?= app_url('product/detail.php?id=' . urlencode($featured->id)) ?>">
                        <img src="<?= app_url('products/' . rawurlencode($featured->photo)) ?>" alt="<?= htmlspecialchars($featured->name, ENT_QUOTES, 'UTF-8') ?>">
                        <span>View bagel</span>
                    </a>
                    <div class="featured-product-copy">
                        <h3><a href="<?= app_url('product/detail.php?id=' . urlencode($featured->id)) ?>"><?= htmlspecialchars($featured->name, ENT_QUOTES, 'UTF-8') ?></a></h3>
                        <p>RM <?= number_format((float) $featured->price, 2) ?></p>
                    </div>
                </article>
            <?php endforeach ?>
        </div>
    <?php else: ?>
        <div class="empty-state"><h3>Fresh batches are coming soon</h3><p>Please check the menu again shortly.</p></div>
    <?php endif ?>
</section>

<section class="home-story" aria-labelledby="story-title">
    <div class="home-story-image">
        <img src="<?= app_url('photos/products/bagelD.jpg') ?>" alt="Freshly baked Pululu bagels in a basket">
    </div>
    <div class="home-story-copy">
        <span class="section-eyebrow">The Pululu way</span>
        <h2 id="story-title">Comforting, cheerful and baked to share.</h2>
        <p>Our little bear mascot represents what Pululu is about: familiar flavours, warm service and food that makes an ordinary day feel special.</p>
        <a class="button button-primary" href="<?= app_url('product/list.php') ?>">Explore the menu</a>
    </div>
</section>

<section class="store-location" id="location">

    <div class="store-location-info">

        <span class="section-eyebrow">
            Visit Pululu
        </span>

        <h2>Find our store</h2>

        <p>
            Visit us and enjoy freshly baked Pululu Bagels.
        </p>

        <address>
            TAR UMT Block D<br>
            Kuala Lumpur, Malaysia
        </address>

        <a
            class="location-button"
            href="https://www.google.com/maps/search/?api=1&amp;query=3.2168553,101.7266496"
            target="_blank"
            rel="noopener"
        >
            Open in Google Maps →
        </a>

    </div>

    <div class="store-map">
        <iframe
            src="https://www.google.com/maps?q=3.2168553,101.7266496&amp;z=18&amp;output=embed"
            loading="lazy"
            allowfullscreen
            referrerpolicy="no-referrer-when-downgrade"
            title="TAR UMT Block D location"
        ></iframe>
    </div>

</section>

<details class="demo-access">
    <summary>Assignment demo access</summary>
    <div class="demo-access-content">
        <p>Use these accounts only when demonstrating the assignment locally.</p>
        <div class="table-scroll">
            <table class="table">
                <thead><tr><th>Email</th><th>Password</th><th>Role</th></tr></thead>
                <tbody>
                    <tr><td>admin@bagel.com</td><td>123456</td><td>Admin</td></tr>
                    <tr><td>member1@bagel.com</td><td>123456</td><td>Member</td></tr>
                    <tr><td>member2@bagel.com</td><td>123456</td><td>Member</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</details>

<?php include '_foot.php'; ?>