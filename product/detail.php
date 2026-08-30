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
    redirect('list.php');
}

// Universal YouTube Embed URL Parser
function get_youtube_embed_url($url) {
    $url = trim($url ?? '');
    if ($url === '') return null;

    $videoId = null;
    if (preg_match('/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=|shorts\/))([\w-]{11})/i', $url, $matches)) {
        $videoId = $matches[1];
    }

    return $videoId ? "https://www.youtube.com/embed/{$videoId}?rel=0" : null;
}

$rawVideoUrl   = $p->video_url ?? $p->video ?? $p->youtube_url ?? '';
$embedVideoUrl = get_youtube_embed_url($rawVideoUrl);

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

// Rating Breakdown Counts
$ratingCounts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
foreach ($reviews as $r) {
    $rt = (int)$r->rating;
    if (isset($ratingCounts[$rt])) $ratingCounts[$rt]++;
}

// Calculate Cart Totals
$cart = get_cart();
$totalCartItems = 0;
foreach ($cart as $prodId => $entry) {
    $totalCartItems += is_array($entry) ? (int)($entry['qty'] ?? 0) : (int)$entry;
}
$currentThisItemQty = is_array($cart[$p->id] ?? null) ? (int)($cart[$p->id]['qty'] ?? 0) : (int)($cart[$p->id] ?? 0);

// ==========================================
// 🛒 HANDLE ADD TO CART (MAX 10 PER ITEM & MAX 100 TOTAL CART)
// ==========================================
if (is_post() && req('action') === 'add_to_cart') {
    if (!isset($_user) || $_user->role !== 'Member') {
        temp('info', 'Please log in to add items to your cart.');
        redirect('/login.php');
    }

    $unit = (int)req('unit', 1);

    if ($unit < 1) {
        temp('error', 'Please select at least 1 item.');
        redirect("detail.php?id={$p->id}");
    }

    // 🔒 1. Check if total cart is full (Max 100 total items)
    if ($totalCartItems >= 100) {
        temp('error', 'Cannot add to cart. Your shopping cart is full (maximum 100 items limit reached).');
        redirect("detail.php?id={$p->id}");
    }

    if (($totalCartItems + $unit) > 100) {
        $allowedAdd = 100 - $totalCartItems;
        temp('error', "Cannot add {$unit} items. You can only add {$allowedAdd} more item(s) before reaching the 100-item cart limit.");
        redirect("detail.php?id={$p->id}");
    }

    // 🔒 2. Check individual item limit (Max 10 per bagel)
    $newThisItemTotal = $currentThisItemQty + $unit;
    if ($newThisItemTotal > 10) {
        temp('error', "Maximum order limit is 10 per bagel. You already have {$currentThisItemQty} in your cart.");
        redirect("detail.php?id={$p->id}");
    }

    // 🔒 3. Check stock availability
    if ($newThisItemTotal > (int)$p->stock) {
        temp('error', 'Sorry, not enough stock available.');
        redirect("detail.php?id={$p->id}");
    }

    update_cart($p->id, $newThisItemTotal);
    temp('info', "Added {$unit} item(s) to your cart!");
    redirect("detail.php?id={$p->id}");
}

// ==========================================
// ⭐ HANDLE REVIEW SUBMISSION (ALLOW ALL REVIEWS)
// ==========================================
if (is_post() && req('action') === 'submit_review') {
    auth('Member');
    $rating = (int)req('rating');
    $review_text = trim(req('review_text'));

    if ($rating >= 1 && $rating <= 5) {
        try {
            $ins = $_db->prepare("
                INSERT INTO product_review (product_id, user_id, rating, comment, created_at) 
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), created_at = NOW()
            ");
            $ins->execute([$p->id, $_user->id, $rating, $review_text]);

            update_product_rating($p->id);

            temp('info', 'Thank you for your review!');
            redirect("detail.php?id={$p->id}");
        } catch (PDOException $e) {
            temp('error', 'Unable to submit review. Please try again.');
            redirect("detail.php?id={$p->id}");
        }
    } else {
        temp('error', 'Please select a valid star rating.');
        redirect("detail.php?id={$p->id}");
    }
}

$_title = htmlspecialchars($p->name) . ' | Pululu Bagel';
include '../_head.php';
?>

<style>
/* =========================================================
   PULULU PREMIUM BAKERY PDP DESIGN SYSTEM
   ========================================================= */
:root {
    --bg-warm: #faf6f0;
    --card-bg: #ffffff;
    --primary-brown: #331f14;
    --primary-orange: #cf7349;
    --primary-orange-hover: #ba6036;
    --accent-cream: #fbf5ef;
    --text-main: #43332b;
    --text-muted: #8e7a6f;
    --border-color: #ede0d8;
    --border-subtle: #f5ebe4;
    --gold-star: #e99e28;
    --green-stock: #2b7a4b;
    --radius-lg: 24px;
    --radius-md: 16px;
    --radius-sm: 10px;
    --shadow-soft: 0 6px 24px rgba(67, 43, 30, 0.05);
}

body {
    background-color: var(--bg-warm);
    color: var(--text-main);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    -webkit-font-smoothing: antialiased;
}

.pdp-wrapper {
    max-width: 1080px;
    margin: 20px auto 70px;
    padding: 0 24px;
}

/* Breadcrumb */
.pdp-breadcrumb {
    font-size: 13.5px;
    font-weight: 500;
    color: var(--text-muted);
    margin-bottom: 22px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.pdp-breadcrumb a {
    color: var(--text-muted);
    text-decoration: none;
    transition: color 0.15s ease;
}
.pdp-breadcrumb a:hover {
    color: var(--primary-orange);
}
.pdp-breadcrumb .divider {
    font-size: 11px;
    opacity: 0.6;
}
.pdp-breadcrumb .current {
    color: var(--primary-brown);
    font-weight: 600;
}

/* 1. Main Showcase Card */
.pdp-main-card {
    background: var(--card-bg);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-color);
    padding: 40px;
    display: grid;
    grid-template-columns: 1fr 1.1fr;
    gap: 48px;
    box-shadow: var(--shadow-soft);
    margin-bottom: 36px;
}

/* Carousel */
.pdp-gallery-wrap {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.pdp-carousel {
    position: relative;
    width: 100%;
    aspect-ratio: 1 / 1;
    border-radius: var(--radius-md);
    overflow: hidden;
    background: #fdfaf8;
    border: 1px solid var(--border-color);
    box-shadow: 0 4px 14px rgba(67, 43, 30, 0.04);
}
.pdp-carousel-slide {
    display: none;
    width: 100%;
    height: 100%;
}
.pdp-carousel-slide.active {
    display: block;
    animation: fadeIn 0.3s ease;
}
.pdp-carousel-slide img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    image-rendering: auto;
}

@keyframes fadeIn {
    from { opacity: 0.6; transform: scale(1.02); }
    to { opacity: 1; transform: scale(1); }
}

.pdp-nav-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(6px);
    border: 1px solid var(--border-color);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-brown);
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    transition: all 0.2s ease;
}
.pdp-nav-btn:hover {
    background: #ffffff;
    color: var(--primary-orange);
    transform: translateY(-50%) scale(1.1);
}
.pdp-nav-prev { left: 14px; }
.pdp-nav-next { right: 14px; }

.pdp-thumbs {
    display: flex;
    gap: 12px;
    overflow-x: auto;
    padding-bottom: 6px;
}
.pdp-thumb {
    width: 72px;
    height: 72px;
    border-radius: var(--radius-sm);
    border: 2px solid var(--border-color);
    overflow: hidden;
    cursor: pointer;
    opacity: 0.6;
    transition: all 0.2s ease;
    flex-shrink: 0;
    background: #fff;
}
.pdp-thumb.active {
    opacity: 1;
    border-color: var(--primary-orange);
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(207, 115, 73, 0.2);
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
.pdp-category-pill {
    align-self: flex-start;
    background: #fbf0e8;
    color: #9c502b;
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 5px 12px;
    border-radius: 999px;
    margin-bottom: 12px;
    border: 1px solid #f3dacd;
}
.pdp-title {
    font-size: 32px;
    font-weight: 800;
    color: var(--primary-brown);
    margin: 0 0 10px;
    line-height: 1.2;
}

.pdp-rating-summary {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 18px;
    font-size: 14px;
    color: var(--text-muted);
}
.pdp-stars {
    color: var(--gold-star);
    letter-spacing: 2px;
}
.pdp-rating-num {
    font-weight: 700;
    color: var(--primary-brown);
}

.pdp-price-row {
    display: flex;
    align-items: baseline;
    margin-bottom: 18px;
}
.pdp-price {
    font-size: 30px;
    font-weight: 800;
    color: var(--primary-orange);
}

.pdp-description {
    font-size: 14.5px;
    line-height: 1.7;
    color: var(--text-main);
    margin-bottom: 26px;
    padding-bottom: 22px;
    border-bottom: 1px solid var(--border-subtle);
}

/* Quantity & Add to Cart */
.pdp-action-box {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 22px;
}
.pdp-qty-stepper {
    display: inline-flex;
    align-items: center;
    border: 1.5px solid var(--border-color);
    border-radius: 12px;
    background: #ffffff;
    overflow: hidden;
    height: 48px;
}
.pdp-qty-btn {
    width: 42px;
    height: 100%;
    background: #fbf7f4;
    border: none;
    color: var(--primary-brown);
    font-size: 18px;
    font-weight: bold;
    cursor: pointer;
    transition: background 0.15s;
}
.pdp-qty-btn:hover {
    background: #f1e4dc;
}
.pdp-qty-input {
    width: 48px;
    height: 100%;
    border: none;
    text-align: center;
    font-size: 15px;
    font-weight: 700;
    color: var(--primary-brown);
    outline: none;
}
.pdp-btn-add-cart {
    flex: 1;
    height: 48px;
    background: var(--primary-orange);
    color: #ffffff;
    border: none;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    box-shadow: 0 4px 14px rgba(207, 115, 73, 0.25);
    text-decoration: none;
}
.pdp-btn-add-cart:hover {
    background: var(--primary-orange-hover);
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(207, 115, 73, 0.35);
}

.pdp-stock-status {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 600;
    color: var(--green-stock);
    margin-bottom: 22px;
}
.pdp-stock-dot {
    width: 8px;
    height: 8px;
    background: var(--green-stock);
    border-radius: 50%;
}

.pdp-features {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    background: var(--accent-cream);
    border: 1px solid var(--border-color);
    padding: 14px 18px;
    border-radius: var(--radius-sm);
}
.pdp-feature-item {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    font-weight: 600;
    color: var(--primary-brown);
}

/* 2. YouTube Video Card */
.pdp-video-card {
    background: var(--card-bg);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-color);
    padding: 36px;
    box-shadow: var(--shadow-soft);
    margin-bottom: 36px;
}
.pdp-video-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--border-subtle);
}
.pdp-video-title {
    font-size: 20px;
    font-weight: 800;
    color: var(--primary-brown);
}
.pdp-video-responsive-wrap {
    max-width: 860px;
    margin: 0 auto;
}
.pdp-video-responsive {
    position: relative;
    width: 100%;
    padding-bottom: 56.25%;
    height: 0;
    overflow: hidden;
    border-radius: var(--radius-md);
    background: #000;
}
.pdp-video-responsive iframe {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    border: 0;
}

/* 3. Reviews Container */
.pdp-reviews-container {
    background: var(--card-bg);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-color);
    padding: 40px;
    box-shadow: var(--shadow-soft);
}
.pdp-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 28px;
    padding-bottom: 18px;
    border-bottom: 1px solid var(--border-color);
}
.pdp-section-title {
    font-size: 22px;
    font-weight: 800;
    color: var(--primary-brown);
}

.pdp-rating-overview {
    display: grid;
    grid-template-columns: 200px 1fr;
    gap: 32px;
    background: var(--accent-cream);
    padding: 24px 28px;
    border-radius: var(--radius-md);
    border: 1px solid var(--border-color);
    margin-bottom: 32px;
    align-items: center;
}
.pdp-big-score {
    text-align: center;
    border-right: 1px solid var(--border-color);
    padding-right: 24px;
}
.pdp-big-number {
    font-size: 46px;
    font-weight: 900;
    color: var(--primary-brown);
    line-height: 1;
    margin-bottom: 6px;
}
.pdp-score-sub {
    font-size: 13px;
    color: var(--text-muted);
    margin-top: 4px;
}

.pdp-rating-bars {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.pdp-bar-row {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 12.5px;
    color: var(--text-muted);
    font-weight: 600;
}
.pdp-bar-track {
    flex: 1;
    height: 7px;
    background: #eadece;
    border-radius: 999px;
    overflow: hidden;
}
.pdp-bar-fill {
    height: 100%;
    background: var(--gold-star);
    border-radius: 999px;
}

.pdp-review-form-card {
    background: #ffffff;
    border: 1.5px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 24px;
    margin-bottom: 36px;
}
.pdp-star-rating-select {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 14px;
}
.pdp-star-label {
    font-size: 26px;
    color: #e2d2c8;
    cursor: pointer;
    transition: transform 0.15s ease, color 0.15s ease;
    user-select: none;
}
.pdp-star-label:hover {
    transform: scale(1.2);
}
.pdp-star-label.active {
    color: var(--gold-star);
}
.pdp-review-textarea {
    width: 100%;
    box-sizing: border-box;
    border: 1.5px solid var(--border-color);
    border-radius: 12px;
    padding: 14px 16px;
    font-size: 14px;
    color: var(--text-main);
    background: #fdfaf8;
    resize: vertical;
    outline: none;
    margin-bottom: 14px;
    font-family: inherit;
}
.pdp-review-textarea:focus {
    background: #ffffff;
    border-color: var(--primary-orange);
    box-shadow: 0 0 0 3px rgba(207, 115, 73, 0.12);
}
.pdp-btn-submit-review {
    background: var(--primary-orange);
    color: #ffffff;
    border: none;
    padding: 11px 24px;
    border-radius: 10px;
    font-size: 13.5px;
    font-weight: 700;
    cursor: pointer;
}
.pdp-btn-submit-review:hover {
    background: var(--primary-orange-hover);
}

.pdp-reviews-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.pdp-review-item {
    padding: 20px 24px;
    border-radius: var(--radius-sm);
    border: 1px solid var(--border-color);
    background: #ffffff;
}
.pdp-review-user {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}
.pdp-user-info {
    display: flex;
    align-items: center;
    gap: 12px;
}
.pdp-user-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    object-fit: cover;
    background: #efe4dc;
}
.pdp-user-name {
    font-size: 14px;
    font-weight: 700;
    color: var(--primary-brown);
}
.pdp-review-date {
    font-size: 12px;
    color: var(--text-muted);
}
.pdp-review-body {
    font-size: 14px;
    line-height: 1.6;
    color: var(--text-main);
    margin: 0;
}
.pdp-empty-reviews {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-muted);
    font-size: 14.5px;
}

.pdp-back-box {
    margin-top: 32px;
}
.pdp-btn-back {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #ffffff;
    border: 1.5px solid var(--border-color);
    color: var(--primary-brown);
    padding: 10px 20px;
    border-radius: 12px;
    font-size: 13.5px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.15s ease;
}
.pdp-btn-back:hover {
    background: var(--accent-cream);
    border-color: var(--primary-orange);
    color: var(--primary-orange);
}

/* Centered Top Floating Toast Notification */
.pl-toast {
    position: fixed;
    top: 24px;
    left: 50%;
    transform: translate(-50%, -20px) scale(0.95);
    background: #3e2619;
    color: #fff;
    padding: 12px 24px;
    border-radius: 12px;
    font-size: 13.5px;
    font-weight: 600;
    box-shadow: 0 8px 24px rgba(62, 38, 25, 0.25);
    display: flex;
    align-items: center;
    gap: 10px;
    z-index: 99999;
    opacity: 0;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    pointer-events: none;
    border-bottom: 3px solid #cf7953;
}

.pl-toast.show {
    opacity: 1;
    transform: translate(-50%, 0) scale(1);
    pointer-events: auto;
}
.pl-toast-icon {
    font-size: 16px;
}

@media (max-width: 860px) {
    .pdp-main-card {
        grid-template-columns: 1fr;
        gap: 32px;
        padding: 24px;
    }
    .pdp-rating-overview {
        grid-template-columns: 1fr;
        text-align: center;
    }
    .pdp-big-score {
        border-right: none;
        border-bottom: 1px solid var(--border-color);
        padding-right: 0;
        padding-bottom: 16px;
    }
}
</style>

<div class="pdp-wrapper">
    <!-- Breadcrumb -->
    <div class="pdp-breadcrumb">
        <a href="/">Home</a>
        <span class="divider">&rsaquo;</span>
        <a href="list.php">Shop Bagels</a>
        <span class="divider">&rsaquo;</span>
        <span class="current"><?= htmlspecialchars($p->name) ?></span>
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
            <div class="pdp-category-pill"><?= htmlspecialchars($p->category_name ?? 'Classic Bagels') ?></div>
            <h1 class="pdp-title"><?= htmlspecialchars($p->name) ?></h1>

            <div class="pdp-rating-summary">
                <span class="pdp-stars">
                    <?= str_repeat('★', (int)round($avgRating)) ?><?= str_repeat('☆', 5 - (int)round($avgRating)) ?>
                </span>
                <span class="pdp-rating-num"><?= $avgRating > 0 ? number_format($avgRating, 1) : 'New' ?></span>
                <span>(<?= $totalReviews ?> <?= $totalReviews === 1 ? 'review' : 'reviews' ?>)</span>
            </div>

            <div class="pdp-price-row">
                <span class="pdp-price">RM <?= number_format($p->price, 2) ?></span>
            </div>

            <div class="pdp-description">
                <?= nl2br(htmlspecialchars($p->description)) ?>
            </div>

            <!-- ADD TO CART / LOGIN ACTION -->
            <?php if (isset($_user) && $_user->role === 'Member'): ?>
                <form method="post" id="addToCartForm">
                    <input type="hidden" name="action" value="add_to_cart">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($p->id) ?>">

                    <div class="pdp-action-box">
                        <div class="pdp-qty-stepper">
                            <button type="button" class="pdp-qty-btn" onclick="stepQty(-1)">−</button>
                            <input type="number" name="unit" id="qtyInput" class="pdp-qty-input" value="1" min="1" max="10" readonly>
                            <button type="button" class="pdp-qty-btn" onclick="stepQty(1)">+</button>
                        </div>

                        <button type="submit" class="pdp-btn-add-cart" id="addToCartBtn">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                            </svg>
                            <span id="btnLabelText">Add to Cart &bull; RM <span id="btnTotalText"><?= number_format($p->price, 2) ?></span></span>
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="pdp-action-box">
                    <a href="/login.php" class="pdp-btn-add-cart" style="text-decoration:none;">
                        🔐 Login to Order &bull; RM <?= number_format($p->price, 2) ?>
                    </a>
                </div>
            <?php endif; ?>

            <div class="pdp-stock-status">
                <span class="pdp-stock-dot"></span>
                In Stock &bull; <?= (int)$p->stock ?> available
            </div>

            <div class="pdp-features">
                <div class="pdp-feature-item"><span>🥖</span> Freshly Baked Daily</div>
                <div class="pdp-feature-item"><span>🚚</span> Local Same-Day Delivery</div>
            </div>
        </div>
    </div>

    <!-- YouTube Product Video Integration -->
    <?php if ($embedVideoUrl): ?>
        <div class="pdp-video-card">
            <div class="pdp-video-header">
                <span style="font-size: 22px;">🎬</span>
                <div class="pdp-video-title">Watch <?= htmlspecialchars($p->name) ?> in Action</div>
            </div>
            <div class="pdp-video-responsive-wrap">
                <div class="pdp-video-responsive">
                    <iframe 
                        src="<?= htmlspecialchars($embedVideoUrl) ?>" 
                        title="<?= htmlspecialchars($p->name) ?> Video Showcase"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                        allowfullscreen>
                    </iframe>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Ratings & Reviews Section -->
    <div class="pdp-reviews-container">
        <div class="pdp-section-header">
            <div class="pdp-section-title">Ratings & Reviews</div>
        </div>

        <?php if ($totalReviews > 0): ?>
            <div class="pdp-rating-overview">
                <div class="pdp-big-score">
                    <div class="pdp-big-number"><?= number_format($avgRating, 1) ?></div>
                    <div class="pdp-stars">
                        <?= str_repeat('★', (int)round($avgRating)) ?><?= str_repeat('☆', 5 - (int)round($avgRating)) ?>
                    </div>
                    <div class="pdp-score-sub"><?= $totalReviews ?> global ratings</div>
                </div>
                <div class="pdp-rating-bars">
                    <?php for ($star = 5; $star >= 1; $star--): 
                        $pct = $totalReviews > 0 ? round(($ratingCounts[$star] / $totalReviews) * 100) : 0;
                    ?>
                        <div class="pdp-bar-row">
                            <span style="width: 45px;"><?= $star ?> star</span>
                            <div class="pdp-bar-track">
                                <div class="pdp-bar-fill" style="width: <?= $pct ?>%;"></div>
                            </div>
                            <span style="width: 30px; text-align: right;"><?= $pct ?>%</span>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Submit Review Card -->
        <div class="pdp-review-form-card">
            <form method="post">
                <input type="hidden" name="action" value="submit_review">
                
                <div class="pdp-star-rating-select">
                    <span style="font-size: 13.5px; font-weight: 700; color: var(--primary-brown); margin-right: 8px;">Your Rating:</span>
                    <input type="hidden" name="rating" id="selectedRatingInput" value="5">
                    <?php for ($s = 1; $s <= 5; $s++): ?>
                        <span class="pdp-star-label active" data-value="<?= $s ?>" onclick="setRating(<?= $s ?>)">★</span>
                    <?php endfor; ?>
                </div>

                <textarea name="review_text" rows="3" class="pdp-review-textarea" placeholder="Share how you enjoyed this bagel (crust, taste, recommended pairings)..."></textarea>
                
                <button type="submit" class="pdp-btn-submit-review">Submit Review</button>
            </form>
        </div>

        <!-- Reviews Feed -->
        <?php if (!empty($reviews)): ?>
            <div class="pdp-reviews-list">
                <?php foreach ($reviews as $rev): ?>
                    <div class="pdp-review-item">
                        <div class="pdp-review-user">
                            <div class="pdp-user-info">
                                <img src="/photos/<?= htmlspecialchars($rev->user_photo ?? 'default.jpg') ?>" class="pdp-user-avatar" alt="<?= htmlspecialchars($rev->user_name ?? 'User') ?>" onerror="this.src='/photos/default.jpg'">
                                <div>
                                    <div class="pdp-user-name"><?= htmlspecialchars($rev->user_name ?: 'Customer') ?></div>
                                    <div class="pdp-stars" style="font-size: 12px;">
                                        <?= str_repeat('★', (int)$rev->rating) ?><?= str_repeat('☆', 5 - (int)$rev->rating) ?>
                                    </div>
                                </div>
                            </div>
                            <span class="pdp-review-date"><?= date('M d, Y', strtotime($rev->created_at)) ?></span>
                        </div>
                        <p class="pdp-review-body"><?= nl2br(htmlspecialchars($rev->comment ?? $rev->review_text ?? $rev->content ?? $rev->message ?? '')) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="pdp-empty-reviews">
                <div style="font-size: 36px; margin-bottom: 8px;">🥯</div>
                <div style="font-weight: 600;">No reviews yet</div>
                <div style="font-size: 13px; margin-top: 4px;">Be the first to share your bagel experience!</div>
            </div>
        <?php endif; ?>

        <div class="pdp-back-box">
            <a href="list.php" class="pdp-btn-back">&larr; Back to Shop</a>
        </div>
    </div>
</div>

<!-- Floating Toast Element -->
<div id="plToast" class="pl-toast">
    <span class="pl-toast-icon">⚠️</span>
    <span id="plToastMsg">Cannot add to cart. Your shopping cart is full (maximum 100 items limit reached).</span>
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

// Stepper Quantity & Toast Notification (Capped at 10 items per bagel & 100 total cart)
const unitPrice = <?= (float)$p->price ?>;
const totalCartItems = <?= (int)$totalCartItems ?>;
const currentThisItemQty = <?= (int)$currentThisItemQty ?>;
const maxStock = Math.min(10, <?= (int)$p->stock ?>);
let toastTimer = null;

function showToast(message) {
    const toast = document.getElementById('plToast');
    const toastMsg = document.getElementById('plToastMsg');
    if (!toast || !toastMsg) return;

    toastMsg.textContent = message;
    toast.classList.add('show');

    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
        toast.classList.remove('show');
    }, 3500);
}

function stepQty(amount) {
    const input = document.getElementById('qtyInput');
    const totalText = document.getElementById('btnTotalText');
    if (!input) return;

    // 1. Check if total cart is already full
    if (totalCartItems >= 100) {
        showToast('Cannot add to cart. Your shopping cart is full (maximum 100 items limit reached).');
        return;
    }

    let currentVal = parseInt(input.value) || 1;
    let nextVal = currentVal + amount;

    // 2. Check total cart overflow
    if (amount > 0 && (totalCartItems + currentVal) >= 100) {
        showToast('Cannot add more items. Your shopping cart will exceed the 100-item maximum limit.');
        return;
    }

    // 3. Check individual item limit
    if (amount > 0 && (currentThisItemQty + currentVal) >= 10) {
        showToast('Maximum order limit is 10 items per bagel.');
        return;
    }

    if (nextVal >= 1 && nextVal <= maxStock) {
        input.value = nextVal;
        if (totalText) totalText.textContent = (nextVal * unitPrice).toFixed(2);
    }
}

// Intercept Add To Cart Form Submit
const addToCartForm = document.getElementById('addToCartForm');
if (addToCartForm) {
    addToCartForm.addEventListener('submit', function(e) {
        const input = document.getElementById('qtyInput');
        const unitVal = parseInt(input.value) || 1;

        if (totalCartItems >= 100) {
            e.preventDefault();
            showToast('Cannot add to cart. Your shopping cart is full (maximum 100 items limit reached).');
            return false;
        }

        if ((totalCartItems + unitVal) > 100) {
            e.preventDefault();
            const allowed = 100 - totalCartItems;
            showToast(`Cannot add ${unitVal} items. You can only add ${allowed} more item(s) before reaching the 100-item limit.`);
            return false;
        }

        if ((currentThisItemQty + unitVal) > 10) {
            e.preventDefault();
            showToast(`Maximum limit is 10 per bagel. You already have ${currentThisItemQty} in your cart.`);
            return false;
        }
    });
}

// Interactive Star Ratings with Hover Effect
const starLabels = document.querySelectorAll('.pdp-star-label');
const ratingInput = document.getElementById('selectedRatingInput');

starLabels.forEach((star, index) => {
    star.addEventListener('mouseenter', () => {
        starLabels.forEach((s, i) => {
            s.classList.toggle('active', i <= index);
        });
    });

    star.addEventListener('mouseleave', () => {
        const currentVal = parseInt(ratingInput.value) || 5;
        starLabels.forEach((s, i) => {
            s.classList.toggle('active', i < currentVal);
        });
    });
});

function setRating(val) {
    ratingInput.value = val;
    starLabels.forEach((star, idx) => {
        star.classList.toggle('active', idx < val);
    });
}
</script>

<?php
include '../_foot.php';
?>