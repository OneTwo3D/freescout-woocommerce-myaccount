<?php
/**
 * Shortcodes for embedding links to My Account pages anywhere on the site.
 *
 * [fswa_tickets_link]  – Anchor link to the Support Tickets My Account page.
 * [fswa_kb_link]       – Anchor link to the Knowledge Base My Account page.
 * [fswa_tickets_url]   – Raw URL of the Support Tickets page (no HTML wrapper).
 * [fswa_kb_url]        – Raw URL of the Knowledge Base page (no HTML wrapper).
 *
 * Both link shortcodes accept:
 *   text  – Link label. Defaults to the configured menu label in Settings.
 *   class – One or more CSS classes for the <a> tag, e.g. class="button".
 *
 * Examples:
 *   [fswa_tickets_link]
 *   [fswa_tickets_link text="View my tickets" class="button"]
 *   [fswa_kb_link text="Browse help articles" class="button fswa-btn"]
 *   Href only: <a href="[fswa_tickets_url]">Tickets</a>
 */

defined( 'ABSPATH' ) || exit;

class FSWA_Shortcodes {

	public static function init(): void {
		add_shortcode( 'fswa_tickets_link', [ __CLASS__, 'tickets_link' ] );
		add_shortcode( 'fswa_kb_link',      [ __CLASS__, 'kb_link' ] );
		add_shortcode( 'fswa_tickets_url',  [ __CLASS__, 'tickets_url' ] );
		add_shortcode( 'fswa_kb_url',       [ __CLASS__, 'kb_url' ] );
	}

	// -------------------------------------------------------------------------
	// Shortcode handlers
	// -------------------------------------------------------------------------

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

	private static function anchor( string $url, string $text, string $class ): string {
		$class_attr = '' !== trim( $class ) ? ' class="' . esc_attr( trim( $class ) ) . '"' : '';
		return '<a href="' . esc_url( $url ) . '"' . $class_attr . '>' . esc_html( $text ) . '</a>';
	}
}
