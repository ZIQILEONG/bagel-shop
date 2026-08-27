<?php
include '../_base.php';

$id = req('id');
$stm = $_db->prepare("
    SELECT p.*, c.name AS category_name 
    FROM product p 
    LEFT JOIN category c ON p.category_id = c.id 
    WHERE p.id = ?
");
$stm->execute([$id]);
$p = $stm->fetch();

if (!$p) {
    redirect('product-listing.php');
}

// Check if this product is a 5-Bagel Set bundle
$isSet5 = (stripos($p->name, '5 Bagel') !== false || stripos($p->name, '5-Pack') !== false);

// 8 Available Bagel Flavours
$flavours = [
    'Blueberry Bagel',
    'Cinnamon Raisin Bagel',
    'Cream Cheese Bagel',
    'Everything Bagel',
    'Garlic Bagel',
    'Plain Bagel',
    'Sesame Bagel',
    'Whole Wheat Bagel'
];

// Fetch Detail Photos
$photoStm = $_db->prepare("
    SELECT photo 
    FROM product_photo 
    WHERE product_id = ? 
    ORDER BY sort_order ASC, id ASC
");
$photoStm->execute([$p->id]);
$detailPhotos = $photoStm->fetchAll(PDO::FETCH_COLUMN);

// Combine main photo with detail photos
$gallery = array_merge([$p->photo ?: 'default.jpg'], $detailPhotos);

// Fetch Reviews
$revStm = $_db->prepare("
    SELECT r.*, u.name AS user_name, u.photo AS user_photo 
    FROM product_review r 
    LEFT JOIN user u ON r.user_id = u.id 
    WHERE r.product_id = ? 
    ORDER BY r.created_at DESC
");
$revStm->execute([$p->id]);
$reviews = $revStm->fetchAll();

$totalReviews = count($reviews);
$avgRating = 0;
if ($totalReviews > 0) {
    $sum = array_sum(array_column($reviews, 'rating'));
    $avgRating = round($sum / $totalReviews, 1);
}

// Handle Add to Cart
if (is_post() && req('action') == 'add_to_cart') {
    $qty = (int)req('qty', 1);
    $selectedFlavours = req('flavours', []);

    if ($isSet5) {
        $totalSelected = array_sum($selectedFlavours);
        if ($totalSelected !== 5) {
            temp('error', 'Please select exactly 5 bagels for this bundle.');
            redirect("product-detail.php?id={$p->id}");
        }
    }

    if ($qty > 0 && $qty <= $p->stock) {
        // Save to cart session (storing flavor breakdown if it is a set)
        $cartItemKey = $isSet5 ? $p->id . '_' . md5(json_encode($selectedFlavours)) : $p->id;
        $_SESSION['cart'][$cartItemKey] = [
            'id'       => $p->id,
            'name'     => $p->name,
            'price'    => $p->price,
            'qty'      => ($_SESSION['cart'][$cartItemKey]['qty'] ?? 0) + $qty,
            'flavours' => $isSet5 ? array_filter($selectedFlavours) : null
        ];

        temp('info', "Added {$qty} item(s) to your cart!");
        redirect("product-detail.php?id={$p->id}");
    } else {
        temp('error', 'Invalid quantity requested.');
    }
}

// Handle Review Submission
if (is_post() && req('action') == 'submit_review') {
    auth();
    $rating = (int)req('rating');
    $review_text = trim(req('review_text'));

    if ($rating >= 1 && $rating <= 5) {
        $ins = $_db->prepare("INSERT INTO product_review (product_id, user_id, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())");
        $ins->execute([$p->id, $_user->id, $rating, $review_text]);
        temp('info', 'Thank you for your review!');
        redirect("product-detail.php?id={$p->id}");
    } else {
        temp('error', 'Please select a star rating.');
    }
}

$_title = htmlspecialchars($p->name) . ' | Pululu Bagel';
include '../_head.php';
?>

<style>
/* =========================================================
   PULULU 5-BAGELS FLAVOUR SELECTION & PDP UI
   ========================================================= */
:root {
    --bg-cream: #faf5f0;
    --card-bg: #ffffff;
    --primary-brown: #3e2619;
    --primary-orange: #cf7953;
    --primary-orange-hover: #b86440;
    --text-main: #4a3b32;
    --text-muted: #8c776b;
    --border-color: #f0e3dc;
    --border-input: #e6d3c8;
    --gold-star: #f5a623;
    --green-stock: #2e7d32;
    --red-alert: #cf4a30;
}

body {
    background-color: var(--bg-cream);
    color: var(--text-main);
}

.pdp-wrapper {
    max-width: 1060px;
    margin: 24px auto 60px;
    padding: 0 20px;
}

.pdp-breadcrumb {
    font-size: 13px;
    color: var(--text-muted);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.pdp-breadcrumb a {
    color: var(--text-muted);
    text-decoration: none;
    transition: color 0.15s;
}
.pdp-breadcrumb a:hover {
    color: var(--primary-orange);
}

.pdp-main-card {
    background: var(--card-bg);
    border-radius: 20px;
    border: 1px solid var(--border-color);
    padding: 36px;
    display: grid;
    grid-template-columns: 1fr 1.15fr;
    gap: 40px;
    box-shadow: 0 4px 20px rgba(62, 38, 25, 0.04);
    margin-bottom: 36px;
}

/* Gallery */
.pdp-gallery-wrap {
    display: flex;
    flex-direction: column;
    gap: 14px;
}
.pdp-carousel {
    position: relative;
    width: 100%;
    aspect-ratio: 1 / 1;
    border-radius: 16px;
    overflow: hidden;
    background: #fdfbf9;
    border: 1px solid var(--border-color);
}
.pdp-carousel-slide {
    display: none;
    width: 100%;
    height: 100%;
}
.pdp-carousel-slide.active {
    display: block;
}
.pdp-carousel-slide img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.pdp-nav-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 36px;
    height: 36px;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(4px);
    border: 1px solid var(--border-color);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-brown);
    cursor: pointer;
    transition: all 0.2s;
}
.pdp-nav-btn:hover {
    background: #ffffff;
    color: var(--primary-orange);
    transform: translateY(-50%) scale(1.08);
}
.pdp-nav-prev { left: 12px; }
.pdp-nav-next { right: 12px; }

.pdp-thumbs {
    display: flex;
    gap: 10px;
    overflow-x: auto;
    padding-bottom: 4px;
}
.pdp-thumb {
    width: 68px;
    height: 68px;
    border-radius: 10px;
    border: 1.5px solid var(--border-color);
    overflow: hidden;
    cursor: pointer;
    opacity: 0.65;
    transition: all 0.2s;
    flex-shrink: 0;
}
.pdp-thumb.active, .pdp-thumb:hover {
    opacity: 1;
    border-color: var(--primary-orange);
}
.pdp-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* Info */
.pdp-info-wrap {
    display: flex;
    flex-direction: column;
}
.pdp-tag {
    align-self: flex-start;
    background: #fdf2eb;
    color: #8d5b4c;
    font-size: 11.5px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 20px;
    margin-bottom: 10px;
    border: 1px solid #f7dfd3;
}
.pdp-title {
    font-size: 28px;
    font-weight: 800;
    color: var(--primary-brown);
    margin: 0 0 10px;
    line-height: 1.25;
}
.pdp-rating-summary {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
    font-size: 13.5px;
    color: var(--text-muted);
}
.pdp-stars {
    color: var(--gold-star);
    letter-spacing: 1px;
}
.pdp-price {
    font-size: 26px;
    font-weight: 800;
    color: var(--primary-orange);
    margin-bottom: 18px;
}
.pdp-description {
    font-size: 14.5px;
    line-height: 1.65;
    color: var(--text-main);
    margin-bottom: 20px;
}

/* =========================================================
   CHOICE OF BAGEL SELECTION CARD
   ========================================================= */
.flavour-selector-card {
    background: #fffdfc;
    border: 1.5px solid var(--border-color);
    border-radius: 14px;
    padding: 16px 20px;
    margin-bottom: 24px;
}
.flavour-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding-bottom: 12px;
    border-bottom: 1px solid #f6eee9;
    margin-bottom: 14px;
}
.flavour-title {
    font-size: 14.5px;
    font-weight: 800;
    color: var(--primary-brown);
}
.flavour-subtitle {
    font-size: 11.5px;
    color: var(--text-muted);
    margin-top: 2px;
}
.flavour-badge-counter {
    font-size: 12.5px;
    font-weight: 700;
    color: var(--red-alert);
}
.flavour-badge-counter.ready {
    color: var(--green-stock);
}

.flavour-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.flavour-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.flavour-name {
    font-size: 13.5px;
    font-weight: 700;
    color: var(--primary-brown);
}

/* Steppers inside Flavour Selector */
.flavour-stepper {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.flavour-btn {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 1.5px solid #dfcfc7;
    background: #ffffff;
    color: #8c7365;
    font-size: 16px;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s ease;
}
.flavour-btn:hover:not(:disabled) {
    border-color: var(--primary-orange);
    color: var(--primary-orange);
    background: #fff8f5;
}
.flavour-btn:disabled {
    opacity: 0.35;
    cursor: not-allowed;
    border-color: #ebdcd5;
}
.flavour-qty-box {
    width: 32px;
    height: 28px;
    border: 1px solid #ebdcd5;
    border-radius: 6px;
    text-align: center;
    font-size: 13px;
    font-weight: 700;
    color: var(--primary-brown);
    background: #fff;
    outline: none;
}

/* Purchase Actions */
.pdp-action-box {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
}
.pdp-qty-stepper {
    display: inline-flex;
    align-items: center;
    border: 1.5px solid var(--border-input);
    border-radius: 10px;
    background: #ffffff;
    overflow: hidden;
}
.pdp-qty-btn {
    width: 38px;
    height: 42px;
    background: #fbf7f4;
    border: none;
    color: var(--primary-brown);
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    transition: background 0.15s;
}
.pdp-qty-btn:hover {
    background: #f0e3dc;
}
.pdp-qty-input {
    width: 44px;
    height: 42px;
    border: none;
    text-align: center;
    font-size: 14.5px;
    font-weight: 700;
    color: var(--primary-brown);
    outline: none;
}
.pdp-btn-add-cart {
    flex: 1;
    height: 44px;
    background: var(--primary-orange);
    color: #ffffff;
    border: none;
    border-radius: 10px;
    font-size: 14.5px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.pdp-btn-add-cart:hover:not(:disabled) {
    background: var(--primary-orange-hover);
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(207, 121, 83, 0.3);
}
.pdp-btn-add-cart:disabled {
    background: #d4bfb4;
    cursor: not-allowed;
    box-shadow: none;
    transform: none;
}

.pdp-stock-status {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12.5px;
    font-weight: 600;
    color: var(--green-stock);
    margin-bottom: 20px;
}
.pdp-stock-dot {
    width: 7px;
    height: 7px;
    background: var(--green-stock);
    border-radius: 50%;
}
.pdp-features {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    background: #fdfbf9;
    border: 1px solid var(--border-color);
    padding: 14px 16px;
    border-radius: 12px;
    font-size: 12.5px;
    color: var(--text-muted);
}
.pdp-feature-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    color: var(--primary-brown);
}

/* Reviews Section */
.pdp-reviews-container {
    background: var(--card-bg);
    border-radius: 20px;
    border: 1px solid var(--border-color);
    padding: 36px;
    box-shadow: 0 4px 20px rgba(62, 38, 25, 0.04);
}
.pdp-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--border-color);
}
.pdp-section-title {
    font-size: 20px;
    font-weight: 800;
    color: var(--primary-brown);
}

.pdp-review-form-card {
    background: #fdfaf8;
    border: 1.5px solid var(--border-color);
    border-radius: 14px;
    padding: 24px;
    margin-bottom: 32px;
}
.pdp-star-rating-select {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 14px;
}
.pdp-star-label {
    font-size: 24px;
    color: #dfcfc7;
    cursor: pointer;
    transition: color 0.15s;
}
.pdp-star-label.active {
    color: var(--gold-star);
}
.pdp-review-textarea {
    width: 100%;
    box-sizing: border-box;
    border: 1.5px solid var(--border-input);
    border-radius: 10px;
    padding: 12px 14px;
    font-size: 13.5px;
    color: var(--text-main);
    background: #ffffff;
    resize: vertical;
    outline: none;
    margin-bottom: 14px;
    font-family: inherit;
}
.pdp-btn-submit-review {
    background: var(--primary-orange);
    color: #ffffff;
    border: none;
    padding: 10px 22px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}
.pdp-btn-submit-review:hover {
    background: var(--primary-orange-hover);
}

.pdp-reviews-list {
    display: flex;
    flex-direction: column;
    gap: 18px;
}
.pdp-review-item {
    padding: 18px 20px;
    border-radius: 12px;
    border: 1px solid var(--border-color);
    background: #fffdfc;
}
.pdp-review-user {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}
.pdp-user-info {
    display: flex;
    align-items: center;
    gap: 10px;
}
.pdp-user-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
    background: #eee;
}
.pdp-user-name {
    font-size: 13.5px;
    font-weight: 700;
    color: var(--primary-brown);
}
.pdp-review-date {
    font-size: 11.5px;
    color: var(--text-muted);
}
.pdp-empty-reviews {
    text-align: center;
    padding: 36px 20px;
    color: var(--text-muted);
    font-size: 14px;
}

.pdp-back-box {
    margin-top: 28px;
}
.pdp-btn-back {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #ffffff;
    border: 1.5px solid var(--border-color);
    color: var(--primary-brown);
    padding: 10px 20px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.15s;
}
.pdp-btn-back:hover {
    background: #fbf6f3;
    border-color: var(--primary-orange);
}

@media (max-width: 820px) {
    .pdp-main-card {
        grid-template-columns: 1fr;
        padding: 24px;
    }
}
</style>

<div class="pdp-wrapper">
    <!-- Breadcrumb -->
    <div class="pdp-breadcrumb">
        <a href="/">Home</a>
        <span>&rsaquo;</span>
        <a href="list.php">Shop Bagels</a>
        <span>&rsaquo;</span>
        <span><?= htmlspecialchars($p->name) ?></span>
    </div>

    <!-- Main Showcase Card -->
    <div class="pdp-main-card">
        <!-- Gallery / Carousel -->
        <div class="pdp-gallery-wrap">
            <div class="pdp-carousel" id="pdpCarousel">
                <?php foreach ($gallery as $index => $img): ?>
                    <div class="pdp-carousel-slide <?= $index === 0 ? 'active' : '' ?>">
                        <img src="/products/<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($p->name) ?>">
                    </div>
                <?php endforeach; ?>

                <?php if (count($gallery) > 1): ?>
                    <button type="button" class="pdp-nav-btn pdp-nav-prev" onclick="changeSlide(-1)">&#10094;</button>
                    <button type="button" class="pdp-nav-btn pdp-nav-next" onclick="changeSlide(1)">&#10095;</button>
                <?php endif; ?>
            </div>

            <?php if (count($gallery) > 1): ?>
                <div class="pdp-thumbs">
                    <?php foreach ($gallery as $index => $img): ?>
                        <div class="pdp-thumb <?= $index === 0 ? 'active' : '' ?>" onclick="goToSlide(<?= $index ?>)">
                            <img src="/products/<?= htmlspecialchars($img) ?>" alt="Thumbnail">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Product Information & Order Form -->
        <div class="pdp-info-wrap">
            <div class="pdp-tag"><?= htmlspecialchars($p->category_name ?? 'Bundle Set') ?></div>
            <h1 class="pdp-title"><?= htmlspecialchars($p->name) ?></h1>

            <div class="pdp-rating-summary">
                <span class="pdp-stars">
                    <?= str_repeat('★', (int)round($avgRating)) ?><?= str_repeat('☆', 5 - (int)round($avgRating)) ?>
                </span>
                <span>(<?= $totalReviews ?> <?= $totalReviews === 1 ? 'review' : 'reviews' ?>)</span>
            </div>

            <div class="pdp-price">RM <?= number_format($p->price, 2) ?></div>

            <div class="pdp-description">
                <?= nl2br(htmlspecialchars($p->description)) ?>
            </div>

            <form method="post" id="addToCartForm">
                <input type="hidden" name="action" value="add_to_cart">

                <!-- CHOICE OF BAGEL MODULE (EXCLUSIVELY FOR 5 BAGELS SET) -->
                <?php if ($isSet5): ?>
                    <div class="flavour-selector-card">
                        <div class="flavour-header">
                            <div>
                                <div class="flavour-title">Choice of Bagel</div>
                                <div class="flavour-subtitle">Select exactly 5 bagels for your bundle</div>
                            </div>
                            <div class="flavour-badge-counter" id="selectedCountBadge">
                                <span id="selectedCountText">0</span>/5 selected
                            </div>
                        </div>

                        <div class="flavour-list">
                            <?php foreach ($flavours as $flavour): ?>
                                <div class="flavour-row">
                                    <span class="flavour-name"><?= htmlspecialchars($flavour) ?></span>
                                    <div class="flavour-stepper">
                                        <button type="button" class="flavour-btn btn-minus" onclick="updateFlavourQty('<?= htmlspecialchars($flavour) ?>', -1)">−</button>
                                        <input type="text" name="flavours[<?= htmlspecialchars($flavour) ?>]" id="f_<?= md5($flavour) ?>" class="flavour-qty-box" value="0" readonly>
                                        <button type="button" class="flavour-btn btn-plus" onclick="updateFlavourQty('<?= htmlspecialchars($flavour) ?>', 1)">+</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Quantity and Add to Cart -->
                <div class="pdp-action-box">
                    <div class="pdp-qty-stepper">
                        <button type="button" class="pdp-qty-btn" onclick="stepQty(-1)">−</button>
                        <input type="number" name="qty" id="qtyInput" class="pdp-qty-input" value="1" min="1" max="<?= $p->stock ?>" readonly>
                        <button type="button" class="pdp-qty-btn" onclick="stepQty(1)">+</button>
                    </div>

                    <button type="submit" class="pdp-btn-add-cart" id="addToCartBtn" <?= $isSet5 ? 'disabled' : '' ?>>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                        </svg>
                        <span id="btnLabelText"><?= $isSet5 ? 'Select 5 Bagels to Add' : 'Add to Cart' ?> &bull; RM <span id="btnTotalText"><?= number_format($p->price, 2) ?></span></span>
                    </button>
                </div>
            </form>

            <div class="pdp-stock-status">
                <span class="pdp-stock-dot"></span>
                In Stock &bull; <?= (int)$p->stock ?> sets available
            </div>

            <div class="pdp-features">
                <div class="pdp-feature-item"><span>🥖</span> Freshly Baked Daily</div>
                <div class="pdp-feature-item"><span>🚚</span> Local Same-Day Delivery</div>
            </div>
        </div>
    </div>

    <!-- Reviews Container -->
    <div class="pdp-reviews-container">
        <div class="pdp-section-header">
            <div class="pdp-section-title">Ratings & Reviews</div>
        </div>

        <div class="pdp-review-form-card">
            <form method="post">
                <input type="hidden" name="action" value="submit_review">
                
                <div class="pdp-star-rating-select">
                    <span style="font-size: 13px; font-weight: 700; color: var(--primary-brown); margin-right: 6px;">Your Rating:</span>
                    <input type="hidden" name="rating" id="selectedRatingInput" value="5">
                    <?php for ($s = 1; $s <= 5; $s++): ?>
                        <span class="pdp-star-label active" data-value="<?= $s ?>" onclick="setRating(<?= $s ?>)">★</span>
                    <?php endfor; ?>
                </div>

                <textarea name="review_text" rows="3" class="pdp-review-textarea" placeholder="Share your experience with this bagel set..."></textarea>
                
                <button type="submit" class="pdp-btn-submit-review">Submit Review</button>
            </form>
        </div>

        <?php if (!empty($reviews)): ?>
            <div class="pdp-reviews-list">
                <?php foreach ($reviews as $rev): ?>
                    <div class="pdp-review-item">
                        <div class="pdp-review-user">
                            <div class="pdp-user-info">
                                <img src="/uploads/<?= htmlspecialchars($rev->user_photo ?: 'default-avatar.png') ?>" class="pdp-user-avatar" alt="">
                                <div>
                                    <div class="pdp-user-name"><?= htmlspecialchars($rev->user_name ?: 'Customer') ?></div>
                                    <div class="pdp-stars" style="font-size: 12px;">
                                        <?= str_repeat('★', (int)$rev->rating) ?><?= str_repeat('☆', 5 - (int)$rev->rating) ?>
                                    </div>
                                </div>
                            </div>
                            <span class="pdp-review-date"><?= date('M d, Y', strtotime($rev->created_at)) ?></span>
                        </div>
                        <p class="pdp-review-body"><?= nl2br(htmlspecialchars($rev->review)) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="pdp-empty-reviews">
                <div class="pdp-empty-icon" style="font-size:32px; margin-bottom:8px;">🥯</div>
                <div>Be the first to review this bagel!</div>
            </div>
        <?php endif; ?>

        <div class="pdp-back-box">
            <a href="product-listing.php" class="pdp-btn-back">&larr; Back to Shop</a>
        </div>
    </div>
</div>

<script>
// Carousel Logic
let currentSlide = 0;
const slides = document.querySelectorAll('.pdp-carousel-slide');
const thumbs = document.querySelectorAll('.pdp-thumb');

function goToSlide(index) {
    if (slides.length === 0) return;
    slides[currentSlide].classList.remove('active');
    if (thumbs.length) thumbs[currentSlide].classList.remove('active');

    currentSlide = (index + slides.length) % slides.length;

    slides[currentSlide].classList.add('active');
    if (thumbs.length) thumbs[currentSlide].classList.add('active');
}

function changeSlide(direction) {
    goToSlide(currentSlide + direction);
}

// Stepper Quantity & Total
const unitPrice = <?= (float)$p->price ?>;
const maxStock = <?= (int)$p->stock ?>;
const isSet5 = <?= $isSet5 ? 'true' : 'false' ?>;

function stepQty(amount) {
    const input = document.getElementById('qtyInput');
    const totalText = document.getElementById('btnTotalText');
    let val = parseInt(input.value) + amount;

    if (val >= 1 && val <= maxStock) {
        input.value = val;
        totalText.textContent = (val * unitPrice).toFixed(2);
    }
}

// Choice of Bagel (5 Set Logic)
const flavourInputs = document.querySelectorAll('.flavour-qty-box');
const maxSelection = 5;

function updateFlavourQty(flavourName, delta) {
    // Generate simple ID lookup matching PHP md5
    const row = Array.from(document.querySelectorAll('.flavour-row')).find(r => 
        r.querySelector('.flavour-name').textContent.trim() === flavourName
    );
    if (!row) return;

    const input = row.querySelector('.flavour-qty-box');
    let currentQty = parseInt(input.value) || 0;
    let totalSelected = getTotalSelectedFlavours();

    // Check bounds
    if (delta > 0 && totalSelected >= maxSelection) return;
    if (delta < 0 && currentQty <= 0) return;

    input.value = Math.max(0, currentQty + delta);
    syncFlavourState();
}

function getTotalSelectedFlavours() {
    let sum = 0;
    flavourInputs.forEach(inp => {
        sum += parseInt(inp.value) || 0;
    });
    return sum;
}

function syncFlavourState() {
    if (!isSet5) return;

    const total = getTotalSelectedFlavours();
    const countText = document.getElementById('selectedCountText');
    const countBadge = document.getElementById('selectedCountBadge');
    const addBtn = document.getElementById('addToCartBtn');
    const btnLabel = document.getElementById('btnLabelText');
    const totalText = document.getElementById('btnTotalText');

    if (countText) countText.textContent = total;

    // Toggle Plus buttons disabled state if limit 5 reached
    document.querySelectorAll('.btn-plus').forEach(btn => {
        btn.disabled = (total >= maxSelection);
    });

    // Toggle Minus buttons state
    flavourInputs.forEach(inp => {
        const row = inp.closest('.flavour-row');
        const minusBtn = row.querySelector('.btn-minus');
        minusBtn.disabled = (parseInt(inp.value) <= 0);
    });

    if (total === maxSelection) {
        countBadge.classList.add('ready');
        addBtn.disabled = false;
        btnLabel.innerHTML = `Add to Cart &bull; RM <span id="btnTotalText">${(parseInt(document.getElementById('qtyInput').value) * unitPrice).toFixed(2)}</span>`;
    } else {
        countBadge.classList.remove('ready');
        addBtn.disabled = true;
        btnLabel.innerHTML = `Select 5 Bagels to Add (${total}/5)`;
    }
}

// Initialize on load
document.addEventListener('DOMContentLoaded', () => {
    syncFlavourState();
});

// Interactive Star Ratings
function setRating(val) {
    document.getElementById('selectedRatingInput').value = val;
    const stars = document.querySelectorAll('.pdp-star-label');
    stars.forEach((star, idx) => {
        if (idx < val) {
            star.classList.add('active');
        } else {
            star.classList.remove('active');
        }
    });
}
</script>

<?php
include '../_foot.php';
?>