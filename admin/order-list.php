<?php
include '../_base.php';

// ----------------------------------------------------------------------------

// (1) Authorization (admin)
auth('Admin');

// (2) Search keyword (order id, member name, or email)
$keyword = req('keyword');

// (3) TODO: return all orders, joined with user (name, email), filtered by keyword if present, newest first

// ----------------------------------------------------------------------------

$_title = 'Order | Listing (Admin)';
include '../_head.php';
?>

<form>
    <?= html_search('keyword') ?>
    <button>Search</button>
</form>

<p><?= count($arr) ?> record(s)</p>

<table class="table">
    <tr>
        <th>Id</th>
        <th>Datetime</th>
        <th>Member</th>
        <th>Count</th>
        <th>Total (RM)</th>
        <th>Status</th>
        <th></th>
    </tr>

    <?php foreach ($arr as $o): ?>
    <tr>
        <td><?= $o->id ?></td>
        <td><?= $o->datetime ?></td>
        <td><?= $o->name ?></td>
        <td class="right"><?= $o->count ?></td>
        <td class="right"><?= $o->total ?></td>
        <td><?= $o->status ?></td>
        <td>
            <button data-get="order-detail.php?id=<?= $o->id ?>">Detail</button>
        </td>
    </tr>
    <?php endforeach ?>
</table>

<?php
include '../_foot.php';