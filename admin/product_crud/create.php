<?php
require '../../config.php';
require '../../_base.php';
$_title = "Add Product";
include '../../_head.php';
?>

<h2>Add New Product</h2>
<form action="process.php" method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="create">
    <div>Name: <input type="text" name="name" required></div>
    <div>Price: <input type="number" step="0.01" name="price" required></div>
    <div>Description:<textarea name="description"></textarea></div>
    <div>Image File: <input type="file" name="img"></div>
    <button type="submit">Save</button>
</form>

<?php include '../../_foot.php'; ?>
