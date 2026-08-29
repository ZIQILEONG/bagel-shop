<?php
include '../_base.php';

// ----------------------------------------------------------------------------

// (1) Authorization (admin)
auth('Admin');

$statuses = [
    'Pending'    => 'Pending',
    'Preparing'  => 'Preparing',
    'Ready'      => 'Ready',
    'Completed'  => 'Completed',
    'Cancelled'  => 'Cancelled',
];

// (2) Return order (based on id)
$id = req('id');

$stm = $_db->prepare("SELECT o.*, u.name, u.email, u.is_deleted FROM orders o JOIN user u ON o.user_id = u.id WHERE o.id = ?");
$stm->execute([$id]);
$o = $stm->fetch();

if (!$o) {
    temp('error', 'Order not found.');
    redirect('order-list.php');
}

// (3) Return items (and products) belong to the order
$stm = $_db->prepare("SELECT i.*, p.name, p.photo FROM order_item i JOIN product p ON i.product_id = p.id WHERE i.order_id = ?");
$stm->execute([$o->id]);
$arr = $stm->fetchAll();

// (4) Handle status update
if (is_post()) {
    if ($o->status == 'Cancelled') {
        temp('info', 'This order has been cancelled and cannot be updated.');
        redirect('order-detail.php?id=' . $o->id);
    }

    $status = req('status');

    if ($status == '') {
        $_err['status'] = 'Required';
    }
    else if (!array_key_exists($status, $statuses)) {
        $_err['status'] = 'Invalid value';
    }

    if (!$_err) {
        $oldStatus = $o->status;

        $stm = $_db->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stm->execute([$status, $o->id]);

        if ($status !== $oldStatus) {
            send_order_status_email($o, $status);
        }

        temp('info', 'Order status updated.');
        redirect('order-detail.php?id=' . $o->id);
    }
}

// ----------------------------------------------------------------------------

function send_order_status_email($order, $newStatus) {
    require_once '../PHPMailer-master/src/PHPMailer.php';
    require_once '../PHPMailer-master/src/SMTP.php';
    require_once '../PHPMailer-master/src/Exception.php';
    require_once '../config.php';

    $smtp_user = defined('SMTP_USERNAME') ? SMTP_USERNAME : (defined('MAIL_USERNAME') ? MAIL_USERNAME : null);
    $smtp_pass = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : (defined('MAIL_PASSWORD') ? MAIL_PASSWORD : null);
    $smtp_host = defined('MAIL_HOST') ? MAIL_HOST : 'smtp.gmail.com';

    if (!$smtp_user || !$smtp_pass) {
        return;
    }

    $status_config = [
        'Pending' => [
            'color'   => '#c07a16',
            'bg'      => '#fff7e6',
            'border'  => '#fae0b8',
            'message' => 'We have received your order and payment. Our bakers will begin preparing your bagels shortly.'
        ],
        'Preparing' => [
            'color'   => '#d97706',
            'bg'      => '#fdf3e7',
            'border'  => '#fcd34d',
            'message' => 'Great news! Your handcrafted bagels are currently being baked fresh in the oven.'
        ],
        'Ready' => [
            'color'   => '#1d68cd',
            'bg'      => '#eaf3ff',
            'border'  => '#c8e0ff',
            'message' => ($order->delivery_method === 'Delivery')
                ? 'Your bagels are packed and waiting for courier collection for doorstep delivery.'
                : 'Your fresh bagels are packed and ready for pickup at our bakery store!'
        ],
        'Completed' => [
            'color'   => '#217d47',
            'bg'      => '#eaf6ed',
            'border'  => '#c6e9d0',
            'message' => 'Your order is complete! We hope you enjoy your delicious bagels. See you again soon!'
        ],
        'Cancelled' => [
            'color'   => '#c0392b',
            'bg'      => '#fdf2f2',
            'border'  => '#f8cfcf',
            'message' => 'This order has been cancelled. If you believe this is an error, please reach out to our support.'
        ],
    ];

    $cfg = $status_config[$newStatus] ?? [
        'color'   => '#cf7953',
        'bg'      => '#fbf5ef',
        'border'  => '#ebdcd5',
        'message' => 'Your order status has been updated.'
    ];

    $site_url = defined('SITE_URL') ? SITE_URL : 'http://localhost:8000';
    $view_order_url = "{$site_url}/order/detail.php?id={$order->id}";

    $html_body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Order #{$order->id} Status Update</title>
    </head>
    <body style='margin: 0; padding: 0; background-color: #faf5f0; font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif;'>
        <table border='0' cellpadding='0' cellspacing='0' width='100%' style='background-color: #faf5f0; padding: 30px 15px;'>
            <tr>
                <td align='center'>
                    <table border='0' cellpadding='0' cellspacing='0' width='100%' style='max-width: 580px; background-color: #ffffff; border-radius: 20px; overflow: hidden; border: 1px solid #ebdcd5; box-shadow: 0 4px 20px rgba(62, 38, 25, 0.05);'>
                        
                        <!-- Header Banner -->
                        <tr>
                            <td style='background-color: #cf7953; padding: 28px 30px; text-align: center;'>
                                <h1 style='color: #ffffff; margin: 0; font-size: 24px; font-weight: 800; letter-spacing: -0.01em;'>Pululu Bagel</h1>
                                <p style='color: #fbf5ef; margin: 6px 0 0; font-size: 14px;'>Order Status Notification</p>
                            </td>
                        </tr>

                        <!-- Body Content -->
                        <tr>
                            <td style='padding: 32px 30px;'>
                                <div style='font-size: 16px; font-weight: bold; color: #3e2619; margin-bottom: 8px;'>
                                    Hi " . htmlspecialchars($order->name) . ",
                                </div>
                                <p style='font-size: 14px; color: #6b584d; margin: 0 0 24px; line-height: 1.5;'>
                                    Here is an update regarding your recent order <b>#{$order->id}</b>.
                                </p>

                                <!-- Status Highlight Box -->
                                <table border='0' cellpadding='0' cellspacing='0' width='100%' style='background-color: {$cfg['bg']}; border: 1.5px solid {$cfg['border']}; border-radius: 14px; padding: 20px; margin-bottom: 24px; text-align: center;'>
                                    <tr>
                                        <td>
                                            <div style='font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; color: #968377; margin-bottom: 6px;'>
                                                Current Status
                                            </div>
                                            <div style='display: inline-block; font-size: 19px; font-weight: 800; color: {$cfg['color']}; margin-bottom: 10px;'>
                                                {$newStatus}
                                            </div>
                                            <div style='font-size: 13.5px; color: #4a3b32; line-height: 1.5; max-width: 440px; margin: 0 auto;'>
                                                {$cfg['message']}
                                            </div>
                                        </td>
                                    </tr>
                                </table>

                                <!-- Order Summary Meta Box -->
                                <table border='0' cellpadding='0' cellspacing='0' width='100%' style='background-color: #fdfaf7; border: 1px solid #ebdcd5; border-radius: 12px; padding: 14px 18px; margin-bottom: 26px;'>
                                    <tr>
                                        <td style='font-size: 13px; color: #968377;'>Order Reference:</td>
                                        <td style='font-size: 13px; font-weight: 800; color: #3e2619; text-align: right;'>#{$order->id}</td>
                                    </tr>
                                    <tr>
                                        <td style='font-size: 13px; color: #968377; padding-top: 6px;'>Order Total:</td>
                                        <td style='font-size: 13px; font-weight: 800; color: #cf7953; text-align: right;'>RM " . number_format($order->total, 2) . "</td>
                                    </tr>
                                    <tr>
                                        <td style='font-size: 13px; color: #968377; padding-top: 6px;'>Fulfillment:</td>
                                        <td style='font-size: 13px; font-weight: bold; color: #3e2619; text-align: right; padding-top: 6px;'>" . htmlspecialchars($order->delivery_method ?? 'Pickup') . "</td>
                                    </tr>
                                </table>

                                <!-- CTA Button -->
                                <div style='text-align: center; margin-top: 10px;'>
                                    <a href='{$view_order_url}' 
                                       style='display: inline-block; background-color: #cf7953; color: #ffffff; text-decoration: none; padding: 13px 30px; border-radius: 12px; font-size: 14px; font-weight: bold; box-shadow: 0 4px 14px rgba(207, 121, 83, 0.25);'>
                                        View Full Order Details &rarr;
                                    </a>
                                </div>
                            </td>
                        </tr>

                        <!-- Footer -->
                        <tr>
                            <td style='background-color: #3e2619; padding: 24px; text-align: center;'>
                                <div style='font-size: 13px; color: #fbf5ef; font-weight: bold; margin-bottom: 4px;'>Pululu Bagel Bakery</div>
                                <div style='font-size: 12px; color: #c4b5ac; line-height: 1.4;'>TAR UMT Block D, Kuala Lumpur, Malaysia</div>
                                <div style='font-size: 11px; color: #968377; margin-top: 10px;'>You received this email because of your order at Pululu Bagel.</div>
                            </td>
                        </tr>

                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>";

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->CharSet    = 'UTF-8';
        $mail->Encoding   = 'base64';
        $mail->isSMTP();
        $mail->Host       = $smtp_host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_user;
        $mail->Password   = $smtp_pass;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom($smtp_user, 'Pululu Bagel');
        $mail->addAddress($order->email, $order->name);

        $mail->Subject = "Order #{$order->id} Status: {$newStatus}";
        $mail->isHTML(true);
        $mail->Body    = $html_body;
        $mail->AltBody = "Dear {$order->name},\n\nYour order #{$order->id} status has been updated to: {$newStatus}.\nOrder Total: RM " . number_format($order->total, 2) . "\n\nView details: {$view_order_url}";

        $mail->send();
    }
    catch (\Exception $e) {
        error_log('Order status email failed: ' . $e->getMessage());
    }
}

// Pre-calculate line item summary
$raw_items_subtotal = 0;
foreach ($arr as $item) {
    $raw_items_subtotal += (float)$item->subtotal;
}

// Fetch shipping address if delivered
$addr = null;
if (($o->delivery_method ?? '') === 'Delivery' && !empty($o->address_id)) {
    $stm = $_db->prepare("SELECT * FROM shipping_address WHERE id = ?");
    $stm->execute([$o->address_id]);
    $addr = $stm->fetch();
}

// Separate Voucher vs Points Discount
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

$points_used_count = 0;
$points_discount_amount = 0.00;

if (isset($o->points_used) && (int)$o->points_used > 0) {
    $points_used_count = (int)$o->points_used;
    $points_discount_amount = round($points_used_count / 100, 2);
} elseif (!empty($o->discount)) {
    $total_discount = (float)$o->discount;
    if ($voucher_discount_amount > 0) {
        $points_discount_amount = max(0, round($total_discount - $voucher_discount_amount, 2));
    } else {
        $points_discount_amount = $total_discount;
    }
    $points_used_count = (int)round($points_discount_amount * 100);
}

if (!empty($o->voucher_code) && $voucher_discount_amount <= 0 && (float)$o->discount > $points_discount_amount) {
    $voucher_discount_amount = round((float)$o->discount - $points_discount_amount, 2);
}

$_title = "Admin | Order #{$o->id} Details";
include '../_head.php';
?>

<style>
/* =========================================================
   PULULU ADMIN ORDER MANAGEMENT UI
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

    --pl-status-preparing-bg: #fdf3e7;
    --pl-status-preparing-color: #d97706;
    --pl-status-preparing-border: #fcd34d;

    --pl-status-ready-bg: #eaf3ff;
    --pl-status-ready-color: #1d68cd;
    --pl-status-ready-border: #c8e0ff;

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

.pl-admin-wrap {
    max-width: 1140px;
    margin: 28px auto 80px;
    padding: 0 20px;
    box-sizing: border-box;
}

/* Breadcrumbs */
.pl-admin-breadcrumb {
    font-size: 13px;
    color: var(--pl-muted);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.pl-admin-breadcrumb a {
    color: var(--pl-muted);
    text-decoration: none;
    transition: color 0.15s ease;
}
.pl-admin-breadcrumb a:hover {
    color: var(--pl-primary);
}

/* Admin Title Header */
.pl-admin-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 24px;
}
.pl-admin-header h1 {
    font-size: 26px;
    font-weight: 800;
    color: var(--pl-brown-dark);
    margin: 0 0 6px;
}
.pl-admin-meta {
    font-size: 13.5px;
    color: var(--pl-muted);
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Status Badges */
.pl-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 800;
    padding: 6px 16px;
    border-radius: 999px;
    border: 1px solid transparent;
}
.pl-badge::before {
    content: '';
    width: 7px;
    height: 7px;
    border-radius: 50%;
}
.pl-badge.pending { background: var(--pl-status-pending-bg); color: var(--pl-status-pending-color); border-color: var(--pl-status-pending-border); }
.pl-badge.pending::before { background: var(--pl-status-pending-color); }

.pl-badge.preparing { background: var(--pl-status-preparing-bg); color: var(--pl-status-preparing-color); border-color: var(--pl-status-preparing-border); }
.pl-badge.preparing::before { background: var(--pl-status-preparing-color); }

.pl-badge.ready { background: var(--pl-status-ready-bg); color: var(--pl-status-ready-color); border-color: var(--pl-status-ready-border); }
.pl-badge.ready::before { background: var(--pl-status-ready-color); }

.pl-badge.completed { background: var(--pl-status-completed-bg); color: var(--pl-status-completed-color); border-color: var(--pl-status-completed-border); }
.pl-badge.completed::before { background: var(--pl-status-completed-color); }

.pl-badge.cancelled { background: var(--pl-status-cancelled-bg); color: var(--pl-status-cancelled-color); border-color: var(--pl-status-cancelled-border); }
.pl-badge.cancelled::before { background: var(--pl-status-cancelled-color); }

/* Layout Grid */
.pl-admin-grid {
    display: grid;
    grid-template-columns: 1.6fr 1fr;
    gap: 28px;
    align-items: start;
}

/* Panel Cards */
.pl-admin-card {
    background: var(--pl-card-bg);
    border: 1px solid var(--pl-border);
    border-radius: 20px;
    padding: 24px;
    box-shadow: 0 4px 18px rgba(62, 38, 25, 0.03);
    margin-bottom: 24px;
}
.pl-admin-card h2 {
    font-size: 16.5px;
    font-weight: 800;
    color: var(--pl-brown-dark);
    margin: 0 0 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f5ebe4;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Status Update Widget */
.pl-status-form {
    display: flex;
    gap: 12px;
    align-items: flex-start;
}
.pl-status-select {
    flex: 1;
    padding: 10px 14px;
    border: 1.5px solid var(--pl-border);
    border-radius: 10px;
    font-size: 14px;
    font-weight: 700;
    color: var(--pl-brown-dark);
    background: #fff;
    outline: none;
    cursor: pointer;
}
.pl-status-select:focus {
    border-color: var(--pl-primary);
}
.pl-btn-update {
    background: var(--pl-brown-dark);
    color: #ffffff;
    border: none;
    padding: 10px 20px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.15s ease;
}
.pl-btn-update:hover {
    background: #23130a;
}

/* Items Table */
.pl-table-items {
    width: 100%;
    border-collapse: collapse;
}
.pl-table-items th {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--pl-muted);
    text-align: left;
    padding-bottom: 10px;
    border-bottom: 1.5px solid #f2e7e1;
}
.pl-table-items td {
    padding: 14px 0;
    border-bottom: 1px solid #f7eeea;
    font-size: 13.5px;
    vertical-align: middle;
}
.pl-table-items tr:last-child td {
    border-bottom: none;
}
.pl-prod-flex {
    display: flex;
    align-items: center;
    gap: 12px;
}
.pl-table-thumb {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    object-fit: cover;
    border: 1px solid var(--pl-border);
    background: #faf6f0;
}
.pl-prod-name {
    font-weight: 700;
    color: var(--pl-brown-dark);
}
.pl-prod-id {
    font-size: 11.5px;
    color: var(--pl-muted);
}

/* Details List in Sidebar */
.pl-info-grid {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.pl-info-row {
    display: flex;
    justify-content: space-between;
    font-size: 13.5px;
    line-height: 1.45;
}
.pl-info-label {
    color: var(--pl-muted);
}
.pl-info-val {
    color: var(--pl-brown-dark);
    font-weight: 600;
    text-align: right;
}

/* Summary Pricing Box */
.pl-summary-line {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13.5px;
    color: var(--pl-text);
    margin-bottom: 10px;
}
.pl-summary-line.discount {
    color: #2b7a4b;
    font-weight: 700;
}
.pl-summary-divider {
    height: 1px;
    background: #f5ebe4;
    margin: 12px 0;
}
.pl-summary-total {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    font-size: 17px;
    font-weight: 800;
    color: var(--pl-brown-dark);
    margin-top: 12px;
}
.pl-total-amount {
    font-size: 22px;
    color: var(--pl-primary);
}

/* Delivery Callout */
.pl-delivery-box {
    background: var(--pl-accent);
    border: 1px solid var(--pl-border);
    border-radius: 12px;
    padding: 14px;
    font-size: 13px;
    line-height: 1.5;
    margin-top: 8px;
}

/* Back Button */
.pl-btn-back-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #ffffff;
    color: var(--pl-brown-dark);
    border: 1.5px solid var(--pl-border);
    padding: 10px 18px;
    border-radius: 10px;
    font-size: 13.5px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.15s ease;
}
.pl-btn-back-link:hover {
    background: var(--pl-accent);
    border-color: #d8c2b5;
}

@media (max-width: 860px) {
    .pl-admin-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="pl-admin-wrap">
    <!-- Breadcrumb -->
    <div class="pl-admin-breadcrumb">
        <a href="/">Home</a>
        <span>&rsaquo;</span>
        <a href="order-list.php">Order Management</a>
        <span>&rsaquo;</span>
        <span style="color: var(--pl-brown-dark); font-weight: 600;">Order #<?= htmlspecialchars($o->id) ?></span>
    </div>

    <!-- Admin Header -->
    <?php $statusKey = strtolower(str_replace(' ', '-', trim((string)$o->status))); ?>
    <div class="pl-admin-header">
        <div>
            <h1>Order #<?= htmlspecialchars($o->id) ?></h1>
            <div class="pl-admin-meta">
                <span>🗓️ <?= date('M d, Y', strtotime($o->datetime)) ?> &bull; <?= date('h:i A', strtotime($o->datetime)) ?></span>
                <span>&bull;</span>
                <span><?= (int)$o->count ?> item(s)</span>
            </div>
        </div>

        <span class="pl-badge <?= $statusKey ?>">
            <?= htmlspecialchars($o->status) ?>
        </span>
    </div>

    <div class="pl-admin-grid">
        <!-- Left: Ordered Bagels & Status Management -->
        <div class="pl-left-col">
            <!-- Status Update Card -->
            <div class="pl-admin-card">
                <h2>⚙️ Update Order Status</h2>
                <?php if ($o->status !== 'Cancelled'): ?>
                    <form method="post" class="pl-status-form">
                        <select name="status" class="pl-status-select">
                            <?php foreach ($statuses as $val => $lbl): ?>
                                <option value="<?= htmlspecialchars($val) ?>" <?= ($o->status === $val) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($lbl) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="pl-btn-update">Update Status</button>
                    </form>
                    <?= err('status') ?>
                <?php else: ?>
                    <div style="background: var(--pl-status-cancelled-bg); color: var(--pl-status-cancelled-color); border: 1px solid var(--pl-status-cancelled-border); padding: 12px 16px; border-radius: 10px; font-size: 13.5px; font-weight: 700;">
                        ⚠️ This order has been cancelled and its status is locked.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Itemized Table Card -->
            <div class="pl-admin-card">
                <h2>Line Items (<?= count($arr) ?>)</h2>
                <table class="pl-table-items">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th style="text-align: right;">Price</th>
                            <th style="text-align: center;">Qty</th>
                            <th style="text-align: right;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($arr as $i): ?>
                            <tr>
                                <td>
                                    <div class="pl-prod-flex">
                                        <img src="/products/<?= htmlspecialchars($i->photo ?: 'default.jpg') ?>" 
                                             class="pl-table-thumb" 
                                             alt="<?= htmlspecialchars($i->name) ?>" 
                                             onerror="this.src='/products/default.jpg'">
                                        <div>
                                            <div class="pl-prod-name"><?= htmlspecialchars($i->name) ?></div>
                                            <div class="pl-prod-id">ID: <?= htmlspecialchars($i->product_id) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td style="text-align: right; color: var(--pl-muted);">
                                    RM <?= number_format((float)$i->price, 2) ?>
                                </td>
                                <td style="text-align: center; font-weight: 700;">
                                    &times; <?= (int)$i->unit ?>
                                </td>
                                <td style="text-align: right; font-weight: 800; color: var(--pl-brown-dark);">
                                    RM <?= number_format((float)$i->subtotal, 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <p style="margin-top: 10px;">
                <a href="order-list.php" class="pl-btn-back-link">&larr; Back to Order Listing</a>
            </p>
        </div>

        <!-- Right: Customer & Financial Summary -->
        <div class="pl-right-col">
            <!-- Customer Information Card -->
            <div class="pl-admin-card">
                <h2>👤 Customer Details</h2>
                <div class="pl-info-grid">
                    <div class="pl-info-row">
                        <span class="pl-info-label">Name</span>
                        <span class="pl-info-val">
                            <?= htmlspecialchars($o->name) ?>
                            <?php if (!empty($o->is_deleted)): ?>
                                <span style="color:#b5192b; font-weight:bold;" title="Account Disabled">⚠️ (Disabled)</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="pl-info-row">
                        <span class="pl-info-label">Email</span>
                        <span class="pl-info-val"><a href="mailto:<?= htmlspecialchars($o->email) ?>" style="color: var(--pl-primary); text-decoration: none;"><?= htmlspecialchars($o->email) ?></a></span>
                    </div>
                    <div class="pl-info-row">
                        <span class="pl-info-label">Fulfillment</span>
                        <span class="pl-info-val"><?= htmlspecialchars($o->delivery_method ?? 'Pickup') ?></span>
                    </div>
                </div>

                <!-- Delivery Address / Pickup info -->
                <?php if (($o->delivery_method ?? 'Pickup') === 'Delivery' && $addr): ?>
                    <div class="pl-delivery-box">
                        <div style="font-weight: 800; color: var(--pl-primary); margin-bottom: 4px;">🚚 Deliver to:</div>
                        <div><b><?= htmlspecialchars($addr->recipient_name) ?></b> (<?= htmlspecialchars($addr->phone) ?>)</div>
                        <div style="color: var(--pl-muted); margin-top: 2px;">
                            <?= htmlspecialchars($addr->address_line1) ?><?= !empty($addr->address_line2) ? ', ' . htmlspecialchars($addr->address_line2) : '' ?><br>
                            <?= htmlspecialchars($addr->city) ?>, <?= htmlspecialchars($addr->state) ?> <?= htmlspecialchars($addr->postcode) ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="pl-delivery-box">
                        <div style="font-weight: 800; color: var(--pl-brown-dark);">🏪 Self Pickup</div>
                        <div style="color: var(--pl-muted);">Customer will collect at TAR UMT Block D.</div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Financial Summary Card -->
            <div class="pl-admin-card">
                <h2>💳 Payment Summary</h2>

                <div class="pl-summary-line">
                    <span>Items Subtotal</span>
                    <span>RM <?= number_format($raw_items_subtotal, 2) ?></span>
                </div>

                <!-- 1. Voucher Discount -->
                <?php if (!empty($o->voucher_code) && $voucher_discount_amount > 0): ?>
                    <div class="pl-summary-line discount">
                        <span>Voucher (<?= htmlspecialchars($o->voucher_code) ?><?= $voucher_percent > 0 ? " &bull; {$voucher_percent}%" : '' ?>)</span>
                        <span>- RM <?= number_format($voucher_discount_amount, 2) ?></span>
                    </div>
                <?php endif; ?>

                <!-- 2. Points Redeemed -->
                <?php if ($points_discount_amount > 0): ?>
                    <div class="pl-summary-line discount">
                        <span>Points Redeemed (<?= number_format($points_used_count) ?> pts)</span>
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
                    <span>Total Paid</span>
                    <span class="pl-total-amount">RM <?= number_format((float)$o->total, 2) ?></span>
                </div>

                <?php if (!empty($o->points_earned) && (int)$o->points_earned > 0): ?>
                    <div style="margin-top: 14px; font-size: 12.5px; color: #b7791f; font-weight: 700; background: #fff8eb; border: 1px solid #fae1b3; padding: 8px 12px; border-radius: 8px; text-align: center;">
                        ⭐ Points Awarded: +<?= (int)$o->points_earned ?> pts
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
include '../_foot.php';
?>