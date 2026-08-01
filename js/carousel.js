(() => {
    'use strict';
    //old website code -- for aesthetics
    // Slide content - real products, linking straight to their detail page
    const SLIDES = [
        { id: 'P002', title: 'Sesame Bagel',         text: 'Toasted sesame seeds baked into every bite. RM 4.80.',            photo: '/products/sesame.jpg' },
        { id: 'P003', title: 'Cinnamon Raisin Bagel', text: 'Warm cinnamon and sweet raisins, fresh from the oven. RM 5.50.', photo: '/products/cinnamon.jpg' },
        { id: 'P004', title: 'Everything Bagel',      text: 'Our signature blend of seeds, garlic and onion. RM 5.80.',       photo: '/products/everything.jpg' },
        { id: 'P005', title: 'Blueberry Bagel',       text: 'Bursting with juicy blueberries in every slice. RM 5.50.',       photo: '/products/blueberry.jpg' },
        { id: 'P006', title: 'Cream Cheese Bagel',    text: 'Soft bagel swirled with rich cream cheese. RM 6.50.',            photo: '/products/creamcheese.jpg' },
    ];

    const INTERVAL = 6000; // ms per slide

    const stage = document.getElementById('carouselStage');
    if (!stage) return; // Carousel isn't on this page - do nothing

    const slides   = stage.querySelectorAll('.carousel-slide');
    const progress = document.getElementById('carouselProgress');
    const dots     = document.querySelectorAll('.carousel-dot');
    const descEl   = document.getElementById('carouselDescription');
    const titleEl  = document.getElementById('carouselTitle');
    const textEl   = document.getElementById('carouselText');
    const linkEl   = document.getElementById('carouselLink');
    const statusEl = document.getElementById('carouselStatus');

    let current   = -1;
    let isPaused  = false;
    let autoTimer = null;

    // Progress-bar tracking
    let rafId    = null;
    let rafStart = null;
    let elapsed  = 0;

    function syncDots(idx) {
        dots.forEach((d, i) => d.classList.toggle('active', i === idx));
    }

    /* Move to a slide */
    function goTo(idx) {
        if (current >= 0) slides[current].classList.remove('active');
        current = idx;
        slides[current].classList.add('active');
        syncDots(current);

        descEl.classList.add('fading');
        setTimeout(() => {
            const s = SLIDES[current];
            titleEl.textContent = s.title;
            textEl.textContent  = s.text;
            linkEl.href = '/product/detail.php?id=' + s.id;
            descEl.classList.remove('fading');
        }, 250);

        elapsed = 0;
        startProgress();
        if (!isPaused) scheduleNext();
    }

    /* Recursive setTimeout - guarantees the chain never breaks */
    function scheduleNext() {
        clearTimeout(autoTimer);
        const remaining = INTERVAL - elapsed;
        autoTimer = setTimeout(() => goTo((current + 1) % SLIDES.length), remaining);
    }

    function startProgress() {
        cancelAnimationFrame(rafId);
        rafStart = null;
        rafId = requestAnimationFrame(tick);
    }

    function tick(ts) {
        if (isPaused) return;
        if (rafStart === null) rafStart = ts - elapsed;
        elapsed = ts - rafStart;
        const frac = Math.min(elapsed / INTERVAL, 1);
        progress.style.width = (frac * 100) + '%';
        if (frac < 1) rafId = requestAnimationFrame(tick);
    }

    function pause() {
        if (isPaused) return;
        isPaused = true;
        clearTimeout(autoTimer);
        cancelAnimationFrame(rafId);
        statusEl.textContent = 'Paused';
        statusEl.classList.add('is-paused');
    }

    function resume() {
        if (!isPaused) return;
        isPaused = false;
        statusEl.textContent = 'Auto';
        statusEl.classList.remove('is-paused');
        rafStart = null;
        rafId = requestAnimationFrame(tick);
        scheduleNext();
    }

    /* Auto / Paused toggle */
    statusEl.addEventListener('click', () => isPaused ? resume() : pause());

    /* Dot buttons */
    dots.forEach(dot => {
        dot.addEventListener('click', () => {
            const idx = parseInt(dot.dataset.dot, 10);
            if (idx === current) return;
            clearTimeout(autoTimer);
            cancelAnimationFrame(rafId);
            elapsed = 0;
            goTo(idx);
        });
    });

    /* Boot */
    goTo(0);
})();