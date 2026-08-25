<?php
include '../_base.php';

// ----------------------------------------------------------------------------

auth('Member');

$order_id = req('order_id');

temp('info', 'Payment was not completed. Your order is saved as "Awaiting Payment" - you can view it in your order history.');
redirect('history.php');