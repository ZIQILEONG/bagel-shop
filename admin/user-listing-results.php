<?php
// Included by user-listing.php for both normal and AJAX renders.
// Expects $pager, $arr, $search, $sort, $dir already set.
?>
<p><?= $pager->item_count ?> record(s)</p>
<form method="post">
<table class="table">
    <tr>
        <th></th>
        <th>Photo</th>
        <?= table_headers(['id' => 'Id', 'name' => 'Name', 'email' => 'Email', 'role' => 'Role', 'phone_no' => 'Phone No'], $sort, $dir, 'search=' . encode($search)) ?>
        <th></th>
    </tr>
    <?php foreach ($arr as $u): ?>
    <tr>
        <td><input type="checkbox" name="ids[]" value="<?= $u->id ?>" <?= $u->id == $_user->id ? 'disabled' : '' ?>></td>
        <td>
            <?php if (!empty($u->photo)): ?>
                <img src="../images/<?= encode($u->photo) ?>" width="50" height="50" style="object-fit: cover;">
            <?php else: ?>
                <img src="../images/photo.jpg" width="50" height="50" style="object-fit: cover;">
            <?php endif; ?>
        </td>
        <td><?= $u->id ?></td>
        <td><?= $u->name ?></td>
        <td><?= $u->email ?></td>
        <td><?= $u->role ?></td>
        <td><?= $u->phone_no ?></td>
        <td><button data-get="user-detail.php?id=<?= $u->id ?>">Detail</button></td>
    </tr>
    <?php endforeach ?>
</table>
<button name="btn" value="delete_selected" data-confirm="Delete selected members?">Delete selected members?</button>
</form>
<?= $pager->html('search=' . encode($search) . '&sort=' . $sort . '&dir=' . $dir) ?>