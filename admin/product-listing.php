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
$_title = 'Product | Listing (Admin)';
include '../_head.php';
?>
<p><?= $pager->item_count ?> record(s)</p>
<p>
    <button data-get="product-detail.php">Create New Product</button>
</p>
<form method="get" class="form">
    <?= html_search('search', 'placeholder="Search by name"') ?>
    <?= html_hidden('sort') ?>
    <?= html_hidden('dir') ?>
    <button type="submit">Search</button>
</form>
<form method="post">
<table class="table">
    <tr>
        <th></th>
        <th>Photo</th>
        <?= table_headers(['id' => 'Id', 'name' => 'Name', 'price' => 'Price (RM)', 'stock' => 'Stock'], $sort, $dir, 'search=' . encode($search)) ?>
        <th></th>
    </tr>
    <?php foreach ($arr as $p): ?>
    <tr>
        <td><input type="checkbox" name="ids[]" value="<?= $p->id ?>"></td>
        <td><img src="/products/<?= $p->photo ?>" width="50" height="50"></td>
        <td><?= $p->id ?></td>
        <td><?= $p->name ?></td>
        <td class="right"><?= $p->price ?></td>
        <td class="right"><?= $p->stock ?></td>
        <td>
            <button data-get="product-detail.php?id=<?= $p->id ?>">Detail</button>
        </td>
    </tr>
    <?php endforeach ?>
</table>
<button name="btn" value="delete_selected" data-confirm="Delete selected products?">Delete Selected</button>
</form>
<?= $pager->html('search=' . encode($search) . '&sort=' . $sort . '&dir=' . $dir) ?>
<?php
include '../_foot.php';