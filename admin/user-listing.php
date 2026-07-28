<?php
include '../_base.php';

// ----------------------------------------------------------------------------

// (1) Authorization (admin)
auth('Admin');

// (2) Return all members
$stm = $_db->prepare("SELECT * FROM user ORDER BY id");
$stm->execute([]);
$arr = $stm->fetchAll();

// ----------------------------------------------------------------------------

$_title = 'Member | Listing (Admin)';
include '../_head.php';
?>

<p><?= count($arr) ?> record(s)</p>

<p>
    <button data-get="user-detail.php">Create New Member</button>
</p>

<table class="table">
    <tr>
        <th>Photo</th>
        <th>Id</th>
        <th>Name</th>
        <th>Email</th>
        <th>Role</th>
        <th>Phone No</th>
        <th></th>
    </tr>

    <?php foreach ($arr as $u): ?>
    <tr>
        <td><img src="/photos/<?= $u->photo ?>" width="50" height="50"></td>
        <td><?= $u->id ?></td>
        <td><?= $u->name ?></td>
        <td><?= $u->email ?></td>
        <td><?= $u->role ?></td>
        <td><?= $u->phone_no ?></td>
        <td>
            <button data-get="user-detail.php?id=<?= $u->id ?>">Detail</button>
        </td>
    </tr>
    <?php endforeach ?>
</table>

<?php
include '../_foot.php';