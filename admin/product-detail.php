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
$photoStm->execute([$p->id ?? '']);
$photos = $photoStm->fetchAll();

// ----------------------------------------------------------------------------
$_title = $p ? 'Product | Detail (Admin)' : 'Product | Create (Admin)';
include '../_head.php';
?>
<style>
.pd-wrap {
    max-width: 960px;
    margin: 0 auto;
}
.pd-card {
    background: #fff;
    border: 1px solid #ead5ca;
    border-radius: 14px;
    padding: 28px 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.04);
}
.pd-breadcrumb {
    font-size: 13px;
    color: #a97c5d;
    margin-bottom: 14px;
}
.pd-breadcrumb a { color: #a97c5d; text-decoration: none; }
.pd-breadcrumb a:hover { text-decoration: underline; }
.pd-title {
    font-size: 22px;
    font-weight: bold;
    color: #5c3820;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.pd-title .pd-badge {
    font-size: 13px;
    background: #f9e5db;
    color: #914e2b;
    padding: 4px 10px;
    border-radius: 20px;
    font-weight: normal;
}

.form-field {
    margin-bottom: 18px;
}
.form-field label {
    display: block;
    font-weight: bold;
    color: #914e2b;
    margin-bottom: 6px;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.form-field input[type="text"],
.form-field input[type="number"],
.form-field select,
.form-field textarea {
    width: 100%;
    padding: 11px 13px;
    border: 1px solid #ead5ca;
    border-radius: 9px;
    background: #fffaf7;
    box-sizing: border-box;
    font-size: 14px;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.form-field input:focus,
.form-field select:focus,
.form-field textarea:focus {
    outline: none;
    border-color: #d9825a;
    box-shadow: 0 0 0 3px rgba(217,130,90,0.15);
}
.form-field input:disabled {
    background: #f2efec;
    color: #999;
}
.form-two-col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px 24px;
}
@media (max-width: 640px) {
    .form-two-col { grid-template-columns: 1fr; }
}
.err {
    color: #c0392b;
    font-size: 12px;
    display: block;
    margin-top: 4px;
}
.pd-hint {
    font-size: 12px;
    color: #a89484;
    margin-top: 4px;
}

.photo-upload-preview {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}
.photo-upload-preview img {
    width: 96px;
    height: 96px;
    object-fit: cover;
    border-radius: 10px;
    border: 1px solid #ead5ca;
}

.pd-section-title {
    font-size: 15px;
    font-weight: bold;
    color: #5c3820;
    margin: 30px 0 12px;
    padding-top: 20px;
    border-top: 1px solid #f0e4da;
}

.photo-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 14px;
}
.photo-card {
    background: #fffaf7;
    border: 1px solid #ead5ca;
    border-radius: 10px;
    overflow: hidden;
    text-align: center;
    transition: box-shadow 0.15s, transform 0.15s;
}
.photo-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    transform: translateY(-2px);
}
.photo-box {
    width: 100%;
    height: 110px;
    overflow: hidden;
    background: #eee;
}
.photo-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    cursor: zoom-in;
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
    cursor: zoom-out;
}
.photo-card .photo-name {
    padding: 9px 8px;
    font-size: 11px;
    color: #85705f;
    word-break: break-all;
}

.pd-actions {
    max-width: 960px;
    margin: 20px auto 0;
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}
.pd-actions button, .pd-actions a button {
    padding: 11px 22px;
    border-radius: 9px;
    border: none;
    font-weight: bold;
    cursor: pointer;
    font-size: 14px;
}
.btn-save { background: #d9825a; color: #fff; }
.btn-save:hover { background: #c66f47; }
.btn-danger { background: #fff; color: #c0392b; border: 1px solid #f0c4bc !important; }
.btn-danger:hover { background: #fdf1ef; }
.btn-secondary { background: #f2efec; color: #5c3820; }
.btn-secondary:hover { background: #e8e2db; }

.pd-batch {
    max-width: 960px;
    margin: 24px auto 0;
    background: #fbf3e8;
    border: 1px solid #f0e2d0;
    border-radius: 14px;
    padding: 22px 26px;
}

.photo-backdrop {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.6);
    z-index: 9998;
}
.photo-backdrop.active { display: block; }

.photo-box img.enlarged {
    position: fixed;
    top: 50%;
    left: 50%;
    max-width: 85vw;
    max-height: 85vh;
    width: auto;
    height: auto;
    object-fit: contain;
    transform: translate(-50%, -50%);
    z-index: 9999;
    background: white;
    padding: 10px;
    border-radius: 10px;
    box-shadow: 0 0 40px rgba(0,0,0,0.5);
    cursor: zoom-out;
}
</style>

<script>
function enlargePhoto(img) {
    img.classList.toggle('enlarged');
}
</script>

<div class="pd-wrap">
    <div class="pd-breadcrumb">
        <a href="product-listing.php">Manage Products</a> / <?= $p ? 'Edit Product' : 'New Product' ?>
    </div>

    <div class="pd-card">
        <div class="pd-title">
            🥯 <?= $p ? htmlspecialchars($p->name) : 'Create New Product' ?>
            <?php if ($p): ?><span class="pd-badge"><?= htmlspecialchars($p->id) ?></span><?php endif; ?>
        </div>

        <form method="post" enctype="multipart/form-data" id="productForm">

            <?php if ($p): ?>
            <div class="form-field">
                <label>Id</label>
                <input type="text" value="<?= $p->id ?>" disabled>
            </div>
            <?php endif ?>

            <div class="form-two-col">
                <div class="form-field">
                    <label for="name">Name</label>
                    <?= html_text('name', 'maxlength="100"') ?>
                    <?= err('name') ?>
                </div>

                <div class="form-field">
                    <label>Category</label>
                    <select name="category_id" required>
                        <option value="">-- Select Category --</option>
                        <?php
                        $catStm = $_db->query("SELECT id, name FROM category ORDER BY name");
                        while ($cat = $catStm->fetch()) {
                            $selected = (($p->category_id ?? '') == $cat->id) ? 'selected' : '';
                            echo "<option value=\"{$cat->id}\" $selected>{$cat->name}</option>";
                        }
                        ?>
                    </select>
                    <?= err('category_id') ?>
                </div>

                <div class="form-field">
                    <label for="price">Price (RM)</label>
                    <?= html_text('price', 'maxlength="10"') ?>
                    <?= err('price') ?>
                </div>

                <div class="form-field">
                    <label for="stock">Stock</label>
                    <?= html_text('stock', 'maxlength="10"') ?>
                    <?= err('stock') ?>
                </div>
            </div>

            <div class="form-field">
                <label for="description">Description</label>
                <textarea name="description" id="description" rows="4"><?= htmlspecialchars(trim($p->description ?? '')) ?></textarea>
            </div>

            <div class="form-field">
                <label for="video_url">Product Video Url</label>
                <?= html_text('video_url', 'placeholder="https://www.youtube.com/watch?v=example"') ?>
                <?= err('video_url') ?>
                <div class="pd-hint">Optional — paste a YouTube link to show a product video.</div>
            </div>

            <div class="form-field">
                <label>Product Photo</label>
                <div class="photo-upload-preview">
                    <img src="/products/<?= $p->photo ?? 'default.jpg' ?>">
                    <?= html_file('photo', 'image/*') ?>
                </div>
                <?= err('photo') ?>
            </div>

            <?php if ($p): ?>
            <div class="pd-section-title">Product Detail Photos</div>
            <div class="photo-cards">
                <?php foreach ($photos as $photo): ?>
                <div class="photo-card">
                    <div class="photo-box">
                        <img src="/products/<?= htmlspecialchars($photo->photo) ?>"
                             alt="Product Detail Photo"
                             onclick="enlargePhoto(this)">
                    </div>
                    <div class="photo-name"><?= htmlspecialchars($photo->photo) ?></div>
                    <input type="hidden" name="sort_order[<?= $photo->id ?>]" value="<?= $photo->sort_order ?>">
                </div>
                <?php endforeach; ?>
                <?php if (!$photos): ?>
                <div class="pd-hint">No detail photos uploaded yet.</div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </form>
    </div>

    <?php if (!$p): ?>
    <div class="pd-batch">
        <form method="post">
            <div class="form-field" style="margin-bottom:12px;">
                <label for="batch_data">Batch Add (one per line: name,price,stock)</label>
                <?= html_textarea('batch_data', 'rows="6" placeholder="Plain Bagel,3.50,50&#10;Sesame Bagel,3.80,40" style="width:100%;"') ?>
            </div>
            <button class="btn-secondary" name="btn" value="batch">Import</button>
        </form>
    </div>
    <?php endif ?>

    <div class="pd-actions">
        <button type="submit" form="productForm" class="btn-save">Save</button>
        <?php if ($p): ?>
        <button class="btn-danger" data-post="product-detail.php?id=<?= $p->id ?>&btn=delete" data-confirm>Delete Product</button>
        <?php endif ?>
        <button class="btn-secondary" data-get="product-listing.php">Back to Listing</button>
    </div>
</div>

<?php
include '../_foot.php';