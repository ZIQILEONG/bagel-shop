<?php
include '../_base.php';

// ----------------------------------------------------------------------------

auth('Member');

$cart = $_SESSION['checkout_cart'] ?? get_cart();

if (empty($cart)) {
    redirect('cart.php');
}

if (is_post()) {
    $method = req('delivery_method');

    if ($method == 'Delivery') {
        $address_id = req('address_id');

        if (!$address_id) {
            $_err['address_id'] = 'Please select a delivery address.';
        }
        else {
            $stm = $_db->prepare("SELECT * FROM shipping_address WHERE id = ? AND user_id = ?");
            $stm->execute([$address_id, $_user->id]);
            if (!$stm->fetch()) {
                $_err['address_id'] = 'Invalid address selected.';
            }
        }
    }

    if (!$_err) {
        $_SESSION['delivery_method'] = $method;
        $_SESSION['address_id'] = ($method == 'Delivery') ? $address_id : null;
        redirect('checkout.php');
    }
}

// Calculate a quick subtotal preview for this page
$total = 0;
foreach ($cart as $product_id => $unit) {
    $stm = $_db->prepare("SELECT price FROM product WHERE id = ?");
    $stm->execute([$product_id]);
    $p = $stm->fetch();
    $total += $p->price * $unit;
}

$stm = $_db->prepare("SELECT * FROM shipping_address WHERE user_id = ? ORDER BY is_default DESC, id DESC");
$stm->execute([$_user->id]);
$addresses = $stm->fetchAll();

// ----------------------------------------------------------------------------

$_title = 'Order | Delivery Options';
include '../_head.php';
?>

<h2>How would you like to receive your order?</h2>

<form method="post" class="form">
    <label>
        <input type="radio" name="delivery_method" value="Pickup" checked onchange="toggleAddress()">
        Self Pickup (Free)
    </label>
    <br>
    <label>
        <input type="radio" name="delivery_method" value="Delivery" onchange="toggleAddress()">
        Delivery (+ RM7.00)
    </label>

    <div id="address-section" style="display:none; margin-top:10px;">
        <?php if (!$addresses): ?>
            <p>You have no saved addresses. <a href="/user/address-create.php">Add one first</a>.</p>
        <?php else: ?>
            <label>Select Delivery Address</label>
            <?php foreach ($addresses as $a): ?>
                <div>
                    <label>
                        <input type="radio" name="address_id" value="<?= $a->id ?>" <?= $a->is_default ? 'checked' : '' ?>>
                        <?= $a->recipient_name ?> - <?= $a->address_line1 ?>, <?= $a->city ?>, <?= $a->state ?> <?= $a->postcode ?>
                        <?php if ($a->is_default): ?><b>(Default)</b><?php endif ?>
                    </label>
                </div>
            <?php endforeach ?>
            <?= err('address_id') ?>
        <?php endif ?>
    </div>

    <p>Subtotal: RM <?= number_format($total, 2) ?></p>

    <button>Continue to Payment</button>
</form>

<script>
function toggleAddress() {
    let isDelivery = $('input[name="delivery_method"]:checked').val() == 'Delivery';
    $('#address-section').toggle(isDelivery);
}
$('form').on('submit', function(e) {
    let method = $('input[name="delivery_method"]:checked').val();
    if (method === 'Delivery') {
        let addressSelected = $('input[name="address_id"]:checked').length > 0;
        if (!addressSelected) {
            e.preventDefault();
            alert('Please select a shipping address before proceeding.');
        }
    }
});
</script>

<?php
include '../_foot.php';