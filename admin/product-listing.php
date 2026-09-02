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

<style>
/* =========================================================
   PULULU PRODUCT LISTING UI
   ========================================================= */
:root {
    --pl-primary: #cf7953;
    --pl-primary-hover: #b86440;
    --pl-brown-dark: #3e2619;
    --pl-text: #4a3b32;
    --pl-muted: #968377;
    --pl-border: #ebdcd5;
    --pl-card-bg: #ffffff;
    --pl-accent: #faf5f0;
}

.pl-prod-container {
    max-width: 1120px;
    margin: 32px auto 80px;
    padding: 0 20px;
    font-family: 'Nunito Sans', sans-serif;
}

/* Breadcrumb */
.pl-crumb {
    font-size: 13px;
    color: var(--pl-muted);
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.pl-crumb a {
    color: var(--pl-muted);
    text-decoration: none;
    transition: color 0.15s;
}
.pl-crumb a:hover {
    color: var(--pl-primary);
}

/* Header */
.pl-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}
.pl-header h1 {
    font-size: 28px;
    font-weight: 800;
    color: var(--pl-brown-dark);
    margin: 0 0 4px;
    letter-spacing: -0.01em;
}
.pl-header p {
    font-size: 14px;
    color: var(--pl-muted);
    margin: 0;
}

/* Control Panel */
.pl-action-panel {
    background: #ffffff;
    border: 1px solid var(--pl-border);
    border-radius: 18px;
    padding: 20px 24px;
    margin-bottom: 24px;
    box-shadow: 0 4px 18px rgba(62, 38, 25, 0.02);
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.pl-search-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 14px;
}

#searchForm {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
    max-width: 480px;
    margin: 0;
}

#searchForm input[type="search"] {
    width: 100%;
    padding: 10px 16px;
    border: 1.5px solid var(--pl-border);
    border-radius: 12px;
    font-size: 13.5px;
    outline: none;
    color: var(--pl-text);
    background: var(--pl-accent);
    transition: all 0.2s ease;
    box-sizing: border-box;
}

#searchForm input[type="search"]:focus {
    background: #ffffff;
    border-color: var(--pl-primary);
    box-shadow: 0 0 0 3px rgba(207, 121, 83, 0.12);
}

#searchForm button[type="submit"] {
    padding: 10px 20px;
    background: var(--pl-brown-dark);
    color: #ffffff;
    border: none;
    border-radius: 12px;
    font-size: 13.5px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
}

#searchForm button[type="submit"]:hover {
    background: #2b1a11;
}

.pl-tools-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 14px;
    padding-top: 16px;
    border-top: 1px solid #f6eee9;
}

.btn-add {
    background: var(--pl-primary);
    color: #ffffff;
    border: none;
    padding: 10px 20px;
    border-radius: 12px;
    font-size: 13.5px;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    box-shadow: 0 4px 14px rgba(207, 121, 83, 0.22);
}

.btn-add:hover {
    background: var(--pl-primary-hover);
    transform: translateY(-1px);
}

.pl-csv-group {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    background: var(--pl-accent);
    padding: 6px 10px 6px 6px;
    border-radius: 14px;
    border: 1px solid var(--pl-border);
}

.pl-csv-btn {
    background: #ffffff;
    color: var(--pl-brown-dark);
    border: 1.5px solid var(--pl-border);
    padding: 8px 16px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
}

.pl-csv-btn:hover {
    border-color: var(--pl-primary);
    color: var(--pl-primary);
}

#selectedCsvName {
    font-size: 13px;
    color: var(--pl-muted);
    font-weight: 600;
    max-width: 180px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    padding: 0 4px;
}

.pl-csv-upload-btn {
    background: var(--pl-brown-dark);
    color: #ffffff;
    border: none;
    padding: 8px 16px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.2s ease;
}

.pl-csv-upload-btn:hover {
    background: #2b1a11;
}

#resultsWrap {
    margin-top: 20px;
}

/* =========================================================
   Pill-Style Pagination
   ========================================================= */
.pager, .pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin: 32px 0 10px;
    list-style: none;
    padding: 0;
}

.pager a, 
.pager span,
.pagination a,
.pagination span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 40px;
    padding: 0 20px;
    background: #ffffff;
    color: #3e2619;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    border-radius: 999px;
    border: 1px solid #ebdcd5;
    box-shadow: 0 2px 8px rgba(62, 38, 25, 0.04);
    transition: all 0.2s ease;
}

.pager a:not(:first-child):not(:nth-child(2)):not(:nth-last-child(2)):not(:last-child),
.pager span:not(:first-child):not(:nth-child(2)):not(:nth-last-child(2)):not(:last-child) {
    min-width: 40px;
    padding: 0 12px;
}

.pager a:hover,
.pagination a:hover {
    background: #faf5f0;
    border-color: #cf7953;
    color: #cf7953;
}

.pager .active,
.pager .current,
.pager span:not([class]),
.pagination .active {
    background: #a34828 !important;
    color: #ffffff !important;
    border-color: #a34828 !important;
    box-shadow: 0 4px 12px rgba(163, 72, 40, 0.28);
}

.pager .disabled,
.pagination .disabled {
    opacity: 0.45;
    cursor: not-allowed;
    pointer-events: none;
}
</style>

<div class="pl-prod-container">
    <div class="pl-crumb">
        <a href="/">Home</a>
        <span>&rsaquo;</span>
        <span style="color: var(--pl-brown-dark); font-weight: 700;">Products</span>
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
            <a href="product-create.php" style="text-decoration: none;">
                <button type="button" class="btn-add">✨ Add New Product</button>
            </a>

            <form method="post" enctype="multipart/form-data" class="pl-csv-group" style="margin: 0;">
                <label style="position:relative;overflow:hidden;display:inline-block;margin:0;">
                    <input type="file" name="csv_file" accept=".csv" required
                           style="position:absolute;opacity:0;left:0;top:0;width:100%;height:100%;cursor:pointer;">
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