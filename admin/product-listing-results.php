<?php
// Included by product-listing.php for both normal and AJAX renders.
// Expects $pager, $arr, $search, $sort, $dir already set.
?>
<p><?= $pager->item_count ?> record(s)</p>
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
        <td><button data-get="product-detail.php?id=<?= $p->id ?>">Detail</button></td>
    </tr>
    <?php endforeach ?>
</table>
<button name="btn" value="delete_selected" data-confirm="Delete selected products?">Delete Selected</button>
<label>Increase price by (%)</label>
<input type="number" name="percent" step="0.01" min="0" style="width:80px">
<button name="btn" value="increase_price" data-confirm="Increase price for selected products?">Increase Price</button>
</form>
<?= $pager->html('search=' . encode($search) . '&sort=' . $sort . '&dir=' . $dir) ?>