<?php
include '../_base.php';
auth('Admin');
$error = '';
if (is_post()) {
    $name = trim(post('name'));
    $category_id = post('category_id');
    $price = post('price');
    $stock = post('stock');
    $description = trim(post('description'));
    $product_video_url = trim(post('product_video_url'));
    if ($name === '') $error .= "Product name cannot be empty.<br>";
    if ($category_id === '') $error .= "Category must be selected.<br>";
    if (!is_numeric($price) || $price <= 0) $error .= "Price must be greater than zero.<br>";
    if (!is_numeric($stock) || $stock < 0) $error .= "Stock cannot be negative.<br>";
    if ($product_video_url !== '' && (!filter_var($product_video_url, FILTER_VALIDATE_URL) || !str_contains($product_video_url, 'youtube.com'))) $error .= "Product video URL is not valid.<br>Example: https://www.youtube.com/watch?v=example";
    if ($error === '') {
        //Auto Generate product ID P001 P002 P003
        $rs = $_db->query("SELECT id FROM product ORDER BY id DESC LIMIT 1");
        $lastId = $rs->fetchColumn();
        if ($lastId) {
            $num = intval(substr($lastId,1)) + 1;
            $newProductId = "P" . str_pad($num,3,"0",STR_PAD_LEFT);
        } else {
            $newProductId = "P001";
        }
        //Insert new product into product table
        $stm = $_db->prepare("INSERT INTO product (id, name, price, video_url, stock, description, category_id) VALUES (?,?,?,?,?,?,?)");
        $stm->execute([$newProductId, $name, $price, $product_video_url, $stock, $description, $category_id]);
        //Upload photo and insert into product_photo table
        if (!empty($_FILES['photo']['name'])) {
            $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $photoName = time() . "." . strtolower($ext);
            move_uploaded_file($_FILES['photo']['tmp_name'], "../products/" . $photoName);
            $photoStm = $_db->prepare("UPDATE product SET photo = ? WHERE id = ?");
            $photoStm->execute([$photoName, $newProductId]);
        }

        // Upload multiple photos and insert into product_photo table
        if (!empty($_FILES['photos']['name'][0])) {
            $sort_order = 1;
            foreach ($_FILES['photos']['name'] as $i => $filename) {
                if ($_FILES['photos']['error'][$i] == UPLOAD_ERR_OK) {
                    $ext = pathinfo($filename, PATHINFO_EXTENSION);

                    $detailPhotoName = time() . "_" . $i . "." . strtolower($ext);

                    move_uploaded_file(
                        $_FILES['photos']['tmp_name'][$i],
                        "../products/" . $detailPhotoName
                    );

                    $photoStm = $_db->prepare("
                        INSERT INTO product_photo (product_id, photo, sort_order, created_at)
                        VALUES (?, ?, ?, ?)
                    ");

                    $photoStm->execute([
                        $newProductId,
                        $detailPhotoName,
                        $sort_order++,
                        date('Y-m-d H:i:s')
                    ]);
                }
            }
        }

        temp('info','New product created successfully.');
        redirect('product-listing.php');
    }
}
$_title = 'Create New Product (Admin)';
include '../_head.php';
?>
<h2>Add New Product</h2>
<?php if($error): ?>
    <div style="color:red; border:1px solid red; padding:10px; margin:10px 0;">
        <?= $error ?>
    </div>
<?php endif ?>
<form method="post" enctype="multipart/form-data" style="max-width:600px;border:1px solid #ccc;padding:20px;border-radius:8px;">
    <div style="margin-bottom:12px;">
        <label>Product Name:</label>
        <input type="text" name="name" style="width:100%;padding:6px;" value="<?= post('name','') ?>" required>
    </div>
    <div style="margin-bottom:12px;">
        <label>Category:</label>
        <select name="category_id" style="width:100%;padding:6px;" required>
            <option value="">-- Select Category --</option>
            <?php
            $catStm = $_db->query("SELECT id, name FROM category ORDER BY name");
            while ($cat = $catStm->fetch()) {
                $selected = (post('category_id') == $cat -> id) ? 'selected' : '';
                echo "<option value=\"{$cat -> id}\" $selected>{$cat -> name}</option>";
            }
            ?>
        </select>
    </div>
    <div style="margin-bottom:12px;">
        <label>Price (RM):</label>
        <input type="number" step="0.01" name="price" style="width:100%;padding:6px;" value="<?= post('price','') ?>" required>
    </div>
    <div style="margin-bottom:12px;">
        <label>Stock Quantity:</label>
        <input type="number" name="stock" style="width:100%;padding:6px;" value="<?= post('stock','0') ?>" required>
    </div>
    <div style="margin-bottom:12px;">
        <label>Description:</label>
        <textarea name="description" rows="4" style="width:100%;padding:6px;"><?= post('description','') ?></textarea>
    </div>
    <div style="margin-bottom:12px;">
        <label>Product Video Url:</label>
        <input type="text" name="product_video_url" style="width:100%;padding:6px;" value="<?= post('product_video_url','') ?>" placeholder="https://www.youtube.com/watch?v=example">
    </div>
    <div style="margin-bottom:16px;">
        <label>Product Photo:</label>
        <!-- id="photoInput" -->
        <input type="file" name="photo" id="photoInput" accept="image/*">
        <!-- Preview image, hidden by default -->
        <div>
            <img id="previewImg" style="margin-top:10px; max-width:200px; display:none; border:1px solid #aaa;">
        </div>
    </div>
    <div style="margin-bottom:12px;">
        <label>Product Detail Photo:</label>
        <div id="dropZone" style="border:3px dashed #ddb99c;border-radius:8px;padding:25px;text-align:center;background:#fbf3e8;cursor:pointer;">
    <p>📁 Drag & Drop photos here or click to select</p>
    <input type="file" name="photos[]" id="photosInput" multiple accept="image/*" style="display:none;">
</div>
<div id="photoPreview" style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px;"></div>

<script>
(function() {
    const dz = document.getElementById('dropZone');
    const input = document.getElementById('photosInput');
    const preview = document.getElementById('photoPreview');

    dz.addEventListener('click', () => input.click());

    ['dragenter','dragover','dragleave','drop'].forEach(ev =>
        dz.addEventListener(ev, e => { e.preventDefault(); e.stopPropagation(); })
    );

    dz.addEventListener('drop', e => {
        input.files = e.dataTransfer.files;
        showPreview(input.files);
    });

    input.addEventListener('change', () => showPreview(input.files));

    function showPreview(files) {
        preview.innerHTML = '';
        [...files].forEach(file => {
            if (!file.type.startsWith('image/')) return;
            const reader = new FileReader();
            reader.onload = e => {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.style = 'width:80px;height:80px;object-fit:cover;border-radius:6px;';
                preview.appendChild(img);
            };
            reader.readAsDataURL(file);
        });
    }
})();
</script>
    </div>
    <div>
        <button type="submit" style="padding:8px 16px;">Save Product</button>
        <button type="button"
                onclick="window.location.href='product-listing.php'"
                style="margin-left:12px;">
            Cancel
        </button>
    </div>
</form>

<!--Image Preview JS Script-->
<script>
    const fileInput = document.getElementById('photoInput');
    const previewImg = document.getElementById('previewImg');
    fileInput.onchange = function(e){
        const file = e.target.files[0];
        if(file){
            const reader = new FileReader();
            reader.onload = function(ev){
                previewImg.src = ev.target.result;
                previewImg.style.display = 'block';
            }
            reader.readAsDataURL(file);
        }else{
            previewImg.style.display = 'none';
        }
    }
</script>

<?php include '../_foot.php'; ?>
