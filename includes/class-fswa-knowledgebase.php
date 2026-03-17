<?php
/**
 * WooCommerce My Account – Knowledge Base integration.
 *
 * Registers a "Knowledge Base" endpoint and renders:
 *  - Home: grid of all KB categories.
 *  - Category: paginated article list for one category.
 *  - Article: full article body with breadcrumb navigation.
 *  - Search: article results for a keyword query.
 *
 * Requires a FreeScout Knowledge Base API module:
 *   • jtorvald/freescout-knowledge-api
 *   • EcomGraduates/KnowledgeBaseApiModule  (recommended — adds single-article endpoint)
 *
 * URL scheme (relative to the My Account page):
 *  knowledge-base/                         → KB home
 *  knowledge-base/search/                  → Search results (?s=query)
 *  knowledge-base/cat-{id}/                → Category article list
 *  knowledge-base/cat-{catId}-art-{artId}/ → Single article
 */

defined( 'ABSPATH' ) || exit;

class FSWA_KnowledgeBase {

	const ENDPOINT = 'knowledge-base';

	// -------------------------------------------------------------------------
	// Shortcode context
	// When $shortcode_base is set the class builds ?fswa_kb=... URLs instead of
	// My-Account path URLs.  Set by FSWA_Shortcodes before rendering; cleared
	// afterwards.
	// -------------------------------------------------------------------------

	/** Base URL of the page that hosts the [fswa_kb] shortcode. */
	private static ?string $shortcode_base = null;

	/** Override for the "Still need help?" link on article pages. */
	private static string $shortcode_ticket_url = '';

	public static function set_shortcode_base( ?string $url ): void {
		self::$shortcode_base = $url;
	}

	public static function set_shortcode_ticket_url( string $url ): void {
		self::$shortcode_ticket_url = $url;
	}

	/** Reset all shortcode-context state. */
	public static function clear_shortcode_context(): void {
		self::$shortcode_base       = null;
		self::$shortcode_ticket_url = '';
	}

	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'add_endpoint' ] );
		add_filter( 'woocommerce_account_menu_items', [ __CLASS__, 'add_menu_item' ] );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', [ __CLASS__, 'render' ] );
	}

	public static function add_endpoint(): void {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
	}

	public static function add_menu_item( array $items ): array {
		if ( ! get_option( 'fswa_kb_enabled', 1 ) ) {
			return $items;
		}

		$label  = get_option( 'fswa_kb_menu_label' ) ?: __( 'Knowledge Base', 'fswa' );
		$logout = $items['customer-logout'] ?? null;
		unset( $items['customer-logout'] );
		$items[ self::ENDPOINT ] = esc_html( $label );
		if ( $logout ) {
			$items['customer-logout'] = $logout;
		}

		return $items;
	}

	// -------------------------------------------------------------------------
	// Dispatch
	// -------------------------------------------------------------------------

	public static function render(): void {
		if ( ! get_option( 'fswa_kb_enabled', 1 ) ) {
			echo '<p class="fswa-notice fswa-notice--info">'
				. esc_html__( 'The knowledge base is not available.', 'fswa' )
				. '</p>';
			return;
		}

		$api = FSWA_API::from_options();
		if ( ! $api ) {
			echo '<p class="fswa-notice fswa-notice--error">'
				. esc_html__( 'Knowledge base is not available at the moment. Please try again later.', 'fswa' )
				. '</p>';
			return;
		}

		$mailbox_id = self::get_mailbox_id();
		if ( ! $mailbox_id ) {
			echo '<p class="fswa-notice fswa-notice--warning">'
				. esc_html__( 'Knowledge base mailbox is not configured. Please set the KB Mailbox ID under WooCommerce → FreeScout.', 'fswa' )
				. '</p>';
			return;
		}

		$value = (string) get_query_var( self::ENDPOINT );

		if ( 'search' === $value ) {
			self::render_search( $api, $mailbox_id );
		} elseif ( preg_match( '/^cat-(\d+)-art-(\d+)$/', $value, $m ) ) {
			// Single article: cat-{catId}-art-{artId}
			self::render_article( (int) $m[1], (int) $m[2], $api, $mailbox_id );
		} elseif ( str_starts_with( $value, 'cat-' ) ) {
			self::render_category( (int) substr( $value, 4 ), $api, $mailbox_id );
		} else {
			self::render_home( $api, $mailbox_id );
		}
	}

	// -------------------------------------------------------------------------
	// Views
	// -------------------------------------------------------------------------

	private static function render_home( FSWA_API $api, int $mailbox_id ): void {
		$result = $api->get_kb_categories( $mailbox_id );

		if ( is_wp_error( $result ) ) {
			self::maybe_show_unavailable( $result );
			return;
		}

		$categories = self::unwrap( $result, 'categories' );

		FSWA_MyAccount::load_template( 'myaccount/kb-home.php', compact( 'categories' ) );
	}

	private static function render_category( int $category_id, FSWA_API $api, int $mailbox_id ): void {
		$result = $api->get_kb_category( $mailbox_id, $category_id );

		if ( is_wp_error( $result ) ) {
			self::maybe_show_unavailable( $result );
			return;
		}

		$data     = self::unwrap( $result );
		$articles = $data['articles'] ?? ( isset( $data[0] ) ? $data : [] );

		// Category metadata may be embedded; otherwise look it up from the list.
		$category = $data['category'] ?? null;
		if ( null === $category ) {
			$cats_result = $api->get_kb_categories( $mailbox_id );
			if ( ! is_wp_error( $cats_result ) ) {
				foreach ( self::unwrap( $cats_result, 'categories' ) as $c ) {
					if ( (int) ( $c['id'] ?? 0 ) === $category_id ) {
						$category = $c;
						break;
					}
				}
			}
		}

		// The KB module API does not paginate category articles.
		$page        = 1;
		$total_pages = 1;

		FSWA_MyAccount::load_template( 'myaccount/kb-category.php', compact(
			'category',
			'articles',
			'page',
			'total_pages',
			'category_id'
		) );
	}

	private static function render_article( int $category_id, int $article_id, FSWA_API $api, int $mailbox_id ): void {
		// Try the dedicated single-article endpoint (EcomGraduates module).
		$article_result = $api->get_kb_article( $mailbox_id, $category_id, $article_id );

		if ( ! is_wp_error( $article_result ) ) {
			$article = self::unwrap( $article_result );
		} else {
			// Fall back: load the category and find the article in the list.
			// This is compatible with the jtorvald module (2-endpoint version).
			$cat_result = $api->get_kb_category( $mailbox_id, $category_id );
			if ( is_wp_error( $cat_result ) ) {
				self::maybe_show_unavailable( $cat_result );
				return;
			}

			$data     = self::unwrap( $cat_result );
			$articles = $data['articles'] ?? ( isset( $data[0] ) ? $data : [] );
			$article  = null;

			foreach ( $articles as $a ) {
				if ( (int) ( $a['id'] ?? 0 ) === $article_id ) {
					$article = $a;
					break;
				}
			}

			if ( null === $article ) {
				echo '<p class="fswa-notice fswa-notice--error">'
					. esc_html__( 'Article not found.', 'fswa' )
					. '</p>';
				return;
			}
		}

		// Fetch category metadata for the breadcrumb.
		$category = null;
		if ( $category_id ) {
			$cats_result = $api->get_kb_categories( $mailbox_id );
			if ( ! is_wp_error( $cats_result ) ) {
				foreach ( self::unwrap( $cats_result, 'categories' ) as $c ) {
					if ( (int) ( $c['id'] ?? 0 ) === $category_id ) {
						$category = $c;
						break;
					}
				}
			}
		}

		FSWA_MyAccount::load_template( 'myaccount/kb-article.php', compact(
			'article',
			'article_id',
			'category',
			'category_id'
		) );
	}

	private static function render_search( FSWA_API $api, int $mailbox_id ): void {
		$query    = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$articles = [];

		if ( '' !== $query ) {
			$result   = $api->search_kb( $mailbox_id, $query );
			if ( ! is_wp_error( $result ) ) {
				$data     = self::unwrap( $result );
				$articles = $data['articles'] ?? ( isset( $data[0] ) ? $data : [] );
			}
		}

		// Search results don't paginate in the current KB API modules.
		$page        = 1;
		$total_pages = 1;

		FSWA_MyAccount::load_template( 'myaccount/kb-search.php', compact(
			'query',
			'articles',
			'page',
			'total_pages'
		) );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Configured KB mailbox ID (0 = not set).
	 */
	public static function get_mailbox_id(): int {
		return (int) get_option( 'fswa_kb_mailbox_id', 0 );
	}

	/**
	 * Unwrap the KB module API response to the useful payload.
	 *
	 * The EcomGraduates module wraps every response as:
	 *   { "success": true, "data": { ... } }
	 *
	 * The jtorvald module returns a plain array or object.
	 *
	 * @param  array  $response  Already-decoded response from FSWA_API.
	 * @param  string $key       Optional key to pull from the unwrapped data.
	 * @return array
	 */
	private static function unwrap( array $response, string $key = '' ): array {
		$data = $response;

		if ( isset( $response['success'] ) && array_key_exists( 'data', $response ) ) {
			$data = (array) $response['data'];
		}

		if ( '' !== $key ) {
			return (array) ( $data[ $key ] ?? [] );
		}

		return $data;
	}

	/**
	 * Show a user-friendly message based on the API error status code.
	 */
	private static function maybe_show_unavailable( WP_Error $error ): void {
		$status = (int) ( $error->get_error_data()['status'] ?? 0 );

		if ( 404 === $status ) {
			echo '<p class="fswa-notice fswa-notice--info">'
				. esc_html__( 'The knowledge base is not available. Please ensure a FreeScout Knowledge Base API module is installed and the KB Mailbox ID is configured correctly.', 'fswa' )
				. '</p>';
		} elseif ( 405 === $status ) {
			echo '<p class="fswa-notice fswa-notice--warning">'
				. esc_html__( 'The knowledge base API returned "Method Not Allowed" (405). Please verify the KB Mailbox ID setting and ensure a compatible Knowledge Base API module is active in FreeScout.', 'fswa' )
				. '</p>';
		} else {
			echo '<p class="fswa-notice fswa-notice--error">'
				. esc_html( $error->get_error_message() )
				. '</p>';
		}
	}

	// -------------------------------------------------------------------------
	// URL builders — context-aware (My Account path vs shortcode query-param)
	// -------------------------------------------------------------------------

	public static function home_url(): string {
		if ( null !== self::$shortcode_base ) {
			return self::$shortcode_base;
		}
		return wc_get_account_endpoint_url( self::ENDPOINT );
	}

	public static function category_url( int $id ): string {
		if ( null !== self::$shortcode_base ) {
			return add_query_arg( 'fswa_kb', 'cat-' . $id, self::$shortcode_base );
		}
		return wc_get_account_endpoint_url( self::ENDPOINT ) . 'cat-' . $id . '/';
	}

	/**
	 * Build an article URL.
	 *
	 * Both category_id and article_id are required because the KB API
	 * endpoint for a single article is nested under its category.
	 *
	 * @param int $category_id  Parent category ID.
	 * @param int $article_id   Article ID.
	 */
	public static function article_url( int $category_id, int $article_id ): string {
		if ( null !== self::$shortcode_base ) {
			return add_query_arg( 'fswa_kb', 'cat-' . $category_id . '-art-' . $article_id, self::$shortcode_base );
		}
		return wc_get_account_endpoint_url( self::ENDPOINT ) . 'cat-' . $category_id . '-art-' . $article_id . '/';
	}

	public static function search_url( string $query = '' ): string {
		if ( null !== self::$shortcode_base ) {
			$base = add_query_arg( 'fswa_kb', 'search', self::$shortcode_base );
			return $query ? add_query_arg( 's', rawurlencode( $query ), $base ) : $base;
		}
		$base = wc_get_account_endpoint_url( self::ENDPOINT ) . 'search/';
		return $query ? add_query_arg( 's', rawurlencode( $query ), $base ) : $base;
	}

	/**
	 * The `action` URL for the KB search <form>.
	 *
	 * In My Account mode this equals search_url() (path-based, the `?s` param
	 * is appended by the browser on GET submission).
	 *
	 * In shortcode mode a GET form discards the action's query string, so we
	 * return the bare page URL and let search_form_extra_fields() emit a hidden
	 * `fswa_kb=search` input instead.
	 */
	public static function search_form_action(): string {
		if ( null !== self::$shortcode_base ) {
			return self::$shortcode_base;
		}
		return wc_get_account_endpoint_url( self::ENDPOINT ) . 'search/';
	}

	/**
	 * Output any hidden inputs the search form needs.
	 * In shortcode mode this emits <input type="hidden" name="fswa_kb" value="search">
	 * so the GET submission preserves the routing parameter.
	 */
	public static function search_form_extra_fields(): void {
		if ( null !== self::$shortcode_base ) {
			echo '<input type="hidden" name="fswa_kb" value="search">';
		}
	}

	/**
	 * URL for the "Still need help? Open a ticket" link on article pages.
	 * Returns the shortcode-supplied URL when set, otherwise the My Account
	 * new-ticket URL.
	 */
	public static function ticket_url(): string {
		if ( '' !== self::$shortcode_ticket_url ) {
			return self::$shortcode_ticket_url;
		}
		return wc_get_account_endpoint_url( FSWA_MyAccount::ENDPOINT ) . 'new/';
	}

	// -------------------------------------------------------------------------
	// Shortcode dispatcher
	// -------------------------------------------------------------------------

	/**
	 * Render the appropriate KB view for the [fswa_kb] shortcode.
	 * Reads ?fswa_kb= instead of the WooCommerce endpoint query var.
	 * Call only after set_shortcode_base() has been called.
	 */
	public static function dispatch_shortcode( FSWA_API $api, int $mailbox_id ): void {
		$value = sanitize_text_field( wp_unslash( $_GET['fswa_kb'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( 'search' === $value ) {
			self::render_search( $api, $mailbox_id );
		} elseif ( preg_match( '/^cat-(\d+)-art-(\d+)$/', $value, $m ) ) {
			self::render_article( (int) $m[1], (int) $m[2], $api, $mailbox_id );
		} elseif ( preg_match( '/^cat-(\d+)$/', $value, $m ) ) {
			self::render_category( (int) $m[1], $api, $mailbox_id );
		} else {
			self::render_home( $api, $mailbox_id );
		}
	}
}
