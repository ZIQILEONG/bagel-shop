<?php
include '../_base.php';

// ----------------------------------------------------------------------------

// (1) Authorization (admin)
auth('Admin');

// (2) Return product (based on id) - null means "create new"
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

// (3) Handle delete
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

// (4) Handle create/update
if (is_post() && req('btn') != 'delete') {
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
            // Update existing product
            $stm = $_db->prepare("UPDATE product SET name = ?, price = ?, stock = ?, photo = ? WHERE id = ?");
            $stm->execute([$name, $price, $stock, $photo, $p->id]);

            temp('info', 'Product updated.');
            redirect('product-detail.php?id=' . $p->id);
        }
        else {
            // Create new product - generate the next Pxxx id
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

// Repopulate form fields for redisplay
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