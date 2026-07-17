<?php
include '../_base.php';

// ----------------------------------------------------------------------------

if (is_post()) {
    $id   = req('id');
    $unit = req('unit');
    update_cart($id, $unit);
    redirect();
}

$id  = req('id');
$stm = $_db->prepare('SELECT * FROM product WHERE id = ?');
$stm->execute([$id]);
$p = $stm->fetch();
if (!$p) redirect('list.php');

// ----------------------------------------------------------------------------

$_title = 'Product | Detail';
include '../_head.php';
?>

<style>
    #photo {
        display: block;
        border: 1px solid #333;
        width: 200px;
        height: 200px;
    }
</style>

<p>
    <img src="/products/<?= $p->photo ?>" id="photo">
</p>

<table class="table detail">
    <tr>
        <th>Id</th>
        <td><?= $p->id ?></td>
    </tr>
    <tr>
        <th>Name</th>
        <td><?= $p->name ?></td>
    </tr>
    <tr>
        <th>Price</th>
        <td>RM <?= $p->price ?></td>
    </tr>
    <tr>
        <th>Unit</th>
        <td>
            <?php
            $cart = get_cart();
            $id   = $p->id;
            $unit = $cart[$p->id] ?? 0; 
            ?>
            <form method="post">
                <?= html_hidden('id') ?>
                <?= html_select('unit', $_units, '') ?>
                <?= $unit ? '✅' : '' ?>
            </form>
        </td>
    </tr>
</table>

<p>
    <button data-get="list.php">List</button>
</p>

<script>
    $('select').on('change', e => e.target.form.submit());
</script>

<?php
include '../_foot.php';