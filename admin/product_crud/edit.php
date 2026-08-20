<?php
require '../../config.php';
require '../../_base.php';
$id = $_GET['id'] ?? 0;
$stmt = $_db->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();
if(!$item){
    redirect("index.php");
}

$_title = "Edit Product";
include '../../_head.php';
?>
<h2>Edit Product #<?= $item->id ?></h2>
<form action="process.php" method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="id" value="<?= $item->id ?>">
    <div>Name: <input type="text" name="name" value="<?= htmlspecialchars($item->name) ?>" required></div>
    <div>Price: <input type="number" step="0.01" name="price" value="<?= $item->price ?>" required></div>
    <div>Description:<textarea name="description"><?= htmlspecialchars($item->description) ?></textarea></div>
    <div>Replace Image: <input type="file" name="img"></div>
    <button type="submit">Update</button>
</form>

<?php include '../../_foot.php'; ?>
