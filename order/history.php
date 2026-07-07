<?php
include '../_base.php';

// ----------------------------------------------------------------------------

// (1) Authorization (member)
// TODO

// (2) Return orders belong to the user (descending)
// TODO
$arr = [];

// ----------------------------------------------------------------------------

$_title = 'Order | History';
include '../_head.php';
?>

<!-- (B) EXTRA: CSS -->
<!-- TODO -->

<p>
    <button data-post="reset.php" data-confirm>Reset</button>
</p>

<p><?= count($arr) ?> record(s)</p>

<table class="table">
    <tr>
        <th>Id</th>
        <th>Datetime</th>
        <th>Count</th>
        <th>Total (RM)</th>
        <th></th>
    </tr>

    <?php foreach ($arr as $o): ?>
    <tr>
        <td><?= $o->id ?></td>
        <td><?= $o->datetime ?></td>
        <td class="right"><?= $o->count ?></td>
        <td class="right"><?= $o->total ?></td>
        <td>
            <button data-get="detail.php?id=<?= $o->id ?>">Detail</button>
            <!-- (A) EXTRA: Product photos -->
            <!-- TODO -->
        </td>
    </tr>
    <?php endforeach ?>
</table>

<?php
include '../_foot.php';