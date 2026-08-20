<?php
require '../../config.php';
require '../../_base.php';
$id = $_GET['id']??0;
$stmt = $_db->prepare("DELETE FROM products WHERE id = ?");
$stmt->execute([$id]);
redirect("index.php");
