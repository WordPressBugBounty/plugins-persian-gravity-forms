<?php

defined( 'ABSPATH' ) || exit;

class GFPersian_SMS_DB {

	/**
	 * @var string sms table name
	 */
	public static string $sms_table;

	/**
	 * @var string sms verification table
	 */
	public static string $verification_table;

	/**
	 * @var string Gravity Forms table
	 */
	public static string $form_table;

	public static function init() {
		global $wpdb;

		self::$sms_table          = $wpdb->prefix . 'gf_sms_sent';
		self::$verification_table = $wpdb->prefix . 'gf_sms_verification';
		self::$form_table         = RGFormsModel::get_form_table_name();
	}

	public static function save_sms_sent( $data ): void {
		global $wpdb;

		if ( empty( $data['entry_id'] ) ) {
			$data['entry_id'] = ! empty( $data['verify_code'] ) ? '_' . $data['verify_code'] . '_' : 0;
		} else {
			$data['entry_id'] = is_array( $data['entry_id'] ) ? implode( ',', $data['entry_id'] ) : $data['entry_id'];
		}

		$data['mobile'] = is_array( $data['mobile'] ) ? implode( ',', $data['mobile'] ) : $data['mobile'];

		$wpdb->insert( self::$sms_table, [
			'date'     => date( 'Y-m-d H:i:s', current_time( 'timestamp', 0 ) ),
			'form_id'  => $data['form_id'] ?? 0,
			'entry_id' => $data['entry_id'],
			'sender'   => $data['sender_number'] ?? 0,
			'reciever' => $data['mobile'] ?? 0,
			'message'  => $data['message'] ?? 0,
		], [
			'%s',  // date
			'%d',  // form_id
			'%s',  // entry_id
			'%s',  // sender
			'%s',  // receiver
			'%s',   // message
		] );

		if ( $wpdb->last_error ) {
			error_log( '[PersianGravityForms][DB] Database Error: ' . $wpdb->last_error );
		}
	}

	/**
	 * @param array $data
	 *
	 * @return false|int
	 */
	public static function update_entry_verify_sent( array $data ) {
		global $wpdb;

		$data['entry_id'] = is_array( $data['entry_id'] ) ? implode( ',', $data['entry_id'] ) : $data['entry_id'];

		$data['verify_code'] = '_' . $data['verify_code'] ?? 0 . '_';

		return $wpdb->update( self::$sms_table, [
			'entry_id' => $data['entry_id'] ?? 0,
		], [
			'form_id'  => $data['form_id'] ?? 0,
			'entry_id' => $data['verify_code'],
		], [ '%s' ], [ '%d', '%s' ] );
	}

	/**
	 * @param array $data
	 *
	 * @return false|int
	 */
	public static function insert_verify( array $data ) {
		global $wpdb;

		$mobile = $data['mobile'] ?? '';

		if ( is_array( $data['mobile'] ) ) {
			$mobile = implode( ',', $data['mobile'] );
		}

		return $wpdb->insert( self::$verification_table, [
			'form_id'  => $data['form_id'] ?? 0,
			'entry_id' => $data['entry_id'] ?? 0,
			'mobile'   => $mobile,
			'code'     => $data['verify_code'] ?? 0,
			'try_num'  => $data['try_num'] ?? 0,
			'sent_num' => $data['sent_num'] ?? 0,
			'status'   => $data['status'] ?? 0,
		], [
			'%d', // form_id
			'%d', // entry_id
			'%s', // mobile
			'%s', // code
			'%d', // try_num
			'%d', // sent_num
			'%d',  // status
		] );
	}

	/**
	 * @param array $data
	 *
	 * @return false|int
	 */
	public static function update_verify( array $data ) {
		global $wpdb;

		if ( empty( $data['id'] ) ) {
			error_log( '[PersianGravityForms][DB]: Theres no ID provided to update verify.' );

			return false;
		}

		return $wpdb->update( self::$verification_table, [
			'entry_id' => $data['entry_id'] ?? 0,
			'try_num'  => $data['try_num'] ?? 0,
			'sent_num' => $data['sent_num'] ?? 0,
			'status'   => $data['status'] ?? 0,
		], [
			'id' => $data['id'],
		], [
			'%s', // entry_id
			'%d', // try_num
			'%d', // sent_num
			'%d',  // status
		], [ '%d' ] // id
		);
	}

	public static function check_sms_sent( $data ): bool {
		global $wpdb;

		$mobile = $data['mobile'] ?? '';

		if ( is_array( $data['mobile'] ) ) {
			$mobile = implode( ',', $data['mobile'] );
		}

		$query = $wpdb->prepare( "SELECT entry_id FROM %i WHERE entry_id = %d AND form_id  = %d AND reciever = %s AND message = %s", [
			self::$sms_table,
			$data['entry_id'] ?? 0,
			$data['form_id'] ?? 0,
			$mobile,
			$data['message'] ?? '',
		] );

		return boolval( $wpdb->get_var( $query ) );
	}

}

GFPersian_SMS_DB::init();