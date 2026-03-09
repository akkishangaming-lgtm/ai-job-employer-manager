<?php
/**
 * Job Detail Template — single job page with Apply/Call/WhatsApp buttons.
 *
 * Variables available:
 * - $job          (object)      — job listing row
 * - $employer     (object|null) — employer profile row
 * - $related_jobs (array)       — related jobs (same industry)
 * - $city_jobs    (array)       — jobs in the same city
 * - $nearby_jobs  (array)       — jobs within 50 km by Haversine
 *
 * @package AI_Job_Employer_Manager
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Helper: render a mini job card (must be defined before use) ───────────
if ( ! function_exists( 'ajem_render_mini_job_card' ) ) :
	/**
	 * Render a mini job card for related/city/nearby sections.
	 *
	 * @param object $j Job row (company_name, company_logo, job_slug, job_title,
	 *                           city, state, salary_min, salary_max,
	 *                           salary_currency, job_type).
	 * @return string Escaped HTML.
	 */
	function ajem_render_mini_job_card( object $j ): string {
		$type   = str_replace( '_', ' ', ucwords( str_replace( '_', ' ', $j->job_type ?? '' ) ) );
		$s_min  = $j->salary_min ? number_format( (float) $j->salary_min ) : '';
		$s_max  = $j->salary_max ? number_format( (float) $j->salary_max ) : '';
		$s_curr = esc_html( $j->salary_currency ?? 'INR' );
		$sal    = ( $s_min || $s_max )
			? $s_curr . ' ' . $s_min . ( $s_min && $s_max ? ' – ' . $s_max : $s_max )
			: '';
		$url    = esc_url( site_url( '/jobs/' . $j->job_slug ) );

		ob_start();
		?>
		<div class="ajem-mini-job-card">
			<a href="<?php echo $url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — already escaped above ?>" class="ajem-mini-job-link">
				<div class="ajem-mini-job-top">
					<?php if ( ! empty( $j->company_logo ) ) : ?>
						<img src="<?php echo esc_url( $j->company_logo ); ?>"
							alt="<?php echo esc_attr( $j->company_name ?? '' ); ?>"
							class="ajem-mini-job-logo">
					<?php else : ?>
						<div class="ajem-mini-job-logo-placeholder">
							<?php echo esc_html( mb_strtoupper( mb_substr( $j->company_name ?? 'J', 0, 1 ) ) ); ?>
						</div>
					<?php endif; ?>
					<div class="ajem-mini-job-info">
						<div class="ajem-mini-job-title"><?php echo esc_html( $j->job_title ); ?></div>
						<div class="ajem-mini-job-company"><?php echo esc_html( $j->company_name ?? '' ); ?></div>
					</div>
				</div>
				<div class="ajem-mini-job-meta">
					<?php if ( $j->city ) : ?>
						<span class="ajem-mini-chip">📍 <?php echo esc_html( $j->city ); ?></span>
					<?php endif; ?>
					<?php if ( $type ) : ?>
						<span class="ajem-badge ajem-badge-type ajem-badge-sm"><?php echo esc_html( $type ); ?></span>
					<?php endif; ?>
					<?php if ( $sal ) : ?>
						<span class="ajem-mini-chip ajem-mini-salary">💰 <?php echo esc_html( $sal ); ?></span>
					<?php endif; ?>
				</div>
			</a>
		</div>
		<?php
		return ob_get_clean();
	}
endif; // function_exists check.

$apply_param  = sanitize_text_field( wp_unslash( $_GET['apply'] ?? '' ) );
$auto_open    = '1' === $apply_param;

$job_url      = esc_url( site_url( '/jobs/' . $job->job_slug ) );
$login_url    = esc_url( wp_login_url( $job_url . '?apply=1' ) );

$skills       = $job->required_skills_array ?? array();

// Format WhatsApp message.
$wa_message   = rawurlencode(
	sprintf(
		/* translators: %s: job title */
		__( 'Hi, I am interested in the %s position at your company.', 'ai-job-employer-manager' ),
		$job->job_title
	)
);
$wa_number    = $employer && $employer->whatsapp_number ? $employer->whatsapp_number : '';
$phone_number = $employer && $employer->contact_phone ? $employer->contact_phone : '';

// Salary string helper.
$salary_str = '';
if ( $job->salary_min || $job->salary_max ) {
	$currency   = $job->salary_currency ?? 'INR';
	$sal_min    = $job->salary_min ? number_format( (float) $job->salary_min ) : '';
	$sal_max    = $job->salary_max ? number_format( (float) $job->salary_max ) : '';
	$salary_str = $currency . ' ' . $sal_min . ( $sal_min && $sal_max ? ' – ' . $sal_max : $sal_max ) . ' / mo';
}

// Job type label.
$type_label = esc_html( str_replace( '_', ' ', ucwords( str_replace( '_', ' ', $job->job_type ?? '' ) ) ) );

// Posted date.
$posted_diff = human_time_diff( strtotime( $job->created_at ), time() );
$posted_date = date_i18n( get_option( 'date_format' ), strtotime( $job->created_at ) );

// Company initial fallback.
$company_initial = mb_strtoupper( mb_substr( $employer ? $employer->company_name : 'J', 0, 1 ) );
$share_text     = rawurlencode( $job->job_title . ( $employer ? ' at ' . $employer->company_name : '' ) );
$linkedin_share = 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode( $job_url );
$twitter_share  = 'https://twitter.com/intent/tweet?text=' . $share_text . '&url=' . rawurlencode( $job_url );

// Deadline status.
$days_until_deadline = null;
if ( $job->application_deadline ) {
	$days_until_deadline = (int) ceil(
		( strtotime( $job->application_deadline ) - time() ) / DAY_IN_SECONDS
	);
}
?>

<div class="ajem-job-detail-page">

	<!-- ── Status / Deadline Alert ───────────────────────────────────────── -->
	<?php if ( in_array( $job->status, array( 'closed', 'expired' ), true ) ) : ?>
		<div class="ajem-job-notice ajem-job-notice-error" role="alert">
			<span>🚫</span>
			<span><?php esc_html_e( 'This job is no longer accepting applications.', 'ai-job-employer-manager' ); ?></span>
		</div>
	<?php elseif ( null !== $days_until_deadline && $days_until_deadline <= 7 && $days_until_deadline >= 0 ) : ?>
		<div class="ajem-job-notice ajem-job-notice-warning" role="alert">
			<span>⏰</span>
			<span>
				<?php
				if ( 0 === $days_until_deadline ) {
					esc_html_e( 'Last day to apply!', 'ai-job-employer-manager' );
				} else {
					printf(
						/* translators: %d: number of days remaining */
						esc_html( _n( 'Only %d day left to apply!', 'Only %d days left to apply!', $days_until_deadline, 'ai-job-employer-manager' ) ),
						(int) $days_until_deadline
					);
				}
				?>
			</span>
		</div>
	<?php endif; ?>

	<!-- ── Header Card ───────────────────────────────────────────────────── -->
	<div class="ajem-jd-header-card">

		<h1 class="ajem-jd-title"><?php echo esc_html( $job->job_title ); ?></h1>

		<?php if ( $employer ) : ?>
			<div class="ajem-jd-company">
				<?php if ( $employer->company_website ) : ?>
					<a href="<?php echo esc_url( $employer->company_website ); ?>" target="_blank" rel="noopener noreferrer">
						<?php echo esc_html( $employer->company_name ); ?>
					</a>
				<?php else : ?>
					<?php echo esc_html( $employer->company_name ); ?>
				<?php endif; ?>
			</div>
		<?php endif; ?>

	</div><!-- /.ajem-jd-header-card -->

	<!-- ── Two-column Content ────────────────────────────────────────────── -->
	<div class="ajem-jd-body">

		<!-- Main Column -->
		<div class="ajem-jd-main">

			<!-- Job Description -->
			<div class="ajem-jd-card">
				<h3 class="ajem-jd-card-title"><?php esc_html_e( 'Job Description', 'ai-job-employer-manager' ); ?></h3>
				<div class="ajem-jd-desc-content">
					<?php echo wp_kses_post( $job->job_description ); ?>
				</div>
			</div>

			<!-- Job Highlights — table layout -->
			<div class="ajem-jd-card ajem-jd-highlights">
				<h3 class="ajem-jd-card-title"><?php esc_html_e( 'Job Highlights', 'ai-job-employer-manager' ); ?></h3>
				<table class="ajem-highlights-table">
					<tbody>
						<?php if ( $type_label ) : ?>
							<tr>
								<th scope="row"><span class="ajem-ht-icon">💼</span> <?php esc_html_e( 'Job Type', 'ai-job-employer-manager' ); ?></th>
								<td><?php echo esc_html( $type_label ); ?></td>
							</tr>
						<?php endif; ?>

						<?php if ( $salary_str ) : ?>
							<tr>
								<th scope="row"><span class="ajem-ht-icon">💰</span> <?php esc_html_e( 'Salary', 'ai-job-employer-manager' ); ?></th>
								<td class="ajem-ht-salary"><?php echo esc_html( $salary_str ); ?></td>
							</tr>
						<?php endif; ?>

						<?php if ( $job->experience_required ) : ?>
							<tr>
								<th scope="row"><span class="ajem-ht-icon">📈</span> <?php esc_html_e( 'Experience', 'ai-job-employer-manager' ); ?></th>
								<td><?php echo esc_html( $job->experience_required ); ?></td>
							</tr>
						<?php endif; ?>

						<?php if ( $job->education_required ) : ?>
							<tr>
								<th scope="row"><span class="ajem-ht-icon">🎓</span> <?php esc_html_e( 'Education', 'ai-job-employer-manager' ); ?></th>
								<td><?php echo esc_html( $job->education_required ); ?></td>
							</tr>
						<?php endif; ?>

						<?php if ( $job->application_deadline ) : ?>
							<tr>
								<th scope="row"><span class="ajem-ht-icon">📅</span> <?php esc_html_e( 'Apply By', 'ai-job-employer-manager' ); ?></th>
								<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $job->application_deadline ) ) ); ?></td>
							</tr>
						<?php endif; ?>

						<?php if ( $job->city ) : ?>
							<tr>
								<th scope="row"><span class="ajem-ht-icon">📍</span> <?php esc_html_e( 'Location', 'ai-job-employer-manager' ); ?></th>
								<td><?php echo esc_html( $job->city ); ?><?php echo $job->state ? ', ' . esc_html( $job->state ) : ''; ?></td>
							</tr>
						<?php endif; ?>

						<?php if ( $job->industry ) : ?>
							<tr>
								<th scope="row"><span class="ajem-ht-icon">🏭</span> <?php esc_html_e( 'Industry', 'ai-job-employer-manager' ); ?></th>
								<td><?php echo esc_html( $job->industry ); ?></td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div><!-- /.ajem-jd-highlights -->

			<!-- Skills -->
			<?php if ( ! empty( $skills ) ) : ?>
				<div class="ajem-jd-card">
					<h3 class="ajem-jd-card-title"><?php esc_html_e( 'Required Skills', 'ai-job-employer-manager' ); ?></h3>
					<div class="ajem-skills-list">
						<?php foreach ( $skills as $skill ) : ?>
							<span class="ajem-skill-tag"><?php echo esc_html( $skill ); ?></span>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

		</div><!-- /.ajem-jd-main -->

		<!-- Sidebar Column -->
		<aside class="ajem-jd-sidebar">

			<!-- Company Info -->
			<?php if ( $employer ) : ?>
				<div class="ajem-sidebar-card ajem-company-card">
					<h4 class="ajem-sidebar-card-heading"><?php esc_html_e( 'About the Company', 'ai-job-employer-manager' ); ?></h4>
					<div class="ajem-company-card-inner">
						<?php if ( $employer->company_logo ) : ?>
							<img src="<?php echo esc_url( $employer->company_logo ); ?>"
								alt="<?php echo esc_attr( $employer->company_name ); ?>"
								class="ajem-company-card-logo">
						<?php else : ?>
							<div class="ajem-company-card-initial"><?php echo esc_html( $company_initial ); ?></div>
						<?php endif; ?>
						<div class="ajem-company-card-details">
							<div class="ajem-company-card-name">
								<?php if ( $employer->company_website ) : ?>
									<a href="<?php echo esc_url( $employer->company_website ); ?>" target="_blank" rel="noopener noreferrer">
										<?php echo esc_html( $employer->company_name ); ?>
									</a>
								<?php else : ?>
									<?php echo esc_html( $employer->company_name ); ?>
								<?php endif; ?>
							</div>
							<?php if ( $employer->industry ) : ?>
								<div class="ajem-company-card-meta">🏭 <?php echo esc_html( $employer->industry ); ?></div>
							<?php endif; ?>
						</div>
					</div>

					<?php if ( $employer->company_size || $employer->founded_year || $employer->city || $employer->company_website ) : ?>
						<table class="ajem-company-info-table">
							<tbody>
								<?php if ( $employer->company_size ) : ?>
									<tr>
										<th scope="row">👥 <?php esc_html_e( 'Size', 'ai-job-employer-manager' ); ?></th>
										<td><?php echo esc_html( $employer->company_size ); ?> <?php esc_html_e( 'employees', 'ai-job-employer-manager' ); ?></td>
									</tr>
								<?php endif; ?>
								<?php if ( $employer->founded_year ) : ?>
									<tr>
										<th scope="row">🗓 <?php esc_html_e( 'Founded', 'ai-job-employer-manager' ); ?></th>
										<td><?php echo esc_html( $employer->founded_year ); ?></td>
									</tr>
								<?php endif; ?>
								<?php if ( $employer->city ) : ?>
									<tr>
										<th scope="row">📍 <?php esc_html_e( 'Location', 'ai-job-employer-manager' ); ?></th>
										<td><?php echo esc_html( $employer->city ); ?><?php echo $employer->state ? ', ' . esc_html( $employer->state ) : ''; ?></td>
									</tr>
								<?php endif; ?>
								<?php if ( $employer->company_website ) : ?>
									<tr>
										<th scope="row">🌐 <?php esc_html_e( 'Website', 'ai-job-employer-manager' ); ?></th>
										<td><a href="<?php echo esc_url( $employer->company_website ); ?>" target="_blank" rel="noopener noreferrer" class="ajem-company-link"><?php echo esc_html( preg_replace( '#^https?://#', '', $employer->company_website ) ); ?></a></td>
									</tr>
								<?php endif; ?>
							</tbody>
						</table>
					<?php endif; ?>

					<?php if ( $employer->company_description ) : ?>
						<div class="ajem-company-desc"><?php echo wp_kses_post( $employer->company_description ); ?></div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

		</aside><!-- /.ajem-jd-sidebar -->

	</div><!-- /.ajem-jd-body -->

	<!-- ── Related / City / Nearby Jobs ─────────────────────────────────── -->
	<?php
	$has_related = ! empty( $related_jobs );
	$has_city    = ! empty( $city_jobs );
	$has_nearby  = ! empty( $nearby_jobs );

	if ( $has_related || $has_city || $has_nearby ) :
	?>
	<div class="ajem-jd-more-jobs">

		<?php if ( $has_related ) : ?>
			<div class="ajem-more-jobs-section">
				<h3 class="ajem-more-jobs-heading">
					🏭 <?php
						printf(
							/* translators: %s: industry name */
							esc_html__( 'More %s Jobs', 'ai-job-employer-manager' ),
							esc_html( $job->industry ?? __( 'Similar', 'ai-job-employer-manager' ) )
						);
					?>
				</h3>
				<div class="ajem-more-jobs-grid">
					<?php foreach ( $related_jobs as $rjob ) : ?>
						<?php echo ajem_render_mini_job_card( $rjob ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( $has_city ) : ?>
			<div class="ajem-more-jobs-section">
				<h3 class="ajem-more-jobs-heading">
					📍 <?php
						printf(
							/* translators: %s: city name */
							esc_html__( 'More Jobs in %s', 'ai-job-employer-manager' ),
							esc_html( $job->city ?? '' )
						);
					?>
				</h3>
				<div class="ajem-more-jobs-grid">
					<?php foreach ( $city_jobs as $cjob ) : ?>
						<?php echo ajem_render_mini_job_card( $cjob ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( $has_nearby ) : ?>
			<div class="ajem-more-jobs-section">
				<h3 class="ajem-more-jobs-heading">
					🗺 <?php esc_html_e( 'Nearby Jobs', 'ai-job-employer-manager' ); ?>
				</h3>
				<div class="ajem-more-jobs-grid">
					<?php foreach ( $nearby_jobs as $njob ) : ?>
						<?php echo ajem_render_mini_job_card( $njob ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

	</div><!-- /.ajem-jd-more-jobs -->
	<?php endif; ?>

</div><!-- /.ajem-job-detail-page -->

<!-- ── Application Modal ─────────────────────────────────────────────────── -->
<div class="ajem-modal" id="ajemApplicationModal" style="display:none;" aria-modal="true" role="dialog">
	<div class="ajem-modal-overlay" id="ajemModalOverlay"></div>
	<div class="ajem-modal-content">
		<button class="ajem-modal-close" id="ajemModalClose"
			aria-label="<?php esc_attr_e( 'Close', 'ai-job-employer-manager' ); ?>">×</button>
		<h3>
			<?php esc_html_e( 'Apply for:', 'ai-job-employer-manager' ); ?>
			<span class="ajem-modal-job-title"><?php echo esc_html( $job->job_title ); ?></span>
		</h3>

		<form id="ajemApplicationForm">
			<?php wp_nonce_field( 'ajem_nonce', 'ajem_nonce' ); ?>
			<input type="hidden" name="job_id" value="<?php echo esc_attr( $job->id ); ?>">

			<div class="ajem-form-group">
				<label for="ajemCoverLetter"><?php esc_html_e( 'Cover Letter', 'ai-job-employer-manager' ); ?></label>
				<textarea id="ajemCoverLetter" name="cover_letter" rows="6"
					placeholder="<?php esc_attr_e( 'Tell the employer why you are a good fit...', 'ai-job-employer-manager' ); ?>"></textarea>
			</div>

			<div class="ajem-form-group">
				<label for="ajemResumeSelect"><?php esc_html_e( 'Select Resume (optional)', 'ai-job-employer-manager' ); ?></label>
				<select id="ajemResumeSelect" name="resume_id">
					<option value=""><?php esc_html_e( '— Use default resume —', 'ai-job-employer-manager' ); ?></option>
				</select>
			</div>

			<div class="ajem-form-actions">
				<button type="submit" class="ajem-btn ajem-btn-primary" id="ajemSubmitApplication">
					<?php esc_html_e( 'Submit Application', 'ai-job-employer-manager' ); ?>
				</button>
				<span class="ajem-save-status" id="ajemApplicationStatus"></span>
			</div>
		</form>
	</div>
</div>

<?php if ( $auto_open && is_user_logged_in() ) : ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
	var modal = document.getElementById('ajemApplicationModal');
	if (modal) { modal.style.display = 'flex'; }
});
</script>
<?php endif; ?>
