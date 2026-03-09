<?php
/**
 * Job Applications — shared table management, status updates, notes.
 *
 * @package AI_Job_Employer_Manager
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AJEM_Job_Applications
 *
 * Manages job applications in the shared wp_job_applications table.
 * Employers update status and add notes; candidates (via candidate plugin) read this table.
 *
 * @since 1.0.0
 */
class AJEM_Job_Applications {

	/**
	 * Submit a new job application.
	 *
	 * @param int    $job_id       Job ID.
	 * @param int    $candidate_id Candidate row ID (from wp_job_candidates).
	 * @param int    $employer_id  Employer row ID.
	 * @param array  $data         Application data (resume_id, cover_letter).
	 * @return int|WP_Error New application ID or error.
	 */
	public function apply( int $job_id, int $candidate_id, int $employer_id, array $data = array() ): int|WP_Error {
		global $wpdb;
		$table = $wpdb->prefix . 'job_applications';

		// Check for duplicate.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM `{$table}` WHERE job_id = %d AND candidate_id = %d LIMIT 1",
				$job_id,
				$candidate_id
			)
		);

		if ( $existing ) {
			return new WP_Error( 'duplicate_application', __( 'You have already applied for this job.', 'ai-job-employer-manager' ) );
		}

		$cover_letter = isset( $data['cover_letter'] ) ? wp_kses_post( $data['cover_letter'] ) : '';
		$resume_id    = isset( $data['resume_id'] ) ? absint( $data['resume_id'] ) : null;

		$result = $wpdb->insert(
			$table,
			array(
				'job_id'       => $job_id,
				'candidate_id' => $candidate_id,
				'employer_id'  => $employer_id,
				'resume_id'    => $resume_id,
				'cover_letter' => $cover_letter,
				'status'       => 'applied',
			),
			array( '%d', '%d', '%d', $resume_id ? '%d' : null, '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to submit application.', 'ai-job-employer-manager' ) );
		}

		$application_id = (int) $wpdb->insert_id;

		// Increment application count on job listing.
		$this->increment_application_count( $job_id );

		do_action( 'ajem_application_received', $application_id, $job_id, $candidate_id, $employer_id );

		return $application_id;
	}

	/**
	 * Update application status.
	 *
	 * @param int    $application_id Application ID.
	 * @param int    $employer_id    Employer ID (ownership check).
	 * @param string $status         New status.
	 * @return bool|WP_Error
	 */
	public function update_status( int $application_id, int $employer_id, string $status ): bool|WP_Error {
		$valid_statuses = apply_filters(
			'ajem_application_statuses',
			array( 'applied', 'viewed', 'shortlisted', 'rejected', 'interview_scheduled', 'hired' )
		);

		if ( ! in_array( $status, $valid_statuses, true ) ) {
			return new WP_Error( 'invalid_status', __( 'Invalid application status.', 'ai-job-employer-manager' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'job_applications';

		// Verify ownership.
		$application = $this->get_by_id( $application_id );
		if ( ! $application ) {
			return new WP_Error( 'not_found', __( 'Application not found.', 'ai-job-employer-manager' ) );
		}

		if ( (int) $application->employer_id !== $employer_id ) {
			return new WP_Error( 'unauthorized', __( 'You do not have permission to update this application.', 'ai-job-employer-manager' ) );
		}

		$old_status = $application->status;

		$result = $wpdb->update(
			$table,
			array( 'status' => $status ),
			array( 'id' => $application_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false !== $result ) {
			do_action( 'ajem_application_status_updated', $application_id, $status, $old_status );
		}

		return false !== $result;
	}

	/**
	 * Add or update employer notes on an application.
	 *
	 * @param int    $application_id Application ID.
	 * @param int    $employer_id    Employer ID.
	 * @param string $notes          Notes text.
	 * @return bool|WP_Error
	 */
	public function add_notes( int $application_id, int $employer_id, string $notes ): bool|WP_Error {
		$application = $this->get_by_id( $application_id );

		if ( ! $application ) {
			return new WP_Error( 'not_found', __( 'Application not found.', 'ai-job-employer-manager' ) );
		}

		if ( (int) $application->employer_id !== $employer_id ) {
			return new WP_Error( 'unauthorized', __( 'You do not have permission to update this application.', 'ai-job-employer-manager' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'job_applications';

		$result = $wpdb->update(
			$table,
			array( 'employer_notes' => wp_kses_post( $notes ) ),
			array( 'id' => $application_id ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get applications for a specific job.
	 *
	 * @param int   $job_id      Job ID.
	 * @param int   $employer_id Employer ID for ownership check.
	 * @param int   $page        Page number.
	 * @param int   $per_page    Per page count.
	 * @return array
	 */
	public function get_by_job( int $job_id, int $employer_id, int $page = 1, int $per_page = 20 ): array {
		global $wpdb;

		$table      = $wpdb->prefix . 'job_applications';
		$candidates = $wpdb->prefix . 'job_candidates'; // candidate plugin table.
		$offset     = ( $page - 1 ) * $per_page;

		// Check if candidate table exists.
		$candidate_table_exists = $this->table_exists( $candidates );

		if ( $candidate_table_exists ) {
			$sql = $wpdb->prepare(
				"SELECT a.*, c.full_name, c.profile_photo, c.contact_phone, c.expected_salary
				 FROM `{$table}` a
				 LEFT JOIN `{$candidates}` c ON a.candidate_id = c.id
				 WHERE a.job_id = %d AND a.employer_id = %d
				 ORDER BY a.applied_at DESC
				 LIMIT %d OFFSET %d",
				$job_id,
				$employer_id,
				$per_page,
				$offset
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT a.*
				 FROM `{$table}` a
				 WHERE a.job_id = %d AND a.employer_id = %d
				 ORDER BY a.applied_at DESC
				 LIMIT %d OFFSET %d",
				$job_id,
				$employer_id,
				$per_page,
				$offset
			);
		}

		$applications = $wpdb->get_results( $sql );
		$total        = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$table}` WHERE job_id = %d AND employer_id = %d",
				$job_id,
				$employer_id
			)
		);

		return array(
			'applications' => $applications ?? array(),
			'total'        => $total,
			'pages'        => $per_page > 0 ? (int) ceil( $total / $per_page ) : 1,
		);
	}

	/**
	 * Get a single application by ID.
	 *
	 * @param int $application_id Application ID.
	 * @return object|null
	 */
	public function get_by_id( int $application_id ): ?object {
		global $wpdb;
		$table = $wpdb->prefix . 'job_applications';

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d LIMIT 1", $application_id )
		) ?: null;
	}

	/**
	 * Get all applications for an employer.
	 *
	 * @param int $employer_id Employer ID.
	 * @param int $page        Page number.
	 * @param int $per_page    Per page count.
	 * @return array
	 */
	public function get_by_employer( int $employer_id, int $page = 1, int $per_page = 20 ): array {
		global $wpdb;
		$table  = $wpdb->prefix . 'job_applications';
		$jobs   = $wpdb->prefix . 'job_listings';
		$offset = ( $page - 1 ) * $per_page;

		$applications = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.*, j.job_title, j.job_slug
				 FROM `{$table}` a
				 LEFT JOIN `{$jobs}` j ON a.job_id = j.id
				 WHERE a.employer_id = %d
				 ORDER BY a.applied_at DESC
				 LIMIT %d OFFSET %d",
				$employer_id,
				$per_page,
				$offset
			)
		);

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$table}` WHERE employer_id = %d",
				$employer_id
			)
		);

		return array(
			'applications' => $applications ?? array(),
			'total'        => $total,
			'pages'        => $per_page > 0 ? (int) ceil( $total / $per_page ) : 1,
		);
	}

	/**
	 * Increment the application count on the job listing.
	 *
	 * @param int $job_id Job ID.
	 * @return void
	 */
	private function increment_application_count( int $job_id ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'job_listings';

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE `{$table}` SET applications_count = applications_count + 1 WHERE id = %d",
				$job_id
			)
		);
	}

	/**
	 * Check whether a given DB table exists.
	 *
	 * @param string $table_name Full table name including prefix.
	 * @return bool
	 */
	private function table_exists( string $table_name ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name;
	}
}
