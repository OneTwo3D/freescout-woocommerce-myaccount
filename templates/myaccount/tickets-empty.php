<?php
/**
 * Template: My Account – empty state when no FreeScout customer record exists yet.
 *
 * Available variables:
 *   @var bool $show_new  Whether to show the "New Ticket" button.
 *
 * Themes can override this file by placing a copy at:
 *   <theme>/fswa/myaccount/tickets-empty.php
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="fswa-tickets fswa-tickets--empty">

	<div class="fswa-tickets__header">
		<h2 class="fswa-tickets__title"><?php esc_html_e( 'Your Support Tickets', 'fswa' ); ?></h2>
		<?php if ( ! empty( $show_new ) ) : ?>
			<a href="<?php echo esc_url( wc_get_account_endpoint_url( FSWA_MyAccount::ENDPOINT ) . 'new/' ); ?>" class="button fswa-btn-new-ticket">
				<?php esc_html_e( '+ New Ticket', 'fswa' ); ?>
			</a>
		<?php endif; ?>
	</div>

	<p class="fswa-tickets__empty">
		<?php esc_html_e( 'You have no support tickets yet.', 'fswa' ); ?>
	</p>

</div>
