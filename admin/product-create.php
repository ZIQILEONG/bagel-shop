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

        // Save Main Photo
        if ($f) {
            $photo = save_photo($f, $dir);
        }

        // Generate Product ID
        $max   = $_db->query("SELECT MAX(id) FROM product")->fetchColumn();
        $num   = $max ? ((int) substr($max, 1) + 1) : 1;
        $newId = 'P' . str_pad($num, 3, '0', STR_PAD_LEFT);

        // Insert Product
        $stm = $_db->prepare("INSERT INTO product (id, name, category_id, price, photo, stock, description, video_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stm->execute([$newId, $name, $category_id, $price, $photo, $stock, $description, $video_url]);

        // Batch Upload Multiple Detail Photos (1 Product = Multiple Photos)
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

<style>
:root {
    --pd-primary: #d9825a;
    --pd-primary-hover: #c66f47;
    --pd-brown-dark: #3e2619;
    --pd-text: #4a3b32;
    --pd-muted: #968377;
    --pd-border: #ebdcd5;
    --pd-border-focus: #d9825a;
    --pd-bg-input: #ffffff;
    --pd-red: #d65c4f;
    --pd-red-hover: #b33a2d;
}

body {
    background-color: #faf5f0;
    color: var(--pd-text);
}

.pd-wrap {
    max-width: 1040px;
    margin: 24px auto 60px;
    padding: 0 20px;
    box-sizing: border-box;
}

.pd-breadcrumb {
    font-size: 13px;
    color: var(--pd-muted);
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.pd-breadcrumb a {
    color: var(--pd-muted);
    text-decoration: none;
    transition: color 0.15s ease;
}
.pd-breadcrumb a:hover {
    color: var(--pd-primary);
}

.pd-card {
    background: #ffffff;
    border: 1px solid #f0e3dc;
    border-radius: 20px;
    padding: 32px 36px;
    box-shadow: 0 4px 20px rgba(62, 38, 25, 0.04);
}

.pd-header {
    display: flex;
    align-items: center;
    margin-bottom: 28px;
}
.pd-title-group {
    display: flex;
    align-items: center;
    gap: 12px;
}
.pd-title-icon {
    font-size: 26px;
    line-height: 1;
}
.pd-title-text {
    font-size: 22px;
    font-weight: 700;
    color: var(--pd-brown-dark);
}

.pd-main-grid {
    display: grid;
    grid-template-columns: 260px 1fr;
    gap: 32px;
    margin-bottom: 24px;
}

.pd-image-column {
    display: flex;
    flex-direction: column;
}
.pd-main-photo-card {
    position: relative;
    border-radius: 16px;
    overflow: hidden;
    border: 1.5px solid var(--pd-border);
    background: #fdfbf9;
    aspect-ratio: 1 / 1;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    transition: all 0.2s ease;
}
.pd-main-photo-card img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.pd-change-photo-btn {
    position: absolute;
    bottom: 14px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(4px);
    border: 1px solid var(--pd-border);
    padding: 7px 16px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    color: var(--pd-brown-dark);
    cursor: pointer;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
    transition: all 0.2s ease;
    white-space: nowrap;
}
.pd-change-photo-btn:hover {
    background: #ffffff;
    color: var(--pd-primary);
}

.pd-form-column {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.form-field {
    display: flex;
    flex-direction: column;
}
.form-field label {
    font-size: 12px;
    font-weight: 700;
    color: var(--pd-brown-dark);
    margin-bottom: 6px;
}
.form-field label .optional {
    font-size: 11px;
    font-weight: 400;
    color: var(--pd-muted);
}
.form-two-col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.pd-input,
.form-field select,
.form-field textarea {
    width: 100%;
    box-sizing: border-box;
    padding: 10px 14px;
    font-size: 13.5px;
    color: var(--pd-text);
    background: var(--pd-bg-input);
    border: 1.5px solid var(--pd-border);
    border-radius: 10px;
    outline: none;
    transition: all 0.2s ease;
    font-family: inherit;
}
.pd-input:focus,
.form-field select:focus,
.form-field textarea:focus {
    border-color: var(--pd-border-focus);
    box-shadow: 0 0 0 3px rgba(217, 130, 90, 0.12);
}
.form-field textarea {
    min-height: 84px;
    resize: vertical;
    line-height: 1.5;
}

.pd-input-group {
    display: flex;
    align-items: stretch;
}
.pd-input-prefix {
    display: flex;
    align-items: center;
    padding: 0 14px;
    background: #fbf6f3;
    border: 1.5px solid var(--pd-border);
    border-right: none;
    border-radius: 10px 0 0 10px;
    font-size: 13px;
    font-weight: 600;
    color: var(--pd-muted);
}
.pd-input-group .pd-input {
    border-radius: 0 10px 10px 0;
}
.pd-input-icon-wrap {
    position: relative;
}
.pd-input-icon {
    position: absolute;
    left: 13px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 13px;
    color: var(--pd-muted);
    pointer-events: none;
}
.pd-input-icon-wrap .pd-input {
    padding-left: 36px;
}

.err {
    font-size: 11px;
    color: var(--pd-red);
    margin-top: 4px;
    font-weight: 600;
}

.pdp-box {
    border: 1.5px solid #f3e8e2;
    border-radius: 16px;
    padding: 24px;
    background: #fff;
    margin-top: 24px;
}
.pdp-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 18px;
}
.pdp-header-icon {
    width: 28px;
    height: 28px;
    background: #fdf3ed;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--pd-primary);
}
.pdp-header-title {
    font-size: 15px;
    font-weight: 700;
    color: var(--pd-brown-dark);
}

/* Grid for dynamic previews + add box */
.pdp-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(155px, 1fr));
    gap: 16px;
}

.pdp-card {
    border: 1px solid #ebdcd5;
    border-radius: 14px;
    background: #fff;
    overflow: hidden;
    position: relative;
    box-shadow: 0 2px 6px rgba(67, 40, 24, 0.03);
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.pdp-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(67, 40, 24, 0.06);
}
.pdp-img-wrap {
    position: relative;
    width: 100%;
    aspect-ratio: 1 / 1;
    overflow: hidden;
    background: #fdfbf9;
}
.pdp-img-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.pdp-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 12px;
    background: #fff;
    border-top: 1px solid #f8eee8;
}
.pdp-filename {
    font-size: 11px;
    color: #8a7366;
    max-width: 100px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-weight: 500;
}
.pdp-delete-btn {
    background: none;
    border: none;
    padding: 0;
    color: var(--pd-red);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.15s ease, color 0.15s ease;
}
.pdp-delete-btn:hover {
    transform: scale(1.15);
    color: var(--pd-red-hover);
}

.pdp-add-box {
    border: 1.5px dashed #d8c2b5;
    border-radius: 14px;
    background: #fffdfc;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 16px;
    cursor: pointer;
    transition: all 0.2s ease;
    aspect-ratio: 1 / 1;
    text-align: center;
}
.pdp-add-box:hover {
    border-color: var(--pd-primary);
    background: #fffbf9;
}
.pdp-add-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #734d38;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 17px;
    margin-bottom: 8px;
}
.pdp-add-title {
    font-size: 13px;
    font-weight: 700;
    color: var(--pd-brown-dark);
    margin-bottom: 3px;
}
.pdp-add-sub {
    font-size: 10.5px;
    color: var(--pd-muted);
    line-height: 1.35;
}

/* Action Buttons */
.pd-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 26px;
}
.btn-save-main {
    background: var(--pd-primary);
    color: #ffffff;
    border: none;
    padding: 11px 26px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
}
.btn-save-main:hover {
    background: var(--pd-primary-hover);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(217, 130, 90, 0.25);
}
.btn-back-outline {
    background: #ffffff;
    color: var(--pd-brown-dark);
    border: 1.5px solid #ebdcd5;
    padding: 10px 20px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
    text-decoration: none;
    display: inline-block;
}
.btn-back-outline:hover {
    background: #fbf6f3;
}

@media (max-width: 768px) {
    .pd-card {
        padding: 20px;
    }
    .pd-main-grid {
        grid-template-columns: 1fr;
    }
    .form-two-col {
        grid-template-columns: 1fr;
    }
    .pd-actions {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>

<div class="pd-wrap">
    <div class="pd-breadcrumb">
        <a href="product-listing.php">Manage Products</a>
        <span>&rsaquo;</span>
        <span>New Product</span>
    </div>

    <div class="pd-card">
        <div class="pd-header">
            <div class="pd-title-group">
                <span class="pd-title-icon">🥯</span>
                <div>
                    <span class="pd-title-text">Create New Product</span>
                </div>
            </div>
        </div>

        <form method="post" enctype="multipart/form-data" id="createProductForm">
            <div class="pd-main-grid">
                <div class="pd-image-column">
                    <div class="pd-main-photo-card" id="mainPhotoCard">
                        <img id="mainProductImage" src="/products/default.jpg" alt="Product Thumbnail">
                        <label for="productPhotoInput" class="pd-change-photo-btn">
                            📷 Upload Photo
                        </label>
                    </div>
                    <input type="file" name="photo" id="productPhotoInput" accept="image/*" style="display:none;">
                    <?= err('photo') ?>
                </div>

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

                    <label for="pdpDetailInput" class="pdp-add-box" id="addBoxLabel">
                        <div class="pdp-add-icon">＋</div>
                        <div class="pdp-add-title">Add Photos</div>
                        <div class="pdp-add-sub">Upload up to 10 photos<br>JPG, PNG (Max 5MB)</div>
                    </label>
                </div>
                <input type="file" id="pdpDetailInput" name="detail_photos[]" multiple accept="image/*" style="display:none;">
            </div>

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