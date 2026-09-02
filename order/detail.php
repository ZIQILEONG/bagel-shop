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

<link rel="stylesheet" href="<?= app_url('css/order-detail.css') ?>">

<div class="pl-detail-wrap">
    <!-- Breadcrumb -->
    <div class="pl-breadcrumb">
        <a href="/">Home</a>
        <span>&rsaquo;</span>
        <a href="/user/profile.php">My Account</a>
        <span>&rsaquo;</span>
        <a href="history.php">Order History</a>
        <span>&rsaquo;</span>
        <span class="il-4-8a27e5">Order #<?= htmlspecialchars($o->id) ?></span>
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
                        <div class="il-78-f4aa0a">
                            🚚 Doorstep Delivery
                        </div>
                        <div><b>Recipient:</b> <?= htmlspecialchars($addr->recipient_name) ?> (<?= htmlspecialchars($addr->phone) ?>)</div>
                        <div class="il-79-07c6a7">
                            <b>Address:</b> <?= htmlspecialchars($addr->address_line1) ?><?= !empty($addr->address_line2) ? ', ' . htmlspecialchars($addr->address_line2) : '' ?>,
                            <?= htmlspecialchars($addr->city) ?>, <?= htmlspecialchars($addr->state) ?> <?= htmlspecialchars($addr->postcode) ?>
                        </div>
                    <?php else: ?>
                        <div class="il-80-4ec524">
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
                                <i class="il-81-8b6852">(refunded)</i>
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