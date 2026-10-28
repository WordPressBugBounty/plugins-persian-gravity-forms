<?php

namespace PersianGravityForms\Payment\Gateways;

use GFAPI;
use GFCommon;
use GFPersian_Core;
use GFPersian_Payments;
use RGFormsModel;

class Zibal {

	private const MAX_RETRIES = 3;

	protected static ?Zibal $_instance = null;

	public static function instance(): ?Zibal {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	private function __construct() {
		add_filter( 'gform_form_settings_fields', [ $this, 'settings_fields' ], 101, 2 );
		add_filter( 'http_request_args', [ $this, 'apply_reseller' ], 10, 2 );
		add_filter( 'gform_confirmation', [ $this, 'request' ], 10001, 4 );
		add_action( 'init', [ $this, 'verify' ], 10 );
	}

	public function settings_fields( array $fields, array $form ): array {

		$gateland_installed = class_exists( 'Nabik\Gateland\Gateland' );

		$new_fields['zibal'] = [
			'title'  => 'درگاه مستقیم زیبال',
			'fields' => [
				[
					'name'          => 'zibal_enable',
					'type'          => 'select',
					'label'         => 'درگاه زیبال برای این فرم فعال باشد؟',
					'default_value' => '0',
					'choices'       => [
						[ 'value' => '0', 'label' => 'خیر' ],
						[ 'value' => '1', 'label' => 'بله' ],
					],
				],
			],
		];

		if ( ! $gateland_installed ) {

			$new_fields['zibal']['fields'][] = [
				'type' => 'html',
				'html' => sprintf(
					'<p>جهت بهره‌مندی از امکانات بیشتر و اتصال به همه‌ درگاه‌های مستقیم، واسط و اعتباری، افزونه گیت‌لند را نصب کنید. <a href="%s">نصب سریع و رایگان از مخزن وردپرس</a></p>',
					esc_url( wp_nonce_url(
						self_admin_url( 'update.php?action=install-plugin&plugin=gateland' ),
						'install-plugin_gateland'
					) )
				),
			];

		}

		return array_merge( array_slice( $fields, 0, 2 ), $new_fields, array_slice( $fields, 2 ) );
	}

	public function apply_reseller( array $args, string $url ): array {

		if (
			! str_contains( $url, 'gateway.zibal.ir/v1/request' )
			&& ! str_contains( $url, 'gateway.zibal.io/v1/request' )
		) {
			return $args;
		}

		$body = json_decode( $args['body'] ?? '', true );

		if ( ! is_array( $body ) ) {
			return $args;
		}

		$body['reseller'] = 'woocommerce';
		$args['body']     = wp_json_encode( $body );

		return $args;
	}

	public function request( $confirmation, array $form, array $entry, bool $ajax ) {

		if ( isset( $_GET['gateway'], $_GET['entry_id'] ) ) {
			return $confirmation;
		}

		if ( empty( $form['zibal_enable'] ) ) {
			return $confirmation;
		}

		$amount = intval( GFCommon::get_order_total( $form, $entry ) );

		if ( $amount <= 0 ) {
			$message = 'مبلغ پرداختی معتبر نمی‌باشد.';
			RGFormsModel::add_note( $entry['id'], get_current_user_id(), 'کاربر', $message );

			return $this->confirmation_message( $form, $message );
		}

		$entry_id = $entry['id'];
		$merchant = trim( GFPersian_Core::_option( 'zibal_merchant' ) );

		$callback_url = add_query_arg( [
			'gateway'  => 'zibal',
			'entry_id' => $entry_id,
			'secure'   => $this->secure_key( $entry_id ),
		], $entry['source_url'] );

		$data = [
			'merchant'    => $merchant,
			'amount'      => GFPersian_Payments::amount( $amount, 'IRR', $entry, $form ),
			'orderId'     => $entry_id,
			'callbackUrl' => $callback_url,
			'description' => sprintf(
				'پرداخت فرم %s - شناسه ورودی %d',
				$form['title'],
				$entry_id
			),
		];

		$response = $this->send_request( 'request', $data );

		if ( empty( $response['trackId'] ) ) {

			$message = 'دریافت شناسه پیگیری از زیبال ناموفق بود.';
			RGFormsModel::add_note( $entry_id, get_current_user_id(), 'کاربر', $message );

			return $this->confirmation_message( $form, $message );
		}

		$entry['payment_status'] = 'Pending';
		$entry['payment_date']   = '';
		$entry['transaction_id'] = $response['trackId'];
		$entry['payment_amount'] = $amount;

		GFAPI::update_entry( $entry );

		return [ 'redirect' => 'https://gateway.zibal.ir/start/' . $response['trackId'] ];
	}

	public function verify(): void {
		if ( ( $_GET['gateway'] ?? '' ) !== 'zibal' || empty( $_GET['entry_id'] ) ) {
			return;
		}

		$entry_id = absint( $_GET['entry_id'] );

		if ( ! $entry_id || ! hash_equals( $this->secure_key( $entry_id ), $_GET['secure'] ?? '' ) ) {
			wp_die( 'کلید امنیتی معتبر نمی‌باشد.', 'خطای پرداخت', [ 'response' => 403 ] );
		}

		$entry = GFAPI::get_entry( $entry_id );

		if ( is_wp_error( $entry ) || empty( $entry['id'] ) ) {
			wp_die( 'ورودی فرم یافت نشد.', 'خطای پرداخت', [ 'response' => 404 ] );
		}

		$form = GFAPI::get_form( intval( $entry['form_id'] ) );

		if ( ! is_array( $form ) || strval( $form['zibal_enable'] ?? '0' ) !== '1' ) {
			wp_die( 'درگاه زیبال برای این فرم فعال نیست.', 'خطای پرداخت', [ 'response' => 400 ] );
		}

		if ( in_array( strval( $entry['payment_status'] ?? '' ), [ 'Paid', 'Failed' ] ) ) {
			GFPersian_Payments::confirmation( $form, $entry, '' );

			return;
		}

		$merchant = trim( GFPersian_Core::_option( 'zibal_merchant' ) );
		$track_id = sanitize_text_field( wp_unslash( $_GET['trackId'] ?? '' ) );
		$success  = absint( $_GET['success'] ?? 0 );

		$was_pending = $entry['payment_status'] === 'Pending';
		$verified    = false;

		if ( $success === 1 && $merchant && $track_id && $entry['transaction_id'] === $track_id ) {

			$result = $this->send_request( 'verify', [
				'merchant'           => $merchant,
				'trackId'            => $track_id,
				'dataOnDoubleVerify' => true,
			] );

			$expected = GFPersian_Payments::amount(
				GFCommon::get_order_total( $form, $entry ),
				'IRR',
				$entry,
				$form
			);

			$verified = is_array( $result )
			            && in_array( $result['result'] ?? 0, [ 100, 201 ] )
			            && ( $result['amount'] ?? - 1 ) == $expected
			            && ( $result['orderId'] ?? '' ) == $entry_id;
		}

		if ( $verified ) {

			$entry['payment_status']      = 'Paid';
			$entry['payment_date']        = current_time( 'mysql' );
			$entry['payment_method']      = 'زیبال';
			$entry['payment_amount']      = GFCommon::get_order_total( $form, $entry );
			$entry['gateway_trans_id']    = sanitize_text_field( strval( $result['refNumber'] ?? $track_id ) );
			$entry['payment_card_number'] = sanitize_text_field( strval( $result['cardNumber'] ?? '' ) );
			gform_update_meta( $entry_id, 'gateway_trans_id', $entry['gateway_trans_id'] );
			gform_update_meta( $entry_id, 'payment_card_number', $entry['payment_card_number'] );

			$message = sprintf(
				'پرداخت با موفقیت انجام شد. شناسه پیگیری زیبال: %s%s',
				esc_html( $track_id ),
				$entry['payment_card_number'] !== '' ? ' | شماره کارت: ' . esc_html( $entry['payment_card_number'] ) : ''
			);
			do_action( 'gform_post_payment_action', $entry, 'complete_payment' );
			$note_type = 'success';

		} else {

			$entry['payment_status'] = 'Failed';
			$message                 = 'پرداخت ناموفق بود یا اطلاعات تراکنش با مبلغ و شناسه سفارش مطابقت نداشت.';
			do_action( 'gform_post_payment_action', $entry, 'fail_payment' );
			$note_type = 'error';

		}

		if ( $was_pending ) {

			RGFormsModel::add_note( $entry_id, 0, 'زیبال', $message, 'notification', $note_type );
			do_action( 'gform_post_payment_action', $entry, 'add_pending_payment' );

		}

		GFAPI::update_entry( $entry, $entry_id );
		$form = GFAPI::get_form( intval( $entry['form_id'] ) );

		GFPersian_Payments::notification( $form, $entry );
		GFPersian_Payments::confirmation( $form, $entry, $message );
	}

	private function send_request( string $action, array $params, int $attempt = 0 ): ?array {

		$response = wp_remote_post(
			'https://gateway.zibal.ir/v1/' . $action,
			[
				'timeout'     => 20,
				'headers'     => [ 'Content-Type' => 'application/json' ],
				'body'        => wp_json_encode( $params ),
				'redirection' => 0,
			]
		);

		if ( is_wp_error( $response ) ) {

			if ( $attempt < self::MAX_RETRIES ) {
				return $this->send_request( $action, $params, $attempt + 1 );
			}

			error_log( 'Gravity Forms Zibal transport error: ' . $response->get_error_message() );

			return null;
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $decoded ) && $attempt < self::MAX_RETRIES ) {
			return $this->send_request( $action, $params, $attempt + 1 );
		}

		return is_array( $decoded ) ? $decoded : null;
	}

	private function secure_key( int $entry_id ): string {
		return hash( 'crc32', $entry_id . wp_salt() );
	}

	private function confirmation_message( array $form, string $message ): string {
		$class = esc_attr( strval( $form['cssClass'] ?? '' ) );
		$theme = function_exists( 'GFFormDisplay' ) ? "data-form-theme='" . esc_attr( \GFFormDisplay::get_form_theme_slug( $form ) ) . "'" : '';
		$html  = sprintf(
			"<div id='gform_confirmation_wrapper_%d' class='form_saved_message_sent gform_confirmation_wrapper %s gform_wrapper' role='alert' %s>%s</div>",
			absint( $form['id'] ?? 0 ),
			$class,
			$theme,
			esc_html( $message )
		);

		return GFCommon::replace_variables( $html, $form, false, true, true, true );
	}

}
