<?php
/**
 * Admin settings page.
 *
 * Adds a "FreeScout" sub-menu under WooCommerce and registers all plugin options.
 */

defined( 'ABSPATH' ) || exit;

class FSWA_Admin {

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
	}

	public static function add_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'FreeScout Integration', 'fswa' ),
			__( 'FreeScout', 'fswa' ),
			'manage_woocommerce',
			'fswa-settings',
			[ __CLASS__, 'render_page' ]
		);
	}

	public static function register_settings(): void {
		// ------------------------------------------------------------------ //
		// Section: API Connection
		// ------------------------------------------------------------------ //
		add_settings_section(
			'fswa_api',
			__( 'FreeScout API Connection', 'fswa' ),
			function () {
				echo '<p>' . esc_html__( 'Enter the URL of your FreeScout installation and an API key. The API key can be generated in FreeScout → Your Profile → API Keys.', 'fswa' ) . '</p>';
			},
			'fswa-settings'
		);

		self::register_field(
			'fswa_api_url',
			__( 'FreeScout URL', 'fswa' ),
			'fswa_api',
			'url',
			__( 'e.g. https://support.example.com', 'fswa' ),
			'sanitize_url'
		);

		self::register_field(
			'fswa_api_key',
			__( 'API Key', 'fswa' ),
			'fswa_api',
			'text',
			'',
			'sanitize_text_field'
		);

		// ------------------------------------------------------------------ //
		// Section: Display
		// ------------------------------------------------------------------ //
		add_settings_section(
			'fswa_display',
			__( 'Display Options', 'fswa' ),
			null,
			'fswa-settings'
		);

		self::register_field(
			'fswa_menu_label',
			__( 'Menu Label', 'fswa' ),
			'fswa_display',
			'text',
			__( 'Support Tickets', 'fswa' ),
			'sanitize_text_field'
		);

		register_setting( 'fswa-settings', 'fswa_per_page', [
			'type'              => 'integer',
			'default'           => 10,
			'sanitize_callback' => function ( $v ) {
				return max( 1, min( 50, (int) $v ) );
			},
		] );
		add_settings_field(
			'fswa_per_page',
			__( 'Tickets Per Page', 'fswa' ),
			function () {
				$value = (int) get_option( 'fswa_per_page', 10 );
				echo '<input type="number" name="fswa_per_page" value="' . esc_attr( $value ) . '" min="1" max="50" class="small-text">';
			},
			'fswa-settings',
			'fswa_display'
		);

		register_setting( 'fswa-settings', 'fswa_allow_new_tickets', [
			'type'              => 'boolean',
			'default'           => 1,
			'sanitize_callback' => 'absint',
		] );
		add_settings_field(
			'fswa_allow_new_tickets',
			__( 'Allow New Tickets', 'fswa' ),
			function () {
				$checked = get_option( 'fswa_allow_new_tickets', 1 );
				echo '<label>';
				echo '<input type="checkbox" name="fswa_allow_new_tickets" value="1" ' . checked( 1, $checked, false ) . '>';
				echo ' ' . esc_html__( 'Let customers open new support tickets from My Account', 'fswa' );
				echo '</label>';
			},
			'fswa-settings',
			'fswa_display'
		);

		// ------------------------------------------------------------------ //
		// Section: New Ticket defaults
		// ------------------------------------------------------------------ //
		add_settings_section(
			'fswa_new_ticket',
			__( 'New Ticket Defaults', 'fswa' ),
			function () {
				echo '<p>' . esc_html__( 'These settings apply when a customer creates a new ticket.', 'fswa' ) . '</p>';
			},
			'fswa-settings'
		);

		self::register_field(
			'fswa_default_mailbox_id',
			__( 'Default Mailbox ID', 'fswa' ),
			'fswa_new_ticket',
			'number',
			'',
			'absint',
			__( 'Leave blank to show a mailbox selector to the customer.', 'fswa' )
		);

		// ------------------------------------------------------------------ //
		// Section: Knowledge Base
		// ------------------------------------------------------------------ //
		add_settings_section(
			'fswa_kb',
			__( 'Knowledge Base', 'fswa' ),
			function () {
				echo '<p>' . wp_kses(
					__( 'Expose a FreeScout knowledge base inside My Account. Requires a <strong>FreeScout Knowledge Base API module</strong> — either <a href="https://github.com/jtorvald/freescout-knowledge-api" target="_blank" rel="noopener">jtorvald/freescout-knowledge-api</a> or <a href="https://github.com/EcomGraduates/KnowledgeBaseApiModule" target="_blank" rel="noopener">EcomGraduates/KnowledgeBaseApiModule</a> (recommended).', 'fswa' ),
					[ 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ], 'strong' => [] ]
				) . '</p>';
			},
			'fswa-settings'
		);

		self::register_field(
			'fswa_kb_mailbox_id',
			__( 'KB Mailbox ID', 'fswa' ),
			'fswa_kb',
			'number',
			'1',
			'absint',
			__( 'The mailbox ID to read knowledge base content from. Required — check FreeScout → Mailboxes for the ID.', 'fswa' )
		);

		self::register_field(
			'fswa_kb_api_token',
			__( 'KB API Token', 'fswa' ),
			'fswa_kb',
			'text',
			'',
			'sanitize_text_field',
			__( 'API token for the Knowledge Base module (e.g. EcomGraduates/KnowledgeBaseApiModule). Generate it in FreeScout → Knowledge Base API → Settings. Leave blank if your module uses the main API Key above.', 'fswa' )
		);

		register_setting( 'fswa-settings', 'fswa_kb_enabled', [
			'type'              => 'boolean',
			'default'           => 1,
			'sanitize_callback' => 'absint',
		] );
		add_settings_field(
			'fswa_kb_enabled',
			__( 'Enable Knowledge Base', 'fswa' ),
			function () {
				$checked = get_option( 'fswa_kb_enabled', 1 );
				echo '<label>';
				echo '<input type="checkbox" name="fswa_kb_enabled" value="1" ' . checked( 1, $checked, false ) . '>';
				echo ' ' . esc_html__( 'Show a Knowledge Base tab in My Account', 'fswa' );
				echo '</label>';
			},
			'fswa-settings',
			'fswa_kb'
		);

		self::register_field(
			'fswa_kb_menu_label',
			__( 'KB Menu Label', 'fswa' ),
			'fswa_kb',
			'text',
			__( 'Knowledge Base', 'fswa' ),
			'sanitize_text_field'
		);

		register_setting( 'fswa-settings', 'fswa_kb_per_page', [
			'type'              => 'integer',
			'default'           => 15,
			'sanitize_callback' => function ( $v ) {
				return max( 1, min( 50, (int) $v ) );
			},
		] );
		add_settings_field(
			'fswa_kb_per_page',
			__( 'Articles Per Page', 'fswa' ),
			function () {
				$value = (int) get_option( 'fswa_kb_per_page', 15 );
				echo '<input type="number" name="fswa_kb_per_page" value="' . esc_attr( $value ) . '" min="1" max="50" class="small-text">';
			},
			'fswa-settings',
			'fswa_kb'
		);

		register_setting( 'fswa-settings', 'fswa_kb_show_search', [
			'type'              => 'boolean',
			'default'           => 1,
			'sanitize_callback' => 'absint',
		] );
		add_settings_field(
			'fswa_kb_show_search',
			__( 'Search Bar', 'fswa' ),
			function () {
				$checked = get_option( 'fswa_kb_show_search', 1 );
				echo '<label>';
				echo '<input type="checkbox" name="fswa_kb_show_search" value="1" ' . checked( 1, $checked, false ) . '>';
				echo ' ' . esc_html__( 'Show a search bar at the top of the knowledge base', 'fswa' );
				echo '</label>';
			},
			'fswa-settings',
			'fswa_kb'
		);

		// ------------------------------------------------------------------ //
		// Section: Spam Protection
		// ------------------------------------------------------------------ //
		add_settings_section(
			'fswa_spam',
			__( 'Spam Protection', 'fswa' ),
			function () {
				echo '<p>' . wp_kses(
					__( 'Add a <a href="https://www.cloudflare.com/products/turnstile/" target="_blank" rel="noopener">Cloudflare Turnstile</a> captcha to the public ticket submission form. Leave blank to disable. Obtain your keys from the Cloudflare dashboard.', 'fswa' ),
					[ 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ] ]
				) . '</p>';
			},
			'fswa-settings'
		);

		self::register_field(
			'fswa_turnstile_site_key',
			__( 'Turnstile Site Key', 'fswa' ),
			'fswa_spam',
			'text',
			'',
			'sanitize_text_field',
			__( 'Shown to visitors — safe to expose publicly.', 'fswa' )
		);

		// Secret key uses password input so it is masked in the admin.
		register_setting( 'fswa-settings', 'fswa_turnstile_secret_key', [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		] );
		add_settings_field(
			'fswa_turnstile_secret_key',
			__( 'Turnstile Secret Key', 'fswa' ),
			function () {
				$value = get_option( 'fswa_turnstile_secret_key', '' );
				echo '<input type="password" id="fswa_turnstile_secret_key" name="fswa_turnstile_secret_key" value="' . esc_attr( $value ) . '" class="regular-text" autocomplete="off">';
				echo '<p class="description">' . esc_html__( 'Keep this secret — never expose it publicly.', 'fswa' ) . '</p>';
			},
			'fswa-settings',
			'fswa_spam'
		);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private static function register_field(
		string  $option,
		string  $label,
		string  $section,
		string  $type,
		string  $placeholder,
		?string $sanitizer,
		string  $description = ''
	): void {
		register_setting( 'fswa-settings', $option, [
			'type'              => 'string',
			'sanitize_callback' => $sanitizer,
		] );

		add_settings_field(
			$option,
			$label,
			function () use ( $option, $type, $placeholder, $description ) {
				$value = get_option( $option, '' );
				echo '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $option ) . '" name="' . esc_attr( $option ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '" class="regular-text">';
				if ( $description ) {
					echo '<p class="description">' . esc_html( $description ) . '</p>';
				}
			},
			'fswa-settings',
			$section
		);
	}

	// -------------------------------------------------------------------------
	// Settings page render
	// -------------------------------------------------------------------------

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Test connection button.
		$test_result = '';
		if ( isset( $_POST['fswa_test_connection'] ) && check_admin_referer( 'fswa_test_connection' ) ) {
			$api = FSWA_API::from_options();
			if ( $api ) {
				$result = $api->get_mailboxes();
				if ( is_wp_error( $result ) ) {
					$test_result = '<div class="notice notice-error inline"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
				} else {
					$count       = count( $result['_embedded']['mailboxes'] ?? [] );
					$test_result = '<div class="notice notice-success inline"><p>'
						/* translators: %d: number of mailboxes */
						. esc_html( sprintf( __( 'Connection successful! Found %d mailbox(es).', 'fswa' ), $count ) )
						. '</p></div>';
				}
			} else {
				$test_result = '<div class="notice notice-warning inline"><p>' . esc_html__( 'Please save your API URL and Key first.', 'fswa' ) . '</p></div>';
			}
		}
		?>
		<div class="wrap fswa-admin-wrap">
			<h1><?php esc_html_e( 'FreeScout Integration', 'fswa' ); ?></h1>

			<?php echo $test_result; // phpcs:ignore WordPress.Security.EscapeOutput ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'fswa-settings' );
				do_settings_sections( 'fswa-settings' );
				submit_button();
				?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'Test Connection', 'fswa' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'fswa_test_connection' ); ?>
				<input type="hidden" name="fswa_test_connection" value="1">
				<?php submit_button( __( 'Test API Connection', 'fswa' ), 'secondary', 'submit', false ); ?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'Knowledge Base API Debugger', 'fswa' ); ?></h2>
			<p><?php esc_html_e( 'Use this tool to inspect the raw JSON the FreeScout KB module returns. This is the fastest way to diagnose missing articles or broken search.', 'fswa' ); ?></p>

			<?php
			$kb_debug_output = '';
			if ( isset( $_POST['fswa_kb_debug'] ) && check_admin_referer( 'fswa_kb_debug' ) ) {
				$kb_debug_output = self::run_kb_debug(
					sanitize_text_field( wp_unslash( $_POST['fswa_kb_debug_endpoint'] ?? 'categories' ) ),
					sanitize_text_field( wp_unslash( $_POST['fswa_kb_debug_param'] ?? '' ) )
				);
			}
			?>

			<form method="post">
				<?php wp_nonce_field( 'fswa_kb_debug' ); ?>
				<input type="hidden" name="fswa_kb_debug" value="1">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="fswa_kb_debug_endpoint"><?php esc_html_e( 'Endpoint', 'fswa' ); ?></label></th>
						<td>
							<select id="fswa_kb_debug_endpoint" name="fswa_kb_debug_endpoint">
								<option value="categories"><?php esc_html_e( 'GET /categories — list all categories', 'fswa' ); ?></option>
								<option value="category"><?php esc_html_e( 'GET /categories/{id} — articles in one category (enter ID below)', 'fswa' ); ?></option>
								<option value="article"><?php esc_html_e( 'GET /articles/{id} — single article by ID (enter ID below)', 'fswa' ); ?></option>
								<option value="search"><?php esc_html_e( 'GET /search?q={term} — search (enter term below)', 'fswa' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="fswa_kb_debug_param"><?php esc_html_e( 'Category ID / Search term', 'fswa' ); ?></label></th>
						<td>
							<input type="text" id="fswa_kb_debug_param" name="fswa_kb_debug_param" value="" class="regular-text"
								placeholder="<?php esc_attr_e( 'e.g. 3  or  order tracking', 'fswa' ); ?>">
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Run KB Debug Request', 'fswa' ), 'secondary', 'submit', false ); ?>
			</form>

			<?php if ( '' !== $kb_debug_output ) : ?>
			<div style="margin-top:16px;">
				<?php echo $kb_debug_output; // phpcs:ignore WordPress.Security.EscapeOutput — already escaped inside run_kb_debug() ?>
			</div>
			<?php endif; ?>

		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// KB debug helper
	// -------------------------------------------------------------------------

	private static function run_kb_debug( string $endpoint, string $param ): string {
		$api        = FSWA_API::from_options();
		$mailbox_id = (int) get_option( 'fswa_kb_mailbox_id', 0 );

		if ( ! $api ) {
			return '<div class="notice notice-warning inline"><p>'
				. esc_html__( 'API not configured. Save your FreeScout URL and API Key first.', 'fswa' )
				. '</p></div>';
		}

		if ( ! $mailbox_id ) {
			return '<div class="notice notice-warning inline"><p>'
				. esc_html__( 'KB Mailbox ID is not set. Configure it in the settings above.', 'fswa' )
				. '</p></div>';
		}

		switch ( $endpoint ) {
			case 'category':
				$cat_id = (int) $param;
				if ( ! $cat_id ) {
					return '<div class="notice notice-error inline"><p>'
						. esc_html__( 'Please enter a category ID.', 'fswa' )
						. '</p></div>';
				}
				$result = $api->get_kb_category( $mailbox_id, $cat_id );
				$label  = "/api/knowledgebase/{$mailbox_id}/categories/{$cat_id}";
				break;

			case 'article':
				$art_id = (int) $param;
				if ( ! $art_id ) {
					return '<div class="notice notice-error inline"><p>'
						. esc_html__( 'Please enter an article ID.', 'fswa' )
						. '</p></div>';
				}
				$result = $api->get_kb_article_direct( $mailbox_id, $art_id );
				$label  = "/api/knowledgebase/{$mailbox_id}/articles/{$art_id}";
				break;

			case 'search':
				$q = trim( $param );
				if ( '' === $q ) {
					return '<div class="notice notice-error inline"><p>'
						. esc_html__( 'Please enter a search term.', 'fswa' )
						. '</p></div>';
				}
				$result = $api->search_kb( $mailbox_id, $q );
				$label  = "/api/knowledgebase/{$mailbox_id}/search?q=" . rawurlencode( $q );
				break;

			default: // categories
				$result = $api->get_kb_categories( $mailbox_id );
				$label  = "/api/knowledgebase/{$mailbox_id}/categories";
				break;
		}

		ob_start();

		if ( is_wp_error( $result ) ) {
			echo '<div class="notice notice-error inline"><p><strong>'
				. esc_html__( 'API error:', 'fswa' ) . '</strong> '
				. esc_html( $result->get_error_message() )
				. '</p></div>';
		} else {
			$json   = wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			$lines  = substr_count( $json, "\n" ) + 1;
			$height = min( max( $lines * 18 + 20, 120 ), 600 );
			echo '<p><strong>' . esc_html__( 'Endpoint:', 'fswa' ) . '</strong> <code>' . esc_html( $label ) . '</code></p>';
			echo '<p style="color:#3c763d;"><strong>' . esc_html__( 'HTTP 200 OK — raw decoded response:', 'fswa' ) . '</strong></p>';
			echo '<textarea readonly style="width:100%;height:' . esc_attr( $height ) . 'px;font-family:monospace;font-size:12px;background:#f6f8fa;border:1px solid #d0d7de;padding:8px;">'
				. esc_textarea( $json )
				. '</textarea>';
		}

		return (string) ob_get_clean();
	}
}
