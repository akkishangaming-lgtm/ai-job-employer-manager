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
define( 'AJEM_DB_VERSION', '1.1.0' );

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

		// Register Custom Post Type for WP admin visibility.
		add_action( 'init', array( $this, 'register_cpt' ) );

		// Run DB upgrade / WP-post sync when DB version changes.
		add_action( 'admin_init', array( $this, 'maybe_upgrade_db' ) );

		// CPT list-table customisations.
		add_filter( 'manage_ajem_job_posts_columns', array( $this, 'cpt_columns' ) );
		add_action( 'manage_ajem_job_posts_custom_column', array( $this, 'cpt_column_content' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'cpt_row_actions' ), 10, 2 );

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
	 * Register the `ajem_job` Custom Post Type for WP admin visibility.
	 *
	 * The CPT is not publicly queryable — public job URLs are handled by the
	 * plugin's own rewrite rules. It is only used so that administrators can
	 * see, search, and manage jobs from the WordPress admin dashboard.
	 *
	 * @return void
	 */
	public function register_cpt(): void {
		$labels = array(
			'name'               => _x( 'Jobs', 'post type general name', 'ai-job-employer-manager' ),
			'singular_name'      => _x( 'Job', 'post type singular name', 'ai-job-employer-manager' ),
			'menu_name'          => __( 'All Jobs', 'ai-job-employer-manager' ),
			'all_items'          => __( 'All Jobs', 'ai-job-employer-manager' ),
			'add_new'            => __( 'Add New', 'ai-job-employer-manager' ),
			'add_new_item'       => __( 'Add New Job', 'ai-job-employer-manager' ),
			'edit_item'          => __( 'Edit Job', 'ai-job-employer-manager' ),
			'new_item'           => __( 'New Job', 'ai-job-employer-manager' ),
			'view_item'          => __( 'View Job', 'ai-job-employer-manager' ),
			'search_items'       => __( 'Search Jobs', 'ai-job-employer-manager' ),
			'not_found'          => __( 'No jobs found.', 'ai-job-employer-manager' ),
			'not_found_in_trash' => __( 'No jobs found in Trash.', 'ai-job-employer-manager' ),
		);

		register_post_type(
			'ajem_job',
			array(
				'labels'          => $labels,
				'description'     => __( 'Job listings managed by AI Job Employer Manager.', 'ai-job-employer-manager' ),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'ajem-dashboard',
				'show_in_rest'    => false,
				'has_archive'     => false,
				'rewrite'         => false,
				'supports'        => array( 'title', 'editor', 'custom-fields' ),
				'map_meta_cap'    => true,
				'capability_type' => 'post',
			)
		);
	}

	/**
	 * Run a DB / WP-post upgrade when the stored DB version is outdated.
	 *
	 * @return void
	 */
	public function maybe_upgrade_db(): void {
		if ( get_option( 'ajem_db_version' ) !== AJEM_DB_VERSION ) {
			AJEM_Activator::upgrade();
			update_option( 'ajem_db_version', AJEM_DB_VERSION );
			flush_rewrite_rules();
		}
	}

	/**
	 * Define custom columns for the `ajem_job` CPT list table.
	 *
	 * @param array $columns Default columns.
	 * @return array
	 */
	public function cpt_columns( array $columns ): array {
		// Keep cb + title, then inject ours, then keep date.
		$new = array();
		foreach ( array( 'cb', 'title' ) as $key ) {
			if ( isset( $columns[ $key ] ) ) {
				$new[ $key ] = $columns[ $key ];
			}
		}
		$new['ajem_status']     = __( 'Status', 'ai-job-employer-manager' );
		$new['ajem_city']       = __( 'City', 'ai-job-employer-manager' );
		$new['ajem_public_url'] = __( 'Public URL', 'ai-job-employer-manager' );
		if ( isset( $columns['date'] ) ) {
			$new['date'] = $columns['date'];
		}
		return $new;
	}

	/**
	 * Render custom column content for the `ajem_job` CPT list table.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id WP post ID.
	 * @return void
	 */
	public function cpt_column_content( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'ajem_status':
				$status = get_post_meta( $post_id, '_ajem_job_status', true );
				echo esc_html( $status ?: '—' );
				break;

			case 'ajem_city':
				$city = get_post_meta( $post_id, '_ajem_job_city', true );
				echo esc_html( $city ?: '—' );
				break;

			case 'ajem_public_url':
				$slug = get_post_meta( $post_id, '_ajem_job_slug', true );
				if ( $slug ) {
					$url = esc_url( site_url( '/jobs/' . $slug ) );
					printf( '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>', $url, esc_html( $url ) );
				} else {
					echo '—';
				}
				break;
		}
	}

	/**
	 * Add a "View Job" row action to the `ajem_job` CPT list table so admins
	 * can click straight to the public job page.
	 *
	 * @param array    $actions Existing row actions.
	 * @param \WP_Post $post    Current post object.
	 * @return array
	 */
	public function cpt_row_actions( array $actions, \WP_Post $post ): array {
		if ( 'ajem_job' !== $post->post_type ) {
			return $actions;
		}

		$slug = get_post_meta( $post->ID, '_ajem_job_slug', true );
		if ( $slug ) {
			$actions['view_public'] = sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( site_url( '/jobs/' . $slug ) ),
				esc_html__( 'View Public Page', 'ai-job-employer-manager' )
			);
		}

		return $actions;
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
		global $post, $ajem_current_job;

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

		// Check for job slug rewrite (also set when template_include selects our template).
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

			$localize_data = array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'restUrl'       => esc_url_raw( rest_url( 'ajem/v1/' ) ),
				'nonce'         => wp_create_nonce( 'ajem_nonce' ),
				'restNonce'     => wp_create_nonce( 'wp_rest' ),
				'currentUserId' => get_current_user_id(),
				'isLoggedIn'    => is_user_logged_in(),
				'loginUrl'      => wp_login_url(),
				'siteUrl'       => site_url(),
				'pluginUrl'     => AJEM_PLUGIN_URL,
				'i18n'          => array(
					'saving'         => __( 'Saving...', 'ai-job-employer-manager' ),
					'saved'          => __( 'Saved!', 'ai-job-employer-manager' ),
					'error'          => __( 'An error occurred. Please try again.', 'ai-job-employer-manager' ),
					'confirmDelete'  => __( 'Are you sure you want to delete this?', 'ai-job-employer-manager' ),
					'locationDetect' => __( 'Detecting location...', 'ai-job-employer-manager' ),
					'locationError'  => __( 'Could not detect location. Please enter manually.', 'ai-job-employer-manager' ),
				),
			);

			// Add job-specific data when on a single job page.
			if ( $is_job_detail && isset( $ajem_current_job->id ) ) {
				$localize_data['currentJobId']   = (int) $ajem_current_job->id;
				$localize_data['currentJobSlug'] = esc_js( $ajem_current_job->job_slug );
				$localize_data['loginUrl']        = wp_login_url( site_url( '/jobs/' . $ajem_current_job->job_slug ) );
			}

			wp_localize_script( 'ajem-employer-dashboard', 'ajemData', $localize_data );
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
