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
 * URL scheme (all relative to the My Account page):
 *  knowledge-base/            → KB home
 *  knowledge-base/search/     → Search results (?s=query)
 *  knowledge-base/cat-{id}/   → Category article list
 *  knowledge-base/{id}/       → Single article
 */

defined( 'ABSPATH' ) || exit;

class FSWA_KnowledgeBase {

	const ENDPOINT = 'knowledge-base';

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

		$label  = get_option( 'fswa_kb_menu_label', __( 'Knowledge Base', 'fswa' ) );
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

		$value = get_query_var( self::ENDPOINT );

		if ( 'search' === $value ) {
			self::render_search( $api );
		} elseif ( str_starts_with( (string) $value, 'cat-' ) ) {
			self::render_category( (int) substr( $value, 4 ), $api );
		} elseif ( is_numeric( $value ) && $value > 0 ) {
			self::render_article( (int) $value, $api );
		} else {
			self::render_home( $api );
		}
	}

	// -------------------------------------------------------------------------
	// Views
	// -------------------------------------------------------------------------

	private static function render_home( FSWA_API $api ): void {
		$result = $api->get_kb_categories();

		if ( is_wp_error( $result ) ) {
			self::maybe_show_unavailable( $result );
			return;
		}

		$categories = $result['_embedded']['categories'] ?? [];

		FSWA_MyAccount::load_template( 'myaccount/kb-home.php', compact( 'categories' ) );
	}

	private static function render_category( int $category_id, FSWA_API $api ): void {
		$category = $api->get_kb_category( $category_id );
		if ( is_wp_error( $category ) ) {
			self::maybe_show_unavailable( $category );
			return;
		}

		$page     = max( 1, (int) ( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$per_page = (int) get_option( 'fswa_kb_per_page', 15 );
		$articles_result = $api->get_kb_articles( $category_id, '', $page, $per_page );

		$articles    = ! is_wp_error( $articles_result ) ? ( $articles_result['_embedded']['articles'] ?? [] ) : [];
		$total_pages = ! is_wp_error( $articles_result ) ? (int) ( $articles_result['page']['totalPages'] ?? 1 )  : 1;

		FSWA_MyAccount::load_template( 'myaccount/kb-category.php', compact(
			'category',
			'articles',
			'page',
			'total_pages',
			'category_id'
		) );
	}

	private static function render_article( int $article_id, FSWA_API $api ): void {
		$article = $api->get_kb_article( $article_id );
		if ( is_wp_error( $article ) ) {
			self::maybe_show_unavailable( $article );
			return;
		}

		// Load the parent category for the breadcrumb (best-effort).
		$category    = null;
		$category_id = (int) ( $article['categoryId'] ?? 0 );
		if ( $category_id ) {
			$cat_result = $api->get_kb_category( $category_id );
			$category   = is_wp_error( $cat_result ) ? null : $cat_result;
		}

		FSWA_MyAccount::load_template( 'myaccount/kb-article.php', compact(
			'article',
			'article_id',
			'category',
			'category_id'
		) );
	}

	private static function render_search( FSWA_API $api ): void {
		$query    = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$page     = max( 1, (int) ( $_GET['paged'] ?? 1 ) );               // phpcs:ignore WordPress.Security.NonceVerification
		$per_page = (int) get_option( 'fswa_kb_per_page', 15 );

		$articles    = [];
		$total_pages = 1;

		if ( '' !== $query ) {
			$result      = $api->get_kb_articles( 0, $query, $page, $per_page );
			$articles    = ! is_wp_error( $result ) ? ( $result['_embedded']['articles'] ?? [] ) : [];
			$total_pages = ! is_wp_error( $result ) ? (int) ( $result['page']['totalPages'] ?? 1 )  : 1;
		}

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
	 * Show a user-friendly message when the Docs module is unavailable (404)
	 * or return a generic error for other failures.
	 */
	private static function maybe_show_unavailable( WP_Error $error ): void {
		$status = (int) ( $error->get_error_data()['status'] ?? 0 );

		if ( 404 === $status ) {
			echo '<p class="fswa-notice fswa-notice--info">'
				. esc_html__( 'The knowledge base is not available. Please ensure the FreeScout Docs module is installed and enabled.', 'fswa' )
				. '</p>';
		} else {
			echo '<p class="fswa-notice fswa-notice--error">'
				. esc_html( $error->get_error_message() )
				. '</p>';
		}
	}

	/**
	 * Convenience URL builders used in templates.
	 */
	public static function home_url(): string {
		return wc_get_account_endpoint_url( self::ENDPOINT );
	}

	public static function category_url( int $id ): string {
		return wc_get_account_endpoint_url( self::ENDPOINT ) . 'cat-' . $id . '/';
	}

	public static function article_url( int $id ): string {
		return wc_get_account_endpoint_url( self::ENDPOINT ) . $id . '/';
	}

	public static function search_url( string $query = '' ): string {
		$base = wc_get_account_endpoint_url( self::ENDPOINT ) . 'search/';
		return $query ? add_query_arg( 's', rawurlencode( $query ), $base ) : $base;
	}
}
