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

if ($p && is_post() && req('btn') == 'delete_photo') {
    $photo_id = req('photo_id');
    $stm = $_db->prepare("SELECT photo FROM product_photo WHERE id = ? AND product_id = ?");
    $stm->execute([$photo_id, $p->id]);
    $photoName = $stm->fetchColumn();

    if ($photoName) {
        $stm = $_db->prepare("DELETE FROM product_photo WHERE id = ? AND product_id = ?");
        $stm->execute([$photo_id, $p->id]);

        $filePath = root('products') . '/' . $photoName;
        if (file_exists($filePath)) unlink($filePath);

        temp('info', 'Photo deleted.');
    }
    redirect('product-detail.php?id=' . $p->id);
}

if ($p && is_post() && req('btn') == 'add_photos') {
    $files = $_FILES['new_photos'] ?? null;
    $count = 0;

    if ($files && is_array($files['tmp_name'])) {
        $dir = root('products');
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $maxSort = $_db->prepare("SELECT MAX(sort_order) FROM product_photo WHERE product_id = ?");
        $maxSort->execute([$p->id]);
        $nextSort = ((int) $maxSort->fetchColumn()) + 1;

        foreach ($files['tmp_name'] as $i => $tmp) {
            if ($files['error'][$i] == UPLOAD_ERR_OK && getimagesize($tmp)) {
                $fakeFile = (object)[
                    'tmp_name' => $tmp,
                    'name'     => $files['name'][$i],
                    'error'    => $files['error'][$i],
                ];
                $photoName = save_photo($fakeFile, $dir);
                $stm = $_db->prepare("INSERT INTO product_photo (product_id, photo, sort_order) VALUES (?, ?, ?)");
                $stm->execute([$p->id, $photoName, $nextSort++]);
                $count++;
            }
        }
    }
    temp('info', "$count photo(s) added.");
    redirect('product-detail.php?id=' . $p->id);
}

if (is_post() && req('btn') != 'delete' && req('btn') != 'batch' && req('btn') != 'add_photos' && req('btn') != 'delete_photo') {
    $name        = req('name');
    $category_id = req('category_id');
    $price       = req('price');
    $stock       = req('stock');
    $description = req('description');
    $video_url   = req('video_url');
    $photo       = $p->photo ?? 'default.jpg';

    if ($name == '') {
        $_err['name'] = 'Product name is required';
    }
    if ($category_id == '') {
        $_err['category_id'] = 'Please select a category';
    }
    if ($price == '') {
        $_err['price'] = 'Price is required';
    } else if (!is_money($price) || $price < 0) {
        $_err['price'] = 'Invalid price format';
    }
    if ($stock == '') {
        $_err['stock'] = 'Stock is required';
    } else if (!ctype_digit((string)$stock)) {
        $_err['stock'] = 'Invalid stock quantity';
    }
    if ($video_url !== '' && (!filter_var($video_url, FILTER_VALIDATE_URL) || !str_contains($video_url, 'youtube.com'))) {
        $_err['video_url'] = "Invalid YouTube URL. Example: https://www.youtube.com/watch?v=example";
    }
    $f = get_file('photo');
    if ($f && !getimagesize($f->tmp_name)) {
        $_err['photo'] = 'Invalid image file uploaded';
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
                    $stm = $_db->prepare("UPDATE product_photo SET sort_order = ? WHERE id = ?");
                    $stm->execute([$sortOrder, $photoId]);
                }
            }

            temp('info', 'Product updated successfully.');
            redirect('product-detail.php?id=' . $p->id);
        } else {
            $max   = $_db->query("SELECT MAX(id) FROM product")->fetchColumn();
            $num   = $max ? ((int) substr($max, 1) + 1) : 1;
            $newId = 'P' . str_pad($num, 3, '0', STR_PAD_LEFT);
            $stm = $_db->prepare("INSERT INTO product (id, name, category_id, price, photo, stock, description, video_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stm->execute([$newId, $name, $category_id, $price, $photo, $stock, $description, $video_url]);
            temp('info', 'Product created successfully.');
            redirect('product-detail.php?id=' . $newId);
        }
    }
}

$name        = $name        ?? $p->name        ?? '';
$price       = $price       ?? $p->price       ?? '';
$stock       = $stock       ?? $p->stock       ?? '';
$category_id = $category_id ?? $p->category_id ?? '';
$description = $description ?? $p->description ?? '';
$video_url   = $video_url   ?? $p->video_url   ?? '';

$photos = [];
if ($p) {
    $photoStm = $_db->prepare("
        SELECT *
        FROM product_photo
        WHERE product_id = ?
        ORDER BY sort_order ASC, id ASC
    ");
    $photoStm->execute([$p->id]);
    $photos = $photoStm->fetchAll();
}

$_title = $p ? 'Edit Product | Pululu' : 'New Product | Pululu';
include '../_head.php';
?>

<link rel="stylesheet" href="<?= app_url('css/admin-product-detail.css') ?>">

<div class="pd-wrap">
    <!-- Breadcrumb -->
    <div class="pd-breadcrumb">
        <a href="product-listing.php">Manage Products</a>
        <span>&rsaquo;</span>
        <span><?= $p ? 'Edit Product' : 'New Product' ?></span>
    </div>

    <!-- Main Card -->
    <div class="pd-card">
        <!-- Header -->
        <div class="pd-header">
            <div class="pd-title-group">
                <span class="pd-title-icon">🥯</span>
                <div>
                    <span class="pd-title-text"><?= $p ? htmlspecialchars($p->name) : 'Create New Product' ?></span>
                    <?php if ($p): ?>
                        <span class="pd-badge-sku"><?= htmlspecialchars($p->id) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Main Product Form -->
        <form method="post" enctype="multipart/form-data" id="productForm">
            <div class="pd-main-grid">
                <!-- Left: Main Photo -->
                <div class="pd-image-column">
                    <div class="pd-main-photo-card">
                        <img id="mainProductImage" src="/products/<?= htmlspecialchars($p->photo ?? 'default.jpg') ?>" alt="<?= htmlspecialchars($p->name ?? 'Product') ?>">
                        <label for="productPhotoInput" class="pd-change-photo-btn">
                            📷 Change Photo
                        </label>
                    </div>
                    <input class="il-35-cb4589" type="file" name="photo" id="productPhotoInput" accept="image/*">
                    <?= err('photo') ?>
                </div>

                <!-- Right: Product Information Inputs -->
                <div class="pd-form-column">
                    <?php if ($p): ?>
                        <div class="form-field">
                            <label>ID</label>
                            <input type="text" class="pd-input" value="<?= htmlspecialchars($p->id) ?>" disabled>
                        </div>
                    <?php endif; ?>

                    <div class="form-two-col">
                        <div class="form-field">
                            <label for="name">Name</label>
                            <?= html_text('name', 'maxlength="100" id="name" class="pd-input" placeholder="e.g. Plain Bagel"') ?>
                            <?= err('name') ?>
                        </div>

                        <div class="form-field">
                            <label for="category_id">Category</label>
                            <select name="category_id" id="category_id" required>
                                <option value="">-- Select Category --</option>
                                <?php
                                $catStm = $_db->query("SELECT id, name FROM category ORDER BY name");
                                while ($cat = $catStm->fetch()) {
                                    $selected = (($category_id ?? '') == $cat->id) ? 'selected' : '';
                                    echo "<option value=\"{$cat->id}\" {$selected}>" . htmlspecialchars($cat->name) . "</option>";
                                }
                                ?>
                            </select>
                            <?= err('category_id') ?>
                        </div>
                    </div>

                    <div class="form-two-col">
                        <div class="form-field">
                            <label for="price">Price (RM)</label>
                            <div class="pd-input-group">
                                <span class="pd-input-prefix">RM</span>
                                <?= html_text('price', 'maxlength="10" id="price" class="pd-input" placeholder="0.00"') ?>
                            </div>
                            <?= err('price') ?>
                        </div>

                        <div class="form-field">
                            <label for="stock">Stock Quantity</label>
                            <?= html_text('stock', 'maxlength="10" id="stock" class="pd-input" placeholder="0"') ?>
                            <?= err('stock') ?>
                        </div>
                    </div>

                    <div class="form-field">
                        <label for="description">Description</label>
                        <textarea name="description" id="description" rows="3" placeholder="A soft and chewy plain bagel with a golden crust. Perfect on its own or with your favorite spread."><?= htmlspecialchars(trim($description)) ?></textarea>
                        <?= err('description') ?>
                    </div>

                    <div class="form-field">
                        <label for="video_url">Product Video URL <span class="optional">(Optional)</span></label>
                        <div class="pd-input-icon-wrap">
                            <span class="pd-input-icon">🔗</span>
                            <?= html_text('video_url', 'id="video_url" class="pd-input" placeholder="https://www.youtube.com/watch?v=example"') ?>
                        </div>
                        <?= err('video_url') ?>
                    </div>
                </div>
            </div>

            <!-- Detail Photos (Within Details) -->
            <?php if ($p): ?>
                <div class="pdp-box">
                    <div class="pdp-header">
                        <div class="pdp-header-icon">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
                                <circle cx="9" cy="9" r="2"/>
                                <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
                            </svg>
                        </div>
                        <div class="pdp-header-title">Product Detail Photos</div>
                    </div>

                    <div class="pdp-grid">
                        <?php foreach ($photos as $photo): ?>
                            <div class="pdp-card">
                                <div class="pdp-img-wrap">
                                    <img src="/products/<?= htmlspecialchars($photo->photo) ?>" alt="Product detail" onclick="enlargePhoto(this)">
                                </div>
                                <div class="pdp-card-footer">
                                    <span class="pdp-filename" title="<?= htmlspecialchars($photo->photo) ?>">
                                        <?= htmlspecialchars($photo->photo) ?>
                                    </span>
                                    <input type="hidden" name="sort_order[<?= $photo->id ?>]" value="<?= $photo->sort_order ?>">
                                    <button type="button" class="pdp-delete-btn" data-post="product-detail.php?id=<?= $p->id ?>&photo_id=<?= $photo->id ?>&btn=delete_photo" data-confirm title="Delete photo">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Add Photo Button -->
                        <label for="pdpDetailInput" class="pdp-add-box">
                            <div class="pdp-add-icon">＋</div>
                            <div class="pdp-add-title">Add Photo</div>
                            <div class="pdp-add-sub">Upload up to 10 photos<br>JPG, PNG (Max 5MB)</div>
                        </label>
                    </div>
                </div>
            <?php endif; ?>
        </form>

        <!-- Hidden Detail Photos Upload Form -->
        <?php if ($p): ?>
            <form class="il-35-cb4589" method="post" enctype="multipart/form-data" id="addPhotosForm">
                <input type="file" id="pdpDetailInput" name="new_photos[]" multiple accept="image/*" onchange="handleDetailFilesSelect(this)">
                <button type="submit" id="pdpSubmitUploadBtn" name="btn" value="add_photos"></button>
            </form>
        <?php endif; ?>

        <!-- Actions -->
        <div class="pd-actions">
            <button type="submit" form="productForm" name="btn" value="save" class="btn-save-main">
                Save Changes
            </button>
            <?php if ($p): ?>
                <button type="button" class="btn-delete-link" data-post="product-detail.php?id=<?= $p->id ?>&btn=delete" data-confirm>
                    Delete Product
                </button>
            <?php endif; ?>
            <button type="button" class="btn-back-outline" data-get="product-listing.php">
                Back to Listing
            </button>
        </div>
    </div>

    <!-- Batch Add Mode (Only on Create) -->
    <?php if (!$p): ?>
        <div class="pd-batch-card">
            <div class="pd-batch-title">📦 Batch Import Products</div>
            <form method="post">
                <div class="form-field">
                    <label for="batch_data">Batch Data <span class="optional">(Format: name, price, stock per line)</span></label>
                    <?= html_textarea('batch_data', 'rows="4" class="pd-input" placeholder="Plain Bagel, 3.50, 50&#10;Sesame Bagel, 3.80, 40"') ?>
                </div>
                <div class="il-36-0dc91c">
                    <button type="submit" class="btn-back-outline" name="btn" value="batch">
                        Import Products
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Lightbox Backdrop -->
<div id="photoBackdrop" class="pd-lightbox-backdrop" onclick="closePhoto()"></div>

<script>
function enlargePhoto(img) {
    const backdrop = document.getElementById('photoBackdrop');
    img.classList.add('enlarged');
    backdrop.classList.add('active');
}

function closePhoto() {
    const img = document.querySelector('.pdp-img-wrap img.enlarged');
    if (img) {
        img.classList.remove('enlarged');
    }
    document.getElementById('photoBackdrop').classList.remove('active');
}

function handleDetailFilesSelect(input) {
    const submitBtn = document.getElementById('pdpSubmitUploadBtn');
    if (input.files && input.files.length > 0) {
        submitBtn.click();
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const photoInput = document.getElementById('productPhotoInput');
    const mainImage = document.getElementById('mainProductImage');

    if (photoInput && mainImage) {
        photoInput.addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    mainImage.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closePhoto();
        }
    });
});
</script>

<?php
include '../_foot.php';
?>