<?php
/**
 * Job Analytics — view tracking, application counts, dashboard statistics.
 *
 * @package AI_Job_Employer_Manager
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AJEM_Job_Analytics
 *
 * Provides analytics data for the employer dashboard:
 * view counts, application counts, shortlisted/hired stats,
 * time-based breakdowns, and top-performing job listings.
 *
 * @since 1.0.0
 */
class AJEM_Job_Analytics {

	/**
	 * Register hooks for view tracking.
	 *
	 * @return void
	 */
	public function init(): void {
		// Track job views via template_redirect for SEO URL pages.
		add_action( 'template_redirect', array( $this, 'maybe_track_job_view' ) );
	}

	/**
	 * Track job view on the public job detail page.
	 *
	 * @return void
	 */
	public function maybe_track_job_view(): void {
		$slug = get_query_var( 'ajem_job_slug' );
		if ( ! $slug ) {
			return;
		}

		$job_posting = new AJEM_Job_Posting();
		$job         = $job_posting->get_by_slug( sanitize_text_field( $slug ) );

		if ( $job ) {
			$job_posting->increment_views( (int) $job->id );
		}
	}

	/**
	 * Get full dashboard statistics for an employer.
	 *
	 * @param int $employer_id Employer row ID.
	 * @return array Dashboard stats.
	 */
	public function get_employer_stats( int $employer_id ): array {
		global $wpdb;
		$jobs_table  = $wpdb->prefix . 'job_listings';
		$apps_table  = $wpdb->prefix . 'job_applications';
		$short_table = $wpdb->prefix . 'job_shortlisted_candidates';

		$cache_key = "ajem_employer_stats_{$employer_id}";
		$cached    = get_transient( $cache_key );
		if ( $cached !== false ) {
			return $cached;
		}

		// Total jobs.
		$total_jobs = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM `{$jobs_table}` WHERE employer_id = %d", $employer_id )
		);

		// Active jobs.
		$active_jobs = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$jobs_table}` WHERE employer_id = %d AND status = 'active'",
				$employer_id
			)
		);

		// Total applications.
		$total_applications = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM `{$apps_table}` WHERE employer_id = %d", $employer_id )
		);

		// New applications this week.
		$applications_this_week = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$apps_table}`
				 WHERE employer_id = %d AND applied_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
				$employer_id
			)
		);

		// Applications this month.
		$applications_this_month = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$apps_table}`
				 WHERE employer_id = %d AND applied_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
				$employer_id
			)
		);

		// Shortlisted.
		$shortlisted = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$apps_table}` WHERE employer_id = %d AND status = 'shortlisted'",
				$employer_id
			)
		);

		// Hired.
		$hired = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$apps_table}` WHERE employer_id = %d AND status = 'hired'",
				$employer_id
			)
		);

		// Total views across all jobs.
		$total_views = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(views_count) FROM `{$jobs_table}` WHERE employer_id = %d",
				$employer_id
			)
		);

		$stats = array(
			'total_jobs'               => $total_jobs,
			'active_jobs'              => $active_jobs,
			'total_applications'       => $total_applications,
			'applications_this_week'   => $applications_this_week,
			'applications_this_month'  => $applications_this_month,
			'shortlisted'              => $shortlisted,
			'hired'                    => $hired,
			'total_views'              => $total_views,
		);

		set_transient( $cache_key, $stats, 15 * MINUTE_IN_SECONDS );

		return $stats;
	}

	/**
	 * Get per-job analytics for a single job.
	 *
	 * @param int $job_id      Job ID.
	 * @param int $employer_id Employer ID (for ownership check).
	 * @return array|null Analytics data or null if not found / unauthorised.
	 */
	public function get_job_stats( int $job_id, int $employer_id ): ?array {
		global $wpdb;
		$jobs_table  = $wpdb->prefix . 'job_listings';
		$apps_table  = $wpdb->prefix . 'job_applications';

		$job = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$jobs_table}` WHERE id = %d AND employer_id = %d LIMIT 1",
				$job_id,
				$employer_id
			)
		);

		if ( ! $job ) {
			return null;
		}

		// Applications by status.
		$status_breakdown = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, COUNT(*) AS count
				 FROM `{$apps_table}`
				 WHERE job_id = %d
				 GROUP BY status",
				$job_id
			)
		);

		// Applications per day (last 30 days).
		$daily = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(applied_at) AS day, COUNT(*) AS count
				 FROM `{$apps_table}`
				 WHERE job_id = %d AND applied_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
				 GROUP BY day
				 ORDER BY day ASC",
				$job_id
			)
		);

		return array(
			'job_id'           => $job_id,
			'job_title'        => $job->job_title,
			'status'           => $job->status,
			'views_count'      => (int) $job->views_count,
			'applications_count' => (int) $job->applications_count,
			'status_breakdown' => $status_breakdown ?? array(),
			'daily_applications' => $daily ?? array(),
			'created_at'       => $job->created_at,
			'application_deadline' => $job->application_deadline,
		);
	}

	/**
	 * Get top performing jobs (most views + most applications).
	 *
	 * @param int $employer_id Employer ID.
	 * @param int $limit       Number of jobs to return.
	 * @return array
	 */
	public function get_top_jobs( int $employer_id, int $limit = 5 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'job_listings';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, job_title, job_slug, status, views_count, applications_count, created_at
				 FROM `{$table}`
				 WHERE employer_id = %d
				 ORDER BY ( views_count + applications_count * 5 ) DESC
				 LIMIT %d",
				$employer_id,
				$limit
			)
		) ?? array();
	}

	/**
	 * Get admin-level global statistics (for the admin settings page).
	 *
	 * @return array
	 */
	public function get_global_stats(): array {
		global $wpdb;

		$cache_key = 'ajem_global_stats';
		$cached    = get_transient( $cache_key );
		if ( $cached !== false ) {
			return $cached;
		}

		$stats = array(
			'total_employers'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}job_employers`" ),
			'total_jobs'         => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}job_listings`" ),
			'active_jobs'        => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}job_listings` WHERE status='active'" ),
			'total_applications' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}job_applications`" ),
			'total_locations'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}job_locations`" ),
		);

		set_transient( $cache_key, $stats, 30 * MINUTE_IN_SECONDS );

		return $stats;
	}
}
