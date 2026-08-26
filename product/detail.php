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
            $userId = $_user->id ?? $_user->user_id ?? null;
            $result = add_review($id, $userId, $rating, $comment);
            temp($result['success'] ? 'info' : 'error', $result['message']);
        }
        redirect('detail.php?id=' . $id);
    } else {
        $unit = (int) req('unit');
        $bundle_items = req('bundle_items'); // Choice of Bagel (for bundles)
        $prep_options = req('prep_options'); // Preparation Options (for all bagels)

        update_cart($id, $unit, [
            'bundle_items' => $bundle_items,
            'prep_options' => $prep_options
        ]);
        redirect();
    }
}

$stm = $_db->prepare('SELECT * FROM product WHERE id = ?');
$stm->execute([$id]);
$p = $stm->fetch();
if (!$p) redirect('list.php');

$youtubeEmbedUrl = '';
if (!empty($p->video_url)) {
    $videoUrl = trim($p->video_url);
    if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $videoUrl, $match)) {
        $youtubeEmbedUrl = "https://www.youtube.com/embed/" . $match[1];
    } elseif (strlen($videoUrl) === 11) {
        $youtubeEmbedUrl = "https://www.youtube.com/embed/" . $videoUrl;
    }
}

// Check if current item is a bundle
$isBundle = ($p->category_id == 4 || stripos($p->name, '5 Bagels') !== false);

$availableBagels = [];
if ($isBundle) {
    // Fetch individual bagel varieties for bundle selection
    $stmBagels = $_db->query('SELECT id, name, stock FROM product WHERE category_id != 4 ORDER BY id ASC');
    $availableBagels = $stmBagels->fetchAll();
}

// Preparation options available for ALL bagels
$prepOptions = [
    'slice'       => 'Slice',
    'no_slice'    => 'No slice',
    'toasted'     => 'Toasted',
    'no_toast'    => 'No toast',
    'spread_on'   => 'Spread on',
    'seperate_cc' => 'Seperate CC',
    'hot'         => 'Hot',
    'iced'        => 'Iced'
];

// Fetch gallery photos (trimmed)
$galleryRows = get_product_photos($id);
$gallery = array_map('trim', array_column($galleryRows, 'photo'));

$slides = [];
foreach ($gallery as $g) {
    if (!empty($g)) {
        $slides[] = '../products/' . $g;
    }
}

if (empty($slides)) {
    if (!empty($p->photo)) {
        $slides[] = '../products/' . trim($p->photo);
    } else {
        $slides[] = '../products/default.jpg';
    }
}

// Fetch reviews
$reviews = get_product_reviews($id);
$reviewCount = count($reviews);

$cart = get_cart();
$unit = $cart[$p->id] ?? 0;

$_title = 'Product | Detail';
include '../_head.php';
?>

<style>
    .detail-layout {
        display: grid;
        grid-template-columns: 280px 1fr;
        gap: 30px;
        align-items: start;
        max-width: 900px;
        margin: 0 auto;
    }
    .slider {
        position: relative;
        width: 280px;
        height: 280px;
        border-radius: 12px;
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
        width: 280px;
        height: 280px;
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
        background: #fdf2e9;
        color: #b95d1b;
        padding: 12px 15px;
        border-radius: 8px;
        font-size: 14px;
        margin: 10px 0;
    }
    .price-big { font-size: 22px; font-weight: bold; margin: 10px 0; color: #333; }
    .rating-summary { color: #fbbf24; font-size: 15px; margin: 6px 0; }
    .rating-summary .count { color: #888; font-size: 13px; }

    /* Accordion & Option Styles */
    .option-accordion {
        margin-top: 15px;
        border-radius: 10px;
        overflow: hidden;
        border: 1px solid #ebebeb;
        background: #fff;
    }
    .accordion-header {
        background: #f7f7f7;
        padding: 12px 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer;
        user-select: none;
    }
    .accordion-title { font-weight: 700; font-size: 15px; color: #222; }
    .accordion-sub { font-size: 12px; color: #777; margin-top: 2px; }
    .accordion-status {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 13px;
        font-weight: 600;
        color: #d97736;
    }
    .accordion-content {
        padding: 10px 16px;
        background: #fff;
    }
    .accordion-content.hidden { display: none; }

    .option-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid #f2f2f2;
    }
    .option-row:last-child { border-bottom: none; }
    .option-row.disabled { opacity: 0.45; }
    .option-name { font-weight: 600; font-size: 14px; color: #333; }

    .stepper {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .stepper-btn {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        border: 1.5px solid #cb783f;
        background: #fff;
        color: #cb783f;
        font-size: 16px;
        font-weight: bold;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .stepper-btn:disabled {
        border-color: #ddd;
        color: #ddd;
        cursor: not-allowed;
    }
    .stepper-val {
        width: 32px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #e0e0e0;
        border-radius: 4px;
        font-weight: bold;
        font-size: 14px;
        background: #fff;
    }

    .qty-row { display: flex; align-items: center; gap: 12px; margin-top: 25px; }
    .qty-btn {
        width: 34px; height: 34px; border-radius: 50%;
        border: 1.5px solid #cb783f; background: #fff;
        color: #cb783f; font-size: 18px; cursor: pointer;
    }
    .qty-input {
        width: 44px; text-align: center; border: 1px solid #ddd;
        border-radius: 6px; padding: 6px 0; font-weight: bold;
    }
    .add-btn {
        margin-top: 15px; width: 100%; padding: 14px;
        background: #cb783f; color: #fff; border: none;
        border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer;
    }
    .add-btn:disabled { background: #e0beaa; color: #fff; cursor: not-allowed; }

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

        <?php if ($reviewCount > 0): ?>
            <div class="rating-summary">
                <?= str_repeat('★', round($p->rating)) . str_repeat('☆', 5 - round($p->rating)) ?>
                <?= number_format($p->rating, 1) ?>
                <span class="count">(<?= $reviewCount ?> review<?= $reviewCount > 1 ? 's' : '' ?>)</span>
            </div>
        <?php else: ?>
            <div class="rating-summary"><span class="count">No reviews yet</span></div>
        <?php endif; ?>

        <?php if ($p->stock <= 0): ?>
            <div class="stock-banner">ℹ️ Sorry, this item is currently unavailable</div>
        <?php endif; ?>

        <div class="price-big">RM<?= number_format($p->price, 2) ?></div>

        <form method="post" id="detailForm">
            <input type="hidden" name="id" value="<?= encode($id) ?>">
            <input type="hidden" name="unit" id="unitField" value="<?= $unit ?: 1 ?>">

            <!-- Choice of Bagel (Shown ONLY for 5 Bagels Bundle) -->
            <?php if ($isBundle): ?>
                <div class="option-accordion">
                    <div class="accordion-header" onclick="toggleSection('bagelSection', 'bagelArrow')">
                        <div>
                            <div class="accordion-title">Choice of Bagel</div>
                            <div class="accordion-sub">Select at least 1 up to a maximum of 5</div>
                        </div>
                        <div class="accordion-status">
                            <span id="bagelCounter">0 selected</span>
                            <span id="bagelArrow">▲</span>
                        </div>
                    </div>
                    <div class="accordion-content" id="bagelSection">
                        <?php foreach ($availableBagels as $b): ?>
                            <?php $outOfStock = ($b->stock <= 0); ?>
                            <div class="option-row <?= $outOfStock ? 'disabled' : '' ?>">
                                <div class="option-name">
                                    <?= encode($b->name) ?>
                                    <?= $outOfStock ? '<br><small style="color:#888;">Not available</small>' : '' ?>
                                </div>
                                <div class="stepper">
                                    <button type="button" class="stepper-btn" onclick="updateItem('bagel', '<?= $b->id ?>', -1)" <?= $outOfStock ? 'disabled' : '' ?>>−</button>
                                    <span class="stepper-val" id="disp_bagel_<?= $b->id ?>">0</span>
                                    <input type="hidden" name="bundle_items[<?= $b->id ?>]" id="input_bagel_<?= $b->id ?>" class="bagel-choice" value="0">
                                    <button type="button" class="stepper-btn" onclick="updateItem('bagel', '<?= $b->id ?>', 1)" <?= $outOfStock ? 'disabled' : '' ?>>+</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Preparation Option (Shown for ALL Bagels) -->
            <div class="option-accordion">
                <div class="accordion-header" onclick="toggleSection('prepSection', 'prepArrow')">
                    <div>
                        <div class="accordion-title">Preparation Option</div>
                        <div class="accordion-sub" id="prepSubText">Select up to <?= $isBundle ? '5' : ($unit ?: 1) ?></div>
                    </div>
                    <div class="accordion-status">
                        <span id="prepCounter">0 selected</span>
                        <span id="prepArrow">▲</span>
                    </div>
                </div>
                <div class="accordion-content" id="prepSection">
                    <?php foreach ($prepOptions as $key => $label): ?>
                        <div class="option-row">
                            <div class="option-name"><?= $label ?></div>
                            <div class="stepper">
                                <button type="button" class="stepper-btn" onclick="updateItem('prep', '<?= $key ?>', -1)">−</button>
                                <span class="stepper-val" id="disp_prep_<?= $key ?>">0</span>
                                <input type="hidden" name="prep_options[<?= $key ?>]" id="input_prep_<?= $key ?>" class="prep-choice" value="0">
                                <button type="button" class="stepper-btn" onclick="updateItem('prep', '<?= $key ?>', 1)">+</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="qty-row">
                <button type="button" class="qty-btn" onclick="changeQty(-1)">−</button>
                <input type="text" class="qty-input" id="qtyDisplay" value="<?= $unit ?: 1 ?>" readonly>
                <button type="button" class="qty-btn" onclick="changeQty(1)">+</button>
            </div>

            <button type="submit" class="add-btn" id="submitBtn" <?= ($p->stock <= 0 || $isBundle) ? 'disabled' : '' ?>>
                <?= $p->stock <= 0 ? 'Out of stock' : ($unit ? "In Cart ✅" : "Add RM" . number_format($p->price, 2)) ?>
            </button>
        </form>
    </div>
</div>

<?php if (!empty($youtubeEmbedUrl)): ?>
    <div class="video-container" style="max-width: 900px; margin: 30px auto;">
        <h3>Product Video</h3>
        <iframe 
            width="100%" 
            height="450" 
            src="<?= encode($youtubeEmbedUrl) ?>" 
            title="Product Video" 
            frameborder="0" 
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
            allowfullscreen
            style="border-radius: 10px;">
        </iframe>
    </div>
<?php endif; ?>

<div class="reviews-section">
    <h3>Ratings &amp; Reviews</h3>

    <?php if (isset($_user) && $_user->role == 'Member'): ?>
    <form method="post" class="review-form">
        <input type="hidden" name="id" value="<?= encode($id) ?>">
        <input type="hidden" name="btn" value="review">
        

        <label>Your Rating</label>
        <select name="rating" required>
            <option value="">- select -</option>
            <option value="5">5 ★★★★★</option>
            <option value="4">4 ★★★★☆</option>
            <option value="3">3 ★★★☆☆</option>
            <option value="2">2 ★★☆☆☆</option>
            <option value="1">1 ★☆☆☆☆</option>
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
            <div class="meta"><?= encode($r->user_name) ?> — <?= date('d M Y', strtotime($r->created_at)) ?></div>
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
    const isBundle = <?= $isBundle ? 'true' : 'false' ?>;
    const maxStock = <?= (int)$p->stock ?: 0 ?>;

    function changeQty(delta) {
        const display = document.getElementById('qtyDisplay');
        const field = document.getElementById('unitField');
        let val = parseInt(display.value) + delta;
        if (val < 1) val = 1;
        if (maxStock > 0 && val > maxStock) val = maxStock;
        display.value = val;
        field.value = val;

        if (!isBundle) {
            document.getElementById('prepSubText').innerText = `Select up to ${val}`;
            // If current prep choices exceed the new quantity, reset excess
            validatePrepLimit(val);
        }
    }

    const slides = document.querySelectorAll('#sliderTrack img');
    const dots = document.querySelectorAll('#sliderDots span');
    let current = 0;

    function updateSlider() {
        document.getElementById('sliderTrack').style.transform = `translateX(-${current * 280}px)`;
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

    // Toggle Section
    function toggleSection(sectionId, arrowId) {
        const sec = document.getElementById(sectionId);
        const arrow = document.getElementById(arrowId);
        const isHidden = sec.classList.toggle('hidden');
        arrow.innerText = isHidden ? '▼' : '▲';
    }

    function getTotal(className) {
        let sum = 0;
        document.querySelectorAll('.' + className).forEach(el => {
            sum += parseInt(el.value) || 0;
        });
        return sum;
    }

    function getMaxAllowed(type) {
        if (type === 'bagel') return 5;
        if (isBundle) return 5;
        return parseInt(document.getElementById('unitField').value) || 1;
    }

    function updateItem(type, id, delta) {
        const input = document.getElementById(`input_${type}_${id}`);
        const display = document.getElementById(`disp_${type}_${id}`);
        const className = type === 'bagel' ? 'bagel-choice' : 'prep-choice';
        const counter = document.getElementById(`${type}Counter`);
        const maxLimit = getMaxAllowed(type);

        let val = parseInt(input.value) || 0;
        let total = getTotal(className);

        if (delta > 0 && total >= maxLimit) return;
        if (delta < 0 && val <= 0) return;

        val += delta;
        input.value = val;
        display.innerText = val;

        total = getTotal(className);
        counter.innerText = `${total} selected`;

        // If it's a bundle, enforce 5 bagel selections before activating Add button
        if (isBundle) {
            const bagelTotal = getTotal('bagel-choice');
            const submitBtn = document.getElementById('submitBtn');
            if (bagelTotal === 5) {
                submitBtn.disabled = false;
                document.getElementById('bagelCounter').style.color = '#2e7d32';
            } else {
                submitBtn.disabled = true;
                document.getElementById('bagelCounter').style.color = '#d97736';
            }
        }
    }

    function validatePrepLimit(limit) {
        let total = getTotal('prep-choice');
        if (total > limit) {
            document.querySelectorAll('.prep-choice').forEach(el => {
                el.value = 0;
                const id = el.id.replace('input_prep_', '');
                document.getElementById(`disp_prep_${id}`).innerText = '0';
            });
            document.getElementById('prepCounter').innerText = '0 selected';
        }
    }
</script>

<?php
include '../_foot.php';