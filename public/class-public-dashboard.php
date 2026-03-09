<?php
/**
 * Public Dashboard — shortcodes, rewrite rule handling, asset enqueueing.
 *
 * @package AI_Job_Employer_Manager
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AJEM_Public_Dashboard
 *
 * Registers the three public-facing shortcodes:
 * - [employer_dashboard]  → full employer control panel
 * - [job_listings]        → public job search/listing page
 * - [job_detail]          → single job detail (also handles SEO URLs)
 *
 * @since 1.0.0
 */
class AJEM_Public_Dashboard {

	/**
	 * Register shortcode + query-var hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_shortcode( 'employer_dashboard', array( $this, 'render_employer_dashboard' ) );
		add_shortcode( 'job_listings', array( $this, 'render_job_listings' ) );
		add_shortcode( 'job_detail', array( $this, 'render_job_detail' ) );

		// Intercept /jobs/{slug} requests and serve them with a theme-wrapped template.
		add_filter( 'template_include', array( $this, 'filter_job_template' ) );
	}

	/**
	 * Replace the default WordPress template with our single-job template when
	 * the `ajem_job_slug` rewrite variable is present.
	 *
	 * Using `template_include` (rather than `template_redirect` + exit) means
	 * WordPress still calls `get_header()` and `get_footer()`, so the active
	 * theme wraps the page and all enqueued CSS/JS are output correctly.
	 *
	 * @param string $template Resolved template path.
	 * @return string
	 */
	public function filter_job_template( string $template ): string {
		$slug = get_query_var( 'ajem_job_slug' );
		if ( ! $slug ) {
			return $template;
		}

		$job_posting = new AJEM_Job_Posting();
		$job         = $job_posting->get_by_slug( sanitize_text_field( $slug ) );

		if ( ! $job ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			return get_404_template();
		}

		// Make the job available as a global so the template and the asset
		// localisation callback (enqueue_frontend_assets) can both access it.
		global $ajem_current_job;
		$ajem_current_job = $job;

		// Increment view counter (deduplicated per-IP via transients).
		$job_posting->increment_views( (int) $job->id );

		return AJEM_PLUGIN_DIR . 'templates/single-ajem-job.php';
	}

	/**
	 * [employer_dashboard] shortcode — displays full employer control panel.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_employer_dashboard( array $atts = array() ): string {
		if ( ! is_user_logged_in() ) {
			return sprintf(
				'<p>%s <a href="%s">%s</a></p>',
				esc_html__( 'Please', 'ai-job-employer-manager' ),
				esc_url( wp_login_url( get_permalink() ) ),
				esc_html__( 'log in to access the employer dashboard.', 'ai-job-employer-manager' )
			);
		}

		if ( ! current_user_can( 'ajem_manage_jobs' ) && ! current_user_can( 'administrator' ) ) {
			return '<p>' . esc_html__( 'You do not have permission to view this page.', 'ai-job-employer-manager' ) . '</p>';
		}

		$profile  = new AJEM_Employer_Profile();
		$employer = $profile->get_by_user_id( get_current_user_id() );

		$analytics   = new AJEM_Job_Analytics();
		$stats       = $employer ? $analytics->get_employer_stats( (int) $employer->id ) : array();
		$listings    = new AJEM_Job_Listings();
		$jobs_result = $employer ? $listings->get_employer_jobs( (int) $employer->id ) : array( 'jobs' => array(), 'total' => 0 );

		ob_start();
		include AJEM_PLUGIN_DIR . 'templates/employer-dashboard.php';
		return ob_get_clean();
	}

	/**
	 * [job_listings] shortcode — public job listing with search & filters.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_job_listings( array $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'per_page' => 20,
				'sort'     => 'newest',
			),
			$atts,
			'job_listings'
		);

		// Read GET params for public filter/search.
		$args = array(
			'keyword'     => sanitize_text_field( wp_unslash( $_GET['keyword'] ?? '' ) ),
			'job_type'    => sanitize_text_field( wp_unslash( $_GET['job_type'] ?? '' ) ),
			'industry'    => sanitize_text_field( wp_unslash( $_GET['industry'] ?? '' ) ),
			'city'        => sanitize_text_field( wp_unslash( $_GET['city'] ?? '' ) ),
			'state'       => sanitize_text_field( wp_unslash( $_GET['state'] ?? '' ) ),
			'salary_min'  => is_numeric( $_GET['salary_min'] ?? '' ) ? (float) $_GET['salary_min'] : null,
			'salary_max'  => is_numeric( $_GET['salary_max'] ?? '' ) ? (float) $_GET['salary_max'] : null,
			'sort'        => sanitize_text_field( wp_unslash( $_GET['sort'] ?? $atts['sort'] ) ),
			'page'        => max( 1, absint( $_GET['paged'] ?? 1 ) ),
			'per_page'    => absint( $atts['per_page'] ),
		);

		$listings     = new AJEM_Job_Listings();
		$result       = $listings->get_listings( $args );
		$filter_options = $listings->get_filter_options();

		ob_start();
		include AJEM_PLUGIN_DIR . 'templates/job-listings.php';
		return ob_get_clean();
	}

	/**
	 * [job_detail] shortcode — single job detail page.
	 *
	 * @param array $atts Shortcode attributes (optional job_id or job_slug).
	 * @return string
	 */
	public function render_job_detail( array $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'job_id'   => 0,
				'job_slug' => '',
			),
			$atts,
			'job_detail'
		);

		$job_posting = new AJEM_Job_Posting();

		if ( $atts['job_id'] ) {
			$job = $job_posting->get_by_id( absint( $atts['job_id'] ) );
		} elseif ( $atts['job_slug'] ) {
			$job = $job_posting->get_by_slug( sanitize_text_field( $atts['job_slug'] ) );
		} else {
			// Try URL param.
			$slug = get_query_var( 'ajem_job_slug' );
			$job  = $slug ? $job_posting->get_by_slug( sanitize_text_field( $slug ) ) : null;
		}

		if ( ! $job ) {
			return '<p>' . esc_html__( 'Job not found.', 'ai-job-employer-manager' ) . '</p>';
		}

		return static::render_job_detail_for_job( $job );
	}

	/**
	 * Render the job detail template for a given job object.
	 *
	 * Made public static so it can be called from `templates/single-ajem-job.php`
	 * without needing to instantiate AJEM_Public_Dashboard.
	 *
	 * @param object $job Job DB row.
	 * @return string Rendered HTML.
	 */
	public static function render_job_detail_for_job( object $job ): string {
		$profile  = new AJEM_Employer_Profile();
		$employer = $profile->get_by_id( (int) $job->employer_id );

		$listings = new AJEM_Job_Listings();

		// Related jobs: same industry, exclude current job.
		$related_jobs = array();
		if ( ! empty( $job->industry ) ) {
			$related_result = $listings->get_listings(
				array(
					'industry' => $job->industry,
					'per_page' => 5,
				)
			);
			$related_jobs = array_filter(
				$related_result['jobs'] ?? array(),
				fn( $j ) => (int) $j->id !== (int) $job->id
			);
			$related_jobs = array_slice( array_values( $related_jobs ), 0, 4 );
		}

		// City jobs: same city, exclude current job and related jobs.
		$city_jobs = array();
		if ( ! empty( $job->city ) ) {
			$city_result = $listings->get_listings(
				array(
					'city'     => $job->city,
					'per_page' => 6,
				)
			);
			$city_jobs = array_filter(
				$city_result['jobs'] ?? array(),
				fn( $j ) => (int) $j->id !== (int) $job->id
			);
			$city_jobs = array_slice( array_values( $city_jobs ), 0, 4 );
		}

		// Nearby jobs: Haversine within 50 km, exclude current job.
		$nearby_jobs = array();
		if ( ! empty( $job->latitude ) && ! empty( $job->longitude ) ) {
			$nearby_result = $listings->get_listings(
				array(
					'lat'         => (float) $job->latitude,
					'lng'         => (float) $job->longitude,
					'distance_km' => 50,
					'per_page'    => 6,
				)
			);
			$nearby_jobs = array_filter(
				$nearby_result['jobs'] ?? array(),
				fn( $j ) => (int) $j->id !== (int) $job->id
			);
			$nearby_jobs = array_slice( array_values( $nearby_jobs ), 0, 4 );
		}

		$job->required_skills_array = json_decode( $job->required_skills ?? '[]', true ) ?? array();

		ob_start();
		include AJEM_PLUGIN_DIR . 'templates/job-detail.php';
		return ob_get_clean();
	}
}
