<?php
include '../_base.php';

$id = req('id');

if (is_post()) {
    if (req('btn') == 'review') {
        if (!$_user) {
            temp('error', 'Please login to leave a review.');
            redirect('login.php');
        }
        $rating  = (int) req('rating');
        $comment = req('comment');

        if ($rating >= 1 && $rating <= 5) {
            $stm = $_db->prepare('INSERT INTO product_review (product_id, user_id, rating, comment) VALUES (?, ?, ?, ?)');
            $stm->execute([$id, $_user->id, $rating, $comment]);
            temp('info', 'Review submitted.');
        } else {
            temp('error', 'Please select a rating.');
        }
        redirect('detail.php?id=' . $id);
    } else {
        $unit = req('unit');
        update_cart($id, $unit);
        redirect();
    }
}

$stm = $_db->prepare('SELECT * FROM product WHERE id = ?');
$stm->execute([$id]);
$p = $stm->fetch();
if (!$p) redirect('list.php');

// Fetch gallery photos
$stm = $_db->prepare('SELECT photo FROM product_photo WHERE product_id = ? ORDER BY sort_order, id');
$stm->execute([$id]);
$gallery = $stm->fetchAll(PDO::FETCH_COLUMN);

$slides = [];
if ($p->photo) $slides[] = '/products/' . $p->photo;
foreach ($gallery as $g) $slides[] = '/photos/products/' . $g;
if (!$slides) $slides[] = '/products/default.jpg';

// Fetch reviews (with reviewer name)
$stm = $_db->prepare('SELECT r.*, u.name AS reviewer_name FROM product_review r
                       JOIN user u ON u.id = r.user_id
                       WHERE r.product_id = ? ORDER BY r.created_at DESC');
$stm->execute([$id]);
$reviews = $stm->fetchAll();

$stm = $_db->prepare('SELECT AVG(rating) AS avg_rating, COUNT(*) AS total FROM product_review WHERE product_id = ?');
$stm->execute([$id]);
$ratingStats = $stm->fetch();

$cart = get_cart();
$unit = $cart[$p->id] ?? 0;

$_title = 'Product | Detail';
include '../_head.php';
?>

<style>
    .detail-layout {
        display: grid;
        grid-template-columns: 260px 1fr;
        gap: 30px;
        align-items: start;
        max-width: 900px;
        margin: 0 auto;
    }
    .slider {
        position: relative;
        width: 260px;
        height: 260px;
        border-radius: 10px;
        overflow: hidden;
        border: 1px solid #eee;
        background: #fafafa;
    }
    .slider-track {
        display: flex;
        height: 100%;
        transition: transform 0.3s ease;
    }
    .slider-track img {
        width: 260px;
        height: 260px;
        object-fit: cover;
        flex-shrink: 0;
    }
    .slider-arrow {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        background: rgba(0,0,0,0.4);
        color: #fff;
        border: none;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        cursor: pointer;
        font-size: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .slider-arrow.prev { left: 8px; }
    .slider-arrow.next { right: 8px; }
    .slider-dots {
        position: absolute;
        bottom: 8px;
        left: 0;
        right: 0;
        display: flex;
        justify-content: center;
        gap: 6px;
    }
    .slider-dots span {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: rgba(255,255,255,0.6);
        cursor: pointer;
    }
    .slider-dots span.active { background: #fff; }

    .stock-banner {
        background: #eaf2ff;
        color: #1a5fd0;
        padding: 12px 15px;
        border-radius: 8px;
        font-size: 14px;
        margin: 10px 0;
    }
    .price-big { font-size: 20px; font-weight: bold; margin: 10px 0; }
    .rating-summary { color: #fbbf24; font-size: 15px; margin: 6px 0; }
    .rating-summary .count { color: #888; font-size: 13px; }

    .qty-row { display: flex; align-items: center; gap: 12px; margin-top: 20px; }
    .qty-btn {
        width: 32px; height: 32px; border-radius: 50%;
        border: 1px solid var(--red, #e07b39); background: #fff;
        color: var(--red, #e07b39); font-size: 18px; cursor: pointer;
    }
    .qty-input {
        width: 40px; text-align: center; border: 1px solid #ddd;
        border-radius: 6px; padding: 6px 0;
    }
    .add-btn {
        margin-top: 15px; width: 100%; padding: 14px;
        background: var(--red, #e07b39); color: #fff; border: none;
        border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer;
    }
    .add-btn:disabled { background: #ccc; cursor: not-allowed; }

    .reviews-section { max-width: 900px; margin: 40px auto 0; }
    .review-form { background: #fafafa; border: 1px solid #eee; border-radius: 8px; padding: 15px; margin-bottom: 20px; }
    .review-form select, .review-form textarea { width: 100%; margin: 6px 0 12px; padding: 8px; }
    .review-item { border-bottom: 1px solid #eee; padding: 12px 0; }
    .review-item .stars { color: #fbbf24; }
    .review-item .meta { color: #888; font-size: 12px; }
</style>

<div class="detail-layout">
    <div>
        <div class="slider" id="slider">
            <div class="slider-track" id="sliderTrack">
                <?php foreach ($slides as $src): ?>
                    <img src="<?= encode($src) ?>" alt="<?= encode($p->name) ?>">
                <?php endforeach; ?>
            </div>
            <?php if (count($slides) > 1): ?>
                <button class="slider-arrow prev" onclick="moveSlide(-1)">‹</button>
                <button class="slider-arrow next" onclick="moveSlide(1)">›</button>
                <div class="slider-dots" id="sliderDots">
                    <?php foreach ($slides as $i => $s): ?>
                        <span data-i="<?= $i ?>" class="<?= $i === 0 ? 'active' : '' ?>" onclick="goToSlide(<?= $i ?>)"></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <h2><?= encode($p->name) ?></h2>

        <?php if ($ratingStats->total > 0): ?>
        <div class="rating-summary">
            <?= str_repeat('★', round($ratingStats->avg_rating)) . str_repeat('☆', 5 - round($ratingStats->avg_rating)) ?>
            <?= number_format($ratingStats->avg_rating, 1) ?>
            <span class="count">(<?= $ratingStats->total ?> review<?= $ratingStats->total > 1 ? 's' : '' ?>)</span>
        </div>
        <?php else: ?>
        <div class="rating-summary"><span class="count">No reviews yet</span></div>
        <?php endif; ?>

        <?php if ($p->stock <= 0): ?>
            <div class="stock-banner">ℹ️ Sorry, this item is currently unavailable</div>
        <?php endif; ?>

        <div class="price-big">RM<?= number_format($p->price, 2) ?></div>

        <form method="post" id="detailForm">
            <?= html_hidden('id') ?>
            <input type="hidden" name="unit" id="unitField" value="<?= $unit ?: 1 ?>">

            <div class="qty-row">
                <button type="button" class="qty-btn" onclick="changeQty(-1)">−</button>
                <input type="text" class="qty-input" id="qtyDisplay" value="<?= $unit ?: 1 ?>" readonly>
                <button type="button" class="qty-btn" onclick="changeQty(1)">+</button>
            </div>

            <button type="submit" class="add-btn" <?= $p->stock <= 0 ? 'disabled' : '' ?>>
                <?= $unit ? "In Cart ✅" : "Add RM" . number_format($p->price, 2) ?>
            </button>
        </form>
    </div>
</div>

<div class="reviews-section">
    <h3>Ratings &amp; Reviews</h3>

    <?php if ($_user?->role == 'Member'): ?>
    <form method="post" class="review-form">
        <?= html_hidden('id') ?>
        <input type="hidden" name="btn" value="review">

        <label>Your Rating</label>
        <select name="rating" required>
            <option value="">-- select --</option>
            <?php for ($i = 5; $i >= 1; $i--): ?>
                <option value="<?= $i ?>"><?= $i ?> star<?= $i > 1 ? 's' : '' ?></option>
            <?php endfor; ?>
        </select>

        <label>Your Review (optional)</label>
        <textarea name="comment" rows="3" maxlength="500"></textarea>

        <button type="submit">Submit Review</button>
    </form>
    <?php else: ?>
    <p><a href="/login.php">Login</a> to leave a review.</p>
    <?php endif; ?>

    <?php if ($reviews): ?>
        <?php foreach ($reviews as $r): ?>
        <div class="review-item">
            <div class="stars"><?= str_repeat('★', $r->rating) . str_repeat('☆', 5 - $r->rating) ?></div>
            <div><?= $r->comment ? encode($r->comment) : '<em>No comment</em>' ?></div>
            <div class="meta"><?= encode($r->reviewer_name) ?> — <?= date('d M Y', strtotime($r->created_at)) ?></div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p style="color:#888;">Be the first to review this bagel!</p>
    <?php endif; ?>
</div>

<p style="max-width: 900px; margin: 20px auto 0;">
    <button data-get="list.php">List</button>
</p>

<script>
    function changeQty(delta) {
        const display = document.getElementById('qtyDisplay');
        const field = document.getElementById('unitField');
        let val = parseInt(display.value) + delta;
        if (val < 1) val = 1;
        if (val > <?= (int)$p->stock ?: 999 ?>) val = <?= (int)$p->stock ?: 999 ?>;
        display.value = val;
        field.value = val;
    }

    const slides = document.querySelectorAll('#sliderTrack img');
    const dots = document.querySelectorAll('#sliderDots span');
    let current = 0;

    function updateSlider() {
        document.getElementById('sliderTrack').style.transform = `translateX(-${current * 260}px)`;
        dots.forEach((d, i) => d.classList.toggle('active', i === current));
    }

    function moveSlide(dir) {
        current = (current + dir + slides.length) % slides.length;
        updateSlider();
    }

    function goToSlide(i) {
        current = i;
        updateSlider();
    }

    <?php if (count($slides) > 1): ?>
    setInterval(() => moveSlide(1), 4000);
    <?php endif; ?>
</script>

<?php
include '../_foot.php';