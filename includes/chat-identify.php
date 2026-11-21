<?php
/**
 * Chat customer identification (FreeScout chat widget).
 *
 * Behaviour-identical port of the legacy mu-plugin pair
 * (freescoutai-identify-config.php + woocommerce-identify.php). For logged-in
 * WooCommerce customers it signs a short-lived HS256 JWT with their identity
 * and calls the FreeScout chat identify endpoint from the browser, so the chat
 * widget can identify the customer without asking for their email.
 *
 * Loaded from the plugin bootstrap only when the identify constants are
 * defined (see fswa_chat_identify_maybe_load() in the main plugin file).
 *
 * Constants, normally in wp-config.php:
 *   FREESCOUTAI_IDENTIFY_SECRET   shared HMAC secret (from the FreeScout module settings)
 *   FREESCOUTAI_FREESCOUT_URL     FreeScout base URL
 *   FREESCOUTAI_MAILBOX_ID        mailbox id
 *   FREESCOUTAI_API_BASE          optional, default /api/freescoutai
 * Legacy CLAUDEAI_IDENTIFY_SECRET / CLAUDEAI_FREESCOUT_URL / CLAUDEAI_MAILBOX_ID
 * are still accepted as aliases.
 *
 * Double-run guard: the legacy mu-plugin defines the global function
 * freescoutai_output_identify_script(). While it exists, this port stays
 * inactive so the script and JWT can never be output twice.
 */

defined( 'ABSPATH' ) || exit;

/**
 * True while the legacy mu-plugin (woocommerce-identify.php) is loaded.
 * mu-plugins load before regular plugins, so this is reliable at bootstrap.
 */
function fswa_chat_identify_legacy_active(): bool {
	return function_exists( 'freescoutai_output_identify_script' );
}

if ( fswa_chat_identify_legacy_active() ) {
	return;
}

add_action( 'wp_head', 'fswa_output_chat_identify_script', 99 );
add_action( 'wp_footer', 'fswa_output_chat_identify_script', 99 );

/**
 * Build the JWT payload for a user.
 *
 * @param WP_User $user User.
 * @param int     $now  Current timestamp.
 */
function fswa_chat_identify_build_payload( $user, int $now ): array {
    $payload = [
        'email'      => $user->user_email,
        'first_name' => $user->first_name ?: $user->display_name,
        'last_name'  => $user->last_name,
        'wc_user_id' => $user->ID,
        'iat'        => $now,
        'exp'        => $now + 3600,
    ];

    $additional = [];
    if ( function_exists( 'wc_get_customer_order_count' ) ) {
        $additional['order_count'] = (int) wc_get_customer_order_count( $user->ID );
    }

    $billing_country = '';
    if ( class_exists( 'WC_Customer' ) ) {
        try {
            $wc_customer = new WC_Customer( $user->ID );
            $total_spent = (float) $wc_customer->get_total_spent();
            if ( $total_spent > 0 ) {
                $additional['total_spent_gbp'] = $total_spent;
            }
            $billing_country = (string) $wc_customer->get_billing_country();
        } catch ( \Exception $e ) {
            // WC_Customer is not guaranteed on every page load.
        }
    }

    if ( function_exists( 'get_woocommerce_currency' ) ) {
        $currency        = get_woocommerce_currency();
        $currency_symbol = function_exists( 'get_woocommerce_currency_symbol' )
            ? html_entity_decode( get_woocommerce_currency_symbol( $currency ), ENT_QUOTES, 'UTF-8' )
            : $currency . ' ';

        $prices_include_tax = get_option( 'woocommerce_prices_include_tax' ) === 'yes';
        $vat_rate           = 0.0;
        if ( class_exists( 'WC_Tax' ) && $billing_country ) {
            $tax_rates = WC_Tax::find_rates( [
                'country'   => $billing_country,
                'state'     => '',
                'postcode'  => '',
                'city'      => '',
                'tax_class' => '',
            ] );
            if ( ! empty( $tax_rates ) ) {
                $first_rate = reset( $tax_rates );
                $vat_rate   = (float) ( $first_rate['rate'] ?? 0 );
            }
        }

        $additional['currency']           = $currency;
        $additional['currency_symbol']    = $currency_symbol;
        $additional['prices_include_tax'] = $prices_include_tax;
        $additional['vat_rate']           = $vat_rate;
        if ( $billing_country ) {
            $additional['billing_country'] = $billing_country;
        }
    }

    if ( $additional ) {
        $payload['additional_attributes'] = $additional;
    }

    return $payload;
}

function fswa_output_chat_identify_script(): void {
    static $rendered = false;
    if ( $rendered || fswa_chat_identify_legacy_active() ) {
        return;
    }

    if ( ! is_user_logged_in() ) {
        return;
    }

    $rendered = true;

    $user    = wp_get_current_user();
    $secret  = FREESCOUTAI_IDENTIFY_SECRET;
    $payload = fswa_chat_identify_build_payload( $user, time() );

    $token         = fswa_chat_identify_build_jwt( $payload, $secret );
    $freescout_url = esc_url_raw( rtrim( FREESCOUTAI_FREESCOUT_URL, '/' ) );
    $mailbox_id    = (int) FREESCOUTAI_MAILBOX_ID;
    $api_base      = '/' . trim( (string) FREESCOUTAI_API_BASE, '/' );
    $email         = (string) $payload['email'];
    $first_name    = (string) ( $payload['first_name'] ?? '' );
    $last_name     = (string) ( $payload['last_name'] ?? '' );
    $name          = trim( $first_name . ' ' . $last_name ) ?: $email;
    $identify_url  = $freescout_url . $api_base . '/chat/identify';

    $json_token      = json_encode( $token, JSON_HEX_TAG | JSON_HEX_AMP );
    $json_url        = json_encode( $identify_url, JSON_HEX_TAG | JSON_HEX_AMP );
    $json_mailbox    = json_encode( $mailbox_id );
    $json_email      = json_encode( $email, JSON_HEX_TAG | JSON_HEX_AMP );
    $json_name       = json_encode( $name, JSON_HEX_TAG | JSON_HEX_AMP );
    $json_first_name = json_encode( $first_name, JSON_HEX_TAG | JSON_HEX_AMP );

    echo <<<HTML
<script id="freescoutai-identify">
(function () {
  'use strict';

  var IDENTIFY_URL  = {$json_url};
  var MAILBOX_ID    = {$json_mailbox};
  var JWT           = {$json_token};
  var PREFILL_EMAIL = {$json_email};
  var PREFILL_NAME  = {$json_name};
  var PREFILL_FIRST = {$json_first_name};
  var CACHE_KEY     = 'freescoutai_identified_' + MAILBOX_ID;

  function isCached() {
    try {
      var raw = localStorage.getItem(CACHE_KEY);
      if (!raw) return false;
      var entry = JSON.parse(raw);
      return entry && entry.exp > Date.now();
    } catch (e) { return false; }
  }

  function setCache(email) {
    try {
      localStorage.setItem(CACHE_KEY, JSON.stringify({
        email: email,
        exp: Date.now() + 3600 * 1000
      }));
    } catch (e) {}
  }

  function setWidgetIdentity(email, name, firstName) {
    window.__cwUser = {
      email: email,
      name: name,
      first_name: firstName || ''
    };

    try {
      window.dispatchEvent(new CustomEvent('freescoutai:identify', {
        detail: window.__cwUser
      }));
    } catch (e) {}
  }

  function prefillCurrentWidget(email, name, firstName) {
    try {
      var root = document.getElementById('cw-root');
      if (!root) return;

      var emailInput = root.querySelector('input[type="email"], input[name="email"]');
      var nameInput  = root.querySelector('input[name="name"], input[placeholder*="name" i]');

      if (emailInput && !emailInput.value) emailInput.value = email;
      if (nameInput && !nameInput.value) nameInput.value = firstName || name;
    } catch (e) {}
  }

  function identifyInWidget(email, name, firstName) {
    setWidgetIdentity(email, name, firstName);
    prefillCurrentWidget(email, name, firstName);

    if (window.FreeScoutW) {
      if (typeof window.FreeScoutW.identify === 'function') {
        window.FreeScoutW.identify({ email: email, name: name });
      } else if (window.FreeScoutW.c) {
        window.FreeScoutW.c.push(['identify', { email: email, name: name }]);
      }
    }

    try {
      document.querySelectorAll('iframe').forEach(function (iframe) {
        try {
          var doc = iframe.contentDocument || iframe.contentWindow.document;
          var emailInput = doc.querySelector('input[type="email"], input[name="email"]');
          var nameInput  = doc.querySelector('input[name="name"], input[placeholder*="name" i]');
          if (emailInput) emailInput.value = email;
          if (nameInput)  nameInput.value  = firstName || name;
        } catch (e) {}
      });
    } catch (e) {}
  }

  function fetchIdentify() {
    return fetch(IDENTIFY_URL, {
      method: 'POST',
      credentials: 'omit',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ token: JWT, mailbox_id: MAILBOX_ID })
    }).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (data) {
        return { ok: response.ok, status: response.status, data: data };
      });
    });
  }

  function doIdentify() {
    if (isCached()) return;

    fetchIdentify()
      .then(function (result) {
        if (!result.ok || !result.data || !result.data.ok) {
          console.warn('[FreescoutAI] Identify failed:', (result.data && result.data.error) || ('HTTP ' + result.status));
          return;
        }

        setCache(result.data.email);
        identifyInWidget(result.data.email, result.data.name, result.data.first_name);
        console.debug('[FreescoutAI] Customer identified:', result.data.email);
      })
      .catch(function (err) {
        console.warn('[FreescoutAI] Identify request error:', err);
      });
  }

  if (PREFILL_EMAIL) {
    identifyInWidget(PREFILL_EMAIL, PREFILL_NAME || PREFILL_EMAIL, PREFILL_FIRST || PREFILL_NAME || PREFILL_EMAIL);
  }

  var identified = false;
  function onceReady() {
    if (identified) return;
    identified = true;
    doIdentify();
  }

  ['freescout:ready', 'freescoutwidget:ready', 'chat:ready', 'widget:ready'].forEach(function (evt) {
    window.addEventListener(evt, onceReady);
  });

  if (document.readyState === 'complete') {
    setTimeout(onceReady, 800);
  } else {
    window.addEventListener('load', function () { setTimeout(onceReady, 800); });
  }
})();
</script>
HTML;
}

function fswa_chat_identify_build_jwt( array $payload, string $secret ): string {
    $header    = fswa_chat_identify_b64url( (string) json_encode( [ 'alg' => 'HS256', 'typ' => 'JWT' ] ) );
    $body      = fswa_chat_identify_b64url( (string) json_encode( $payload ) );
    $signature = fswa_chat_identify_b64url( hash_hmac( 'sha256', $header . '.' . $body, $secret, true ) );
    return $header . '.' . $body . '.' . $signature;
}

function fswa_chat_identify_b64url( string $data ): string {
    return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
}
