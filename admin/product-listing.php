<?php
include '../_base.php';

// ----------------------------------------------------------------------------

// (1) Authorization (admin)
auth('Admin');

// (2) Return all products
$stm = $_db->prepare("SELECT * FROM product ORDER BY id");
$stm->execute([]);
$arr = $stm->fetchAll();

// ----------------------------------------------------------------------------

$_title = 'Product | Listing (Admin)';
include '../_head.php';
?>

<p><?= count($arr) ?> record(s)</p>

<p>
    <button data-get="product-detail.php">Create New Product</button>
</p>

<table class="table">
    <tr>
        <th>Photo</th>
        <th>Id</th>
        <th>Name</th>
        <th>Price (RM)</th>
        <th>Stock</th>
        <th></th>
    </tr>

    <?php foreach ($arr as $p): ?>
    <tr>
        <td><img src="/products/<?= $p->photo ?>" width="50" height="50"></td>
        <td><?= $p->id ?></td>
        <td><?= $p->name ?></td>
        <td class="right"><?= $p->price ?></td>
        <td class="right"><?= $p->stock ?></td>
        <td>
            <button data-get="product-detail.php?id=<?= $p->id ?>">Detail</button>
        </td>
    </tr>
    <?php endforeach ?>
</table>

<?php
include '../_foot.php';