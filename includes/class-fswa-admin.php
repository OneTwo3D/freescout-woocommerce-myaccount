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
				echo '<p>' . esc_html__( 'Expose the FreeScout Docs knowledge base inside My Account. Requires the FreeScout Docs module to be installed.', 'fswa' ) . '</p>';
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
		</div>
		<?php
	}
}
