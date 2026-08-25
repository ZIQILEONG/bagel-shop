function pululuDebounce(callback, delay = 300) {
    let timer;
    return function (...args) {
        clearTimeout(timer);
        timer = setTimeout(() => callback.apply(this, args), delay);
    };
}

function pululuSubmitPost(url) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = url || window.location.href;
    document.body.appendChild(form);
    form.submit();
}

function pululuRunElementAction(element) {
    if (element.dataset.get !== undefined) {
        window.location.href = element.dataset.get || window.location.href;
        return;
    }

    if (element.dataset.post !== undefined) {
        pululuSubmitPost(element.dataset.post);
        return;
    }

    const form = element.closest('form');
    if (!form) return;

    if (typeof form.requestSubmit === 'function') {
        form.requestSubmit(element);
    } else {
        if (element.name) {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = element.name;
            hiddenInput.value = element.value;
            form.appendChild(hiddenInput);
        }
        form.submit();
    }
}

function pululuRefreshListing(container, extraParameters = {}) {
    const $container = $(container);
    if (!$container.length) return;

    const requestUrl = $container.data('ajaxUrl');
    const $form = $($container.data('searchForm'));
    if (!requestUrl || !$form.length) return;

    const parameters = Object.assign({
        search: $form.find('[name=search]').val() || '',
        sort: $form.find('[name=sort]').val() || '',
        dir: $form.find('[name=dir]').val() || '',
        page: 1,
    }, extraParameters);

    $container.addClass('is-loading');
    $.get(requestUrl, parameters)
        .done((html) => $container.html(html))
        .fail(() => Swal.fire('Unable to refresh', 'Please try again in a moment.', 'error'))
        .always(() => $container.removeClass('is-loading'));
}

$(() => {
    const $firstError = $('.err').filter(function () {
        return $(this).text().trim() !== '';
    }).first();

    if ($firstError.length) {
        $firstError.prev().trigger('focus');
    } else {
        $('main form :input:not(button):visible:first').trigger('focus');
    }

    $(document).on('click', '[data-confirm]', function (event) {
        event.preventDefault();
        event.stopImmediatePropagation();

        const element = this;
        Swal.fire({
            title: 'Please confirm',
            text: element.dataset.confirm || 'Are you sure you want to continue?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#cf694c',
            cancelButtonColor: '#75645d',
            confirmButtonText: 'Yes, continue',
            cancelButtonText: 'Cancel',
        }).then((result) => {
            if (result.isConfirmed) pululuRunElementAction(element);
        });
    });

    $(document).on('click', '[data-get]', function (event) {
        event.preventDefault();
        window.location.href = this.dataset.get || window.location.href;
    });

    $(document).on('click', '[data-post]', function (event) {
        event.preventDefault();
        pululuSubmitPost(this.dataset.post);
    });

    $(document).on('click', '[type=reset]', (event) => {
        event.preventDefault();
        window.location.reload();
    });

    $(document).on('input', '[data-upper]', function () {
        const selectionStart = this.selectionStart;
        const selectionEnd = this.selectionEnd;
        this.value = this.value.toUpperCase();
        this.setSelectionRange(selectionStart, selectionEnd);
    });

    $(document).on('change', 'label.upload input[type=file]', function () {
        const file = this.files[0];
        const image = $(this).siblings('img')[0];
        if (!image) return;

        image.dataset.originalSource ??= image.src;
        if (file?.type.startsWith('image/')) {
            image.src = URL.createObjectURL(file);
        } else {
            image.src = image.dataset.originalSource;
            this.value = '';
        }
    });

    $(document).on('input', '[data-live-search]', pululuDebounce(function () {
        pululuRefreshListing($(this).data('target'), { page: 1 });
    }));

    $(document).on('change', '[data-live-refresh]', function () {
        pululuRefreshListing($(this).data('target'), { page: 1 });
    });

    $(document).on('click', '[data-ajax-container] .pager a', function (event) {
        event.preventDefault();
        const query = ($(this).attr('href') || '').split('?')[1] || '';
        const page = new URLSearchParams(query).get('page') || 1;
        pululuRefreshListing($(this).closest('[data-ajax-container]'), { page });
    });

    $(document).on('change', '.product select[name=unit][data-auto-submit]', function () {
        this.form?.requestSubmit();
    });
});

document.addEventListener('DOMContentLoaded', () => {
    const heroText = document.querySelector('.hover-text');
    if (!heroText || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    heroText.addEventListener('mousemove', (event) => {
        const bounds = heroText.getBoundingClientRect();
        const x = event.clientX - bounds.left - bounds.width / 2;
        const y = event.clientY - bounds.top - bounds.height / 2;
        heroText.style.transform = `translateY(-8px) rotateX(${-y / 10}deg) rotateY(${x / 10}deg)`;
    });

    heroText.addEventListener('mouseleave', () => {
        heroText.style.transform = 'translateY(0)';
    });
});