<?php
require '../config.php';
require '../_base.php';

if(is_post()){
    $imageData = $_POST['photo'] ?? '';
    if(!empty($imageData)){
        $imageData = str_replace('data:image/png;base64,', '', $imageData);
        $imageData = base64_decode($imageData);
        $fileName = "cam_".time().".png";
        file_put_contents("../photos/".$fileName, $imageData);
        echo "Photo saved: ".$fileName;
        exit;
    }
}

$_title = "Webcam Photo Capture";
include '../_head.php';
?>

<h2>Capture Photo from Webcam</h2>
<video id="video" autoplay playsinline style="width:600px;border:1px solid black;"></video>
<br>
<button id="captureBtn">Capture Photo</button>
<canvas id="canvas" style="display:none;"></canvas>
<div id="preview"></div>

<script>
const video = document.getElementById('video');
const canvas = document.getElementById('canvas');
const ctx = canvas.getContext('2d');

navigator.mediaDevices.getUserMedia({video:true})
.then(stream=>video.srcObject = stream);

document.getElementById('captureBtn').addEventListener('click',()=>{
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    ctx.drawImage(video,0,0);
    const photoData = canvas.toDataURL('image/png');

    fetch('webcam_capture.php',{
        method:'POST',
        body: new URLSearchParams({photo:photoData})
    }).then(res=>res.text()).then(txt=>{
        document.getElementById('preview').innerHTML = `<img src="${photoData}" style="max-width:400px;"><p>${txt}</p>`;
    })
})
</script>

<?php include '../_foot.php'; ?>
