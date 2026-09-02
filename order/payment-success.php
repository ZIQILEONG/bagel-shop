<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config.php';
include '../_base.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ----------------------------------------------------------------------------

auth('Member');

$free_order_id = req('free_order');

if ($free_order_id) {
    $order_id = $free_order_id;
    $method = 'FREE (Voucher/Points)';
    $transaction_id = 'N/A';

    $stm = $_db->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stm->execute([$order_id, $_user->id]);
    $o = $stm->fetch();

    if (!$o) {
        redirect('history.php');
    }
}
else {
    \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

    $session_id = req('session_id');
    $session = \Stripe\Checkout\Session::retrieve($session_id);

    if ($session->payment_status !== 'paid') {
        redirect('payment-cancel.php');
    }

    $order_id = $session->metadata->order_id;

    $stm = $_db->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stm->execute([$order_id, $_user->id]);
    $o = $stm->fetch();

    if (!$o) {
        redirect('history.php');
    }

    $intent = \Stripe\PaymentIntent::retrieve($session->payment_intent);
    $method = strtoupper($intent->payment_method_types[0] ?? 'CARD');
    $transaction_id = $session->payment_intent;
}

if ($o->status !== 'Awaiting Payment') {
    redirect("detail.php?id=$order_id");
}

$_db->beginTransaction();

// Mark order as paid, award points earned on final amount paid
$points_earned = floor($o->total);

// update order status and set the earned points
$stm = $_db->prepare("UPDATE orders SET status = 'Pending', points_earned = ? WHERE id = ?");
$stm->execute([$points_earned, $order_id]);

// add points to user acc
$stm = $_db->prepare("UPDATE user SET points = points + ? WHERE id = ?");
$stm->execute([$points_earned, $_user->id]);

// Decrement stock for each item
$stm = $_db->prepare("SELECT * FROM order_item WHERE order_id = ?");
$stm->execute([$order_id]);
$items = $stm->fetchAll();

// Prevent stock from going below 0 and prevent variable collision
$update_stock_stm = $_db->prepare("UPDATE product SET stock = GREATEST(0, stock - ?) WHERE id = ?");
foreach ($items as $item) {
    $update_stock_stm->execute([$item->unit, $item->product_id]);
}

// Record payment
$stm = $_db->prepare("INSERT INTO payment (order_id, method, amount, status, transaction_id, datetime) VALUES (?, ?, ?, 'Paid', ?, NOW())");
$stm->execute([$order_id, $method, $o->total, $transaction_id]);

$_db->commit();

$_user->points += $points_earned;
$_SESSION['user'] = $_user;

// Send e-Receipt Email
try {
    // Fetch shipping address details if delivery
    $addr = null;
    if ($o->delivery_method === 'Delivery' && $o->address_id) {
        $stm = $_db->prepare("SELECT * FROM shipping_address WHERE id = ?");
        $stm->execute([$o->address_id]);
        $addr = $stm->fetch();
    }

    // calculate items subtotal
    $items_html = '';
    $raw_items_subtotal = 0;
    foreach ($items as $item) {
        $stm = $_db->prepare("SELECT name, photo FROM product WHERE id = ?");
        $stm->execute([$item->product_id]);
        $p = $stm->fetch();
        $prod_name = $p->name ?? "Bagel #{$item->product_id}";
        $raw_items_subtotal += (float)$item->subtotal;

        $items_html .= "
        <tr>
            <td style='padding: 12px 0; border-bottom: 1px solid #f2e7e1; font-size: 14px; color: #3e2619;'>
                <b>" . htmlspecialchars($prod_name) . "</b>
                <div style='font-size: 12px; color: #968377;'>Qty: {$item->unit} &times; RM " . number_format($item->price, 2) . "</div>
            </td>
            <td style='padding: 12px 0; border-bottom: 1px solid #f2e7e1; font-size: 14px; font-weight: bold; color: #3e2619; text-align: right;'>
                RM " . number_format($item->subtotal, 2) . "
            </td>
        </tr>";
    }

    if ($o->delivery_method === 'Delivery' && $addr) {
        $delivery_html = "
        <div style='background: #fbf5ef; border: 1px solid #ebdcd5; border-radius: 12px; padding: 16px; margin-top: 20px;'>
            <div style='font-size: 12px; font-weight: bold; text-transform: uppercase; color: #cf7953; margin-bottom: 6px;'>
                Doorstep Delivery Details
            </div>
            <div style='font-size: 13.5px; color: #3e2619; font-weight: bold;'>
                " . htmlspecialchars($addr->recipient_name) . " (" . htmlspecialchars($addr->phone) . ")
            </div>
            <div style='font-size: 13px; color: #6b584d; margin-top: 4px; line-height: 1.4;'>
                " . htmlspecialchars($addr->address_line1) . (!empty($addr->address_line2) ? ', ' . htmlspecialchars($addr->address_line2) : '') . "<br>
                " . htmlspecialchars($addr->city) . ", " . htmlspecialchars($addr->state) . " " . htmlspecialchars($addr->postcode) . "
            </div>
        </div>";
    } else {
        $delivery_html = "
        <div style='background: #fbf5ef; border: 1px solid #ebdcd5; border-radius: 12px; padding: 16px; margin-top: 20px;'>
            <div style='font-size: 12px; font-weight: bold; text-transform: uppercase; color: #cf7953; margin-bottom: 4px;'>
                Store Self-Pickup
            </div>
            <div style='font-size: 13px; color: #3e2619;'>
                Collect fresh from <b>Pululu Bagel Bakery</b> (TAR UMT Block D, Kuala Lumpur).
            </div>
        </div>";
    }

    // Calculate Voucher and Points Reductions
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

    $discount_rows_html = '';

    if (!empty($o->voucher_code) && $voucher_discount_amount > 0) {
        $discount_rows_html .= "
        <tr>
            <td style='padding: 6px 0; font-size: 13.5px; color: #2b7a4b;'>Voucher (" . htmlspecialchars($o->voucher_code) . ($voucher_percent > 0 ? " &bull; {$voucher_percent}%" : "") . ")</td>
            <td style='padding: 6px 0; font-size: 13.5px; color: #2b7a4b; text-align: right; font-weight: bold;'>- RM " . number_format($voucher_discount_amount, 2) . "</td>
        </tr>";
    }

    if ($points_discount_amount > 0) {
        $discount_rows_html .= "
        <tr>
            <td style='padding: 6px 0; font-size: 13.5px; color: #2b7a4b;'>Points Redeemed (" . number_format($points_used_count) . " pts)</td>
            <td style='padding: 6px 0; font-size: 13.5px; color: #2b7a4b; text-align: right; font-weight: bold;'>- RM " . number_format($points_discount_amount, 2) . "</td>
        </tr>";
    }

    if (!empty($o->delivery_fee) && (float)$o->delivery_fee > 0) {
        $discount_rows_html .= "
        <tr>
            <td style='padding: 6px 0; font-size: 13.5px; color: #6b584d;'>Delivery Fee</td>
            <td style='padding: 6px 0; font-size: 13.5px; color: #3e2619; text-align: right; font-weight: bold;'>+ RM " . number_format((float)$o->delivery_fee, 2) . "</td>
        </tr>";
    }

    $formatted_date = date('M d, Y', strtotime($o->datetime)) . ' &bull; ' . date('h:i A', strtotime($o->datetime));

    $html_body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Your Pululu Bagel Receipt</title>
    </head>
    <body style='margin: 0; padding: 0; background-color: #faf5f0; font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif;'>
        <table border='0' cellpadding='0' cellspacing='0' width='100%' style='background-color: #faf5f0; padding: 30px 15px;'>
            <tr>
                <td align='center'>
                    <!-- Main Card -->
                    <table border='0' cellpadding='0' cellspacing='0' width='100%' style='max-width: 580px; background-color: #ffffff; border-radius: 20px; overflow: hidden; border: 1px solid #ebdcd5; box-shadow: 0 4px 20px rgba(62, 38, 25, 0.05);'>
                        
                        <!-- Header Banner -->
                        <tr>
                            <td style='background-color: #cf7953; padding: 30px; text-align: center;'>
                                <h1 style='color: #ffffff; margin: 0; font-size: 24px; font-weight: 800; letter-spacing: -0.01em;'>Pululu Bagel</h1>
                                <p style='color: #fbf5ef; margin: 6px 0 0; font-size: 14px;'>Freshly baked with love &amp; care</p>
                            </td>
                        </tr>

                        <!-- Content Area -->
                        <tr>
                            <td style='padding: 30px;'>
                                <div style='font-size: 16px; font-weight: bold; color: #3e2619; margin-bottom: 4px;'>
                                    Hi " . htmlspecialchars($_user->name) . ",
                                </div>
                                <p style='font-size: 14px; color: #6b584d; margin: 0 0 20px; line-height: 1.5;'>
                                    Thank you for your order! Your payment has been confirmed and we are preparing your fresh batch of bagels.
                                </p>

                                <!-- Order Metadata Box -->
                                <table border='0' cellpadding='0' cellspacing='0' width='100%' style='background-color: #fdfaf7; border: 1px solid #ebdcd5; border-radius: 12px; padding: 14px 18px; margin-bottom: 22px;'>
                                    <tr>
                                        <td style='font-size: 13px; color: #968377;'>Order Reference:</td>
                                        <td style='font-size: 13px; font-weight: 800; color: #3e2619; text-align: right;'>#{$order_id}</td>
                                    </tr>
                                    <tr>
                                        <td style='font-size: 13px; color: #968377; padding-top: 4px;'>Date &amp; Time:</td>
                                        <td style='font-size: 13px; color: #3e2619; text-align: right; padding-top: 4px;'>{$formatted_date}</td>
                                    </tr>
                                    <tr>
                                        <td style='font-size: 13px; color: #968377; padding-top: 4px;'>Payment Method:</td>
                                        <td style='font-size: 13px; font-weight: bold; color: #cf7953; text-align: right; padding-top: 4px;'>{$method}</td>
                                    </tr>
                                </table>

                                <!-- Items List -->
                                <div style='font-size: 14px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #3e2619; margin-bottom: 8px;'>
                                    Ordered Items
                                </div>
                                <table border='0' cellpadding='0' cellspacing='0' width='100%' style='margin-bottom: 16px;'>
                                    {$items_html}
                                </table>

                                <!-- Summary Totals -->
                                <table border='0' cellpadding='0' cellspacing='0' width='100%' style='margin-top: 10px; border-top: 1.5px solid #ebdcd5; padding-top: 12px;'>
                                    <tr>
                                        <td style='padding: 6px 0; font-size: 13.5px; color: #6b584d;'>Items Subtotal</td>
                                        <td style='padding: 6px 0; font-size: 13.5px; color: #3e2619; text-align: right; font-weight: bold;'>RM " . number_format($raw_items_subtotal, 2) . "</td>
                                    </tr>
                                    {$discount_rows_html}
                                    <tr>
                                        <td style='padding: 12px 0 6px; font-size: 16px; font-weight: 800; color: #3e2619;'>Total Paid</td>
                                        <td style='padding: 12px 0 6px; font-size: 20px; font-weight: 800; color: #cf7953; text-align: right;'>RM " . number_format($o->total, 2) . "</td>
                                    </tr>
                                </table>

                                <!-- Reward Points Callout -->
                                <div style='background: #fff8eb; border: 1px solid #fae1b3; border-radius: 10px; padding: 10px 14px; margin-top: 14px; text-align: center; font-size: 13px; color: #b7791f; font-weight: bold;'>
                                    You earned +" . (int)$points_earned . " reward points from this purchase!
                                </div>

                                <!-- Fulfillment Info -->
                                {$delivery_html}

                                <!-- Action Button -->
                                <div style='text-align: center; margin-top: 30px;'>
                                    <a href='" . (defined('SITE_URL') ? SITE_URL : 'http://localhost:8000') . "/order/detail.php?id={$order_id}' 
                                       style='display: inline-block; background-color: #cf7953; color: #ffffff; text-decoration: none; padding: 13px 28px; border-radius: 12px; font-size: 14px; font-weight: bold; box-shadow: 0 4px 14px rgba(207, 121, 83, 0.25);'>
                                        View Order Status &rarr;
                                    </a>
                                </div>
                            </td>
                        </tr>

                        <!-- Footer -->
                        <tr>
                            <td style='background-color: #3e2619; padding: 24px; text-align: center;'>
                                <div style='font-size: 13px; color: #fbf5ef; font-weight: bold; margin-bottom: 4px;'>Pululu Bagel Bakery</div>
                                <div style='font-size: 12px; color: #c4b5ac; line-height: 1.4;'>TAR UMT Block D, Kuala Lumpur, Malaysia</div>
                                <div style='font-size: 11px; color: #968377; margin-top: 12px;'>This is an automated e-receipt for your recent order.</div>
                            </td>
                        </tr>

                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>";

    $mail = new PHPMailer(true);
    $mail->CharSet    = 'UTF-8';
    $mail->Encoding   = 'base64';
    $mail->isSMTP();
    $mail->Host       = MAIL_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = MAIL_USERNAME;
    $mail->Password   = MAIL_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom(MAIL_USERNAME, MAIL_FROM_NAME);
    $mail->addAddress($_user->email, $_user->name);
    $mail->Subject = "Your Pululu Bagel Receipt - Order #{$order_id}";

    $mail->isHTML(true);
    $mail->Body    = $html_body;
    $mail->AltBody = "Hi {$_user->name}, thank you for your order #{$order_id}. Total Paid: RM " . number_format($o->total, 2);

    $mail->send();
}
catch (Exception $e) {
    error_log("Receipt email failed: " . $mail->ErrorInfo);
}

// ----------------------------------------------------------------------------

temp('info', 'Payment successful! Your order has been placed.');
redirect("detail.php?id=$order_id");