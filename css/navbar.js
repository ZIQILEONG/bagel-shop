(() => {
    'use strict';

    const header = document.querySelector('.pululu-header');
    const menuButton = document.querySelector('.pululu-menu-toggle');
    const primaryNavigation = document.getElementById('pululuPrimaryNav');
    const actionMenus = document.querySelectorAll('.pululu-actions details');

    if (!header || !menuButton || !primaryNavigation) return;

    function closeMobileMenu() {
        primaryNavigation.classList.remove('is-open');
        menuButton.classList.remove('is-open');
        menuButton.setAttribute('aria-expanded', 'false');
        menuButton.setAttribute('aria-label', 'Open menu');
    }

    function closeActionMenus(except = null) {
        actionMenus.forEach(menu => {
            if (menu !== except) menu.open = false;
        });
    }

    menuButton.addEventListener('click', () => {
        const isOpen = primaryNavigation.classList.toggle('is-open');
        menuButton.classList.toggle('is-open', isOpen);
        menuButton.setAttribute('aria-expanded', String(isOpen));
        menuButton.setAttribute('aria-label', isOpen ? 'Close menu' : 'Open menu');
        closeActionMenus();
    });

    primaryNavigation.addEventListener('click', event => {
        if (event.target.closest('a')) closeMobileMenu();
    });

    actionMenus.forEach(menu => {
        menu.addEventListener('toggle', () => {
            if (menu.open) {
                closeActionMenus(menu);
                closeMobileMenu();
            }
        });
    });

    document.addEventListener('click', event => {
        if (!event.target.closest('.pululu-header')) {
            closeActionMenus();
            closeMobileMenu();
        }
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            closeActionMenus();
            closeMobileMenu();
            menuButton.focus();
        }
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 760) closeMobileMenu();
    });
})();