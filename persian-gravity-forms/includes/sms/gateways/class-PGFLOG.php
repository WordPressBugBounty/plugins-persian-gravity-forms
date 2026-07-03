<?php

defined( 'ABSPATH' ) || exit;

class GFPersian_SMS_PGFLOG extends GFPersian_SMS_Gateway {

	public static function id(): string {
		return 'logger';
	}

	public static function name(): string {
		return 'pgf.log - مخصوص وبمستران و توسعه دهندگان';
	}

	public function send(): bool {

		$this->log_variables( [
			'username'      => $this->username,
			'password'      => $this->password,
			'sender_number' => $this->sender_number,
			'mobile'        => $this->mobiles,
			'message'       => $this->message,
		] );

		return true;
	}

	public function log_variables( $args ) {

		self::log( PHP_EOL . '######## ' . date( 'Y-m-d H:i:s' ) );

		foreach ( $args as $key => $value ) {
			self::log( "$key: " . print_r( $value, true ) );
		}

	}

	public function log( $message ) {
		$log_file =  wp_upload_dir()['basedir'] . '/gravity_forms/logs/pgf.log';

		if ( ! file_exists($log_file) ) { 
			wp_mkdir_p( dirname( $log_file ) );
			touch( $log_file );
		}

		error_log( $message . PHP_EOL, 3, $log_file);
	}
}
