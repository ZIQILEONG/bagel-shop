<?php
include '../_base.php';
auth('Admin');

$id       = req('id');       // product id, for redirect
$type     = req('type');     // 'main' or 'gallery'
$photo_id = req('photo_id'); // only for gallery
$action   = req('action');   // rotate_left, rotate_right, flip_h, flip_v

if ($type === 'main') {
    $stm = $_db->prepare('SELECT photo FROM product WHERE id = ?');
    $stm->execute([$id]);
    $filename = $stm->fetchColumn();
    $dir = root('products');
} else {
    $stm = $_db->prepare('SELECT photo FROM product_photo WHERE id = ? AND product_id = ?');
    $stm->execute([$photo_id, $id]);
    $filename = $stm->fetchColumn();
    $dir = root('photos/products');
}

if (!$filename || !file_exists("$dir/$filename")) {
    temp('error', 'Photo not found.');
    redirect('product-detail.php?id=' . $id);
}

$path = "$dir/$filename";
$ext  = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

switch ($ext) {
    case 'jpg':
    case 'jpeg':
        $img = imagecreatefromjpeg($path);
        break;
    case 'png':
        $img = imagecreatefrompng($path);
        break;
    case 'webp':
        $img = imagecreatefromwebp($path);
        break;
    default:
        temp('error', 'Unsupported image format for editing.');
        redirect('product-detail.php?id=' . $id);
}

switch ($action) {
    case 'rotate_left':
        $img = imagerotate($img, 90, 0);
        break;
    case 'rotate_right':
        $img = imagerotate($img, -90, 0);
        break;
    case 'flip_h':
        imageflip($img, IMG_FLIP_HORIZONTAL);
        break;
    case 'flip_v':
        imageflip($img, IMG_FLIP_VERTICAL);
        break;
}

switch ($ext) {
    case 'jpg':
    case 'jpeg':
        imagejpeg($img, $path, 90);
        break;
    case 'png':
        imagepng($img, $path);
        break;
    case 'webp':
        imagewebp($img, $path);
        break;
}
imagedestroy($img);

temp('info', 'Image updated.');
redirect('product-detail.php?id=' . $id);