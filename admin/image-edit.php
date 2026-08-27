<?php
include '../_base.php';
auth('Admin');

$id       = req('id');
$photo_id = req('photo_id'); // empty string = main product photo
$action   = req('action');

if ($photo_id === '') {
    $stm = $_db->prepare('SELECT photo FROM product WHERE id = ?');
    $stm->execute([$id]);
    $filename = $stm->fetchColumn();
} else {
    $stm = $_db->prepare('SELECT photo FROM product_photo WHERE id = ? AND product_id = ?');
    $stm->execute([$photo_id, $id]);
    $filename = $stm->fetchColumn();
}

$dir  = root('products');
$path = "$dir/$filename";

if (!$filename || !file_exists($path)) {
    temp('info', 'Photo not found.');
    redirect('product-detail.php?id=' . $id);
}

$ops = match ($action) {
    'rotate_left'  => [['type' => 'rotate', 'angle' => -90]],
    'rotate_right' => [['type' => 'rotate', 'angle' => 90]],
    'flip_h'       => [['type' => 'flip', 'direction' => 'x']],
    'flip_v'       => [['type' => 'flip', 'direction' => 'y']],
    default        => [],
};

if ($ops) {
    process_image($path, $path, $ops);
    temp('info', 'Image updated.');
}

redirect('product-detail.php?id=' . $id);