<?php
/**
 * Job Posting — create, edit, status management, slug generation, cron expiry.
 *
 * @package AI_Job_Employer_Manager
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AJEM_Job_Posting
 *
 * Handles job lifecycle: create → edit → pause/activate → expire → close/delete.
 * Also manages slug generation, required-skills JSON, WP Cron expiry, and view counting.
 *
 * @since 1.0.0
 */
class AJEM_Job_Posting {

	/**
	 * Register hooks (cron etc.).
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'ajem_expire_jobs', array( $this, 'expire_overdue_jobs' ) );

		if ( ! wp_next_scheduled( 'ajem_expire_jobs' ) ) {
			wp_schedule_event( time(), 'daily', 'ajem_expire_jobs' );
		}
	}

	/**
	 * Create a new job listing.
	 *
	 * @param int   $employer_id Employer (row) ID from wp_job_employers.
	 * @param array $data        Job data.
	 * @return int|WP_Error New job ID or WP_Error on failure.
	 */
	public function create( int $employer_id, array $data ): int|WP_Error {
		$validation = $this->validate( $data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Check employer job limit.
		$max_jobs = (int) apply_filters( 'ajem_max_jobs_per_employer', get_option( 'ajem_max_jobs_per_employer', 50 ) );
		if ( $max_jobs > 0 ) {
			$current_count = $this->count_by_employer( $employer_id );
			if ( $current_count >= $max_jobs ) {
				return new WP_Error( 'limit_exceeded', __( 'Maximum job limit reached for your account.', 'ai-job-employer-manager' ) );
			}
		}

		global $wpdb;
		$table    = $wpdb->prefix . 'job_listings';
		$prepared = $this->prepare_data( $data );

		$prepared['employer_id'] = $employer_id;
		$prepared['job_slug']    = $this->generate_unique_slug(
			$prepared['job_title'],
			$prepared['city'] ?? ''
		);

		$result = $wpdb->insert( $table, $prepared );

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to create job listing.', 'ai-job-employer-manager' ) );
		}

		$job_id = (int) $wpdb->insert_id;

		// Create a corresponding WP post so the job is visible in WP admin.
		$this->sync_to_wp_post( $job_id, $prepared );

		do_action( 'ajem_job_created', $job_id, $employer_id, $prepared );

		return $job_id;
	}

	/**
	 * Update an existing job listing.
	 *
	 * @param int   $job_id      Job ID.
	 * @param int   $employer_id Employer ID (for ownership check).
	 * @param array $data        Updated data.
	 * @return bool|WP_Error True on success or WP_Error.
	 */
	public function update( int $job_id, int $employer_id, array $data ): bool|WP_Error {
		$job = $this->get_by_id( $job_id );
		if ( ! $job ) {
			return new WP_Error( 'not_found', __( 'Job not found.', 'ai-job-employer-manager' ) );
		}

		if ( (int) $job->employer_id !== $employer_id ) {
			return new WP_Error( 'unauthorized', __( 'You do not own this job listing.', 'ai-job-employer-manager' ) );
		}

		global $wpdb;
		$table    = $wpdb->prefix . 'job_listings';
		$prepared = $this->prepare_data( $data );

		// Regenerate slug only if title or city changed.
		if ( isset( $prepared['job_title'] ) || isset( $prepared['city'] ) ) {
			$new_title = $prepared['job_title'] ?? $job->job_title;
			$new_city  = $prepared['city'] ?? $job->city;
			$new_slug  = $this->generate_slug( $new_title, $new_city );

			// Only update slug if it changed.
			if ( $new_slug !== $job->job_slug ) {
				$prepared['job_slug'] = $this->generate_unique_slug( $new_title, $new_city, $job_id );
			}
		}

		$result = $wpdb->update(
			$table,
			$prepared,
			array(
				'id'          => $job_id,
				'employer_id' => $employer_id,
			),
			null,
			array( '%d', '%d' )
		);

		if ( false !== $result ) {
			// Keep the WP post in sync.
			$this->sync_to_wp_post( $job_id, array_merge( $prepared, array( 'employer_id' => $employer_id ) ) );
			do_action( 'ajem_job_updated', $job_id, $employer_id, $prepared );
		}

		return false !== $result;
	}

	/**
	 * Change job status.
	 *
	 * @param int    $job_id      Job ID.
	 * @param int    $employer_id Employer ID.
	 * @param string $status      New status.
	 * @return bool|WP_Error
	 */
	public function change_status( int $job_id, int $employer_id, string $status ): bool|WP_Error {
		$allowed = array( 'active', 'paused', 'expired', 'closed', 'draft' );
		if ( ! in_array( $status, $allowed, true ) ) {
			return new WP_Error( 'invalid_status', __( 'Invalid job status.', 'ai-job-employer-manager' ) );
		}

		$job = $this->get_by_id( $job_id );
		if ( ! $job ) {
			return new WP_Error( 'not_found', __( 'Job not found.', 'ai-job-employer-manager' ) );
		}

		if ( (int) $job->employer_id !== $employer_id ) {
			return new WP_Error( 'unauthorized', __( 'You do not own this job listing.', 'ai-job-employer-manager' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'job_listings';

		$result = $wpdb->update(
			$table,
			array( 'status' => $status ),
			array( 'id' => $job_id, 'employer_id' => $employer_id ),
			array( '%s' ),
			array( '%d', '%d' )
		);

		if ( false !== $result ) {
			// Keep WP post status in sync.
			$this->sync_to_wp_post( $job_id, array( 'status' => $status, 'employer_id' => $employer_id ) );
			do_action( 'ajem_job_status_changed', $job_id, $status, $job->status );
		}

		return false !== $result;
	}

	/**
	 * Delete a job listing.
	 *
	 * @param int $job_id      Job ID.
	 * @param int $employer_id Employer ID for ownership check.
	 * @return bool|WP_Error
	 */
	public function delete( int $job_id, int $employer_id ): bool|WP_Error {
		$job = $this->get_by_id( $job_id );
		if ( ! $job ) {
			return new WP_Error( 'not_found', __( 'Job not found.', 'ai-job-employer-manager' ) );
		}

		if ( (int) $job->employer_id !== $employer_id ) {
			return new WP_Error( 'unauthorized', __( 'You do not own this job listing.', 'ai-job-employer-manager' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'job_listings';

		$result = $wpdb->delete(
			$table,
			array( 'id' => $job_id, 'employer_id' => $employer_id ),
			array( '%d', '%d' )
		);

		if ( false !== $result ) {
			// Move the linked WP post to Trash.
			$wp_post_id = $this->get_wp_post_id( $job_id );
			if ( $wp_post_id ) {
				wp_trash_post( $wp_post_id );
			}
		}

		return false !== $result;
	}

	/**
	 * Get a single job by ID.
	 *
	 * @param int $job_id Job ID.
	 * @return object|null
	 */
	public function get_by_id( int $job_id ): ?object {
		global $wpdb;
		$table = $wpdb->prefix . 'job_listings';

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d LIMIT 1", $job_id )
		) ?: null;
	}

	/**
	 * Get a single job by slug.
	 *
	 * @param string $slug Job slug.
	 * @return object|null
	 */
	public function get_by_slug( string $slug ): ?object {
		global $wpdb;
		$table = $wpdb->prefix . 'job_listings';

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE job_slug = %s LIMIT 1", $slug )
		) ?: null;
	}

	/**
	 * Count jobs belonging to an employer.
	 *
	 * @param int $employer_id Employer ID.
	 * @return int
	 */
	public function count_by_employer( int $employer_id ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'job_listings';

		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE employer_id = %d", $employer_id )
		);
	}

	/**
	 * Increment views count for a job, deduplicated by session.
	 *
	 * @param int $job_id Job ID.
	 * @return void
	 */
	public function increment_views( int $job_id ): void {
		if ( ! session_id() ) {
			// Use WordPress user session / transient dedup instead of PHP sessions.
		}

		$transient_key = 'ajem_view_' . $job_id . '_' . md5( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ) );

		if ( get_transient( $transient_key ) ) {
			return; // Already counted this IP today.
		}

		set_transient( $transient_key, true, DAY_IN_SECONDS );

		global $wpdb;
		$table = $wpdb->prefix . 'job_listings';

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE `{$table}` SET views_count = views_count + 1 WHERE id = %d",
				$job_id
			)
		);
	}

	/**
	 * WP Cron callback — expire jobs past application_deadline.
	 *
	 * @return void
	 */
	public function expire_overdue_jobs(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'job_listings';

		$expired_ids = $wpdb->get_col(
			"SELECT id FROM `{$table}`
			 WHERE status = 'active'
			   AND application_deadline IS NOT NULL
			   AND application_deadline < CURDATE()"
		);

		if ( empty( $expired_ids ) ) {
			return;
		}

		$placeholders = implode( ',', array_fill( 0, count( $expired_ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE `{$table}` SET status = 'expired' WHERE id IN ({$placeholders})",
				...$expired_ids
			)
		);

		foreach ( $expired_ids as $job_id ) {
			do_action( 'ajem_job_status_changed', (int) $job_id, 'expired', 'active' );
		}
	}

	/**
	 * Generate a URL-safe slug from job title and city.
	 *
	 * @param string $title Job title.
	 * @param string $city  City name.
	 * @return string
	 */
	private function generate_slug( string $title, string $city ): string {
		$combined = trim( $title . ' ' . $city );
		return sanitize_title( $combined );
	}

	/**
	 * Generate a unique slug, appending a counter if necessary.
	 *
	 * @param string $title     Job title.
	 * @param string $city      City name.
	 * @param int    $exclude_id Job ID to exclude (for updates).
	 * @return string Unique slug.
	 */
	private function generate_unique_slug( string $title, string $city, int $exclude_id = 0 ): string {
		global $wpdb;
		$table     = $wpdb->prefix . 'job_listings';
		$base_slug = $this->generate_slug( $title, $city );
		$slug      = $base_slug;
		$counter   = 1;

		while ( true ) {
			if ( $exclude_id > 0 ) {
				$exists = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT id FROM `{$table}` WHERE job_slug = %s AND id != %d LIMIT 1",
						$slug,
						$exclude_id
					)
				);
			} else {
				$exists = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT id FROM `{$table}` WHERE job_slug = %s LIMIT 1",
						$slug
					)
				);
			}

			if ( ! $exists ) {
				break;
			}

			++$counter;
			$slug = $base_slug . '-' . $counter;
		}

		return $slug;
	}

	/**
	 * Get the WP post ID linked to a job.
	 *
	 * @param int $job_id Job ID in our custom table.
	 * @return int WP post ID, or 0 if not found.
	 */
	public function get_wp_post_id( int $job_id ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'job_listings';

		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT wp_post_id FROM `{$table}` WHERE id = %d LIMIT 1", $job_id )
		);
	}

	/**
	 * Create or update the WP post (`ajem_job` CPT) that mirrors a job row.
	 *
	 * This gives administrators visibility of all jobs in the WP admin dashboard
	 * (Posts → All Jobs) without changing the public URL routing.
	 *
	 * @param int   $job_id   Job ID in our custom table.
	 * @param array $data     Partial job data (subset that was just saved).
	 * @return void
	 */
	private function sync_to_wp_post( int $job_id, array $data ): void {
		// CPT must exist; skip gracefully if it hasn't been registered yet.
		if ( ! post_type_exists( 'ajem_job' ) ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'job_listings';

		// Fetch the full row so we always have all fields.
		$job = $this->get_by_id( $job_id );
		if ( ! $job ) {
			return;
		}

		$post_status   = ( 'active' === $job->status ) ? 'publish' : 'draft';
		$existing_post = (int) $job->wp_post_id;

		$post_arr = array(
			'post_title'   => wp_strip_all_tags( $job->job_title ),
			'post_content' => $job->job_description ?? '',
			'post_status'  => $post_status,
			'post_type'    => 'ajem_job',
			'meta_input'   => array(
				'_ajem_job_id'      => $job_id,
				'_ajem_job_slug'    => $job->job_slug,
				'_ajem_job_status'  => $job->status,
				'_ajem_job_city'    => $job->city,
				'_ajem_employer_id' => (int) $job->employer_id,
			),
		);

		if ( $existing_post > 0 && get_post( $existing_post ) ) {
			// Update existing WP post.
			$post_arr['ID'] = $existing_post;
			wp_update_post( $post_arr );
		} else {
			// Create a new WP post and store its ID back in our table.
			$post_id = wp_insert_post( $post_arr, true );

			if ( $post_id && ! is_wp_error( $post_id ) ) {
				$wpdb->update(
					$table,
					array( 'wp_post_id' => $post_id ),
					array( 'id' => $job_id ),
					array( '%d' ),
					array( '%d' )
				);
			}
		}
	}

	/**
	 * Validate required job fields.
	 *
	 * @param array $data Job data.
	 * @return true|WP_Error
	 */
	private function validate( array $data ): true|WP_Error {
		if ( empty( $data['job_title'] ) ) {
			return new WP_Error( 'missing_title', __( 'Job title is required.', 'ai-job-employer-manager' ) );
		}

		if ( empty( $data['job_description'] ) ) {
			return new WP_Error( 'missing_description', __( 'Job description is required.', 'ai-job-employer-manager' ) );
		}

		$valid_types = apply_filters( 'ajem_job_types', array( 'full_time', 'part_time', 'internship', 'contract', 'remote' ) );
		if ( ! empty( $data['job_type'] ) && ! in_array( $data['job_type'], $valid_types, true ) ) {
			return new WP_Error( 'invalid_type', __( 'Invalid job type.', 'ai-job-employer-manager' ) );
		}

		return true;
	}

	/**
	 * Sanitize and prepare job data for DB operations.
	 *
	 * @param array $data Raw input data.
	 * @return array Sanitized data.
	 */
	private function prepare_data( array $data ): array {
		$prepared = array();

		$text_fields = array( 'job_title', 'industry', 'experience_required', 'education_required', 'salary_currency', 'country', 'state', 'district', 'city' );
		foreach ( $text_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$prepared[ $field ] = sanitize_text_field( $data[ $field ] );
			}
		}

		if ( isset( $data['job_description'] ) ) {
			$prepared['job_description'] = wp_kses_post( $data['job_description'] );
		}

		if ( isset( $data['job_type'] ) ) {
			$valid_types = apply_filters( 'ajem_job_types', array( 'full_time', 'part_time', 'internship', 'contract', 'remote' ) );
			$prepared['job_type'] = in_array( $data['job_type'], $valid_types, true )
				? $data['job_type'] : 'full_time';
		}

		if ( isset( $data['status'] ) ) {
			$valid_statuses = array( 'active', 'paused', 'expired', 'closed', 'draft' );
			$prepared['status'] = in_array( $data['status'], $valid_statuses, true )
				? $data['status'] : 'draft';
		}

		if ( isset( $data['salary_min'] ) ) {
			$prepared['salary_min'] = is_numeric( $data['salary_min'] ) ? (float) $data['salary_min'] : null;
		}
		if ( isset( $data['salary_max'] ) ) {
			$prepared['salary_max'] = is_numeric( $data['salary_max'] ) ? (float) $data['salary_max'] : null;
		}

		if ( isset( $data['latitude'] ) ) {
			$prepared['latitude'] = is_numeric( $data['latitude'] ) ? (float) $data['latitude'] : null;
		}
		if ( isset( $data['longitude'] ) ) {
			$prepared['longitude'] = is_numeric( $data['longitude'] ) ? (float) $data['longitude'] : null;
		}

		if ( isset( $data['application_deadline'] ) ) {
			$date = sanitize_text_field( $data['application_deadline'] );
			$prepared['application_deadline'] = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : null;
		}

		if ( isset( $data['required_skills'] ) ) {
			$skills = is_array( $data['required_skills'] ) ? $data['required_skills'] : json_decode( $data['required_skills'], true );
			if ( is_array( $skills ) ) {
				$skills = array_map( 'sanitize_text_field', $skills );
				$prepared['required_skills'] = wp_json_encode( $skills );
			}
		}

		if ( isset( $data['is_featured'] ) ) {
			$prepared['is_featured'] = (int) (bool) $data['is_featured'];
		}

		return $prepared;
	}
}
