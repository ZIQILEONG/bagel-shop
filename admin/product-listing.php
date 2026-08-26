<?php
include '../_base.php';
require '../lib/SimplePager.php';
auth('Admin');
// ========= CSV upload logic [Place at the very beginning of the file] =========
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
    //Fix: Full path redirection
    redirect(app_url('admin/product-listing.php'));
}
// ========= Everything below is your original code; do not modify =========
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

$query = "SELECT p.*, c.name as category_name FROM product p left join category c on p.category_id = c.id $where ORDER BY $sort $dir";
$pager = new SimplePager($query, $params, '10', $page);
$arr   = $pager->result;
if (get('ajax') == '1') {
    include 'product-listing-results.php';
    exit();
}
$_title = 'Product | Listing (Admin)';
include '../_head.php';
?>
<form method="get" class="form" id="searchForm">
    <?= html_search('search', 'placeholder="Search by name" autocomplete="off"') ?>
    <?= html_hidden('sort') ?>
    <?= html_hidden('dir') ?>
    <button type="submit">Search</button>
</form>

<!-- ===== Styled CSV Upload Area ===== -->
<div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap;margin:18px 0;padding:14px 18px;background:#fbf3e8;border-radius:10px;border:1px solid #f0e2d0;">
    <a href="product-create.php">
        <button class="btn-add" style="margin:0;">+ Add New Product</button>
    </a>

    <form method="post" enctype="multipart/form-data" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
        <!-- Custom file selection button; hide the native input -->
        <label style="position:relative;overflow:hidden;display:inline-block;margin:0;">
            <input type="file" name="csv_file" accept=".csv" required
                   style="position:absolute;opacity:0;left:0;top:0;width:100%;height:100%;cursor:pointer;">
            <button type="button" style="background:#ffffff;color:#914e2b;border:1px solid #ddb99c;padding:9px 18px;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;margin:0;">
                📂 Choose CSV File
            </button>
        </label>

        <!-- Display the selected file name -->
        <span id="selectedCsvName" style="min-width:200px;font-size:14px;color:#704c33;">No file chosen</span>

        <button type="submit" class="btn-add" style="margin:0;">
            📥 Upload CSV
        </button>
    </form>
</div>

<div id="resultsWrap">
<?php include 'product-listing-results.php'; ?>
</div>

<script src="../js/product-listing.js"></script>
<!-- JS to display the selected CSV filename -->
<script>
document.addEventListener('DOMContentLoaded', function(){
    const csvInput = document.querySelector('input[name="csv_file"]');
    const nameDisplay = document.getElementById('selectedCsvName');
    csvInput.addEventListener('change', function(){
        if(this.files.length > 0){
            nameDisplay.textContent = this.files[0].name;
        }else{
            nameDisplay.textContent = "No file chosen";
        }
    })
})
</script>

<?php
include '../_foot.php';
