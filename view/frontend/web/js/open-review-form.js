/**
 * The invitation's links end in #review-form. Luma marks the Reviews tab active for that hash but leaves
 * its content hidden, so a shopper arriving from the email saw no form. Open the tab and scroll to it.
 */
define(['jquery'], function ($) {
    'use strict';

    return function () {
        var started = Date.now(),
            lastClick = 0,
            timer;

        if (window.location.hash !== '#review-form') {
            return;
        }
        timer = setInterval(function () {
            var form = $('#review-form'),
                title = $('#tab-label-reviews-title');

            if (form.is(':visible')) {
                clearInterval(timer);
                form.get(0).scrollIntoView({block: 'start'});

                return;
            }
            // The tabs widget may not be ready yet; click again every second until the form shows.
            if (title.length && Date.now() - lastClick > 1000) {
                lastClick = Date.now();
                title.trigger('click');
            }
            if (Date.now() - started > 10000) {
                clearInterval(timer);
            }
        }, 200);
    };
});
