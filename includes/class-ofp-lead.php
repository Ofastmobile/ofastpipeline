<?php
/**
 * OFP_Lead
 *
 * Handles all lead record operations.
 *
 * LEAD STATUSES:
 *  new        → just captured, no contact made yet
 *  contacted  → at least one SMS or call has been sent
 *  interested → lead responded to IVR (pressed a digit)
 *  converted  → business owner marked as won
 *  dead       → no response after full sequence, manually closed
 *
 * Depends on: ofp_leads table, OFP_Security.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OFP_Lead {

    /**
     * Create a new lead record.
     *
     * @param  int         $client_id    Owning client ID.
     * @param  string      $name         Lead name (may be empty).
     * @param  string      $phone        Lead phone (required).
     * @param  string|null $email        Lead email (optional).
     * @param  string|null $source       Referrer URL or label.
     * @param  int|null    $property_id  Property ID for listing inquiries (v2.1).
     * @return int                       New lead ID.
     */
    public static function create(
        int $client_id,
        string $name,
        string $phone,
        ?string $email      = null,
        ?string $source     = null,
        ?int $property_id   = null
    ): int {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'ofp_leads',
            [
                'client_id'   => $client_id,
                'property_id' => $property_id,
                'name'        => sanitize_text_field( $name ),
                'phone'       => OFP_Security::sanitize_phone( $phone ),
                'email'       => $email ? sanitize_email( $email ) : null,
                'source'      => $source ? sanitize_text_field( $source ) : 'direct',
                'ip_address'  => OFP_Security::get_client_ip(),
                'status'      => 'new',
                'created_at'  => current_time( 'mysql' ),
            ]
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * Fetch a single lead by ID.
     *
     * @param  int         $id
     * @return object|null
     */
    public static function get( int $id ): ?object {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ofp_leads WHERE id = %d LIMIT 1",
                $id
            )
        );
    }

    /**
     * Fetch all leads for a client, newest first.
     *
     * @param  int         $client_id
     * @param  string|null $status     Optional filter.
     * @param  int         $limit      0 = no limit.
     * @return array
     */
    public static function for_client(
        int $client_id,
        ?string $status = null,
        int $limit      = 0
    ): array {
        global $wpdb;

        $where = 'client_id = %d';
        $args  = [ $client_id ];

        if ( $status ) {
            $where .= ' AND status = %s';
            $args[] = $status;
        }

        $limit_sql = $limit > 0 ? "LIMIT {$limit}" : '';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ofp_leads
                 WHERE {$where}
                 ORDER BY created_at DESC {$limit_sql}",
                ...$args
            )
        );
    }

    /**
     * Check if a phone number already submitted a lead for this client
     * within the given time window. Prevents duplicate submissions.
     *
     * @param  int    $client_id
     * @param  string $phone
     * @param  int    $within_hours  Default 24.
     * @return bool
     */
    public static function is_duplicate(
        int $client_id,
        string $phone,
        int $within_hours = 24
    ): bool {
        global $wpdb;

        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ofp_leads
                 WHERE client_id = %d
                   AND phone     = %s
                   AND created_at > DATE_SUB( NOW(), INTERVAL %d HOUR )
                 LIMIT 1",
                $client_id,
                $phone,
                $within_hours
            )
        );
    }

    /**
     * Update a lead's status.
     * Stamps converted_at when status = 'converted'.
     *
     * @param  int    $id
     * @param  string $status
     * @return void
     */
    public static function update_status( int $id, string $status ): void {
        global $wpdb;

        $data = [ 'status' => sanitize_text_field( $status ) ];

        if ( $status === 'converted' ) {
            $data['converted_at'] = current_time( 'mysql' );
        }

        $wpdb->update( $wpdb->prefix . 'ofp_leads', $data, [ 'id' => $id ] );
    }

    /**
     * Store the IVR digit response on the lead.
     * Called by OFP_IVR::handle_callback().
     *
     * @param  int    $id
     * @param  string $digit
     * @return void
     */
    public static function record_ivr_response( int $id, string $digit ): void {
        global $wpdb;

        $wpdb->update(
            $wpdb->prefix . 'ofp_leads',
            [
                'ivr_response' => sanitize_text_field( $digit ),
                'status'       => 'interested',
            ],
            [ 'id' => $id ]
        );
    }

    /**
     * Append a timestamped note to a lead.
     *
     * @param  int    $id
     * @param  string $note
     * @return void
     */
    public static function add_note( int $id, string $note ): void {
        global $wpdb;

        $lead = self::get( $id );
        if ( ! $lead ) return;

        $timestamp = current_time( 'mysql' );
        $existing  = $lead->notes ? $lead->notes . "\n\n" : '';

        $wpdb->update(
            $wpdb->prefix . 'ofp_leads',
            [ 'notes' => $existing . "[{$timestamp}] " . sanitize_textarea_field( $note ) ],
            [ 'id'    => $id ]
        );
    }

    /**
     * Return summary stats for a client's leads.
     *
     * @param  int   $client_id
     * @return array
     */
    public static function get_stats( int $client_id ): array {
        global $wpdb;
        $p = $wpdb->prefix;

        return [
            'total'      => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$p}ofp_leads WHERE client_id = %d", $client_id ) ),
            'today'      => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$p}ofp_leads WHERE client_id = %d AND DATE(created_at) = CURDATE()", $client_id ) ),
            'this_month' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$p}ofp_leads WHERE client_id = %d AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())", $client_id ) ),
            'converted'  => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$p}ofp_leads WHERE client_id = %d AND status = 'converted'", $client_id ) ),
            'interested' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$p}ofp_leads WHERE client_id = %d AND status = 'interested'", $client_id ) ),
        ];
    }
}
