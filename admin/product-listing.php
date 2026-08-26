<?php
include '../_base.php';
require '../lib/SimplePager.php';
auth('Admin');

if (is_post() && req('btn') == 'delete_selected') {
    $ids = post('ids', []);
    if (is_array($ids) && count($ids) > 0) {
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $stm = $_db->prepare("DELETE FROM product WHERE id IN ($in)");
        $stm->execute($ids);
        temp('info', count($ids) . ' product(s) deleted.');
    }
    redirect('product-listing.php');
}

if (is_post() && req('btn') == 'increase_price') {
    $ids     = post('ids', []);
    $percent = req('percent');
    if (is_array($ids) && count($ids) > 0 && is_numeric($percent) && $percent >= -100) {
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $stm = $_db->prepare("UPDATE product SET price = ROUND(price * (1 + ?/100), 2) WHERE id IN ($in)");
        $stm->execute(array_merge([$percent], $ids));
        $action = $percent < 0 ? 'decreased' : 'increased';
        temp('info', count($ids) . ' product(s) price ' . $action . ' by ' . abs($percent) . '%.');
    }
    redirect('product-listing.php');
}

$search = get('search', '');
$sort   = get('sort', 'id');
$dir    = get('dir', 'asc') == 'desc' ? 'desc' : 'asc';
$page   = get('page', '1');

$sorts = ['id', 'name', 'price', 'stock'];
if (!in_array($sort, $sorts)) {
    $sort = 'id';
}

$where  = '';
$params = [];
if ($search != '') {
    $where = 'WHERE name LIKE ?';
    $params[] = "%$search%";
}

$query = "SELECT * FROM product $where ORDER BY $sort $dir";
$pager = new SimplePager($query, $params, '10', $page);
$arr   = $pager->result;
if (get('ajax') == '1') {
    include 'product-listing-results.php';
    exit();
}
$_title = 'Product | Listing (Admin)';
include '../_head.php';
?>
<form method="get" class="form" id="searchForm">
    <?= html_search('search', 'placeholder="Search by name" autocomplete="off"') ?>
    <?= html_hidden('sort') ?>
    <?= html_hidden('dir') ?>
    <button type="submit">Search</button>
</form>

<div id="resultsWrap">
<?php include 'product-listing-results.php'; ?>
</div>
<script src="../js/product-listing.js"></script>
<?php
include '../_foot.php';