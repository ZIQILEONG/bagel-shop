<?php
include '_base.php';

// ----------------------------------------------------------------------------



// ----------------------------------------------------------------------------

$_title = 'Index';
include '_head.php';
?>

<section class="hero">
    <h1 class="hover-text">Pululu Bagel</h1>
    <p class="tagline">Freshly baked bagels, delivered warm to your door.</p>
</section>

<section class="carousel-section">
    <div class="carousel-stage" id="carouselStage">
        <div class="carousel-slide active" data-index="0"><img src="/photos/products/bagelA.png" alt="Sesame Bagel"></div>
        <div class="carousel-slide"        data-index="1"><img src="/photos/products/bagelB.jpg" alt="Cinnamon Raisin Bagel"></div>
        <div class="carousel-slide"        data-index="2"><img src="/photos/products/bagelC.jpg" alt="Everything Bagel"></div>
        <div class="carousel-slide"        data-index="3"><img src="/photos/products/bagelD.jpg" alt="Blueberry Bagel"></div>
        <div class="carousel-slide"        data-index="4"><img src="/photos/products/bagelE.jpg" alt="Cream Cheese Bagel"></div>
        <div class="carousel-progress" id="carouselProgress"></div>
    </div>

    <div class="carousel-description" id="carouselDescription">
        <h2 id="carouselTitle"></h2>
        <p id="carouselText"></p>
        <a class="button" id="carouselLink" href="/product/list.php">Order Now</a>
    </div>

    <div class="carousel-controls">
        <button type="button" class="carousel-status" id="carouselStatus">Auto</button>
        <button type="button" class="carousel-dot active" data-dot="0" aria-label="Slide 1">1</button>
        <button type="button" class="carousel-dot"        data-dot="1" aria-label="Slide 2">2</button>
        <button type="button" class="carousel-dot"        data-dot="2" aria-label="Slide 3">3</button>
        <button type="button" class="carousel-dot"        data-dot="3" aria-label="Slide 4">4</button>
        <button type="button" class="carousel-dot"        data-dot="4" aria-label="Slide 5">5</button>
    </div>
</section>

<section class="demo-accounts">
    <h2>Demo accounts</h2>
    <table class="table">
        <tr>
            <th>Email</th>
            <th>Password</th>
            <th>Role</th>
        </tr>
        <tr>
            <td>admin@bagel.com</td>
            <td>123456</td>
            <td>Admin</td>
        </tr>
        <tr>
            <td>member1@bagel.com</td>
            <td>123456</td>
            <td>Member</td>
        </tr>
        <tr>
            <td>member2@bagel.com</td>
            <td>123456</td>
            <td>Member</td>
        </tr>
    </table>
</section>

<script src="/js/carousel.js"></script>

<?php
include '_foot.php';