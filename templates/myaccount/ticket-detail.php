<?php
/**
 * Template: My Account – Single ticket / conversation view.
 *
 * Available variables (injected by FSWA_MyAccount::render_ticket):
 *   @var array $conversation      Full conversation object from the API.
 *   @var array $threads           Array of thread objects for this conversation.
 *   @var int   $conversation_id   Conversation ID.
 *
 * Themes can override this file by placing a copy at:
 *   <theme>/fswa/myaccount/ticket-detail.php
 */

defined( 'ABSPATH' ) || exit;

$subject    = esc_html( $conversation['subject'] ?? __( '(no subject)', 'fswa' ) );
$status     = ucfirst( $conversation['status'] ?? '' );
$status_cls = 'fswa-status--' . strtolower( str_replace( ' ', '-', $conversation['status'] ?? 'unknown' ) );
$list_url   = wc_get_account_endpoint_url( FSWA_MyAccount::ENDPOINT );
$is_closed  = in_array( strtolower( $conversation['status'] ?? '' ), [ 'closed', 'spam' ], true );
?>

<div class="fswa-ticket-detail">

	<!-- Breadcrumb -->
	<nav class="fswa-breadcrumb">
		<a href="<?php echo esc_url( $list_url ); ?>">&larr; <?php esc_html_e( 'All Tickets', 'fswa' ); ?></a>
	</nav>

	<!-- Header -->
	<div class="fswa-ticket-detail__header">
		<h2 class="fswa-ticket-detail__subject"><?php echo $subject; ?></h2>
		<span class="fswa-status <?php echo esc_attr( $status_cls ); ?>"><?php echo esc_html( $status ); ?></span>
		<span class="fswa-ticket-detail__id">#<?php echo esc_html( $conversation_id ); ?></span>
	</div>

	<!-- Thread -->
	<div class="fswa-thread" id="fswa-thread">
		<?php if ( empty( $threads ) ) : ?>
			<p class="fswa-thread__empty"><?php esc_html_e( 'No messages yet.', 'fswa' ); ?></p>
		<?php else : ?>
			<?php foreach ( $threads as $thread ) :
				$type        = $thread['type'] ?? 'message';
				$body        = $thread['body'] ?? '';
				$created_by  = $thread['createdBy'] ?? [];
				$is_customer = ( 'customer' === $type );
				$is_note     = ( 'note' === $type );

				// Skip internal notes.
				if ( $is_note ) {
					continue;
				}

				$author = $is_customer
					? esc_html( trim( ( $created_by['firstName'] ?? '' ) . ' ' . ( $created_by['lastName'] ?? '' ) ) )
					: esc_html( $created_by['email'] ?? __( 'Support Team', 'fswa' ) );

				$date_raw = $thread['createdAt'] ?? '';
				$date     = $date_raw
					? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $date_raw ) )
					: '';

				$msg_class = 'fswa-message fswa-message--' . ( $is_customer ? 'customer' : 'agent' );
			?>
			<div class="<?php echo esc_attr( $msg_class ); ?>">
				<div class="fswa-message__meta">
					<span class="fswa-message__author"><?php echo $author; ?></span>
					<?php if ( $date ) : ?>
						<span class="fswa-message__date"><?php echo esc_html( $date ); ?></span>
					<?php endif; ?>
				</div>
				<div class="fswa-message__body">
					<?php echo wp_kses_post( $body ); ?>
				</div>
			</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>

	<!-- Reply form -->
	<?php if ( ! $is_closed ) : ?>
	<div class="fswa-reply-form" id="fswa-reply-form">
		<h3 class="fswa-reply-form__title"><?php esc_html_e( 'Send a Reply', 'fswa' ); ?></h3>

		<div class="fswa-notice fswa-notice--success" id="fswa-reply-success" style="display:none;"></div>
		<div class="fswa-notice fswa-notice--error"   id="fswa-reply-error"   style="display:none;"></div>

		<form id="fswa-reply" class="fswa-reply-form__form" novalidate>
			<input type="hidden" name="action"          value="fswa_post_reply">
			<input type="hidden" name="nonce"           value="<?php echo esc_attr( wp_create_nonce( 'fswa_nonce' ) ); ?>">
			<input type="hidden" name="conversation_id" value="<?php echo esc_attr( $conversation_id ); ?>">

			<div class="fswa-form-field">
				<label for="fswa-reply-body" class="screen-reader-text"><?php esc_html_e( 'Your message', 'fswa' ); ?></label>
				<textarea
					id="fswa-reply-body"
					name="body"
					rows="6"
					placeholder="<?php esc_attr_e( 'Type your reply here…', 'fswa' ); ?>"
					required
				></textarea>
			</div>

			<button type="submit" class="button fswa-btn fswa-btn-submit" id="fswa-reply-submit">
				<?php esc_html_e( 'Send Reply', 'fswa' ); ?>
			</button>
		</form>
	</div>
	<?php else : ?>
		<p class="fswa-notice fswa-notice--info">
			<?php esc_html_e( 'This ticket is closed. Please open a new ticket if you need further assistance.', 'fswa' ); ?>
		</p>
	<?php endif; ?>

</div>
