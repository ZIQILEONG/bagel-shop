<?php
include '../../_base.php';
auth('Admin');

$msg = '';
$msgType = '';

if (is_post()) {
    // 获取上传的csv文件
    $file = $_FILES['csv_file'] ?? null;

    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $msg = "Please select a valid CSV file.";
        $msgType = "error";
    } else {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (strtolower($ext) !== 'csv') {
            $msg = "Only .csv file allowed.";
            $msgType = "error";
        } else {
            $handle = fopen($file['tmp_name'], "r");
            if (!$handle) {
                $msg = "Cannot open uploaded file";
                $msgType = "error";
            } else {
                $firstRow = true;
                $insertCount = 0;
                $_db->beginTransaction();
                try {
                    while (($row = fgetcsv($handle, 1000, ",")) !== false) {
                        // 跳过第一行表头
                        if ($firstRow) {
                            $firstRow = false;
                            continue;
                        }
                        // 字段顺序：id,name,price,photo,description,stock,category_id
                        [$id,$name,$price,$photo,$description,$stock,$category_id] = $row;

                        $stm = $_db->prepare("
                            INSERT INTO product(id,name,price,photo,description,stock,category_id)
                            VALUES (?,?,?,?,?,?,?)
                        ");
                        $stm->execute([$id,$name,$price,$photo,$description,$stock,$category_id]);
                        $insertCount++;
                    }
                    fclose($handle);
                    $_db->commit();
                    $msg = "Success! Imported {$insertCount} product records.";
                    $msgType = "info";
                } catch (Exception $e) {
                    $_db->rollBack();
                    fclose($handle);
                    $msg = "Import failed: " . $e->getMessage();
                    $msgType = "error";
                }
            }
        }
    }
    temp($msgType, $msg);
    redirect();
}

$_title = "Admin | Batch Import Product CSV";
include '../../_head.php';
?>
<h2>Batch Import Products from CSV</h2>
<div style="max-width:600px;">
    <div style="background:#f7f7f7;padding:14px;border-radius:8px;margin-bottom:16px;">
        <h4>CSV File Format Requirement</h4>
        <p>First row(header): <code>id,name,price,photo,description,stock,category_id</code></p>
        <p>Example row: <code>P001,Plain Bagel,7.90,bagel01.jpg,Original plain bagel,120,1</code></p>
        <p>⚠️ Warning: Product ID must be unique, duplicate ID will cause error.</p>
    </div>

    <form method="post" enctype="multipart/form-data">
        <div>
            <label>Select CSV File:</label>
            <input type="file" name="csv_file" accept=".csv" required>
        </div>
        <div style="margin-top:16px;">
            <button type="submit">Upload & Import</button>
            <a href="product_list.php"><button type="button">Back Product List</button></a>
        </div>
    </form>
</div>

<?php include '../../_foot.php'; ?>
