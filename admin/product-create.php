<?php
include '../_base.php';
auth('Admin');
$error = '';
if (is_post()) {
    $name = trim(post('name'));
    $price = post('price');
    $stock = post('stock');
    $description = trim(post('description'));
    if ($name === '') $error .= "Product name cannot be empty.<br>";
    if (!is_numeric($price) || $price <= 0) $error .= "Price must be greater than zero.<br>";
    if (!is_numeric($stock) || $stock < 0) $error .= "Stock cannot be negative.<br>";
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
        $stm = $_db->prepare("INSERT INTO product (id, name, price, stock, description) VALUES (?,?,?,?,?)");
        $stm->execute([$newProductId, $name, $price, $stock, $description]);
        //Upload photo and insert into product_photo table
        if (!empty($_FILES['photo']['name'])) {
            $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $photoName = time() . "." . strtolower($ext);
            move_uploaded_file($_FILES['photo']['tmp_name'], "../products/" . $photoName);
            $photoStm = $_db->prepare("INSERT INTO product_photo (product_id, photo, sort_order, created_at) VALUES (?,?,0,NOW())");
            $photoStm->execute([$newProductId, $photoName]);
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
    <div style="margin-bottom:16px;">
        <label>Product Photo:</label>
        <!-- 这里加 id="photoInput" -->
        <input type="file" name="photo" id="photoInput" accept="image/*">
        <!-- 预览图片，默认隐藏 -->
        <div>
            <img id="previewImg" style="margin-top:10px; max-width:200px; display:none; border:1px solid #aaa;">
        </div>
    </div>
    <div>
        <button type="submit" style="padding:8px 16px;">Save Product</button>
        <a href="product-listing.php" style="margin-left:12px;">Cancel</a>
    </div>
</form>

<!--图片预览JS脚本-->
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
