<?php
include '../_base.php';

$id = req('id');

if (is_post()) {
    if (req('btn') == 'review') {
        auth('Member');
        $rating  = (int) req('rating');
        $comment = req('comment');

        if ($rating < 1 || $rating > 5) {
            temp('error', 'Please select a rating.');
        } else {
            $result = add_review($id, $_user->id, $rating, $comment);
            temp($result['success'] ? 'info' : 'error', $result['message']);
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

$galleryRows = get_product_photos($id);

$slides = [];
if ($p->photo) $slides[] = '/products/' . $p->photo;
foreach ($galleryRows as $g) $slides[] = '/products/' . $g->photo;
if (!$slides) $slides[] = '/products/default.jpg';

$reviews     = get_product_reviews($id);
$reviewCount = count($reviews);

$cart = get_cart();
$unit = $cart[$p->id] ?? 0;

// YouTube embed
$videoEmbed = null;
if ($p->video_url) {
    preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $p->video_url, $m);
    if (isset($m[1])) $videoEmbed = $m[1];
}

$_title = 'Product | Detail';
include '../_head.php';
?>

<style>
    .detail-layout { display: grid; grid-template-columns: 300px 1fr; gap: 30px; max-width: 950px; margin: 0 auto; align-items: start; }
    .slider { position: relative; width: 300px; height: 300px; border-radius: 10px; overflow: hidden; border: 1px solid #eee; background: #fafafa; }
    .slider-track { display: flex; height: 100%; transition: transform 0.3s ease; }
    .slider-track img { width: 300px; height: 300px; object-fit: cover; flex-shrink: 0; }
    .slider-arrow { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.4); color: #fff; border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; }
    .slider-arrow.prev { left: 8px; } .slider-arrow.next { right: 8px; }
    .slider-dots { position: absolute; bottom: 8px; left: 0; right: 0; display: flex; justify-content: center; gap: 6px; }
    .slider-dots span { width: 7px; height: 7px; border-radius: 50%; background: rgba(255,255,255,0.6); cursor: pointer; }
    .slider-dots span.active { background: #fff; }

    .stock-banner { background: #eaf2ff; color: #1a5fd0; padding: 10px 14px; border-radius: 8px; margin: 8px 0; font-size: 14px; }
    .price-big { font-size: 20px; font-weight: bold; margin: 8px 0; }
    .rating-summary { color: #fbbf24; }
    .qty-row { display: flex; gap: 10px; align-items: center; margin: 15px 0; }
    .video-preview iframe { width: 100%; max-width: 500px; aspect-ratio: 16/9; border-radius: 8px; margin-top: 15px; }

    .reviews-section { max-width: 950px; margin: 40px auto 0; }
    .review-form { background: #fafafa; border: 1px solid #eee; border-radius: 8px; padding: 15px; margin-bottom: 20px; }
    .review-item { border-bottom: 1px solid #eee; padding: 10px 0; }
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
                        <span class="<?= $i === 0 ? 'active' : '' ?>" onclick="goToSlide(<?= $i ?>)"></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <h2><?= encode($p->name) ?></h2>

        <?php if ($reviewCount > 0): ?>
        <div class="rating-summary">
            <?= str_repeat('★', round($p->rating)) . str_repeat('☆', 5 - round($p->rating)) ?>
            <?= number_format($p->rating, 1) ?> (<?= $reviewCount ?> review<?= $reviewCount > 1 ? 's' : '' ?>)
        </div>
        <?php else: ?>
        <div class="rating-summary" style="color:#888;">No reviews yet</div>
        <?php endif; ?>

        <?php if ($p->stock <= 0): ?>
            <div class="stock-banner">ℹ️ Sorry, this item is currently unavailable</div>
        <?php endif; ?>

        <div class="price-big">RM<?= number_format($p->price, 2) ?></div>
        <p><?= nl2br(encode($p->description ?? '')) ?></p>

        <form method="post">
            <?= html_hidden('id') ?>
            <input type="hidden" name="unit" id="unitField" value="<?= $unit ?: 1 ?>">
            <div class="qty-row">
                <button type="button" onclick="changeQty(-1)">−</button>
                <input type="text" id="qtyDisplay" value="<?= $unit ?: 1 ?>" readonly style="width:40px;text-align:center;">
                <button type="button" onclick="changeQty(1)">+</button>
            </div>
            <button type="submit" <?= $p->stock <= 0 ? 'disabled' : '' ?>>
                <?= $unit ? 'In Cart ✅' : 'Add RM' . number_format($p->price, 2) ?>
            </button>
        </form>

        <?php if ($videoEmbed): ?>
        <div class="video-preview">
            <iframe src="https://www.youtube.com/embed/<?= encode($videoEmbed) ?>" frameborder="0" allowfullscreen></iframe>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="reviews-section">
    <h3>Ratings &amp; Reviews</h3>

    <?php if ($_user?->role == 'Member'): ?>
    <form method="post" class="review-form">
        <?= html_hidden('id') ?>
        <input type="hidden" name="btn" value="review">
        <label>Your Rating</label>
        <?= html_select('rating', $_ratings, '- select -') ?>
        <br><br>
        <label>Your Review (optional)</label><br>
        <?= html_textarea('comment', 'rows="3" style="width:100%;" maxlength="500"') ?>
        <br>
        <button type="submit">Submit Review</button>
    </form>
    <?php else: ?>
    <p><a href="<?= app_url('login.php') ?>">Login</a> to leave a review.</p>
    <?php endif; ?>

    <?php if ($reviews): ?>
        <?php foreach ($reviews as $r): ?>
        <div class="review-item">
            <div class="stars"><?= str_repeat('★', $r->rating) . str_repeat('☆', 5 - $r->rating) ?></div>
            <div><?= $r->comment ? encode($r->comment) : '<em>No comment</em>' ?></div>
            <div class="meta"><?= encode($r->user_name) ?> — <?= date('d M Y', strtotime($r->created_at)) ?></div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p style="color:#888;">Be the first to review this bagel!</p>
    <?php endif; ?>
</div>

<p style="max-width:950px;margin:20px auto 0;">
    <button data-get="list.php">Back to List</button>
</p>

<script>
    function changeQty(delta) {
        const display = document.getElementById('qtyDisplay');
        const field = document.getElementById('unitField');
        let val = parseInt(display.value) + delta;
        if (val < 1) val = 1;
        if (val > <?= (int)$p->stock ?: 999 ?>) val = <?= (int)$p->stock ?: 999 ?>;
        display.value = val; field.value = val;
    }
    const slides = document.querySelectorAll('#sliderTrack img');
    const dots = document.querySelectorAll('#sliderDots span');
    let current = 0;
    function updateSlider() {
        document.getElementById('sliderTrack').style.transform = `translateX(-${current * 300}px)`;
        dots.forEach((d, i) => d.classList.toggle('active', i === current));
    }
    function moveSlide(dir) { current = (current + dir + slides.length) % slides.length; updateSlider(); }
    function goToSlide(i) { current = i; updateSlider(); }
    <?php if (count($slides) > 1): ?>
    setInterval(() => moveSlide(1), 4000);
    <?php endif; ?>
</script>

<?php include '../_foot.php'; ?>