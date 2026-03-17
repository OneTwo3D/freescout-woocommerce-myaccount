<?php
/**
 * AJAX handlers for frontend ticket and knowledge base actions.
 *
 * Handles:
 *  - Posting a reply to an existing conversation.
 *  - Creating a new conversation (ticket).
 *  - Live-searching the knowledge base.
 */

defined( 'ABSPATH' ) || exit;

class FSWA_Ajax {

	public static function init(): void {
		add_action( 'wp_ajax_fswa_post_reply',          [ __CLASS__, 'post_reply' ] );
		add_action( 'wp_ajax_fswa_create_ticket',       [ __CLASS__, 'create_ticket' ] );
		add_action( 'wp_ajax_nopriv_fswa_create_ticket', [ __CLASS__, 'create_ticket' ] ); // guest support
		add_action( 'wp_ajax_fswa_kb_search',           [ __CLASS__, 'kb_search' ] );
		add_action( 'wp_ajax_nopriv_fswa_kb_search',    [ __CLASS__, 'kb_search' ] );   // guest KB search
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
		// Nonce required; login is NOT required (guest submission supported).
		self::verify_nonce( false );

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

		$safe_body = wp_kses_post( nl2br( $body ) );

		if ( is_user_logged_in() ) {
			// Logged-in: use WordPress account details and redirect to the new ticket.
			$user  = wp_get_current_user();
			$email = $user->user_email;
			$first = $user->first_name;
			$last  = $user->last_name;

			$result = $api->create_conversation( $mailbox_id, $subject, $safe_body, $email, $first, $last );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( [ 'message' => $result->get_error_message() ], 500 );
			}

			$new_id   = (int) ( $result['id'] ?? 0 );
			$redirect = $new_id
				? wc_get_account_endpoint_url( FSWA_MyAccount::ENDPOINT ) . $new_id . '/'
				: wc_get_account_endpoint_url( FSWA_MyAccount::ENDPOINT );

			wp_send_json_success( [
				'message'  => __( 'Your ticket has been created.', 'fswa' ),
				'redirect' => $redirect,
			] );

		} else {
			// Guest: require name and email from the form.
			$guest_name  = sanitize_text_field( wp_unslash( $_POST['guest_name'] ?? '' ) );
			$guest_email = sanitize_email( wp_unslash( $_POST['guest_email'] ?? '' ) );

			if ( empty( $guest_name ) ) {
				wp_send_json_error( [ 'message' => __( 'Please enter your name.', 'fswa' ) ], 400 );
			}

			if ( ! is_email( $guest_email ) ) {
				wp_send_json_error( [ 'message' => __( 'Please enter a valid email address.', 'fswa' ) ], 400 );
			}

			$name_parts = explode( ' ', $guest_name, 2 );
			$first      = $name_parts[0];
			$last       = $name_parts[1] ?? '';

			$result = $api->create_conversation( $mailbox_id, $subject, $safe_body, $guest_email, $first, $last );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( [ 'message' => $result->get_error_message() ], 500 );
			}

			wp_send_json_success( [
				/* translators: %s: the guest's email address */
				'message'  => sprintf(
					__( "Your ticket has been submitted. We'll get back to you at %s shortly.", 'fswa' ),
					$guest_email
				),
				'redirect' => '', // no My Account redirect for guests
			] );
		}
	}

	// -------------------------------------------------------------------------
	// Knowledge Base live search
	// -------------------------------------------------------------------------

	/**
	 * Return up to 8 KB article suggestions matching a short query string.
	 * Used by the live-search autocomplete on the KB search bar.
	 */
	public static function kb_search(): void {
		// Login not required — KB search is open to guests via the [fswa_kb] shortcode.
		self::verify_nonce( false );

		if ( ! get_option( 'fswa_kb_enabled', 1 ) ) {
			wp_send_json_error( [ 'message' => __( 'Knowledge base is not available.', 'fswa' ) ], 403 );
		}

		$query = sanitize_text_field( wp_unslash( $_POST['query'] ?? '' ) );
		if ( mb_strlen( $query ) < 2 ) {
			wp_send_json_success( [ 'articles' => [] ] );
		}

		$api = FSWA_API::from_options();
		if ( ! $api ) {
			wp_send_json_error( [ 'message' => __( 'Knowledge base is not available at the moment.', 'fswa' ) ], 503 );
		}

		$result = $api->get_kb_articles( 0, $query, 1, 8 );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ], 500 );
		}

		$raw      = $result['_embedded']['articles'] ?? [];
		$articles = [];

		foreach ( $raw as $article ) {
			$id    = (int) ( $article['id'] ?? 0 );
			$title = sanitize_text_field( $article['name'] ?? $article['title'] ?? '' );
			if ( $id && $title ) {
				$articles[] = [
					'id'    => $id,
					'title' => $title,
					'url'   => FSWA_KnowledgeBase::article_url( $id ),
				];
			}
		}

		wp_send_json_success( [ 'articles' => $articles ] );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Verify the AJAX nonce and, optionally, that the user is logged in.
	 *
	 * @param bool $require_login  Pass false to allow guest (non-logged-in) requests.
	 */
	private static function verify_nonce( bool $require_login = true ): void {
		if ( $require_login && ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => __( 'You must be logged in.', 'fswa' ) ], 401 );
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'fswa_nonce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed. Please refresh and try again.', 'fswa' ) ], 403 );
		}
	}
}
