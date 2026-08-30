<?php
session_start();
require "db.php";
$pid = $_GET['id'];

$stmt = $pdo->prepare("SELECT p.*,pp.photo,pp.id as photo_id FROM product p
LEFT JOIN product_photo pp ON p.id=pp.product_id AND pp.sort_order=0
WHERE p.id=?");
$stmt->execute([$pid]);
$item = $stmt->fetch();

if($_SERVER['REQUEST_METHOD']==='POST' && $_FILES['newphoto']['error']===UPLOAD_ERR_OK){
    $ext = pathinfo($_FILES['newphoto']['name'],PATHINFO_EXTENSION);
    $filename = time().".".$ext;
    move_uploaded_file($_FILES['newphoto']['tmp_name'],"../products/".$filename);

    if($item['photo_id']){
        $upd = $pdo->prepare("UPDATE product_photo SET photo=? WHERE id=?");
        $upd->execute([$filename,$item['photo_id']]);
    }else{
        $ins = $pdo->prepare("INSERT INTO product_photo(product_id,photo,sort_order,created_at) VALUES (?,?,0,NOW())");
        $ins->execute([$pid,$filename]);
    }
    header("Location:product-listing.php");
    exit;
}
?>
<h3>Edit Product <?= $pid ?></h3>

<form method="post" enctype="multipart/form-data">
    <?php if(!empty($item['photo'])){ ?>
        <p>Current image:<img src="../products/<?=$item['photo']?>" width="100"></p>
    <?php }else{ ?>
        <p>⚠️This product has no image</p>
    <?php } ?>
    <input type="file" name="newphoto" accept="image/*">
    <br>
    <button type="submit">Upload New Image</button>
</form>
