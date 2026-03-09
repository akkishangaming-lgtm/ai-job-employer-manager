<?php
/**
 * Employer Dashboard Template — full sidebar employer control panel.
 *
 * Variables available:
 * - $employer  (object|null) — employer profile row
 * - $stats     (array)       — analytics stats
 * - $jobs_result (array)     — employer jobs { jobs, total, pages }
 *
 * @package AI_Job_Employer_Manager
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$user    = wp_get_current_user();
$user_id = (int) $user->ID;

// Active section.
$section = sanitize_key( $_GET['section'] ?? 'overview' );
$allowed_sections = array( 'overview', 'profile', 'post-job', 'my-jobs', 'applications', 'shortlisted', 'analytics', 'settings' );
if ( ! in_array( $section, $allowed_sections, true ) ) {
	$section = 'overview';
}

$stats       = $stats ?? array();
$jobs        = $jobs_result['jobs'] ?? array();
$jobs_total  = $jobs_result['total'] ?? 0;

$company_name = $employer ? esc_html( $employer->company_name ) : esc_html( $user->display_name );
?>

<div class="ajem-dashboard-wrapper" id="ajem-dashboard">

	<!-- Mobile hamburger -->
	<button class="ajem-hamburger" id="ajemHamburger" aria-label="<?php esc_attr_e( 'Toggle menu', 'ai-job-employer-manager' ); ?>">
		<span></span><span></span><span></span>
	</button>

	<!-- Sidebar -->
	<aside class="ajem-sidebar" id="ajemSidebar">
		<div class="ajem-sidebar-header">
			<?php if ( $employer && $employer->company_logo ) : ?>
				<img src="<?php echo esc_url( $employer->company_logo ); ?>" alt="<?php echo esc_attr( $company_name ); ?>" class="ajem-sidebar-logo">
			<?php else : ?>
				<div class="ajem-sidebar-avatar"><?php echo esc_html( mb_substr( $company_name, 0, 1 ) ); ?></div>
			<?php endif; ?>
			<div class="ajem-sidebar-name"><?php echo esc_html( $company_name ); ?></div>
		</div>

		<nav class="ajem-nav">
			<?php
			$nav_items = array(
				'overview'    => array( 'icon' => '📊', 'label' => __( 'Dashboard', 'ai-job-employer-manager' ) ),
				'profile'     => array( 'icon' => '🏢', 'label' => __( 'Company Profile', 'ai-job-employer-manager' ) ),
				'post-job'    => array( 'icon' => '➕', 'label' => __( 'Post New Job', 'ai-job-employer-manager' ) ),
				'my-jobs'     => array( 'icon' => '📋', 'label' => __( 'My Jobs', 'ai-job-employer-manager' ) ),
				'applications'=> array( 'icon' => '📩', 'label' => __( 'Applications', 'ai-job-employer-manager' ) ),
				'shortlisted' => array( 'icon' => '⭐', 'label' => __( 'Shortlisted', 'ai-job-employer-manager' ) ),
				'analytics'   => array( 'icon' => '📈', 'label' => __( 'Analytics', 'ai-job-employer-manager' ) ),
				'settings'    => array( 'icon' => '⚙️', 'label' => __( 'Settings', 'ai-job-employer-manager' ) ),
			);
			foreach ( $nav_items as $key => $item ) :
				$active = $section === $key ? ' ajem-nav-active' : '';
				$url    = add_query_arg( 'section', $key );
				?>
				<a href="<?php echo esc_url( $url ); ?>" class="ajem-nav-item<?php echo esc_attr( $active ); ?>" data-section="<?php echo esc_attr( $key ); ?>">
					<span class="ajem-nav-icon"><?php echo esc_html( $item['icon'] ); ?></span>
					<?php echo esc_html( $item['label'] ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
	</aside>

	<!-- Main content -->
	<main class="ajem-main">

		<!-- OVERVIEW -->
		<div class="ajem-section<?php echo $section === 'overview' ? ' active' : ''; ?>" id="ajem-section-overview">
			<h2><?php esc_html_e( 'Dashboard Overview', 'ai-job-employer-manager' ); ?></h2>

			<div class="ajem-stats-grid">
				<?php
				$stat_cards = array(
					array( 'label' => __( 'Active Jobs', 'ai-job-employer-manager' ), 'value' => $stats['active_jobs'] ?? 0, 'class' => 'green' ),
					array( 'label' => __( 'Total Applications', 'ai-job-employer-manager' ), 'value' => $stats['total_applications'] ?? 0, 'class' => 'blue' ),
					array( 'label' => __( 'This Week', 'ai-job-employer-manager' ), 'value' => $stats['applications_this_week'] ?? 0, 'class' => 'cyan' ),
					array( 'label' => __( 'Shortlisted', 'ai-job-employer-manager' ), 'value' => $stats['shortlisted'] ?? 0, 'class' => 'orange' ),
					array( 'label' => __( 'Hired', 'ai-job-employer-manager' ), 'value' => $stats['hired'] ?? 0, 'class' => 'purple' ),
					array( 'label' => __( 'Total Views', 'ai-job-employer-manager' ), 'value' => $stats['total_views'] ?? 0, 'class' => 'gray' ),
				);
				foreach ( $stat_cards as $card ) :
					?>
					<div class="ajem-stat-card ajem-stat-<?php echo esc_attr( $card['class'] ); ?>">
						<div class="ajem-stat-value"><?php echo esc_html( number_format( (int) $card['value'] ) ); ?></div>
						<div class="ajem-stat-label"><?php echo esc_html( $card['label'] ); ?></div>
					</div>
				<?php endforeach; ?>
			</div>

			<h3><?php esc_html_e( 'Recent Job Listings', 'ai-job-employer-manager' ); ?></h3>
			<?php if ( ! empty( $jobs ) ) : ?>
				<div class="ajem-table-wrap">
					<table class="ajem-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Job Title', 'ai-job-employer-manager' ); ?></th>
								<th><?php esc_html_e( 'Status', 'ai-job-employer-manager' ); ?></th>
								<th><?php esc_html_e( 'Views', 'ai-job-employer-manager' ); ?></th>
								<th><?php esc_html_e( 'Applications', 'ai-job-employer-manager' ); ?></th>
								<th><?php esc_html_e( 'Deadline', 'ai-job-employer-manager' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( array_slice( $jobs, 0, 5 ) as $job ) : ?>
								<tr>
									<td><a href="<?php echo esc_url( site_url( '/jobs/' . $job->job_slug ) ); ?>"><?php echo esc_html( $job->job_title ); ?></a></td>
									<td><span class="ajem-badge ajem-badge-<?php echo esc_attr( $job->status ); ?>"><?php echo esc_html( $job->status ); ?></span></td>
									<td><?php echo esc_html( number_format( (int) $job->views_count ) ); ?></td>
									<td><?php echo esc_html( number_format( (int) $job->applications_count ) ); ?></td>
									<td><?php echo $job->application_deadline ? esc_html( $job->application_deadline ) : '—'; ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else : ?>
				<p><?php esc_html_e( 'You have not posted any jobs yet.', 'ai-job-employer-manager' ); ?> <a href="<?php echo esc_url( add_query_arg( 'section', 'post-job' ) ); ?>"><?php esc_html_e( 'Post your first job →', 'ai-job-employer-manager' ); ?></a></p>
			<?php endif; ?>
		</div>

		<!-- COMPANY PROFILE -->
		<div class="ajem-section<?php echo $section === 'profile' ? ' active' : ''; ?>" id="ajem-section-profile">
			<h2><?php esc_html_e( 'Company Profile', 'ai-job-employer-manager' ); ?></h2>

			<!-- Company Logo Upload -->
			<div class="ajem-logo-upload-wrap">
				<div class="ajem-logo-preview" id="ajemLogoPreview">
					<?php if ( $employer && $employer->company_logo ) : ?>
						<img src="<?php echo esc_url( $employer->company_logo ); ?>" alt="<?php esc_attr_e( 'Company Logo', 'ai-job-employer-manager' ); ?>" id="ajemLogoImg">
					<?php else : ?>
						<span class="ajem-logo-placeholder-text" id="ajemLogoPlaceholder"><?php esc_html_e( 'No logo', 'ai-job-employer-manager' ); ?></span>
					<?php endif; ?>
				</div>
				<div class="ajem-logo-upload-controls">
					<label for="ajemLogoFile" class="ajem-btn ajem-btn-secondary">
						<?php esc_html_e( '📷 Choose Logo', 'ai-job-employer-manager' ); ?>
					</label>
					<input type="file" id="ajemLogoFile" name="company_logo_file" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;">
					<p class="description"><?php esc_html_e( 'JPEG, PNG, GIF or WebP. Max 2 MB.', 'ai-job-employer-manager' ); ?></p>
					<span class="ajem-logo-upload-status" id="ajemLogoUploadStatus"></span>
				</div>
			</div>

			<form class="ajem-form" id="ajemProfileForm">
				<?php wp_nonce_field( 'ajem_nonce', 'ajem_nonce' ); ?>

				<div class="ajem-form-grid">
					<div class="ajem-form-group">
						<label for="ajem_company_name"><?php esc_html_e( 'Company Name', 'ai-job-employer-manager' ); ?> <span class="required">*</span></label>
						<input type="text" id="ajem_company_name" name="company_name" value="<?php echo $employer ? esc_attr( $employer->company_name ) : ''; ?>" required>
					</div>

					<div class="ajem-form-group">
						<label for="ajem_industry"><?php esc_html_e( 'Industry', 'ai-job-employer-manager' ); ?></label>
						<input type="text" id="ajem_industry" name="industry" value="<?php echo $employer ? esc_attr( $employer->industry ) : ''; ?>">
					</div>

					<div class="ajem-form-group">
						<label for="ajem_company_size"><?php esc_html_e( 'Company Size', 'ai-job-employer-manager' ); ?></label>
						<select id="ajem_company_size" name="company_size">
							<?php
							$sizes = array( '', '1-10', '11-50', '51-200', '201-500', '501-1000', '1000+' );
							foreach ( $sizes as $size ) {
								$selected = ( $employer && $employer->company_size === $size ) ? 'selected' : '';
								echo '<option value="' . esc_attr( $size ) . '" ' . esc_attr( $selected ) . '>' . esc_html( $size ?: __( '— Select —', 'ai-job-employer-manager' ) ) . '</option>';
							}
							?>
						</select>
					</div>

					<div class="ajem-form-group">
						<label for="ajem_founded_year"><?php esc_html_e( 'Founded Year', 'ai-job-employer-manager' ); ?></label>
						<input type="number" id="ajem_founded_year" name="founded_year" min="1800" max="<?php echo esc_attr( gmdate( 'Y' ) ); ?>" value="<?php echo $employer && $employer->founded_year ? esc_attr( $employer->founded_year ) : ''; ?>">
					</div>

					<div class="ajem-form-group">
						<label for="ajem_contact_email"><?php esc_html_e( 'Contact Email', 'ai-job-employer-manager' ); ?></label>
						<input type="email" id="ajem_contact_email" name="contact_email" value="<?php echo $employer ? esc_attr( $employer->contact_email ) : esc_attr( $user->user_email ); ?>">
					</div>

					<div class="ajem-form-group">
						<label for="ajem_contact_phone"><?php esc_html_e( 'Contact Phone', 'ai-job-employer-manager' ); ?></label>
						<input type="tel" id="ajem_contact_phone" name="contact_phone" value="<?php echo $employer ? esc_attr( $employer->contact_phone ) : ''; ?>">
					</div>

					<div class="ajem-form-group">
						<label for="ajem_whatsapp_number"><?php esc_html_e( 'WhatsApp Number', 'ai-job-employer-manager' ); ?></label>
						<input type="tel" id="ajem_whatsapp_number" name="whatsapp_number" placeholder="91XXXXXXXXXX" value="<?php echo $employer ? esc_attr( $employer->whatsapp_number ) : ''; ?>">
					</div>

					<div class="ajem-form-group">
						<label for="ajem_company_website"><?php esc_html_e( 'Company Website', 'ai-job-employer-manager' ); ?></label>
						<input type="url" id="ajem_company_website" name="company_website" value="<?php echo $employer ? esc_attr( $employer->company_website ) : ''; ?>">
					</div>

					<div class="ajem-form-group ajem-full-width">
						<label for="ajem_company_description"><?php esc_html_e( 'Company Description', 'ai-job-employer-manager' ); ?></label>
						<textarea id="ajem_company_description" name="company_description" rows="5"><?php echo $employer ? wp_kses_post( $employer->company_description ) : ''; ?></textarea>
					</div>

					<!-- Location -->
					<div class="ajem-form-group ajem-full-width">
						<label><?php esc_html_e( 'Location', 'ai-job-employer-manager' ); ?></label>
						<button type="button" class="ajem-btn ajem-btn-secondary" id="ajemDetectLocation"><?php esc_html_e( '📍 Detect My Location', 'ai-job-employer-manager' ); ?></button>
						<span class="ajem-location-status" id="ajemLocationStatus"></span>
					</div>

					<div class="ajem-form-group">
						<label for="ajem_country"><?php esc_html_e( 'Country', 'ai-job-employer-manager' ); ?></label>
						<input type="text" id="ajem_country" name="country" value="<?php echo $employer ? esc_attr( $employer->country ) : ''; ?>">
					</div>
					<div class="ajem-form-group">
						<label for="ajem_state"><?php esc_html_e( 'State', 'ai-job-employer-manager' ); ?></label>
						<input type="text" id="ajem_state" name="state" value="<?php echo $employer ? esc_attr( $employer->state ) : ''; ?>">
					</div>
					<div class="ajem-form-group">
						<label for="ajem_district"><?php esc_html_e( 'District', 'ai-job-employer-manager' ); ?></label>
						<input type="text" id="ajem_district" name="district" value="<?php echo $employer ? esc_attr( $employer->district ) : ''; ?>">
					</div>
					<div class="ajem-form-group">
						<label for="ajem_city"><?php esc_html_e( 'City', 'ai-job-employer-manager' ); ?></label>
						<input type="text" id="ajem_city" name="city" value="<?php echo $employer ? esc_attr( $employer->city ) : ''; ?>">
					</div>

					<input type="hidden" name="latitude" id="ajem_latitude" value="<?php echo $employer && $employer->latitude ? esc_attr( $employer->latitude ) : ''; ?>">
					<input type="hidden" name="longitude" id="ajem_longitude" value="<?php echo $employer && $employer->longitude ? esc_attr( $employer->longitude ) : ''; ?>">
				</div>

				<div class="ajem-form-actions">
					<button type="submit" class="ajem-btn ajem-btn-primary"><?php esc_html_e( 'Save Profile', 'ai-job-employer-manager' ); ?></button>
					<span class="ajem-save-status" id="ajemProfileStatus"></span>
				</div>
			</form>
		</div>

		<!-- POST NEW JOB -->
		<div class="ajem-section<?php echo $section === 'post-job' ? ' active' : ''; ?>" id="ajem-section-post-job">
			<h2><?php esc_html_e( 'Post New Job', 'ai-job-employer-manager' ); ?></h2>

			<form class="ajem-form" id="ajemPostJobForm">
				<?php wp_nonce_field( 'ajem_nonce', 'ajem_nonce' ); ?>

				<div class="ajem-form-grid">
					<div class="ajem-form-group ajem-full-width">
						<label for="ajem_job_title"><?php esc_html_e( 'Job Title', 'ai-job-employer-manager' ); ?> <span class="required">*</span></label>
						<input type="text" id="ajem_job_title" name="job_title" required placeholder="<?php esc_attr_e( 'e.g. Software Developer', 'ai-job-employer-manager' ); ?>">
					</div>

					<div class="ajem-form-group">
						<label for="ajem_job_type"><?php esc_html_e( 'Job Type', 'ai-job-employer-manager' ); ?></label>
						<select id="ajem_job_type" name="job_type">
							<option value="full_time"><?php esc_html_e( 'Full Time', 'ai-job-employer-manager' ); ?></option>
							<option value="part_time"><?php esc_html_e( 'Part Time', 'ai-job-employer-manager' ); ?></option>
							<option value="internship"><?php esc_html_e( 'Internship', 'ai-job-employer-manager' ); ?></option>
							<option value="contract"><?php esc_html_e( 'Contract', 'ai-job-employer-manager' ); ?></option>
							<option value="remote"><?php esc_html_e( 'Remote', 'ai-job-employer-manager' ); ?></option>
						</select>
					</div>

					<div class="ajem-form-group">
						<label for="ajem_job_industry"><?php esc_html_e( 'Industry', 'ai-job-employer-manager' ); ?></label>
						<input type="text" id="ajem_job_industry" name="industry">
					</div>

					<div class="ajem-form-group">
						<label for="ajem_experience_required"><?php esc_html_e( 'Experience Required', 'ai-job-employer-manager' ); ?></label>
						<input type="text" id="ajem_experience_required" name="experience_required" placeholder="<?php esc_attr_e( 'e.g. 2-5 years', 'ai-job-employer-manager' ); ?>">
					</div>

					<div class="ajem-form-group">
						<label for="ajem_education_required"><?php esc_html_e( 'Education Required', 'ai-job-employer-manager' ); ?></label>
						<input type="text" id="ajem_education_required" name="education_required" placeholder="<?php esc_attr_e( 'e.g. Bachelor\'s Degree', 'ai-job-employer-manager' ); ?>">
					</div>

					<div class="ajem-form-group">
						<label for="ajem_salary_min"><?php esc_html_e( 'Salary Min (per month)', 'ai-job-employer-manager' ); ?></label>
						<input type="number" id="ajem_salary_min" name="salary_min" min="0">
					</div>

					<div class="ajem-form-group">
						<label for="ajem_salary_max"><?php esc_html_e( 'Salary Max (per month)', 'ai-job-employer-manager' ); ?></label>
						<input type="number" id="ajem_salary_max" name="salary_max" min="0">
					</div>

					<div class="ajem-form-group">
						<label for="ajem_salary_currency"><?php esc_html_e( 'Currency', 'ai-job-employer-manager' ); ?></label>
						<select id="ajem_salary_currency" name="salary_currency">
							<?php
							$currencies = apply_filters( 'ajem_salary_currencies', array( 'INR' => 'INR (₹)', 'USD' => 'USD ($)', 'EUR' => 'EUR (€)', 'GBP' => 'GBP (£)' ) );
							foreach ( $currencies as $code => $label ) {
								echo '<option value="' . esc_attr( $code ) . '">' . esc_html( $label ) . '</option>';
							}
							?>
						</select>
					</div>

					<div class="ajem-form-group">
						<label for="ajem_application_deadline"><?php esc_html_e( 'Application Deadline', 'ai-job-employer-manager' ); ?></label>
						<input type="date" id="ajem_application_deadline" name="application_deadline" min="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>">
					</div>

					<div class="ajem-form-group">
						<label for="ajem_job_status"><?php esc_html_e( 'Status', 'ai-job-employer-manager' ); ?></label>
						<select id="ajem_job_status" name="status">
							<option value="draft"><?php esc_html_e( 'Draft', 'ai-job-employer-manager' ); ?></option>
							<option value="active"><?php esc_html_e( 'Active', 'ai-job-employer-manager' ); ?></option>
						</select>
					</div>

					<!-- Job Location -->
					<div class="ajem-form-group ajem-full-width">
						<label><?php esc_html_e( 'Job Location', 'ai-job-employer-manager' ); ?></label>
						<button type="button" class="ajem-btn ajem-btn-secondary" id="ajemDetectJobLocation"><?php esc_html_e( '📍 Use My Company Location', 'ai-job-employer-manager' ); ?></button>
					</div>
					<div class="ajem-form-group">
						<label for="ajem_job_country"><?php esc_html_e( 'Country', 'ai-job-employer-manager' ); ?></label>
						<input type="text" id="ajem_job_country" name="country" value="<?php echo $employer ? esc_attr( $employer->country ) : ''; ?>">
					</div>
					<div class="ajem-form-group">
						<label for="ajem_job_state"><?php esc_html_e( 'State', 'ai-job-employer-manager' ); ?></label>
						<input type="text" id="ajem_job_state" name="state" value="<?php echo $employer ? esc_attr( $employer->state ) : ''; ?>">
					</div>
					<div class="ajem-form-group">
						<label for="ajem_job_district"><?php esc_html_e( 'District', 'ai-job-employer-manager' ); ?></label>
						<input type="text" id="ajem_job_district" name="district" value="<?php echo $employer ? esc_attr( $employer->district ) : ''; ?>">
					</div>
					<div class="ajem-form-group">
						<label for="ajem_job_city"><?php esc_html_e( 'City', 'ai-job-employer-manager' ); ?></label>
						<input type="text" id="ajem_job_city" name="city" value="<?php echo $employer ? esc_attr( $employer->city ) : ''; ?>">
					</div>
					<input type="hidden" name="latitude" id="ajem_job_latitude" value="<?php echo $employer && $employer->latitude ? esc_attr( $employer->latitude ) : ''; ?>">
					<input type="hidden" name="longitude" id="ajem_job_longitude" value="<?php echo $employer && $employer->longitude ? esc_attr( $employer->longitude ) : ''; ?>">

					<!-- Required Skills -->
					<div class="ajem-form-group ajem-full-width">
						<label><?php esc_html_e( 'Required Skills', 'ai-job-employer-manager' ); ?></label>
						<div class="ajem-skills-input">
							<div class="ajem-skills-tags" id="ajemSkillsTags"></div>
							<input type="text" id="ajemSkillInput" placeholder="<?php esc_attr_e( 'Type a skill and press Enter', 'ai-job-employer-manager' ); ?>">
						</div>
						<input type="hidden" name="required_skills" id="ajemRequiredSkills" value="[]">
					</div>

					<!-- Job Description -->
					<div class="ajem-form-group ajem-full-width">
						<label for="ajem_job_description"><?php esc_html_e( 'Job Description', 'ai-job-employer-manager' ); ?> <span class="required">*</span></label>
						<textarea id="ajem_job_description" name="job_description" rows="12" required></textarea>
					</div>
				</div>

				<div class="ajem-form-actions">
					<button type="submit" class="ajem-btn ajem-btn-primary" id="ajemPostJobSubmit"><?php esc_html_e( 'Post Job', 'ai-job-employer-manager' ); ?></button>
					<span class="ajem-save-status" id="ajemPostJobStatus"></span>
				</div>
			</form>
		</div>

		<!-- MY JOBS -->
		<div class="ajem-section<?php echo $section === 'my-jobs' ? ' active' : ''; ?>" id="ajem-section-my-jobs">
			<h2><?php esc_html_e( 'My Job Listings', 'ai-job-employer-manager' ); ?></h2>

			<div class="ajem-table-actions">
				<a href="<?php echo esc_url( add_query_arg( 'section', 'post-job' ) ); ?>" class="ajem-btn ajem-btn-primary"><?php esc_html_e( '+ Post New Job', 'ai-job-employer-manager' ); ?></a>
			</div>

			<?php if ( ! empty( $jobs ) ) : ?>
				<div class="ajem-table-wrap">
					<table class="ajem-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Job Title', 'ai-job-employer-manager' ); ?></th>
								<th><?php esc_html_e( 'Type', 'ai-job-employer-manager' ); ?></th>
								<th><?php esc_html_e( 'Status', 'ai-job-employer-manager' ); ?></th>
								<th><?php esc_html_e( 'Views', 'ai-job-employer-manager' ); ?></th>
								<th><?php esc_html_e( 'Applications', 'ai-job-employer-manager' ); ?></th>
								<th><?php esc_html_e( 'Deadline', 'ai-job-employer-manager' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'ai-job-employer-manager' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $jobs as $job ) : ?>
								<tr data-job-id="<?php echo esc_attr( $job->id ); ?>">
									<td>
										<a href="<?php echo esc_url( site_url( '/jobs/' . $job->job_slug ) ); ?>" target="_blank"><?php echo esc_html( $job->job_title ); ?></a>
										<?php if ( $job->is_featured ) : ?><span class="ajem-featured-badge"><?php esc_html_e( 'Featured', 'ai-job-employer-manager' ); ?></span><?php endif; ?>
									</td>
									<td><?php echo esc_html( str_replace( '_', ' ', ucfirst( $job->job_type ) ) ); ?></td>
									<td>
										<select class="ajem-status-select" data-job-id="<?php echo esc_attr( $job->id ); ?>">
											<?php foreach ( array( 'active', 'paused', 'closed', 'draft' ) as $st ) : ?>
												<option value="<?php echo esc_attr( $st ); ?>" <?php selected( $job->status, $st ); ?>><?php echo esc_html( ucfirst( $st ) ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
									<td><?php echo esc_html( number_format( (int) $job->views_count ) ); ?></td>
									<td><?php echo esc_html( number_format( (int) $job->applications_count ) ); ?></td>
									<td><?php echo $job->application_deadline ? esc_html( $job->application_deadline ) : '—'; ?></td>
									<td>
										<button class="ajem-btn-icon ajem-delete-job" data-job-id="<?php echo esc_attr( $job->id ); ?>" title="<?php esc_attr_e( 'Delete', 'ai-job-employer-manager' ); ?>">🗑️</button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else : ?>
				<p><?php esc_html_e( 'No jobs posted yet.', 'ai-job-employer-manager' ); ?></p>
			<?php endif; ?>
		</div>

		<!-- APPLICATIONS -->
		<div class="ajem-section<?php echo $section === 'applications' ? ' active' : ''; ?>" id="ajem-section-applications">
			<h2><?php esc_html_e( 'Applications', 'ai-job-employer-manager' ); ?></h2>
			<div id="ajemApplicationsList">
				<p class="ajem-loading"><?php esc_html_e( 'Loading applications...', 'ai-job-employer-manager' ); ?></p>
			</div>
		</div>

		<!-- SHORTLISTED -->
		<div class="ajem-section<?php echo $section === 'shortlisted' ? ' active' : ''; ?>" id="ajem-section-shortlisted">
			<h2><?php esc_html_e( 'Shortlisted Candidates', 'ai-job-employer-manager' ); ?></h2>
			<div id="ajemShortlistedList">
				<p class="ajem-loading"><?php esc_html_e( 'Loading shortlisted candidates...', 'ai-job-employer-manager' ); ?></p>
			</div>
		</div>

		<!-- ANALYTICS -->
		<div class="ajem-section<?php echo $section === 'analytics' ? ' active' : ''; ?>" id="ajem-section-analytics">
			<h2><?php esc_html_e( 'Analytics', 'ai-job-employer-manager' ); ?></h2>

			<div class="ajem-analytics-summary">
				<div class="ajem-chart-wrap">
					<h3><?php esc_html_e( 'Applications Overview', 'ai-job-employer-manager' ); ?></h3>
					<div id="ajemAnalyticsChart" class="ajem-bar-chart">
						<p class="ajem-loading"><?php esc_html_e( 'Loading analytics...', 'ai-job-employer-manager' ); ?></p>
					</div>
				</div>
				<div class="ajem-top-jobs-wrap">
					<h3><?php esc_html_e( 'Top Performing Jobs', 'ai-job-employer-manager' ); ?></h3>
					<div id="ajemTopJobs">
						<p class="ajem-loading"><?php esc_html_e( 'Loading...', 'ai-job-employer-manager' ); ?></p>
					</div>
				</div>
			</div>
		</div>

		<!-- SETTINGS -->
		<div class="ajem-section<?php echo $section === 'settings' ? ' active' : ''; ?>" id="ajem-section-settings">
			<h2><?php esc_html_e( 'Account Settings', 'ai-job-employer-manager' ); ?></h2>

			<div class="ajem-settings-info">
				<p><strong><?php esc_html_e( 'Username:', 'ai-job-employer-manager' ); ?></strong> <?php echo esc_html( $user->user_login ); ?></p>
				<p><strong><?php esc_html_e( 'Email:', 'ai-job-employer-manager' ); ?></strong> <?php echo esc_html( $user->user_email ); ?></p>
				<p>
					<a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>" class="ajem-btn ajem-btn-secondary"><?php esc_html_e( 'Log Out', 'ai-job-employer-manager' ); ?></a>
					&nbsp;
					<a href="<?php echo esc_url( admin_url( 'profile.php' ) ); ?>" class="ajem-btn ajem-btn-outline"><?php esc_html_e( 'Edit WordPress Profile', 'ai-job-employer-manager' ); ?></a>
				</p>
			</div>
		</div>

	</main>
</div>

<!-- Toast notification -->
<div class="ajem-toast" id="ajemToast"></div>
