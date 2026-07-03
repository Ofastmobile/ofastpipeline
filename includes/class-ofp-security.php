<?php
/**
 * OFP_Security
 *
 * Central security utility class. Handles:
 *  - Input sanitization (phone numbers)
 *  - AES-256-CBC encryption / decryption (for client API keys at rest)
 *  - IP-based rate limiting (stored in ofp_rate_limits table)
 *  - Cloudflare Turnstile server-side token verification
 *
 * Used by: OFP_REST_API, OFP_Auth, OFP_Client_Portal, OFP_Client
 * Depends on: ofp_rate_limits table (created by OFP_Activator)
 *             ofp_encryption_key / ofp_encryption_iv wp_options (set by OFP_Activator)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OFP_Security {

    // ─────────────────────────────────────────────────────────────────────────
    // SANITIZATION
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Strip everything except digits and the leading + from a phone number.
     * Works for Nigerian formats: 08012345678, +2348012345678, 2348012345678
     *
     * @param  string $input Raw phone input from a form or API.
     * @return string        Cleaned phone string.
     */
    public static function sanitize_phone( string $input ): string {
        // Keep only digits and a leading +
        $cleaned = preg_replace( '/[^0-9+]/', '', trim( $input ) );

        // Normalise: if it starts with 0 and is 11 digits, it's a local Nigerian number.
        // Leave as-is — Africa's Talking and BulkSMSNigeria both accept 08XXXXXXXXX.
        return $cleaned;
    }

    /**
     * Validate that a sanitised phone number looks plausible.
     * Minimum 10 digits, maximum 15 (E.164 max without +).
     *
     * @param  string $phone Already sanitised phone string.
     * @return bool
     */
    public static function is_valid_phone( string $phone ): bool {
        $digits_only = preg_replace( '/[^0-9]/', '', $phone );
        $len         = strlen( $digits_only );
        return $len >= 10 && $len <= 15;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ENCRYPTION / DECRYPTION
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Encrypt a plaintext value using AES-256-CBC.
     * Keys are stored in wp_options, generated once on plugin activation.
     *
     * @param  string $value Plaintext to encrypt (e.g. an API key).
     * @return string        Base64-encoded ciphertext, or empty string on failure.
     */
    public static function encrypt( string $value ): string {
        if ( empty( $value ) ) {
            return '';
        }

        $key = get_option( 'ofp_encryption_key', '' );
        $iv  = substr( get_option( 'ofp_encryption_iv', '' ), 0, 16 );

        if ( ! $key || ! $iv ) {
            // Keys not set yet — should never happen after activation.
            return $value;
        }

        $encrypted = openssl_encrypt( $value, 'AES-256-CBC', $key, 0, $iv );
        return $encrypted !== false ? base64_encode( $encrypted ) : '';
    }

    /**
     * Decrypt a value previously encrypted with self::encrypt().
     *
     * @param  string $encrypted Base64-encoded ciphertext.
     * @return string            Decrypted plaintext, or empty string on failure.
     */
    public static function decrypt( string $encrypted ): string {
        if ( empty( $encrypted ) ) {
            return '';
        }

        $key = get_option( 'ofp_encryption_key', '' );
        $iv  = substr( get_option( 'ofp_encryption_iv', '' ), 0, 16 );

        if ( ! $key || ! $iv ) {
            return $encrypted;
        }

        $decrypted = openssl_decrypt(
            base64_decode( $encrypted ),
            'AES-256-CBC',
            $key,
            0,
            $iv
        );

        return $decrypted !== false ? $decrypted : '';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // RATE LIMITING
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Check if an IP has exceeded the allowed action count within the time window.
     * Inserts a new record if not exceeded, terminates with 429 if exceeded.
     *
     * Old records are cleaned up by the monthly cron (OFP_Cron_Handler).
     *
     * @param string $ip     Visitor IP address.
     * @param string $action Identifier for the action (e.g. 'lead_submit', 'client_login').
     * @param int    $max    Maximum allowed attempts within $window seconds.
     * @param int    $window Time window in seconds.
     *
     * @return void  Terminates execution with wp_die() if limit exceeded.
     */
    public static function check_rate_limit(
        string $ip,
        string $action,
        int $max    = 5,
        int $window = 300
    ): void {
        global $wpdb;

        $table = $wpdb->prefix . 'ofp_rate_limits';

        // Count how many attempts this IP has made for this action in the window.
        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                 WHERE ip = %s
                   AND action = %s
                   AND created_at > DATE_SUB(NOW(), INTERVAL %d SECOND)",
                $ip,
                $action,
                $window
            )
        );

        if ( $count >= $max ) {
            // Return a polite JSON response for AJAX / REST callers,
            // or a plain page for direct browser hits.
            if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
                wp_send_json(
                    [ 'success' => false, 'message' => 'Too many attempts. Please try again later.' ],
                    429
                );
            }
            wp_die(
                esc_html__( 'Too many attempts. Please try again later.', 'ofast-pipeline' ),
                esc_html__( 'Rate Limited', 'ofast-pipeline' ),
                [ 'response' => 429 ]
            );
        }

        // Log this attempt.
        $wpdb->insert(
            $table,
            [
                'ip'         => $ip,
                'action'     => $action,
                'created_at' => current_time( 'mysql' ),
            ]
        );
    }

    /**
     * Return the real client IP, respecting Cloudflare's CF-Connecting-IP header.
     * Falls back to REMOTE_ADDR if the CF header is absent.
     *
     * @return string
     */
    public static function get_client_ip(): string {
        // Cloudflare passes the real visitor IP in this header.
        if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
            return sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
        }

        if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            // X-Forwarded-For can be a comma-separated list; take the first.
            $ips = explode( ',', wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
            return trim( sanitize_text_field( $ips[0] ) );
        }

        return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' ) );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CLOUDFLARE TURNSTILE VERIFICATION
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Verify a Cloudflare Turnstile token with Cloudflare's siteverify API.
     *
     * The secret key is stored in wp_options as 'ofp_turnstile_secret'.
     * Configure it via wp-admin → OFast Pipeline → Settings.
     *
     * @param  string|null $token The cf-turnstile-response value from the form submission.
     * @return bool               True if valid, false if invalid or API error.
     */
    public static function verify_turnstile( ?string $token ): bool {
        if ( empty( $token ) ) {
            return false;
        }

        $secret = get_option( 'ofp_turnstile_secret', '' );

        // If no secret is configured yet (e.g. during local dev), skip verification.
        // Remove this bypass before going to production.
        if ( empty( $secret ) ) {
            return true; // ← REMOVE IN PRODUCTION
        }

        $response = wp_remote_post(
            'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            [
                'body'    => [
                    'secret'   => $secret,
                    'response' => $token,
                    'remoteip' => self::get_client_ip(),
                ],
                'timeout' => 10,
            ]
        );

        if ( is_wp_error( $response ) ) {
            // Log the error silently; don't block the user on an API failure.
            error_log( '[OFP] Turnstile verification error: ' . $response->get_error_message() );
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ) );
        return isset( $body->success ) && $body->success === true;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // NONCE HELPERS (used in admin forms)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Output a hidden nonce field for an OFP admin form.
     *
     * @param string $action Unique nonce action name.
     */
    public static function nonce_field( string $action ): void {
        wp_nonce_field( 'ofp_' . $action, 'ofp_nonce' );
    }

    /**
     * Verify a nonce submitted from an OFP admin form.
     * Terminates with wp_die() on failure.
     *
     * @param string $action Unique nonce action name.
     */
    public static function verify_nonce( string $action ): void {
        $nonce = isset( $_POST['ofp_nonce'] )
            ? sanitize_text_field( wp_unslash( $_POST['ofp_nonce'] ) )
            : '';

        if ( ! wp_verify_nonce( $nonce, 'ofp_' . $action ) ) {
            wp_die(
                esc_html__( 'Security check failed. Please refresh and try again.', 'ofast-pipeline' ),
                esc_html__( 'Security Error', 'ofast-pipeline' ),
                [ 'response' => 403 ]
            );
        }
    }
}
