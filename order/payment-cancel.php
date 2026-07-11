<?php
include '../_base.php';

// ----------------------------------------------------------------------------

auth('Member');

temp('info', 'Payment was cancelled. Your cart is still saved.');
redirect('cart.php');