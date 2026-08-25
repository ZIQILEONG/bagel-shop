<?php
include __DIR__ . '/../_base.php';
require_once __DIR__ . '/../lib/SimplePager.php';

// Handle add to cart
if (is_post()) {
    $id   = req('id');
    $unit = req('unit');
    update_cart($id, $unit);
    redirect();
}

// Get filter parameters
$search = get('search', '');
$category_id = get('category_id', '');
$min_price = get('min_price', '');
$max_price = get('max_price', '');
$sort   = get('sort', 'name');
$dir    = get('dir', 'asc') == 'desc' ? 'desc' : 'asc';
$page   = get('page', '1');

// Validate sort field
$sorts = ['name', 'price', 'stock', 'rating'];
if (!in_array($sort, $sorts)) {
    $sort = 'name';
}

// Build WHERE clause
$where_conditions = [];
$params = [];

if ($search != '') {
    $where_conditions[] = 'p.name LIKE ?';
    $params[] = "%$search%";
}

if ($category_id != '') {
    $where_conditions[] = 'p.category_id = ?';
    $params[] = $category_id;
}

if ($min_price != '') {
    $where_conditions[] = 'p.price >= ?';
    $params[] = $min_price;
}

if ($max_price != '') {
    $where_conditions[] = 'p.price <= ?';
    $params[] = $max_price;
}

$where = '';
if (count($where_conditions) > 0) {
    $where = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Build query
$query = "SELECT p.*,
    (SELECT AVG(rating) FROM product_review WHERE product_id = p.id) AS rating,
    (SELECT COUNT(*) FROM product_review WHERE product_id = p.id) AS review_count
    FROM product p $where ORDER BY $sort $dir";
$pager = new SimplePager($query, $params, '12', $page);
$arr   = $pager->result;

// Get categories for filter dropdown
$categories = get_categories();

$_title = 'Product | List';
include '../_head.php';
?>

<style>
    .filter-section {
        background: var(--white);
        padding: 20px;
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        margin-bottom: 20px;
    }
    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 15px;
        align-items: end;
    }
    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    .filter-group label {
        font-weight: bold;
        color: var(--brown);
        font-size: 14px;
    }
    .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    .product-card {
        background: var(--white);
        border-radius: var(--radius);
        overflow: hidden;
        box-shadow: var(--shadow);
        transition: transform 0.2s, box-shadow 0.2s;
        position: relative;
    }
    .product-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.12);
    }
    .product-image {
        width: 100%;
        height: 200px;
        object-fit: cover;
        cursor: pointer;
        transition: opacity 0.2s;
    }
    .product-image:hover {
        opacity: 0.9;
    }
    .product-info {
        padding: 15px;
    }
    .product-name {
        font-weight: bold;
        color: var(--text);
        margin-bottom: 8px;
        font-size: 16px;
    }
    .product-price {
        color: var(--red);
        font-weight: bold;
        font-size: 18px;
        margin-bottom: 5px;
    }
    .product-stock {
        color: var(--text-muted);
        font-size: 13px;
        margin-bottom: 10px;
    }
    .product-rating {
        color: #fbbf24;
        font-size: 14px;
        margin-bottom: 10px;
    }
    .product-actions {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    .product-actions select {
        flex: 1;
        padding: 8px;
        border-radius: 6px;
        border: 1px solid var(--border);
    }
    .product-actions button {
        padding: 8px 15px;
        font-size: 13px;
    }
    .cart-indicator {
        position: absolute;
        top: 10px;
        right: 10px;
        background: var(--green, #10b981);
        color: white;
        padding: 4px 8px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: bold;
    }
    .clear-filters {
        display: inline-block;
        margin-top: 15px;
        color: var(--red);
        text-decoration: underline;
        cursor: pointer;
    }
</style>

<div class="filter-section">
    <form method="get" class="filter-grid">
        <div class="filter-group">
            <label>Search</label>
            <?= html_search('search', 'placeholder="Search bagels..."') ?>
        </div>

        <div class="filter-group">
            <label>Category</label>
            <?= html_select('category_id', array_column($categories, 'name', 'id'), 'All Categories') ?>
        </div>

        <div class="filter-group">
            <label>Min Price (RM)</label>
            <?= html_number('min_price', '', '', '0.01', 'placeholder="0"') ?>
        </div>

        <div class="filter-group">
            <label>Max Price (RM)</label>
            <?= html_number('max_price', '', '', '0.01', 'placeholder="100"') ?>
        </div>

        <div class="filter-group">
            <label>Sort By</label>
            <?= html_select('sort', ['name' => 'Name', 'price' => 'Price', 'stock' => 'Stock', 'rating' => 'Rating'], null) ?>
        </div>

        <div class="filter-group">
            <label>Order</label>
            <?= html_select('dir', ['asc' => 'Low to High / A-Z', 'desc' => 'High to Low / Z-A'], null) ?>
        </div>

        <div class="filter-group">
            <button type="submit">Apply Filters</button>
        </div>
    </form>

    <?php if ($search || $category_id || $min_price || $max_price): ?>
    <a href="/product/list.php" class="clear-filters">✕ Clear all filters</a>
    <?php endif; ?>
</div>

<div class="products-grid">
    <?php foreach ($arr as $p): ?>
        <?php
        $cart = get_cart();
        $id   = $p->id;
        $unit = $cart[$p->id] ?? 0;
        ?>
        <div class="product-card">
            <?php if ($unit): ?>
            <div class="cart-indicator">✅ In Cart</div>
            <?php endif; ?>

            <img src="/products/<?= $p->photo ?>"
                 alt="<?= encode($p->name) ?>"
                 class="product-image"
                 data-get="/product/detail.php?id=<?= $p->id ?>">

            <div class="product-info">
                <div class="product-name"><?= encode($p->name) ?></div>

                <?php if ($p->rating): ?>
                <div class="product-rating">
                    <?= str_repeat('★', round($p->rating)) ?>
                    <?= number_format($p->rating, 1) ?>
                </div>
                <?php endif; ?>

                <div class="product-price">RM <?= number_format($p->price, 2) ?></div>
                <div class="product-stock">Stock: <?= $p->stock ?></div>

                <?php if ($_user?->role == 'Member'): ?>
                <form method="post" class="product-actions">
                    <?= html_hidden('id') ?>
                    <?= html_select('unit', $_units, $unit ? $unit : '') ?>
                    <button type="submit">Add</button>
                </form>
                <?php else: ?>
                <div class="product-actions">
                    <a href="/login.php" style="font-size: 13px; color: var(--red);">Login to order</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?= $pager->html(build_filter_query()) ?>

<script>
$(document).ready(function() {
    // Auto-submit on select change
    $('.filter-grid select').on('change', function(e) {
        // Only auto-submit for sort and dir
        if ($(this).attr('name') === 'sort' || $(this).attr('name') === 'dir') {
            this.form.submit();
        }
    });
});
</script>

<?php
// Helper function to build query string with current filters
function build_filter_query() {
    $params = [];
    $search = get('search', '');
    $category_id = get('category_id', '');
    $min_price = get('min_price', '');
    $max_price = get('max_price', '');
    $sort = get('sort', 'name');
    $dir = get('dir', 'asc');

    if ($search) $params[] = 'search=' . urlencode($search);
    if ($category_id) $params[] = 'category_id=' . urlencode($category_id);
    if ($min_price) $params[] = 'min_price=' . urlencode($min_price);
    if ($max_price) $params[] = 'max_price=' . urlencode($max_price);
    if ($sort) $params[] = 'sort=' . urlencode($sort);
    if ($dir) $params[] = 'dir=' . urlencode($dir);

    return implode('&', $params);
}

include '../_foot.php';
