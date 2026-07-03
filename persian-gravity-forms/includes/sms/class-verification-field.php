<?php

use PersianGravityForms\Objects\Mobile;

defined( 'ABSPATH' ) || exit;

class GFPersian_SMS_Verification {

	public function __construct() {

		if ( is_admin() ) {
			add_filter( 'gform_add_field_buttons', [ $this, 'gravity_sms_fields' ], 9998 );
			add_filter( 'gform_field_type_title', [ $this, 'title' ], 10, 2 );
			add_action( 'gform_editor_js_set_default_values', [ $this, 'default_label' ] );
			add_action( 'gform_editor_js', [ $this, 'editor_js' ] );
			add_action( 'gform_field_standard_settings', [ $this, 'standard_settings' ], 10, 2 );
			add_filter( 'gform_tooltips', [ $this, 'tooltips' ] );
		}

		add_filter( 'gform_field_validation', [ $this, 'validation' ], 10, 4 );
		add_filter( 'gform_entry_post_save', [ $this, 'process' ], 10, 2 );
		add_action( 'gform_field_input', [ $this, 'input' ], 10, 5 );
		add_action( 'gform_field_css_class', [ $this, 'classes' ], 10, 3 );
		add_filter( 'gform_merge_tag_filter', [ $this, 'all_fields' ], 10, 4 );
		add_action( 'gform_enqueue_scripts', [ $this, 'external_js' ], 10, 2 );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
	}

	public function gravity_sms_fields( $field_groups ) {

		foreach ( $field_groups as $key => $group ) {

			if ( $group['name'] == 'gf_persian_fields' ) {
				$group['fields'][] = [
					'class'     => 'button',
					'value'     => 'تایید تلفن',
					'data-type' => 'sms_verification',
				];
			}

			$field_groups[ $key ] = $group;
		}

		return $field_groups;
	}

	public function title( $title, $field_type ) {
		return ( $field_type == 'sms_verification' ) ? 'تایید تلفن' : $title;
	}

	public function default_label() { ?>
		case 'sms_verification' :
		field.label = 'تایید تلفن';
		break;
		<?php
	}

	public function classes( $classes, $field, $form ) {

		if ( ( $field['type'] ?? '' ) === 'sms_verification' ) {
			$classes .= ' gfield_contains_required gform_sms_verification';
		}

		return $classes;
	}

	public function input( $input, $field, $value, $entry_id, $form_id ) {
		if ( ( $field['type'] ?? '' ) !== 'sms_verification' ) {
			return $input;
		}

		if ( ! is_admin() && ( RGFormsModel::get_input_type( $field ) === 'adminonly_hidden' ) ) {
			return '';
		}

		$form            = GFAPI::get_form( $form_id );
		$is_entry_detail = GFCommon::is_entry_detail();
		$is_form_editor  = GFCommon::is_form_editor();

		$field_id = $field['id'];
		$form_id  = empty( $form_id ) ? rgget( 'id' ) : $form_id;

		$disabled_text         = $is_form_editor ? "disabled='disabled'" : '';
		$input_id              = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_{$field_id}" : "input_{$form_id}_{$field_id}";
		$size                  = rgar( $field, 'size' );
		$class_suffix          = $is_entry_detail ? '_admin' : '';
		$class                 = $size . $class_suffix;
		$placeholder_attribute = $field->get_field_placeholder_attribute();
		$required_attribute    = $field->isRequired ? 'aria-required="true"' : '';
		$invalid_attribute     = $field->failed_validation ? 'aria-invalid="true"' : 'aria-invalid="false"';
		$html5_attributes      = " {$placeholder_attribute} {$required_attribute} {$invalid_attribute} ";
		$tabindex              = GFCommon::get_tabindex();

		$text_input = '<div class="ginput_container ginput_container_text ginput_container_verfication">';
		$text_input .= '<input style="margin-block-end:10px;" name="input_' . $field_id . '" id="' . $input_id . '" type="text" value="' . esc_attr( $value ) . '" class="verify_code ' . esc_attr( $class ) . '" ' . $tabindex . ' ' . $html5_attributes . ' ' . $disabled_text . '/>';

		if ( $is_form_editor ) {
			$input = $text_input . '</div><br/>';
			$input .= '<div class="gf-html-container ginput_container_verfication" id="ginput_container_verfication_' . $field_id . '">';
			$input .= '<span style="line-height: 25px;">با اضافه کردن این فیلد، کاربر ابتدا باید شماره تلفن خود را از طریق پیامک تایید کند تا بتواند وارد مراحل بعدی پر کردن فرم شود.</span>';
			$input .= '</div>';

			return $input;
		}

		if ( $is_entry_detail ) {
			return $text_input . '</div>';
		}

		$mobile_field_id = rgar( $field, 'field_sms_verify_mobile' );
		$mobile_field    = RGFormsModel::get_field( $form, $mobile_field_id );
		$diff_page       = ! empty( $mobile_field['pageNumber'] ) && ! empty( $field['pageNumber'] ) && $mobile_field['pageNumber'] != $field['pageNumber'];
		$result          = [];

		if ( $diff_page && apply_filters( 'sms_verify_self_validation', true ) ) {
			$result = $this->validation( [ 'action' => 'sms_verify_self_validation' ], $value, $form, $field );
		}

		if ( ! $diff_page && apply_filters( 'gform_button_verify', true ) && empty( $field['conditionalLogic'] ) ) {
			$max_page_num = GFFormDisplay::get_max_page_number( $form );
			if ( ( ! empty( $field['pageNumber'] ) && $field['pageNumber'] == $max_page_num ) || ! empty( $field['pageNumber'] ) ) {
				add_filter( 'gform_submit_button', [ $this, 'submit_button' ], 10, 2 );
			} elseif ( $max_page_num > 1 ) {
				add_filter( 'gform_next_button', [ $this, 'next_button' ], 10, 2 );
			}
		}

		if ( apply_filters( 'sms_verify_display_none', true ) ) {
			return "<style>#field_{$form_id}_{$field_id}{display:none !important;}</style>";
		}

		$input = '';

		if ( apply_filters( 'sms_verify_field', false ) || ( $diff_page && apply_filters( 'sms_verify_field', false ) ) ) {
			$input .= $text_input;
			if ( apply_filters( 'sms_verify_resend', false ) ) {
				$target_mobile_id = rgar( $field, 'field_sms_verify_mobile' );
				$input            .= sprintf( '<input type="button" id="gf_sms_resend_trigger" class="gform_button button" value="ارسال مجدد" data-form="%d" data-field="%d" data-mobile-target="%s" />', esc_attr( $form_id ), esc_attr( $field_id ), esc_attr( $target_mobile_id ) );
				$input            .= '<div id="gf_sms_resend_status" style="margin-block:10px; font-size: 13px; vertical-align: middle; font-weight: bold;"></div>';
			}
			$input .= '</div>';
		}

		if ( ! empty( $result['message_'] ) ) {
			$input .= '<div class="ginput_container ginput_container_text ginput_container_verfication ginput_container_verfication_"><p>' . $result["message_"] . '</p></div>';
		}

		return $input;
	}

	public function validation( $result, $value, $form, $field ) {
		global $wpdb;

		if ( ( $field['type'] ?? '' ) !== 'sms_verification' ) {
			return $result;
		}

		$form_id = intval( $form['id'] );

		$mobile_field_id = rgar( $field, 'field_sms_verify_mobile' );
		$mobile_field    = RGFormsModel::get_field( $form, $mobile_field_id );

		if ( ! $mobile_field ) {
			$result['message']  = 'لطفا جهت تکمیل پیکربندی فرم، ورودی تلفن همراه را از تنظیمات فیلد «تایید تلفن» انتخاب کنید.';
			$result['is_valid'] = false;

			return $this->result( $result, false );
		}

		$mobile         = $this->get_mobile( $field );
		$mobiles_object = new Mobile( $mobile, $this->country_code( $field ) );

		if ( ! $mobiles_object->is_valid() ) {
			$result['message']  = 'تلفن همراه وارد شده، نامعتبر است.';
			$result['is_valid'] = false;

			return $this->result( $result, true );
		}

		if ( isset( $mobile_field->noDuplicates ) && $mobile_field->noDuplicates && RGFormsModel::is_duplicate( $form_id, $mobile_field, $mobile ) ) {
			return $result;
		}

		if ( ! $mobiles_object->is_valid() ) {

			$result['message']  = sprintf( ' لطفا تلفن همراه خود را در ورودی  «%s» وارد کنید.', $mobile_field->label );
			$result['is_valid'] = false;

			return $this->result( $result, false );
		}

		if ( in_array( $mobile, $this->white_list( $field ) ) ) {
			return $this->result( $result, true );
		}

		$verification_row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM %i WHERE mobile = %s AND form_id = %d AND entry_id = 0 ORDER BY id DESC LIMIT 1",
			GFPersian_SMS_DB::$verification_table,
			$mobiles_object->get_recipients_string(),
			$form_id
		) );

		$id       = intval( $verification_row->id ?? 0 );
		$code     = $verification_row->code ?? 0;
		$status   = intval( $verification_row->status ?? 0 );
		$try_num  = intval( $verification_row->try_num ?? 0 );
		$sent_num = intval( $verification_row->sent_num ?? 0 );

		$new_try_num = ( ( $result['action'] ?? '' ) === 'sms_verify_self_validation' ) ? $try_num : $try_num + 1;

		if ( empty( $code ) ) {
			$code = $this->rand_mask( rgar( $field, 'sms_verify_code_type_rand' ) );
		}

		$allowed_try = rgar( $field, 'sms_verify_try_num', 3 );
		$allowed_try = $allowed_try ? ( $allowed_try - 1 ) : 10;

		$submitted_code = rgpost( 'input_' . str_replace( '.', '_', $field['id'] ) );

		if ( $try_num <= $allowed_try && $submitted_code == $code ) {

			$this->save_successful_verification( [
				'id'          => $id,
				'form_id'     => $form_id,
				'mobile'      => $mobile,
				'code'        => $code,
				'new_try_num' => $new_try_num,
				'sent_num'    => $sent_num
			] );

			return $this->result( $result, true );
		}

		if ( $status !== 0 ) {
			return $this->result( $result, true );
		}

		$result['is_valid'] = false;

		if ( $try_num >= $allowed_try ) {

			if ( $id ) {
				GFPersian_SMS_DB::update_verify( [ 'id' => $id, 'try_num' => $new_try_num, 'sent_num' => $sent_num ] );
			}

			$result['message'] = 'تعداد دفعات مجاز بررسی کد تایید تلفن همراه به پایان رسیده است.';

			return $this->result( $result, false );
		}

		$result = $this->send_sms( [
			'result'         => $result,
			'field'          => $field,
			'form_id'        => $form_id,
			'id'             => $id,
			'code'           => $code,
			'try_num'        => $try_num,
			'new_try_num'    => $new_try_num,
			'sent_num'       => $sent_num,
			'mobiles_object' => $mobiles_object,
		] );

		return $this->result( $result, true );
	}

	private function save_successful_verification( array $data ): void {
		extract( $data );

		if ( $id ) {
			GFPersian_SMS_DB::update_verify( [
				'id'       => $id,
				'try_num'  => $new_try_num,
				'sent_num' => $sent_num,
				'status'   => 1,
			] );

			return;
		}

		GFPersian_SMS_DB::insert_verify( [
			'form_id'     => $form_id,
			'mobile'      => $mobile,
			'verify_code' => $code,
			'status'      => 1,
			'try_num'     => $new_try_num,
			'sent_num'    => $sent_num,
		] );
	}

	private function send_sms( array $data ): array {
		extract( $data );

		$message = rgar( $field, 'sms_verify_code_msg_body' ) ?? $code;
		$message = ! str_contains( $message, '%code%' ) ? $message . ' %code%' : $message;
		$message = str_replace( '%code%', $code, $message );
		// @todo : How user gets out of the blacklist?
		$result['message'] = 'کاربر گرامی، تعداد تلاش شما برای دریافت کد تایید به حداکثر مجاز رسیده است.';

		$allowed_send = rgar( $field, 'sms_verify_sent_num', 3 );
		if ( $sent_num < $allowed_send ) {
			add_filter( 'sms_verify_resend', '__return_true', 99 );
			$result['message'] = 'جهت تایید تلفن همراه، کد تایید پیامک شده را وارد کنید.';
		}

		$data = [
			'message'      => $message,
			'mobile'       => $mobiles_object->get_recipients(),
			'form_id'      => $form_id,
			'verify_code'  => $code,
			'country_code' => $mobiles_object->get_country_code(),
		];

		if ( ! $id ) {

			try {

				GFPersian_SMS_Sender::send( $data );
				$data['try_num']     = $try_num;
				$data['sent_num']    = $sent_num + 1;
				$data['verify_code'] = $code;
				GFPersian_SMS_DB::insert_verify( $data );

			} catch ( Exception $e ) {
				$result['message'] = $e->getMessage();
			}

			return $result;
		}

		if ( ! rgempty( 'input_' . $field['id'] ) ) {

			$data['id']       = $id;
			$data['try_num']  = $new_try_num;
			$data['sent_num'] = $sent_num;
			GFPersian_SMS_DB::update_verify( $data );

			$result['message'] = 'کد تایید وارد شده صحیح نمی‌باشد.';

		}

		return $result;
	}

	public function result( array $result, bool $show_input ): array {

		if ( $result['is_valid'] ?? false ) {
			add_filter( 'gform_button_verify', '__return_false', 99 );

			return $result;
		}

		add_filter( 'gform_validation_message', [ $this, 'change_message' ], 1, 2 );
		add_filter( 'sms_verify_display_none', '__return_false', 99 );

		if ( $show_input ) {
			add_filter( 'sms_verify_field', '__return_true', 99 );
		}

		if ( ( $result['action'] ?? '' ) === 'sms_verify_self_validation' ) {
			$result['message_'] = $result['message'] ?? '';

			return $result;
		}

		add_filter( 'sms_verify_self_validation', '__return_false', 99 );

		return $result;
	}

	public function process( array $entry, array $form ): array {
		global $wpdb;

		$sms_verification = GFCommon::get_fields_by_type( $form, [ 'sms_verification' ] );
		if ( ! is_array( $sms_verification ) || empty( $sms_verification ) ) {
			return $entry;
		}

		foreach ( $sms_verification as $field_obj ) {
			$field  = (array) $field_obj;
			$mobile = $this->get_mobile( $field );

			$verification_row = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM %i WHERE mobile = %s AND form_id = %d AND entry_id = 0 ORDER BY id DESC LIMIT 1",
				GFPersian_SMS_DB::$verification_table,
				$mobile,
				intval( $form['id'] )
			) );

			if ( ! $verification_row ) {
				continue;
			}

			$field_id           = $field['id'];
			$verify_code        = $verification_row->code;
			$entry[ $field_id ] = $verify_code;

			$data = [
				'id'          => intval( $verification_row->id ),
				'form_id'     => intval( $form['id'] ),
				'entry_id'    => intval( $entry['id'] ),
				'verify_code' => $verify_code,
				'try_num'     => $verification_row->try_num,
				'sent_num'    => $verification_row->sent_num,
				'status'      => 1,
			];

			GFPersian_SMS_DB::update_entry_verify_sent( $data );
			GFPersian_SMS_DB::update_verify( $data );

			GFAPI::update_entry_field( $entry['id'], $field_id, $verify_code );
		}

		return $entry;
	}

	public function editor_js() {
		$settings = GFPersian_SMS::get_options();
		?>
		<script type='text/javascript'>
            jQuery(document).ready(function ($) {
                fieldSettings['sms_verification'] = '.label_setting, .placeholder_setting, .label_placement_setting, .conditional_logic_field_setting, .admin_label_setting, .size_setting, .default_value_setting, .css_class_setting, .sms_verification_setting, .field_sms_verify_mobile, .sms_country_code';

                function populate_mobile_select() {
                    populate_select(
                        "select[id^=field_sms_verify_mobile]",
                        function (field) {
                            return field.type === 'phone';
                        },
                        'لطفا یک ورودی تلفن را انتخاب نمایید.'
                    );
                }

                function populate_country_code_select() {
                    populate_select(
                        "select[id^=field_sms_verify_country_code_dynamic]",
                        function (field) {
                            return ['select', 'text', 'number', 'checkbox', 'radio', 'hidden'].includes(field.type);
                        },
                        'لطفا یک ورودی کد کشور را انتخاب نمایید.'
                    );
                }

                function populate_select(selector, field_filter_callback, default_option_text) {
                    const options = [
                        "<option value=''>" + default_option_text + "</option>"
                    ];

                    $.each(window.form.fields, function (i, field) {
                        if (!field_filter_callback(field)) {
                            return true;
                        }

                        options.push.apply(options, create_field_options(field));
                    });

                    $(selector).html(options.join(""));
                }

                function create_field_options(field) {
                    const options = [];

                    if (field.inputs) {
                        $.each(field.inputs, function (j, input) {
                            options.push(
                                "<option value='" + input.id + "'>" +
                                field.label + " (" + input.label + ") (ID: " + input.id + ")" +
                                "</option>"
                            );
                        });

                        return options;
                    }

                    options.push(
                        "<option value='" + field.id + "'>" +
                        field.label + " (ID: " + field.id + ")" +
                        "</option>"
                    );

                    return options;
                }

                $(document).on(
                    "gform_field_deleted gform_field_added",
                    function () {
                        populate_mobile_select();
                        populate_country_code_select();
                    }
                );

                populate_mobile_select();
                populate_country_code_select();

                $(document).on("gform_load_field_settings", function (event, field, form) {

                    let sms_verify_country_code_radio_dynamic_el = $("#sms_verify_country_code_radio_dynamic");
                    let sms_verify_country_code_radio_el = $("#sms_verify_country_code_radio");
                    let sms_verify_country_code_div_el = $("#sms_verify_country_code_div");
                    let field_sms_verify_country_code_dynamic_div_el = $("#field_sms_verify_country_code_dynamic_div");

                    if (field.sms_verify_country_code_radio === 'dynamic') {
                        sms_verify_country_code_radio_dynamic_el.prop("checked", true);
                        sms_verify_country_code_div_el.hide("slow");
                        field_sms_verify_country_code_dynamic_div_el.show("slow");
                    } else {
                        sms_verify_country_code_radio_el.prop("checked", true);
                        sms_verify_country_code_div_el.show("slow");
                        field_sms_verify_country_code_dynamic_div_el.hide("slow");
                    }

                    $('input[name="sms_verify_country_code_radio"]').off("click").on("click", function () {
                        if ($(this).val() === 'dynamic') {
                            sms_verify_country_code_div_el.hide("slow");
                            field_sms_verify_country_code_dynamic_div_el.show("slow");
                        } else {
                            sms_verify_country_code_div_el.show("slow");
                            field_sms_verify_country_code_dynamic_div_el.hide("slow");
                        }
                    });

                    let field_sms_verify_mobile_el = $("#field_sms_verify_mobile");
                    let sms_verify_try_num_el = $("#sms_verify_try_num");
                    let sms_verify_sent_num_el = $("#sms_verify_sent_num");
                    let sms_verify_code_type_rand_el = $("#sms_verify_code_type_rand");
                    let sms_verify_country_code_el = $('#sms_verify_country_code');
                    let field_sms_verify_country_code_dynamic_el = $("#field_sms_verify_country_code_dynamic");
                    let sms_verify_code_msg_body_el = $("#sms_verify_code_msg_body");
                    let sms_verify_code_white_list_el = $("#sms_verify_code_white_list");
                    let sms_verify_code_all_fields_el = $("#sms_verify_code_all_fields");

                    field_sms_verify_mobile_el.val(field["field_sms_verify_mobile"]);
                    sms_verify_try_num_el.val(field["sms_verify_try_num"]);
                    sms_verify_sent_num_el.val(field["sms_verify_sent_num"]);
                    sms_verify_code_type_rand_el.val(field["sms_verify_code_type_rand"]);
                    sms_verify_country_code_el.val(
                        typeof field.sms_verify_country_code === "undefined"
                            ? <?php echo ! empty( $settings ) && ! empty( $settings["code"] ) ? esc_js( $settings["code"] ) : "''"; ?>
                            : field.sms_verify_country_code
                    );
                    field_sms_verify_country_code_dynamic_el.val(field["field_sms_verify_country_code_dynamic"]);
                    sms_verify_code_msg_body_el.val(field["sms_verify_code_msg_body"]);
                    sms_verify_code_white_list_el.val(field["sms_verify_code_white_list"]);
                    sms_verify_code_all_fields_el.prop("checked", field["sms_verify_code_all_fields"] === true);

                    $.each(<?php echo wp_json_encode( $this->related_form_fields() ); ?>, function (i, field_name) {
                        $("#field_sms_verify_" + field_name).val(field["field_sms_verify_" + field_name]);
                    });

                });

            });
		</script>
		<?php
	}

	public function tooltips( $tooltips ) {

		$tooltips['form_gravity_sms_fields']        = '<h6>پیامک گرویتی</h6>فیلدهای';
		$tooltips['sms_verify_code_type_select']    = 'شما می‌توانید تعیین کنید که کدهای فعال‌سازی خود را چگونه می‌خواهید در نظر گرفته شوند. توجه داشته باشید که در نوع دستی، هر کد ممکن است به چندین نفر ارسال شود.';
		$tooltips['sms_verify_mobile']              = '<h6>فیلد موبایل</h6>فیلد شماره موبایل را برای تایید انتخاب کنید.';
		$tooltips['sms_verify_code_msg_body']       = '<h6>متن پیامک</h6>متن پیامک حاوی کد فعال‌سازی را وارد کنید. همچنین برای کد فعال‌سازی، از کد کوتاه داده شده استفاده کنید.';
		$tooltips['sms_verify_try_num']             = 'تعداد دفعاتی را که یک شماره مجاز است در این فرم کد اشتباه وارد کند، تعیین کنید.';
		$tooltips['sms_verify_sent_num']            = 'تعیین کنید که یک شماره چند بار مجاز است درخواست کد فعالسازی را در این فرم بدهد.';
		$tooltips['sms_verify_all_fields']          = 'با فعال‌سازی این بخش، محتوای این فیلد از تگ "all_fields" پنهان خواهد شد.';
		$tooltips['sms_verify_country_code_select'] = '<h6>کد کشور</h6>شما می‌توانید کد کشور پیش‌فرض را تغییر دهید، اما اگر شماره موبایل وارد شده به فرمت بین‌المللی باشد، این کد کشور تاثیری نخواهد داشت.';
		$tooltips['sms_verify_code_white_list']     = '<h6>فهرست سفید</h6>شماره‌هایی را وارد کنید که نیازی به تأیید ندارند.';

		return $tooltips;
	}

	public function standard_settings( $position, $form_id ) {

		if ( $position == 50 ) { ?>

			<li class="sms_verification_setting field_setting">

				<div class="field_sms_verify_mobile">
					<br/>
					<label for="field_sms_verify_mobile">
						فیلد تلفن همراه
						<?php gform_tooltip( 'sms_verify_mobile' ) ?>
					</label>
					<select id="field_sms_verify_mobile"
					        onchange="SetFieldProperty('field_sms_verify_mobile', this.value);"
					        required></select>
				</div>

				<div class="sms_country_code">
					<br/>
					<label>
						کد کشور
						<?php gform_tooltip( 'sms_verify_country_code_select' ); ?>
					</label>
					<div>
						<input type="radio" name="sms_verify_country_code_radio"
						       id="sms_verify_country_code_radio" size="10" value="static"
						       onclick="SetFieldProperty('sms_verify_country_code_radio', this.value);"/>
						<label for="sms_verify_country_code_radio" class="inline">
							ایستا
						</label>

						<input type="radio" name="sms_verify_country_code_radio"
						       id="sms_verify_country_code_radio_dynamic" size="10" value="dynamic"
						       onclick="SetFieldProperty('sms_verify_country_code_radio', this.value);"/>
						<label for="sms_verify_country_code_radio_dynamic" class="inline">
							پویا
						</label>
					</div>

					<div id="sms_verify_country_code_div">
						<input id="sms_verify_country_code" name="sms_verify_country_code" type="text"
						       size="35" style="direction:ltr !important;text-align:left;"
						       onkeyup="SetFieldProperty('sms_verify_country_code', this.value);">
					</div>

					<div id="field_sms_verify_country_code_dynamic_div">
						<select id="field_sms_verify_country_code_dynamic"
						        onchange="SetFieldProperty('field_sms_verify_country_code_dynamic', this.value);"
						>
						</select>
					</div>
				</div>

				<div class="sms_verify_type_div">
					<br/>
					<label>
						چگونه کدهای تأیید را وارد کنیم؟
						<?php gform_tooltip( 'sms_verify_code_type_select' ); ?>
					</label>

					<div id="sms_verify_code_type_rand_div">
						<input id="sms_verify_code_type_rand" name="sms_verify_code_type_rand" type="text" size="35"
						       style="direction:ltr !important;text-align:left;"
						       onkeyup="SetFieldProperty('sms_verify_code_type_rand', this.value);">
						<p class="mask_text_description_" style="margin: 5px 0px 0px;">
							<?php esc_html_e( 'Enter a custom mask', 'gravityforms' ) ?>.
							<a onclick="tb_show('<?php echo esc_attr__( 'Custom Mask Instructions', 'gravityforms' ) ?>', '#TB_inline?width=350&inlineId=custom_mask_instructions', '');"
							   href="javascript:void(0);"><?php esc_html_e( 'Help', 'gravityforms' ) ?></a>
						</p>
					</div>

				</div>

				<div id="sms_verify_code_msg_body_div">
					<br/>
					<label for="sms_verify_code_msg_body">
						متن پیامک
						<?php gform_tooltip( 'sms_verify_code_msg_body' ); ?>
					</label>
					<textarea id="sms_verify_code_msg_body" class="fieldwidth-1"
					          onkeyup="SetFieldProperty('sms_verify_code_msg_body', this.value);"></textarea>
					<span class="description">کد تایید = <code>%code%</code></span>
				</div>

				<div class="sms_verify_try_num_div">
					<br/>
					<label for="sms_verify_try_num">
						حداکثر تعداد تلاش تایید کد
						<?php gform_tooltip( 'sms_verify_try_num' ); ?>
					</label>
					<input type="text" size="35" id="sms_verify_try_num" value="3"
					       onkeyup="SetFieldProperty('sms_verify_try_num', this.value || 3);"/>
				</div>

				<div class="sms_verify_sent_num_div">
					<br/>
					<label for="sms_verify_sent_num">
						حداکثر تعداد ارسال مجدد کد
						<?php gform_tooltip( 'sms_verify_sent_num' ); ?>
					</label>
					<input type="text" size="35" id="sms_verify_sent_num" value="3"
					       onkeyup="SetFieldProperty('sms_verify_sent_num', this.value || 3);"/>
				</div>

				<div class="sms_verify_code_all_fields_div">
					<br/>
					<input type="checkbox" id="sms_verify_code_all_fields"
					       onclick="SetFieldProperty('sms_verify_code_all_fields', this.checked);"/>
					<label for="sms_verify_code_all_fields" class="inline">
						پنهان کردن از تگ مرج {all_fields}
						<?php gform_tooltip( 'sms_verify_all_fields' ); ?>
					</label>
				</div>

				<div id="sms_verify_code_white_list_div">
					<br/>
					<label for="sms_verify_code_white_list">
						شماره های موبایل مستثنی شده
						<?php gform_tooltip( 'sms_verify_code_white_list' ); ?>
					</label>
					<textarea id="sms_verify_code_white_list" style="text-align:left;direction:ltr !important;"
					          class="fieldwidth-1"
					          onkeyup="SetFieldProperty('sms_verify_code_white_list', this.value);"
					></textarea>
					<span class="description">شماره ها را با ویرگول جدا کنید</span>
				</div>

			</li>
			<?php
		}
	}

	public function related_form_fields() {
		return [ 'mobile', 'country_code_dynamic' ];
	}

	public function rand_str( $type = 2 ) {
		$alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$numbers  = $type == 1 ? '0123456789' : '';
		$rand     = str_split( str_shuffle( $alphabet . $numbers ) );

		return $rand[ rand( 0, count( $rand ) - 1 ) ];
	}

	public function rand_mask( $mask ) {

		if ( empty( $mask ) ) {
			return rand( 10000, 99999 );
		}

		$all_str = str_split( $mask );
		$code    = '';

		foreach ( (array) $all_str as $str ) {

			if ( $str == '*' ) {
				$code .= $this->rand_str( 1 );
			} elseif ( $str == 'a' ) {
				$code .= $this->rand_str( 2 );
			} elseif ( $str == '9' ) {
				$code .= rand( 0, 9 );
			} else {
				$code .= $str;
			}

		}

		return $code;
	}

	public function country_code( $field ) {
		$field     = (array) $field;
		$code_type = rgar( $field, 'sms_verify_country_code_radio' );

		if ( $code_type == 'dynamic' ) {

			$code = rgar( $field, 'field_sms_verify_country_code_dynamic' );
			$code = str_replace( '.', '_', $code );
			$code = "input_{$code}";
			$code = ! rgempty( $code ) ? sanitize_text_field( rgpost( $code ) ) : '';

		} else {
			$code = rgar( $field, 'sms_verify_country_code' );
		}

		return $code;
	}

	public function get_mobile( $field ) {
		$field  = (array) $field;
		$mobile = rgar( $field, 'field_sms_verify_mobile' );
		$mobile = str_replace( '.', '_', $mobile );
		$mobile = "input_{$mobile}";
		$mobile = ! rgempty( $mobile ) ? sanitize_text_field( rgpost( $mobile ) ) : '';

		return Mobile::format( $mobile, $this->country_code( $field ) );
	}

	public function white_list( $field ) {
		$field   = (array) $field;
		$numbers = rgar( $field, 'sms_verify_code_white_list' );

		return ( new Mobile( $numbers, $this->country_code( $field ) ) )->get_recipients();
	}

	public function submit_button( $button, $form ) {
		unset( $form['button']['text'] );
		$text = apply_filters( 'sms_verification_button', 'تایید شماره تلفن', $button, $form );

		if ( is_callable( [ 'GFFormDisplay', 'get_form_button' ] ) ) {
			return GFFormDisplay::get_form_button( $form['id'], "gform_submit_button_{$form['id']}", $form['button'], $text, 'gform_button', $text, 0 );
		}

		return $this->get_form_button( $form['id'], "gform_submit_button_{$form['id']}", $form['button'], $text, 'gform_button', $text, 0 );

	}

	public function next_button( $button, $form ) {
		unset( $form['button']['text'] );
		$text  = apply_filters( 'sms_verification_button', 'تایید شماره تلفن', $button, $form );
		$field = GFCommon::get_fields_by_type( $form, [ 'page' ] );

		if ( is_callable( [ 'GFFormDisplay', 'get_form_button' ] ) ) {
			return GFFormDisplay::get_form_button( $form['id'], "gform_next_button_{$form['id']}_{$field->id}", $field->nextButton, $text, 'gform_next_button', $text, $field->pageNumber );
		} else {
			return $this->get_form_button( $form['id'], "gform_next_button_{$form['id']}_{$field->id}", $field->nextButton, $text, 'gform_next_button', $text, $field->pageNumber );
		}
	}

	public function change_message( $message, $form ): string {
		return "<div class='validation_error'>برای ادامه، کد تایید پیامک شده را وارد کنید و مجددا فرم را ثبت نمایید.</div>";
	}

	public function all_fields( $value, $merge_tag, $modifier, $field ) {
		if ( $merge_tag === 'all_fields' && $field->type === 'sms_verification' && rgar( $field, 'sms_verify_code_all_fields' ) ) {
			return false;
		}

		return $value;
	}

	public function get_form_button( $form_id, $button_input_id, $button, $default_text, $class, $alt, $target_page_number, $onclick = '' ) {

		$tabindex = GFCommon::get_tabindex();

		$input_type = 'submit';

		if ( ! empty( $target_page_number ) ) {
			$onclick    = "onclick='jQuery(\"#gform_target_page_number_{$form_id}\").val(\"{$target_page_number}\"); {$onclick} jQuery(\"#gform_{$form_id}\").trigger(\"submit\",[true]); '";
			$input_type = 'button';
		} else {
			$set_submitting = "if( !jQuery(\"#gform_{$form_id}\")[0].checkValidity || jQuery(\"#gform_{$form_id}\")[0].checkValidity()){window[\"gf_submitting_{$form_id}\"]=true;}";

			$onclick_submit = $button['type'] == 'link' ? "jQuery(\"#gform_{$form_id}\").trigger(\"submit\",[true]);" : '';

			$onclick = "onclick='if(window[\"gf_submitting_{$form_id}\"]){return false;}  {$set_submitting} {$onclick} {$onclick_submit}'";
		}

		if ( rgar( $button, 'type' ) == 'text' || rgar( $button, 'type' ) == 'link' || empty( $button['imageUrl'] ) ) {

			$button_text = ! empty( $button['text'] ) ? $button['text'] : $default_text;
			if ( rgar( $button, 'type' ) == 'link' ) {
				$button_input = "<a href='javascript:void(0);' id='{$button_input_id}_link' class='{$class}' {$tabindex} {$onclick}>{$button_text}</a>";
			} else {
				$class        .= ' button';
				$button_input = "<input type='{$input_type}' id='{$button_input_id}' class='{$class}' value='" . esc_attr( $button_text ) . "' {$tabindex} {$onclick} />";
			}

		} else {

			$imageUrl     = $button['imageUrl'];
			$class        .= ' gform_image_button';
			$button_input = "<input type='image' src='{$imageUrl}' id='{$button_input_id}' class='{$class}' alt='{$alt}' {$tabindex} {$onclick} />";

		}

		return $button_input;
	}

	public function external_js( array $form, bool $ajax ): void {
		$fields = GFCommon::get_fields_by_type( $form, [ 'sms_verification' ] );

		if ( empty( $fields ) ) {
			return;
		}

		$min = wp_scripts_get_suffix();

		wp_enqueue_script( 'gf-sms-verification-field', GF_PERSIAN_URL . "assets/js/verification-field{$min}.js", [ 'jquery' ], GF_PERSIAN_VERSION, true );

		wp_localize_script( 'gf-sms-verification-field', 'gf_persian_sms_verification', [
			'root'  => esc_url_raw( rest_url( 'gf-persian/sms-verification/' ) ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
		] );
	}

	public function register_rest_routes(): void {
		register_rest_route( 'gf-persian/sms-verification', '/resend', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_rest_resend' ],
			'permission_callback' => [ $this, 'permission_callback' ]
		] );
	}

	public function handle_rest_resend( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$params       = $request->get_json_params();
		$form_id      = isset( $params['form_id'] ) ? intval( $params['form_id'] ) : 0;
		$field_id     = isset( $params['field_id'] ) ? intval( $params['field_id'] ) : 0;
		$mobile_value = isset( $params['mobile_value'] ) ? sanitize_text_field( $params['mobile_value'] ) : '';

		if ( empty( $mobile_value ) ) {
			return new WP_REST_Response( [ 'message' => 'لطفا ابتدا شماره موبایل خود را وارد کنید.' ], 400 );
		}

		$form  = GFAPI::get_form( $form_id );
		$field = RGFormsModel::get_field( $form, $field_id );

		$mobile         = Mobile::format( $mobile_value, $this->country_code( $field ) );
		$mobiles_object = new Mobile( $mobile, $this->country_code( $field ) );

		if ( ! $mobiles_object->is_valid() ) {
			return new WP_REST_Response( [ 'message' => 'تلفن همراه وارد شده، معتبر نمی‌باشد.' ], 400 );
		}

		$verification_row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM %i WHERE mobile = %s AND form_id = %d AND entry_id = 0 ORDER BY id DESC LIMIT 1",
			GFPersian_SMS_DB::$verification_table,
			$mobiles_object->get_recipients_string(),
			$form_id
		) );


		if ( ! is_object( $verification_row ) ) {
			return new WP_REST_Response( [ 'message' => 'رکورد معتبری برای این شماره یافت نشد. فرم را مجدد بررسی کنید.' ], 400 );
		}

		$id           = intval( $verification_row->id );
		$code         = $verification_row->code;
		$sent_num     = intval( $verification_row->sent_num );
		$try_num      = intval( $verification_row->try_num );
		$allowed_send = rgar( $field, 'sms_verify_sent_num', 3 );

		if ( $sent_num >= $allowed_send ) {
			return new WP_REST_Response( [ 'message' => 'تعداد دفعات مجاز ارسال پیامک کد تایید به پایان رسیده است.' ], 429 );
		}

		$message = rgar( $field, 'sms_verify_code_msg_body' ) ?? $code;
		$message = ! str_contains( $message, '%code%' ) ? $message . ' %code%' : $message;
		$message = str_replace( '%code%', $code, $message );

		$data = [
			'id'           => $id,
			'message'      => $message,
			'mobile'       => $mobiles_object->get_recipients(),
			'form_id'      => $form_id,
			'verify_code'  => $code,
			'country_code' => $mobiles_object->get_country_code(),
			'try_num'      => $try_num,
			'sent_num'     => $sent_num + 1,
		];

		try {
			GFPersian_SMS_Sender::send( $data );
			GFPersian_SMS_DB::update_verify( $data );

			return new WP_REST_Response( [ 'message' => 'کد تایید با موفقیت مجددا پیامک شد.' ], 200 );
		} catch ( Exception $e ) {
			return new WP_REST_Response( [ 'message' => $e->getMessage() ], 500 );
		}
	}

	public function permission_callback( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'rest_forbidden', 'درخواست نامعتبر است (خطای اعتبارسنجی امنیتی).', [ 'status' => 403 ] );
		}

		return true;
	}
}

new GFPersian_SMS_Verification();