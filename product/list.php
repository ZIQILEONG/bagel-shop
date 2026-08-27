<?php
include '../_base.php';

if (is_post()) {
    $id   = req('id');
    $unit = req('unit');
    update_cart($id, $unit);
    redirect();
}

$search      = get('search', '');
$category_id = get('category_id', '');
$min_price   = get('min_price', '');
$max_price   = get('max_price', '');

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
if ($min_price != '') {
    $where[]  = 'p.price >= ?';
    $params[] = $min_price;
}
if ($max_price != '') {
    $where[]  = 'p.price <= ?';
    $params[] = $max_price;
}

$sql = 'SELECT p.* FROM product p';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY p.name';

$stm = $_db->prepare($sql);
$stm->execute($params);
$products = $stm->fetchAll();

$categories = get_categories();

$_title = 'Product | List';
include '../_head.php';
?>

<style>
    .filter-bar {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 12px;
        align-items: end;
        background: #fafafa;
        border: 1px solid #eee;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 20px;
    }
    .filter-bar label { display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; }
    .filter-bar input, .filter-bar select { width: 100%; padding: 7px; box-sizing: border-box; }

    .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
        gap: 18px;
    }
    .product-card {
        border: 1px solid #eee;
        border-radius: 10px;
        overflow: hidden;
        background: #fff;
    }
    .product-card img {
        width: 100%; height: 180px; object-fit: cover; cursor: pointer;
    }
    .product-card .info { padding: 12px; }
    .product-card .name { font-weight: bold; margin-bottom: 4px; }
    .product-card .price { color: #d9534f; font-weight: bold; }
    .product-card .rating { color: #fbbf24; font-size: 13px; }
</style>

<form method="get" class="filter-bar">
    <div>
        <label>Search</label>
        <?= html_search('search', 'placeholder="Search bagels..."') ?>
    </div>
    <div>
        <label>Category</label>
        <?php
        $cat_opts = [];
        foreach ($categories as $c) $cat_opts[$c->id] = $c->name;
        ?>
        <?= html_select('category_id', $cat_opts, 'All Categories') ?>
    </div>
    <div>
        <label>Min Price (RM)</label>
        <?= html_number('min_price', 0, '', '0.01') ?>
    </div>
    <div>
        <label>Max Price (RM)</label>
        <?= html_number('max_price', 0, '', '0.01') ?>
    </div>
    <div>
        <button type="submit">Apply</button>
    </div>
</form>

<div class="products-grid">
    <?php foreach ($products as $p): ?>
        <?php
        $cart = get_cart();
        $id   = $p->id;
        $unit = $cart[$p->id] ?? 0;
        ?>
        <div class="product-card">
            <a href="detail.php?id=<?= $p->id ?>">
                <img src="/products/<?= encode($p->photo) ?>" alt="<?= encode($p->name) ?>">
            </a>
            <div class="info">
                <div class="name"><?= encode($p->name) ?></div>
                <?php if ($p->rating): ?>
                <div class="rating"><?= str_repeat('★', round($p->rating)) ?> <?= number_format($p->rating, 1) ?></div>
                <?php endif; ?>
                <div class="price">RM <?= number_format($p->price, 2) ?></div>
                <div>Stock: <?= $p->stock ?></div>

                <?php if ($_user?->role == 'Member'): ?>
                <form method="post">
                    <?= html_hidden('id') ?>
                    <?= html_select('unit', $_units, $unit ?: '') ?>
                    <button type="submit">Add</button>
                </form>
                <?php else: ?>
                <a href="<?= app_url('login.php') ?>">Login to order</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php include '../_foot.php'; ?>