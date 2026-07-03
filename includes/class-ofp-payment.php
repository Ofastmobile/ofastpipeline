<?php
/**
 * OFP_Payment
 *
 * Provider-agnostic payment gateway interface.
 *
 * ARCHITECTURE:
 *  This class is the ONLY payment entry point for the rest of the plugin.
 *  OFP_Client, OFP_Subscription, OFP_REST_API all call OFP_Payment methods.
 *  They never talk to a gateway class directly.
 *
 *  The active provider is set in wp-admin → OFast Pipeline → Settings.
 *  Switching from Monnify to Paystack = changing one setting, zero code changes.
 *
 * SUPPORTED GATEWAYS:
 *  - monnify      (Monnify Virtual Accounts)
 *  - paystack     (Paystack Dedicated Virtual Accounts)
 *  - flutterwave  (Flutterwave Virtual Account Numbers)
 *
 * ADDING A NEW GATEWAY:
 *  1. Create includes/gateways/class-ofp-gateway-{slug}.php
 *  2. Implement the OFP_Gateway_Interface methods
 *  3. Add the slug to SUPPORTED_GATEWAYS
 *  4. Add its credentials to the Settings page
 *  That is all. No other file needs to change.
 *
 * VIRTUAL ACCOUNT STANDARD:
 *  create_virtual_account() always returns a stdClass with:
 *   ->account_number  (string)
 *   ->bank_name       (string)
 *  Or null on failure. All gateway adapters normalise to this format.
 *
 * Depends on: gateway adapter classes, wp_options for provider config.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OFP_Payment {

    const SUPPORTED_GATEWAYS = [ 'monnify', 'paystack', 'flutterwave' ];

    // ─────────────────────────────────────────────────────────────────────────
    // GATEWAY RESOLVER
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Get an instance of the configured gateway adapter.
     *
     * @return OFP_Gateway_Interface|null  Null if provider not configured or unsupported.
     */
    private static function get_gateway(): ?object {
        $provider = get_option( 'ofp_payment_provider', 'monnify' );

        if ( ! in_array( $provider, self::SUPPORTED_GATEWAYS, true ) ) {
            error_log( "[OFP_Payment] Unsupported provider: {$provider}" );
            return null;
        }

        $class = 'OFP_Gateway_' . ucfirst( $provider );

        if ( ! class_exists( $class ) ) {
            error_log( "[OFP_Payment] Gateway class not found: {$class}" );
            return null;
        }

        return new $class();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUBLIC INTERFACE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Create a dedicated virtual bank account for a client.
     *
     * Called by OFP_Client::create() during onboarding.
     * Returns a normalised object regardless of which gateway handled it.
     *
     * @param  array $client_data {
     *     @type string $business_name
     *     @type string $owner_name
     *     @type string $email
     * }
     * @param  int   $client_id  The OFP client ID (used as account reference).
     * @return object|null       stdClass with ->account_number and ->bank_name, or null.
     */
    public static function create_virtual_account( array $client_data, int $client_id ): ?object {
        $gateway = self::get_gateway();
        if ( ! $gateway ) return null;

        return $gateway->create_virtual_account( $client_data, $client_id );
    }

    /**
     * Handle an incoming payment webhook from the configured gateway.
     *
     * Called by OFP_REST_API::payment_webhook().
     * Each gateway verifies its own signature before processing.
     *
     * @param  WP_REST_Request $request  The incoming webhook request.
     * @return WP_REST_Response
     */
    public static function handle_webhook( WP_REST_Request $request ): WP_REST_Response {
        $gateway = self::get_gateway();

        if ( ! $gateway ) {
            return new WP_REST_Response( [ 'error' => 'No payment provider configured.' ], 500 );
        }

        return $gateway->handle_webhook( $request );
    }

    /**
     * Get the name of the currently configured payment provider.
     *
     * @return string  e.g. 'monnify', 'paystack', 'flutterwave'
     */
    public static function get_provider(): string {
        return get_option( 'ofp_payment_provider', 'monnify' );
    }

    /**
     * Check whether payment is fully configured and ready.
     * Used by the Settings page to show a status indicator.
     *
     * @return bool
     */
    public static function is_configured(): bool {
        $gateway = self::get_gateway();
        if ( ! $gateway ) return false;
        return $gateway->is_configured();
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// GATEWAY INTERFACE
// Defines the contract every gateway adapter must fulfil.
// ─────────────────────────────────────────────────────────────────────────────

interface OFP_Gateway_Interface {

    /**
     * Create a dedicated virtual account for a client.
     *
     * @param  array $client_data  Business name, owner name, email.
     * @param  int   $client_id    OFP client ID used as the account reference.
     * @return object|null         stdClass { account_number, bank_name } or null.
     */
    public function create_virtual_account( array $client_data, int $client_id ): ?object;

    /**
     * Handle and verify an incoming webhook from this gateway.
     *
     * @param  WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_webhook( WP_REST_Request $request ): WP_REST_Response;

    /**
     * Check if this gateway has its required credentials configured.
     *
     * @return bool
     */
    public function is_configured(): bool;
}
