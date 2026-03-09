<?php
/**
 * Location Manager — manages 10,000+ cities, Haversine distances, AJAX autocomplete.
 *
 * @package AI_Job_Employer_Manager
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AJEM_Location_Manager
 *
 * Manages the wp_job_locations table, implements the Haversine formula
 * for distance calculations, provides AJAX autocomplete for location fields,
 * and handles CSV import/export of location data.
 *
 * @since 1.0.0
 */
class AJEM_Location_Manager {

	/**
	 * Earth radius in kilometres.
	 */
	private const EARTH_RADIUS_KM = 6371;

	/**
	 * Register AJAX handlers.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_ajax_ajem_location_search', array( $this, 'ajax_location_search' ) );
		add_action( 'wp_ajax_nopriv_ajem_location_search', array( $this, 'ajax_location_search' ) );
	}

	/**
	 * AJAX handler — location autocomplete search.
	 *
	 * @return void
	 */
	public function ajax_location_search(): void {
		check_ajax_referer( 'ajem_nonce', 'nonce' );

		$query = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) );

		if ( strlen( $query ) < 2 ) {
			wp_send_json_success( array() );
			return;
		}

		$results = $this->search_locations( $query );
		wp_send_json_success( $results );
	}

	/**
	 * Search locations by keyword (city, state, or country).
	 *
	 * @param string $query    Search term.
	 * @param int    $limit    Max results.
	 * @return array Array of matching location objects.
	 */
	public function search_locations( string $query, int $limit = 20 ): array {
		global $wpdb;
		$table     = $wpdb->prefix . 'job_locations';
		$cache_key = 'ajem_loc_search_' . md5( $query . $limit );
		$cached    = get_transient( $cache_key );

		if ( $cached !== false ) {
			return $cached;
		}

		$like = '%' . $wpdb->esc_like( $query ) . '%';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, country, state, district, city, latitude, longitude
				 FROM `{$table}`
				 WHERE is_active = 1
				   AND ( city LIKE %s OR state LIKE %s OR country LIKE %s )
				 ORDER BY city ASC
				 LIMIT %d",
				$like,
				$like,
				$like,
				$limit
			)
		);

		$results = $results ?? array();
		set_transient( $cache_key, $results, 5 * MINUTE_IN_SECONDS );

		return $results;
	}

	/**
	 * Get all active cities in a state.
	 *
	 * @param string $state State name.
	 * @return array
	 */
	public function get_cities_by_state( string $state ): array {
		global $wpdb;
		$table     = $wpdb->prefix . 'job_locations';
		$cache_key = 'ajem_cities_' . md5( $state );
		$cached    = get_transient( $cache_key );

		if ( $cached !== false ) {
			return $cached;
		}

		$cities = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, city, district, latitude, longitude
				 FROM `{$table}`
				 WHERE state = %s AND is_active = 1 AND location_type = 'city'
				 ORDER BY city ASC",
				$state
			)
		);

		$cities = $cities ?? array();
		set_transient( $cache_key, $cities, HOUR_IN_SECONDS );

		return $cities;
	}

	/**
	 * Get coordinates for a specific city.
	 *
	 * @param string $city     City name.
	 * @param string $state    State (optional, improves accuracy).
	 * @param string $country  Country (optional).
	 * @return array{lat: float|null, lng: float|null}
	 */
	public function get_coordinates( string $city, string $state = '', string $country = '' ): array {
		global $wpdb;
		$table  = $wpdb->prefix . 'job_locations';
		$where  = array( 'city = %s', 'is_active = 1' );
		$params = array( $city );

		if ( $state ) {
			$where[]  = 'state = %s';
			$params[] = $state;
		}
		if ( $country ) {
			$where[]  = 'country = %s';
			$params[] = $country;
		}

		$where_sql = implode( ' AND ', $where );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT latitude, longitude FROM `{$table}` WHERE {$where_sql} LIMIT 1",
				...$params
			)
		);

		return array(
			'lat' => $row ? (float) $row->latitude : null,
			'lng' => $row ? (float) $row->longitude : null,
		);
	}

	/**
	 * Calculate distance between two lat/lng points using the Haversine formula.
	 *
	 * @param float $lat1 Origin latitude.
	 * @param float $lng1 Origin longitude.
	 * @param float $lat2 Destination latitude.
	 * @param float $lng2 Destination longitude.
	 * @return float Distance in kilometres.
	 */
	public function haversine( float $lat1, float $lng1, float $lat2, float $lng2 ): float {
		$delta_lat = deg2rad( $lat2 - $lat1 );
		$delta_lng = deg2rad( $lng2 - $lng1 );

		$a = sin( $delta_lat / 2 ) ** 2
			+ cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * sin( $delta_lng / 2 ) ** 2;

		$c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );

		return self::EARTH_RADIUS_KM * $c;
	}

	/**
	 * Find nearby cities within a given radius.
	 *
	 * @param float $lat       Origin latitude.
	 * @param float $lng       Origin longitude.
	 * @param int   $radius_km Radius in kilometres.
	 * @param int   $limit     Max results.
	 * @return array Array of location objects with distance_km.
	 */
	public function find_nearby_cities( float $lat, float $lng, int $radius_km = 50, int $limit = 50 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'job_locations';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, city, state, country, latitude, longitude,
				 ( %f * ACOS(
					 COS(RADIANS(%f)) * COS(RADIANS(latitude)) *
					 COS(RADIANS(longitude) - RADIANS(%f)) +
					 SIN(RADIANS(%f)) * SIN(RADIANS(latitude))
				 ) ) AS distance_km
				 FROM `{$table}`
				 WHERE is_active = 1
				   AND latitude IS NOT NULL
				   AND longitude IS NOT NULL
				 HAVING distance_km <= %d
				 ORDER BY distance_km ASC
				 LIMIT %d",
				self::EARTH_RADIUS_KM,
				$lat,
				$lng,
				$lat,
				$radius_km,
				$limit
			)
		);

		return $results ?? array();
	}

	/**
	 * Import locations from an associative array (e.g., from CSV rows).
	 *
	 * @param array $rows Array of rows with keys: country, state, district, city, latitude, longitude.
	 * @return array{inserted: int, skipped: int, errors: string[]}
	 */
	public function import_locations( array $rows ): array {
		global $wpdb;
		$table    = $wpdb->prefix . 'job_locations';
		$inserted = 0;
		$skipped  = 0;
		$errors   = array();

		foreach ( $rows as $index => $row ) {
			$country  = sanitize_text_field( $row['country'] ?? '' );
			$state    = sanitize_text_field( $row['state'] ?? '' );
			$district = sanitize_text_field( $row['district'] ?? '' );
			$city     = sanitize_text_field( $row['city'] ?? '' );

			if ( empty( $city ) || empty( $country ) ) {
				++$skipped;
				$errors[] = sprintf(
					/* translators: %d: row number */
					__( 'Row %d: city and country are required.', 'ai-job-employer-manager' ),
					$index + 1
				);
				continue;
			}

			// Skip duplicates.
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM `{$table}` WHERE country = %s AND state = %s AND district = %s AND city = %s LIMIT 1",
					$country,
					$state,
					$district,
					$city
				)
			);

			if ( $exists ) {
				++$skipped;
				continue;
			}

			$lat = isset( $row['latitude'] ) && is_numeric( $row['latitude'] ) ? (float) $row['latitude'] : null;
			$lng = isset( $row['longitude'] ) && is_numeric( $row['longitude'] ) ? (float) $row['longitude'] : null;

			$result = $wpdb->insert(
				$table,
				array(
					'country'       => $country,
					'state'         => $state,
					'district'      => $district,
					'city'          => $city,
					'latitude'      => $lat,
					'longitude'     => $lng,
					'location_type' => 'city',
					'is_active'     => 1,
				)
			);

			if ( $result ) {
				++$inserted;
			} else {
				++$skipped;
			}
		}

		// Clear location caches.
		$this->clear_location_cache();

		return array(
			'inserted' => $inserted,
			'skipped'  => $skipped,
			'errors'   => $errors,
		);
	}

	/**
	 * Parse a CSV file into an array of location rows.
	 *
	 * @param string $file_path Absolute path to CSV file.
	 * @return array Array of associative row arrays.
	 */
	public function parse_csv( string $file_path ): array {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return array();
		}

		$rows   = array();
		$handle = fopen( $file_path, 'r' );

		if ( ! $handle ) {
			return array();
		}

		$headers = fgetcsv( $handle );
		if ( ! $headers ) {
			fclose( $handle );
			return array();
		}

		$headers = array_map( 'strtolower', array_map( 'trim', $headers ) );

		while ( ( $line = fgetcsv( $handle ) ) !== false ) {
			$row = array();
			foreach ( $headers as $i => $header ) {
				$row[ $header ] = $line[ $i ] ?? '';
			}
			$rows[] = $row;
		}

		fclose( $handle );

		return $rows;
	}

	/**
	 * Clear all location transient caches.
	 *
	 * @return void
	 */
	public function clear_location_cache(): void {
		global $wpdb;
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '_transient_ajem_loc%'
			    OR option_name LIKE '_transient_timeout_ajem_loc%'
			    OR option_name LIKE '_transient_ajem_cities%'
			    OR option_name LIKE '_transient_timeout_ajem_cities%'"
		);
	}
}
