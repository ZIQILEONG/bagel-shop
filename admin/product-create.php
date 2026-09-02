<?php
include '../_base.php';
auth('Admin');

if (is_post() && req('btn') == 'create') {
    $name        = req('name');
    $category_id = req('category_id');
    $price       = req('price');
    $stock       = req('stock');
    $description = req('description');
    $video_url   = req('video_url');
    $photo       = 'default.jpg';

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
        $_err['photo'] = 'Invalid main image file uploaded';
    }

    if (!$_err) {
        $dir = root('products');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // 1. Save Main Photo
        if ($f) {
            $photo = save_photo($f, $dir);
        }

        // 2. Generate Product ID
        $max   = $_db->query("SELECT MAX(id) FROM product")->fetchColumn();
        $num   = $max ? ((int) substr($max, 1) + 1) : 1;
        $newId = 'P' . str_pad($num, 3, '0', STR_PAD_LEFT);

        // 3. Insert Product
        $stm = $_db->prepare("INSERT INTO product (id, name, category_id, price, photo, stock, description, video_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stm->execute([$newId, $name, $category_id, $price, $photo, $stock, $description, $video_url]);

        // 4. Batch Upload Multiple Detail Photos (1 Product = Multiple Photos)
        $detailFiles = $_FILES['detail_photos'] ?? null;
        if ($detailFiles && is_array($detailFiles['tmp_name'])) {
            $sort = 1;
            foreach ($detailFiles['tmp_name'] as $i => $tmp) {
                if ($detailFiles['error'][$i] === UPLOAD_ERR_OK && getimagesize($tmp)) {
                    $fakeFile = (object)[
                        'tmp_name' => $tmp,
                        'name'     => $detailFiles['name'][$i],
                        'error'    => $detailFiles['error'][$i],
                    ];
                    $savedDetailPhoto = save_photo($fakeFile, $dir);
                    
                    $pStm = $_db->prepare("INSERT INTO product_photo (product_id, photo, sort_order) VALUES (?, ?, ?)");
                    $pStm->execute([$newId, $savedDetailPhoto, $sort++]);
                }
            }
        }

        temp('info', 'Product and detail photos created successfully.');
        redirect('product-detail.php?id=' . $newId);
    }
}

$name        = req('name');
$price       = req('price');
$stock       = req('stock') ?? '0';
$category_id = req('category_id');
$description = req('description');
$video_url   = req('video_url');

$_title = 'Add New Product | Pululu';
include '../_head.php';
?>

<link rel="stylesheet" href="<?= app_url('css/admin-product-create.css') ?>">

<div class="pd-wrap">
    <!-- Breadcrumb -->
    <div class="pd-breadcrumb">
        <a href="product-listing.php">Manage Products</a>
        <span>&rsaquo;</span>
        <span>New Product</span>
    </div>

    <!-- Main Card -->
    <div class="pd-card">
        <!-- Header -->
        <div class="pd-header">
            <div class="pd-title-group">
                <span class="pd-title-icon">🥯</span>
                <div>
                    <span class="pd-title-text">Create New Product</span>
                </div>
            </div>
        </div>

        <!-- Create Form -->
        <form method="post" enctype="multipart/form-data" id="createProductForm">
            <div class="pd-main-grid">
                <!-- Left: Main Product Photo -->
                <div class="pd-image-column">
                    <div class="pd-main-photo-card" id="mainPhotoCard">
                        <img id="mainProductImage" src="/products/default.jpg" alt="Product Thumbnail">
                        <label for="productPhotoInput" class="pd-change-photo-btn">
                            📷 Upload Photo
                        </label>
                    </div>
                    <input class="il-35-cb4589" type="file" name="photo" id="productPhotoInput" accept="image/*">
                    <?= err('photo') ?>
                </div>

                <!-- Right: Information Fields -->
                <div class="pd-form-column">
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
                        <textarea name="description" id="description" rows="3" placeholder="A soft and chewy plain bagel with a golden crust. Perfect on its own or with your favorite spread."><?= htmlspecialchars(trim($description ?? '')) ?></textarea>
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

            <!-- Detail Photos Tile (Multiple Photo Upload) -->
            <div class="pdp-box">
                <div class="pdp-header">
                    <div class="pdp-header-icon">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
                            <circle cx="9" cy="9" r="2"/>
                            <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
                        </svg>
                    </div>
                    <div class="pdp-header-title">Product Detail Photos (Multiple)</div>
                </div>

                <div class="pdp-grid" id="detailPhotosGrid">
                    <!-- Dynamic preview cards will be injected here -->
                    <label for="pdpDetailInput" class="pdp-add-box" id="addBoxLabel">
                        <div class="pdp-add-icon">＋</div>
                        <div class="pdp-add-title">Add Photos</div>
                        <div class="pdp-add-sub">Upload up to 10 photos<br>JPG, PNG (Max 5MB)</div>
                    </label>
                </div>
                <input class="il-35-cb4589" type="file" id="pdpDetailInput" name="detail_photos[]" multiple accept="image/*">
            </div>

            <!-- Action Buttons -->
            <div class="pd-actions">
                <button type="submit" name="btn" value="create" class="btn-save-main">
                    Save Product
                </button>
                <a href="product-listing.php" class="btn-back-outline">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // -------------------------------------------------------------
    // 1. MAIN PHOTO: FILE SELECT & DRAG-AND-DROP
    // -------------------------------------------------------------
    const photoInput    = document.getElementById('productPhotoInput');
    const mainImage     = document.getElementById('mainProductImage');
    const mainPhotoCard = document.getElementById('mainPhotoCard');

    function updateMainPreview(file) {
        if (file && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => mainImage.src = e.target.result;
            reader.readAsDataURL(file);
        }
    }

    if (photoInput) {
        photoInput.addEventListener('change', function () {
            if (this.files.length > 0) {
                updateMainPreview(this.files[0]);
            }
        });
    }

    if (mainPhotoCard && photoInput) {
        ['dragenter', 'dragover'].forEach(eName => {
            mainPhotoCard.addEventListener(eName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                mainPhotoCard.style.borderColor = 'var(--pd-primary)';
                mainPhotoCard.style.boxShadow = '0 0 0 3px rgba(217, 130, 90, 0.2)';
            });
        });

        ['dragleave', 'drop'].forEach(eName => {
            mainPhotoCard.addEventListener(eName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                mainPhotoCard.style.borderColor = 'var(--pd-border)';
                mainPhotoCard.style.boxShadow = 'none';
            });
        });

        mainPhotoCard.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            if (files.length > 0 && files[0].type.startsWith('image/')) {
                photoInput.files = files;
                updateMainPreview(files[0]);
            }
        });
    }

    // -------------------------------------------------------------
    // 2. MULTI-PHOTO DETAIL GALLERY: FILE SELECT, DRAG-AND-DROP & REMOVAL
    // -------------------------------------------------------------
    const detailInput = document.getElementById('pdpDetailInput');
    const detailGrid  = document.getElementById('detailPhotosGrid');
    const addBoxLabel = document.getElementById('addBoxLabel');
    const dt          = new DataTransfer();

    function renderDetailPreviews() {
        // Remove existing preview cards
        detailGrid.querySelectorAll('.pdp-temp-card').forEach(el => el.remove());

        Array.from(dt.files).forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = function (e) {
                const card = document.createElement('div');
                card.className = 'pdp-card pdp-temp-card';
                card.innerHTML = `
                    <div class="pdp-img-wrap">
                        <img src="${e.target.result}" alt="Detail Preview">
                    </div>
                    <div class="pdp-card-footer">
                        <span class="pdp-filename" title="${file.name}">${file.name}</span>
                        <button type="button" class="pdp-delete-btn" onclick="removeDetailFile(${index})" title="Remove photo">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
                            </svg>
                        </button>
                    </div>
                `;
                detailGrid.insertBefore(card, addBoxLabel);
            };
            reader.readAsDataURL(file);
        });
    }

    function addDetailFiles(filesList) {
        Array.from(filesList).forEach(file => {
            if (file.type.startsWith('image/')) {
                dt.items.add(file);
            }
        });
        detailInput.files = dt.files;
        renderDetailPreviews();
    }

    if (detailInput) {
        detailInput.addEventListener('change', function () {
            addDetailFiles(this.files);
        });
    }

    if (addBoxLabel) {
        ['dragenter', 'dragover'].forEach(eName => {
            addBoxLabel.addEventListener(eName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                addBoxLabel.style.borderColor = 'var(--pd-primary)';
                addBoxLabel.style.background = '#fff8f5';
            });
        });

        ['dragleave', 'drop'].forEach(eName => {
            addBoxLabel.addEventListener(eName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                addBoxLabel.style.borderColor = '#d8c2b5';
                addBoxLabel.style.background = '#fffdfc';
            });
        });

        addBoxLabel.addEventListener('drop', (e) => {
            if (e.dataTransfer.files.length > 0) {
                addDetailFiles(e.dataTransfer.files);
            }
        });
    }

    window.removeDetailFile = function(index) {
        const newDt = new DataTransfer();
        Array.from(dt.files).forEach((file, i) => {
            if (i !== index) newDt.items.add(file);
        });
        
        dt.items.clear();
        Array.from(newDt.files).forEach(file => dt.items.add(file));
        detailInput.files = dt.files;
        
        renderDetailPreviews();
    };
});
</script>

<?php
include '../_foot.php';
?>