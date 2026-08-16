// ============================================================================
// General Functions
// ============================================================================



// ============================================================================
// Page Load (jQuery)
// ============================================================================

$(() => {

    // Autofocus
    $('form :input:not(button):first').focus();
    $('.err:first').prev().focus();
    $('.err:first').prev().find(':input:first').focus();
    
// Confirmation message (SweetAlert2)
$('[data-confirm]').on('click', function (e) {
    const el = e.target;

    if (el.dataset.confirmed) {
        delete el.dataset.confirmed; // let the real click proceed
        return;
    }

    e.preventDefault();
    e.stopImmediatePropagation();

    Swal.fire({
        title: 'Please Confirm',
        text: el.dataset.confirm || 'Are you sure?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#b5192b',
        cancelButtonColor: '#8a7264',
        confirmButtonText: 'Yes, continue',
    }).then(result => {
        if (result.isConfirmed) {
            el.dataset.confirmed = '1';
            el.click(); // re-fires the click so data-get/data-post handlers run
        }
    });
});

    // Initiate GET request
    $('[data-get]').on('click', e => {
        e.preventDefault();
        const url = e.target.dataset.get;
        location = url || location;
    });

    // Initiate POST request
    $('[data-post]').on('click', e => {
        e.preventDefault();
        const url = e.target.dataset.post;
        const f = $('<form>').appendTo(document.body)[0];
        f.method = 'POST';
        f.action = url || location;
        f.submit();
    });

    // Reset form
    $('[type=reset]').on('click', e => {
        e.preventDefault();
        location = location;
    });

    // Auto uppercase
    $('[data-upper]').on('input', e => {
        const a = e.target.selectionStart;
        const b = e.target.selectionEnd;
        e.target.value = e.target.value.toUpperCase();
        e.target.setSelectionRange(a, b);
    });

    // Photo preview
    $('label.upload input[type=file]').on('change', e => {
        const f = e.target.files[0];
        const img = $(e.target).siblings('img')[0];

        if (!img) return;

        img.dataset.src ??= img.src;

        if (f?.type.startsWith('image/')) {
            img.src = URL.createObjectURL(f);
        }
        else {
            img.src = img.dataset.src;
            e.target.value = '';
        }
    });

});

const text = document.querySelector(".hover-text");

text.addEventListener("mousemove", (e) => {
    const rect = text.getBoundingClientRect();

    const x = e.clientX - rect.left - rect.width / 2;
    const y = e.clientY - rect.top - rect.height / 2;

    text.style.transform = `
        translateY(-8px)
        rotateX(${-y / 10}deg)
        rotateY(${x / 10}deg)
    `;
});

text.addEventListener("mouseleave", () => {
    text.style.transform = "translateY(0)";
});

// ============================================================================
// General Functions
// ============================================================================

// Debounce helper — used by live search so we don't fire an AJAX call on every keystroke
function debounce(fn, delay) {
    let timer;
    return function (...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), delay);
    };
}

// Refresh an AJAX-driven listing container.
// `container` is any selector/element with [data-ajax-container], [data-ajax-url]
// and [data-search-form] attributes (see product/list.php for an example).
function ajaxRefreshContainer(container, extra = {}) {
    const $container = $(container);
    if (!$container.length) {
        return;
    }

    const url = $container.data('ajaxUrl');
    if (!url) {
        return;
    }

    const $form = $($container.data('searchForm'));
    const params = Object.assign({
        search: $form.find('[name=search]').val() || '',
        sort: $form.find('[name=sort]').val() || '',
        dir: $form.find('[name=dir]').val() || '',
        page: 1,
    }, extra);

    $container.addClass('is-loading');

    $.get(url, params)
        .done(html => $container.html(html))
        .fail(() => Swal.fire('Oops!', 'Could not load results, please try again.', 'error'))
        .always(() => $container.removeClass('is-loading'));
}

// ============================================================================
// Page Load (jQuery)
// ============================================================================

$(() => {

    // Autofocus
    $('form :input:not(button):first').focus();
    $('.err:first').prev().focus();
    $('.err:first').prev().find(':input:first').focus();

    // Confirmation message (SweetAlert2 replaces the native confirm() popup)
    $('[data-confirm]').on('click', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();

        const el = this;
        const text = el.dataset.confirm || 'Are you sure?';

        Swal.fire({
            title: 'Are you sure?',
            text: text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#b5192b',
            cancelButtonColor: '#8a7264',
            confirmButtonText: 'Yes, proceed',
            cancelButtonText: 'Cancel',
        }).then(result => {
            if (!result.isConfirmed) {
                return;
            }

            // Re-run whichever action the element was originally meant to do
            if (el.dataset.get) {
                location = el.dataset.get;
                return;
            }

            if (el.dataset.post) {
                const f = $('<form>').appendTo(document.body)[0];
                f.method = 'POST';
                f.action = el.dataset.post;
                f.submit();
                return;
            }

            // Plain submit button inside a <form> (e.g. "Delete Selected")
            const form = el.closest('form');
            if (form) {
                if (typeof form.requestSubmit === 'function') {
                    // Preserves the button's own name/value (e.g. btn=delete_selected)
                    form.requestSubmit(el);
                } else {
                    if (el.name) {
                        $('<input>').attr({
                            type: 'hidden',
                            name: el.name,
                            value: el.value,
                        }).appendTo(form);
                    }
                    form.submit();
                }
            }
        });
    });

    // Initiate GET request
    $('[data-get]').on('click', e => {
        e.preventDefault();
        const url = e.target.dataset.get;
        location = url || location;
    });

    // Initiate POST request
    $('[data-post]').on('click', e => {
        e.preventDefault();
        const url = e.target.dataset.post;
        const f = $('<form>').appendTo(document.body)[0];
        f.method = 'POST';
        f.action = url || location;
        f.submit();
    });

    // Reset form
    $('[type=reset]').on('click', e => {
        e.preventDefault();
        location = location;
    });

    // Auto uppercase
    $('[data-upper]').on('input', e => {
        const a = e.target.selectionStart;
        const b = e.target.selectionEnd;
        e.target.value = e.target.value.toUpperCase();
        e.target.setSelectionRange(a, b);
    });

    // Photo preview
    $('label.upload input[type=file]').on('change', e => {
        const f = e.target.files[0];
        const img = $(e.target).siblings('img')[0];

        if (!img) return;

        img.dataset.src ??= img.src;

        if (f?.type.startsWith('image/')) {
            img.src = URL.createObjectURL(f);
        }
        else {
            img.src = img.dataset.src;
            e.target.value = '';
        }
    });

    // ------------------------------------------------------------------
    // Live search / AJAX listing filters
    // (used by product/list.php — see [data-ajax-container] there)
    // ------------------------------------------------------------------

    // Search-as-you-type
    $(document).on('input', '[data-live-search]', debounce(function () {
        ajaxRefreshContainer($(this).data('target'), { page: 1 });
    }, 300));

    // Sort / direction dropdowns also refresh live
    $(document).on('change', '[data-live-refresh]', function () {
        ajaxRefreshContainer($(this).data('target'), { page: 1 });
    });

    // Intercept pager clicks inside a live AJAX container so paging doesn't reload the page
    $(document).on('click', '[data-ajax-container] .pager a', function (e) {
        e.preventDefault();
        const href = $(this).attr('href') || '';
        const qs = href.split('?')[1] || '';
        const params = new URLSearchParams(qs);
        const $container = $(this).closest('[data-ajax-container]');
        ajaxRefreshContainer($container, { page: params.get('page') || 1 });
    });

    // Add-to-cart quantity selects inside live-updated product grids
    $(document).on('change', '.product select[name=unit]', e => e.target.form.submit());

});

// ============================================================================
// Decorative hover effect (index page hero title)
// ============================================================================

const heroText = document.querySelector(".hover-text");

if (heroText) {
    heroText.addEventListener("mousemove", (e) => {
        const rect = heroText.getBoundingClientRect();

        const x = e.clientX - rect.left - rect.width / 2;
        const y = e.clientY - rect.top - rect.height / 2;

        heroText.style.transform = `
            translateY(-8px)
            rotateX(${-y / 10}deg)
            rotateY(${x / 10}deg)
        `;
    });

    heroText.addEventListener("mouseleave", () => {
        heroText.style.transform = "translateY(0)";
    });
}