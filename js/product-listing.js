$(function () {
    let timer = null;
    function loadResults(page) {
        $.get('product-listing.php', {
            ajax: 1,
            search: $('#search').val(),
            sort: $('#sort').val(),
            dir: $('#dir').val(),
            page: page || 1
        }, html => $('#resultsWrap').html(html));
    }
    $('#search').on('input', function () {
        clearTimeout(timer);
        timer = setTimeout(() => loadResults(1), 350); // debounce
    });
    $('#resultsWrap').on('click', '.pager a', function (e) {
        e.preventDefault();
        loadResults(new URL(this.href).searchParams.get('page'));
    });
    $('#searchForm').on('submit', e => { e.preventDefault(); loadResults(1); });

    // Preview + confirm before batch price update
    $('#resultsWrap').on('click', 'button[name="btn"][value="increase_price"]', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();

        const $form    = $(this).closest('form');
        const $checked = $form.find('input[name="ids[]"]:checked');
        const percent  = parseFloat($form.find('input[name="percent"]').val());

        if ($checked.length === 0) {
            Swal.fire('No products selected', 'Please select at least one product first.', 'warning');
            return;
        }
        if (isNaN(percent)) {
            Swal.fire('Invalid percentage', 'Please enter a valid percentage (negative to lower prices, positive to raise them).', 'warning');
            return;
        }
        if (percent < -100) {
            Swal.fire('Invalid percentage', 'Percentage must be -100 or greater (prices cannot go below zero).', 'warning');
            return;
        }

        const isDecrease = percent < 0;

        let rows = '';
        $checked.each(function () {
            const $tr      = $(this).closest('tr');
            const name     = $tr.find('td').eq(3).text().trim();
            const oldPrice = parseFloat($tr.find('td').eq(4).text().trim());
            const newPrice = Math.round(oldPrice * (1 + percent / 100) * 100) / 100;
            const changeColor = newPrice < oldPrice ? 'var(--green, #2e8b57)' : 'var(--red)';
            rows += `
                <tr>
                    <td style="padding:6px 10px;text-align:left;">${name}</td>
                    <td style="padding:6px 10px;text-align:right;">RM ${oldPrice.toFixed(2)}</td>
                    <td style="padding:6px 10px;text-align:right;color:${changeColor};font-weight:bold;">RM ${newPrice.toFixed(2)}</td>
                </tr>`;
        });

        const html = `
            <p style="margin-bottom:10px;">This will ${isDecrease ? 'decrease' : 'increase'} the price of <b>${$checked.length}</b> product(s) by <b>${Math.abs(percent)}%</b>:</p>
            <div style="max-height:300px;overflow-y:auto;border:1px solid var(--border);border-radius:8px;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="background:var(--red);color:var(--white);">
                            <th style="padding:6px 10px;text-align:left;">Product</th>
                            <th style="padding:6px 10px;text-align:right;">Old Price</th>
                            <th style="padding:6px 10px;text-align:right;">New Price</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
        `;

        Swal.fire({
            title: 'Confirm Price Update',
            html: html,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#b5192b',
            cancelButtonColor: '#8a7264',
            confirmButtonText: isDecrease ? 'Yes, lower prices' : 'Yes, update prices',
            cancelButtonText: 'Cancel',
            width: 520,
        }).then(result => {
            if (result.isConfirmed) {
                $('<input>').attr({ type: 'hidden', name: 'btn', value: 'increase_price' }).appendTo($form);
                $form[0].submit();
            }
        });
    });
});