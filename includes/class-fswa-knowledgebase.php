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
			// Single article with known category: cat-{catId}-art-{artId}
			self::render_article( (int) $m[1], (int) $m[2], $api, $mailbox_id );
		} elseif ( preg_match( '/^art-(\d+)$/', $value, $m ) ) {
			// Single article without category context (e.g. from search results)
			self::render_article_by_id( (int) $m[1], $api, $mailbox_id );
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

		$all = self::unwrap( $result, 'categories' );

		// Only show root-level categories on the home page; subcategories are
		// displayed when their parent category page is opened.
		$categories = array_values( array_filter( $all, function ( array $cat ): bool {
			return self::get_parent_id( $cat ) === 0;
		} ) );

		// If every category has no parent info fall back to the full list so
		// we never show an empty home page.
		if ( empty( $categories ) ) {
			$categories = $all;
		}

		FSWA_MyAccount::load_template( 'myaccount/kb-home.php', compact( 'categories' ) );
	}

	private static function render_category( int $category_id, FSWA_API $api, int $mailbox_id ): void {
		$result = $api->get_kb_category( $mailbox_id, $category_id );

		if ( is_wp_error( $result ) ) {
			self::maybe_show_unavailable( $result );
			return;
		}

		$data     = self::unwrap( $result );

		// Category metadata may be embedded at the top level OR as a nested
		// 'category' key. Extract it first so we can also check for articles
		// nested inside it (some module versions return articles there).
		$category = $data['category'] ?? null;

		// Articles may be at top level, under 'docs', nested inside the
		// category object, or the response may be a plain array.
		$articles = $data['articles']
			?? $data['docs']
			?? ( null !== $category ? ( $category['articles'] ?? $category['docs'] ?? null ) : null )
			?? ( isset( $data[0] ) ? $data : [] );

		// Prefer subcategories embedded in the category endpoint response —
		// the updated EcomGraduates module includes a 'subcategories' array
		// directly on the category object, saving a second API call.
		$embedded_subs = $data['children']
			?? $data['subcategories']
			?? $data['subCategories']
			?? ( null !== $category ? ( $category['children'] ?? $category['subcategories'] ?? null ) : null )
			?? null;

		$subcategories = ( null !== $embedded_subs && is_array( $embedded_subs ) )
			? array_values( $embedded_subs )
			: [];

		// If subcategories weren't embedded (older module versions) or category
		// metadata is still missing, fetch the full categories list and resolve both.
		$cats_result  = null;
		$all_cats     = [];
		if ( empty( $subcategories ) || null === $category ) {
			$cats_result = $api->get_kb_categories( $mailbox_id );
			if ( ! is_wp_error( $cats_result ) ) {
				$all_cats = self::unwrap( $cats_result, 'categories' );

				if ( null === $category ) {
					foreach ( $all_cats as $c ) {
						if ( (int) ( $c['id'] ?? 0 ) === $category_id ) {
							$category = $c;
							break;
						}
					}
				}

				if ( empty( $subcategories ) ) {
					foreach ( $all_cats as $c ) {
						if ( self::get_parent_id( $c ) === $category_id ) {
							$subcategories[] = $c;
						}
					}
				}
			}
		}

		// Admin-only debug panel: helps diagnose why subcategories are not showing.
		if ( empty( $subcategories ) && current_user_can( 'manage_woocommerce' ) ) {
			echo '<details style="margin:8px 0;font-size:12px;border:1px dashed #ccc;padding:6px;">'
				. '<summary style="cursor:pointer;color:#666;">⚙ FSWA debug — category API response (visible to admins only)</summary>'
				. '<p style="margin:4px 0;color:#666;">Category endpoint response:</p>'
				. '<pre style="overflow:auto;max-height:300px;background:#f6f8fa;padding:6px;">'
				. esc_html( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) )
				. '</pre>'
				. '<p style="margin:4px 0;color:#666;">All-categories endpoint (first 3 items shown, check for parent_id/parentId field):</p>'
				. '<pre style="overflow:auto;max-height:300px;background:#f6f8fa;padding:6px;">'
				. esc_html( wp_json_encode( array_slice( $all_cats, 0, 3 ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) )
				. '</pre>'
				. '</details>';
		}

		// The KB module API does not paginate category articles.
		$page        = 1;
		$total_pages = 1;

		FSWA_MyAccount::load_template( 'myaccount/kb-category.php', compact(
			'category',
			'articles',
			'subcategories',
			'page',
			'total_pages',
			'category_id'
		) );
	}

	/**
	 * Render an article when the category ID is not known (e.g. from a search result).
	 *
	 * Tries a direct /articles/{id} endpoint first (not all modules support it),
	 * then falls back to scanning every category until the article is found.
	 */
	private static function render_article_by_id( int $article_id, FSWA_API $api, int $mailbox_id ): void {
		// --- Attempt 1: direct endpoint (EcomGraduates-style). ---
		$result = $api->get_kb_article_direct( $mailbox_id, $article_id );

		if ( ! is_wp_error( $result ) ) {
			$data = self::unwrap( $result );
			// Some modules nest the article under an 'article' key.
			$article     = ( isset( $data['article'] ) && is_array( $data['article'] ) )
				? $data['article']
				: $data;
			$category_id = 0;
			$category    = null;
		} else {
			// --- Attempt 2: scan all categories for the article. ---
			$cats_result = $api->get_kb_categories( $mailbox_id );
			if ( is_wp_error( $cats_result ) ) {
				self::maybe_show_unavailable( $cats_result );
				return;
			}

			$article     = null;
			$category_id = 0;
			$category    = null;

			foreach ( self::unwrap( $cats_result, 'categories' ) as $cat ) {
				$cid = (int) ( $cat['id'] ?? 0 );
				if ( ! $cid ) {
					continue;
				}

				$cat_result = $api->get_kb_category( $mailbox_id, $cid );
				if ( is_wp_error( $cat_result ) ) {
					continue;
				}

				$data     = self::unwrap( $cat_result );
				$cat_meta = $data['category'] ?? null;
				$articles = $data['articles']
					?? $data['docs']
					?? ( null !== $cat_meta ? ( $cat_meta['articles'] ?? $cat_meta['docs'] ?? null ) : null )
					?? ( isset( $data[0] ) ? $data : [] );

				foreach ( $articles as $a ) {
					if ( (int) ( $a['id'] ?? 0 ) === $article_id ) {
						$article     = $a;
						$category_id = $cid;
						$category    = $cat;
						break 2;
					}
				}
			}

			if ( null === $article ) {
				echo '<p class="fswa-notice fswa-notice--error">'
					. esc_html__( 'Article not found.', 'fswa' )
					. '</p>';
				return;
			}
		}

		FSWA_MyAccount::load_template( 'myaccount/kb-article.php', compact(
			'article',
			'article_id',
			'category',
			'category_id'
		) );
	}

	private static function render_article( int $category_id, int $article_id, FSWA_API $api, int $mailbox_id ): void {
		// Try the dedicated single-article endpoint (EcomGraduates module).
		$article_result = $api->get_kb_article( $mailbox_id, $category_id, $article_id );

		$category = null;

		if ( ! is_wp_error( $article_result ) ) {
			$data    = self::unwrap( $article_result );
			// Some modules nest the article under an 'article' key.
			$article = ( isset( $data['article'] ) && is_array( $data['article'] ) )
				? $data['article']
				: $data;
			// The updated module includes the category object in the same response —
			// use it for the breadcrumb to avoid a second API call.
			if ( isset( $data['category'] ) && is_array( $data['category'] ) ) {
				$category = $data['category'];
			}
		} else {
			// Fall back: load the category and find the article in the list.
			// This is compatible with the jtorvald module (2-endpoint version).
			$cat_result = $api->get_kb_category( $mailbox_id, $category_id );
			if ( is_wp_error( $cat_result ) ) {
				self::maybe_show_unavailable( $cat_result );
				return;
			}

			$data     = self::unwrap( $cat_result );
			$cat_meta = $data['category'] ?? null;
			$articles = $data['articles']
				?? $data['docs']
				?? ( null !== $cat_meta ? ( $cat_meta['articles'] ?? $cat_meta['docs'] ?? null ) : null )
				?? ( isset( $data[0] ) ? $data : [] );
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

			$category = $cat_meta;
		}

		// If category metadata still missing (e.g. article endpoint returned no
		// category object and the fallback path wasn't taken), fetch it now.
		if ( null === $category && $category_id ) {
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
		// Use 'fswa_q' instead of WordPress's built-in 's' to avoid redirect_canonical()
		// treating KB search pages as WordPress blog-search pages and redirecting away.
		$query    = sanitize_text_field( wp_unslash( $_GET['fswa_q'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$articles = [];
		$raw_result = null;

		if ( '' !== $query ) {
			$result     = $api->search_kb( $mailbox_id, $query );
			$raw_result = $result; // keep for admin debug panel
			if ( ! is_wp_error( $result ) ) {
				$data     = self::unwrap( $result );
				$articles = $data['articles']
					?? $data['docs']
					?? $data['results']
					?? $data['hits']
					?? $data['items']
					?? ( isset( $data[0] ) ? $data : [] );
			} else {
				// Surface API errors as an admin notice to aid debugging.
				if ( current_user_can( 'manage_woocommerce' ) ) {
					echo '<p class="fswa-notice fswa-notice--warning">'
						. esc_html( $result->get_error_message() )
						. '</p>';
				}
			}
		}

		// Admin-only debug panel: shows the raw API response so the correct
		// articles key name can be identified when search returns no results.
		if ( '' !== $query && empty( $articles ) && current_user_can( 'manage_woocommerce' ) ) {
			if ( null !== $raw_result && ! is_wp_error( $raw_result ) ) {
				echo '<details style="margin:8px 0;font-size:12px;border:1px dashed #ccc;padding:6px;">'
					. '<summary style="cursor:pointer;color:#666;">⚙ FSWA debug — search API response (visible to admins only)</summary>'
					. '<pre style="overflow:auto;max-height:300px;background:#f6f8fa;padding:6px;">'
					. esc_html( wp_json_encode( $raw_result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) )
					. '</pre>'
					. '</details>';
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
	 * Extract the parent category ID from a category object.
	 *
	 * Different KB API modules use different field names:
	 *   • parentId  (integer, EcomGraduates module)
	 *   • parent_id (integer, some variants)
	 *   • parent    (may be an integer, or an object/array with an 'id' key)
	 *
	 * Returns 0 when the category is a root (top-level) category or when no
	 * parent information is available.
	 *
	 * @param  array $category  Category object from the API.
	 * @return int  Parent category ID, or 0.
	 */
	private static function get_parent_id( array $category ): int {
		// Check well-known API parent fields. Only trust the value when it is
		// non-zero — a zero/null means the API returned no real parent info, so
		// we still fall through to the manually configured hierarchy map.
		foreach ( [ 'parentId', 'parent_id', 'parentCategoryId', 'parent_category_id' ] as $key ) {
			if ( array_key_exists( $key, $category ) ) {
				$val       = $category[ $key ];
				$parent_id = is_array( $val ) ? (int) ( $val['id'] ?? 0 ) : (int) $val;
				if ( $parent_id !== 0 ) {
					return $parent_id;
				}
				break; // Field exists but is 0/null — skip remaining keys, check manual map.
			}
		}
		if ( array_key_exists( 'parent', $category ) ) {
			$parent    = $category['parent'];
			$parent_id = is_array( $parent ) ? (int) ( $parent['id'] ?? 0 ) : (int) $parent;
			if ( $parent_id !== 0 ) {
				return $parent_id;
			}
		}

		// Fallback: check the manually configured category hierarchy.
		// This is required when the API returns a flat list with no parent info,
		// or when it returns parentId: null for every category.
		$cat_id = (int) ( $category['id'] ?? 0 );
		if ( $cat_id ) {
			$maps = self::hierarchy_maps();
			if ( isset( $maps['parent_of'][ $cat_id ] ) ) {
				return $maps['parent_of'][ $cat_id ];
			}
		}

		return 0;
	}

	/**
	 * Parse the `fswa_kb_category_hierarchy` option into two lookup maps.
	 *
	 * Format (one line per parent):  parent_id:child_id,child_id,...
	 * Example:                       1:2,3,9
	 *
	 * @return array{children_of: array<int,int[]>, parent_of: array<int,int>}
	 */
	private static function hierarchy_maps(): array {
		static $maps = null;
		if ( null !== $maps ) {
			return $maps;
		}

		$maps = [ 'children_of' => [], 'parent_of' => [] ];
		$raw  = (string) get_option( 'fswa_kb_category_hierarchy', '' );

		foreach ( explode( "\n", $raw ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || false === strpos( $line, ':' ) ) {
				continue;
			}
			[ $parent_str, $children_str ] = explode( ':', $line, 2 );
			$parent_id = (int) trim( $parent_str );
			if ( ! $parent_id ) {
				continue;
			}
			foreach ( explode( ',', $children_str ) as $child_str ) {
				$child_id = (int) trim( $child_str );
				if ( $child_id ) {
					$maps['children_of'][ $parent_id ][] = $child_id;
					$maps['parent_of'][ $child_id ]       = $parent_id;
				}
			}
		}

		return $maps;
	}

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
		} elseif ( 500 === $status ) {
			// A 500 is often a database error (e.g. missing column after a
			// module update whose migration has not been run yet).
			// Show a generic message to regular users; give admins the detail.
			echo '<p class="fswa-notice fswa-notice--error">'
				. esc_html__( 'The knowledge base is temporarily unavailable. Please try again later.', 'fswa' )
				. '</p>';
			if ( current_user_can( 'manage_woocommerce' ) ) {
				echo '<p class="fswa-notice fswa-notice--warning" style="font-size:12px;">'
					. esc_html__( 'Admin info: FreeScout returned a 500 error. If you recently updated the KnowledgeBaseApiModule, run: php artisan module:migrate KnowledgeBaseApiModule', 'fswa' )
					. '<br><code>' . esc_html( $error->get_error_message() ) . '</code>'
					. '</p>';
			}
		} else {
			// For other unexpected errors, show the message to admins only;
			// regular users see a generic notice.
			if ( current_user_can( 'manage_woocommerce' ) ) {
				echo '<p class="fswa-notice fswa-notice--error">'
					. esc_html( $error->get_error_message() )
					. '</p>';
			} else {
				echo '<p class="fswa-notice fswa-notice--error">'
					. esc_html__( 'The knowledge base is temporarily unavailable. Please try again later.', 'fswa' )
					. '</p>';
			}
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
	 * Build an article URL when the parent category ID is unknown.
	 *
	 * Uses the art-{id} scheme which resolves the article server-side
	 * (see render_article_by_id). Use article_url() instead when the
	 * category ID is known, as it avoids the extra API lookup.
	 *
	 * @param int $article_id  Article ID.
	 */
	public static function article_url_by_id( int $article_id ): string {
		if ( null !== self::$shortcode_base ) {
			return add_query_arg( 'fswa_kb', 'art-' . $article_id, self::$shortcode_base );
		}
		return wc_get_account_endpoint_url( self::ENDPOINT ) . 'art-' . $article_id . '/';
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
			return $query ? add_query_arg( 'fswa_q', $query, $base ) : $base;
		}
		$base = wc_get_account_endpoint_url( self::ENDPOINT ) . 'search/';
		return $query ? add_query_arg( 'fswa_q', $query, $base ) : $base;
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
		} elseif ( preg_match( '/^art-(\d+)$/', $value, $m ) ) {
			self::render_article_by_id( (int) $m[1], $api, $mailbox_id );
		} elseif ( preg_match( '/^cat-(\d+)$/', $value, $m ) ) {
			self::render_category( (int) $m[1], $api, $mailbox_id );
		} else {
			self::render_home( $api, $mailbox_id );
		}
	}
}
