<?php
include '../_base.php';

// ----------------------------------------------------------------------------
auth('Member');

$id = req('id');

// Fetch order
$stm = $_db->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stm->execute([$id, $_user->id]);
$o = $stm->fetch();

if (!$o) {
    temp('info', 'Order not found.');
    redirect('history.php');
}

// Fetch order items joined with product
$stm = $_db->prepare("
    SELECT i.*, p.name, p.photo 
    FROM order_item i 
    JOIN product p ON i.product_id = p.id 
    WHERE i.order_id = ?
");
$stm->execute([$o->id]);
$arr = $stm->fetchAll();

// Fetch shipping address if delivered
$addr = null;
if (($o->delivery_method ?? '') === 'Delivery' && !empty($o->address_id)) {
    $stm = $_db->prepare("SELECT * FROM shipping_address WHERE id = ?");
    $stm->execute([$o->address_id]);
    $addr = $stm->fetch();
}

// 1. Calculate raw items subtotal directly from line items
$raw_items_subtotal = 0;
foreach ($arr as $item) {
    $raw_items_subtotal += (float)$item->subtotal;
}

// 2. Fetch specific voucher discount percentage if a code was used
$voucher_percent = 0;
$voucher_discount_amount = 0.00;

if (!empty($o->voucher_code)) {
    $stm = $_db->prepare("SELECT percent FROM voucher WHERE code = ?");
    $stm->execute([$o->voucher_code]);
    $v_data = $stm->fetch();
    
    if ($v_data) {
        $voucher_percent = (float)$v_data->percent;
        $voucher_discount_amount = round($raw_items_subtotal * ($voucher_percent / 100), 2);
    }
}

// 3. Separate points discount from voucher discount
$points_used_count = 0;
$points_discount_amount = 0.00;

if (isset($o->points_used) && (int)$o->points_used > 0) {
    $points_used_count = (int)$o->points_used;
    $points_discount_amount = round($points_used_count / 100, 2);
} elseif (!empty($o->discount)) {
    // If voucher was present, the remainder of total discount is from points
    $total_discount = (float)$o->discount;
    if ($voucher_discount_amount > 0) {
        $points_discount_amount = max(0, round($total_discount - $voucher_discount_amount, 2));
    } else {
        $points_discount_amount = $total_discount;
    }
    $points_used_count = (int)round($points_discount_amount * 100);
}

// If voucher discount couldn't be computed from percent, derive it directly
if (!empty($o->voucher_code) && $voucher_discount_amount <= 0 && (float)$o->discount > $points_discount_amount) {
    $voucher_discount_amount = round((float)$o->discount - $points_discount_amount, 2);
}

// ----------------------------------------------------------------------------
$_title = "Order #{$o->id} | Details";
include '../_head.php';
?>

<style>
/* =========================================================
   PULULU ORDER DETAIL MODERN UI/UX
   ========================================================= */
:root {
    --pl-primary: #cf7953;
    --pl-primary-hover: #b86440;
    --pl-brown-dark: #3e2619;
    --pl-text: #4a3b32;
    --pl-muted: #968377;
    --pl-border: #ebdcd5;
    --pl-card-bg: #ffffff;
    --pl-accent: #fbf5ef;
    
    --pl-status-pending-bg: #fff7e6;
    --pl-status-pending-color: #c07a16;
    --pl-status-pending-border: #fae0b8;

    --pl-status-processing-bg: #eaf3ff;
    --pl-status-processing-color: #1d68cd;
    --pl-status-processing-border: #c8e0ff;

    --pl-status-completed-bg: #eaf6ed;
    --pl-status-completed-color: #217d47;
    --pl-status-completed-border: #c6e9d0;

    --pl-status-cancelled-bg: #fdf2f2;
    --pl-status-cancelled-color: #c0392b;
    --pl-status-cancelled-border: #f8cfcf;
}

body {
    background-color: #faf5f0;
    color: var(--pl-text);
}

.pl-detail-wrap {
    max-width: 1040px;
    margin: 32px auto 80px;
    padding: 0 20px;
    box-sizing: border-box;
}

/* Breadcrumb */
.pl-breadcrumb {
    font-size: 13px;
    color: var(--pl-muted);
    margin-bottom: 22px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.pl-breadcrumb a {
    color: var(--pl-muted);
    text-decoration: none;
    transition: color 0.15s ease;
}
.pl-breadcrumb a:hover {
    color: var(--pl-primary);
}

/* Header */
.pl-detail-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 26px;
    flex-wrap: wrap;
    gap: 16px;
}
.pl-order-title-group h1 {
    font-size: 28px;
    font-weight: 800;
    color: var(--pl-brown-dark);
    margin: 0 0 6px;
}
.pl-order-meta-text {
    font-size: 13.5px;
    color: var(--pl-muted);
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Status Badges */
.pl-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 800;
    padding: 6px 16px;
    border-radius: 999px;
    border: 1px solid transparent;
}
.pl-status-badge::before {
    content: '';
    width: 7px;
    height: 7px;
    border-radius: 50%;
}
.pl-status-badge.completed {
    background: var(--pl-status-completed-bg);
    color: var(--pl-status-completed-color);
    border-color: var(--pl-status-completed-border);
}
.pl-status-badge.completed::before { background: var(--pl-status-completed-color); }

.pl-status-badge.pending,
.pl-status-badge.awaiting-payment {
    background: var(--pl-status-pending-bg);
    color: var(--pl-status-pending-color);
    border-color: var(--pl-status-pending-border);
}
.pl-status-badge.pending::before,
.pl-status-badge.awaiting-payment::before { background: var(--pl-status-pending-color); }

.pl-status-badge.processing {
    background: var(--pl-status-processing-bg);
    color: var(--pl-status-processing-color);
    border-color: var(--pl-status-processing-border);
}
.pl-status-badge.processing::before { background: var(--pl-status-processing-color); }

.pl-status-badge.cancelled {
    background: var(--pl-status-cancelled-bg);
    color: var(--pl-status-cancelled-color);
    border-color: var(--pl-status-cancelled-border);
}
.pl-status-badge.cancelled::before { background: var(--pl-status-cancelled-color); }

/* Main Grid Layout */
.pl-detail-grid {
    display: grid;
    grid-template-columns: 1.55fr 1fr;
    gap: 28px;
    align-items: start;
}

/* Card Container */
.pl-card-panel {
    background: var(--pl-card-bg);
    border: 1px solid var(--pl-border);
    border-radius: 20px;
    padding: 26px;
    box-shadow: 0 4px 18px rgba(62, 38, 25, 0.03);
    margin-bottom: 24px;
}
.pl-card-panel h2 {
    font-size: 17px;
    font-weight: 800;
    color: var(--pl-brown-dark);
    margin: 0 0 18px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f5ebe4;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Item Rows */
.pl-item-row {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 14px 0;
    border-bottom: 1px solid #f5ebe4;
}
.pl-item-row:last-child {
    border-bottom: none;
    padding-bottom: 0;
}
.pl-item-row:first-child {
    padding-top: 0;
}

.pl-item-thumb {
    width: 64px;
    height: 64px;
    border-radius: 12px;
    object-fit: cover;
    border: 1px solid var(--pl-border);
    background: #faf6f0;
    flex-shrink: 0;
}
.pl-item-info {
    flex: 1;
}
.pl-item-name {
    font-size: 15px;
    font-weight: 700;
    color: var(--pl-brown-dark);
    margin-bottom: 4px;
}
.pl-item-meta {
    font-size: 13px;
    color: var(--pl-muted);
}
.pl-item-total {
    text-align: right;
    font-size: 15px;
    font-weight: 800;
    color: var(--pl-brown-dark);
}

/* Fulfillment Block */
.pl-fulfill-box {
    background: var(--pl-accent);
    border: 1px solid var(--pl-border);
    border-radius: 14px;
    padding: 16px;
    margin-top: 8px;
    font-size: 13.5px;
    line-height: 1.55;
}
.pl-fulfill-box b {
    color: var(--pl-brown-dark);
}

/* Summary Pricing Box */
.pl-summary-line {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13.5px;
    color: var(--pl-text);
    margin-bottom: 11px;
}
.pl-summary-line.discount {
    color: #2b7a4b;
    font-weight: 700;
}
.pl-summary-divider {
    height: 1px;
    background: #f5ebe4;
    margin: 14px 0;
}
.pl-summary-total {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    font-size: 18px;
    font-weight: 800;
    color: var(--pl-brown-dark);
    margin-top: 14px;
}
.pl-total-amount {
    font-size: 24px;
    color: var(--pl-primary);
}

/* Points Pill */
.pl-points-badge {
    display: flex;
    align-items: center;
    gap: 6px;
    background: #fff8eb;
    border: 1px solid #fae1b3;
    color: #b7791f;
    padding: 10px 14px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 700;
    margin-top: 16px;
}

/* Action Buttons */
.pl-action-bar {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 24px;
}
.pl-btn-action {
    padding: 12px 22px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: none;
}
.pl-btn-back {
    background: #ffffff;
    color: var(--pl-brown-dark);
    border: 1.5px solid var(--pl-border);
}
.pl-btn-back:hover {
    background: var(--pl-accent);
    border-color: #d8c2b5;
}
.pl-btn-pay {
    background: var(--pl-primary);
    color: #ffffff;
    box-shadow: 0 4px 14px rgba(207, 121, 83, 0.25);
}
.pl-btn-pay:hover {
    background: var(--pl-primary-hover);
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(207, 121, 83, 0.35);
}
.pl-btn-cancel-order {
    background: #ffffff;
    color: var(--pl-status-cancelled-color);
    border: 1.5px solid #f5d6d6;
}
.pl-btn-cancel-order:hover {
    background: #fdf2f2;
    border-color: var(--pl-status-cancelled-color);
}

@media (max-width: 800px) {
    .pl-detail-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="pl-detail-wrap">
    <!-- Breadcrumb -->
    <div class="pl-breadcrumb">
        <a href="/">Home</a>
        <span>&rsaquo;</span>
        <a href="/user/profile.php">My Account</a>
        <span>&rsaquo;</span>
        <a href="history.php">Order History</a>
        <span>&rsaquo;</span>
        <span style="color: var(--pl-brown-dark); font-weight: 600;">Order #<?= htmlspecialchars($o->id) ?></span>
    </div>

    <!-- Header Section -->
    <?php
    $statusKey = strtolower(str_replace(' ', '-', trim((string)$o->status)));
    ?>
    <div class="pl-detail-header">
        <div class="pl-order-title-group">
            <h1>Order #<?= htmlspecialchars($o->id) ?></h1>
            <div class="pl-order-meta-text">
                <span>🗓️ <?= date('M d, Y', strtotime($o->datetime)) ?> &bull; <?= date('h:i A', strtotime($o->datetime)) ?></span>
                <span>&bull;</span>
                <span><?= (int)$o->count ?> total item(s)</span>
            </div>
        </div>

        <span class="pl-status-badge <?= $statusKey ?>">
            <?= htmlspecialchars($o->status) ?>
        </span>
    </div>

    <!-- Main Grid -->
    <div class="pl-detail-grid">
        <!-- Left: Ordered Bagels & Items -->
        <div class="pl-left-col">
            <div class="pl-card-panel">
                <h2>🥯 Items in this Order (<?= count($arr) ?>)</h2>
                
                <div class="pl-items-container">
                    <?php foreach ($arr as $i): ?>
                        <div class="pl-item-row">
                            <img src="/products/<?= htmlspecialchars($i->photo ?: 'default.jpg') ?>" 
                                 class="pl-item-thumb" 
                                 alt="<?= htmlspecialchars($i->name) ?>" 
                                 onerror="this.src='/products/default.jpg'">
                            
                            <div class="pl-item-info">
                                <div class="pl-item-name"><?= htmlspecialchars($i->name) ?></div>
                                <div class="pl-item-meta">
                                    RM <?= number_format((float)$i->price, 2) ?> &times; <?= (int)$i->unit ?>
                                </div>
                            </div>

                            <div class="pl-item-total">
                                RM <?= number_format((float)$i->subtotal, 2) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Fulfillment Details -->
            <div class="pl-card-panel">
                <h2>📦 Fulfillment Method</h2>
                <div class="pl-fulfill-box">
                    <?php if (($o->delivery_method ?? 'Pickup') === 'Delivery' && $addr): ?>
                        <div style="font-weight: 800; color: var(--pl-primary); margin-bottom: 6px;">
                            🚚 Doorstep Delivery
                        </div>
                        <div><b>Recipient:</b> <?= htmlspecialchars($addr->recipient_name) ?> (<?= htmlspecialchars($addr->phone) ?>)</div>
                        <div style="margin-top: 4px;">
                            <b>Address:</b> <?= htmlspecialchars($addr->address_line1) ?><?= !empty($addr->address_line2) ? ', ' . htmlspecialchars($addr->address_line2) : '' ?>,
                            <?= htmlspecialchars($addr->city) ?>, <?= htmlspecialchars($addr->state) ?> <?= htmlspecialchars($addr->postcode) ?>
                        </div>
                    <?php else: ?>
                        <div style="font-weight: 800; color: var(--pl-brown-dark); margin-bottom: 4px;">
                            🏪 Store Self-Pickup
                        </div>
                        <div>Collect fresh from <b>Pululu Bagel Bakery</b> (TAR UMT Block D, Kuala Lumpur).</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right: Order Payment Summary & Actions -->
        <div class="pl-right-col">
            <div class="pl-card-panel">
                <h2>Payment Summary</h2>

                <div class="pl-summary-line">
                    <span>Items Subtotal</span>
                    <span>RM <?= number_format($raw_items_subtotal, 2) ?></span>
                </div>

                <!-- 1. Dedicated Voucher Row -->
                <?php if (!empty($o->voucher_code) && $voucher_discount_amount > 0): ?>
                    <div class="pl-summary-line discount">
                        <span>Voucher (<?= htmlspecialchars($o->voucher_code) ?><?= $voucher_percent > 0 ? " &bull; {$voucher_percent}%" : '' ?>)</span>
                        <span>- RM <?= number_format($voucher_discount_amount, 2) ?></span>
                    </div>
                <?php endif; ?>

                <!-- 2. Points Used & Refund Note -->
                <?php if ($points_discount_amount > 0): ?>
                    <div class="pl-summary-line discount">
                        <span>
                            Points Redeemed (<?= number_format($points_used_count) ?> pts)
                            <?php if ($o->status === 'Cancelled'): ?>
                                <i style="font-size:11px;">(refunded)</i>
                            <?php endif ?>
                        </span>
                        <span>- RM <?= number_format($points_discount_amount, 2) ?></span>
                    </div>
                <?php endif; ?>

                <!-- 3. Delivery Fee -->
                <?php if (!empty($o->delivery_fee) && (float)$o->delivery_fee > 0): ?>
                    <div class="pl-summary-line">
                        <span>Delivery Fee</span>
                        <span>+ RM <?= number_format((float)$o->delivery_fee, 2) ?></span>
                    </div>
                <?php endif; ?>

                <div class="pl-summary-divider"></div>

                <div class="pl-summary-total">
                    <span>Total Amount</span>
                    <span class="pl-total-amount">RM <?= number_format((float)$o->total, 2) ?></span>
                </div>

                <?php if (!empty($o->points_earned) && $o->points_earned > 0): ?>
                    <div class="pl-points-badge">
                        <span>⭐</span>
                        <span>Earned <b>+<?= (int)$o->points_earned ?> points</b> from this purchase</span>
                    </div>

                <?php endif; ?>

                <!-- Action Button Group -->
                <div class="pl-action-bar">
                    <a href="history.php" class="pl-btn-action pl-btn-back">
                        &larr; Back to History
                    </a>

                    <?php if ($o->status === 'Awaiting Payment'): ?>
                        <a href="pay.php?id=<?= htmlspecialchars($o->id) ?>" class="pl-btn-action pl-btn-pay">
                            💳 Pay Now
                        </a>
                        <button type="button" 
                                class="pl-btn-action pl-btn-cancel-order" 
                                onclick="confirmCancelOrder('<?= htmlspecialchars($o->id) ?>')">
                            Cancel Order
                        </button>
                    <?php elseif ($o->status === 'Pending'): ?>
                        <button type="button" 
                                class="pl-btn-action pl-btn-cancel-order" 
                                onclick="confirmCancelOrder('<?= htmlspecialchars($o->id) ?>')">
                            Cancel Order
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function confirmCancelOrder(orderId) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Cancel Order?',
            text: `Are you sure you want to cancel Order #${orderId}? This cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#c0392b',
            cancelButtonColor: '#968377',
            confirmButtonText: 'Yes, Cancel Order',
            cancelButtonText: 'Keep Order',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                let form = document.createElement('form');
                form.method = 'POST';
                form.action = `cancel.php?id=${orderId}`;
                document.body.appendChild(form);
                form.submit();
            }
        });
    } else {
        if (confirm(`Are you sure you want to cancel Order #${orderId}?`)) {
            let form = document.createElement('form');
            form.method = 'POST';
            form.action = `cancel.php?id=${orderId}`;
            document.body.appendChild(form);
            form.submit();
        }
    }
}
</script>

<?php
include '../_foot.php';
?>