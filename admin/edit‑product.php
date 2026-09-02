<?php
include '../_base.php';
auth('Admin');

$pid = $_GET['id'];

//Retrieve product and image records
$stm = $_db->prepare("SELECT p.*, pp.id AS photo_id, pp.photo 
FROM product p 
LEFT JOIN product_photo pp ON p.id=pp.product_id AND pp.sort_order=0 
WHERE p.id=?");
$stm->execute([$pid]);
$item = $stm->fetch();

if(is_post()){
    if(!empty($_FILES['newphoto']['name'])){
        $ext = pathinfo($_FILES['newphoto']['name'],PATHINFO_EXTENSION);
        $photoName = time().".".strtolower($ext);
        move_uploaded_file($_FILES['newphoto']['tmp_name'],"../products/".$photoName);

        if($item->photo_id){
            //Update existing images
            $upd = $_db->prepare("UPDATE product_photo SET photo=? WHERE id=?");
            $upd->execute([$photoName,$item->photo_id]);
        }else{
            // Fill in missing records; specifically designed to fix issues like P010.
            $ins = $_db->prepare("INSERT INTO product_photo(product_id,photo,sort_order,created_at) VALUES (?,?,0,NOW())");
            $ins->execute([$pid,$photoName]);
        }
        temp('info','Photo updated');
        redirect('product-listing.php');
    }
}

$_title = "Edit Product ".$pid;
include '../_head.php';
?>
<h2>Edit Product <?= $pid ?></h2>

<form method="post" enctype="multipart/form-data">
    <?php if(!empty($item->photo)): ?>
        <p>Current Photo:<br>
        <img class="il-2-742864" src="../products/<?= $item->photo ?>">
        </p>
    <?php else: ?>
        <p class="il-3-b64e6d">⚠️This product has NO photo record</p>
    <?php endif ?>

    <input type="file" name="newphoto" accept="image/*">
    <br><br>
    <button type="submit">Upload New Photo</button>
    <a href="product-listing.php">Back</a>
</form>

<?php include '../_foot.php'; ?>
