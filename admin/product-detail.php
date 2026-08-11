<?php
include '../_base.php';
auth('Admin');
$id = req('id');
$p = null;
if ($id) {
    $stm = $_db->prepare("SELECT * FROM product WHERE id = ?");
    $stm->execute([$id]);
    $p = $stm->fetch();
    if (!$p) {
        redirect('product-listing.php');
    }
}
if ($p && is_post() && req('btn') == 'delete') {
    $stm = $_db->prepare("DELETE FROM product WHERE id = ?");
    $ok  = $stm->execute([$p->id]);
    if ($ok) {
        temp('info', 'Product deleted.');
        redirect('product-listing.php');
    }
    temp('info', 'Cannot delete this product: it is referenced by existing orders.');
    redirect('product-detail.php?id=' . $p->id);
}
if (!$p && is_post() && req('btn') == 'batch') {
    $lines  = explode("\n", req('batch_data'));
    $count  = 0;
    $errors = [];
    foreach ($lines as $n => $line) {
        $line = trim($line);
        if ($line == '') {
            continue;
        }
        $cols = array_map('trim', explode(',', $line));
        if (count($cols) < 3 || $cols[0] == '' || !is_money($cols[1]) || !ctype_digit($cols[2])) {
            $errors[] = 'Line ' . ($n + 1) . ': invalid data';
            continue;
        }
        [$bName, $bPrice, $bStock] = $cols;
        $max   = $_db->query("SELECT MAX(id) FROM product")->fetchColumn();
        $num   = $max ? ((int) substr($max, 1) + 1) : 1;
        $newId = 'P' . str_pad($num, 3, '0', STR_PAD_LEFT);
        $stm = $_db->prepare("INSERT INTO product (id, name, price, photo, stock) VALUES (?, ?, ?, ?, ?)");
        $stm->execute([$newId, $bName, $bPrice, 'default.jpg', $bStock]);
        $count++;
    }
    temp('info', "$count product(s) imported." . ($errors ? ' Skipped - ' . implode('; ', $errors) : ''));
    redirect('product-listing.php');
}
if (is_post() && req('btn') != 'delete' && req('btn') != 'batch') {
    $name  = req('name');
    $price = req('price');
    $stock = req('stock');
    $photo = $p->photo ?? 'default.jpg';
    if ($name == '') {
        $_err['name'] = 'Required';
    }
    if ($price == '') {
        $_err['price'] = 'Required';
    }
    else if (!is_money($price) || $price < 0) {
        $_err['price'] = 'Invalid value';
    }
    if ($stock == '') {
        $_err['stock'] = 'Required';
    }
    else if (!ctype_digit($stock)) {
        $_err['stock'] = 'Invalid value';
    }
    $f = get_file('photo');
    if ($f && !getimagesize($f->tmp_name)) {
        $_err['photo'] = 'Invalid image';
    }
    if (!$_err) {
        if ($f) {
            $dir = root('products');
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $photo = save_photo($f, $dir);
        }
        if ($p) {
            $stm = $_db->prepare("UPDATE product SET name = ?, price = ?, stock = ?, photo = ? WHERE id = ?");
            $stm->execute([$name, $price, $stock, $photo, $p->id]);
            temp('info', 'Product updated.');
            redirect('product-detail.php?id=' . $p->id);
        }
        else {
            $max   = $_db->query("SELECT MAX(id) FROM product")->fetchColumn();
            $num   = $max ? ((int) substr($max, 1) + 1) : 1;
            $newId = 'P' . str_pad($num, 3, '0', STR_PAD_LEFT);
            $stm = $_db->prepare("INSERT INTO product (id, name, price, photo, stock) VALUES (?, ?, ?, ?, ?)");
            $stm->execute([$newId, $name, $price, $photo, $stock]);
            temp('info', 'Product created.');
            redirect('product-detail.php?id=' . $newId);
        }
    }
}
$name  = $name  ?? $p->name  ?? '';
$price = $price ?? $p->price ?? '';
$stock = $stock ?? $p->stock ?? '';
// ----------------------------------------------------------------------------
$_title = $p ? 'Product | Detail (Admin)' : 'Product | Create (Admin)';
include '../_head.php';
?>
<form method="post" enctype="multipart/form-data" class="form">
    <?php if ($p): ?>
    <label>Id</label>
    <b><?= $p->id ?></b>
    <br>
    <?php endif ?>
    <label for="name">Name</label>
    <?= html_text('name', 'maxlength="100"') ?>
    <?= err('name') ?>
    <label for="price">Price (RM)</label>
    <?= html_text('price', 'maxlength="10"') ?>
    <?= err('price') ?>
    <label for="stock">Stock</label>
    <?= html_text('stock', 'maxlength="10"') ?>
    <?= err('stock') ?>
    <label class="upload" for="photo">
        <img src="/products/<?= $p->photo ?? 'default.jpg' ?>">
        <?= html_file('photo', 'image/*') ?>
    </label>
    <?= err('photo') ?>
    <section>
        <button>Save</button>
    </section>
</form>
<?php if (!$p): ?>
<form method="post" class="form">
    <label for="batch_data">Batch Add (one per line: name,price,stock)</label>
    <?= html_textarea('batch_data', 'rows="6" placeholder="Plain Bagel,3.50,50&#10;Sesame Bagel,3.80,40"') ?>
    <section>
        <button name="btn" value="batch">Import</button>
    </section>
</form>
<?php endif ?>
<?php if ($p): ?>
<p>
    <button data-post="product-detail.php?id=<?= $p->id ?>&btn=delete" data-confirm>Delete Product</button>
</p>
<?php endif ?>
<p>
    <button data-get="product-listing.php">Back to Listing</button>
</p>
<?php
include '../_foot.php';