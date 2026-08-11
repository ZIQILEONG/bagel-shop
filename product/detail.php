<?php
include '../_base.php';
$id = req('id');
$p = null;
if ($id) {
    $stm = $_db->prepare("SELECT * FROM product WHERE id = ?");
    $stm->execute([$id]);
    $p = $stm->fetch();
}
if (!$p) {
    redirect('list.php');
}
$_title = $p->name . ' | Product';
include '../_head.php';
?>
<h1><?= $p->name ?></h1>
<img src="/products/<?= $p->photo ?>" width="200" height="200">
<p>Price: RM <?= number_format($p->price, 2) ?></p>
<p>Stock: <?= $p->stock ?></p>
<?php if ($_user?->role == 'Member'): ?>
<form method="post" action="list.php">
    <?= html_hidden('id') ?>
    <?= html_select('unit', $_units, '') ?>
    <button>Add to Cart</button>
</form>
<?php endif ?>
<p>
    <button data-get="list.php">Back to Menu</button>
</p>
<?php
include '../_foot.php';