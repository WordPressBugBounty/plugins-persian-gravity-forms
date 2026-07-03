<?php

abstract class GFPersian_SMS_Gateway {

	public string $gateway;
	public ?string $username = null;
	public ?string $password = null;
	public ?string $sender_number = null;
	public ?string $country_code;
	public array $mobiles = [];
	public string $message = '';
	public array $failed_numbers = [];

	/**
	 * @throws Exception
	 */
	public function __construct( $data ) {

		$this->username = $this->get_username();
		$this->password = $this->get_password();

		if ( empty( $this->username ) ) {
			throw new Exception( 'لطفاً مشخصات وبسرویس پیامک را از پیشخوان مدیریت > فرم ها > پیکربندی > گرویتی فرم فارسی، وارد نمایید.' );
		}

		$this->sender_number = $data['sender_number'] ?? '';
		$this->country_code  = $data['country_code'] ?? '';
		$this->mobiles       = $data['mobile'] ?? '';
		$this->message       = wp_strip_all_tags( $data['message'] ?? '' );
	}

	/**
	 * @return mixed
	 *
	 * @throws Exception
	 */
	abstract public function send(): bool;

	abstract public static function name(): string;

	public static function id(): string {
		return get_called_class();
	}

	/**
	 * @throws Exception
	 */
	public function format_failed_numbers(): bool {

		if ( empty( $this->failed_numbers ) ) {
			return true;
		}

		$grouped = [];

		foreach ( $this->failed_numbers as $number => $message ) {

			if ( isset( $grouped[ $message ] ) ) {
				$grouped[ $message ] = $number . ', ' . $grouped[ $message ];
			} else {
				$grouped[ $message ] = $number . ': ' . $message;
			}

		}

		throw new Exception( implode( ' | ', array_values( $grouped ) ) );
	}

	public function is_pattern(): bool {
		return str_starts_with( $this->message, 'pattern:' ) || str_starts_with( $this->message, 'pcode:' ) || str_starts_with( $this->message, 'patterncode:' );
	}

	public function parse_pattern(): array {

		$result = [
			'code' => '',
			'vars' => [],
		];

		$message = str_replace( [ "\r\n", "\n", "\\r\\n", "\\n" ], '~', $this->message );
		$parts   = explode( '~', $message );

		foreach ( $parts as $part ) {

			[ $key, $value ] = explode( ':', $part, 2 );

			$key   = trim( $key, "}{% \n\r\t\v\x00" );
			$value = trim( $value );

			if ( in_array( $key, [ 'pattern', 'pcode', 'patterncode' ] ) ) {
				$result['code'] = $value;
			} elseif ( strlen( $key ) ) {
				$result['vars'][ $key ] = $value;
			}

		}

		return $result;
	}

	public static function get_sms_gateway( $data ): self {

		$active_gateway = GFPersian_SMS::_option( 'sms_gateway' );

		if ( ! $active_gateway || ! class_exists( $active_gateway ) || ! is_subclass_of( $active_gateway, self::class ) ) {
			$active_gateway = GFPersian_SMS_PGFLOG::class;
		}

		return new $active_gateway( $data );
	}

	public static function get_sender_number(): string {
		return GFPersian_SMS::_option( 'sms_from_numbers', '' );
	}

	protected function get_username(): string {
		return GFPersian_Core::_option( 'sms_username', '' );
	}

	protected function get_password(): string {
		return GFPersian_Core::_option( 'sms_password', '' );
	}

	public function get_token(): string {
		$username = trim( $this->username );
		$password = trim( $this->password );
		if ( empty( $username ) && empty( $password ) ) {
			return '';
		}
		if ( ! empty( $username ) ) {
			return $username;
		}

		return $password;
	}
}