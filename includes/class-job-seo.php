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
 * SEO meta tags for job detail pages, provides a dynamic XML job sitemap,
 * and injects SEO tags for the public job listings page.
 *
 * @since 1.0.0
 */
class AJEM_Job_SEO {

	/**
	 * Per-request cache for the current job object.
	 *
	 * @var object|null|false  null = not fetched yet; false = no job found.
	 */
	private object|null|false $job_cache = null;

	/**
	 * Per-request cache of employer objects keyed by employer_id.
	 *
	 * @var array<int, object|null>
	 */
	private array $employer_cache = array();

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_head', array( $this, 'output_job_schema' ) );
		add_action( 'wp_head', array( $this, 'output_job_meta_tags' ) );
		add_action( 'wp_head', array( $this, 'output_listing_seo' ) );
		add_filter( 'document_title_parts', array( $this, 'filter_document_title_parts' ) );
		add_action( 'template_redirect', array( $this, 'handle_sitemap_request' ) );
		add_action( 'ajem_job_created', array( $this, 'ping_search_engines' ) );
	}

	/**
	 * Output Schema.org JobPosting + BreadcrumbList JSON-LD for the current job detail page.
	 *
	 * @return void
	 */
	public function output_job_schema(): void {
		$job = $this->get_current_job();
		if ( ! $job ) {
			return;
		}

		$employer        = $this->get_employer( (int) $job->employer_id );
		$employment_type = $this->get_schema_employment_type( $job->job_type ?? '' );
		$job_url         = site_url( '/jobs/' . $job->job_slug );

		$schema = array(
			'@context'           => 'https://schema.org',
			'@type'              => 'JobPosting',
			'title'              => $job->job_title,
			'description'        => wp_strip_all_tags( $job->job_description ?? '' ),
			'url'                => $job_url,
			'identifier'         => array(
				'@type' => 'PropertyValue',
				'name'  => get_bloginfo( 'name' ),
				'value' => (string) $job->id,
			),
			'datePosted'         => gmdate( 'Y-m-d', strtotime( $job->created_at ) ),
			'validThrough'       => $job->application_deadline
				? $job->application_deadline . 'T23:59:59'
				: gmdate( 'Y-m-d', strtotime( '+30 days' ) ),
			'employmentType'     => $employment_type,
			'directApply'        => true,
			'hiringOrganization' => array(
				'@type'  => 'Organization',
				'name'   => $employer ? $employer->company_name : '',
				'sameAs' => $employer && $employer->company_website ? $employer->company_website : '',
				'logo'   => $employer && $employer->company_logo ? $employer->company_logo : '',
			),
			'jobLocation'        => array(
				'@type'   => 'Place',
				'address' => array(
					'@type'           => 'PostalAddress',
					'addressLocality' => $job->city ?? '',
					'addressRegion'   => $job->state ?? '',
					'addressCountry'  => $job->country ?? '',
				),
			),
		);

		// Geo coordinates.
		if ( ! empty( $job->latitude ) && ! empty( $job->longitude ) ) {
			$schema['jobLocation']['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => (float) $job->latitude,
				'longitude' => (float) $job->longitude,
			);
		}

		// Industry.
		if ( ! empty( $job->industry ) ) {
			$schema['industry'] = $job->industry;
		}

		// Skills.
		$skills_array = json_decode( $job->required_skills ?? '[]', true ) ?? array();
		if ( ! empty( $skills_array ) ) {
			$schema['skills'] = implode( ', ', array_map( 'sanitize_text_field', $skills_array ) );
		}

		// Education requirements.
		if ( ! empty( $job->education_required ) ) {
			$schema['educationRequirements'] = array(
				'@type'              => 'EducationalOccupationalCredential',
				'credentialCategory' => sanitize_text_field( $job->education_required ),
			);
		}

		// Experience requirements.
		if ( ! empty( $job->experience_required ) ) {
			$schema['experienceRequirements'] = array(
				'@type'       => 'OccupationalExperienceRequirements',
				'description' => sanitize_text_field( $job->experience_required ),
			);
		}

		// Salary.
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

		echo '<script type="application/ld+json">'
			. wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
			. '</script>' . "\n";

		// BreadcrumbList schema.
		$ajem_settings    = get_option( 'ajem_settings', array() );
		$listings_page_id = ! empty( $ajem_settings['listings_page_id'] ) ? (int) $ajem_settings['listings_page_id'] : 0;
		$listings_url     = $listings_page_id > 0 ? get_permalink( $listings_page_id ) : '';

		$items = array(
			array(
				'@type'    => 'ListItem',
				'position' => 1,
				'name'     => __( 'Home', 'ai-job-employer-manager' ),
				'item'     => home_url( '/' ),
			),
		);

		if ( $listings_url ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => 2,
				'name'     => __( 'Jobs', 'ai-job-employer-manager' ),
				'item'     => $listings_url,
			);
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => 3,
				'name'     => $job->job_title,
				'item'     => $job_url,
			);
		} else {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => 2,
				'name'     => $job->job_title,
				'item'     => $job_url,
			);
		}

		$breadcrumb = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $items,
		);

		echo '<script type="application/ld+json">'
			. wp_json_encode( $breadcrumb, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
			. '</script>' . "\n";
	}

	/**
	 * Output comprehensive SEO meta tags for the single job detail page.
	 *
	 * Emits: description, keywords, canonical, robots, Open Graph, Twitter Card.
	 * Also hooks `document_title_parts` to set the browser tab title (WP ≥ 4.4).
	 *
	 * @return void
	 */
	public function output_job_meta_tags(): void {
		$job = $this->get_current_job();
		if ( ! $job ) {
			return;
		}

		$employer    = $this->get_employer( (int) $job->employer_id );
		$company     = $employer ? $employer->company_name : '';
		$title       = $job->job_title . ( $company ? ' at ' . $company : '' );
		$description = wp_trim_words( wp_strip_all_tags( $job->job_description ?? '' ), 30, '...' );
		$canonical   = site_url( '/jobs/' . $job->job_slug );
		$og_image    = $employer && $employer->company_logo ? $employer->company_logo : '';
		$site_name   = get_bloginfo( 'name' );
		$locale      = str_replace( '-', '_', get_bloginfo( 'language' ) );

		// Build keyword list from skills + industry + city.
		$skills_array = json_decode( $job->required_skills ?? '[]', true ) ?? array();
		$keyword_parts = array_filter(
			array_merge(
				array_map( 'sanitize_text_field', $skills_array ),
				array_filter( array( $job->industry ?? '', $job->city ?? '', $job->job_title ) )
			)
		);
		$keywords = implode( ', ', array_unique( $keyword_parts ) );

		// ── Standard meta ───────────────────────────────────────────────────
		echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
		if ( $keywords ) {
			echo '<meta name="keywords" content="' . esc_attr( $keywords ) . '">' . "\n";
		}
		echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
		echo '<meta name="robots" content="index, follow">' . "\n";

		// ── Open Graph ──────────────────────────────────────────────────────
		echo '<meta property="og:type" content="website">' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( $description ) . '">' . "\n";
		echo '<meta property="og:url" content="' . esc_url( $canonical ) . '">' . "\n";
		echo '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '">' . "\n";
		echo '<meta property="og:locale" content="' . esc_attr( $locale ) . '">' . "\n";
		if ( $og_image ) {
			echo '<meta property="og:image" content="' . esc_url( $og_image ) . '">' . "\n";
			echo '<meta property="og:image:alt" content="' . esc_attr( $company . ' logo' ) . '">' . "\n";
		}

		// ── Twitter Card ────────────────────────────────────────────────────
		echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
		echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '">' . "\n";
		echo '<meta name="twitter:description" content="' . esc_attr( $description ) . '">' . "\n";
		if ( $og_image ) {
			echo '<meta name="twitter:image" content="' . esc_url( $og_image ) . '">' . "\n";
			echo '<meta name="twitter:image:alt" content="' . esc_attr( $company . ' logo' ) . '">' . "\n";
		}
	}

	/**
	 * Filter `document_title_parts` (WP ≥ 4.4) to set the browser-tab / SEO
	 * title for both single job pages and the job listings page.
	 *
	 * @param array $title_parts Existing title parts.
	 * @return array
	 */
	public function filter_document_title_parts( array $title_parts ): array {
		$job = $this->get_current_job();

		if ( $job ) {
			$employer             = $this->get_employer( (int) $job->employer_id );
			$company              = $employer ? $employer->company_name : '';
			$title_parts['title'] = $job->job_title . ( $company ? ' at ' . $company : '' );
			return $title_parts;
		}

		// Listings page.
		global $post;
		if ( $post instanceof WP_Post && has_shortcode( $post->post_content, 'job_listings' ) ) {
			$keyword = sanitize_text_field( wp_unslash( $_GET['keyword'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$city    = sanitize_text_field( wp_unslash( $_GET['city'] ?? '' ) );    // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( $keyword && $city ) {
				$title_parts['title'] = sprintf(
					/* translators: 1: keyword, 2: city */
					__( '%1$s Jobs in %2$s', 'ai-job-employer-manager' ),
					$keyword,
					$city
				);
			} elseif ( $keyword ) {
				$title_parts['title'] = sprintf(
					/* translators: %s: keyword */
					__( '%s Jobs', 'ai-job-employer-manager' ),
					$keyword
				);
			} elseif ( $city ) {
				$title_parts['title'] = sprintf(
					/* translators: %s: city */
					__( 'Jobs in %s', 'ai-job-employer-manager' ),
					$city
				);
			} else {
				$title_parts['title'] = __( 'Job Listings', 'ai-job-employer-manager' );
			}
		}

		return $title_parts;
	}

	/**
	 * Output SEO meta tags + WebPage JSON-LD for the job listings page.
	 *
	 * Only fires when the current page contains the `[job_listings]` shortcode.
	 *
	 * @return void
	 */
	public function output_listing_seo(): void {
		// Single job pages are handled by output_job_meta_tags().
		if ( get_query_var( 'ajem_job_slug' ) ) {
			return;
		}

		global $post;
		if ( ! $post instanceof WP_Post || ! has_shortcode( $post->post_content, 'job_listings' ) ) {
			return;
		}

		$site_name = get_bloginfo( 'name' );
		$site_desc = get_bloginfo( 'description' );
		$locale    = str_replace( '-', '_', get_bloginfo( 'language' ) );
		$page_url  = get_permalink( $post->ID );

		// Build context-aware title and description from search params.
		$keyword = sanitize_text_field( wp_unslash( $_GET['keyword'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$city    = sanitize_text_field( wp_unslash( $_GET['city'] ?? '' ) );    // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $keyword && $city ) {
			$title       = sprintf(
				/* translators: 1: keyword, 2: city */
				__( '%1$s Jobs in %2$s', 'ai-job-employer-manager' ),
				$keyword,
				$city
			);
			$description = sprintf(
				/* translators: 1: keyword, 2: city */
				__( 'Find %1$s jobs in %2$s. Browse the latest openings and apply today on %3$s.', 'ai-job-employer-manager' ),
				$keyword,
				$city,
				$site_name
			);
		} elseif ( $keyword ) {
			$title       = sprintf(
				/* translators: %s: keyword */
				__( '%s Jobs', 'ai-job-employer-manager' ),
				$keyword
			);
			$description = sprintf(
				/* translators: 1: keyword, 2: site name */
				__( 'Browse %1$s job openings on %2$s. Find and apply for the best opportunities.', 'ai-job-employer-manager' ),
				$keyword,
				$site_name
			);
		} elseif ( $city ) {
			$title       = sprintf(
				/* translators: %s: city */
				__( 'Jobs in %s', 'ai-job-employer-manager' ),
				$city
			);
			$description = sprintf(
				/* translators: 1: city, 2: site name */
				__( 'Find jobs in %1$s on %2$s. Explore the latest openings and apply today.', 'ai-job-employer-manager' ),
				$city,
				$site_name
			);
		} else {
			/* translators: %s: site name */
			$title       = sprintf( __( 'Job Listings — %s', 'ai-job-employer-manager' ), $site_name );
			$description = $site_desc
				?: __( 'Browse all available job listings and find your next opportunity.', 'ai-job-employer-manager' );
		}

		// ── Standard meta ───────────────────────────────────────────────────
		echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
		echo '<link rel="canonical" href="' . esc_url( $page_url ) . '">' . "\n";
		echo '<meta name="robots" content="index, follow">' . "\n";

		// ── Open Graph ──────────────────────────────────────────────────────
		echo '<meta property="og:type" content="website">' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( $description ) . '">' . "\n";
		echo '<meta property="og:url" content="' . esc_url( $page_url ) . '">' . "\n";
		echo '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '">' . "\n";
		echo '<meta property="og:locale" content="' . esc_attr( $locale ) . '">' . "\n";

		// ── Twitter Card ────────────────────────────────────────────────────
		echo '<meta name="twitter:card" content="summary">' . "\n";
		echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '">' . "\n";
		echo '<meta name="twitter:description" content="' . esc_attr( $description ) . '">' . "\n";

		// ── WebPage + SearchAction JSON-LD ──────────────────────────────────
		$schema = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'WebPage',
			'name'        => $title,
			'description' => $description,
			'url'         => $page_url,
			'publisher'   => array(
				'@type' => 'Organization',
				'name'  => $site_name,
				'url'   => home_url( '/' ),
			),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => add_query_arg( 'keyword', '{search_term_string}', $page_url ),
				),
				'query-input' => 'required name=search_term_string',
			),
		);

		echo '<script type="application/ld+json">'
			. wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
			. '</script>' . "\n";
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
	 * Get the current job from the URL slug query var, with per-request caching.
	 *
	 * @return object|null Job row or null.
	 */
	private function get_current_job(): ?object {
		if ( $this->job_cache !== null ) {
			return $this->job_cache ?: null; // false means "already fetched, no job".
		}

		$slug = get_query_var( 'ajem_job_slug' );
		if ( ! $slug ) {
			$this->job_cache = false;
			return null;
		}

		$job_posting     = new AJEM_Job_Posting();
		$job             = $job_posting->get_by_slug( sanitize_text_field( $slug ) );
		$this->job_cache = $job ?? false;

		return $job;
	}

	/**
	 * Get employer profile by employer ID, with per-request caching.
	 *
	 * @param int $employer_id Employer ID.
	 * @return object|null
	 */
	private function get_employer( int $employer_id ): ?object {
		if ( array_key_exists( $employer_id, $this->employer_cache ) ) {
			return $this->employer_cache[ $employer_id ];
		}

		$profile                             = new AJEM_Employer_Profile();
		$this->employer_cache[ $employer_id ] = $profile->get_by_id( $employer_id );

		return $this->employer_cache[ $employer_id ];
	}
}
