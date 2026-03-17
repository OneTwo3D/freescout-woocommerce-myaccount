<?php
/**
 * Template: My Account – Support Tickets list.
 *
 * Available variables (injected by FSWA_MyAccount::render_ticket_list):
 *   @var array $conversations   Array of conversation objects from the API.
 *   @var int   $page            Current page number.
 *   @var int   $total           Total number of conversations.
 *   @var int   $total_pages     Total number of pages.
 *   @var int   $per_page        Items per page.
 *
 * Themes can override this file by placing a copy at:
 *   <theme>/fswa/myaccount/tickets.php
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="fswa-tickets">

	<div class="fswa-tickets__header">
		<h2 class="fswa-tickets__title"><?php esc_html_e( 'Your Support Tickets', 'fswa' ); ?></h2>
		<?php if ( get_option( 'fswa_allow_new_tickets', 1 ) ) : ?>
			<a href="<?php echo esc_url( wc_get_account_endpoint_url( FSWA_MyAccount::ENDPOINT ) . 'new/' ); ?>" class="button fswa-btn-new-ticket">
				<?php esc_html_e( '+ New Ticket', 'fswa' ); ?>
			</a>
		<?php endif; ?>
	</div>

	<?php if ( empty( $conversations ) ) : ?>

		<p class="fswa-tickets__empty"><?php esc_html_e( 'You have no support tickets yet.', 'fswa' ); ?></p>

	<?php else : ?>

		<table class="fswa-tickets__table woocommerce-orders-table shop_table shop_table_responsive">
			<thead>
				<tr>
					<th class="fswa-col-id"><?php esc_html_e( '#', 'fswa' ); ?></th>
					<th class="fswa-col-subject"><?php esc_html_e( 'Subject', 'fswa' ); ?></th>
					<th class="fswa-col-status"><?php esc_html_e( 'Status', 'fswa' ); ?></th>
					<th class="fswa-col-updated"><?php esc_html_e( 'Last Updated', 'fswa' ); ?></th>
					<th class="fswa-col-action"><?php esc_html_e( 'Action', 'fswa' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $conversations as $conv ) :
					$conv_id   = (int) ( $conv['id'] ?? 0 );
					$subject   = esc_html( $conv['subject'] ?? __( '(no subject)', 'fswa' ) );
					$status    = esc_html( ucfirst( $conv['status'] ?? '' ) );
					$updated   = ! empty( $conv['updatedAt'] )
						? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $conv['updatedAt'] ) )
						: '';
					$detail_url = wc_get_account_endpoint_url( FSWA_MyAccount::ENDPOINT ) . $conv_id . '/';

					$status_slug = strtolower( str_replace( ' ', '-', $conv['status'] ?? 'unknown' ) );
				?>
				<tr class="fswa-ticket-row fswa-ticket-row--<?php echo esc_attr( $status_slug ); ?>">
					<td class="fswa-col-id" data-title="<?php esc_attr_e( '#', 'fswa' ); ?>">
						<?php echo esc_html( $conv_id ); ?>
					</td>
					<td class="fswa-col-subject" data-title="<?php esc_attr_e( 'Subject', 'fswa' ); ?>">
						<a href="<?php echo esc_url( $detail_url ); ?>"><?php echo $subject; ?></a>
					</td>
					<td class="fswa-col-status" data-title="<?php esc_attr_e( 'Status', 'fswa' ); ?>">
						<span class="fswa-status fswa-status--<?php echo esc_attr( $status_slug ); ?>">
							<?php echo $status; ?>
						</span>
					</td>
					<td class="fswa-col-updated" data-title="<?php esc_attr_e( 'Last Updated', 'fswa' ); ?>">
						<?php echo esc_html( $updated ); ?>
					</td>
					<td class="fswa-col-action">
						<a href="<?php echo esc_url( $detail_url ); ?>" class="button">
							<?php esc_html_e( 'View', 'fswa' ); ?>
						</a>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $total_pages > 1 ) : ?>
		<div class="fswa-pagination woocommerce-pagination">
			<?php
			$base_url = wc_get_account_endpoint_url( FSWA_MyAccount::ENDPOINT );
			echo paginate_links( [
				'base'      => add_query_arg( 'paged', '%#%', $base_url ),
				'format'    => '?paged=%#%',
				'current'   => $page,
				'total'     => $total_pages,
				'prev_text' => '&laquo; ' . esc_html__( 'Previous', 'fswa' ),
				'next_text' => esc_html__( 'Next', 'fswa' ) . ' &raquo;',
			] );
			?>
		</div>
		<?php endif; ?>

	<?php endif; ?>

</div>
