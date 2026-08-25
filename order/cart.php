<?php
include '../_base.php';

// ---------------------------------------------------------------------------
// when add to cart, check for login. If not login, popout error prompting login. --Chai
if (is_post()) {
    $btn = req('btn');
    $cart = get_cart();

    // Get currently selected cart items
    $checked_items = $_POST['checked_items'] ?? [];

    // Make sure all IDs are integers and remove duplicates
    $checked_items = array_values(
        array_unique(
            array_map('intval', $checked_items)
        )
    );

    // Remember selected items after page reload
    $_SESSION['cart_selected'] = $checked_items;

    // Remember reward point choice
    $_SESSION['cart_use_reward_points'] =
        isset($_POST['use_reward_points']);

    // Clear whole cart
    if ($btn == 'clear') {
        set_cart();

        // Also clear cart from database
        if ($_user) {
            save_cart_to_db($_user->id, $_db);
        }

        unset($_SESSION['cart_selected']);
        unset($_SESSION['cart_use_reward_points']);
        unset($_SESSION['checkout_cart']);
        unset($_SESSION['checkout_use_reward_points']);
        unset($_SESSION['voucher']);

        temp('info', 'Cart cleared.');
        redirect('?');
    }

    // Delete selected items
    if ($btn == 'delete_selected') {
        if (empty($checked_items)) {
            temp(
                'info',
                'Please select at least one item to delete.'
            );
            redirect('?');
        }

        foreach ($checked_items as $id) {
            update_cart($id, 0);
        }
        unset($_SESSION['cart_selected']);
        temp(
            'info',
            'Selected item(s) removed from cart.'
        );
        redirect('?');
    }

    // Apply voucher
    if ($btn == 'apply_voucher') {
        $code = strtoupper(
            trim(req('voucher_code'))
        );

        // Empty voucher box = remove voucher
        if ($code == '') {
            unset($_SESSION['voucher']);
            temp(
                'info',
                'Voucher removed.'
            );
            redirect('?');
        }

        $stm = $_db->prepare("
            SELECT *
            FROM voucher
            WHERE code = ?
        ");

        $stm->execute([$code]);
        $v = $stm->fetch();

        if (
            $v &&
            $v->active == 1 &&
            $v->expiry >= date('Y-m-d')
        ) {
            $_SESSION['voucher'] = [
                'code'    => $v->code,
                'percent' => (float)$v->percent
            ];
            temp(
                'info',
                'Voucher applied successfully!'
            );
        }
        else {
            unset($_SESSION['voucher']);
            temp(
                'info',
                'Invalid or expired voucher code.'
            );
        }
        redirect('?');
    }

    // Checkout selected products
    if ($btn == 'checkout_selected') {
        if (empty($checked_items)) {
            temp(
                'info',
                'Please select at least one item to checkout.'
            );
            redirect('?');
        }
        $checkout_cart = [];

        foreach ($checked_items as $id) {
            if (isset($cart[$id])) {
                $checkout_cart[$id] =
                    (int)$cart[$id];
            }
        }

        if (empty($checkout_cart)) {
            temp(
                'info',
                'The selected items are no longer available in your cart.'
            );
            redirect('?');
        }

        // Only selected products go to checkout
        $_SESSION['checkout_cart'] =
            $checkout_cart;

        // Remember whether customer wants reward points
        $_SESSION['checkout_use_reward_points'] =
            isset($_POST['use_reward_points']);
        $_SESSION['cart_selected'] =
            array_keys($checkout_cart);

        redirect('checkout.php');
    }

    // Update product quantity
    if ($btn == 'update_quantity') {

        $id   = (int)req('id');
        $unit = (int)req('unit');

        update_cart($id, $unit);
        redirect('?');
    }
    redirect('?');
}

// ----------------------------------------------------------------------------

$_title = 'Order | Shopping Cart';
include '../_head.php';

// Get current cart
$cart = get_cart();

// Current voucher
$voucher = $_SESSION['voucher'] ?? null;

// Items user selected previously
$selected_ids = array_map(
    'intval',
    $_SESSION['cart_selected'] ?? []
);

// User reward points
$user_points = max(
    0,
    (int)($_user->points ?? 0)
);

// Convert points to RM
$points_value = reward_points_value($user_points);

// Remember reward choice
$use_reward_points = $_SESSION['cart_use_reward_points'] ?? false;
?>

<style>
    .popup {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border-radius: 4px;
    }
    .right {
        text-align: right;
    }
    .cart-bulk-actions {
        margin-bottom: 15px;
        display: flex;
        gap: 15px;
        align-items: center;
    }
    .btn-delete-selected {
        background-color: #dc3545;
        color: white;
        border: none;
        padding: 6px 12px;
        border-radius: 4px;
        cursor: pointer;
    }
    .btn-delete-selected:hover {
        background-color: #c82333;
    }
    .item-checkbox, #select-all {
        width: 16px;
        height: 16px;
        cursor: pointer;
    }
</style>

<?php if (!$cart): ?>

    <!-- EMPTY CART -->
    <div style="
        text-align: center;
        padding: 50px 20px;
        margin: 30px 0;
        background: #fff8f0;
        border-radius: 12px;
    ">
        <h2>No items found in your cart</h2>
        <p>Your cart is currently empty.</p>
        <a href="/product/list.php">Continue Shopping</a>
    </div>

<?php else: ?>

<form method="post" id="cart-bulk-form">

    <!-- CART ACTION BUTTONS -->
    <div class="cart-bulk-actions">
        <label style="
            cursor: pointer;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 6px;
        ">
            <input
                type="checkbox"
                id="select-all"
            >Select All</label>

        <button
            type="submit"
            name="btn"
            value="delete_selected"
            class="btn-delete-selected"
            onclick="
                return confirm(
                    'Delete selected items from cart?'
                )
            ">🗑️ Delete Selected</button>

        <button
            type="submit"
            name="btn"
            value="clear"
            onclick="
                return confirm(
                    'Clear the whole cart?'
                )
            "
        > Clear Cart</button>
    </div>

    <!--  CART TABLE -->
    <table class="table">
        <tr>
            <th width="5%"></th>
            <th>Id</th>
            <th>Image</th>
            <th>Name</th>
            <th>Price (RM)</th>
            <th>Unit</th>
            <th>Subtotal (RM)</th>
        </tr>

        <?php
        $stm = $_db->prepare("
            SELECT *
            FROM product
            WHERE id = ?
        ");

        foreach ($cart as $id => $unit):
            $stm->execute([$id]);
            $p = $stm->fetch();

            // Product no longer exists
            if (!$p) {
                continue;
            }

            $unit = (int)$unit;

            $subtotal =
                (float)$p->price * $unit;

            // Check checkbox again after voucher/page reload
            $checked = in_array(
                (int)$p->id,
                $selected_ids,
                true
            );
        ?>

        <tr>
            <!-- CHECKBOX -->
            <td>
                <input
                    type="checkbox"
                    name="checked_items[]"
                    value="<?= $p->id ?>"
                    class="item-checkbox"
                    data-unit="<?= $unit ?>"
                    data-subtotal="<?=
                        number_format(
                            $subtotal,
                            2,
                            '.',
                            ''
                        )
                    ?>"
                    <?= $checked ? 'checked' : '' ?>>
            </td>

            <td>
                <?= $p->id ?>
            </td>

            <td>
                <img
                    src="/products/<?= $p->photo ?>"
                    class="popup">
            </td>

            <td>
                <?= $p->name ?>
            </td>

            <td class="right">
                <?= number_format(
                    $p->price,
                    2
                ) ?>
            </td>

            <!-- QUANTITY -->
            <td>
                <input
                    type="hidden"
                    class="row-id"
                    value="<?= $p->id ?>">
                <select class="qty-select">
                    <?php
                    for ($q = 1; $q <= 10; $q++):
                    ?>
                        <option
                            value="<?= $q ?>"
                            <?=
                            $q == $unit
                                ? 'selected'
                                : ''
                            ?>><?= $q ?>
                        </option>
                    <?php endfor ?>
                </select>
            </td>
            <td class="right">
                <?= number_format(
                    $subtotal,
                    2
                ) ?>
            </td>
        </tr>
        <?php endforeach ?>
    </table>

    <!-- CHECKOUT SUMMARY -->
    <div style="
        margin-top: 25px;
        padding: 22px;
        background: #fff8f0;
        border-radius: 12px;
    ">
        <h2>Checkout Summary</h2>

        <!-- SHOW WHEN NOTHING SELECTED -->
        <p id="no-selection-message">Select at least one item to see the total.</p>

        <!-- HIDDEN UNTIL ITEM SELECTED -->
        <div
            id="selected-summary"
            style="display: none;">
            <table class="table">
                <tr>
                    <th>Selected Items</th>
                    <td class="right"><span id="selected-count">0</span></td>
                </tr>

                <tr>
                    <th>Subtotal</th>
                    <td class="right">RM<span id="selected-subtotal">0.00</span></td>
                </tr>

                <?php if ($voucher): ?>
                    <tr>
                        <th>Voucher
                            (
                            <?= $voucher['code'] ?>
                            -
                            <?= $voucher['percent'] ?>%
                            )
                        </th>
                        <td class="right">- RM<span id="voucher-discount">0.00</span></td>
                    </tr>
                <?php endif ?>

                <!-- REWARD DISCOUNT ROW -->
                <tr
                    id="reward-discount-row"
                    style="display: none;">
                    <th>Reward Points</th>
                    <td class="right">- RM<span id="reward-discount">0.00</span></td>
                </tr>

                <tr>
                    <th>Total to Pay</th>
                    <td class="right">
                        <b>RM<span id="selected-final">0.00</span></b>
                    </td>
                </tr>
            </table>
        </div>

        <!-- VOUCHER -->
        <div style="margin-top: 20px;">
            <label>
                <b>Voucher Code</b>
            </label>
            <div style="
                display: flex;
                gap: 10px;
                margin-top: 8px;
            ">
                <input
                    type="text"
                    name="voucher_code"
                    value="<?=
                        $voucher['code']
                        ?? ''
                    ?>"
                    placeholder="Enter voucher code">
                <button
                    type="submit"
                    name="btn"
                    value="apply_voucher"
                >Apply Voucher
                </button>
            </div>
            <?php if ($voucher): ?>
                <p>
                    ✅ Voucher applied:
                    <?= $voucher['percent'] ?>%
                    off selected items
                </p>
            <?php endif ?>
        </div>

        <!-- REWARD POINTS -->
        <?php if ($_user?->role == 'Member'): ?>
            <div style="
                margin-top: 20px;
                padding-top: 15px;
                border-top: 1px solid #ddd;
            ">
                <h3>Reward Points</h3>
                <p>
                    You currently have
                    <b>
                        <?= number_format(
                            $user_points
                        ) ?>
                        points
                    </b>
                    worth
                    <b>
                        RM
                        <?= number_format(
                            $points_value,
                            2
                        ) ?>
                    </b>
                </p>

                <label style="
                    display: flex;
                    gap: 8px;
                    align-items: center;
                ">
                    <input
                        type="checkbox"
                        id="use_reward_points"
                        name="use_reward_points"
                        value="1"
                        <?= $use_reward_points
                            ? 'checked'
                            : ''
                        ?>
                        disabled
                    >
                    <span id="reward-choice-text">
                        Select items first to see
                        how many points can be used.
                    </span>
                </label>
                <small>
                    100 points = RM1.00.
                    Maximum
                    <?= REWARD_MAX_PERCENT ?>%
                    of the amount after voucher
                    can be deducted using
                    reward points.
                </small>
            </div>
        <?php endif ?>

        <!-- CHECKOUT BUTTON -->
        <div style="
            margin-top: 25px;
            text-align: right;
        ">
            <?php if ($_user?->role == 'Member'): ?>
                <button
                    type="submit"
                    name="btn"
                    value="checkout_selected"
                    id="checkout-selected-btn"
                    disabled
                >
                    Checkout Selected
                </button>
            <?php else: ?>
                Please
                <a href="/login.php">login</a>
                as member to checkout.
            <?php endif ?>
        </div>
    </div>
</form>
<?php endif ?>

<?php if ($cart): ?>

<script>
$(document).ready(function() {
    // Voucher percentage from PHP
    const voucherPercent =
        <?= $voucher
            ? (float)$voucher['percent']
            : 0
        ?>;

    // User available points
    const availablePoints =
        <?= (int)$user_points ?>;

    // 1 point = RM0.01
    const pointValue =
        <?= REWARD_POINT_VALUE ?>;

    // Maximum reward deduction percentage
    const rewardMaxPercent =
        <?= REWARD_MAX_PERCENT ?>;

    // SELECT ALL
    $('#select-all').on(
        'change',
        function() {

            $('.item-checkbox')
                .prop(
                    'checked',
                    this.checked
                );

            updateSelectedSummary();
        }
    );

    // INDIVIDUAL CHECKBOX
    $('.item-checkbox').on(
        'change',
        function() {

            let totalCheckboxes =
                $('.item-checkbox').length;

            let checkedCheckboxes =
                $('.item-checkbox:checked').length;

            $('#select-all').prop(
                'checked',
                totalCheckboxes > 0 &&
                totalCheckboxes === checkedCheckboxes
            );

            updateSelectedSummary();
        }
    );

    // REWARD POINT CHECKBOX
    $('#use_reward_points').on(
        'change',
        function() {
            updateSelectedSummary();
        }
    );

    // UPDATE QUANTITY
    $('.qty-select').on(
        'change',
        function() {

            const row =
                $(this).closest('tr');

            const productId =
                row
                .find('.row-id')
                .val();

            const selectedUnit =
                $(this).val();

            const form =
                $('#cart-bulk-form');

            // Tell PHP this is quantity update
            $('<input>')
                .attr({
                    type: 'hidden',
                    name: 'btn',
                    value: 'update_quantity'
                })
                .appendTo(form);

            $('<input>')
                .attr({
                    type: 'hidden',
                    name: 'id',
                    value: productId
                })
                .appendTo(form);

            $('<input>')
                .attr({
                    type: 'hidden',
                    name: 'unit',
                    value: selectedUnit
                })
                .appendTo(form);
            form.submit();
        }
    );

    // CALCULATE SELECTED ITEMS
    function updateSelectedSummary() {
        let selectedUnits = 0;
        let subtotal = 0;

        // Only calculate CHECKED items
        $('.item-checkbox:checked')
            .each(function() {

                selectedUnits +=
                    Number(
                        $(this).data('unit')
                    );

                subtotal +=
                    Number(
                        $(this).data('subtotal')
                    );
            });

        // NOTHING SELECTED
        if (selectedUnits <= 0) {
            $('#selected-summary').hide();
            $('#no-selection-message').show();
            $('#checkout-selected-btn')
                .prop(
                    'disabled',
                    true
                );

            $('#use_reward_points')
                .prop(
                    'checked',
                    false
                )
                .prop(
                    'disabled',
                    true
                );

            $('#reward-choice-text')
                .text(
                    'Select items first to see how many points can be used.'
                );

            $('#reward-discount-row')
                .hide();

            return;
        }

        // SOMETHING SELECTED
        $('#selected-summary').show();
        $('#no-selection-message').hide();
        $('#checkout-selected-btn')
            .prop(
                'disabled',
                false
            );

        // VOUCHER
        let voucherDiscount =
            subtotal *
            voucherPercent /
            100;

        voucherDiscount =
            Number(
                voucherDiscount.toFixed(2)
            );

        let afterVoucher =
            subtotal -
            voucherDiscount;

        afterVoucher =
            Math.max(
                0,
                Number(
                    afterVoucher.toFixed(2)
                )
            );

        // REWARD POINT LIMIT
        let maxRewardMoney =
            afterVoucher *
            rewardMaxPercent /
            100;

        maxRewardMoney =
            Number(
                maxRewardMoney.toFixed(2)
            );

        let maxPointsByOrder =
            Math.floor(
                maxRewardMoney /
                pointValue
            );

        let usablePoints =
            Math.min(
                availablePoints,
                maxPointsByOrder
            );

        usablePoints =
            Math.max(
                0,
                usablePoints
            );

        let usableRewardMoney =
            usablePoints *
            pointValue;

        usableRewardMoney =
            Number(
                usableRewardMoney.toFixed(2)
            );

        // UPDATE REWARD TEXT
        if ($('#use_reward_points').length) {
            if (usablePoints > 0) {
                $('#use_reward_points')
                    .prop(
                        'disabled',
                        false
                    );

                $('#reward-choice-text')
                    .text(
                        'Use ' +
                        usablePoints.toLocaleString() +
                        ' point(s) to save RM ' +
                        usableRewardMoney.toFixed(2)
                    );
            }
            else {
                $('#use_reward_points')
                    .prop(
                        'checked',
                        false
                    )
                    .prop(
                        'disabled',
                        true
                    );

                $('#reward-choice-text')
                    .text(
                        'No reward points can be used for this selection.'
                    );
            }
        }

        // REWARD DEDUCTION
        let rewardDiscount = 0;
        if (
            $('#use_reward_points')
                .is(':checked')
        ) {
            rewardDiscount =
                usableRewardMoney;
        }

        // FINAL TOTAL
        let finalTotal =
            afterVoucher -
            rewardDiscount;

        finalTotal =
            Math.max(
                0,
                Number(
                    finalTotal.toFixed(2)
                )
            );

        // SHOW VALUES
        $('#selected-count')
            .text(
                selectedUnits
            );

        $('#selected-subtotal')
            .text(
                subtotal.toFixed(2)
            );

        $('#voucher-discount')
            .text(
                voucherDiscount.toFixed(2)
            );

        $('#reward-discount')
            .text(
                rewardDiscount.toFixed(2)
            );

        $('#selected-final')
            .text(
                finalTotal.toFixed(2)
            );

        // Only show reward row when user chooses it
        if (rewardDiscount > 0) {
            $('#reward-discount-row')
                .show();
        }
        else {
            $('#reward-discount-row')
                .hide();
        }
    }

    // INITIAL PAGE LOAD
    let totalCheckboxes =
        $('.item-checkbox').length;

    let checkedCheckboxes =
        $('.item-checkbox:checked').length;

    $('#select-all').prop(
        'checked',
        totalCheckboxes > 0 &&
        totalCheckboxes === checkedCheckboxes
    );

    updateSelectedSummary();
});
</script>
<?php endif ?>

<?php
include '../_foot.php';