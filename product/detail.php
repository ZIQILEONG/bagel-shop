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

// Check if this product is a 5-Bagel Set bundle
$isSet5 = (stripos($p->name, '5 Bagel') !== false || stripos($p->name, '5-Pack') !== false || stripos($p->name, '5') !== false);

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

// Rating Breakdown Counts
$ratingCounts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
foreach ($reviews as $r) {
    $rt = (int)$r->rating;
    if (isset($ratingCounts[$rt])) $ratingCounts[$rt]++;
}

// Handle Add to Cart
if (is_post() && req('action') == 'add_to_cart') {
    $qty = (int)req('qty', 1);
    $selectedFlavours = req('flavours', []);

    if ($isSet5) {
        $totalSelected = 0;
        if (is_array($selectedFlavours)) {
            foreach ($selectedFlavours as $fQty) {
                $totalSelected += (int)$fQty;
            }
        }
        if ($totalSelected !== 5) {
            temp('error', 'Please select exactly 5 bagels for this bundle.');
            redirect("product-detail.php?id={$p->id}");
        }
    }

    if ($qty > 0 && $qty <= $p->stock) {
        $cartItemKey = $isSet5 ? $p->id . '_' . md5(json_encode($selectedFlavours)) : $p->id;
        $_SESSION['cart'][$cartItemKey] = [
            'id'       => $p->id,
            'name'     => $p->name,
            'price'    => $p->price,
            'qty'      => ($_SESSION['cart'][$cartItemKey]['qty'] ?? 0) + $qty,
            'flavours' => $isSet5 ? array_filter($selectedFlavours) : null
        ];

        temp('info', "Added {$qty} item(s) to your cart!");
        redirect('/index.php');
    } else {
        temp('error', 'Invalid quantity requested.');
        redirect("product-detail.php?id={$p->id}");
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
    --red-alert: #d04632;
    --radius-lg: 24px;
    --radius-md: 16px;
    --radius-sm: 10px;
    --shadow-soft: 0 6px 24px rgba(67, 43, 30, 0.05);
    --shadow-hover: 0 10px 30px rgba(67, 43, 30, 0.09);
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

/* -------------------------------------------------------------
   1. MAIN SHOWCASE CARD
   ------------------------------------------------------------- */
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

/* Gallery / Carousel */
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
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.pdp-nav-btn:hover {
    background: #ffffff;
    color: var(--primary-orange);
    transform: translateY(-50%) scale(1.1);
    box-shadow: 0 6px 16px rgba(207, 115, 73, 0.2);
}
.pdp-nav-prev { left: 14px; }
.pdp-nav-next { right: 14px; }

.pdp-thumbs {
    display: flex;
    gap: 12px;
    overflow-x: auto;
    padding-bottom: 6px;
    scrollbar-width: thin;
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
.pdp-thumb:hover {
    opacity: 0.9;
}
.pdp-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* Product Info */
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
    letter-spacing: -0.02em;
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
    font-size: 15px;
}
.pdp-rating-num {
    font-weight: 700;
    color: var(--primary-brown);
}

.pdp-price-row {
    display: flex;
    align-items: baseline;
    gap: 10px;
    margin-bottom: 18px;
}
.pdp-price {
    font-size: 30px;
    font-weight: 800;
    color: var(--primary-orange);
    letter-spacing: -0.01em;
}

.pdp-description {
    font-size: 14.5px;
    line-height: 1.7;
    color: var(--text-main);
    margin-bottom: 26px;
    padding-bottom: 22px;
    border-bottom: 1px solid var(--border-subtle);
}

/* 5-Bagel Flavour Selector */
.flavour-selector-card {
    background: var(--accent-cream);
    border: 1.5px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 20px 22px;
    margin-bottom: 24px;
}
.flavour-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding-bottom: 14px;
    border-bottom: 1px solid #eedfd6;
    margin-bottom: 16px;
}
.flavour-title {
    font-size: 15px;
    font-weight: 800;
    color: var(--primary-brown);
}
.flavour-subtitle {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 3px;
}
.flavour-badge-counter {
    font-size: 13px;
    font-weight: 700;
    color: var(--red-alert);
    background: #fff;
    padding: 4px 10px;
    border-radius: 20px;
    border: 1px solid #f4cfc7;
}
.flavour-badge-counter.ready {
    color: var(--green-stock);
    border-color: #cde6d5;
    background: #f0f9f3;
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
    background: #ffffff;
    padding: 8px 14px;
    border-radius: var(--radius-sm);
    border: 1px solid var(--border-subtle);
}
.flavour-name {
    font-size: 13.5px;
    font-weight: 600;
    color: var(--primary-brown);
}
.flavour-stepper {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.flavour-btn {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 1.5px solid #e2d2c8;
    background: #ffffff;
    color: #7b6255;
    font-size: 16px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s ease;
}
.flavour-btn:hover:not(:disabled) {
    border-color: var(--primary-orange);
    color: var(--primary-orange);
    background: #fff6f1;
}
.flavour-btn:disabled {
    opacity: 0.3;
    cursor: not-allowed;
    border-color: #eddcd3;
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

/* Quantity & CTA Button */
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
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    box-shadow: 0 4px 14px rgba(207, 115, 73, 0.25);
}
.pdp-btn-add-cart:hover:not(:disabled) {
    background: var(--primary-orange-hover);
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(207, 115, 73, 0.35);
}
.pdp-btn-add-cart:disabled {
    background: #dbcdc4;
    cursor: not-allowed;
    box-shadow: none;
    transform: none;
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
    box-shadow: 0 0 0 3px rgba(43, 122, 75, 0.15);
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

/* -------------------------------------------------------------
   2. YOUTUBE VIDEO SHOWCASE CARD
   ------------------------------------------------------------- */
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
    padding-bottom: 56.25%; /* 16:9 Aspect Ratio */
    height: 0;
    overflow: hidden;
    border-radius: var(--radius-md);
    background: #000;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
}
.pdp-video-responsive iframe {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    border: 0;
}

/* -------------------------------------------------------------
   3. RATINGS & REVIEWS SECTION
   ------------------------------------------------------------- */
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

/* Rating Overview Grid */
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

/* Submit Review Card */
.pdp-review-form-card {
    background: #ffffff;
    border: 1.5px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 24px;
    margin-bottom: 36px;
    box-shadow: 0 2px 8px rgba(67, 43, 30, 0.03);
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
    transition: all 0.2s ease;
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
    transition: all 0.2s;
}
.pdp-btn-submit-review:hover {
    background: var(--primary-orange-hover);
    transform: translateY(-1px);
}

/* Reviews List */
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
    box-shadow: 0 2px 6px rgba(67, 43, 30, 0.02);
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
    border: 1px solid var(--border-color);
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

            <form method="post" id="addToCartForm">
                <input type="hidden" name="action" value="add_to_cart">

                <!-- CHOICE OF BAGEL (FOR 5-BAGEL SET) -->
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
                                        <input type="text" name="flavours[<?= htmlspecialchars($flavour) ?>]" class="flavour-qty-box" value="0" readonly>
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
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                        </svg>
                        <span id="btnLabelText"><?= $isSet5 ? 'Select 5 Bagels to Add' : 'Add to Cart' ?> &bull; RM <span id="btnTotalText"><?= number_format($p->price, 2) ?></span></span>
                    </button>
                </div>
            </form>

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
            <!-- Score Breakdown Overview -->
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
        if (totalText) totalText.textContent = (val * unitPrice).toFixed(2);
    }
}

// 5-Bagel Set Selection Logic
const maxSelection = 5;

function updateFlavourQty(flavourName, delta) {
    const row = Array.from(document.querySelectorAll('.flavour-row')).find(r => 
        r.querySelector('.flavour-name').textContent.trim() === flavourName
    );
    if (!row) return;

    const input = row.querySelector('.flavour-qty-box');
    let currentQty = parseInt(input.value) || 0;
    let totalSelected = getTotalSelectedFlavours();

    if (delta > 0 && totalSelected >= maxSelection) return;
    if (delta < 0 && currentQty <= 0) return;

    input.value = Math.max(0, currentQty + delta);
    syncFlavourState();
}

function getTotalSelectedFlavours() {
    let sum = 0;
    document.querySelectorAll('.flavour-qty-box').forEach(inp => {
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

    if (countText) countText.textContent = total;

    document.querySelectorAll('.btn-plus').forEach(btn => {
        btn.disabled = (total >= maxSelection);
    });

    document.querySelectorAll('.flavour-qty-box').forEach(inp => {
        const row = inp.closest('.flavour-row');
        const minusBtn = row.querySelector('.btn-minus');
        minusBtn.disabled = (parseInt(inp.value) <= 0);
    });

    if (total === maxSelection) {
        countBadge.classList.add('ready');
        addBtn.disabled = false;
        const currentQty = parseInt(document.getElementById('qtyInput').value) || 1;
        btnLabel.innerHTML = `Add to Cart &bull; RM <span id="btnTotalText">${(currentQty * unitPrice).toFixed(2)}</span>`;
    } else {
        countBadge.classList.remove('ready');
        addBtn.disabled = true;
        btnLabel.innerHTML = `Select 5 Bagels to Add (${total}/5)`;
    }
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

document.addEventListener('DOMContentLoaded', () => {
    syncFlavourState();
});
</script>

<?php
include '../_foot.php';
?>