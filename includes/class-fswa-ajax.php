<?php
/**
 * AJAX handlers for frontend ticket actions.
 *
 * Handles:
 *  - Posting a reply to an existing conversation.
 *  - Creating a new conversation (ticket).
 */

defined( 'ABSPATH' ) || exit;

class FSWA_Ajax {

	public static function init(): void {
		add_action( 'wp_ajax_fswa_post_reply',       [ __CLASS__, 'post_reply' ] );
		add_action( 'wp_ajax_fswa_create_ticket',    [ __CLASS__, 'create_ticket' ] );
	}

	// -------------------------------------------------------------------------
	// Reply to existing ticket
	// -------------------------------------------------------------------------

	public static function post_reply(): void {
		self::verify_nonce();

		$conversation_id = (int) ( $_POST['conversation_id'] ?? 0 );
		$body            = wp_unslash( $_POST['body'] ?? '' );

		if ( ! $conversation_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid ticket ID.', 'fswa' ) ], 400 );
		}

		if ( empty( trim( $body ) ) ) {
			wp_send_json_error( [ 'message' => __( 'Message body cannot be empty.', 'fswa' ) ], 400 );
		}

		$api = FSWA_API::from_options();
		if ( ! $api ) {
			wp_send_json_error( [ 'message' => __( 'Support is not available at the moment.', 'fswa' ) ], 503 );
		}

		// Verify the conversation belongs to the logged-in customer.
		$conversation = $api->get_conversation( $conversation_id );
		if ( is_wp_error( $conversation ) ) {
			wp_send_json_error( [ 'message' => $conversation->get_error_message() ], 404 );
		}

		$user  = wp_get_current_user();
		$email = strtolower( $user->user_email );
		if ( strtolower( $conversation['customer']['email'] ?? '' ) !== $email ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'fswa' ) ], 403 );
		}

		// Sanitise body – allow basic formatting tags.
		$allowed = [
			'b'      => [],
			'strong' => [],
			'i'      => [],
			'em'     => [],
			'u'      => [],
			'br'     => [],
			'p'      => [],
			'ul'     => [],
			'ol'     => [],
			'li'     => [],
		];
		$safe_body = wp_kses( nl2br( $body ), $allowed );

		$result = $api->post_reply( $conversation_id, $safe_body, $user->user_email );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ], 500 );
		}

		wp_send_json_success( [
			'message' => __( 'Your reply has been sent.', 'fswa' ),
			'thread'  => $result,
		] );
	}

	// -------------------------------------------------------------------------
	// Create new ticket
	// -------------------------------------------------------------------------

	public static function create_ticket(): void {
		self::verify_nonce();

		if ( ! get_option( 'fswa_allow_new_tickets', 1 ) ) {
			wp_send_json_error( [ 'message' => __( 'Creating new tickets is not available.', 'fswa' ) ], 403 );
		}

		$subject    = sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) );
		$body       = wp_unslash( $_POST['body'] ?? '' );
		$mailbox_id = (int) ( $_POST['mailbox_id'] ?? get_option( 'fswa_default_mailbox_id', 0 ) );

		if ( empty( $subject ) ) {
			wp_send_json_error( [ 'message' => __( 'Please enter a subject.', 'fswa' ) ], 400 );
		}

		if ( empty( trim( $body ) ) ) {
			wp_send_json_error( [ 'message' => __( 'Please enter a message.', 'fswa' ) ], 400 );
		}

		if ( ! $mailbox_id ) {
			wp_send_json_error( [ 'message' => __( 'No mailbox selected.', 'fswa' ) ], 400 );
		}

		$api = FSWA_API::from_options();
		if ( ! $api ) {
			wp_send_json_error( [ 'message' => __( 'Support is not available at the moment.', 'fswa' ) ], 503 );
		}

		$user       = wp_get_current_user();
		$safe_body  = wp_kses_post( nl2br( $body ) );

		$result = $api->create_conversation(
			$mailbox_id,
			$subject,
			$safe_body,
			$user->user_email,
			$user->first_name,
			$user->last_name
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ], 500 );
		}

		$new_id    = (int) ( $result['id'] ?? 0 );
		$redirect  = $new_id
			? wc_get_account_endpoint_url( FSWA_MyAccount::ENDPOINT ) . $new_id . '/'
			: wc_get_account_endpoint_url( FSWA_MyAccount::ENDPOINT );

		wp_send_json_success( [
			'message'  => __( 'Your ticket has been created.', 'fswa' ),
			'redirect' => $redirect,
		] );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private static function verify_nonce(): void {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => __( 'You must be logged in.', 'fswa' ) ], 401 );
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'fswa_nonce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed. Please refresh and try again.', 'fswa' ) ], 403 );
		}
	}
}
