<?php
/**
 * Plugin activator — creates database tables and the employer role.
 *
 * @package AI_Job_Employer_Manager
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AJEM_Activator
 *
 * Handles all setup tasks that run on plugin activation:
 * - Create / upgrade database tables using dbDelta().
 * - Create the 'employer' WordPress role.
 * - Flush rewrite rules.
 *
 * @since 1.0.0
 */
class AJEM_Activator {

	/**
	 * Run activation routines.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::create_tables();
		self::create_employer_role();
		update_option( 'ajem_db_version', AJEM_DB_VERSION );
		flush_rewrite_rules();
	}

	/**
	 * Run upgrade routines (called when AJEM_DB_VERSION changes).
	 *
	 * - Applies any new columns via dbDelta.
	 * - Creates WP posts for existing jobs that don't have one yet.
	 *
	 * @return void
	 */
	public static function upgrade(): void {
		self::create_tables();            // dbDelta adds new columns (e.g. wp_post_id) to existing tables.
		self::sync_existing_jobs_to_wp(); // Create WP posts for jobs that have no wp_post_id.
	}

	/**
	 * Create WP posts of type `ajem_job` for all existing jobs that are not
	 * yet linked to a WP post.  This is idempotent: it only processes rows
	 * where `wp_post_id IS NULL` or `wp_post_id = 0`.
	 *
	 * @return void
	 */
	public static function sync_existing_jobs_to_wp(): void {
		global $wpdb;

		$table = $wpdb->prefix . 'job_listings';

		// Guard: if post type isn't registered yet, skip — we'll retry next request.
		if ( ! post_type_exists( 'ajem_job' ) ) {
			return;
		}

		$jobs = $wpdb->get_results(
			"SELECT id, employer_id, job_title, job_slug, job_description, status, city
			 FROM `{$table}`
			 WHERE wp_post_id IS NULL OR wp_post_id = 0"
		);

		if ( empty( $jobs ) ) {
			return;
		}

		foreach ( $jobs as $job ) {
			$post_status = ( 'active' === $job->status ) ? 'publish' : 'draft';

			$post_id = wp_insert_post(
				array(
					'post_title'   => wp_strip_all_tags( $job->job_title ),
					'post_content' => $job->job_description ?? '',
					'post_status'  => $post_status,
					'post_type'    => 'ajem_job',
					'meta_input'   => array(
						'_ajem_job_id'        => (int) $job->id,
						'_ajem_job_slug'      => $job->job_slug,
						'_ajem_job_status'    => $job->status,
						'_ajem_job_city'      => $job->city,
						'_ajem_employer_id'   => (int) $job->employer_id,
					),
				),
				true
			);

			if ( $post_id && ! is_wp_error( $post_id ) ) {
				$wpdb->update(
					$table,
					array( 'wp_post_id' => $post_id ),
					array( 'id' => (int) $job->id ),
					array( '%d' ),
					array( '%d' )
				);
			}
		}
	}

	/**
	 * Run deactivation routines.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();

		// Clear scheduled cron events.
		$timestamp = wp_next_scheduled( 'ajem_expire_jobs' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'ajem_expire_jobs' );
		}
	}

	/**
	 * Create all required database tables.
	 *
	 * @return void
	 */
	private static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// 1. wp_job_employers.
		$table_employers = $wpdb->prefix . 'job_employers';
		$sql_employers   = "CREATE TABLE {$table_employers} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			company_name VARCHAR(255) DEFAULT '' NOT NULL,
			company_logo VARCHAR(500) DEFAULT '',
			company_website VARCHAR(500) DEFAULT '',
			company_description TEXT,
			industry VARCHAR(255) DEFAULT '',
			company_size VARCHAR(50) DEFAULT '',
			founded_year YEAR DEFAULT NULL,
			contact_email VARCHAR(255) DEFAULT '',
			contact_phone VARCHAR(20) DEFAULT '',
			whatsapp_number VARCHAR(20) DEFAULT '',
			latitude DECIMAL(10,8) DEFAULT NULL,
			longitude DECIMAL(11,8) DEFAULT NULL,
			country VARCHAR(100) DEFAULT '',
			state VARCHAR(100) DEFAULT '',
			district VARCHAR(100) DEFAULT '',
			city VARCHAR(100) DEFAULT '',
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY user_id (user_id)
		) {$charset_collate};";

		// 2. wp_job_listings.
		$table_listings = $wpdb->prefix . 'job_listings';
		$sql_listings   = "CREATE TABLE {$table_listings} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			employer_id BIGINT UNSIGNED NOT NULL,
			wp_post_id BIGINT UNSIGNED DEFAULT NULL,
			job_title VARCHAR(255) DEFAULT '' NOT NULL,
			job_slug VARCHAR(255) DEFAULT '' NOT NULL,
			job_description LONGTEXT,
			job_type ENUM('full_time','part_time','internship','contract','remote') DEFAULT 'full_time',
			industry VARCHAR(255) DEFAULT '',
			experience_required VARCHAR(100) DEFAULT '',
			education_required VARCHAR(255) DEFAULT '',
			salary_min DECIMAL(10,2) DEFAULT NULL,
			salary_max DECIMAL(10,2) DEFAULT NULL,
			salary_currency VARCHAR(10) DEFAULT 'INR',
			required_skills TEXT,
			latitude DECIMAL(10,8) DEFAULT NULL,
			longitude DECIMAL(11,8) DEFAULT NULL,
			country VARCHAR(100) DEFAULT '',
			state VARCHAR(100) DEFAULT '',
			district VARCHAR(100) DEFAULT '',
			city VARCHAR(100) DEFAULT '',
			application_deadline DATE DEFAULT NULL,
			status ENUM('active','paused','expired','closed','draft') DEFAULT 'draft',
			views_count INT DEFAULT 0,
			applications_count INT DEFAULT 0,
			is_featured TINYINT(1) DEFAULT 0,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY job_slug (job_slug),
			KEY employer_id (employer_id),
			KEY wp_post_id (wp_post_id),
			KEY status (status),
			KEY job_type (job_type),
			KEY industry (industry),
			KEY city (city),
			FULLTEXT KEY search_idx (job_title, job_description)
		) {$charset_collate};";

		// 3. wp_job_locations.
		$table_locations = $wpdb->prefix . 'job_locations';
		$sql_locations   = "CREATE TABLE {$table_locations} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			country VARCHAR(100) DEFAULT '' NOT NULL,
			state VARCHAR(100) DEFAULT '' NOT NULL,
			district VARCHAR(100) DEFAULT '',
			city VARCHAR(100) DEFAULT '' NOT NULL,
			latitude DECIMAL(10,8) DEFAULT NULL,
			longitude DECIMAL(11,8) DEFAULT NULL,
			parent_id BIGINT UNSIGNED DEFAULT NULL,
			location_type ENUM('country','state','district','city') DEFAULT 'city',
			is_active TINYINT(1) DEFAULT 1,
			PRIMARY KEY (id),
			KEY country (country),
			KEY state (state),
			KEY city (city),
			UNIQUE KEY unique_location (country(50), state(50), district(50), city(50))
		) {$charset_collate};";

		// 4. wp_job_applications (shared table — candidate plugin reads from here).
		$table_applications = $wpdb->prefix . 'job_applications';
		$sql_applications   = "CREATE TABLE {$table_applications} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			job_id BIGINT UNSIGNED NOT NULL,
			candidate_id BIGINT UNSIGNED NOT NULL,
			employer_id BIGINT UNSIGNED NOT NULL,
			resume_id BIGINT UNSIGNED DEFAULT NULL,
			cover_letter TEXT,
			status ENUM('applied','viewed','shortlisted','rejected','interview_scheduled','hired') DEFAULT 'applied',
			employer_notes TEXT,
			applied_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY job_id (job_id),
			KEY candidate_id (candidate_id),
			KEY employer_id (employer_id),
			UNIQUE KEY unique_application (job_id, candidate_id)
		) {$charset_collate};";

		// 5. wp_job_shortlisted_candidates.
		$table_shortlisted = $wpdb->prefix . 'job_shortlisted_candidates';
		$sql_shortlisted   = "CREATE TABLE {$table_shortlisted} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			employer_id BIGINT UNSIGNED NOT NULL,
			candidate_id BIGINT UNSIGNED NOT NULL,
			job_id BIGINT UNSIGNED DEFAULT NULL,
			notes TEXT,
			shortlisted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY employer_id (employer_id),
			KEY candidate_id (candidate_id),
			UNIQUE KEY unique_shortlist (employer_id, candidate_id, job_id)
		) {$charset_collate};";

		dbDelta( $sql_employers );
		dbDelta( $sql_listings );
		dbDelta( $sql_locations );
		dbDelta( $sql_applications );
		dbDelta( $sql_shortlisted );
	}

	/**
	 * Create the 'employer' user role with appropriate capabilities.
	 *
	 * @return void
	 */
	private static function create_employer_role(): void {
		add_role(
			'employer',
			__( 'Employer', 'ai-job-employer-manager' ),
			array(
				'read'                   => true,
				'ajem_manage_jobs'       => true,
				'ajem_manage_profile'    => true,
				'ajem_view_applications' => true,
				'ajem_shortlist'         => true,
				'ajem_view_candidates'   => true,
			)
		);
	}
}
