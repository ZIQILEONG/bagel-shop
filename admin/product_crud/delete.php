<?php
require '../../config.php';
require '../../_base.php';

$id = $_GET['id'];
$s = $_db->prepare("DELETE FROM products_crud WHERE id = ?");
$s->execute([$id]);

redirect("index.php");
?>
