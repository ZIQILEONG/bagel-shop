<?php
include '../_base.php';

auth('Admin');

$stm = $_db->prepare("
    SELECT p.name, SUM(i.unit) AS total_sold
    FROM order_item i
    JOIN product p ON i.product_id = p.id
    JOIN orders o ON i.order_id = o.id
    WHERE o.status != 'Cancelled'
    GROUP BY p.id
    ORDER BY total_sold DESC
    LIMIT 5
");
$stm->execute();
$arr = $stm->fetchAll();

// ----------------------------------------------------------------------------

$_title = 'Top 5 Selling Products';
include '../_head.php';
?>

<table class="table">
    <tr>
        <th>Rank</th>
        <th>Product Name</th>
        <th>Total Sold</th>
    </tr>

    <?php $rank = 1; foreach ($arr as $row): ?>
        <tr>
            <td><?= $rank++ ?></td>
            <td><?= $row->name ?></td>
            <td class="right"><?= $row->total_sold ?></td>
        </tr>
        <?php endforeach ?>
</table>

<?php
include '../_foot.php';