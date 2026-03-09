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

				<table class="ajem-jobs-table">
					<thead>
						<tr>
							<th class="ajem-jt-col-job"><?php esc_html_e( 'Job', 'ai-job-employer-manager' ); ?></th>
							<th class="ajem-jt-col-details"><?php esc_html_e( 'Details', 'ai-job-employer-manager' ); ?></th>
							<th class="ajem-jt-col-desc"><?php esc_html_e( 'Description', 'ai-job-employer-manager' ); ?></th>
							<th class="ajem-jt-col-action"><?php esc_html_e( 'Action', 'ai-job-employer-manager' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $jobs as $job ) :
						// Salary string.
						$jt_sal = '';
						if ( $job->salary_min || $job->salary_max ) {
							$jt_curr = $job->salary_currency ?? 'INR';
							$jt_min  = $job->salary_min ? number_format( (float) $job->salary_min ) : '';
							$jt_max  = $job->salary_max ? number_format( (float) $job->salary_max ) : '';
							$jt_sal  = $jt_curr . ' ' . $jt_min . ( $jt_min && $jt_max ? ' – ' . $jt_max : $jt_max );
						}
						// Description excerpt — strip HTML tags, limit to 130 chars.
						$jt_desc_raw     = wp_strip_all_tags( $job->job_description ?? '' );
						$jt_desc_excerpt = mb_strlen( $jt_desc_raw ) > 130
							? mb_substr( $jt_desc_raw, 0, 130 ) . '…'
							: $jt_desc_raw;
						// Job type label.
						$jt_type = str_replace( '_', ' ', ucfirst( $job->job_type ?? '' ) );
						// Job URL.
						$jt_url  = esc_url( site_url( '/jobs/' . $job->job_slug ) );
					?>
					<tr class="ajem-jt-row<?php echo $job->is_featured ? ' ajem-jt-featured' : ''; ?>">

						<!-- Job column: logo + title + company + location -->
						<td class="ajem-jt-job">
							<div class="ajem-jt-job-inner">
								<?php if ( ! empty( $job->company_logo ) ) : ?>
									<img src="<?php echo esc_url( $job->company_logo ); ?>"
										alt="<?php echo esc_attr( $job->company_name ?? '' ); ?>"
										class="ajem-jt-logo">
								<?php else : ?>
									<div class="ajem-jt-logo-placeholder">
										<?php echo esc_html( mb_strtoupper( mb_substr( $job->company_name ?? 'J', 0, 1 ) ) ); ?>
									</div>
								<?php endif; ?>
								<div class="ajem-jt-job-meta">
									<a href="<?php echo $jt_url; ?>" class="ajem-jt-title">
										<?php echo esc_html( $job->job_title ); ?>
									</a>
									<?php if ( ! empty( $job->company_name ) ) : ?>
										<div class="ajem-jt-company"><?php echo esc_html( $job->company_name ); ?></div>
									<?php endif; ?>
									<?php if ( $job->city ) : ?>
										<div class="ajem-jt-location">📍 <?php echo esc_html( $job->city ); ?><?php echo $job->state ? ', ' . esc_html( $job->state ) : ''; ?></div>
									<?php endif; ?>
									<?php if ( $job->is_featured ) : ?>
										<span class="ajem-badge ajem-badge-featured"><?php esc_html_e( 'Featured', 'ai-job-employer-manager' ); ?></span>
									<?php endif; ?>
								</div>
							</div>
						</td>

						<!-- Details column: type + salary + deadline + posted -->
						<td class="ajem-jt-details">
							<?php if ( $jt_type ) : ?>
								<span class="ajem-badge ajem-badge-type"><?php echo esc_html( $jt_type ); ?></span>
							<?php endif; ?>
							<?php if ( $jt_sal ) : ?>
								<div class="ajem-jt-salary">💰 <?php echo esc_html( $jt_sal ); ?></div>
							<?php endif; ?>
							<?php if ( $job->application_deadline ) : ?>
								<div class="ajem-jt-deadline">⏰ <?php
									printf(
										/* translators: %s: deadline date */
										esc_html__( 'Deadline: %s', 'ai-job-employer-manager' ),
										esc_html( date_i18n( get_option( 'date_format' ), strtotime( $job->application_deadline ) ) )
									);
								?></div>
							<?php endif; ?>
							<div class="ajem-jt-posted">🕒 <?php echo esc_html( human_time_diff( strtotime( $job->created_at ), time() ) . ' ' . __( 'ago', 'ai-job-employer-manager' ) ); ?></div>
							<?php if ( ! empty( $job->required_skills_array ) ) : ?>
								<div class="ajem-jt-skills">
									<?php foreach ( array_slice( $job->required_skills_array, 0, 4 ) as $skill ) : ?>
										<span class="ajem-skill-tag"><?php echo esc_html( $skill ); ?></span>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</td>

						<!-- Description column -->
						<td class="ajem-jt-desc">
							<?php if ( $jt_desc_excerpt ) : ?>
								<p class="ajem-jt-desc-text"><?php echo esc_html( $jt_desc_excerpt ); ?></p>
							<?php else : ?>
								<span class="ajem-jt-no-desc">—</span>
							<?php endif; ?>
						</td>

						<!-- Action column: View + Apply -->
						<td class="ajem-jt-action">
							<a href="<?php echo $jt_url; ?>"
								class="ajem-btn ajem-btn-sm ajem-btn-primary ajem-btn-full">
								<?php esc_html_e( 'View Job', 'ai-job-employer-manager' ); ?>
							</a>
							<a href="<?php echo $jt_url; ?>?apply=1"
								class="ajem-btn ajem-btn-sm ajem-btn-outline ajem-btn-full">
								📝 <?php esc_html_e( 'Apply', 'ai-job-employer-manager' ); ?>
							</a>
						</td>

					</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

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
