<?php
include '../_base.php';
require '../lib/SimplePager.php';
auth('Admin');

// ========= CSV upload logic =========
if (is_post() && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        temp('error', "Please choose CSV file.");
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            temp('error', "Only CSV file allowed.");
        } else {
            $handle = fopen($file['tmp_name'], 'r');
            if (!$handle) {
                temp('error', "Cannot read file");
            } else {
                $_db->beginTransaction();
                $count = 0;
                $skipHeader = true;
                try {
                    while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                        if ($skipHeader) {
                            $skipHeader = false;
                            continue;
                        }
                        [$id, $name, $price, $photo, $description, $stock, $category_id] = $row;
                        $stm = $_db->prepare("INSERT INTO product(id,name,price,photo,description,stock,category_id) VALUES (?,?,?,?,?,?,?)");
                        $stm->execute([$id, $name, $price, $photo, $description, $stock, $category_id]);
                        $count++;
                    }
                    fclose($handle);
                    $_db->commit();
                    temp('info', "✅ Import success, total {$count} products added.");
                } catch (Exception $e) {
                    $_db->rollBack();
                    fclose($handle);
                    temp('error', "❌ Import failed: " . $e->getMessage());
                }
            }
        }
    }
    redirect(app_url('admin/product-listing.php'));
}

// ========= Batch Operations =========
if (is_post() && req('btn') == 'delete_selected') {
    $ids = post('ids', []);
    if (is_array($ids) && count($ids) > 0) {
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $stm = $_db->prepare("DELETE FROM product WHERE id IN ($in)");
        $stm->execute($ids);
        temp('info', count($ids) . ' product(s) deleted.');
    }
    redirect('product-listing.php');
}

if (is_post() && req('btn') == 'increase_price') {
    $ids     = post('ids', []);
    $percent = req('percent');
    if (is_array($ids) && count($ids) > 0 && is_numeric($percent) && $percent >= -100) {
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $stm = $_db->prepare("UPDATE product SET price = ROUND(price * (1 + ?/100), 2) WHERE id IN ($in)");
        $stm->execute(array_merge([$percent], $ids));
        $action = $percent < 0 ? 'decreased' : 'increased';
        temp('info', count($ids) . ' product(s) price ' . $action . ' by ' . abs($percent) . '%.');
    }
    redirect('product-listing.php');
}

// ========= Query & Pagination =========
$search = get('search', '');
$sort   = get('sort', 'p.id');
$dir    = get('dir', 'asc') == 'desc' ? 'desc' : 'asc';
$page   = get('page', '1');

$sorts = ['p.id', 'c.name', 'p.name', 'p.price', 'p.stock'];
if (!in_array($sort, $sorts)) {
    $sort = 'p.id';
}

$where  = '';
$params = [];
if ($search != '') {
    $where = 'WHERE p.name LIKE ?';
    $params[] = "%$search%";
}

$query = "SELECT p.*, c.name as category_name FROM product p LEFT JOIN category c ON p.category_id = c.id $where ORDER BY $sort $dir";
$pager = new SimplePager($query, $params, '10', $page);
$arr   = $pager->result;

if (get('ajax') == '1') {
    include 'product-listing-results.php';
    exit();
}

$_title = 'Product | Listing (Admin)';
include '../_head.php';
?>

<link rel="stylesheet" href="<?= app_url('css/admin-product-listing.css') ?>">

<div class="pl-prod-container">
    <div class="pl-crumb">
        <a href="/">Home</a>
        <span>&rsaquo;</span>
        <span class="il-19-e0e1a0">Products</span>
    </div>

    <div class="pl-header">
        <div>
            <h1>Product Management</h1>
            <p>Organize bakery inventory, adjust pricing, and batch-import bagels.</p>
        </div>
    </div>

    <div class="pl-action-panel">
        <div class="pl-search-row">
            <form method="get" class="form" id="searchForm">
                <?= html_search('search', 'placeholder="Search bagels by name..." autocomplete="off"') ?>
                <?= html_hidden('sort') ?>
                <?= html_hidden('dir') ?>
                <button type="submit">Search</button>
            </form>
        </div>

        <div class="pl-tools-row">
            <a class="il-39-c6ab9b" href="product-create.php">
                <button type="button" class="btn-add">✨ Add New Product</button>
            </a>

            <form method="post" enctype="multipart/form-data" class="pl-csv-group il-40-1c244e">
                <label class="il-41-778c85">
                    <input class="il-42-c29ae3" type="file" name="csv_file" accept=".csv" required
                          >
                    <button type="button" class="pl-csv-btn">📂 Choose CSV</button>
                </label>

                <span id="selectedCsvName">No file chosen</span>

                <button type="submit" class="pl-csv-upload-btn">📥 Upload CSV</button>
            </form>
        </div>
    </div>

    <div id="resultsWrap">
        <?php include 'product-listing-results.php'; ?>
    </div>
</div>

<script src="../js/product-listing.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function(){
    const csvInput = document.querySelector('input[name="csv_file"]');
    const nameDisplay = document.getElementById('selectedCsvName');
    if (csvInput && nameDisplay) {
        csvInput.addEventListener('change', function(){
            if(this.files.length > 0){
                nameDisplay.textContent = this.files[0].name;
            }else{
                nameDisplay.textContent = "No file chosen";
            }
        });
    }
});
</script>

<?php
include '../_foot.php';
?>