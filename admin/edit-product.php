<?php
include '../_base.php';
auth('Admin');

$pid = $_GET['id'];

//拿商品和图片记录
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
            //更新已有图片
            $upd = $_db->prepare("UPDATE product_photo SET photo=? WHERE id=?");
            $upd->execute([$photoName,$item->photo_id]);
        }else{
            //补缺失的记录，专门修复P010这种
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
        <img src="../products/<?= $item->photo ?>" style="max-width:180px;">
        </p>
    <?php else: ?>
        <p style="color:red;">⚠️This product has NO photo record</p>
    <?php endif ?>

    <input type="file" name="newphoto" accept="image/*">
    <br><br>
    <button type="submit">Upload New Photo</button>
    <a href="product-listing.php">Back</a>
</form>

<?php include '../_foot.php'; ?>
