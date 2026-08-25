<?php
include '../_base.php';

if (is_post()) {
    $id   = req('id');
    $unit = req('unit');
    update_cart($id, $unit);
    redirect();
}

$id  = req('id');
$stm = $_db->prepare('SELECT * FROM product WHERE id = ?');
$stm->execute([$id]);
$p = $stm->fetch();
if (!$p) redirect('list.php');

// Fetch gallery photos (from product_photo table)
$stm = $_db->prepare('SELECT photo FROM product_photo WHERE product_id = ? ORDER BY sort_order, id');
$stm->execute([$id]);
$gallery = $stm->fetchAll(PDO::FETCH_COLUMN);

// Build slider list: main photo first, then gallery photos
$slides = [];
if ($p->photo) $slides[] = '/products/' . $p->photo;
foreach ($gallery as $g) $slides[] = '/photos/products/' . $g;
if (!$slides) $slides[] = '/products/default.jpg';

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