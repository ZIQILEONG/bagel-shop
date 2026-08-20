<?php
require '../../config.php';
require '../../_base.php';

$stmt = $_db->query("SELECT * FROM products");
$products = $stmt->fetchAll();

$_title = "Admin Product Maintenance";
include '../../_head.php';
?>

<h2>Product Admin CRUD</h2>
<a href="create.php">[+] Add New Product</a>
<table border="1" cellpadding="8">
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Price</th>
        <th>Description</th>
        <th>Image</th>
        <th>Action</th>
    </tr>
    <?php foreach($products as $p): ?>
    <tr>
        <td><?= $p->id ?></td>
        <td><?= htmlspecialchars($p->name) ?></td>
        <td><?= $p->price ?></td>
        <td><?= htmlspecialchars($p->description) ?></td>
        <td><?= $p->image ?></td>
        <td>
            <a href="edit.php?id=<?= $p->id ?>">Edit</a>
            <a href="delete.php?id=<?= $p->id ?>" onclick="return confirm('Confirm delete?')">Delete</a>
        </td>
    </tr>
    <?php endforeach ?>
</table>

<?php include '../../_foot.php'; ?>
