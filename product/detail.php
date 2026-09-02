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

<link rel="stylesheet" href="<?= app_url('css/product-detail.css') ?>">

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
                <?php if ((int)$p->stock > 0): ?>
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
                        <button type="button" class="pdp-btn-add-cart il-82-e3646f" disabled>
                            Currently Out of Stock
                        </button>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="pdp-action-box">
                    <a href="/login.php" class="pdp-btn-add-cart il-75-116e33">
                        🔐 Login to Order &bull; RM <?= number_format($p->price, 2) ?>
                    </a>
                </div>
            <?php endif; ?>

            <div class="stock-status il-83-f98fef">
                <?php if ((int)$p->stock > 0): ?>
                    <span class="il-84-148dc3">● In Stock • <?= (int)$p->stock ?> available</span>
                <?php else: ?>
                    <span class="il-85-290ef7">● Out of Stock</span>
                <?php endif; ?>
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
                            <span class="il-87-bdce7b"><?= $star ?> star</span>
                            <div class="pdp-bar-track">
                                <div class="pdp-bar-fill" style="width: <?= $pct ?>%;"></div>
                            </div>
                            <span class="il-88-d6b8eb"><?= $pct ?>%</span>
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
                    <span class="il-89-56a34a">Your Rating:</span>
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
                <div class="il-91-1d995b">🥯</div>
                <div class="il-92-93d1d3">No reviews yet</div>
                <div class="il-93-0a8161">Be the first to share your bagel experience!</div>
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