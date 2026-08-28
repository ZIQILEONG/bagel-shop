<?php
include '../_base.php';
require '../lib/SimplePager.php';

if (is_post()) {
    $id   = req('id');
    $unit = (int)req('unit', 1);
    update_cart($id, $unit);
    redirect();
}

// ---------------- Filters & Search ----------------
$search      = get('search', '');
$category_id = get('category_id', '');
$min_price   = get('min_price', '');
$max_price   = get('max_price', '');
$sort        = get('sort', 'name_asc');
$page        = get('page', '1');

$where  = [];
$params = [];

if ($search != '') {
    $where[]  = 'p.name LIKE ?';
    $params[] = "%$search%";
}
if ($category_id != '') {
    $where[]  = 'p.category_id = ?';
    $params[] = $category_id;
}
if ($min_price != '' && is_numeric($min_price)) {
    $where[]  = 'p.price >= ?';
    $params[] = $min_price;
}
if ($max_price != '' && is_numeric($max_price)) {
    $where[]  = 'p.price <= ?';
    $params[] = $max_price;
}

// ---------------- Sorting ----------------
switch ($sort) {
    case 'price_asc':
        $orderBy = 'p.price ASC, p.name ASC';
        break;
    case 'price_desc':
        $orderBy = 'p.price DESC, p.name ASC';
        break;
    case 'rating_desc':
        $orderBy = 'live_rating DESC, p.name ASC';
        break;
    case 'name_desc':
        $orderBy = 'p.name DESC';
        break;
    case 'name_asc':
    default:
        $orderBy = 'p.name ASC';
        break;
}

$sql = 'SELECT p.*, c.name AS category_name, 
               COALESCE(AVG(r.rating), 0) AS live_rating, 
               COUNT(r.id) AS review_count 
        FROM product p 
        LEFT JOIN category c ON p.category_id = c.id 
        LEFT JOIN product_review r ON p.id = r.product_id';

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' GROUP BY p.id ORDER BY ' . $orderBy;

// SimplePager: 8 items per page for a clean 4-column grid
$pager    = new SimplePager($sql, $params, 8, $page);
$products = $pager->result;

$categories = get_categories();

$_title = 'Shop Fresh Bagels | Pululu';
include '../_head.php';
?>

<style>
/* =========================================================
   PULULU COMPLETE SHOPPING PAGE UI/UX
   ========================================================= */
:root {
    --pl-primary: #cf7953;
    --pl-primary-hover: #b86440;
    --pl-brown-dark: #3e2619;
    --pl-text: #4a3b32;
    --pl-muted: #968377;
    --pl-border: #ebdcd5;
    --pl-card-bg: #ffffff;
    --pl-gold: #f5a623;
}

body {
    background-color: #faf5f0;
    color: var(--pl-text);
}

.pl-page-wrapper {
    max-width: 1160px;
    margin: 24px auto 60px;
    padding: 0 20px;
    box-sizing: border-box;
}

/* Header */
.pl-header-banner {
    text-align: center;
    margin-bottom: 28px;
}
.pl-header-banner h1 {
    font-size: 28px;
    font-weight: 800;
    color: var(--pl-brown-dark);
    margin: 0 0 6px;
}
.pl-header-banner p {
    font-size: 14px;
    color: var(--pl-muted);
    margin: 0;
}

/* Filter & Sort Bar Card */
.filter-card {
    background: var(--pl-card-bg);
    border: 1px solid var(--pl-border);
    border-radius: 16px;
    padding: 20px 24px;
    margin-bottom: 24px;
    box-shadow: 0 4px 16px rgba(62, 38, 25, 0.03);
}

.filter-grid {
    display: grid;
    grid-template-columns: 2fr 1.5fr 1.2fr 1fr 1fr auto;
    gap: 12px;
    align-items: end;
}

.filter-field label {
    display: block;
    font-size: 11.5px;
    font-weight: 700;
    color: var(--pl-brown-dark);
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}

.filter-field input,
.filter-field select {
    width: 100%;
    padding: 9.5px 12px;
    box-sizing: border-box;
    border: 1.5px solid var(--pl-border);
    border-radius: 10px;
    background: #fffdfc;
    font-size: 13.5px;
    color: var(--pl-text);
    outline: none;
    transition: all 0.2s ease;
}

.filter-field input:focus,
.filter-field select:focus {
    border-color: var(--pl-primary);
    box-shadow: 0 0 0 3px rgba(207, 121, 83, 0.12);
}

.btn-apply-filter {
    background: var(--pl-primary);
    color: #ffffff;
    border: none;
    padding: 10px 20px;
    border-radius: 10px;
    font-size: 13.5px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
    height: 40px;
}
.btn-apply-filter:hover {
    background: var(--pl-primary-hover);
    transform: translateY(-1px);
    box-shadow: 0 3px 10px rgba(207, 121, 83, 0.25);
}

/* Result Meta info */
.pl-results-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
    color: var(--pl-muted);
    margin-bottom: 18px;
    padding: 0 4px;
}

/* Products Grid (4 columns) */
.products-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 20px;
    margin-bottom: 36px;
}

/* Product Card */
.product-card {
    border: 1px solid var(--pl-border);
    border-radius: 16px;
    overflow: hidden;
    background: var(--pl-card-bg);
    display: flex;
    flex-direction: column;
    box-shadow: 0 3px 12px rgba(62, 38, 25, 0.03);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.product-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(62, 38, 25, 0.08);
}
.product-card .img-wrap {
    position: relative;
    width: 100%;
    aspect-ratio: 1 / 1;
    background: #fdfbf9;
    overflow: hidden;
}
.product-card img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    image-rendering: auto;
    transition: transform 0.3s ease;
}
.product-card:hover img {
    transform: scale(1.04);
}

.product-card .info {
    padding: 14px;
    display: flex;
    flex-direction: column;
    flex: 1;
}
.product-card .category-badge {
    font-size: 11px;
    font-weight: 700;
    color: var(--pl-primary);
    margin-bottom: 3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.product-card .name {
    font-size: 14.5px;
    font-weight: 700;
    color: var(--pl-brown-dark);
    margin-bottom: 5px;
    line-height: 1.3;
}
.product-card .rating {
    color: var(--pl-gold);
    font-size: 11.5px;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 4px;
}
.product-card .rating span:first-child {
    letter-spacing: 1px;
}
.product-card .price {
    font-size: 15.5px;
    font-weight: 800;
    color: var(--pl-primary);
    margin-bottom: 12px;
}

/* Card Action Bottom */
.card-action-wrap {
    margin-top: auto;
    padding-top: 10px;
    border-top: 1px solid #f8eee8;
}
.cart-form-row {
    display: flex;
    gap: 6px;
    align-items: center;
}
.cart-form-row select {
    width: 58px;
    padding: 7px;
    border-radius: 8px;
    border: 1.5px solid var(--pl-border);
    background: #fff;
    font-size: 13px;
    font-weight: 600;
    color: var(--pl-brown-dark);
    outline: none;
    cursor: pointer;
}
.cart-form-row select:focus {
    border-color: var(--pl-primary);
}
.btn-card-add {
    flex: 1;
    background: var(--pl-primary);
    color: #fff;
    border: none;
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 12.5px;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.15s ease;
}
.btn-card-add:hover {
    background: var(--pl-primary-hover);
}
.login-hint-btn {
    display: block;
    text-align: center;
    font-size: 12px;
    color: var(--pl-muted);
    text-decoration: none;
    padding: 8px 0;
    font-weight: 600;
}
.login-hint-btn:hover {
    color: var(--pl-primary);
    text-decoration: underline;
}

/* Empty State */
.pl-empty-state {
    text-align: center;
    padding: 60px 20px;
    background: #fff;
    border: 1px solid var(--pl-border);
    border-radius: 16px;
}
.pl-empty-state h3 {
    font-size: 18px;
    color: var(--pl-brown-dark);
    margin: 10px 0 6px;
}
.pl-empty-state p {
    font-size: 13px;
    color: var(--pl-muted);
    margin: 0;
}

/* Pagination */
.pl-pagination-wrap,
.pager {
    display: flex !important;
    justify-content: center !important;
    align-items: center !important;
    gap: 8px !important;
    margin: 45px auto 25px !important;
    padding: 0 !important;
    list-style: none !important;
}

.pl-pagination-wrap a,
.pl-pagination-wrap span,
.pager a,
.pager span {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    height: 38px !important;
    min-height: 38px !important;
    padding: 0 16px !important;
    min-width: 38px !important;
    width: auto !important;
    white-space: nowrap !important;
    border-radius: 9999px !important;
    border: 1px solid #ebdcd5 !important;
    background: #ffffff !important;
    color: #3e2619 !important;
    font-size: 13.5px !important;
    font-weight: 600 !important;
    text-decoration: none !important;
    box-shadow: 0 1px 3px rgba(62, 38, 25, 0.04) !important;
    box-sizing: border-box !important;
    transition: all 0.15s ease !important;
}

.pl-pagination-wrap a:hover,
.pager a:hover {
    background: #fff8f5 !important;
    border-color: #cf7953 !important;
    color: #cf7953 !important;
}

.pl-pagination-wrap .active,
.pl-pagination-wrap .current,
.pl-pagination-wrap span.current,
.pager .active,
.pager .current,
.pager span.current,
.pager a.current {
    background: #9d482b !important;
    border-color: #9d482b !important;
    color: #ffffff !important;
    font-weight: 700 !important;
    box-shadow: 0 2px 6px rgba(157, 72, 43, 0.25) !important;
}

.pl-pagination-wrap .disabled,
.pager .disabled {
    opacity: 0.4 !important;
    cursor: not-allowed !important;
    background: #ffffff !important;
    border-color: #ebdcd5 !important;
    color: #968377 !important;
}

@media (max-width: 1024px) {
    .products-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    .filter-grid {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 680px) {
    .products-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }
    .filter-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="pl-page-wrapper">
    <!-- Header -->
    <div class="pl-header-banner">
        <h1>Freshly Baked Bagels</h1>
        <p>Hand-rolled, boiled, and baked fresh every morning in small batches.</p>
    </div>

    <!-- Filter & Sorting Bar -->
    <div class="filter-card">
        <form method="get" class="filter-grid" id="filterForm">
            <div class="filter-field">
                <label>Search</label>
                <?= html_search('search', 'placeholder="Search bagels..." value="' . encode($search) . '"') ?>
            </div>

            <div class="filter-field">
                <label>Category</label>
                <?php
                $cat_opts = ['' => '-- All Categories --'];
                foreach ($categories as $c) {
                    $cat_opts[$c->id] = $c->name;
                }
                ?>
                <?= html_select('category_id', $cat_opts, $category_id) ?>
            </div>

            <div class="filter-field">
                <label>Sort By</label>
                <?php
                $sort_opts = [
                    'name_asc'    => 'Name (A to Z)',
                    'name_desc'   => 'Name (Z to A)',
                    'price_asc'   => 'Price: Low to High',
                    'price_desc'  => 'Price: High to Low',
                    'rating_desc' => 'Top Rated'
                ];
                ?>
                <?= html_select('sort', $sort_opts, $sort) ?>
            </div>

            <div class="filter-field">
                <label>Min (RM)</label>
                <?= html_number('min_price', 0, '', '0.50', 'placeholder="0.00" value="' . encode($min_price) . '"') ?>
            </div>

            <div class="filter-field">
                <label>Max (RM)</label>
                <?= html_number('max_price', 0, '', '0.50', 'placeholder="50.00" value="' . encode($max_price) . '"') ?>
            </div>

            <div>
                <button type="submit" class="btn-apply-filter">Apply</button>
            </div>
        </form>
    </div>

    <!-- Meta Info -->
    <div class="pl-results-meta">
        <span>Showing <?= count($products) ?> of <?= $pager->count ?> bagels</span>
        <?php if ($search || $category_id || $min_price || $max_price): ?>
            <a href="list.php" style="color: var(--pl-primary); text-decoration: none; font-weight: 600;">Clear Filters ✕</a>
        <?php endif; ?>
    </div>

    <!-- Products Grid (4 Columns) -->
    <?php if (!empty($products)): ?>
        <div class="products-grid">
            <?php foreach ($products as $p): ?>
                <?php
                $cart = get_cart();
                $cartEntry = $cart[$p->id] ?? 0;
                $unit = is_array($cartEntry) ? (int)($cartEntry['qty'] ?? 1) : (int)$cartEntry;
                $maxStockLimit = min(10, max(1, (int)$p->stock));

                // Direct live rating computation
                $revCount    = (int)($p->review_count ?? 0);
                $liveRating  = $revCount > 0 ? (float)$p->live_rating : 0.0;
                $filledStars = $revCount > 0 ? (int)round($liveRating) : 0;
                ?>
                <div class="product-card">
                    <a href="detail.php?id=<?= $p->id ?>" class="img-wrap">
                        <img src="/products/<?= encode($p->photo ?: 'default.jpg') ?>" alt="<?= encode($p->name) ?>">
                    </a>
                    <div class="info">
                        <span class="category-badge"><?= encode($p->category_name ?? 'Bagel') ?></span>
                        <div class="name"><?= encode($p->name) ?></div>

                        <div class="rating">
                            <span><?= str_repeat('★', $filledStars) ?><?= str_repeat('☆', 5 - $filledStars) ?></span>
                            <span><?= $revCount > 0 ? number_format($liveRating, 1) : '0.0' ?></span>
                        </div>

                        <div class="price">RM <?= number_format($p->price, 2) ?></div>

                        <div class="card-action-wrap">
                            <?php if (isset($_user) && $_user->role == 'Member'): ?>
                                <form method="post" class="cart-form-row">
                                    <input type="hidden" name="id" value="<?= encode($p->id) ?>">
                                    <select name="unit">
                                        <?php for ($i = 1; $i <= $maxStockLimit; $i++): ?>
                                            <option value="<?= $i ?>" <?= ($unit == $i || ($unit <= 0 && $i == 1)) ? 'selected' : '' ?>><?= $i ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <button type="submit" class="btn-card-add">Add</button>
                                </form>
                            <?php else: ?>
                                <a href="<?= app_url('login.php') ?>" class="login-hint-btn">Login to order</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination Bar -->
        <div class="pl-pagination-wrap">
            <?php $pager->html(); ?>
        </div>

    <?php else: ?>
        <div class="pl-empty-state">
            <div style="font-size: 38px;">🥯</div>
            <h3>No bagels found</h3>
            <p>Try adjusting your search criteria or price filters.</p>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const categorySelect = document.querySelector('select[name="category_id"]');
    const sortSelect = document.querySelector('select[name="sort"]');
    const filterForm = document.getElementById('filterForm');

    if (categorySelect) {
        categorySelect.addEventListener('change', () => filterForm.submit());
    }
    if (sortSelect) {
        sortSelect.addEventListener('change', () => filterForm.submit());
    }
});
</script>

<?php include '../_foot.php'; ?>