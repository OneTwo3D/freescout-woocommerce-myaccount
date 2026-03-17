<?php
/**
 * WooCommerce My Account integration.
 *
 * Registers the "Support Tickets" endpoint and renders the list / detail views.
 */

defined( 'ABSPATH' ) || exit;

class FSWA_MyAccount {

	/** Endpoint slug used in the URL. */
	const ENDPOINT = 'support-tickets';

	public static function init(): void {
		// Register the rewrite endpoint.
		add_action( 'init', [ __CLASS__, 'add_endpoint' ] );

		// Add the menu item.
		add_filter( 'woocommerce_account_menu_items', [ __CLASS__, 'add_menu_item' ] );

		// Render content for the endpoint.
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', [ __CLASS__, 'render' ] );

		// Enqueue assets on My Account pages.
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
	}

	public static function add_endpoint(): void {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
	}

	public static function add_menu_item( array $items ): array {
		$label = get_option( 'fswa_menu_label', __( 'Support Tickets', 'fswa' ) );

		// Insert before "logout".
		$logout = $items['customer-logout'] ?? null;
		unset( $items['customer-logout'] );
		$items[ self::ENDPOINT ] = esc_html( $label );
		if ( $logout ) {
			$items['customer-logout'] = $logout;
		}

		return $items;
	}

	/**
	 * Dispatch to list or detail template.
	 */
	public static function render(): void {
		$api = FSWA_API::from_options();
		if ( ! $api ) {
			echo '<p class="fswa-notice fswa-notice--error">'
				. esc_html__( 'Support tickets are not available at the moment. Please try again later.', 'fswa' )
				. '</p>';
			return;
		}

		// The endpoint value may be a conversation ID (int) or empty.
		$endpoint_value = get_query_var( self::ENDPOINT );

		if ( $endpoint_value && 'new' !== $endpoint_value ) {
			// Detail view.
			self::render_ticket( (int) $endpoint_value, $api );
		} elseif ( 'new' === $endpoint_value ) {
			// New ticket form.
			self::render_new_ticket_form( $api );
		} else {
			// List view.
			self::render_ticket_list( $api );
		}
	}

	// -------------------------------------------------------------------------
	// Views
	// -------------------------------------------------------------------------

	private static function render_ticket_list( FSWA_API $api ): void {
		$user  = wp_get_current_user();
		$email = $user->user_email;

		$customer_id = $api->get_customer_id_by_email( $email );
		if ( is_wp_error( $customer_id ) ) {
			// No customer yet – show an empty state rather than an error.
			self::load_template( 'myaccount/tickets-empty.php', [
				'show_new' => (bool) get_option( 'fswa_allow_new_tickets', 1 ),
			] );
			return;
		}

		$page      = max( 1, (int) ( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$per_page  = (int) get_option( 'fswa_per_page', 10 );
		$result    = $api->get_conversations( $customer_id, $page, $per_page );

		if ( is_wp_error( $result ) ) {
			echo '<p class="fswa-notice fswa-notice--error">'
				. esc_html( $result->get_error_message() )
				. '</p>';
			return;
		}

		$conversations = $result['_embedded']['conversations'] ?? [];
		$total         = (int) ( $result['page']['totalCount'] ?? 0 );
		$total_pages   = (int) ( $result['page']['totalPages'] ?? 1 );

		self::load_template( 'myaccount/tickets.php', compact(
			'conversations',
			'page',
			'total',
			'total_pages',
			'per_page'
		) );
	}

	private static function render_ticket( int $conversation_id, FSWA_API $api ): void {
		$conversation = $api->get_conversation( $conversation_id );
		if ( is_wp_error( $conversation ) ) {
			echo '<p class="fswa-notice fswa-notice--error">'
				. esc_html( $conversation->get_error_message() )
				. '</p>';
			return;
		}

		// Security: make sure this conversation belongs to the current user.
		$user  = wp_get_current_user();
		$email = strtolower( $user->user_email );
		$conv_email = strtolower( $conversation['customer']['email'] ?? '' );

		if ( $email !== $conv_email ) {
			echo '<p class="fswa-notice fswa-notice--error">'
				. esc_html__( 'You do not have permission to view this ticket.', 'fswa' )
				. '</p>';
			return;
		}

		$threads_result = $api->get_threads( $conversation_id );
		$threads        = ! is_wp_error( $threads_result )
			? ( $threads_result['_embedded']['threads'] ?? [] )
			: [];

		self::load_template( 'myaccount/ticket-detail.php', compact(
			'conversation',
			'threads',
			'conversation_id'
		) );
	}

	private static function render_new_ticket_form( FSWA_API $api ): void {
		if ( ! get_option( 'fswa_allow_new_tickets', 1 ) ) {
			wp_safe_redirect( wc_get_account_endpoint_url( self::ENDPOINT ) );
			exit;
		}

		$mailboxes_result = $api->get_mailboxes();
		$mailboxes        = ! is_wp_error( $mailboxes_result )
			? ( $mailboxes_result['_embedded']['mailboxes'] ?? [] )
			: [];

		self::load_template( 'myaccount/ticket-new.php', compact( 'mailboxes' ) );
	}

	// -------------------------------------------------------------------------
	// Asset loading
	// -------------------------------------------------------------------------

	public static function enqueue_assets(): void {
		if ( ! is_account_page() ) {
			return;
		}

		wp_enqueue_style(
			'fswa-frontend',
			FSWA_PLUGIN_URL . 'assets/css/frontend.css',
			[],
			FSWA_VERSION
		);

		wp_enqueue_script(
			'fswa-frontend',
			FSWA_PLUGIN_URL . 'assets/js/frontend.js',
			[ 'jquery' ],
			FSWA_VERSION,
			true
		);

		wp_localize_script( 'fswa-frontend', 'fswa', [
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'fswa_nonce' ),
			'i18n'      => [
				'sending'     => __( 'Sending…', 'fswa' ),
				'send'        => __( 'Send Reply', 'fswa' ),
				'emptyReply'  => __( 'Please enter a message before sending.', 'fswa' ),
				'replyError'  => __( 'There was a problem sending your reply. Please try again.', 'fswa' ),
			],
		] );
	}

	// -------------------------------------------------------------------------
	// Template loader
	// -------------------------------------------------------------------------

	/**
	 * Load a template file, looking in the theme first then in the plugin.
	 *
	 * Themes can override templates by placing them in:
	 *   <theme>/fswa/myaccount/tickets.php   (etc.)
	 *
	 * @param string $template  Relative path inside the templates/ directory.
	 * @param array  $data      Variables extracted into the template scope.
	 */
	public static function load_template( string $template, array $data = [] ): void {
		$theme_file  = get_stylesheet_directory() . '/fswa/' . $template;
		$plugin_file = FSWA_PLUGIN_DIR . 'templates/' . $template;

		$file = file_exists( $theme_file ) ? $theme_file : $plugin_file;

		if ( ! file_exists( $file ) ) {
			/* translators: %s template file path */
			echo '<p>' . esc_html( sprintf( __( 'Template not found: %s', 'fswa' ), $template ) ) . '</p>';
			return;
		}

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		extract( $data, EXTR_SKIP );
		include $file;
	}
}
