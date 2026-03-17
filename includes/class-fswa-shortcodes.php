<?php
/**
 * Shortcodes for embedding links and forms anywhere on the site.
 *
 * [fswa_new_ticket_form]  – Full new-ticket form. Works for guests and logged-in users.
 * [fswa_tickets_link]     – Anchor link to the Support Tickets My Account page.
 * [fswa_kb_link]          – Anchor link to the Knowledge Base My Account page.
 * [fswa_tickets_url]      – Raw URL of the Support Tickets page (no HTML wrapper).
 * [fswa_kb_url]           – Raw URL of the Knowledge Base page (no HTML wrapper).
 *
 * Link shortcode attributes:
 *   text  – Link label. Defaults to the configured menu label in Settings.
 *   class – One or more CSS classes for the <a> tag, e.g. class="button".
 */

defined( 'ABSPATH' ) || exit;

class FSWA_Shortcodes {

	public static function init(): void {
		add_shortcode( 'fswa_new_ticket_form', [ __CLASS__, 'new_ticket_form' ] );
		add_shortcode( 'fswa_tickets_link',    [ __CLASS__, 'tickets_link' ] );
		add_shortcode( 'fswa_kb_link',         [ __CLASS__, 'kb_link' ] );
		add_shortcode( 'fswa_tickets_url',     [ __CLASS__, 'tickets_url' ] );
		add_shortcode( 'fswa_kb_url',          [ __CLASS__, 'kb_url' ] );
	}

	// -------------------------------------------------------------------------
	// Shortcode handlers
	// -------------------------------------------------------------------------

	/**
	 * [fswa_new_ticket_form]
	 *
	 * Renders a full support ticket form. Works for both logged-in users and
	 * guests — guests must supply their name and email address.
	 * Enqueues plugin CSS/JS automatically.
	 *
	 * @param  array|string $atts  (no attributes used currently)
	 * @return string  HTML output.
	 */
	public static function new_ticket_form( $atts ): string {
		if ( ! get_option( 'fswa_allow_new_tickets', 1 ) ) {
			return '<p class="fswa-notice fswa-notice--info">'
				. esc_html__( 'Ticket submission is currently unavailable.', 'fswa' )
				. '</p>';
		}

		$api = FSWA_API::from_options();
		if ( ! $api ) {
			return '<p class="fswa-notice fswa-notice--error">'
				. esc_html__( 'Support is not available at the moment. Please try again later.', 'fswa' )
				. '</p>';
		}

		// Enqueue frontend assets (safe to call here – runs before wp_footer).
		self::enqueue_assets();

		$mailboxes_result = $api->get_mailboxes();
		$mailboxes        = ! is_wp_error( $mailboxes_result )
			? ( $mailboxes_result['_embedded']['mailboxes'] ?? [] )
			: [];

		ob_start();
		FSWA_MyAccount::load_template( 'myaccount/ticket-new-guest.php', compact( 'mailboxes' ) );
		return (string) ob_get_clean();
	}

	/**
	 * [fswa_tickets_link text="..." class="..."]
	 *
	 * @param  array|string $atts
	 * @return string  HTML anchor element.
	 */
	public static function tickets_link( $atts ): string {
		$default_text = get_option( 'fswa_menu_label' ) ?: __( 'Support Tickets', 'fswa' );

		$atts = shortcode_atts(
			[ 'text' => $default_text, 'class' => '' ],
			$atts,
			'fswa_tickets_link'
		);

		return self::anchor(
			wc_get_account_endpoint_url( FSWA_MyAccount::ENDPOINT ),
			$atts['text'],
			$atts['class']
		);
	}

	/**
	 * [fswa_kb_link text="..." class="..."]
	 *
	 * @param  array|string $atts
	 * @return string  HTML anchor element.
	 */
	public static function kb_link( $atts ): string {
		$default_text = get_option( 'fswa_kb_menu_label' ) ?: __( 'Knowledge Base', 'fswa' );

		$atts = shortcode_atts(
			[ 'text' => $default_text, 'class' => '' ],
			$atts,
			'fswa_kb_link'
		);

		return self::anchor(
			wc_get_account_endpoint_url( FSWA_KnowledgeBase::ENDPOINT ),
			$atts['text'],
			$atts['class']
		);
	}

	/**
	 * [fswa_tickets_url]
	 *
	 * Outputs only the URL — useful inside custom HTML e.g.
	 *   <a href="[fswa_tickets_url]" class="my-class">Tickets</a>
	 *
	 * @return string  Escaped URL.
	 */
	public static function tickets_url(): string {
		return esc_url( wc_get_account_endpoint_url( FSWA_MyAccount::ENDPOINT ) );
	}

	/**
	 * [fswa_kb_url]
	 *
	 * @return string  Escaped URL.
	 */
	public static function kb_url(): string {
		return esc_url( wc_get_account_endpoint_url( FSWA_KnowledgeBase::ENDPOINT ) );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Enqueue the plugin's frontend CSS and JS.
	 * Called from shortcode handlers so assets load on non-My-Account pages too.
	 */
	private static function enqueue_assets(): void {
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

		// wp_localize_script is idempotent with the same handle: safe to call
		// even if FSWA_MyAccount::enqueue_assets() already ran on this page.
		wp_localize_script( 'fswa-frontend', 'fswa', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'fswa_nonce' ),
			'i18n'    => [
				'sending'    => __( 'Sending…', 'fswa' ),
				'send'       => __( 'Send Reply', 'fswa' ),
				'emptyReply' => __( 'Please enter a message before sending.', 'fswa' ),
				'replyError' => __( 'There was a problem. Please try again.', 'fswa' ),
			],
		] );
	}

	private static function anchor( string $url, string $text, string $class ): string {
		$class_attr = '' !== trim( $class ) ? ' class="' . esc_attr( trim( $class ) ) . '"' : '';
		return '<a href="' . esc_url( $url ) . '"' . $class_attr . '>' . esc_html( $text ) . '</a>';
	}
}
