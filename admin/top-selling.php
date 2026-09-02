<?php
include '../_base.php';
auth('Admin');

$stm = $_db->prepare("
    SELECT p.name, SUM(i.unit) AS total_sold
    FROM order_item i
    JOIN product p ON i.product_id = p.id
    JOIN orders o ON i.order_id = o.id
    WHERE o.status != 'Cancelled'
    GROUP BY p.id, p.name
    ORDER BY total_sold DESC
    LIMIT 5
");
$stm->execute();
$arr = $stm->fetchAll();

$_title = 'Admin | Top 5 Best Selling Bagels';
include '../_head.php';
?>

<div class="il-50-cf3844">
    <!-- Breadcrumb & Title -->
    <div class="il-51-e46be5">
        <a class="il-52-bd169b" href="/">Home</a> &rsaquo;
        <span class="il-53-4611fb">Top Selling</span>
    </div>
    
    <div class="il-54-0d4573">
        <h1 class="il-55-bef8f2">Top 5 Selling Bagels</h1>
        <p class="il-56-f078dc">Our most popular items ranked by completed customer orders.</p>
    </div>

    <!-- Table Card -->
    <div class="il-57-634cb7">
        <table class="il-58-358f9e">
            <thead>
                <tr class="il-59-cd77b5">
                    <th class="il-60-2a7419">Rank</th>
                    <th class="il-61-ebf196">Bagel Name</th>
                    <th class="il-62-66c361">Total Sold</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($arr)): ?>
                    <tr>
                        <td class="il-63-dba9b1" colspan="3">
                            No sales data recorded yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $rank = 1; 
                    $medals = [1 => '🥇', 2 => '🥈', 3 => '🥉'];
                    foreach ($arr as $row): 
                    ?>
                        <tr class="il-64-bf4e98" onmouseover="this.style.background='#fdfaf7'" onmouseout="this.style.background='transparent'">
                            <!-- Rank Medal / Number -->
                            <td style="text-align: center; padding: 14px 10px; font-size: <?= $rank <= 3 ? '18px' : '14px' ?>; font-weight: 800; color: #3e2619;">
                                <?= $medals[$rank] ?? "#$rank" ?>
                            </td>

                            <!-- Product Name -->
                            <td class="il-65-f1bbf5">
                                <?= htmlspecialchars($row->name) ?>
                            </td>

                            <!-- Total Units -->
                            <td class="il-66-7cd25e">
                                <b class="il-67-3267f5"><?= number_format((int)$row->total_sold) ?></b>
                                <span class="il-68-dfdf37">units</span>
                            </td>
                        </tr>
                    <?php $rank++; endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
include '../_foot.php';
?>