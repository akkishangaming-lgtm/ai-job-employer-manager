<?php
/**
 * Single Job Page Template — renders a job detail page inside the active theme.
 *
 * WordPress routes /jobs/{slug} here via the `template_include` filter
 * in AJEM_Public_Dashboard::filter_job_template().  By using get_header() /
 * get_footer() the active theme provides the full page chrome (navigation,
 * sidebars, footer widgets, etc.) and all enqueued CSS/JS are output normally
 * inside wp_head() / wp_footer().
 *
 * @global object $ajem_current_job  Job row populated by filter_job_template().
 *
 * @package AI_Job_Employer_Manager
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $ajem_current_job;

// Bail gracefully if the job wasn't set (should never happen in normal flow).
if ( ! $ajem_current_job ) {
	wp_redirect( home_url( '/' ) );
	exit;
}

get_header();
?>

<div id="ajem-single-job-wrap" class="ajem-single-job-page-wrap">
	<?php
	echo AJEM_Public_Dashboard::render_job_detail_for_job( $ajem_current_job ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?>
</div><!-- /#ajem-single-job-wrap -->

<?php
get_footer();
