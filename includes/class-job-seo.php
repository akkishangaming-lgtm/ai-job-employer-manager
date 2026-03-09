<?php
/**
 * Job SEO — Schema.org JobPosting JSON-LD, SEO meta tags, XML sitemap.
 *
 * @package AI_Job_Employer_Manager
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AJEM_Job_SEO
 *
 * Outputs Schema.org JobPosting JSON-LD structured data, generates
 * SEO meta tags for job detail pages, and provides a dynamic XML job sitemap.
 *
 * @since 1.0.0
 */
class AJEM_Job_SEO {

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_head', array( $this, 'output_job_schema' ) );
		add_action( 'wp_head', array( $this, 'output_job_meta_tags' ) );
		add_action( 'template_redirect', array( $this, 'handle_sitemap_request' ) );
		add_action( 'ajem_job_created', array( $this, 'ping_search_engines' ) );
	}

	/**
	 * Output Schema.org JobPosting JSON-LD for the current job detail page.
	 *
	 * @return void
	 */
	public function output_job_schema(): void {
		$job = $this->get_current_job();
		if ( ! $job ) {
			return;
		}

		$employer = $this->get_employer( (int) $job->employer_id );

		$employment_type = $this->get_schema_employment_type( $job->job_type ?? '' );

		$schema = array(
			'@context'           => 'https://schema.org',
			'@type'              => 'JobPosting',
			'title'              => esc_html( $job->job_title ),
			'description'        => wp_strip_all_tags( $job->job_description ?? '' ),
			'datePosted'         => gmdate( 'Y-m-d', strtotime( $job->created_at ) ),
			'validThrough'       => $job->application_deadline ?? gmdate( 'Y-m-d', strtotime( '+30 days' ) ),
			'employmentType'     => $employment_type,
			'hiringOrganization' => array(
				'@type'  => 'Organization',
				'name'   => $employer ? esc_html( $employer->company_name ) : '',
				'sameAs' => $employer ? esc_url( $employer->company_website ) : '',
				'logo'   => $employer ? esc_url( $employer->company_logo ) : '',
			),
			'jobLocation'        => array(
				'@type'   => 'Place',
				'address' => array(
					'@type'           => 'PostalAddress',
					'addressLocality' => esc_html( $job->city ?? '' ),
					'addressRegion'   => esc_html( $job->state ?? '' ),
					'addressCountry'  => esc_html( $job->country ?? '' ),
				),
			),
		);

		// Optional: salary.
		if ( ! empty( $job->salary_min ) || ! empty( $job->salary_max ) ) {
			$schema['baseSalary'] = array(
				'@type'    => 'MonetaryAmount',
				'currency' => $job->salary_currency ?? 'INR',
				'value'    => array(
					'@type'    => 'QuantitativeValue',
					'minValue' => (float) ( $job->salary_min ?? 0 ),
					'maxValue' => (float) ( $job->salary_max ?? 0 ),
					'unitText' => 'MONTH',
				),
			);
		}

		// Output safely — JSON_UNESCAPED_SLASHES is safe here as we esc_* above.
		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}

	/**
	 * Output SEO title and meta description for job detail pages.
	 *
	 * @return void
	 */
	public function output_job_meta_tags(): void {
		$job = $this->get_current_job();
		if ( ! $job ) {
			return;
		}

		$employer    = $this->get_employer( (int) $job->employer_id );
		$company     = $employer ? esc_attr( $employer->company_name ) : '';
		$title       = esc_attr( $job->job_title ) . ( $company ? ' at ' . $company : '' );
		$description = wp_trim_words( wp_strip_all_tags( $job->job_description ?? '' ), 30, '...' );

		// Override wp_title via filter.
		add_filter(
			'wp_title',
			function () use ( $title ) {
				return esc_html( $title );
			}
		);

		echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( $description ) . '">' . "\n";
		echo '<meta property="og:type" content="website">' . "\n";
		echo '<meta property="og:url" content="' . esc_url( site_url( '/jobs/' . $job->job_slug ) ) . '">' . "\n";

		if ( $employer && $employer->company_logo ) {
			echo '<meta property="og:image" content="' . esc_url( $employer->company_logo ) . '">' . "\n";
		}
	}

	/**
	 * Handle XML job sitemap request (ajem_sitemap=1 query var).
	 *
	 * @return void
	 */
	public function handle_sitemap_request(): void {
		if ( ! get_query_var( 'ajem_sitemap' ) ) {
			return;
		}

		header( 'Content-Type: application/xml; charset=utf-8' );

		global $wpdb;
		$table = $wpdb->prefix . 'job_listings';

		$jobs = $wpdb->get_results(
			"SELECT job_slug, updated_at, created_at, is_featured
			 FROM `{$table}`
			 WHERE status = 'active'
			 ORDER BY updated_at DESC
			 LIMIT 50000"
		);

		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		$now = time();

		foreach ( $jobs as $job ) {
			$url      = site_url( '/jobs/' . $job->job_slug );
			$lastmod  = gmdate( 'Y-m-d', strtotime( $job->updated_at ) );
			$age_days = ( $now - strtotime( $job->created_at ) ) / DAY_IN_SECONDS;

			// Priority: featured = 0.9, fresh (<7d) = 0.8, recent (<30d) = 0.6, old = 0.4.
			if ( $job->is_featured ) {
				$priority = '0.9';
			} elseif ( $age_days <= 7 ) {
				$priority = '0.8';
			} elseif ( $age_days <= 30 ) {
				$priority = '0.6';
			} else {
				$priority = '0.4';
			}

			echo "\t<url>\n";
			echo "\t\t<loc>" . esc_url( $url ) . "</loc>\n";
			echo "\t\t<lastmod>" . esc_html( $lastmod ) . "</lastmod>\n";
			echo "\t\t<changefreq>weekly</changefreq>\n";
			echo "\t\t<priority>" . esc_html( $priority ) . "</priority>\n";
			echo "\t</url>\n";
		}

		echo '</urlset>';
		exit;
	}

	/**
	 * Ping search engines when a new job is created (optional).
	 *
	 * @param int $job_id Newly created job ID.
	 * @return void
	 */
	public function ping_search_engines( int $job_id ): void {
		$sitemap_url = urlencode( site_url( '/job-sitemap.xml' ) );

		$ping_urls = array(
			'https://www.google.com/ping?sitemap=' . $sitemap_url,
			'https://www.bing.com/ping?sitemap=' . $sitemap_url,
		);

		foreach ( $ping_urls as $ping_url ) {
			wp_remote_get(
				$ping_url,
				array(
					'blocking'   => false,
					'user-agent' => 'WordPress/' . get_bloginfo( 'version' ),
				)
			);
		}
	}

	/**
	 * Convert AJEM job type to Schema.org employmentType value.
	 *
	 * @param string $job_type AJEM job type.
	 * @return string Schema.org employment type.
	 */
	private function get_schema_employment_type( string $job_type ): string {
		$map = array(
			'full_time'  => 'FULL_TIME',
			'part_time'  => 'PART_TIME',
			'internship' => 'INTERN',
			'contract'   => 'CONTRACTOR',
			'remote'     => 'TELECOMMUTE',
		);

		return $map[ $job_type ] ?? 'FULL_TIME';
	}

	/**
	 * Get the current job from the URL slug query var.
	 *
	 * @return object|null Job row or null.
	 */
	private function get_current_job(): ?object {
		$slug = get_query_var( 'ajem_job_slug' );
		if ( ! $slug ) {
			return null;
		}

		$job_posting = new AJEM_Job_Posting();
		return $job_posting->get_by_slug( sanitize_text_field( $slug ) );
	}

	/**
	 * Get employer profile by employer ID.
	 *
	 * @param int $employer_id Employer ID.
	 * @return object|null
	 */
	private function get_employer( int $employer_id ): ?object {
		$profile = new AJEM_Employer_Profile();
		return $profile->get_by_id( $employer_id );
	}
}
