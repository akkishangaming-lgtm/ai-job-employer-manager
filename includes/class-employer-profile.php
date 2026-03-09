<?php
/**
 * Employer Profile CRUD operations.
 *
 * @package AI_Job_Employer_Manager
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AJEM_Employer_Profile
 *
 * Handles all CRUD operations for the employer company profile,
 * including geolocation data, logo upload, and WhatsApp formatting.
 *
 * @since 1.0.0
 */
class AJEM_Employer_Profile {

	/**
	 * Get employer profile by WordPress user ID.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return object|null Profile row or null if not found.
	 */
	public function get_by_user_id( int $user_id ): ?object {
		global $wpdb;

		$table = $wpdb->prefix . 'job_employers';

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE user_id = %d LIMIT 1", $user_id )
		) ?: null;
	}

	/**
	 * Get employer profile by employer (row) ID.
	 *
	 * @param int $employer_id Employer row ID.
	 * @return object|null Profile row or null.
	 */
	public function get_by_id( int $employer_id ): ?object {
		global $wpdb;

		$table = $wpdb->prefix . 'job_employers';

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d LIMIT 1", $employer_id )
		) ?: null;
	}

	/**
	 * Create a new employer profile.
	 *
	 * @param int   $user_id WordPress user ID.
	 * @param array $data    Profile data.
	 * @return int|false Inserted row ID or false on failure.
	 */
	public function create( int $user_id, array $data ): int|false {
		global $wpdb;

		$table    = $wpdb->prefix . 'job_employers';
		$prepared = $this->prepare_data( $data );

		$prepared['user_id'] = $user_id;

		$result = $wpdb->insert( $table, $prepared );

		if ( false === $result ) {
			return false;
		}

		$profile_id = (int) $wpdb->insert_id;

		do_action( 'ajem_employer_profile_updated', $user_id, $profile_id, $prepared );

		return $profile_id;
	}

	/**
	 * Update an existing employer profile.
	 *
	 * @param int   $user_id WordPress user ID.
	 * @param array $data    Updated profile data.
	 * @return bool True on success, false on failure.
	 */
	public function update( int $user_id, array $data ): bool {
		global $wpdb;

		$table    = $wpdb->prefix . 'job_employers';
		$prepared = $this->prepare_data( $data );

		$result = $wpdb->update(
			$table,
			$prepared,
			array( 'user_id' => $user_id ),
			null,
			array( '%d' )
		);

		if ( false !== $result ) {
			$profile = $this->get_by_user_id( $user_id );
			do_action( 'ajem_employer_profile_updated', $user_id, $profile ? $profile->id : 0, $prepared );
		}

		return false !== $result;
	}

	/**
	 * Create or update profile (upsert).
	 *
	 * @param int   $user_id WordPress user ID.
	 * @param array $data    Profile data.
	 * @return int|false Profile ID on success, false on failure.
	 */
	public function save( int $user_id, array $data ): int|false {
		$existing = $this->get_by_user_id( $user_id );

		if ( $existing ) {
			$updated = $this->update( $user_id, $data );
			return $updated ? (int) $existing->id : false;
		}

		return $this->create( $user_id, $data );
	}

	/**
	 * Delete employer profile and all related data.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return bool True on success.
	 */
	public function delete( int $user_id ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'job_employers';

		$result = $wpdb->delete( $table, array( 'user_id' => $user_id ), array( '%d' ) );

		return false !== $result;
	}

	/**
	 * Handle company logo upload via WordPress media uploader.
	 *
	 * @param array $file     $_FILES array element.
	 * @param int   $user_id  Owner user ID.
	 * @return string|WP_Error Attachment URL or WP_Error on failure.
	 */
	public function upload_logo( array $file, int $user_id ): string|WP_Error {
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		if ( ! function_exists( 'media_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}

		// Validate file type.
		$allowed_types = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
		$file_type     = $file['type'] ?? '';

		if ( ! in_array( $file_type, $allowed_types, true ) ) {
			return new WP_Error( 'invalid_file_type', __( 'Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.', 'ai-job-employer-manager' ) );
		}

		$upload_overrides = array( 'test_form' => false );
		$uploaded         = wp_handle_upload( $file, $upload_overrides );

		if ( isset( $uploaded['error'] ) ) {
			return new WP_Error( 'upload_error', $uploaded['error'] );
		}

		$attachment = array(
			'post_mime_type' => $uploaded['type'],
			'post_title'     => sanitize_file_name( $file['name'] ),
			'post_content'   => '',
			'post_status'    => 'inherit',
			'post_author'    => $user_id,
		);

		$attach_id   = wp_insert_attachment( $attachment, $uploaded['file'] );
		$attach_data = wp_generate_attachment_metadata( $attach_id, $uploaded['file'] );
		wp_update_attachment_metadata( $attach_id, $attach_data );

		return wp_get_attachment_url( $attach_id );
	}

	/**
	 * Format and validate a WhatsApp number.
	 *
	 * Strips non-digit characters, ensures it starts with a country code.
	 *
	 * @param string $number Raw phone/WhatsApp number.
	 * @return string Cleaned number (digits only, no leading +).
	 */
	public function format_whatsapp_number( string $number ): string {
		// Remove all non-digit characters.
		$cleaned = preg_replace( '/\D/', '', $number );

		// Ensure it's a reasonable length (7–15 digits per E.164).
		if ( strlen( $cleaned ) < 7 || strlen( $cleaned ) > 15 ) {
			return '';
		}

		return $cleaned;
	}

	/**
	 * Prepare and sanitize profile data for DB insert/update.
	 *
	 * @param array $data Raw input data.
	 * @return array Sanitized data ready for database.
	 */
	private function prepare_data( array $data ): array {
		$prepared = array();

		$text_fields = array(
			'company_name', 'company_logo', 'company_website', 'industry',
			'company_size', 'contact_email', 'contact_phone', 'country',
			'state', 'district', 'city',
		);

		foreach ( $text_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				if ( 'contact_email' === $field ) {
					$prepared[ $field ] = sanitize_email( $data[ $field ] );
				} elseif ( in_array( $field, array( 'company_logo', 'company_website' ), true ) ) {
					$prepared[ $field ] = esc_url_raw( $data[ $field ] );
				} else {
					$prepared[ $field ] = sanitize_text_field( $data[ $field ] );
				}
			}
		}

		if ( isset( $data['company_description'] ) ) {
			$prepared['company_description'] = wp_kses_post( $data['company_description'] );
		}

		if ( isset( $data['contact_phone'] ) ) {
			$prepared['contact_phone'] = sanitize_text_field( $data['contact_phone'] );
		}

		if ( isset( $data['whatsapp_number'] ) ) {
			$prepared['whatsapp_number'] = $this->format_whatsapp_number( $data['whatsapp_number'] );
		}

		if ( isset( $data['founded_year'] ) ) {
			$year = absint( $data['founded_year'] );
			$prepared['founded_year'] = ( $year >= 1800 && $year <= (int) gmdate( 'Y' ) ) ? $year : null;
		}

		if ( isset( $data['latitude'] ) ) {
			$prepared['latitude'] = is_numeric( $data['latitude'] ) ? (float) $data['latitude'] : null;
		}

		if ( isset( $data['longitude'] ) ) {
			$prepared['longitude'] = is_numeric( $data['longitude'] ) ? (float) $data['longitude'] : null;
		}

		return $prepared;
	}
}
