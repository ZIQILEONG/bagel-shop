<?php
include '../_base.php';
auth('Admin'); // Ensure only Admin can access this page

$id = req('id');
$p = null;
$photos = [];

// 1. Fetch Product Data using PDO
if ($id) {
    $stm = $_db->prepare("SELECT * FROM product WHERE id = ?");
    $stm->execute([$id]);
    $p = $stm->fetch();

    // 2. Fetch Additional Photos using PDO
    if ($p) {
        $stm = $_db->prepare("SELECT * FROM product_photo WHERE product_id = ? ORDER BY sort_order, id");
        $stm->execute([$id]);
        $photos = $stm->fetchAll();
    } else {
        redirect('product-listing.php');
    }
}

// 3. Handle Delete Product
if ($p && is_post() && req('btn') == 'delete') {
    // Check for orders before deleting (Optional safety check)
    $stm = $_db->prepare("SELECT COUNT(*) FROM order_item WHERE product_id = ?");
    $stm->execute([$p->id]);
    $count = $stm->fetchColumn();

    if ($count > 0) {
        temp('error', 'Cannot delete: Product has existing orders.');
    } else {
        $stm = $_db->prepare("DELETE FROM product WHERE id = ?");
        $stm->execute([$p->id]);
        temp('info', 'Product deleted.');
        redirect('product-listing.php');
    }
}

// 4. Handle Save (Create/Update Main Product)
if (is_post() && req('btn') != 'delete' && req('btn') != 'batch' && req('btn') != 'upload_photos' && req('btn') != 'delete_photo') {
    $name  = req('name');
    $price = req('price');
    $stock = req('stock');
    $category_id = req('category_id');
    $video_url = req('video_url');
    
    // Keep existing photo if not uploading new one
    $photo = $p->photo ?? 'default.jpg';

    // Validation
    if ($name == '') $_err['name'] = 'Required';
    if ($price == '') $_err['price'] = 'Required';
    elseif (!is_money($price) || $price < 0) $_err['price'] = 'Invalid value';
    if ($stock == '') $_err['stock'] = 'Required';
    elseif (!ctype_digit($stock)) $_err['stock'] = 'Invalid value';

    // Handle Main Photo Upload
    $f = get_file('photo');
    if ($f && $f['error'] == 0) {
        if (!getimagesize($f['tmp_name'])) {
            $_err['photo'] = 'Invalid image file';
        }
    }

    if (!$_err) {
        if ($f && $f['error'] == 0) {
            $dir = root('products');
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $photo = save_photo($f, $dir);
        }

        if ($p) {
            // UPDATE
            $stm = $_db->prepare("UPDATE product SET name = ?, price = ?, stock = ?, photo = ?, category_id = ?, video_url = ? WHERE id = ?");
            $stm->execute([$name, $price, $stock, $photo, $category_id, $video_url, $p->id]);
            temp('info', 'Product updated.');
        } else {
            // CREATE
            $max   = $_db->query("SELECT MAX(id) FROM product")->fetchColumn();
            $num   = $max ? ((int) substr($max, 1) + 1) : 1;
            $newId = 'P' . str_pad($num, 3, '0', STR_PAD_LEFT);
            
            $stm = $_db->prepare("INSERT INTO product (id, name, price, photo, stock, category_id, video_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stm->execute([$newId, $name, $price, $photo, $stock, $category_id, $video_url]);
            temp('info', 'Product created.');
            redirect('product-detail.php?id=' . $newId);
        }
        redirect('product-detail.php?id=' . ($p ? $p->id : $newId));
    }
}

// 5. Handle Multiple Photo Upload
if (is_post() && req('btn') == 'upload_photos' && $p) {
    $files = $_FILES['additional_photos'] ?? [];
    $count = 0;

    if ($files && is_array($files['tmp_name'])) {
        $dir = root('photos/products');
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        foreach ($files['tmp_name'] as $i => $tmp_name) {
            if ($files['error'][$i] == 0) {
                $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
                $photo_name = uniqid() . '.' . strtolower($ext);
                
                // Move file
                if (move_uploaded_file($tmp_name, "$dir/$photo_name")) {
                    // Insert into DB using PDO
                    $stm = $_db->prepare("INSERT INTO product_photo (product_id, photo, sort_order) VALUES (?, ?, ?)");
                    $stm->execute([$p->id, $photo_name, $count + 1]);
                    $count++;
                }
            }
        }
        temp('info', "$count photo(s) uploaded.");
        redirect('product-detail.php?id=' . $p->id);
    }
}

// 6. Handle Delete Single Photo
if (is_post() && req('btn') == 'delete_photo' && $p) {
    $photo_id = req('photo_id');
    $photo_name = req('photo_name');

    $stm = $_db->prepare("DELETE FROM product_photo WHERE id = ? AND product_id = ?");
    $stm->execute([$photo_id, $p->id]);

    $file_path = root("photos/products/$photo_name");
    if (file_exists($file_path)) unlink($file_path);

    temp('info', 'Photo deleted.');
    redirect('product-detail.php?id=' . $p->id);
}

// Pre-fill form values
$name        = $name        ?? $p->name        ?? '';
$price       = $price       ?? $p->price       ?? '';
$stock       = $stock       ?? $p->stock       ?? '';
$category_id = $category_id ?? $p->category_id ?? '';
$video_url   = $video_url   ?? $p->video_url   ?? '';

// Get categories
$categories = [];
$stm = $_db->query("SELECT * FROM category ORDER BY name");
$categories = $stm->fetchAll();

$_title = $p ? 'Edit Product (Admin)' : 'Create Product (Admin)';
include '../_head.php';
?>

<style>
    .form { max-width: 800px; margin: 0 auto; }
    .photo-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 15px;
        margin: 20px 0;
    }
    .photo-item {
        position: relative;
        border: 1px solid var(--border);
        border-radius: 8px;
        overflow: hidden;
        background: #fff;
    }
    .photo-item img {
        width: 100%;
        height: 150px;
        object-fit: cover;
        display: block;
    }
    .delete-btn {
        position: absolute;
        top: 5px;
        right: 5px;
        background: rgba(220, 38, 38, 0.9);
        color: white;
        border: none;
        border-radius: 50%;
        width: 30px;
        height: 30px;
        cursor: pointer;
        font-size: 18px;
        line-height: 1;
    }
    .drop-zone {
        border: 3px dashed var(--border);
        border-radius: 8px;
        padding: 30px;
        text-align: center;
        background: var(--cream);
        cursor: pointer;
        transition: all 0.3s;
    }
    .drop-zone.dragover {
        border-color: var(--red);
        background: var(--pink);
    }
    .video-preview iframe {
        width: 100%;
        max-width: 560px;
        aspect-ratio: 16/9;
        border-radius: 8px;
        margin-top: 10px;
    }
</style>

<form method="post" enctype="multipart/form-data" class="form">
    <?php if ($p): ?>
    <label>ID</label>
    <b><?= encode($p->id) ?></b>
    <br><br>
    <?php endif; ?>

    <label for="name">Name</label>
    <?= html_text('name', 'value="' . encode($name) . '" maxlength="100"') ?>
    <?= err('name') ?>

    <label for="price">Price (RM)</label>
    <?= html_text('price', 'value="' . encode($price) . '" maxlength="10"') ?>
    <?= err('price') ?>

    <label for="stock">Stock</label>
    <?= html_text('stock', 'value="' . encode($stock) . '" maxlength="10"') ?>
    <?= err('stock') ?>

    <label for="category_id">Category</label>
    <?php 
    $cat_opts = ['' => '- Select Category -'];
    foreach($categories as $c) $cat_opts[$c->id] = $c->name;
    ?>
    <?= html_select('category_id', $cat_opts, $category_id) ?>

    <label for="video_url">YouTube URL</label>
    <?= html_text('video_url', 'value="' . encode($video_url) . '" placeholder="https://www.youtube.com/watch?v=..."') ?>
    <?php if ($video_url): 
        preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $video_url, $matches);
        if (isset($matches[1])): ?>
        <div class="video-preview">
            <iframe src="https://www.youtube.com/embed/<?= $matches[1] ?>" frameborder="0" allowfullscreen></iframe>
        </div>
    <?php endif; endif; ?>

    <label>Main Photo</label>
    <?php if ($p && $p->photo): ?>
        <img src="/products/<?= $p->photo ?>" style="max-width: 200px; border: 1px solid #ddd; margin-bottom: 10px;">
    <?php endif; ?>
    <?= html_file('photo', 'accept="image/*"') ?>
    <?= err('photo') ?>

    <section style="margin-top: 20px;">
        <button type="submit">Save Product</button>
        <a href="product-listing.php" style="margin-left: 10px;">Cancel</a>
    </section>
</form>

<?php if ($p): ?>
<hr style="margin: 40px 0;">
<h2>Additional Photos (Gallery)</h2>

<!-- Upload Form -->
<form method="post" enctype="multipart/form-data" id="photo-upload-form">
    <div class="drop-zone" id="dropZone">
        <p>📁 Drag & Drop photos here or click to select</p>
        <input type="file" name="additional_photos[]" id="additionalPhotos" multiple accept="image/*" style="display: none;">
    </div>
    <div id="preview-container" class="photo-grid"></div>
    <section style="margin-top: 15px;">
        <button type="submit" name="btn" value="upload_photos">Upload Selected Photos</button>
    </section>
</form>

<!-- Existing Photos List -->
<?php if ($photos): ?>
<h3 style="margin-top: 30px;">Uploaded Gallery</h3>
<div class="photo-grid">
    <?php foreach ($photos as $photo): ?>
    <div class="photo-item">
        <img src="/photos/products/<?= $photo->photo ?>" alt="Gallery">
        <form method="post" style="position: absolute; top: 5px; right: 5px;">
            <?= html_hidden('photo_id', "value='$photo->id'") ?>
            <?= html_hidden('photo_name', "value='$photo->photo'") ?>
            <button type="submit" name="btn" value="delete_photo" class="delete-btn" onclick="return confirm('Delete this photo?')">×</button>
        </form>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php if ($p): ?>
<p style="margin-top: 40px; border-top: 2px solid #eee; padding-top: 20px;">
    <button data-post="product-detail.php?id=<?= $p->id ?>&btn=delete" data-confirm="Permanently delete this product?" style="background: var(--red);">Delete Product</button>
</p>
<?php endif; ?>

<script>
$(document).ready(function() {
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('additionalPhotos');
    const previewContainer = document.getElementById('preview-container');

    if(dropZone) {
        dropZone.addEventListener('click', () => fileInput.click());

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => { e.preventDefault(); e.stopPropagation(); }, false);
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false);
        });

        dropZone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            handleFiles(dt.files);
        }, false);

        fileInput.addEventListener('change', function() {
            handleFiles(this.files);
        });
    }

    function handleFiles(files) {
        ([...files]).forEach(file => {
            if (file.type.startsWith('image/')) previewFile(file);
        });
    }

    function previewFile(file) {
        const reader = new FileReader();
        reader.readAsDataURL(file);
        reader.onloadend = function() {
            const div = document.createElement('div');
            div.className = 'photo-item';
            div.innerHTML = `<img src="${reader.result}" alt="Preview">`;
            previewContainer.appendChild(div);
        }
    }
});
</script>

<?php include '../_foot.php'; ?>