<?php
include '../_base.php';

// ----------------------------------------------------------------------------

// (1) Authorization (member)
auth('Member');

// (2) Get order id, verify it belongs to this user AND is still Pending


// (3) Update status to 'Cancelled'


temp('info', 'Order cancelled successfully.');
redirect('detail.php?id=' . $id);