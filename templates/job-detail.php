<?php
/**
 * Job Detail Template — single job page with Apply/Call/WhatsApp buttons.
 *
 * Variables available:
 * - $job          (object)      — job listing row
 * - $employer     (object|null) — employer profile row
 * - $related_jobs (array)       — related job objects
 *
 * @package AI_Job_Employer_Manager
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$apply_param  = sanitize_text_field( wp_unslash( $_GET['apply'] ?? '' ) );
$auto_open    = '1' === $apply_param;

$job_url      = esc_url( site_url( '/jobs/' . $job->job_slug ) );
$login_url    = esc_url( wp_login_url( $job_url . '?apply=1' ) );

$skills       = $job->required_skills_array ?? array();

// Format WhatsApp message.
$wa_message   = rawurlencode( sprintf(
	/* translators: %s: job title */
	__( 'Hi, I am interested in the %s position at your company.', 'ai-job-employer-manager' ),
	$job->job_title
) );
$wa_number    = $employer && $employer->whatsapp_number ? $employer->whatsapp_number : '';
$phone_number = $employer && $employer->contact_phone ? $employer->contact_phone : '';
?>

<div class="ajem-job-detail-page">
	<div class="ajem-job-detail-container">

		<!-- Main Content -->
		<article class="ajem-job-detail-main">

			<!-- Job Header -->
			<div class="ajem-job-detail-header">
				<?php if ( $employer && $employer->company_logo ) : ?>
					<img src="<?php echo esc_url( $employer->company_logo ); ?>" alt="<?php echo esc_attr( $employer->company_name ); ?>" class="ajem-job-detail-logo">
				<?php endif; ?>

				<div class="ajem-job-detail-heading">
					<h1 class="ajem-job-detail-title"><?php echo esc_html( $job->job_title ); ?></h1>
					<div class="ajem-job-detail-company"><?php echo esc_html( $employer ? $employer->company_name : '' ); ?></div>
					<div class="ajem-job-detail-meta">
						<?php if ( $job->city ) : ?>
							<span class="ajem-meta-chip">📍 <?php echo esc_html( $job->city ); ?><?php echo $job->state ? ', ' . esc_html( $job->state ) : ''; ?></span>
						<?php endif; ?>

						<span class="ajem-badge ajem-badge-type"><?php echo esc_html( str_replace( '_', ' ', ucfirst( $job->job_type ) ) ); ?></span>

						<?php if ( $job->industry ) : ?>
							<span class="ajem-meta-chip">🏭 <?php echo esc_html( $job->industry ); ?></span>
						<?php endif; ?>

						<?php if ( $job->experience_required ) : ?>
							<span class="ajem-meta-chip">💼 <?php echo esc_html( $job->experience_required ); ?></span>
						<?php endif; ?>

						<?php if ( $job->education_required ) : ?>
							<span class="ajem-meta-chip">🎓 <?php echo esc_html( $job->education_required ); ?></span>
						<?php endif; ?>

						<?php if ( $job->salary_min || $job->salary_max ) : ?>
							<span class="ajem-meta-chip ajem-salary-chip">
								💰 <?php echo esc_html( $job->salary_currency ?? 'INR' ); ?>
								<?php echo $job->salary_min ? esc_html( number_format( (float) $job->salary_min ) ) : ''; ?>
								<?php echo ( $job->salary_min && $job->salary_max ) ? ' — ' : ''; ?>
								<?php echo $job->salary_max ? esc_html( number_format( (float) $job->salary_max ) ) . ' / mo' : ''; ?>
							</span>
						<?php endif; ?>

						<?php if ( $job->application_deadline ) : ?>
							<span class="ajem-meta-chip ajem-deadline-chip">⏰ <?php echo esc_html__( 'Apply by:', 'ai-job-employer-manager' ) . ' ' . esc_html( $job->application_deadline ); ?></span>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<!-- Action Buttons — Apply / Call / WhatsApp -->
			<div class="ajem-job-action-buttons" id="ajemActionButtons">

				<!-- Apply Now -->
				<?php if ( is_user_logged_in() ) : ?>
					<button class="ajem-btn ajem-btn-apply ajem-btn-primary" id="ajemApplyBtn" data-job-id="<?php echo esc_attr( $job->id ); ?>">
						📝 <?php esc_html_e( 'Apply Now', 'ai-job-employer-manager' ); ?>
					</button>
				<?php else : ?>
					<a href="<?php echo esc_url( $login_url ); ?>" class="ajem-btn ajem-btn-apply ajem-btn-primary">
						📝 <?php esc_html_e( 'Apply Now', 'ai-job-employer-manager' ); ?>
					</a>
				<?php endif; ?>

				<!-- Call Employer -->
				<?php if ( $phone_number ) : ?>
					<a href="tel:+<?php echo esc_attr( preg_replace( '/\D/', '', $phone_number ) ); ?>" class="ajem-btn ajem-btn-call">
						📞 <?php esc_html_e( 'Call Employer', 'ai-job-employer-manager' ); ?>
					</a>
				<?php endif; ?>

				<!-- WhatsApp -->
				<?php if ( $wa_number ) : ?>
					<a href="https://wa.me/<?php echo esc_attr( $wa_number ); ?>?text=<?php echo esc_attr( $wa_message ); ?>" class="ajem-btn ajem-btn-whatsapp" target="_blank" rel="noopener noreferrer">
						💬 <?php esc_html_e( 'WhatsApp', 'ai-job-employer-manager' ); ?>
					</a>
				<?php endif; ?>
			</div>

			<!-- Skills -->
			<?php if ( ! empty( $skills ) ) : ?>
				<div class="ajem-job-skills-section">
					<h3><?php esc_html_e( 'Required Skills', 'ai-job-employer-manager' ); ?></h3>
					<div class="ajem-skills-list">
						<?php foreach ( $skills as $skill ) : ?>
							<span class="ajem-skill-tag"><?php echo esc_html( $skill ); ?></span>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<!-- Job Description -->
			<div class="ajem-job-description">
				<h3><?php esc_html_e( 'Job Description', 'ai-job-employer-manager' ); ?></h3>
				<div class="ajem-job-desc-content">
					<?php echo wp_kses_post( $job->job_description ); ?>
				</div>
			</div>

			<!-- Share -->
			<div class="ajem-share-section">
				<h4><?php esc_html_e( 'Share this job', 'ai-job-employer-manager' ); ?></h4>
				<button class="ajem-btn ajem-btn-sm" id="ajemCopyLink"><?php esc_html_e( '🔗 Copy Link', 'ai-job-employer-manager' ); ?></button>
				<?php if ( $wa_number || true ) : ?>
					<a href="https://wa.me/?text=<?php echo esc_attr( rawurlencode( $job->job_title . ' — ' . $job_url ) ); ?>" class="ajem-btn ajem-btn-sm ajem-btn-whatsapp" target="_blank" rel="noopener noreferrer"><?php esc_html_e( '📲 Share on WhatsApp', 'ai-job-employer-manager' ); ?></a>
				<?php endif; ?>
			</div>
		</article>

		<!-- Sidebar -->
		<aside class="ajem-job-detail-sidebar">

			<!-- Company Info -->
			<?php if ( $employer ) : ?>
				<div class="ajem-sidebar-card">
					<h4><?php esc_html_e( 'About the Company', 'ai-job-employer-manager' ); ?></h4>
					<?php if ( $employer->company_logo ) : ?>
						<img src="<?php echo esc_url( $employer->company_logo ); ?>" alt="<?php echo esc_attr( $employer->company_name ); ?>" class="ajem-sidebar-company-logo">
					<?php endif; ?>
					<h3><?php echo esc_html( $employer->company_name ); ?></h3>
					<?php if ( $employer->industry ) : ?>
						<p>🏭 <?php echo esc_html( $employer->industry ); ?></p>
					<?php endif; ?>
					<?php if ( $employer->company_size ) : ?>
						<p>👥 <?php echo esc_html( $employer->company_size ); ?> <?php esc_html_e( 'employees', 'ai-job-employer-manager' ); ?></p>
					<?php endif; ?>
					<?php if ( $employer->city ) : ?>
						<p>📍 <?php echo esc_html( $employer->city ); ?><?php echo $employer->state ? ', ' . esc_html( $employer->state ) : ''; ?></p>
					<?php endif; ?>
					<?php if ( $employer->company_website ) : ?>
						<p>🌐 <a href="<?php echo esc_url( $employer->company_website ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $employer->company_website ); ?></a></p>
					<?php endif; ?>
					<?php if ( $employer->company_description ) : ?>
						<div class="ajem-company-desc"><?php echo wp_kses_post( $employer->company_description ); ?></div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<!-- Quick Apply Reminder (mobile-friendly) -->
			<div class="ajem-sidebar-card ajem-apply-card">
				<h4><?php esc_html_e( 'Interested?', 'ai-job-employer-manager' ); ?></h4>
				<?php if ( is_user_logged_in() ) : ?>
					<button class="ajem-btn ajem-btn-primary ajem-btn-full" id="ajemApplyBtnSidebar" data-job-id="<?php echo esc_attr( $job->id ); ?>">
						📝 <?php esc_html_e( 'Apply Now', 'ai-job-employer-manager' ); ?>
					</button>
				<?php else : ?>
					<a href="<?php echo esc_url( $login_url ); ?>" class="ajem-btn ajem-btn-primary ajem-btn-full">
						📝 <?php esc_html_e( 'Apply Now', 'ai-job-employer-manager' ); ?>
					</a>
				<?php endif; ?>
				<?php if ( $phone_number ) : ?>
					<a href="tel:+<?php echo esc_attr( preg_replace( '/\D/', '', $phone_number ) ); ?>" class="ajem-btn ajem-btn-call ajem-btn-full">📞 <?php esc_html_e( 'Call', 'ai-job-employer-manager' ); ?></a>
				<?php endif; ?>
				<?php if ( $wa_number ) : ?>
					<a href="https://wa.me/<?php echo esc_attr( $wa_number ); ?>?text=<?php echo esc_attr( $wa_message ); ?>" class="ajem-btn ajem-btn-whatsapp ajem-btn-full" target="_blank" rel="noopener noreferrer">💬 <?php esc_html_e( 'WhatsApp', 'ai-job-employer-manager' ); ?></a>
				<?php endif; ?>
			</div>
		</aside>

	</div>

	<!-- Related Jobs -->
	<?php if ( ! empty( $related_jobs ) ) : ?>
		<div class="ajem-related-jobs">
			<h3><?php esc_html_e( 'Similar Jobs', 'ai-job-employer-manager' ); ?></h3>
			<div class="ajem-related-jobs-grid">
				<?php foreach ( $related_jobs as $related ) : ?>
					<div class="ajem-related-job-card">
						<a href="<?php echo esc_url( site_url( '/jobs/' . $related->job_slug ) ); ?>">
							<h4><?php echo esc_html( $related->job_title ); ?></h4>
							<p><?php echo esc_html( $related->company_name ?? '' ); ?></p>
							<?php if ( $related->city ) : ?>
								<span>📍 <?php echo esc_html( $related->city ); ?></span>
							<?php endif; ?>
						</a>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>
</div>

<!-- Application Modal -->
<div class="ajem-modal" id="ajemApplicationModal" style="display:none;" aria-modal="true" role="dialog">
	<div class="ajem-modal-overlay" id="ajemModalOverlay"></div>
	<div class="ajem-modal-content">
		<button class="ajem-modal-close" id="ajemModalClose" aria-label="<?php esc_attr_e( 'Close', 'ai-job-employer-manager' ); ?>">×</button>
		<h3><?php esc_html_e( 'Apply for:', 'ai-job-employer-manager' ); ?> <?php echo esc_html( $job->job_title ); ?></h3>

		<form id="ajemApplicationForm">
			<?php wp_nonce_field( 'ajem_nonce', 'ajem_nonce' ); ?>
			<input type="hidden" name="job_id" value="<?php echo esc_attr( $job->id ); ?>">

			<div class="ajem-form-group">
				<label for="ajemCoverLetter"><?php esc_html_e( 'Cover Letter', 'ai-job-employer-manager' ); ?></label>
				<textarea id="ajemCoverLetter" name="cover_letter" rows="6" placeholder="<?php esc_attr_e( 'Tell the employer why you are a good fit...', 'ai-job-employer-manager' ); ?>"></textarea>
			</div>

			<div class="ajem-form-group">
				<label for="ajemResumeSelect"><?php esc_html_e( 'Select Resume (optional)', 'ai-job-employer-manager' ); ?></label>
				<select id="ajemResumeSelect" name="resume_id">
					<option value=""><?php esc_html_e( '— Use default resume —', 'ai-job-employer-manager' ); ?></option>
				</select>
			</div>

			<div class="ajem-form-actions">
				<button type="submit" class="ajem-btn ajem-btn-primary" id="ajemSubmitApplication"><?php esc_html_e( 'Submit Application', 'ai-job-employer-manager' ); ?></button>
				<span class="ajem-save-status" id="ajemApplicationStatus"></span>
			</div>
		</form>
	</div>
</div>

<script>
// Auto-open application modal if ?apply=1 parameter is set (post-login redirect).
<?php if ( $auto_open && is_user_logged_in() ) : ?>
document.addEventListener('DOMContentLoaded', function() {
	var modal = document.getElementById('ajemApplicationModal');
	if (modal) {
		modal.style.display = 'flex';
	}
});
<?php endif; ?>
</script>
