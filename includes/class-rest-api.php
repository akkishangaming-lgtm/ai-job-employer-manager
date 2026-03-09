<?php
/**
 * REST API endpoints for AI Job Employer Manager.
 *
 * @package AI_Job_Employer_Manager
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AJEM_REST_API
 *
 * Registers and handles all REST API routes under the namespace `ajem/v1`.
 *
 * @since 1.0.0
 */
class AJEM_REST_API {

	/**
	 * API namespace.
	 */
	private const NAMESPACE = 'ajem/v1';

	/**
	 * Register all REST routes.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		$api = new self();

		// ── Employer profile ──────────────────────────────────────────────────
		register_rest_route(
			self::NAMESPACE,
			'/employer/profile',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $api, 'get_employer_profile' ),
					'permission_callback' => array( $api, 'is_employer' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $api, 'save_employer_profile' ),
					'permission_callback' => array( $api, 'is_employer' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $api, 'save_employer_profile' ),
					'permission_callback' => array( $api, 'is_employer' ),
				),
			)
		);

		// Employer geolocation.
		register_rest_route(
			self::NAMESPACE,
			'/employer/geolocation',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $api, 'save_employer_geolocation' ),
				'permission_callback' => array( $api, 'is_employer' ),
			)
		);

		// ── Employer jobs ─────────────────────────────────────────────────────
		register_rest_route(
			self::NAMESPACE,
			'/employer/jobs',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $api, 'get_employer_jobs' ),
					'permission_callback' => array( $api, 'is_employer' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $api, 'create_job' ),
					'permission_callback' => array( $api, 'is_employer' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/employer/jobs/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $api, 'get_single_employer_job' ),
					'permission_callback' => array( $api, 'is_employer' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $api, 'update_job' ),
					'permission_callback' => array( $api, 'is_employer' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $api, 'delete_job' ),
					'permission_callback' => array( $api, 'is_employer' ),
				),
			)
		);

		// Job status.
		register_rest_route(
			self::NAMESPACE,
			'/employer/jobs/(?P<id>\d+)/status',
			array(
				'methods'             => 'PUT',
				'callback'            => array( $api, 'update_job_status' ),
				'permission_callback' => array( $api, 'is_employer' ),
			)
		);

		// ── Applications ──────────────────────────────────────────────────────
		register_rest_route(
			self::NAMESPACE,
			'/employer/jobs/(?P<id>\d+)/applications',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $api, 'get_job_applications' ),
				'permission_callback' => array( $api, 'is_employer' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/employer/applications',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $api, 'get_all_employer_applications' ),
				'permission_callback' => array( $api, 'is_employer' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/employer/applications/(?P<id>\d+)/status',
			array(
				'methods'             => 'PUT',
				'callback'            => array( $api, 'update_application_status' ),
				'permission_callback' => array( $api, 'is_employer' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/employer/applications/(?P<id>\d+)/notes',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $api, 'add_application_notes' ),
				'permission_callback' => array( $api, 'is_employer' ),
			)
		);

		// ── Candidates ────────────────────────────────────────────────────────
		register_rest_route(
			self::NAMESPACE,
			'/employer/candidates/search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $api, 'search_candidates' ),
				'permission_callback' => array( $api, 'is_employer' ),
			)
		);

		// ── Shortlist ─────────────────────────────────────────────────────────
		register_rest_route(
			self::NAMESPACE,
			'/employer/shortlist',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $api, 'get_shortlist' ),
					'permission_callback' => array( $api, 'is_employer' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $api, 'add_to_shortlist' ),
					'permission_callback' => array( $api, 'is_employer' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $api, 'remove_from_shortlist' ),
					'permission_callback' => array( $api, 'is_employer' ),
				),
			)
		);

		// ── Analytics ─────────────────────────────────────────────────────────
		register_rest_route(
			self::NAMESPACE,
			'/employer/analytics',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $api, 'get_analytics' ),
				'permission_callback' => array( $api, 'is_employer' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/employer/analytics/job/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $api, 'get_job_analytics' ),
				'permission_callback' => array( $api, 'is_employer' ),
			)
		);

		// ── Public job browsing (no auth) ─────────────────────────────────────
		register_rest_route(
			self::NAMESPACE,
			'/jobs',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $api, 'get_public_jobs' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/jobs/(?P<slug>[a-z0-9\-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $api, 'get_public_job_by_slug' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/jobs/(?P<id>\d+)/view',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $api, 'track_job_view' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/jobs/(?P<id>\d+)/apply',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $api, 'apply_for_job' ),
				'permission_callback' => array( $api, 'is_logged_in' ),
			)
		);

		// ── Location search (no auth) ─────────────────────────────────────────
		register_rest_route(
			self::NAMESPACE,
			'/locations/search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $api, 'search_locations' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	// ── Permission callbacks ──────────────────────────────────────────────────

	/**
	 * Check if current user is logged in.
	 *
	 * @return bool|WP_Error
	 */
	public function is_logged_in(): bool|WP_Error {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_forbidden', __( 'Authentication required.', 'ai-job-employer-manager' ), array( 'status' => 401 ) );
		}
		return true;
	}

	/**
	 * Check if current user has employer capabilities.
	 *
	 * Accepts: administrator, employer, or any user with 'ajem_manage_jobs'.
	 *
	 * @return bool|WP_Error
	 */
	public function is_employer(): bool|WP_Error {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_forbidden', __( 'Authentication required.', 'ai-job-employer-manager' ), array( 'status' => 401 ) );
		}

		if ( current_user_can( 'administrator' ) || current_user_can( 'ajem_manage_jobs' ) ) {
			return true;
		}

		return new WP_Error( 'rest_forbidden', __( 'You do not have permission to access this resource.', 'ai-job-employer-manager' ), array( 'status' => 403 ) );
	}

	// ── Helper ────────────────────────────────────────────────────────────────

	/**
	 * Get the employer row ID for the current user.
	 *
	 * @return int|null Employer row ID or null.
	 */
	private function get_current_employer_id(): ?int {
		$profile = new AJEM_Employer_Profile();
		$row     = $profile->get_by_user_id( get_current_user_id() );
		return $row ? (int) $row->id : null;
	}

	// ── Route handlers ────────────────────────────────────────────────────────

	/**
	 * GET /employer/profile
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_employer_profile( WP_REST_Request $request ): WP_REST_Response {
		$profile = new AJEM_Employer_Profile();
		$data    = $profile->get_by_user_id( get_current_user_id() );

		if ( ! $data ) {
			return new WP_REST_Response( array( 'profile' => null ), 200 );
		}

		return new WP_REST_Response( array( 'profile' => $data ), 200 );
	}

	/**
	 * POST/PUT /employer/profile
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function save_employer_profile( WP_REST_Request $request ): WP_REST_Response {
		$data    = $request->get_json_params() ?: $request->get_body_params();
		$profile = new AJEM_Employer_Profile();
		$result  = $profile->save( get_current_user_id(), $data );

		if ( false === $result ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Failed to save profile.', 'ai-job-employer-manager' ) ), 500 );
		}

		return new WP_REST_Response( array( 'success' => true, 'id' => $result ), 200 );
	}

	/**
	 * POST /employer/geolocation
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function save_employer_geolocation( WP_REST_Request $request ): WP_REST_Response {
		$data    = $request->get_json_params() ?: $request->get_body_params();
		$profile = new AJEM_Employer_Profile();
		$result  = $profile->save(
			get_current_user_id(),
			array(
				'latitude'  => $data['latitude'] ?? null,
				'longitude' => $data['longitude'] ?? null,
				'country'   => $data['country'] ?? '',
				'state'     => $data['state'] ?? '',
				'district'  => $data['district'] ?? '',
				'city'      => $data['city'] ?? '',
			)
		);

		return new WP_REST_Response( array( 'success' => false !== $result ), 200 );
	}

	/**
	 * GET /employer/jobs
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_employer_jobs( WP_REST_Request $request ): WP_REST_Response {
		$employer_id = $this->get_current_employer_id();
		if ( ! $employer_id ) {
			return new WP_REST_Response( array( 'jobs' => array(), 'total' => 0 ), 200 );
		}

		$listings = new AJEM_Job_Listings();
		$result   = $listings->get_employer_jobs(
			$employer_id,
			array(
				'page'     => absint( $request->get_param( 'page' ) ?: 1 ),
				'per_page' => absint( $request->get_param( 'per_page' ) ?: 20 ),
				'status'   => sanitize_text_field( $request->get_param( 'status' ) ?: '' ),
			)
		);

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * GET /employer/jobs/{id}
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_single_employer_job( WP_REST_Request $request ): WP_REST_Response {
		$employer_id = $this->get_current_employer_id();
		$job_posting = new AJEM_Job_Posting();
		$job         = $job_posting->get_by_id( (int) $request['id'] );

		if ( ! $job || ( ! current_user_can( 'administrator' ) && (int) $job->employer_id !== $employer_id ) ) {
			return new WP_REST_Response( array( 'message' => __( 'Job not found.', 'ai-job-employer-manager' ) ), 404 );
		}

		return new WP_REST_Response( array( 'job' => $job ), 200 );
	}

	/**
	 * POST /employer/jobs
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function create_job( WP_REST_Request $request ): WP_REST_Response {
		$employer_id = $this->get_current_employer_id();
		if ( ! $employer_id ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Employer profile not found. Please create your company profile first.', 'ai-job-employer-manager' ) ), 400 );
		}

		$data       = $request->get_json_params() ?: $request->get_body_params();
		$job_posting = new AJEM_Job_Posting();
		$result      = $job_posting->create( $employer_id, $data );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => $result->get_error_message() ), 400 );
		}

		return new WP_REST_Response( array( 'success' => true, 'id' => $result ), 201 );
	}

	/**
	 * PUT /employer/jobs/{id}
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function update_job( WP_REST_Request $request ): WP_REST_Response {
		$employer_id = $this->get_current_employer_id();
		$data        = $request->get_json_params() ?: $request->get_body_params();
		$job_posting = new AJEM_Job_Posting();
		$result      = $job_posting->update( (int) $request['id'], $employer_id ?? 0, $data );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => $result->get_error_message() ), 400 );
		}

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * DELETE /employer/jobs/{id}
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function delete_job( WP_REST_Request $request ): WP_REST_Response {
		$employer_id = $this->get_current_employer_id();
		$job_posting = new AJEM_Job_Posting();
		$result      = $job_posting->delete( (int) $request['id'], $employer_id ?? 0 );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => $result->get_error_message() ), 400 );
		}

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * PUT /employer/jobs/{id}/status
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function update_job_status( WP_REST_Request $request ): WP_REST_Response {
		$employer_id = $this->get_current_employer_id();
		$data        = $request->get_json_params() ?: $request->get_body_params();
		$status      = sanitize_text_field( $data['status'] ?? '' );
		$job_posting = new AJEM_Job_Posting();
		$result      = $job_posting->change_status( (int) $request['id'], $employer_id ?? 0, $status );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => $result->get_error_message() ), 400 );
		}

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * GET /employer/jobs/{id}/applications
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_job_applications( WP_REST_Request $request ): WP_REST_Response {
		$employer_id = $this->get_current_employer_id();
		$apps        = new AJEM_Job_Applications();
		$result      = $apps->get_by_job(
			(int) $request['id'],
			$employer_id ?? 0,
			absint( $request->get_param( 'page' ) ?: 1 ),
			absint( $request->get_param( 'per_page' ) ?: 20 )
		);

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * GET /employer/applications
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_all_employer_applications( WP_REST_Request $request ): WP_REST_Response {
		$employer_id = $this->get_current_employer_id();
		$apps        = new AJEM_Job_Applications();
		$result      = $apps->get_by_employer(
			$employer_id ?? 0,
			absint( $request->get_param( 'page' ) ?: 1 ),
			absint( $request->get_param( 'per_page' ) ?: 20 )
		);

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * PUT /employer/applications/{id}/status
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function update_application_status( WP_REST_Request $request ): WP_REST_Response {
		$employer_id = $this->get_current_employer_id();
		$data        = $request->get_json_params() ?: $request->get_body_params();
		$status      = sanitize_text_field( $data['status'] ?? '' );
		$apps        = new AJEM_Job_Applications();
		$result      = $apps->update_status( (int) $request['id'], $employer_id ?? 0, $status );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => $result->get_error_message() ), 400 );
		}

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * POST /employer/applications/{id}/notes
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function add_application_notes( WP_REST_Request $request ): WP_REST_Response {
		$employer_id = $this->get_current_employer_id();
		$data        = $request->get_json_params() ?: $request->get_body_params();
		$notes       = $data['notes'] ?? '';
		$apps        = new AJEM_Job_Applications();
		$result      = $apps->add_notes( (int) $request['id'], $employer_id ?? 0, $notes );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => $result->get_error_message() ), 400 );
		}

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * GET /employer/candidates/search
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function search_candidates( WP_REST_Request $request ): WP_REST_Response {
		$search = new AJEM_Candidate_Search();
		$args   = array(
			'keyword'        => sanitize_text_field( $request->get_param( 'keyword' ) ?: '' ),
			'skills'         => array_map( 'sanitize_text_field', (array) ( $request->get_param( 'skills' ) ?: array() ) ),
			'city'           => sanitize_text_field( $request->get_param( 'city' ) ?: '' ),
			'state'          => sanitize_text_field( $request->get_param( 'state' ) ?: '' ),
			'experience_min' => absint( $request->get_param( 'experience_min' ) ?: 0 ),
			'experience_max' => absint( $request->get_param( 'experience_max' ) ?: 0 ),
			'lat'            => $request->get_param( 'lat' ),
			'lng'            => $request->get_param( 'lng' ),
			'distance_km'    => absint( $request->get_param( 'distance_km' ) ?: 50 ),
			'page'           => absint( $request->get_param( 'page' ) ?: 1 ),
			'per_page'       => absint( $request->get_param( 'per_page' ) ?: 20 ),
		);

		$result = $search->search( $args );
		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * GET /employer/shortlist
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_shortlist( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;
		$employer_id = $this->get_current_employer_id();
		$table       = $wpdb->prefix . 'job_shortlisted_candidates';
		$page        = absint( $request->get_param( 'page' ) ?: 1 );
		$per_page    = absint( $request->get_param( 'per_page' ) ?: 20 );
		$offset      = ( $page - 1 ) * $per_page;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE employer_id = %d ORDER BY shortlisted_at DESC LIMIT %d OFFSET %d",
				$employer_id ?? 0,
				$per_page,
				$offset
			)
		);

		$total = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE employer_id = %d", $employer_id ?? 0 )
		);

		return new WP_REST_Response( array( 'shortlisted' => $rows ?? array(), 'total' => $total ), 200 );
	}

	/**
	 * POST /employer/shortlist
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function add_to_shortlist( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;
		$employer_id  = $this->get_current_employer_id();
		$data         = $request->get_json_params() ?: $request->get_body_params();
		$candidate_id = absint( $data['candidate_id'] ?? 0 );
		$job_id       = absint( $data['job_id'] ?? 0 ) ?: null;
		$notes        = wp_kses_post( $data['notes'] ?? '' );
		$table        = $wpdb->prefix . 'job_shortlisted_candidates';

		if ( ! $candidate_id ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => __( 'candidate_id is required.', 'ai-job-employer-manager' ) ), 400 );
		}

		$result = $wpdb->replace(
			$table,
			array(
				'employer_id'  => $employer_id ?? 0,
				'candidate_id' => $candidate_id,
				'job_id'       => $job_id,
				'notes'        => $notes,
			),
			array( '%d', '%d', $job_id ? '%d' : null, '%s' )
		);

		do_action( 'ajem_candidate_shortlisted', $employer_id, $candidate_id, $job_id );

		return new WP_REST_Response( array( 'success' => false !== $result ), 200 );
	}

	/**
	 * DELETE /employer/shortlist
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function remove_from_shortlist( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;
		$employer_id  = $this->get_current_employer_id();
		$data         = $request->get_json_params() ?: $request->get_body_params();
		$candidate_id = absint( $data['candidate_id'] ?? 0 );
		$table        = $wpdb->prefix . 'job_shortlisted_candidates';

		$result = $wpdb->delete(
			$table,
			array(
				'employer_id'  => $employer_id ?? 0,
				'candidate_id' => $candidate_id,
			),
			array( '%d', '%d' )
		);

		return new WP_REST_Response( array( 'success' => false !== $result ), 200 );
	}

	/**
	 * GET /employer/analytics
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_analytics( WP_REST_Request $request ): WP_REST_Response {
		$employer_id = $this->get_current_employer_id();
		$analytics   = new AJEM_Job_Analytics();
		$stats       = $analytics->get_employer_stats( $employer_id ?? 0 );
		$top_jobs    = $analytics->get_top_jobs( $employer_id ?? 0 );

		return new WP_REST_Response( array( 'stats' => $stats, 'top_jobs' => $top_jobs ), 200 );
	}

	/**
	 * GET /employer/analytics/job/{id}
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_job_analytics( WP_REST_Request $request ): WP_REST_Response {
		$employer_id = $this->get_current_employer_id();
		$analytics   = new AJEM_Job_Analytics();
		$data        = $analytics->get_job_stats( (int) $request['id'], $employer_id ?? 0 );

		if ( ! $data ) {
			return new WP_REST_Response( array( 'message' => __( 'Job not found.', 'ai-job-employer-manager' ) ), 404 );
		}

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * GET /jobs — public job listings.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_public_jobs( WP_REST_Request $request ): WP_REST_Response {
		$listings = new AJEM_Job_Listings();
		$result   = $listings->get_listings(
			array(
				'keyword'     => sanitize_text_field( $request->get_param( 'keyword' ) ?: '' ),
				'job_type'    => sanitize_text_field( $request->get_param( 'job_type' ) ?: '' ),
				'industry'    => sanitize_text_field( $request->get_param( 'industry' ) ?: '' ),
				'city'        => sanitize_text_field( $request->get_param( 'city' ) ?: '' ),
				'state'       => sanitize_text_field( $request->get_param( 'state' ) ?: '' ),
				'salary_min'  => $request->get_param( 'salary_min' ),
				'salary_max'  => $request->get_param( 'salary_max' ),
				'sort'        => sanitize_text_field( $request->get_param( 'sort' ) ?: 'newest' ),
				'lat'         => $request->get_param( 'lat' ),
				'lng'         => $request->get_param( 'lng' ),
				'distance_km' => absint( $request->get_param( 'distance_km' ) ?: 0 ),
				'page'        => absint( $request->get_param( 'page' ) ?: 1 ),
				'per_page'    => absint( $request->get_param( 'per_page' ) ?: 20 ),
			)
		);

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * GET /jobs/{slug} — single public job by slug.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_public_job_by_slug( WP_REST_Request $request ): WP_REST_Response {
		$job_posting = new AJEM_Job_Posting();
		$job         = $job_posting->get_by_slug( sanitize_text_field( $request['slug'] ) );

		if ( ! $job || $job->status !== 'active' ) {
			return new WP_REST_Response( array( 'message' => __( 'Job not found.', 'ai-job-employer-manager' ) ), 404 );
		}

		$profile  = new AJEM_Employer_Profile();
		$employer = $profile->get_by_id( (int) $job->employer_id );

		if ( $employer ) {
			// Don't expose sensitive employer data.
			unset( $employer->user_id );
		}

		$job->required_skills_array = json_decode( $job->required_skills ?? '[]', true ) ?? array();

		return new WP_REST_Response( array( 'job' => $job, 'employer' => $employer ), 200 );
	}

	/**
	 * GET /jobs/{id}/view — increment view count.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function track_job_view( WP_REST_Request $request ): WP_REST_Response {
		$job_posting = new AJEM_Job_Posting();
		$job_posting->increment_views( (int) $request['id'] );
		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * POST /jobs/{id}/apply — candidate applies for job.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function apply_for_job( WP_REST_Request $request ): WP_REST_Response {
		$job_posting = new AJEM_Job_Posting();
		$job         = $job_posting->get_by_id( (int) $request['id'] );

		if ( ! $job || $job->status !== 'active' ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Job not found or not active.', 'ai-job-employer-manager' ) ), 404 );
		}

		// Resolve candidate ID from candidate plugin table.
		global $wpdb;
		$candidates_table = $wpdb->prefix . 'job_candidates';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$candidates_table}'" ) !== $candidates_table ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Candidate profile not found. Please install AI Job Candidate Manager.', 'ai-job-employer-manager' ) ), 400 );
		}

		$candidate = $wpdb->get_row(
			$wpdb->prepare( "SELECT id FROM `{$candidates_table}` WHERE user_id = %d LIMIT 1", get_current_user_id() )
		);

		if ( ! $candidate ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Candidate profile not found.', 'ai-job-employer-manager' ) ), 400 );
		}

		$data   = $request->get_json_params() ?: $request->get_body_params();
		$apps   = new AJEM_Job_Applications();
		$result = $apps->apply( (int) $job->id, (int) $candidate->id, (int) $job->employer_id, $data );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => $result->get_error_message() ), 400 );
		}

		return new WP_REST_Response( array( 'success' => true, 'application_id' => $result ), 201 );
	}

	/**
	 * GET /locations/search — location autocomplete.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function search_locations( WP_REST_Request $request ): WP_REST_Response {
		$query    = sanitize_text_field( $request->get_param( 'q' ) ?: '' );
		$location = new AJEM_Location_Manager();
		$results  = $location->search_locations( $query );
		return new WP_REST_Response( $results, 200 );
	}
}
