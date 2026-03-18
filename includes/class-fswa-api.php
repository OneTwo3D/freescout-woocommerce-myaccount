<?php
/**
 * FreeScout REST API wrapper.
 *
 * Handles all HTTP communication with the FreeScout API.
 * Docs: https://github.com/freescout-helpdesk/freescout/wiki/REST-API
 */

defined( 'ABSPATH' ) || exit;

class FSWA_API {

	/** @var string Base URL of the FreeScout installation, no trailing slash. */
	private string $base_url;

	/** @var string FreeScout API key. */
	private string $api_key;

	/** @var int HTTP request timeout in seconds. */
	private int $timeout;

	public function __construct( string $base_url, string $api_key, int $timeout = 15 ) {
		$this->base_url = untrailingslashit( $base_url );
		$this->api_key  = $api_key;
		$this->timeout  = $timeout;
	}

	// -------------------------------------------------------------------------
	// Factory
	// -------------------------------------------------------------------------

	/**
	 * Build an instance from the saved plugin options.
	 *
	 * @return static|null  Null when the plugin has not been configured yet.
	 */
	public static function from_options(): ?self {
		$url = get_option( 'fswa_api_url', '' );
		$key = get_option( 'fswa_api_key', '' );

		if ( empty( $url ) || empty( $key ) ) {
			return null;
		}

		return new self( $url, $key );
	}

	// -------------------------------------------------------------------------
	// Customers
	// -------------------------------------------------------------------------

	/**
	 * Look up the FreeScout customer ID for a given e-mail address.
	 *
	 * @param  string $email
	 * @return int|WP_Error  Customer ID or WP_Error on failure.
	 */
	public function get_customer_id_by_email( string $email ) {
		$response = $this->get( '/api/customers', [ 'email' => $email ] );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$customers = $response['_embedded']['customers'] ?? [];
		if ( empty( $customers ) ) {
			return new WP_Error( 'fswa_no_customer', __( 'No FreeScout customer found for your e-mail address.', 'fswa' ) );
		}

		return (int) $customers[0]['id'];
	}

	// -------------------------------------------------------------------------
	// Conversations / Tickets
	// -------------------------------------------------------------------------

	/**
	 * Return a paginated list of conversations for a customer.
	 *
	 * @param  int $customer_id  FreeScout customer ID.
	 * @param  int $page         1-based page number.
	 * @param  int $per_page     Number of items per page (max 50 by API).
	 * @return array|WP_Error
	 */
	public function get_conversations( int $customer_id, int $page = 1, int $per_page = 20 ) {
		return $this->get( '/api/conversations', [
			'customerId' => $customer_id,
			'page'       => $page,
			'pageSize'   => min( $per_page, 50 ),
			'sortField'  => 'updatedAt',
			'sortOrder'  => 'desc',
		] );
	}

	/**
	 * Return a single conversation with its thread.
	 *
	 * @param  int $conversation_id
	 * @return array|WP_Error
	 */
	public function get_conversation( int $conversation_id ) {
		return $this->get( '/api/conversations/' . $conversation_id, [ 'embed' => 'threads' ] );
	}

	/**
	 * Post a customer reply to an existing conversation.
	 *
	 * @param  int    $conversation_id
	 * @param  string $body         HTML or plain text message body.
	 * @param  string $customer_email
	 * @return array|WP_Error  Created thread object or error.
	 */
	public function post_reply( int $conversation_id, string $body, string $customer_email ) {
		$payload = [
			'type'     => 'customer',
			'text'     => wp_kses_post( $body ),
			'customer' => [
				'email' => $customer_email,
			],
		];

		return $this->post(
			'/api/conversations/' . $conversation_id . '/threads',
			$payload
		);
	}

	/**
	 * Create a new conversation (ticket) on behalf of a customer.
	 *
	 * @param  int    $mailbox_id
	 * @param  string $subject
	 * @param  string $body
	 * @param  string $customer_email
	 * @param  string $customer_first_name
	 * @param  string $customer_last_name
	 * @param  array  $attachments  Optional. Each entry: ['fileName'=>'...','mimeType'=>'...','data'=>'<base64>'].
	 * @return array|WP_Error
	 */
	public function create_conversation(
		int    $mailbox_id,
		string $subject,
		string $body,
		string $customer_email,
		string $customer_first_name = '',
		string $customer_last_name  = '',
		array  $attachments         = []
	) {
		// Thread customer is required so FreeScout can set thread.customer_id.
		$thread = [
			'type'     => 'customer',
			'text'     => $body,
			'customer' => [ 'email' => $customer_email ],
		];

		if ( ! empty( $attachments ) ) {
			$thread['attachments'] = $attachments;
		}

		// Build customer object — omit name fields when empty so FreeScout
		// does not receive empty strings it may treat as invalid.
		$customer = [ 'email' => $customer_email ];
		if ( '' !== $customer_first_name ) {
			$customer['firstName'] = $customer_first_name;
		}
		if ( '' !== $customer_last_name ) {
			$customer['lastName'] = $customer_last_name;
		}

		$payload = [
			'type'      => 'email',
			'mailboxId' => $mailbox_id,
			'subject'   => sanitize_text_field( $subject ),
			'customer'  => $customer,
			'threads'   => [ $thread ],
		];

		return $this->post( '/api/conversations', $payload );
	}

	/**
	 * Return all configured mailboxes (used when creating new tickets).
	 *
	 * @return array|WP_Error
	 */
	public function get_mailboxes() {
		return $this->get( '/api/mailboxes' );
	}

	// -------------------------------------------------------------------------
	// Knowledge Base
	//
	// Requires a FreeScout Knowledge Base API module such as:
	//   • jtorvald/freescout-knowledge-api          (2 endpoints)
	//   • EcomGraduates/KnowledgeBaseApiModule      (full CRUD + search)
	//
	// Both expose routes under /api/knowledgebase/{mailbox_id}/...
	// The mailbox_id is configured in WooCommerce → FreeScout → KB Mailbox ID.
	// -------------------------------------------------------------------------

	/**
	 * Return all KB categories for a mailbox.
	 *
	 * @param  int $mailbox_id  FreeScout mailbox ID.
	 * @return array|WP_Error
	 */
	public function get_kb_categories( int $mailbox_id ) {
		return $this->kb_get( '/api/knowledgebase/' . $mailbox_id . '/categories' );
	}

	/**
	 * Return articles for a KB category (response may also include category metadata).
	 *
	 * @param  int $mailbox_id   FreeScout mailbox ID.
	 * @param  int $category_id  KB category ID.
	 * @return array|WP_Error
	 */
	public function get_kb_category( int $mailbox_id, int $category_id ) {
		return $this->kb_get( '/api/knowledgebase/' . $mailbox_id . '/categories/' . $category_id );
	}

	/**
	 * Return a single KB article by ID alone (no category context).
	 *
	 * Tries /api/knowledgebase/{mailboxId}/articles/{articleId}.
	 * Returns WP_Error (typically 404) when the module doesn't support
	 * this endpoint; callers should fall back gracefully.
	 *
	 * @param  int $mailbox_id   FreeScout mailbox ID.
	 * @param  int $article_id   KB article ID.
	 * @return array|WP_Error
	 */
	public function get_kb_article_direct( int $mailbox_id, int $article_id ) {
		return $this->kb_get( '/api/knowledgebase/' . $mailbox_id . '/articles/' . $article_id );
	}

	/**
	 * Return a single KB article.
	 *
	 * Supported by EcomGraduates/KnowledgeBaseApiModule.
	 * The jtorvald module does not expose this endpoint (expect a 404).
	 *
	 * @param  int $mailbox_id   FreeScout mailbox ID.
	 * @param  int $category_id  Parent KB category ID.
	 * @param  int $article_id   KB article ID.
	 * @return array|WP_Error
	 */
	public function get_kb_article( int $mailbox_id, int $category_id, int $article_id ) {
		return $this->kb_get( '/api/knowledgebase/' . $mailbox_id . '/categories/' . $category_id . '/articles/' . $article_id );
	}

	/**
	 * Search KB articles.
	 *
	 * @param  int    $mailbox_id  FreeScout mailbox ID.
	 * @param  string $query       Search term.
	 * @return array|WP_Error
	 */
	public function search_kb( int $mailbox_id, string $query ) {
		return $this->kb_get( '/api/knowledgebase/' . $mailbox_id . '/search', [ 'q' => $query ] );
	}

	// -------------------------------------------------------------------------
	// HTTP helpers
	// -------------------------------------------------------------------------

	/**
	 * Perform a GET request for a Knowledge Base endpoint.
	 *
	 * Identical to get() but automatically appends the KB API token (stored in
	 * the fswa_kb_api_token option) as a `token` query parameter when set.
	 * Required by the EcomGraduates/KnowledgeBaseApiModule; ignored by modules
	 * that authenticate via the X-FreeScout-API-Key header instead.
	 *
	 * @param  string $endpoint
	 * @param  array  $params
	 * @return array|WP_Error
	 */
	private function kb_get( string $endpoint, array $params = [] ) {
		$token = get_option( 'fswa_kb_api_token', '' );
		if ( '' !== $token ) {
			$params['token'] = $token;
		}

		$url = $this->base_url . $endpoint;
		if ( ! empty( $params ) ) {
			$url = $url . '?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
		}

		// KB endpoints authenticate via ?token= and must NOT carry the
		// X-FreeScout-API-Key header. FreeScout's own REST API layer intercepts
		// any request that bears that header and routes it through its own
		// dispatcher, which has no knowledge of the KB module routes and
		// returns 405 Method Not Allowed.
		$args = [
			'timeout' => $this->timeout,
			'headers' => [
				'Accept' => 'application/json',
			],
		];

		$response = wp_remote_get( $url, $args );
		return $this->parse_response( $response );
	}

	/**
	 * Perform a GET request.
	 *
	 * @param  string $endpoint  Path starting with /.
	 * @param  array  $params    Query string parameters.
	 * @return array|WP_Error    Decoded JSON body or WP_Error.
	 */
	private function get( string $endpoint, array $params = [] ) {
		$url = $this->base_url . $endpoint;
		if ( ! empty( $params ) ) {
			// Use RFC3986 so spaces are encoded as %20, not +, for broader server compatibility.
			$url = $url . '?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
		}

		$response = wp_remote_get( $url, $this->request_args() );
		return $this->parse_response( $response );
	}

	/**
	 * Perform a POST request with a JSON body.
	 *
	 * @param  string $endpoint
	 * @param  array  $data
	 * @return array|WP_Error
	 */
	private function post( string $endpoint, array $data ) {
		$url  = $this->base_url . $endpoint;
		$args = $this->request_args();

		$args['method'] = 'POST';
		$args['body']   = wp_json_encode( $data );
		$args['headers']['Content-Type'] = 'application/json';

		$response = wp_remote_post( $url, $args );
		return $this->parse_response( $response, [ 200, 201 ] );
	}

	/**
	 * Default wp_remote_* argument array.
	 */
	private function request_args(): array {
		return [
			'timeout' => $this->timeout,
			'headers' => [
				'X-FreeScout-API-Key' => $this->api_key,
				'Accept'              => 'application/json',
			],
		];
	}

	/**
	 * Parse a wp_remote_* response.
	 *
	 * @param  array|WP_Error $response
	 * @param  int[]          $valid_codes  HTTP status codes treated as success.
	 * @return array|WP_Error
	 */
	private function parse_response( $response, array $valid_codes = [ 200 ] ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );

		if ( ! in_array( $status, $valid_codes, true ) ) {
			$detail  = $this->extract_error_message( $body );
			$message = $detail
				/* translators: 1: HTTP status code  2: error detail from FreeScout */
				? sprintf( __( 'FreeScout API error (HTTP %1$d): %2$s', 'fswa' ), $status, $detail )
				/* translators: %d: HTTP status code */
				: sprintf( __( 'FreeScout API returned HTTP %d.', 'fswa' ), $status );
			return new WP_Error( 'fswa_api_error', $message, [ 'status' => $status ] );
		}

		if ( empty( $body ) ) {
			return [];
		}

		$decoded = json_decode( $body, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'fswa_json_error', __( 'Could not decode FreeScout API response.', 'fswa' ) );
		}

		return $decoded;
	}

	/**
	 * Try to pull a human-readable error message from an API error body.
	 */
	private function extract_error_message( string $body ): string {
		$data = json_decode( $body, true );
		return $data['message'] ?? $data['error'] ?? '';
	}
}
