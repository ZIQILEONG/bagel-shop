<?php
include '../_base.php';
auth('Admin');

$capturedImagePath = '';

if (is_post()) {
    $name = req('name');
    $price = req('price');
    $description = req('description');

    // Handle camera photo (base64)    $base64Img = req('capture_photo');
    if (!empty($base64Img)) {
        $imgData = explode(',', $base64Img)[1];
        $binary = base64_decode($imgData);
        $filename = 'cam_' . uniqid() . '.png';
        $savePath = __DIR__ . '/../products/' . $filename;
        file_put_contents($savePath, $binary);
        $capturedImagePath = 'products/' . $filename;
    }

    // Insert into database
    $stm = $_db->prepare("
        INSERT INTO product (name, price, description, capture_photo)
        VALUES (?, ?, ?, ?)
    ");
    $stm->execute([$name, $price, $description, $capturedImagePath]);

    redirect('product-listing.php');
}

$_title = "Add New Product";
include '../_head.php';
?>

<form method="post">

        <div style="margin:20px 0; border:1px solid #ccc; padding:15px;">
        <h4>Webcam Capture Product Photo</h4>
        <video id="webcam" autoplay playsinline style="width:340px; border:1px solid #888;"></video>
        <canvas id="canvas" style="display:none;"></canvas>

        <div style="margin:10px 0;">
            <button type="button" id="btnOpen">Open Webcam</button>
            <button type="button" id="btnSnap">Take Photo</button>
        </div>

        <div>
            <p>Preview:</p>
            <img id="previewImg" style="max‑width:260px; display:none;">
        </div>
        <input type="hidden" name="capture_photo" id="inputCapture">
    </div>


    <button type="submit">Save Product</button>
</form>
<!-- ========== JS Script: After the form, before _foot.php ========== -->
<script>
const video = document.getElementById('webcam');
const canvas = document.getElementById('canvas');
const ctx = canvas.getContext('2d');
const previewImg = document.getElementById('previewImg');
const inputCapture = document.getElementById('inputCapture');
let stream = null;

document.getElementById('btnOpen').addEventListener('click', async () => {
    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: "environment" }
        });
        video.srcObject = stream;
    } catch (err) {
        if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
            alert("No webcam device found on this computer.\nPlease use a device with camera.");
        } else {
            alert("Cannot open webcam: " + err.message);
        }
    }
});

document.getElementById('btnSnap').addEventListener('click', () => {
    if (!stream) {
        alert("Please open webcam first");
        return;
    }
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    ctx.drawImage(video, 0, 0);
    const base64 = canvas.toDataURL('image/png');
    previewImg.src = base64;
    previewImg.style.display = 'block';
    inputCapture.value = base64;
});

window.addEventListener('beforeunload', () => {
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
    }
});
</script>

<?php include '../_foot.php'; ?>
