<?php
include '../_base.php';
require '../lib/SimplePager.php';

if (is_post()) {
    $id   = req('id');
    $unit = req('unit');
    update_cart($id, $unit);
    redirect();
}

$search = get('search', '');
$sort   = get('sort', 'name');
$dir    = get('dir', 'asc') == 'desc' ? 'desc' : 'asc';
$page   = get('page', '1');

$sorts = ['name', 'price', 'stock'];
if (!in_array($sort, $sorts)) {
    $sort = 'name';
}

$where  = '';
$params = [];
if ($search != '') {
    $where = 'WHERE name LIKE ?';
    $params[] = "%$search%";
}

$query = "SELECT * FROM product $where ORDER BY $sort $dir";
$pager = new SimplePager($query, $params, '12', $page);
$arr   = $pager->result;
$_title = 'Product | List';
include '../_head.php';
?>
<style>
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    .product {
        border: 1px solid
        width: 200px;
        height: 200px;
        position: relative;
    }
    .product img {
        display: block;
        width: 100%;
        height: 100%;
        cursor: pointer;
    }
    .product form,
    .product div {
        position: absolute;
        background:
        color:
        padding: 5px;
        text-align: center;
    }
    .product form {
        inset: 0 0 auto auto;
    }
    .product div {
        inset: auto 0 0 0;
    }
</style>
<form method="get" class="form">
    <?= html_search('search', 'placeholder="Search bagels..."') ?>
    <?= html_select('sort', ['name' => 'Name', 'price' => 'Price', 'stock' => 'Stock'], null) ?>
    <?= html_select('dir', ['asc' => 'Low to High / A-Z', 'desc' => 'High to Low / Z-A'], null) ?>
    <button type="submit">Apply</button>
</form>
<div id="products">
    <?php foreach ($arr as $p): ?>
        <?php
        $cart = get_cart();
        $id   = $p->id;
        $unit = $cart[$p->id] ?? 0;
        ?>
        <div class="product">
            <form method="post">
                <?= $unit ? '✅' : '' ?>
                <?= html_hidden('id') ?>
                <?= html_select('unit', $_units, '') ?>
            </form>
            <img src="/products/<?= $p->photo ?>"
                 data-get="/product/detail.php?id=<?= $p->id ?>">
            <div><?= $p->name ?> | RM <?= $p->price ?></div>
        </div>
    <?php endforeach ?>
</div>
<?= $pager->html('search=' . encode($search) . '&sort=' . $sort . '&dir=' . $dir) ?>
<script>
    $('select').on('change', e => e.target.form.submit());
</script>
<?php
include '../_foot.php';