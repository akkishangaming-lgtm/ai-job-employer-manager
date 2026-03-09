<?php
/**
 * Job Listings — public search, filters, sorting, pagination, distance filtering.
 *
 * @package AI_Job_Employer_Manager
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AJEM_Job_Listings
 *
 * Provides paginated job listings for the public frontend with
 * keyword search (FULLTEXT), multi-dimensional filters, sorting,
 * and optional Haversine-based distance filtering.
 *
 * @since 1.0.0
 */
class AJEM_Job_Listings {

	/**
	 * Default number of listings per page.
	 */
	private const DEFAULT_PER_PAGE = 20;

	/**
	 * Get public job listings with filters and pagination.
	 *
	 * @param array $args {
	 *     Optional query args.
	 *
	 *     @type string   $keyword          Full-text search keyword.
	 *     @type string   $job_type         Filter by job type.
	 *     @type string   $industry         Filter by industry.
	 *     @type string   $city             Filter by city.
	 *     @type string   $state            Filter by state.
	 *     @type float    $salary_min       Minimum salary.
	 *     @type float    $salary_max       Maximum salary.
	 *     @type string   $experience       Experience filter.
	 *     @type string   $sort             Sort order: newest|salary_high|salary_low|most_viewed|deadline.
	 *     @type int      $page             Pagination page number.
	 *     @type int      $per_page         Results per page.
	 *     @type float    $lat              Candidate latitude for distance filter.
	 *     @type float    $lng              Candidate longitude for distance filter.
	 *     @type int      $distance_km      Max distance in km (20, 50, 100).
	 *     @type int      $employer_id      Restrict to one employer (shows all statuses).
	 * }
	 * @return array {
	 *     @type array $jobs  Array of job objects.
	 *     @type int   $total Total matching jobs.
	 *     @type int   $pages Total pages.
	 * }
	 */
	public function get_listings( array $args = array() ): array {
		global $wpdb;
		$table     = $wpdb->prefix . 'job_listings';
		$employers = $wpdb->prefix . 'job_employers';

		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, (int) ( $args['per_page'] ?? self::DEFAULT_PER_PAGE ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		// Base select — join employer for company name + logo.
		$select = "SELECT jl.*, je.company_name, je.company_logo, je.whatsapp_number, je.contact_phone";
		$from   = "FROM `{$table}` jl LEFT JOIN `{$employers}` je ON jl.employer_id = je.id";
		$where  = array();
		$params = array();

		// Visibility: public sees only active, employer sees own jobs.
		$employer_id = isset( $args['employer_id'] ) ? (int) $args['employer_id'] : 0;
		if ( $employer_id > 0 ) {
			$where[]  = 'jl.employer_id = %d';
			$params[] = $employer_id;
		} else {
			$where[] = "jl.status = 'active'";
		}

		// Keyword full-text search.
		if ( ! empty( $args['keyword'] ) ) {
			$keyword  = sanitize_text_field( $args['keyword'] );
			$where[]  = 'MATCH(jl.job_title, jl.job_description) AGAINST (%s IN BOOLEAN MODE)';
			$params[] = $keyword . '*';
		}

		// Filter: job_type.
		if ( ! empty( $args['job_type'] ) ) {
			$where[]  = 'jl.job_type = %s';
			$params[] = sanitize_text_field( $args['job_type'] );
		}

		// Filter: industry.
		if ( ! empty( $args['industry'] ) ) {
			$where[]  = 'jl.industry = %s';
			$params[] = sanitize_text_field( $args['industry'] );
		}

		// Filter: city.
		if ( ! empty( $args['city'] ) ) {
			$where[]  = 'jl.city = %s';
			$params[] = sanitize_text_field( $args['city'] );
		}

		// Filter: state.
		if ( ! empty( $args['state'] ) ) {
			$where[]  = 'jl.state = %s';
			$params[] = sanitize_text_field( $args['state'] );
		}

		// Filter: salary range.
		if ( isset( $args['salary_min'] ) && is_numeric( $args['salary_min'] ) ) {
			$where[]  = 'jl.salary_max >= %f';
			$params[] = (float) $args['salary_min'];
		}
		if ( isset( $args['salary_max'] ) && is_numeric( $args['salary_max'] ) ) {
			$where[]  = '( jl.salary_min <= %f OR jl.salary_min IS NULL )';
			$params[] = (float) $args['salary_max'];
		}

		// Distance filter using Haversine.
		$has_distance = (
			isset( $args['lat'], $args['lng'], $args['distance_km'] ) &&
			is_numeric( $args['lat'] ) &&
			is_numeric( $args['lng'] )
		);

		if ( $has_distance ) {
			$lat         = (float) $args['lat'];
			$lng         = (float) $args['lng'];
			$distance_km = (int) $args['distance_km'];
			// Haversine formula requires lat twice: once for COS and once for SIN calculations.
			$select .= ", ( 6371 * ACOS(
				COS(RADIANS(%f)) * COS(RADIANS(jl.latitude)) *
				COS(RADIANS(jl.longitude) - RADIANS(%f)) +
				SIN(RADIANS(%f)) * SIN(RADIANS(jl.latitude))
			) ) AS distance_km";
			array_unshift( $params, $lat, $lng, $lat );
			$where[]  = 'jl.latitude IS NOT NULL AND jl.longitude IS NOT NULL';
		}

		// Build WHERE clause.
		$where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		// Build HAVING clause for distance (must come after WHERE).
		$having_sql = '';
		if ( $has_distance ) {
			$having_sql = "HAVING distance_km <= {$distance_km}";
		}

		// Sort order.
		$sort_map = array(
			'newest'      => 'jl.created_at DESC',
			'salary_high' => 'jl.salary_max DESC',
			'salary_low'  => 'jl.salary_min ASC',
			'most_viewed' => 'jl.views_count DESC',
			'deadline'    => 'jl.application_deadline ASC',
		);
		$sort_key  = sanitize_text_field( $args['sort'] ?? 'newest' );
		$order_sql = 'ORDER BY ' . ( $sort_map[ $sort_key ] ?? 'jl.created_at DESC' );

		// Count query.
		$count_sql = "SELECT COUNT(*) {$from} {$where_sql}";

		// Data query.
		$data_sql = "{$select} {$from} {$where_sql} {$having_sql} {$order_sql} LIMIT %d OFFSET %d";

		$count_params = $params;
		$data_params  = array_merge( $params, array( $per_page, $offset ) );

		// Allow external modification of the full query args.
		$query_args = apply_filters(
			'ajem_job_listing_query',
			array(
				'count_sql'    => $count_sql,
				'data_sql'     => $data_sql,
				'count_params' => $count_params,
				'data_params'  => $data_params,
			),
			$args
		);

		// Prepare + run.
		$total = 0;
		$jobs  = array();

		if ( ! empty( $query_args['count_params'] ) ) {
			$total = (int) $wpdb->get_var(
				$wpdb->prepare( $query_args['count_sql'], ...$query_args['count_params'] )
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $query_args['count_sql'] );
		}

		if ( ! empty( $query_args['data_params'] ) ) {
			$jobs = $wpdb->get_results(
				$wpdb->prepare( $query_args['data_sql'], ...$query_args['data_params'] )
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$jobs = $wpdb->get_results( $query_args['data_sql'] );
		}

		// Decode required_skills JSON for convenience.
		foreach ( $jobs as &$job ) {
			if ( ! empty( $job->required_skills ) ) {
				$job->required_skills_array = json_decode( $job->required_skills, true ) ?? array();
			} else {
				$job->required_skills_array = array();
			}
		}
		unset( $job );

		return array(
			'jobs'  => $jobs ?? array(),
			'total' => $total,
			'pages' => $per_page > 0 ? (int) ceil( $total / $per_page ) : 1,
		);
	}

	/**
	 * Get jobs for a specific employer.
	 *
	 * @param int   $employer_id Employer ID.
	 * @param array $args        Extra filters (status, page, per_page).
	 * @return array
	 */
	public function get_employer_jobs( int $employer_id, array $args = array() ): array {
		$args['employer_id'] = $employer_id;
		return $this->get_listings( $args );
	}

	/**
	 * Get distinct filter values for the jobs that are currently active.
	 *
	 * @return array Associative array with keys: industries, cities, states, job_types.
	 */
	public function get_filter_options(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'job_listings';

		$cache_key = 'ajem_filter_options';
		$cached    = get_transient( $cache_key );
		if ( $cached !== false ) {
			return $cached;
		}

		$options = array(
			'industries' => $wpdb->get_col( "SELECT DISTINCT industry FROM `{$table}` WHERE status='active' AND industry != '' ORDER BY industry ASC" ),
			'cities'     => $wpdb->get_col( "SELECT DISTINCT city FROM `{$table}` WHERE status='active' AND city != '' ORDER BY city ASC" ),
			'states'     => $wpdb->get_col( "SELECT DISTINCT state FROM `{$table}` WHERE status='active' AND state != '' ORDER BY state ASC" ),
			'job_types'  => array_keys( $this->get_job_type_labels() ),
		);

		set_transient( $cache_key, $options, HOUR_IN_SECONDS );
		return $options;
	}

	/**
	 * Get human-readable job type labels.
	 *
	 * @return array<string, string>
	 */
	public function get_job_type_labels(): array {
		return apply_filters(
			'ajem_job_type_labels',
			array(
				'full_time'  => __( 'Full Time', 'ai-job-employer-manager' ),
				'part_time'  => __( 'Part Time', 'ai-job-employer-manager' ),
				'internship' => __( 'Internship', 'ai-job-employer-manager' ),
				'contract'   => __( 'Contract', 'ai-job-employer-manager' ),
				'remote'     => __( 'Remote', 'ai-job-employer-manager' ),
			)
		);
	}
}
