<?php
require '../config.php';
require '../_base.php';
$msg = '';

if(is_post() && isset($_FILES['import_file'])){
    $file = $_FILES['import_file'];
    if($file['error'] === 0){
        $handle = fopen($file['tmp_name'], "r");
        $count = 0;
        while(($row = fgetcsv($handle))!==false){
            $name = $row[0] ?? '';
            $price = $row[1] ?? 0;
            $desc = $row[2] ?? '';
            if(!empty($name)){
                $stmt = $_db->prepare("INSERT INTO products(name,price,description,created_at) VALUES(?,?,?,NOW())");
                $stmt->execute([$name,$price,$desc]);
                $count++;
            }
        }
        fclose($handle);
        $msg = "Batch insert completed. Total inserted: ".$count;
    }
}

$_title = "Batch Import Products (CSV / Text)";
include '../_head.php';
?>

<h2>Batch Insert from CSV / Text File</h2>
<?php if($msg): ?><p style="color:green;"><?= $msg ?></p><?php endif; ?>

<p>CSV format: name,price,description (one product per line)</p>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="import_file" accept=".csv,.txt">
    <button type="submit">Upload & Batch Insert</button>
</form>

<?php include '../_foot.php'; ?>
