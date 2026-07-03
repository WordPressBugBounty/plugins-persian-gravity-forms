<?php

namespace PersianGravityForms\Objects;

use GFPersian_SMS;
use PersianGravityForms\Helpers\Number;

class Mobile {
	public array $mobiles = [];
	public ?string $country_code = null;

	public function __construct( $mobiles, ?string $country_code = null ) {
		$this->set_country_code( $country_code );
		$this->format_multiple( $mobiles );
	}

	public function set_country_code( ?string $country_code = null ) {
		$this->country_code = ! empty( $country_code ) ? $country_code : self::get_default_country_code();
	}

	public function get_country_code(): ?string {
		return self::clean_country_code( $this->country_code ?? self::get_default_country_code() );
	}

	public static function get_default_country_code() {
		return GFPersian_SMS::_option( 'sms_country_code', '98' );
	}

	public function format_multiple( $mobiles ): void {
		$mobiles = $this->prepare_list( $mobiles );

		$this->mobiles = array_filter( array_values( array_map( function ( $mobile ) {
			return self::format( $mobile, $this->country_code );
		}, $mobiles ) ) );
	}

	public function prepare_list( $mobiles ): array {
		if ( is_array( $mobiles ) ) {
			return $mobiles;
		}

		if ( is_string( $mobiles ) && str_contains( $mobiles, ',' ) ) {

			return array_map( 'trim', explode( ',', $mobiles ) );
		}

		return [ strval( $mobiles ) ];
	}

	public static function format( string $mobile, string $country_code ): string {
		$mobile = Number::en( trim( $mobile ) );

		if ( empty( $mobile ) ) {
			return '';
		}

		$country_code = self::clean_country_code( $country_code );

		preg_match_all( '/\d+/', $mobile, $matches );
		$phone = ! empty( $matches[0] ) ? implode( '', $matches[0] ) : '';

		if ( empty( $phone ) ) {
			return '';
		}

		if ( str_contains( $mobile, '+' ) || str_contains( $mobile, '%2B' ) ) {
			return '+' . $phone;
		}

		if ( str_starts_with( $phone, '00' ) ) {
			return '+' . substr( $phone, 2 );
		}

		if ( str_starts_with( $phone, '0' ) ) {
			$phone = substr( $phone, 1 );
		}

		if ( ! str_starts_with( $phone, $country_code ) ) {
			$phone = $country_code . $phone;
		}

		return '+' . $phone;
	}

	public function is_valid(): bool {
		if ( empty( $this->mobiles ) ) {
			return false;
		}

		foreach ( $this->mobiles as $mobile ) {

			if ( ! preg_match( '/^(\+989|989|09)[0-9]{9}$/', $mobile ) ) {
				continue;
			}

			return true;
		}

		return false;
	}

	public function get_recipients(): array {
		return array_filter( $this->mobiles );
	}

	public function get_recipients_string(): string {
		return implode( ',', $this->get_recipients() );
	}

	public static function clean_country_code( ?string $code = null ) {
		if ( empty( $code ) ) {
			return self::get_default_country_code();
		}

		$code = Number::en( trim( $code ) );

		if ( str_starts_with( $code, '+' ) ) {
			$code = substr( $code, 1 );
		}

		if ( str_starts_with( $code, '00' ) ) {
			$code = substr( $code, 2 );
		}

		if ( str_starts_with( $code, '0' ) ) {
			$code = substr( $code, 1 );
		}

		return filter_var( $code, FILTER_SANITIZE_NUMBER_INT );
	}
}
