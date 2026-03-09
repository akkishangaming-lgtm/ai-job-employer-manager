<?php
/**
 * Job Listings Template — public listing page with search and filters.
 *
 * Variables available:
 * - $result          (array) — { jobs, total, pages }
 * - $filter_options  (array) — { industries, cities, states, job_types }
 * - $args            (array) — current query args
 *
 * @package AI_Job_Employer_Manager
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$jobs         = $result['jobs'] ?? array();
$total        = $result['total'] ?? 0;
$pages        = $result['pages'] ?? 1;
$current_page = max( 1, absint( $_GET['paged'] ?? 1 ) );
$keyword      = sanitize_text_field( wp_unslash( $_GET['keyword'] ?? '' ) );
$job_type     = sanitize_text_field( wp_unslash( $_GET['job_type'] ?? '' ) );
$industry     = sanitize_text_field( wp_unslash( $_GET['industry'] ?? '' ) );
$city         = sanitize_text_field( wp_unslash( $_GET['city'] ?? '' ) );
?>

<div class="ajem-listings-page">

	<!-- Search Bar -->
	<div class="ajem-search-bar">
		<form method="get" action="" class="ajem-search-form">
			<div class="ajem-search-inputs">
				<input type="text" name="keyword" value="<?php echo esc_attr( $keyword ); ?>" placeholder="<?php esc_attr_e( 'Job title, skills, or company', 'ai-job-employer-manager' ); ?>" class="ajem-search-keyword">
				<input type="text" name="city" value="<?php echo esc_attr( $city ); ?>" placeholder="<?php esc_attr_e( 'City or location', 'ai-job-employer-manager' ); ?>" class="ajem-search-city">
				<button type="submit" class="ajem-btn ajem-btn-primary"><?php esc_html_e( 'Search Jobs', 'ai-job-employer-manager' ); ?></button>
			</div>
		</form>
	</div>

	<div class="ajem-listings-container">

		<!-- Filters sidebar -->
		<aside class="ajem-filters-sidebar">
			<form method="get" id="ajemFiltersForm">
				<?php if ( $keyword ) : ?>
					<input type="hidden" name="keyword" value="<?php echo esc_attr( $keyword ); ?>">
				<?php endif; ?>

				<div class="ajem-filter-group">
					<h4><?php esc_html_e( 'Job Type', 'ai-job-employer-manager' ); ?></h4>
					<?php
					$job_type_labels = array(
						'full_time'  => __( 'Full Time', 'ai-job-employer-manager' ),
						'part_time'  => __( 'Part Time', 'ai-job-employer-manager' ),
						'internship' => __( 'Internship', 'ai-job-employer-manager' ),
						'contract'   => __( 'Contract', 'ai-job-employer-manager' ),
						'remote'     => __( 'Remote', 'ai-job-employer-manager' ),
					);
					foreach ( $job_type_labels as $value => $label ) :
						?>
						<label class="ajem-filter-checkbox">
							<input type="radio" name="job_type" value="<?php echo esc_attr( $value ); ?>" <?php checked( $job_type, $value ); ?>>
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
					<?php if ( $job_type ) : ?>
						<a href="<?php echo esc_url( remove_query_arg( 'job_type' ) ); ?>" class="ajem-clear-filter"><?php esc_html_e( 'Clear', 'ai-job-employer-manager' ); ?></a>
					<?php endif; ?>
				</div>

				<?php if ( ! empty( $filter_options['industries'] ) ) : ?>
					<div class="ajem-filter-group">
						<h4><?php esc_html_e( 'Industry', 'ai-job-employer-manager' ); ?></h4>
						<select name="industry" onchange="this.form.submit()">
							<option value=""><?php esc_html_e( 'All Industries', 'ai-job-employer-manager' ); ?></option>
							<?php foreach ( $filter_options['industries'] as $ind ) : ?>
								<option value="<?php echo esc_attr( $ind ); ?>" <?php selected( $industry, $ind ); ?>><?php echo esc_html( $ind ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php endif; ?>

				<div class="ajem-filter-group">
					<h4><?php esc_html_e( 'Salary Range', 'ai-job-employer-manager' ); ?></h4>
					<div class="ajem-salary-range">
						<div class="ajem-salary-field">
							<label for="ajemSalMin"><?php esc_html_e( 'Min', 'ai-job-employer-manager' ); ?></label>
							<input type="number" id="ajemSalMin" name="salary_min"
								placeholder="0"
								value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET['salary_min'] ?? '' ) ) ); ?>"
								min="0">
						</div>
						<span class="ajem-salary-sep">–</span>
						<div class="ajem-salary-field">
							<label for="ajemSalMax"><?php esc_html_e( 'Max', 'ai-job-employer-manager' ); ?></label>
							<input type="number" id="ajemSalMax" name="salary_max"
								placeholder="<?php esc_attr_e( 'Any', 'ai-job-employer-manager' ); ?>"
								value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET['salary_max'] ?? '' ) ) ); ?>"
								min="0">
						</div>
					</div>
					<button type="submit" class="ajem-btn ajem-btn-sm ajem-btn-full"><?php esc_html_e( 'Apply', 'ai-job-employer-manager' ); ?></button>
				</div>

				<div class="ajem-filter-group">
					<h4><?php esc_html_e( 'Sort By', 'ai-job-employer-manager' ); ?></h4>
					<select name="sort" onchange="this.form.submit()">
						<option value="newest" <?php selected( sanitize_text_field( wp_unslash( $_GET['sort'] ?? '' ) ), 'newest' ); ?>><?php esc_html_e( 'Newest First', 'ai-job-employer-manager' ); ?></option>
						<option value="salary_high" <?php selected( sanitize_text_field( wp_unslash( $_GET['sort'] ?? '' ) ), 'salary_high' ); ?>><?php esc_html_e( 'Salary: High to Low', 'ai-job-employer-manager' ); ?></option>
						<option value="salary_low" <?php selected( sanitize_text_field( wp_unslash( $_GET['sort'] ?? '' ) ), 'salary_low' ); ?>><?php esc_html_e( 'Salary: Low to High', 'ai-job-employer-manager' ); ?></option>
						<option value="most_viewed" <?php selected( sanitize_text_field( wp_unslash( $_GET['sort'] ?? '' ) ), 'most_viewed' ); ?>><?php esc_html_e( 'Most Viewed', 'ai-job-employer-manager' ); ?></option>
						<option value="deadline" <?php selected( sanitize_text_field( wp_unslash( $_GET['sort'] ?? '' ) ), 'deadline' ); ?>><?php esc_html_e( 'Closing Soon', 'ai-job-employer-manager' ); ?></option>
					</select>
				</div>
			</form>
		</aside>

		<!-- Job cards -->
		<div class="ajem-jobs-list">
			<div class="ajem-results-header">
				<span class="ajem-results-count">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of jobs */
							_n( '%d job found', '%d jobs found', $total, 'ai-job-employer-manager' ),
							$total
						)
					);
					?>
				</span>
			</div>

			<?php if ( empty( $jobs ) ) : ?>
				<div class="ajem-no-results">
					<h3><?php esc_html_e( 'No jobs found', 'ai-job-employer-manager' ); ?></h3>
					<p><?php esc_html_e( 'Try adjusting your search filters.', 'ai-job-employer-manager' ); ?></p>
				</div>
			<?php else : ?>
				<?php foreach ( $jobs as $job ) : ?>
					<div class="ajem-job-card">
						<div class="ajem-job-card-header">
							<?php if ( ! empty( $job->company_logo ) ) : ?>
								<img src="<?php echo esc_url( $job->company_logo ); ?>" alt="<?php echo esc_attr( $job->company_name ); ?>" class="ajem-job-card-logo">
							<?php else : ?>
								<div class="ajem-job-card-logo-placeholder"><?php echo esc_html( mb_substr( $job->company_name ?? '', 0, 1 ) ); ?></div>
							<?php endif; ?>
							<div class="ajem-job-card-info">
								<h3 class="ajem-job-card-title">
									<a href="<?php echo esc_url( site_url( '/jobs/' . $job->job_slug ) ); ?>"><?php echo esc_html( $job->job_title ); ?></a>
								</h3>
								<div class="ajem-job-card-company"><?php echo esc_html( $job->company_name ?? '' ); ?></div>
								<div class="ajem-job-card-meta">
									<?php if ( $job->city ) : ?>
										<span class="ajem-meta-item">📍 <?php echo esc_html( $job->city ); ?><?php echo $job->state ? ', ' . esc_html( $job->state ) : ''; ?></span>
									<?php endif; ?>
									<span class="ajem-badge ajem-badge-type"><?php echo esc_html( str_replace( '_', ' ', ucfirst( $job->job_type ) ) ); ?></span>
									<?php if ( $job->salary_min || $job->salary_max ) :
										$sal_curr = $job->salary_currency ?? 'INR';
										$sal_min  = $job->salary_min ? number_format( (float) $job->salary_min ) : '';
										$sal_max  = $job->salary_max ? number_format( (float) $job->salary_max ) : '';
										$sal_str  = $sal_curr . ' ' . $sal_min . ( $sal_min && $sal_max ? ' – ' . $sal_max : $sal_max );
									?>
									<span class="ajem-meta-item ajem-salary">💰 <?php echo esc_html( $sal_str ); ?></span>
								<?php endif; ?>
								</div>
							</div>
							<?php if ( $job->is_featured ) : ?>
								<div class="ajem-featured-ribbon"><?php esc_html_e( 'Featured', 'ai-job-employer-manager' ); ?></div>
							<?php endif; ?>
						</div>

						<?php if ( ! empty( $job->required_skills_array ) ) : ?>
							<div class="ajem-job-skills">
								<?php foreach ( array_slice( $job->required_skills_array, 0, 5 ) as $skill ) : ?>
									<span class="ajem-skill-tag"><?php echo esc_html( $skill ); ?></span>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

						<div class="ajem-job-card-footer">
							<span class="ajem-job-date"><?php echo esc_html( human_time_diff( strtotime( $job->created_at ), time() ) . ' ' . __( 'ago', 'ai-job-employer-manager' ) ); ?></span>
							<?php if ( $job->application_deadline ) : ?>
								<span class="ajem-job-deadline">⏰ <?php echo esc_html( $job->application_deadline ); ?></span>
							<?php endif; ?>
							<a href="<?php echo esc_url( site_url( '/jobs/' . $job->job_slug ) ); ?>" class="ajem-btn ajem-btn-sm ajem-btn-primary"><?php esc_html_e( 'View Job', 'ai-job-employer-manager' ); ?></a>
						</div>
					</div>
				<?php endforeach; ?>

				<!-- Pagination -->
				<?php if ( $pages > 1 ) : ?>
					<div class="ajem-pagination">
						<?php for ( $i = 1; $i <= $pages; $i++ ) : ?>
							<?php if ( $i === $current_page ) : ?>
								<span class="ajem-page-current"><?php echo esc_html( $i ); ?></span>
							<?php else : ?>
								<a href="<?php echo esc_url( add_query_arg( 'paged', $i ) ); ?>" class="ajem-page-link"><?php echo esc_html( $i ); ?></a>
							<?php endif; ?>
						<?php endfor; ?>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	</div>
</div>
