<?php
/**
 * Stub-based test for includes/chat-identify.php. Run: php tests/chat-identify-test.php
 * Uses a throw-away secret; no real credentials.
 */
define( 'ABSPATH', __DIR__ . '/' );
define( 'FREESCOUTAI_IDENTIFY_SECRET', 'test-secret' );
define( 'FREESCOUTAI_FREESCOUT_URL', 'https://support.example.test/' );
define( 'FREESCOUTAI_MAILBOX_ID', 7 );
define( 'FREESCOUTAI_API_BASE', '/api/freescoutai' );

$GLOBALS['hooks']  = array();
$GLOBALS['logged'] = true;
function add_action( $h, $cb, $p = 10 ) { $GLOBALS['hooks'][] = array( $h, $cb, $p ); }
function is_user_logged_in() { return $GLOBALS['logged']; }
function esc_url_raw( $u ) { return $u; }
function get_option( $k ) { return 'no'; }
function wp_get_current_user() {
	return (object) array( 'user_email' => 'a@example.test', 'first_name' => '', 'display_name' => 'Ann', 'last_name' => 'Lee', 'ID' => 42 );
}

$fail = 0;
function check( $name, $ok ) { global $fail; echo ( $ok ? 'ok   ' : 'FAIL ' ) . $name . "\n"; if ( ! $ok ) { $fail++; } }

$mode = $argv[1] ?? 'main';
if ( 'main' === $mode ) {
	$rc = 0;
	foreach ( array( 'legacy', 'plain' ) as $m ) {
		passthru( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' ' . $m, $r );
		$rc |= $r;
	}
	exit( $rc );
}

if ( 'legacy' === $mode ) {
	// Guard: legacy mu function present => include registers nothing and renders nothing.
	function freescoutai_output_identify_script() {}
	require __DIR__ . '/../includes/chat-identify.php';
	check( 'guard: legacy detected', fswa_chat_identify_legacy_active() === true );
	check( 'guard: no hooks registered while legacy mu is loaded', count( $GLOBALS['hooks'] ) === 0 );
	ob_start();
	fswa_output_chat_identify_script();
	check( 'guard: render is a no-op even if called directly', ob_get_clean() === '' );
	exit( $fail ? 1 : 0 );
}

// plain: no legacy function.
require __DIR__ . '/../includes/chat-identify.php';
check( 'plain: legacy not detected', fswa_chat_identify_legacy_active() === false );
$map = array();
foreach ( $GLOBALS['hooks'] as $h ) { $map[ $h[0] ] = array( $h[1], $h[2] ); }
check( 'hooks: wp_head and wp_footer at priority 99', isset( $map['wp_head'], $map['wp_footer'] )
	&& $map['wp_head'] === array( 'fswa_output_chat_identify_script', 99 )
	&& $map['wp_footer'] === array( 'fswa_output_chat_identify_script', 99 ) );

// Payload: no WooCommerce stubs => only identity fields.
$user = wp_get_current_user();
$p    = fswa_chat_identify_build_payload( $user, 1000 );
check( 'payload: identity fields', $p['email'] === 'a@example.test' && $p['first_name'] === 'Ann' && $p['last_name'] === 'Lee' && $p['wc_user_id'] === 42 );
check( 'payload: iat/exp = now, now+3600', $p['iat'] === 1000 && $p['exp'] === 4600 );
check( 'payload: no additional_attributes without WC', ! isset( $p['additional_attributes'] ) );
check( 'payload: key order matches legacy', array_keys( $p ) === array( 'email', 'first_name', 'last_name', 'wc_user_id', 'iat', 'exp' ) );

// JWT: structure and signature.
$jwt = fswa_chat_identify_build_jwt( $p, 'test-secret' );
list( $h, $b, $s ) = explode( '.', $jwt );
$dec = function ( $x ) { return base64_decode( strtr( $x, '-_', '+/' ) ); };
check( 'jwt: header HS256/JWT', json_decode( $dec( $h ), true ) === array( 'alg' => 'HS256', 'typ' => 'JWT' ) );
check( 'jwt: body round-trips', json_decode( $dec( $b ), true ) === json_decode( json_encode( $p ), true ) );
check( 'jwt: signature valid', hash_equals( rtrim( strtr( base64_encode( hash_hmac( 'sha256', "$h.$b", 'test-secret', true ) ), '+/', '-_' ), '=' ), $s ) );
check( 'jwt: base64url, no padding', ! preg_match( '/[+\/=]/', $jwt ) );

// Render.
ob_start();
fswa_output_chat_identify_script();
$out = ob_get_clean();
check( 'render: script tag id', strpos( $out, '<script id="freescoutai-identify">' ) === 0 );
check( 'render: identify URL joins base + /chat/identify', strpos( $out, 'https:\/\/support.example.test\/api\/freescoutai\/chat\/identify' ) !== false );
check( 'render: mailbox id', strpos( $out, 'var MAILBOX_ID    = 7;' ) !== false );
ob_start();
fswa_output_chat_identify_script();
check( 'render: once per request', ob_get_clean() === '' );
exit( $fail ? 1 : 0 );
