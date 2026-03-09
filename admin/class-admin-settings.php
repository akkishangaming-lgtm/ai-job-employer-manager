<?php
/**
 * Admin Settings page for AI Job Employer Manager.
 *
 * @package AI_Job_Employer_Manager
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AJEM_Admin_Settings
 *
 * Registers the WordPress admin menu page and handles the settings form,
 * plugin statistics, and CSV location import/export.
 *
 * @since 1.0.0
 */
class AJEM_Admin_Settings {

	/**
	 * Option group name.
	 */
	private const OPTION_GROUP = 'ajem_settings_group';

	/**
	 * Option name for main settings.
	 */
	private const OPTION_NAME = 'ajem_settings';

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_ajem_import_locations', array( $this, 'handle_location_import' ) );
		add_action( 'admin_post_ajem_export_locations', array( $this, 'handle_location_export' ) );
	}

	/**
	 * Register admin menu pages.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'AI Job Portal', 'ai-job-employer-manager' ),
			__( 'Job Portal', 'ai-job-employer-manager' ),
			'manage_options',
			'ajem-dashboard',
			array( $this, 'render_dashboard_page' ),
			'dashicons-businessman',
			26
		);

		add_submenu_page(
			'ajem-dashboard',
			__( 'Dashboard', 'ai-job-employer-manager' ),
			__( 'Dashboard', 'ai-job-employer-manager' ),
			'manage_options',
			'ajem-dashboard',
			array( $this, 'render_dashboard_page' )
		);

		add_submenu_page(
			'ajem-dashboard',
			__( 'Settings', 'ai-job-employer-manager' ),
			__( 'Settings', 'ai-job-employer-manager' ),
			'manage_options',
			'ajem-settings',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			'ajem-dashboard',
			__( 'Location Manager', 'ai-job-employer-manager' ),
			__( 'Location Manager', 'ai-job-employer-manager' ),
			'manage_options',
			'ajem-locations',
			array( $this, 'render_locations_page' )
		);
	}

	/**
	 * Register settings using the Settings API.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array( $this, 'sanitize_settings' )
		);

		add_settings_section(
			'ajem_general',
			__( 'General Settings', 'ai-job-employer-manager' ),
			null,
			'ajem-settings'
		);

		add_settings_field(
			'max_jobs_per_employer',
			__( 'Max Jobs Per Employer', 'ai-job-employer-manager' ),
			array( $this, 'field_max_jobs' ),
			'ajem-settings',
			'ajem_general'
		);

		add_settings_field(
			'default_expiry_days',
			__( 'Default Job Expiry (days)', 'ai-job-employer-manager' ),
			array( $this, 'field_expiry_days' ),
			'ajem-settings',
			'ajem_general'
		);

		add_settings_field(
			'jobs_per_page',
			__( 'Jobs Per Page (public)', 'ai-job-employer-manager' ),
			array( $this, 'field_jobs_per_page' ),
			'ajem-settings',
			'ajem_general'
		);
	}

	/**
	 * Sanitize settings before saving.
	 *
	 * @param array $input Raw settings input.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( array $input ): array {
		$output = array();

		$output['max_jobs_per_employer'] = isset( $input['max_jobs_per_employer'] )
			? absint( $input['max_jobs_per_employer'] ) : 50;

		$output['default_expiry_days'] = isset( $input['default_expiry_days'] )
			? absint( $input['default_expiry_days'] ) : 30;

		$output['jobs_per_page'] = isset( $input['jobs_per_page'] )
			? absint( $input['jobs_per_page'] ) : 20;

		return $output;
	}

	/**
	 * Render admin dashboard overview page.
	 *
	 * @return void
	 */
	public function render_dashboard_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'ai-job-employer-manager' ) );
		}

		$analytics = new AJEM_Job_Analytics();
		$stats     = $analytics->get_global_stats();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'AI Job Employer Manager — Dashboard', 'ai-job-employer-manager' ); ?></h1>

			<div class="ajem-admin-stats" style="display:flex;gap:20px;flex-wrap:wrap;margin:20px 0;">
				<?php
				$stat_items = array(
					array( 'label' => __( 'Total Employers', 'ai-job-employer-manager' ), 'value' => $stats['total_employers'] ),
					array( 'label' => __( 'Total Jobs', 'ai-job-employer-manager' ), 'value' => $stats['total_jobs'] ),
					array( 'label' => __( 'Active Jobs', 'ai-job-employer-manager' ), 'value' => $stats['active_jobs'] ),
					array( 'label' => __( 'Total Applications', 'ai-job-employer-manager' ), 'value' => $stats['total_applications'] ),
					array( 'label' => __( 'Locations Loaded', 'ai-job-employer-manager' ), 'value' => $stats['total_locations'] ),
				);
				foreach ( $stat_items as $item ) {
					echo '<div style="background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:20px 24px;min-width:150px;">';
					echo '<div style="font-size:28px;font-weight:700;color:#0073aa;">' . esc_html( number_format( $item['value'] ) ) . '</div>';
					echo '<div style="color:#666;font-size:13px;margin-top:4px;">' . esc_html( $item['label'] ) . '</div>';
					echo '</div>';
				}
				?>
			</div>

			<h2><?php esc_html_e( 'Quick Links', 'ai-job-employer-manager' ); ?></h2>
			<ul>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=ajem-settings' ) ); ?>"><?php esc_html_e( 'Plugin Settings', 'ai-job-employer-manager' ); ?></a></li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=ajem-locations' ) ); ?>"><?php esc_html_e( 'Location Manager', 'ai-job-employer-manager' ); ?></a></li>
				<li><a href="<?php echo esc_url( site_url( '/job-sitemap.xml' ) ); ?>" target="_blank"><?php esc_html_e( 'View Job Sitemap', 'ai-job-employer-manager' ); ?></a></li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'ai-job-employer-manager' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'AI Job Employer Manager — Settings', 'ai-job-employer-manager' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( 'ajem-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render location manager page.
	 *
	 * @return void
	 */
	public function render_locations_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'ai-job-employer-manager' ) );
		}

		global $wpdb;
		$table       = $wpdb->prefix . 'job_locations';
		$total_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );

		$import_message = '';
		if ( isset( $_GET['ajem_import'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( 'success' === $_GET['ajem_import'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$inserted = absint( $_GET['inserted'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$skipped  = absint( $_GET['skipped'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$import_message = sprintf(
					/* translators: 1: inserted count, 2: skipped count */
					__( 'Import complete: %1$d inserted, %2$d skipped.', 'ai-job-employer-manager' ),
					$inserted,
					$skipped
				);
			}
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Location Manager', 'ai-job-employer-manager' ); ?></h1>
			<p><?php echo esc_html( sprintf( /* translators: %d: count */ __( 'Total locations loaded: %d', 'ai-job-employer-manager' ), $total_count ) ); ?></p>

			<?php if ( $import_message ) : ?>
				<div class="notice notice-success"><p><?php echo esc_html( $import_message ); ?></p></div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Import Locations from CSV', 'ai-job-employer-manager' ); ?></h2>
			<p><?php esc_html_e( 'CSV must have headers: country, state, district, city, latitude, longitude', 'ai-job-employer-manager' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="ajem_import_locations">
				<?php wp_nonce_field( 'ajem_import_locations' ); ?>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'CSV File', 'ai-job-employer-manager' ); ?></th>
						<td><input type="file" name="locations_csv" accept=".csv" required></td>
					</tr>
				</table>
				<?php submit_button( __( 'Import CSV', 'ai-job-employer-manager' ) ); ?>
			</form>

			<h2><?php esc_html_e( 'Export Locations to CSV', 'ai-job-employer-manager' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ajem_export_locations">
				<?php wp_nonce_field( 'ajem_export_locations' ); ?>
				<?php submit_button( __( 'Export CSV', 'ai-job-employer-manager' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle CSV location import (admin-post.php action).
	 *
	 * @return void
	 */
	public function handle_location_import(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorised.', 'ai-job-employer-manager' ) );
		}

		check_admin_referer( 'ajem_import_locations' );

		if ( empty( $_FILES['locations_csv']['tmp_name'] ) ) {
			wp_safe_redirect( add_query_arg( array( 'ajem_import' => 'error' ), admin_url( 'admin.php?page=ajem-locations' ) ) );
			exit;
		}

		$location_manager = new AJEM_Location_Manager();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Filesystem path must not be sanitized.
		$rows             = $location_manager->parse_csv( $_FILES['locations_csv']['tmp_name'] );
		$result           = $location_manager->import_locations( $rows );

		wp_safe_redirect(
			add_query_arg(
				array(
					'ajem_import' => 'success',
					'inserted'    => $result['inserted'],
					'skipped'     => $result['skipped'],
				),
				admin_url( 'admin.php?page=ajem-locations' )
			)
		);
		exit;
	}

	/**
	 * Handle CSV location export (admin-post.php action).
	 *
	 * @return void
	 */
	public function handle_location_export(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorised.', 'ai-job-employer-manager' ) );
		}

		check_admin_referer( 'ajem_export_locations' );

		global $wpdb;
		$table = $wpdb->prefix . 'job_locations';
		$rows  = $wpdb->get_results( "SELECT country, state, district, city, latitude, longitude FROM `{$table}` ORDER BY country, state, city ASC", ARRAY_A );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="job-locations.csv"' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'country', 'state', 'district', 'city', 'latitude', 'longitude' ) );

		foreach ( $rows as $row ) {
			fputcsv( $out, $row );
		}

		fclose( $out );
		exit;
	}

	// ── Settings field renderers ──────────────────────────────────────────────

	/**
	 * Render max_jobs_per_employer field.
	 *
	 * @return void
	 */
	public function field_max_jobs(): void {
		$options = get_option( self::OPTION_NAME, array() );
		$value   = isset( $options['max_jobs_per_employer'] ) ? (int) $options['max_jobs_per_employer'] : 50;
		echo '<input type="number" name="' . esc_attr( self::OPTION_NAME ) . '[max_jobs_per_employer]" value="' . esc_attr( $value ) . '" min="0" max="9999">';
		echo '<p class="description">' . esc_html__( 'Set 0 for unlimited.', 'ai-job-employer-manager' ) . '</p>';
	}

	/**
	 * Render default_expiry_days field.
	 *
	 * @return void
	 */
	public function field_expiry_days(): void {
		$options = get_option( self::OPTION_NAME, array() );
		$value   = isset( $options['default_expiry_days'] ) ? (int) $options['default_expiry_days'] : 30;
		echo '<input type="number" name="' . esc_attr( self::OPTION_NAME ) . '[default_expiry_days]" value="' . esc_attr( $value ) . '" min="1" max="365">';
	}

	/**
	 * Render jobs_per_page field.
	 *
	 * @return void
	 */
	public function field_jobs_per_page(): void {
		$options = get_option( self::OPTION_NAME, array() );
		$value   = isset( $options['jobs_per_page'] ) ? (int) $options['jobs_per_page'] : 20;
		echo '<input type="number" name="' . esc_attr( self::OPTION_NAME ) . '[jobs_per_page]" value="' . esc_attr( $value ) . '" min="5" max="100">';
	}
}
