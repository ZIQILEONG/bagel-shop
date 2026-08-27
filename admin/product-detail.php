<?php
include '../_base.php';
auth('Admin');
$id = req('id');
$p = null;
if ($id) {
    $stm = $_db->prepare("
        SELECT 
            p.*
        FROM product p 
        WHERE p.id = ?"
    );
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
    $category_id = req('category_id');
    $price = req('price');
    $stock = req('stock');
    $description = req('description');
    $video_url = req('video_url');
    $photo = $p->photo ?? 'default.jpg';

    if ($name == '') {
        $_err['name'] = 'Required';
    }
    if ($category_id == '') {
        $_err['category_id'] = 'Required';
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
    if ($video_url !== '' && (!filter_var($video_url, FILTER_VALIDATE_URL) || !str_contains($video_url, 'youtube.com'))) 
        $_err['video_url'] = "Product video URL is not valid.<br>Example: https://www.youtube.com/watch?v=example";
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
            $stm = $_db->prepare("UPDATE product SET name = ?, category_id = ?, price = ?, stock = ?, photo = ?, description = ?, video_url = ? WHERE id = ?");
            $stm->execute([$name, $category_id, $price, $stock, $photo, $description, $video_url, $p->id]);            

            if (isset($_POST['sort_order'])) {
                foreach ($_POST['sort_order'] as $photoId => $sortOrder) {
                    $stm = $_db->prepare("
                        UPDATE product_photo
                        SET sort_order = ?
                        WHERE id = ?
                    ");

                    $stm->execute([
                        $sortOrder,
                        $photoId
                    ]);
                }
            }

            temp('info', 'Product updated.');
            redirect('product-detail.php?id=' . $p->id);
        }
        else {
            $max   = $_db->query("SELECT MAX(id) FROM product")->fetchColumn();
            $num   = $max ? ((int) substr($max, 1) + 1) : 1;
            $newId = 'P' . str_pad($num, 3, '0', STR_PAD_LEFT);
            $stm = $_db->prepare("INSERT INTO product (id, name, category_id, price, photo, stock, description, video_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stm->execute([$newId, $name, $category_id, $price, $photo, $stock, $description, $video_url]);
            temp('info', 'Product created.');
            redirect('product-detail.php?id=' . $newId);
        }
    }
}
$name  = $name  ?? $p->name  ?? '';
$price = $price ?? $p->price ?? '';
$stock = $stock ?? $p->stock ?? '';
$category_id = $category_id ?? $p->category_id ?? '';
$description = $description ?? $p->description ?? '';
$video_url = $video_url ?? $p->video_url ?? '';

$photoStm = $_db->prepare("
    SELECT *
    FROM product_photo
    WHERE product_id = ?
    ORDER BY sort_order
");
$photoStm->execute([$p->id]);
$photos = $photoStm->fetchAll();

// ----------------------------------------------------------------------------
$_title = $p ? 'Product | Detail (Admin)' : 'Product | Create (Admin)';
include '../_head.php';
?>
<style>
.detail-photos {
    grid-column: 1 / -1;
    margin-top: 20px;
    width: 100%;
}

.photo-header {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
    margin-bottom: 12px;
}

.photo-header div {
    background: #f9e5db;
    padding: 14px;
    border-radius: 8px;
    font-weight: bold;
}

.photo-row {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 12px;

    padding: 16px;
    margin-bottom: 16px;

    background: #fffaf7;
    border: 1px solid #ead5ca;
    border-radius: 10px;
}

.photo-row div {
    background: #f9e5db;
    padding: 14px;
    border-radius: 8px;
    font-weight: bold;
}

.photo-box {
    width: 100%;
    height: 100px;
    overflow: hidden;
    border-radius: 8px;
    background: #eee;
}

.photo-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    cursor: pointer;
    transition: transform 0.3s ease;
}

.photo-box img.enlarged {
    position: fixed;
    top: 50%;
    left: 50%;
    max-width: 80vw;
    max-height: 80vh;
    width: auto;
    height: auto;
    object-fit: contain;
    transform: translate(-50%, -50%);
    z-index: 9999;
    background: white;
    padding: 10px;
    border-radius: 10px;
    box-shadow: 0 0 30px rgba(0,0,0,0.4);
}
</style>

<script>
function enlargePhoto(img) {
    img.classList.toggle('enlarged');
}
</script>

<form method="post" enctype="multipart/form-data" class="form" style="grid: auto / 1fr 2fr 1fr !important;>
    <?php if ($p): ?>
    <label>Id</label>
    <input type="text" value="<?= $p->id ?>" disabled>
    <?php endif ?>
    <label for="name">Name</label>
    <?= html_text('name', 'maxlength="100" style="width:100%;"') ?>
    <?= err('name') ?>
    <label>Category</label>
    <select name="category_id" style="width:100%;padding:6px;" required>
        <option value="">-- Select Category --</option>
        <?php
        $catStm = $_db->query("SELECT id, name FROM category ORDER BY name");
        while ($cat = $catStm->fetch()) {
            $selected = ($p -> category_id == $cat -> id) ? 'selected' : '';
            echo "<option value=\"{$cat -> id}\" $selected>{$cat -> name}</option>";
        }
        ?>
    </select>
    <?= err('category_id') ?>
    <label for="price">Price (RM)</label>
    <?= html_text('price', 'maxlength="10" style="width:100%;"') ?>
    <?= err('price') ?>
    <label for="stock">Stock</label>
    <?= html_text('stock', 'maxlength="10" style="width:100%;"') ?>
    <?= err('stock') ?>
    <label for="description">Description</label>
    <textarea name="description" id="description" rows="4" style="width:100%;"><?= htmlspecialchars(trim($p->description)) ?></textarea>
    <br>
    <label for="video_url">Product Video Url:</label>
    <?= html_text('video_url', 'placeholder="https://www.youtube.com/watch?v=example" style="width:100%;"') ?>
    <?= err('video_url') ?>
    <label>Product Photo:</label>
    <label class="upload" for="photo">
        <img src="/products/<?= $p->photo ?? 'default.jpg' ?>">
        <?= html_file('photo', 'image/*') ?>
    </label>
    <?= err('photo') ?>

    <!-- Display product detail photos if available -->
    <div class="detail-photos">
        <div class="photo-header">
            <div>Product Detail Photo</div>
        </div>

        <div class="photo-row">
            <div>Order</div>
            <div>Photo Name</div>
            <div>Photo</div>

            <?php foreach ($photos as $photo): ?>
                <div class="photo-order">
                    <input
                        type="number"
                        name="sort_order[<?= $photo->id ?>]"
                        value="<?= $photo->sort_order ?>"
                        style="width:100%;height:100%;"
                        min="1" 
                    >
                </div>

                <div class="photo-name">
                    <?= htmlspecialchars($photo->photo) ?>
                </div>

                    </div>
</div>

<!-- === NEW: Image edit (rotate/flip) section === -->
<div class="detail-photos" style="margin-top:20px;">
    <div class="photo-header"><div>Edit Photos (Rotate / Flip)</div></div>
    <div class="photo-row" style="grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));">
        <?php if ($p && $p->photo): ?>
        <div>
            <div class="photo-box"><img src="/products/<?= encode($p->photo) ?>"></div>
            <div style="display:flex;gap:4px;margin-top:6px;flex-wrap:wrap;">
                <button type="button" data-post="image-edit.php?id=<?= $p->id ?>&photo_id=&action=rotate_left">⟲</button>
                <button type="button" data-post="image-edit.php?id=<?= $p->id ?>&photo_id=&action=rotate_right">⟳</button>
                <button type="button" data-post="image-edit.php?id=<?= $p->id ?>&photo_id=&action=flip_h">⇋</button>
                <button type="button" data-post="image-edit.php?id=<?= $p->id ?>&photo_id=&action=flip_v">⇅</button>
            </div>
        </div>
        <?php endif; ?>
        <?php foreach ($photos as $photo): ?>
        <div>
            <div class="photo-box"><img src="/products/<?= encode($photo->photo) ?>"></div>
            <div style="display:flex;gap:4px;margin-top:6px;flex-wrap:wrap;">
                <button type="button" data-post="image-edit.php?id=<?= $p->id ?>&photo_id=<?= $photo->id ?>&action=rotate_left">⟲</button>
                <button type="button" data-post="image-edit.php?id=<?= $p->id ?>&photo_id=<?= $photo->id ?>&action=rotate_right">⟳</button>
                <button type="button" data-post="image-edit.php?id=<?= $p->id ?>&photo_id=<?= $photo->id ?>&action=flip_h">⇋</button>
                <button type="button" data-post="image-edit.php?id=<?= $p->id ?>&photo_id=<?= $photo->id ?>&action=flip_v">⇅</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<!-- === END NEW section === -->

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