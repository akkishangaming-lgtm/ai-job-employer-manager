<?php
/**
 * Plugin Name: AI Job Employer Manager
 * Plugin URI:  https://github.com/akkishangaming-lgtm/ai-job-employer-manager
 * Description: Manages employers, companies, job postings, job applications, and job visibility for a job portal. Integrates with AI Job Candidate Manager plugin.
 * Version:     1.0.0
 * Author:      AI Job Portal
 * Text Domain: ai-job-employer-manager
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 *
 * @package AI_Job_Employer_Manager
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'AJEM_VERSION', '1.0.0' );
define( 'AJEM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AJEM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AJEM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'AJEM_DB_VERSION', '1.0.0' );

/**
 * Main plugin class — singleton.
 *
 * @since 1.0.0
 */
final class AI_Job_Employer_Manager {

	/**
	 * Singleton instance.
	 *
	 * @var AI_Job_Employer_Manager|null
	 */
	private static ?AI_Job_Employer_Manager $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return AI_Job_Employer_Manager
	 */
	public static function get_instance(): AI_Job_Employer_Manager {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — private, use get_instance().
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->register_hooks();
	}

	/**
	 * Load all plugin dependencies.
	 *
	 * @return void
	 */
	private function load_dependencies(): void {
		require_once AJEM_PLUGIN_DIR . 'includes/class-activator.php';
		require_once AJEM_PLUGIN_DIR . 'includes/class-employer-profile.php';
		require_once AJEM_PLUGIN_DIR . 'includes/class-job-posting.php';
		require_once AJEM_PLUGIN_DIR . 'includes/class-job-listings.php';
		require_once AJEM_PLUGIN_DIR . 'includes/class-job-applications.php';
		require_once AJEM_PLUGIN_DIR . 'includes/class-candidate-search.php';
		require_once AJEM_PLUGIN_DIR . 'includes/class-location-manager.php';
		require_once AJEM_PLUGIN_DIR . 'includes/class-job-analytics.php';
		require_once AJEM_PLUGIN_DIR . 'includes/class-job-seo.php';
		require_once AJEM_PLUGIN_DIR . 'includes/class-rest-api.php';
		require_once AJEM_PLUGIN_DIR . 'admin/class-admin-settings.php';
		require_once AJEM_PLUGIN_DIR . 'public/class-public-dashboard.php';
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		// Activation / deactivation hooks.
		register_activation_hook( __FILE__, array( 'AJEM_Activator', 'activate' ) );
		register_deactivation_hook( __FILE__, array( 'AJEM_Activator', 'deactivate' ) );

		// Init hook.
		add_action( 'init', array( $this, 'init' ) );

		// REST API.
		add_action( 'rest_api_init', array( 'AJEM_REST_API', 'register_routes' ) );

		// Admin.
		if ( is_admin() ) {
			$admin = new AJEM_Admin_Settings();
			$admin->init();
		}

		// Frontend.
		$public = new AJEM_Public_Dashboard();
		$public->init();

		// SEO.
		$seo = new AJEM_Job_SEO();
		$seo->init();

		// Analytics.
		$analytics = new AJEM_Job_Analytics();
		$analytics->init();

		// Job posting (cron etc.).
		$job_posting = new AJEM_Job_Posting();
		$job_posting->init();

		// Enqueue assets.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Plugin init — rewrite rules, text domain.
	 *
	 * @return void
	 */
	public function init(): void {
		load_plugin_textdomain( 'ai-job-employer-manager', false, dirname( AJEM_PLUGIN_BASENAME ) . '/languages' );
		$this->register_rewrite_rules();
	}

	/**
	 * Register custom rewrite rules for SEO-friendly job URLs.
	 *
	 * @return void
	 */
	private function register_rewrite_rules(): void {
		add_rewrite_rule(
			'^jobs/([^/]+)/?$',
			'index.php?ajem_job_slug=$matches[1]',
			'top'
		);
		add_rewrite_tag( '%ajem_job_slug%', '([^/]+)' );

		// Job sitemap.
		add_rewrite_rule(
			'^job-sitemap\.xml$',
			'index.php?ajem_sitemap=1',
			'top'
		);
		add_rewrite_tag( '%ajem_sitemap%', '([^/]+)' );
	}

	/**
	 * Enqueue frontend scripts and styles.
	 *
	 * @return void
	 */
	public function enqueue_frontend_assets(): void {
		global $post;

		$is_dashboard_page = false;
		$is_job_detail     = false;
		$is_job_listing    = false;

		// Check if current page has our shortcodes.
		if ( $post instanceof WP_Post ) {
			if ( has_shortcode( $post->post_content, 'employer_dashboard' ) ) {
				$is_dashboard_page = true;
			}
			if ( has_shortcode( $post->post_content, 'job_listings' ) ) {
				$is_job_listing = true;
			}
			if ( has_shortcode( $post->post_content, 'job_detail' ) ) {
				$is_job_detail = true;
			}
		}

		// Check for job slug rewrite.
		if ( get_query_var( 'ajem_job_slug' ) ) {
			$is_job_detail = true;
		}

		if ( $is_dashboard_page || $is_job_detail || $is_job_listing ) {
			wp_enqueue_style(
				'ajem-employer-dashboard',
				AJEM_PLUGIN_URL . 'assets/css/employer-dashboard.css',
				array(),
				AJEM_VERSION
			);

			wp_enqueue_script(
				'ajem-employer-dashboard',
				AJEM_PLUGIN_URL . 'assets/js/employer-dashboard.js',
				array( 'jquery' ),
				AJEM_VERSION,
				true
			);

			wp_localize_script(
				'ajem-employer-dashboard',
				'ajemData',
				array(
					'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
					'restUrl'        => esc_url_raw( rest_url( 'ajem/v1/' ) ),
					'nonce'          => wp_create_nonce( 'ajem_nonce' ),
					'restNonce'      => wp_create_nonce( 'wp_rest' ),
					'currentUserId'  => get_current_user_id(),
					'isLoggedIn'     => is_user_logged_in(),
					'loginUrl'       => wp_login_url(),
					'siteUrl'        => site_url(),
					'pluginUrl'      => AJEM_PLUGIN_URL,
					'i18n'           => array(
						'saving'          => __( 'Saving...', 'ai-job-employer-manager' ),
						'saved'           => __( 'Saved!', 'ai-job-employer-manager' ),
						'error'           => __( 'An error occurred. Please try again.', 'ai-job-employer-manager' ),
						'confirmDelete'   => __( 'Are you sure you want to delete this?', 'ai-job-employer-manager' ),
						'locationDetect'  => __( 'Detecting location...', 'ai-job-employer-manager' ),
						'locationError'   => __( 'Could not detect location. Please enter manually.', 'ai-job-employer-manager' ),
					),
				)
			);
		}
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		if ( strpos( $hook, 'ajem' ) === false && strpos( $hook, 'ai-job-employer' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'ajem-admin',
			AJEM_PLUGIN_URL . 'assets/css/employer-dashboard.css',
			array(),
			AJEM_VERSION
		);

		wp_enqueue_script(
			'ajem-admin',
			AJEM_PLUGIN_URL . 'assets/js/employer-dashboard.js',
			array( 'jquery' ),
			AJEM_VERSION,
			true
		);

		wp_localize_script(
			'ajem-admin',
			'ajemData',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'restUrl'   => esc_url_raw( rest_url( 'ajem/v1/' ) ),
				'nonce'     => wp_create_nonce( 'ajem_nonce' ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
				'siteUrl'   => site_url(),
				'pluginUrl' => AJEM_PLUGIN_URL,
			)
		);
	}
}

// Boot the plugin.
AI_Job_Employer_Manager::get_instance();
