<?php
require '../../config.php';
require '../../_base.php';

$action = $_POST['action'] ?? '';

// Create new product
if($action === 'create'){
    $name = $_POST['name'];
    $price = $_POST['price'];
    $desc = $_POST['description'];
    $imgName = '';
    if(isset($_FILES['img']) && $_FILES['img']['error']===0){
        $imgName = time()."_".basename($_FILES['img']['name']);
        move_uploaded_file($_FILES['img']['tmp_name'], "../../photos/".$imgName);
    }
    $s = $_db->prepare("INSERT INTO products(name,price,description,image,created_at) VALUES(?,?,?,?,NOW())");
    $s->execute([$name,$price,$desc,$imgName]);
}

// Update existing product
if($action === 'update'){
    $id = $_POST['id'];
    $name = $_POST['name'];
    $price = $_POST['price'];
    $desc = $_POST['description'];
    $imgName = null;
    if(isset($_FILES['img']) && $_FILES['img']['error']===0){
        $imgName = time()."_".basename($_FILES['img']['name']);
        move_uploaded_file($_FILES['img']['tmp_name'], "../../photos/".$imgName);
    }
    if($imgName){
        $s = $_db->prepare("UPDATE products SET name=?,price=?,description=?,image=? WHERE id=?");
        $s->execute([$name,$price,$desc,$imgName,$id]);
    }else{
        $s = $_db->prepare("UPDATE products SET name=?,price=?,description=? WHERE id=?");
        $s->execute([$name,$price,$desc,$id]);
    }
}

redirect("index.php");
