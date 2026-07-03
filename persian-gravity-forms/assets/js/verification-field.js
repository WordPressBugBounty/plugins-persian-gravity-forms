jQuery(document).ready(function ($) {

    let is_resend_request_running = false;
    let is_resend_cooling_down = false;
    let timer_interval;

    function run_countdown(button_element, duration) {
        clearInterval(timer_interval);

        is_resend_cooling_down = true;

        button_element
            .prop('disabled', true)
            .val('ارسال مجدد کد (' + duration + ' ثانیه)');

        timer_interval = setInterval(function () {
            duration--;

            if (duration <= 0) {

                clearInterval(timer_interval);

                is_resend_cooling_down = false;

                button_element
                    .prop('disabled', false)
                    .val('ارسال مجدد کد تایید');

                $('#gf_sms_resend_status').text('');

            } else {

                button_element.val('ارسال مجدد کد (' + duration + ' ثانیه)');

            }

        }, 1000);
    }

    let initial_button_el = $('#gf_sms_resend_trigger');

    if (initial_button_el.length) {
        run_countdown(initial_button_el, 60);
    }

    $(document)
        .off('click', '#gf_sms_resend_trigger')
        .on('click', '#gf_sms_resend_trigger', function (e) {
            e.preventDefault();

            if (is_resend_request_running || is_resend_cooling_down) {
                return;
            }

            let resend_button_el = $(this);
            let resend_status_el = $('#gf_sms_resend_status');
            let form_id = resend_button_el.data('form');
            let field_id = resend_button_el.data('field');
            let mobile_target_id = resend_button_el.data('mobile-target');
            let form_el = $('#gform_' + form_id);

            if (!mobile_target_id) {
                resend_status_el
                    .text('خطا: فیلد شماره موبایل به درستی تنظیم نشده است.')
                    .css('color', 'red');
                return;
            }

            let normalized_mobile_target_id = String(mobile_target_id).replace('.', '_');

            let target_mobile_field_selector =
                'input[name="input_' + mobile_target_id + '"], ' +
                'input[name="input_' + normalized_mobile_target_id + '"], ' +
                '#input_' + form_id + '_' + normalized_mobile_target_id;

            let mobile_field_value = $(target_mobile_field_selector).val();

            if (!mobile_field_value || mobile_field_value.trim() === '') {
                resend_status_el
                    .text('لطفا ابتدا شماره موبایل خود را وارد کنید.')
                    .css('color', 'red');
                return;
            }

            is_resend_request_running = true;

            resend_button_el
                .prop('disabled', true)
                .val('در حال ارسال مجدد کد...');

            resend_status_el.text('').css('color', '#333');

            form_el
                .find('input[type="submit"], button[type="submit"]')
                .prop('disabled', true)
                .css('opacity', '0.6');

            fetch(gf_persian_sms_verification.root + 'resend', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': gf_persian_sms_verification.nonce
                },
                body: JSON.stringify({
                    form_id: form_id,
                    field_id: field_id,
                    mobile_value: mobile_field_value
                })
            })
                .then(function (response) {
                    return response.json().then(function (data) {

                        if (!response.ok) {
                            throw new Error(data.message || 'خطایی در سیستم رخ داده است. لطفا مجددا تلاش کنید.');
                        }

                        return data;
                    });
                })
                .then(function (data) {
                    resend_status_el
                        .text(data.message)
                        .css('color', 'green');

                    form_el
                        .find('input[type="submit"], button[type="submit"]')
                        .prop('disabled', false)
                        .css('opacity', '1');

                    is_resend_request_running = false;

                    run_countdown(resend_button_el, 60);
                })
                .catch(function (error) {
                    resend_status_el
                        .text(error.message)
                        .css('color', 'red');

                    resend_button_el
                        .prop('disabled', false)
                        .val('ارسال مجدد کد تایید');

                    form_el
                        .find('input[type="submit"], button[type="submit"]')
                        .prop('disabled', false)
                        .css('opacity', '1');

                    is_resend_request_running = false;
                    is_resend_cooling_down = false;
                });
        });

    $(document).on('submit', 'form[id^="gform_"]', function (e) {

        let form = $(this);

        if (is_resend_request_running) {

            e.preventDefault();
            e.stopPropagation();

            return false;
        }

        let verify_field = form.find('.verify_code');

        if (!verify_field.length) {
            return true;
        }

        let field_wrapper = verify_field.closest('.gfield');
        let validation_message = field_wrapper.find('.validation_message');

        if (verify_field.val().trim() === '') {

            e.preventDefault();
            e.stopPropagation();

            field_wrapper.addClass('gfield_error');
            verify_field.attr('aria-invalid', 'true');
            verify_field.css('border', '1px solid red');

            validation_message.text('لطفا کد تایید پیامک شده را وارد کنید.');

            $('html, body').animate({
                scrollTop: field_wrapper.offset().top - 120
            }, 500, function () {
                verify_field.focus();
            });

            return false;
        }

    });

});
