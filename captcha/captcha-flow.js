(function () {
    'use strict';

    const root = document.getElementById('puzzle-check');

    if (!root) {
        return;
    }

    const message = document.getElementById('puzzle-message');
    let slider;
    let verifying = false;

    function showMessage(text, type) {
        message.textContent = text;
        message.className = 'puzzle-message ' + (type || '');
    }

    async function completePuzzle() {
        if (verifying) {
            return;
        }

        verifying = true;
        showMessage('Checking puzzle...', '');

        try {
            const response = await fetch(root.dataset.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    action: root.dataset.action,
                    nonce: root.dataset.nonce,
                    trail: Array.isArray(slider.trail) ? slider.trail : [],
                    position: parseFloat(slider.block.style.left || '0'),
                    target: Number(slider.x)
                })
            });

            const result = await response.json();

            if (!response.ok || !result.success || !result.redirect) {
                if (result.redirect) {
                    window.location.href = result.redirect;
                    return;
                }

                throw new Error(result.message || 'Puzzle verification failed.');
            }

            showMessage('Success! Continuing...', 'success');

            window.setTimeout(function () {
                window.location.href = result.redirect;
            }, 350);
        }
        catch (error) {
            showMessage(error.message || 'Verification failed. Try again.', 'error');

            if (slider) {
                slider.reset();
            }
        }
        finally {
            verifying = false;
        }
    }

    slider = sliderCaptcha({
        id: 'slider-captcha',
        width: 280,
        height: 155,
        offset: 6,
        repeatIcon: '',
        loadingText: 'Loading puzzle...',
        failedText: 'Try again',
        barText: 'Slide to complete the puzzle',
        setSrc: function () {
            const number = Math.floor(Math.random() * 3) + 1;
            return root.dataset.assetBase + '/images/captcha' + number + '.png?v=' + Date.now();
        },
        onSuccess: completePuzzle,
        onFail: function () {
            showMessage('The puzzle did not match. Try again.', 'error');
        },
        onRefresh: function () {
            showMessage('Complete the new puzzle.', '');
        }
    });
})();
