<?php
/**
 * Candidate Search — search candidates from the AI Job Candidate Manager plugin tables.
 *
 * @package AI_Job_Employer_Manager
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AJEM_Candidate_Search
 *
 * Searches candidates stored by the AI Job Candidate Manager plugin.
 * Gracefully degrades if that plugin is not installed.
 *
 * @since 1.0.0
 */
class AJEM_Candidate_Search {

	/**
	 * Check whether the candidate plugin tables exist.
	 *
	 * @return bool True when the candidate plugin tables are available.
	 */
	public function is_candidate_plugin_active(): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'job_candidates';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table;
	}

	/**
	 * Search candidates with filters.
	 *
	 * @param array $args {
	 *     Search arguments.
	 *
	 *     @type string   $keyword        Text search (name, about).
	 *     @type string[] $skills         Required skills (AND logic).
	 *     @type int      $experience_min Min years of experience.
	 *     @type int      $experience_max Max years of experience.
	 *     @type string   $education      Education level filter.
	 *     @type string   $city           City filter.
	 *     @type string   $state          State filter.
	 *     @type float    $lat            Searcher latitude for distance filter.
	 *     @type float    $lng            Searcher longitude for distance filter.
	 *     @type int      $distance_km    Max search radius km.
	 *     @type int      $page           Page number.
	 *     @type int      $per_page       Results per page.
	 * }
	 * @return array{candidates: array, total: int, pages: int, plugin_missing: bool}
	 */
	public function search( array $args = array() ): array {
		if ( ! $this->is_candidate_plugin_active() ) {
			return array(
				'candidates'     => array(),
				'total'          => 0,
				'pages'          => 0,
				'plugin_missing' => true,
				'message'        => __( 'Install AI Job Candidate Manager to search candidates.', 'ai-job-employer-manager' ),
			);
		}

		global $wpdb;
		$candidates_table = $wpdb->prefix . 'job_candidates';
		$skills_table     = $wpdb->prefix . 'candidate_skills';
		$resumes_table    = $wpdb->prefix . 'candidate_resumes';

		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$where  = array( "c.profile_visibility != 'private'" );
		$params = array();

		// Keyword search.
		if ( ! empty( $args['keyword'] ) ) {
			$keyword  = '%' . $wpdb->esc_like( sanitize_text_field( $args['keyword'] ) ) . '%';
			$where[]  = '( c.full_name LIKE %s OR c.about LIKE %s )';
			$params[] = $keyword;
			$params[] = $keyword;
		}

		// City filter.
		if ( ! empty( $args['city'] ) ) {
			$where[]  = 'c.city = %s';
			$params[] = sanitize_text_field( $args['city'] );
		}

		// State filter.
		if ( ! empty( $args['state'] ) ) {
			$where[]  = 'c.state = %s';
			$params[] = sanitize_text_field( $args['state'] );
		}

		// Distance filter via Haversine.
		$has_distance = (
			isset( $args['lat'], $args['lng'], $args['distance_km'] ) &&
			is_numeric( $args['lat'] ) &&
			is_numeric( $args['lng'] )
		);

		$distance_select = '';
		if ( $has_distance ) {
			$lat  = (float) $args['lat'];
			$lng  = (float) $args['lng'];
			$dist = (int) $args['distance_km'];

			$distance_select = $wpdb->prepare(
				", ( 6371 * ACOS(
					COS(RADIANS(%f)) * COS(RADIANS(c.latitude)) *
					COS(RADIANS(c.longitude) - RADIANS(%f)) +
					SIN(RADIANS(%f)) * SIN(RADIANS(c.latitude))
				) ) AS distance_km",
				$lat,
				$lng,
				$lat
			);
			$where[] = 'c.latitude IS NOT NULL AND c.longitude IS NOT NULL';
		}

		$where_sql = 'WHERE ' . implode( ' AND ', $where );
		$having    = $has_distance ? "HAVING distance_km <= {$dist}" : '';

		// Count.
		$count_sql = "SELECT COUNT(*) FROM `{$candidates_table}` c {$where_sql}";
		$data_sql  = "SELECT c.id, c.user_id, c.full_name, c.profile_photo, c.about,
			                c.city, c.state, c.country, c.expected_salary,
			                c.preferred_job_type, c.profile_visibility
			                {$distance_select}
			          FROM `{$candidates_table}` c
			          {$where_sql}
			          {$having}
			          ORDER BY c.created_at DESC
			          LIMIT %d OFFSET %d";

		if ( ! empty( $params ) ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) );
			$data_params = array_merge( $params, array( $per_page, $offset ) );
			$candidates  = $wpdb->get_results( $wpdb->prepare( $data_sql, ...$data_params ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$total      = (int) $wpdb->get_var( $count_sql );
			$candidates = $wpdb->get_results(
				$wpdb->prepare( $data_sql, $per_page, $offset )
			);
		}

		// Filter by skills if required (post-query, as skills are in separate table).
		if ( ! empty( $args['skills'] ) && is_array( $args['skills'] ) ) {
			$candidates = $this->filter_by_skills( $candidates ?? array(), $args['skills'], $skills_table );
		}

		// Attach skills to each candidate.
		if ( ! empty( $candidates ) ) {
			$candidates = $this->attach_skills( $candidates, $skills_table );
		}

		// Respect employers_only visibility.
		$candidates = array_filter(
			$candidates ?? array(),
			fn( $c ) => in_array( $c->profile_visibility ?? 'public', array( 'public', 'employers_only' ), true )
		);

		return array(
			'candidates'     => array_values( $candidates ),
			'total'          => $total,
			'pages'          => $per_page > 0 ? (int) ceil( $total / $per_page ) : 1,
			'plugin_missing' => false,
		);
	}

	/**
	 * Filter candidates that have all required skills.
	 *
	 * @param array  $candidates   Candidate objects.
	 * @param array  $skills       Required skills (AND logic).
	 * @param string $skills_table Skills table name.
	 * @return array Filtered candidates.
	 */
	private function filter_by_skills( array $candidates, array $skills, string $skills_table ): array {
		if ( empty( $candidates ) ) {
			return array();
		}

		global $wpdb;

		// Check if skills table exists.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$skills_table}'" ) !== $skills_table ) {
			return $candidates;
		}

		$candidate_ids = array_map( fn( $c ) => (int) $c->id, $candidates );
		$sanitised     = array_map( 'sanitize_text_field', $skills );
		$required      = count( $sanitised );

		$placeholders_ids    = implode( ',', array_fill( 0, count( $candidate_ids ), '%d' ) );
		$placeholders_skills = implode( ',', array_fill( 0, $required, '%s' ) );

		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT candidate_id, COUNT(*) AS matched
				 FROM `{$skills_table}`
				 WHERE candidate_id IN ({$placeholders_ids})
				   AND LOWER(skill_name) IN ({$placeholders_skills})
				 GROUP BY candidate_id
				 HAVING matched = %d",
				...array_merge( $candidate_ids, array_map( 'strtolower', $sanitised ), array( $required ) )
			)
		);

		$qualified_ids = array_column( $rows, 'candidate_id' );
		return array_filter( $candidates, fn( $c ) => in_array( $c->id, $qualified_ids, false ) );
	}

	/**
	 * Attach skill names to candidate objects.
	 *
	 * @param array  $candidates   Candidate objects.
	 * @param string $skills_table Skills table name.
	 * @return array Candidates with attached skills array.
	 */
	private function attach_skills( array $candidates, string $skills_table ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$skills_table}'" ) !== $skills_table ) {
			foreach ( $candidates as &$c ) {
				$c->skills = array();
			}
			return $candidates;
		}

		$ids          = array_map( fn( $c ) => (int) $c->id, $candidates );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT candidate_id, skill_name, skill_level FROM `{$skills_table}` WHERE candidate_id IN ({$placeholders})",
				...$ids
			)
		);

		$skills_map = array();
		foreach ( $rows as $row ) {
			$skills_map[ $row->candidate_id ][] = array(
				'skill'  => $row->skill_name,
				'level'  => $row->skill_level,
			);
		}

		foreach ( $candidates as &$c ) {
			$c->skills = $skills_map[ $c->id ] ?? array();
		}

		return $candidates;
	}
}
