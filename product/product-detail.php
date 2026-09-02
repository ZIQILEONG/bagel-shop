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

// Universal YouTube Embed URL Parser (handles watch, shorts, youtu.be, and extra params)
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

<link rel="stylesheet" href="<?= app_url('css/product-product-detail.css') ?>">

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

    <!-- YouTube Product Video Integration -->
    <?php if ($embedVideoUrl): ?>
        <div class="pdp-video-card">
            <div class="pdp-video-header">
                <span class="il-86-da08d5">🎬</span>
                <div class="pdp-video-title">Watch <?= htmlspecialchars($p->name) ?> in Action</div>
            </div>
            <div class="pdp-video-responsive">
                <iframe 
                    src="<?= htmlspecialchars($embedVideoUrl) ?>" 
                    title="<?= htmlspecialchars($p->name) ?> Video Showcase"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                    allowfullscreen>
                </iframe>
            </div>
        </div>
    <?php endif; ?>

    <!-- Reviews Container -->
    <div class="pdp-reviews-container">
        <div class="pdp-section-header">
            <div class="pdp-section-title">Ratings & Reviews</div>
        </div>

        <div class="pdp-review-form-card">
            <form method="post">
                <input type="hidden" name="action" value="submit_review">
                
                <div class="pdp-star-rating-select">
                    <span class="il-98-c8e5dc">Your Rating:</span>
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
                                <img src="/photos/<?= htmlspecialchars($rev->user_photo ?? 'default.jpg') ?>" class="pdp-user-avatar" alt="<?= htmlspecialchars($rev->user_name ?? 'User') ?>" onerror="this.src='/photos/default.jpg'">
                                <div>
                                    <div class="pdp-user-name"><?= htmlspecialchars($rev->user_name ?: 'Customer') ?></div>
                                    <div class="pdp-stars il-90-cea31e">
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
                <div class="pdp-empty-icon il-99-b1aba3">🥯</div>
                <div>Be the first to review this bagel!</div>
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

// Choice of Bagel (5 Set Logic)
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

    // Toggle Plus buttons disabled state if limit 5 reached
    document.querySelectorAll('.btn-plus').forEach(btn => {
        btn.disabled = (total >= maxSelection);
    });

    // Toggle Minus buttons state
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