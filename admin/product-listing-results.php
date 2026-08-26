<?php
// Included by product-listing.php for both normal and AJAX renders.
// Expects $pager, $arr, $search, $sort, $dir to ve already set.
$_low_stock_threshold = 11;
?>
<p><?= $pager->item_count ?> record(s)</p>

<!-- Add New Product  -->
<a href="product-create.php" style="display:inline-block; margin-bottom:10px; padding:8px 14px; background:#8b4513; color:white; text-decoration:none; border-radius:4px;">
    + Add New Product
</a>

<form method="post">
<table class="table">
    <tr>
        <th></th>
        <th>Photo</th>
        <?= table_headers(['id' => 'Id', 'category_name' => 'Category', 'name' => 'Name', 'price' => 'Price (RM)', 'stock' => 'Stock'], $sort, $dir, 'search=' . encode($search)) ?>
        <th></th>
    </tr>
    <?php foreach ($arr as $p): ?>
    <?php $is_low_stock = $p->stock < $_low_stock_threshold; ?>
    <tr<?= $is_low_stock ? " style='background:#ffe8e8'" : '' ?>>
        <td><input type="checkbox" name="ids[]" value="<?= $p->id ?>"></td>
        <td><img src="/products/<?= $p->photo ?>" width="50" height="50"></td>
        <td><?= $p->id ?></td>
        <td><?= $p->category_name ?></td>
        <td><?= $p->name ?></td>
        <td class="right"><?= $p->price ?></td>
        <td class="right">
            <?= $p->stock ?>
            <?php if ($is_low_stock): ?>
                <span style="display:inline-block;margin-left:6px;padding:2px 8px;border-radius:999px;background:#b5192b;color:#fff;font-size:11px;font-weight:bold;">Low Stock</span>
            <?php endif ?>
        </td>
        <td><button data-get="product-detail.php?id=<?= $p->id ?>">Detail</button></td>
    </tr>
    <?php endforeach ?>
</table>
<button name="btn" value="delete_selected" data-confirm="Delete selected products?">Delete Selected</button>
<label>Increase price by (%)</label>
<input type="number" name="percent" step="0.01" min="0" style="width:80px">
<button name="btn" value="increase_price">Increase Price</button>
</form>
<?= $pager->html('search=' . encode($search) . '&sort=' . $sort . '&dir=' . $dir) ?>
