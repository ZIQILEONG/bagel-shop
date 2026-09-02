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
<video class="il-69-11cf8d" id="video" autoplay playsinline></video>
<br>
<button id="captureBtn">Capture Photo</button>
<canvas class="il-35-cb4589" id="canvas"></canvas>
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
        document.getElementById('preview').innerHTML = `<img class="il-70-88efe2" src="${photoData}"><p>${txt}</p>`;
    })
})
</script>

<?php include '../_foot.php'; ?>
