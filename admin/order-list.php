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

<style>
:root {
    --pl-primary: #cf7953;
    --pl-primary-hover: #b86440;
    --pl-brown-dark: #3e2619;
    --pl-text: #4a3b32;
    --pl-muted: #968377;
    --pl-border: #ebdcd5;
    --pl-card-bg: #ffffff;
    --pl-accent: #faf5f0;
}

.pl-order-container {
    max-width: 1120px;
    margin: 32px auto 80px;
    padding: 0 20px;
    font-family: 'Nunito Sans', sans-serif;
}

/* Breadcrumb */
.pl-crumb {
    font-size: 13px;
    color: var(--pl-muted);
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.pl-crumb a {
    color: var(--pl-muted);
    text-decoration: none;
    transition: color 0.15s;
}
.pl-crumb a:hover {
    color: var(--pl-primary);
}

/* Header */
.pl-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}
.pl-header h1 {
    font-size: 28px;
    font-weight: 800;
    color: var(--pl-brown-dark);
    margin: 0 0 4px;
    letter-spacing: -0.01em;
}
.pl-header p {
    font-size: 14px;
    color: var(--pl-muted);
    margin: 0;
}

/* Controls Bar (Search + Filter Tabs) */
.pl-controls-card {
    background: #ffffff;
    border: 1px solid var(--pl-border);
    border-radius: 16px;
    padding: 14px 18px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 14px;
    box-shadow: 0 2px 10px rgba(62, 38, 25, 0.02);
}

.pl-filter-tabs {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}
.pl-tab-btn {
    padding: 6px 14px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 700;
    color: var(--pl-muted);
    text-decoration: none;
    border: 1px solid transparent;
    transition: all 0.2s ease;
}
.pl-tab-btn:hover {
    color: var(--pl-primary);
    background: var(--pl-accent);
}
.pl-tab-btn.is-active {
    background: var(--pl-brown-dark);
    color: #ffffff;
    border-color: var(--pl-brown-dark);
}

.pl-search-form {
    display: flex;
    align-items: center;
    gap: 8px;
}
.pl-search-input {
    padding: 8px 14px;
    border: 1.5px solid var(--pl-border);
    border-radius: 10px;
    font-size: 13px;
    outline: none;
    color: var(--pl-text);
    width: 200px;
    transition: border-color 0.2s ease;
}
.pl-search-input:focus {
    border-color: var(--pl-primary);
}
.pl-search-btn {
    padding: 8px 16px;
    background: var(--pl-primary);
    color: #ffffff;
    border: none;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.2s ease;
}
.pl-search-btn:hover {
    background: var(--pl-primary-hover);
}

/* Master Table Card */
.pl-table-card {
    background: var(--pl-card-bg);
    border: 1px solid var(--pl-border);
    border-radius: 18px;
    overflow-x: auto;
    box-shadow: 0 4px 20px rgba(62, 38, 25, 0.04);
}

.pl-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
}
.pl-table th {
    background: #faf6f2;
    color: var(--pl-muted);
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    padding: 14px 20px;
    border-bottom: 1.5px solid #f0e3db;
    white-space: nowrap;
}
.pl-table td {
    padding: 16px 20px;
    border-bottom: 1px solid #f7eeea;
    color: var(--pl-text);
    font-size: 13.5px;
    vertical-align: middle;
}
.pl-table tr:last-child td {
    border-bottom: none;
}
.pl-table tbody tr {
    transition: background-color 0.15s ease;
}
.pl-table tbody tr:hover {
    background-color: #fdfaf8;
}

/* Order ID Tag */
.pl-order-id {
    font-family: monospace;
    font-size: 13px;
    font-weight: 800;
    color: var(--pl-brown-dark);
    background: #fbf5ef;
    border: 1px solid var(--pl-border);
    padding: 3px 8px;
    border-radius: 6px;
    white-space: nowrap;
}

/* Status Badges with Colored Dots */
.pl-status-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 800;
    text-transform: capitalize;
    white-space: nowrap;
}
.pl-status-tag::before {
    content: "";
    width: 6px;
    height: 6px;
    border-radius: 50%;
}
.pl-status-completed { background: #eaf6ed; color: #1e7e45; }
.pl-status-completed::before { background: #22c55e; }

.pl-status-pending { background: #fef6e7; color: #b45309; }
.pl-status-pending::before { background: #f59e0b; }

.pl-status-processing { background: #eef4ff; color: #1d4ed8; }
.pl-status-processing::before { background: #3b82f6; }

.pl-status-cancelled { background: #fdf2f2; color: #c0392b; }
.pl-status-cancelled::before { background: #ef4444; }

/* View Button */
.pl-btn-view {
    background: #ffffff;
    color: var(--pl-brown-dark);
    border: 1.5px solid var(--pl-border);
    padding: 7px 16px;
    border-radius: 10px;
    font-size: 12.5px;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
}
.pl-btn-view:hover {
    background: var(--pl-primary);
    border-color: var(--pl-primary);
    color: #ffffff;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(207, 121, 83, 0.25);
}

/* =========================================================
   PILL PAGINATION
   ========================================================= */
.pager, .pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin: 32px 0 10px;
    list-style: none;
    padding: 0;
}

.pager a, 
.pager span,
.pagination a,
.pagination span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 40px;
    padding: 0 20px;
    background: #ffffff;
    color: #3e2619;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    border-radius: 999px;
    border: 1px solid #ebdcd5;
    box-shadow: 0 2px 8px rgba(62, 38, 25, 0.04);
    transition: all 0.2s ease;
}

.pager a:not(:first-child):not(:nth-child(2)):not(:nth-last-child(2)):not(:last-child),
.pager span:not(:first-child):not(:nth-child(2)):not(:nth-last-child(2)):not(:last-child) {
    min-width: 40px;
    padding: 0 12px;
}

.pager a:hover,
.pagination a:hover {
    background: #faf5f0;
    border-color: #cf7953;
    color: #cf7953;
}

.pager .active,
.pager .current,
.pager span:not([class]),
.pagination .active {
    background: #a34828 !important;
    color: #ffffff !important;
    border-color: #a34828 !important;
    box-shadow: 0 4px 12px rgba(163, 72, 40, 0.28);
}

.pager .disabled,
.pagination .disabled {
    opacity: 0.45;
    cursor: not-allowed;
    pointer-events: none;
}
</style>

<div class="pl-order-container">
    
    <!-- Breadcrumb -->
    <div class="pl-crumb">
        <a href="/">Home</a>
        <span>&rsaquo;</span>
        <span style="color: var(--pl-brown-dark); font-weight: 700;">Orders</span>
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
                <a href="order-list.php" style="font-size: 12.5px; color: var(--pl-muted); text-decoration: none; font-weight: 700;">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Orders Master Table -->
    <div class="pl-table-card">
        <table class="pl-table">
            <thead>
                <tr>
                    <th style="width: 90px;">Order ID</th>
                    <th style="width: 190px;">Date &amp; Time</th>
                    <th>Customer Name</th>
                    <th style="width: 80px; text-align: center;">Items</th>
                    <th style="width: 130px; text-align: right;">Total Amount</th>
                    <th style="width: 140px; text-align: center;">Status</th>
                    <th style="width: 90px; text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($arr)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 50px 20px; color: var(--pl-muted);">
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
                        <td style="color: #6d5b51; font-size: 13px; white-space: nowrap;">
                            <?= date('d M Y, h:i A', strtotime($o->datetime)) ?>
                        </td>

                        <!-- Member Name -->
                        <td>
                            <strong style="color: var(--pl-brown-dark); font-size: 14.5px;">
                                <?= htmlspecialchars($o->name) ?>
                            </strong>
                            <?php if ($o->is_deleted): ?>
                                <span style="font-size: 11px; color: #c0392b; font-weight: 700; background: #fdf2f2; padding: 2px 6px; border-radius: 4px; margin-left: 4px;" title="Account deactivated">Disabled</span>
                            <?php endif; ?>
                        </td>

                        <!-- Items Count -->
                        <td style="text-align: center; font-weight: 700; white-space: nowrap;">
                            <?= (int)$o->count ?>
                        </td>

                        <!-- Total (RM) -->
                        <td style="text-align: right; font-weight: 800; color: var(--pl-brown-dark); font-size: 14.5px; white-space: nowrap;">
                            RM <?= number_format((float)$o->total, 2) ?>
                        </td>

                        <!-- Status Badge -->
                        <td style="text-align: center; white-space: nowrap;">
                            <span class="pl-status-tag <?= $status_class ?>">
                                <?= htmlspecialchars($o->status) ?>
                            </span>
                        </td>

                        <!-- View Button -->
                        <td style="text-align: right; white-space: nowrap;">
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