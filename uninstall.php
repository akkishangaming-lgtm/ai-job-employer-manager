<?php
/**
 * Uninstall handler for AI Job Employer Manager.
 *
 * Fired when the plugin is uninstalled via the WordPress admin.
 * Removes all plugin data: database tables, options, roles, transients.
 *
 * @package AI_Job_Employer_Manager
 * @since   1.0.0
 */

// Prevent direct file access — must be called by WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Remove plugin tables.
$tables = array(
	$wpdb->prefix . 'job_employers',
	$wpdb->prefix . 'job_listings',
	$wpdb->prefix . 'job_locations',
	$wpdb->prefix . 'job_applications',
	$wpdb->prefix . 'job_shortlisted_candidates',
);

foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot be parameterised.
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
}

// Remove plugin options.
$options = array(
	'ajem_db_version',
	'ajem_max_jobs_per_employer',
	'ajem_default_expiry_days',
	'ajem_featured_job_settings',
	'ajem_settings',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// Remove transients.
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '_transient_ajem_%'
	    OR option_name LIKE '_transient_timeout_ajem_%'"
);

// Remove employer user role.
remove_role( 'employer' );

// Clear rewrite rules.
flush_rewrite_rules();
