<?php
/**
 * Template: My Account – New ticket form.
 *
 * Available variables (injected by FSWA_MyAccount::render_new_ticket_form):
 *   @var array $mailboxes  Array of mailbox objects from the API.
 *
 * Themes can override this file by placing a copy at:
 *   <theme>/fswa/myaccount/ticket-new.php
 */

defined( 'ABSPATH' ) || exit;

$list_url          = wc_get_account_endpoint_url( FSWA_MyAccount::ENDPOINT );
$default_mailbox   = (int) get_option( 'fswa_default_mailbox_id', 0 );
$show_mailbox_sel  = empty( $default_mailbox ) && ! empty( $mailboxes );
?>

<div class="fswa-new-ticket">

	<nav class="fswa-breadcrumb">
		<a href="<?php echo esc_url( $list_url ); ?>">&larr; <?php esc_html_e( 'All Tickets', 'fswa' ); ?></a>
	</nav>

	<h2 class="fswa-new-ticket__title"><?php esc_html_e( 'Open a New Support Ticket', 'fswa' ); ?></h2>

	<div class="fswa-notice fswa-notice--success" id="fswa-new-ticket-success" style="display:none;"></div>
	<div class="fswa-notice fswa-notice--error"   id="fswa-new-ticket-error"   style="display:none;"></div>

	<form id="fswa-new-ticket-form" class="fswa-form" novalidate>
		<input type="hidden" name="action" value="fswa_create_ticket">
		<input type="hidden" name="nonce"  value="<?php echo esc_attr( wp_create_nonce( 'fswa_nonce' ) ); ?>">

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
