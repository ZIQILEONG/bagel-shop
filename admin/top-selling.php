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

<div style="max-width: 800px; margin: 30px auto 80px; padding: 0 20px;">
    <!-- Breadcrumb & Title -->
    <div style="font-size: 13px; color: #968377; margin-bottom: 12px;">
        <a href="/" style="color: #968377; text-decoration: none;">Home</a> &rsaquo;
        <span style="color: #3e2619; font-weight: 600;">Top Selling</span>
    </div>
    
    <div style="margin-bottom: 24px;">
        <h1 style="font-size: 26px; font-weight: 800; color: #3e2619; margin: 0 0 6px;">Top 5 Selling Bagels</h1>
        <p style="font-size: 14px; color: #968377; margin: 0;">Our most popular items ranked by completed customer orders.</p>
    </div>

    <!-- Table Card -->
    <div style="background: #ffffff; border: 1px solid #ebdcd5; border-radius: 18px; padding: 20px; box-shadow: 0 4px 18px rgba(62,38,25,0.04);">
        <table style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead>
                <tr style="background: #faf5f0; color: #968377; font-size: 12px; text-transform: uppercase; font-weight: 800;">
                    <th style="width: 80px; text-align: center; padding: 12px 10px; border-radius: 8px 0 0 8px;">Rank</th>
                    <th style="padding: 12px 16px;">Bagel Name</th>
                    <th style="width: 160px; text-align: right; padding: 12px 16px; border-radius: 0 8px 8px 0;">Total Sold</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($arr)): ?>
                    <tr>
                        <td colspan="3" style="text-align: center; padding: 36px 16px; color: #968377; font-size: 14px;">
                            No sales data recorded yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $rank = 1; 
                    $medals = [1 => '🥇', 2 => '🥈', 3 => '🥉'];
                    foreach ($arr as $row): 
                    ?>
                        <tr style="border-bottom: 1px solid #f7eeea; transition: background 0.15s ease;" onmouseover="this.style.background='#fdfaf7'" onmouseout="this.style.background='transparent'">
                            <!-- Rank Medal / Number -->
                            <td style="text-align: center; padding: 14px 10px; font-size: <?= $rank <= 3 ? '18px' : '14px' ?>; font-weight: 800; color: #3e2619;">
                                <?= $medals[$rank] ?? "#$rank" ?>
                            </td>

                            <!-- Product Name -->
                            <td style="padding: 14px 16px; font-weight: 700; color: #3e2619; font-size: 14.5px;">
                                <?= htmlspecialchars($row->name) ?>
                            </td>

                            <!-- Total Units -->
                            <td style="text-align: right; padding: 14px 16px;">
                                <b style="font-size: 15px; color: #cf7953;"><?= number_format((int)$row->total_sold) ?></b>
                                <span style="font-size: 12px; color: #968377; margin-left: 2px;">units</span>
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