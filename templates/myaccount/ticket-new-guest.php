<?php
/**
 * Template: Standalone new ticket form (used by the [fswa_new_ticket_form] shortcode).
 *
 * Works for both logged-in users and guests:
 *  - Logged-in  – name/email fields are hidden; uses the WordPress account.
 *  - Guest      – name and email fields are shown and required.
 *
 * Available variables (injected by FSWA_Shortcodes::new_ticket_form):
 *   @var array $mailboxes  Array of mailbox objects from the API.
 *
 * Themes can override this file by placing a copy at:
 *   <theme>/fswa/myaccount/ticket-new-guest.php
 */

defined( 'ABSPATH' ) || exit;

$default_mailbox  = (int) get_option( 'fswa_default_mailbox_id', 0 );
$show_mailbox_sel = empty( $default_mailbox ) && ! empty( $mailboxes );
$is_guest         = ! is_user_logged_in();
?>

<div class="fswa-new-ticket">

	<div class="fswa-notice fswa-notice--success" id="fswa-new-ticket-success" style="display:none;"></div>
	<div class="fswa-notice fswa-notice--error"   id="fswa-new-ticket-error"   style="display:none;"></div>

	<form id="fswa-new-ticket-form" class="fswa-form" novalidate>
		<input type="hidden" name="action" value="fswa_create_ticket">
		<input type="hidden" name="nonce"  value="<?php echo esc_attr( wp_create_nonce( 'fswa_nonce' ) ); ?>">

		<?php if ( $is_guest ) : ?>

		<div class="fswa-form-field">
			<label for="fswa-guest-name"><?php esc_html_e( 'Your Name', 'fswa' ); ?> <span class="required">*</span></label>
			<input
				type="text"
				id="fswa-guest-name"
				name="guest_name"
				maxlength="100"
				placeholder="<?php esc_attr_e( 'First and last name', 'fswa' ); ?>"
				autocomplete="name"
				required
			>
		</div>

		<div class="fswa-form-field">
			<label for="fswa-guest-email"><?php esc_html_e( 'Email Address', 'fswa' ); ?> <span class="required">*</span></label>
			<input
				type="email"
				id="fswa-guest-email"
				name="guest_email"
				placeholder="<?php esc_attr_e( 'you@example.com', 'fswa' ); ?>"
				autocomplete="email"
				required
			>
		</div>

		<?php endif; ?>

		<?php if ( $show_mailbox_sel ) : ?>
		<div class="fswa-form-field">
			<label for="fswa-mailbox"><?php esc_html_e( 'Department', 'fswa' ); ?></label>
			<select id="fswa-mailbox" name="mailbox_id" required>
				<option value=""><?php esc_html_e( '— Select a department —', 'fswa' ); ?></option>
				<?php foreach ( $mailboxes as $mb ) : ?>
					<option value="<?php echo esc_attr( $mb['id'] ); ?>">
						<?php echo esc_html( $mb['name'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php else : ?>
			<input type="hidden" name="mailbox_id" value="<?php echo esc_attr( $default_mailbox ); ?>">
		<?php endif; ?>

		<div class="fswa-form-field">
			<label for="fswa-subject"><?php esc_html_e( 'Subject', 'fswa' ); ?> <span class="required">*</span></label>
			<input
				type="text"
				id="fswa-subject"
				name="subject"
				maxlength="255"
				placeholder="<?php esc_attr_e( 'Brief description of your issue', 'fswa' ); ?>"
				required
			>
		</div>

		<div class="fswa-form-field">
			<label for="fswa-body"><?php esc_html_e( 'Message', 'fswa' ); ?> <span class="required">*</span></label>
			<textarea
				id="fswa-body"
				name="body"
				rows="8"
				placeholder="<?php esc_attr_e( 'Describe your issue in detail…', 'fswa' ); ?>"
				required
			></textarea>
		</div>

		<button type="submit" class="button fswa-btn fswa-btn-submit" id="fswa-new-ticket-submit">
			<?php esc_html_e( 'Submit Ticket', 'fswa' ); ?>
		</button>
	</form>

</div>
