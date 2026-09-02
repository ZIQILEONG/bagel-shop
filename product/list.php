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
        $orderBy = 'p.rating DESC, p.name ASC';
        break;
    case 'name_desc':
        $orderBy = 'p.name DESC';
        break;
    case 'name_asc':
    default:
        $orderBy = 'p.name ASC';
        break;
}

// Clean SQL structure so SimplePager calculates the total page count accurately
$sql = 'SELECT p.*, c.name AS category_name FROM product p LEFT JOIN category c ON p.category_id = c.id';

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY ' . $orderBy;

// SimplePager: 8 items per page (17 items will generate 3 pages: 8 + 8 + 1)
$pager    = new SimplePager($sql, $params, 8, $page);
$products = $pager->result;

$categories = get_categories();

$_title = 'Shop Fresh Bagels | Pululu';
include '../_head.php';
?>

<link rel="stylesheet" href="<?= app_url('css/product-list.css') ?>">

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
            <a class="il-94-7eaa30" href="list.php">Clear Filters ✕</a>
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

                // Read live rating and review count directly
                $revStm = $_db->prepare("SELECT COUNT(*) AS total_count, COALESCE(AVG(rating), 0) AS avg_rating FROM product_review WHERE product_id = ?");
                $revStm->execute([$p->id]);
                $revData = $revStm->fetch();

                $revCount    = (int)($revData->total_count ?? 0);
                $liveRating  = $revCount > 0 ? (float)$revData->avg_rating : 0.0;
                $filledStars = $revCount > 0 ? (int)round($liveRating) : 0;
                ?>
                <div class="product-card">
                    <a href="detail.php?id=<?= $p->id ?>" class="img-wrap">
                        <img src="/products/<?= encode($p->photo ?: 'default.jpg') ?>" alt="<?= encode($p->name) ?>">
                    </a>
                    <div class="info">
                        <span class="category-badge"><?= encode($p->category_name ?? 'Bagel') ?></span>
                        <div class="name"><?= encode($p->name) ?></div>

                        <div class="rating <?= $revCount > 0 ? 'has-reviews' : 'no-reviews' ?>">
                            <?php if ($revCount > 0): ?>
                                <span><?= str_repeat('★', $filledStars) ?><?= str_repeat('☆', 5 - $filledStars) ?></span>
                                <span><?= number_format($liveRating, 1) ?> (<?= $revCount ?>)</span>
                            <?php else: ?>
                                <span><?= str_repeat('☆', 5) ?></span>
                                <span class="il-95-15b680">0.0 (0)</span>
                            <?php endif; ?>
                        </div>

                        <div class="price">RM <?= number_format($p->price, 2) ?></div>

                        <div class="card-action-wrap">
                            <?php if (isset($_user) && $_user->role == 'Member'): ?>
                                <?php if ((int)$p->stock > 0): ?>
                                    <form method="post" class="cart-form-row">
                                        <input type="hidden" name="id" value="<?= encode($p->id) ?>">
                                        <select name="unit">
                                            <?php for ($i = 1; $i <= min(10, (int)$p->stock); $i++): ?>
                                                <option value="<?= $i ?>" <?= ($unit == $i || ($unit <= 0 && $i == 1)) ? 'selected' : '' ?>><?= $i ?></option>
                                            <?php endfor; ?>
                                        </select>
                                        <button type="submit" class="btn-card-add">Add</button>
                                    </form>
                                <?php else: ?>
                                    <button type="button" class="btn-card-add il-96-532e65" disabled>Out of Stock</button>
                                <?php endif; ?>
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
            <div class="il-97-c49588">🥯</div>
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