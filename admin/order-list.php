<?php
include '../_base.php';
require '../lib/SimplePager.php';

// Authorization (Admin only)
auth('Admin');

// Read filters
$page   = req('page', 1);
$status = req('status', 'All');
$search = trim(req('search', ''));

// Build Filter Conditions
$where  = [];
$params = [];

if ($status !== 'All' && $status !== '') {
    $where[]  = "o.status = ?";
    $params[] = $status;
}

if ($search !== '') {
    $where[]  = "(o.id LIKE ? OR u.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Main Query with SimplePager (10 per page)
$query = "
    SELECT o.*, u.name, u.is_deleted 
    FROM orders o 
    JOIN user u ON o.user_id = u.id 
    $where_clause
    ORDER BY o.id DESC
";

$pager = new SimplePager($query, $params, 10, $page);
$arr   = $pager->result;

// ----------------------------------------------------------------------------
$_title = 'Admin | Order Management';
include '../_head.php';
?>

<link rel="stylesheet" href="<?= app_url('css/admin-order-list.css') ?>">

<div class="pl-order-container">
    
    <!-- Breadcrumb -->
    <div class="pl-crumb">
        <a href="/">Home</a>
        <span>&rsaquo;</span>
        <span class="il-19-e0e1a0">Orders</span>
    </div>

    <!-- Header -->
    <div class="pl-header">
        <div>
            <h1>Orders Management</h1>
            <p>Review customer purchases and track fulfillment status.</p>
        </div>
    </div>

    <!-- Controls Bar: Filter Status Tabs + Search -->
    <div class="pl-controls-card">
        <div class="pl-filter-tabs">
            <?php 
            $tab_options = ['All' => 'All Orders', 'Pending' => 'Pending', 'Processing' => 'Processing', 'Completed' => 'Completed', 'Cancelled' => 'Cancelled'];
            foreach ($tab_options as $tab_val => $tab_label): 
                $is_active = ($status === $tab_val);
            ?>
                <a href="?status=<?= urlencode($tab_val) ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?>" 
                   class="pl-tab-btn <?= $is_active ? 'is-active' : '' ?>">
                    <?= $tab_label ?>
                </a>
            <?php endforeach; ?>
        </div>

        <form method="get" class="pl-search-form">
            <?php if ($status !== 'All'): ?>
                <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
            <?php endif; ?>
            <input type="search" name="search" class="pl-search-input" placeholder="Search ID or Name..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="pl-search-btn">Search</button>
            <?php if ($search !== '' || $status !== 'All'): ?>
                <a class="il-20-68c8b9" href="order-list.php">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Orders Master Table -->
    <div class="pl-table-card">
        <table class="pl-table">
            <thead>
                <tr>
                    <th class="il-21-51378c">Order ID</th>
                    <th class="il-22-666b47">Date &amp; Time</th>
                    <th>Customer Name</th>
                    <th class="il-23-41414d">Items</th>
                    <th class="il-24-a847e6">Total Amount</th>
                    <th class="il-25-39ccde">Status</th>
                    <th class="il-26-43dabb">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($arr)): ?>
                    <tr>
                        <td class="il-27-579d22" colspan="7">
                            🔍 No order records matching your criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($arr as $o): 
                        $status_class = match(strtolower(trim($o->status))) {
                            'completed'  => 'pl-status-completed',
                            'pending'    => 'pl-status-pending',
                            'processing' => 'pl-status-processing',
                            'cancelled'  => 'pl-status-cancelled',
                            default      => 'pl-status-pending'
                        };
                    ?>
                    <tr>
                        <!-- Order ID -->
                        <td>
                            <span class="pl-order-id">#<?= htmlspecialchars($o->id) ?></span>
                        </td>

                        <!-- Datetime (No Wrap) -->
                        <td class="il-28-a7ce7a">
                            <?= date('d M Y, h:i A', strtotime($o->datetime)) ?>
                        </td>

                        <!-- Member Name -->
                        <td>
                            <strong class="il-29-49e04b">
                                <?= htmlspecialchars($o->name) ?>
                            </strong>
                            <?php if ($o->is_deleted): ?>
                                <span class="il-30-5ed6aa" title="Account deactivated">Disabled</span>
                            <?php endif; ?>
                        </td>

                        <!-- Items Count -->
                        <td class="il-31-8898d8">
                            <?= (int)$o->count ?>
                        </td>

                        <!-- Total (RM) -->
                        <td class="il-32-99c275">
                            RM <?= number_format((float)$o->total, 2) ?>
                        </td>

                        <!-- Status Badge -->
                        <td class="il-33-09ec48">
                            <span class="pl-status-tag <?= $status_class ?>">
                                <?= htmlspecialchars($o->status) ?>
                            </span>
                        </td>

                        <!-- View Button -->
                        <td class="il-34-ece624">
                            <button type="button" class="pl-btn-view" data-get="order-detail.php?id=<?= urlencode($o->id) ?>">
                                View
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pill Pagination -->
    <?php $pager->html() ?>

</div>

<?php
include '../_foot.php';
?>