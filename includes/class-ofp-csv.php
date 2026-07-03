<?php
/**
 * OFP_CSV
 *
 * Generates monthly pipeline reports as CSV files.
 *
 * WHAT GETS GENERATED:
 *  Two CSV files per client per month:
 *  1. leads_{month}_{year}.csv       — all leads captured that month
 *  2. communications_{month}_{year}.csv — all messages sent that month
 *
 * FILES ARE STORED IN:
 *  wp-content/uploads/ofp-archives/{client_id}/
 *  Protected from direct browser access via .htaccess (generated on first use).
 *
 * ACCESS CONTROL:
 *  Each archive gets a 72-hour tokenised download URL.
 *  The token is stored in ofp_archives and validated before serving the file.
 *  The client template /reports handles the actual file serving.
 *
 * CALLED BY:
 *  - OFP_Cron_Handler::monthly_archive() — automatically on 1st of each month
 *  - Admin reports page — manually for any client/month combination
 *
 * Depends on: ofp_leads, ofp_communications_log, ofp_archives tables, OFP_Mailer.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OFP_CSV {

    /**
     * Generate monthly lead and communications CSV reports for a client.
     *
     * Creates the archive directory if it doesn't exist, writes both CSVs,
     * stores the archive record with a 72-hour download token, and emails
     * the client a download link.
     *
     * @param  int    $client_id  Client ID.
     * @param  int    $month      Month number (1–12).
     * @param  int    $year       Four-digit year.
     * @return array {
     *     @type string $leads_file  Absolute path to the leads CSV.
     *     @type string $comms_file  Absolute path to the communications CSV.
     *     @type string $token       Download token (72-hour expiry).
     * }|false  False on failure.
     */
    public static function generate_monthly_report(
        int $client_id,
        int $month,
        int $year
    ): array|false {
        global $wpdb;
        $p = $wpdb->prefix;

        $client = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$p}ofp_clients WHERE id = %d LIMIT 1",
                $client_id
            )
        );

        if ( ! $client ) {
            error_log( "[OFP_CSV] Client {$client_id} not found." );
            return false;
        }

        // ── Create archive directory ──────────────────────────────────────────
        $upload_dir  = wp_upload_dir();
        $archive_dir = trailingslashit( $upload_dir['basedir'] ) . 'ofp-archives/' . $client_id . '/';

        if ( ! file_exists( $archive_dir ) ) {
            wp_mkdir_p( $archive_dir );
            // Prevent direct browser access to archive files.
            file_put_contents(
                $archive_dir . '.htaccess',
                "deny from all\n"
            );
            file_put_contents(
                $archive_dir . 'index.php',
                "<?php // Silence is golden.\n"
            );
        }

        $month_padded = str_pad( $month, 2, '0', STR_PAD_LEFT );
        $period_label = $month_padded . '_' . $year;

        // ── 1. Leads CSV ─────────────────────────────────────────────────────
        $leads_file = $archive_dir . "leads_{$period_label}.csv";
        $leads      = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT name, phone, email, source, created_at, status,
                        ivr_response, converted_at, notes
                 FROM {$p}ofp_leads
                 WHERE client_id   = %d
                   AND MONTH(created_at) = %d
                   AND YEAR(created_at)  = %d
                 ORDER BY created_at ASC",
                $client_id, $month, $year
            )
        );

        $leads_fh = fopen( $leads_file, 'w' );
        if ( ! $leads_fh ) {
            error_log( "[OFP_CSV] Could not open leads file for writing: {$leads_file}" );
            return false;
        }

        // UTF-8 BOM so Excel opens the file correctly.
        fwrite( $leads_fh, "\xEF\xBB\xBF" );

        fputcsv( $leads_fh, [
            'Name', 'Phone', 'Email', 'Source',
            'Date Submitted', 'Status', 'IVR Response',
            'Converted At', 'Notes',
        ] );

        foreach ( $leads as $lead ) {
            fputcsv( $leads_fh, [
                $lead->name        ?? '',
                $lead->phone       ?? '',
                $lead->email       ?? '',
                $lead->source      ?? '',
                $lead->created_at  ?? '',
                $lead->status      ?? '',
                $lead->ivr_response ?? '',
                $lead->converted_at ?? '',
                $lead->notes       ?? '',
            ] );
        }

        fclose( $leads_fh );

        // ── 2. Communications CSV ─────────────────────────────────────────────
        $comms_file = $archive_dir . "communications_{$period_label}.csv";
        $comms      = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT l.name as lead_name, l.phone as lead_phone,
                        c.type, c.message, c.status, c.provider,
                        c.provider_ref, c.cost, c.sent_at
                 FROM {$p}ofp_communications_log c
                 JOIN {$p}ofp_leads l ON l.id = c.lead_id
                 WHERE c.client_id      = %d
                   AND MONTH(c.sent_at) = %d
                   AND YEAR(c.sent_at)  = %d
                 ORDER BY c.sent_at ASC",
                $client_id, $month, $year
            )
        );

        $comms_fh = fopen( $comms_file, 'w' );
        if ( ! $comms_fh ) {
            error_log( "[OFP_CSV] Could not open comms file for writing: {$comms_file}" );
            return false;
        }

        fwrite( $comms_fh, "\xEF\xBB\xBF" );

        fputcsv( $comms_fh, [
            'Lead Name', 'Lead Phone', 'Type', 'Message',
            'Status', 'Provider', 'Provider Ref',
            'Cost (NGN)', 'Sent At',
        ] );

        foreach ( $comms as $comm ) {
            fputcsv( $comms_fh, [
                $comm->lead_name   ?? '',
                $comm->lead_phone  ?? '',
                $comm->type        ?? '',
                $comm->message     ?? '',
                $comm->status      ?? '',
                $comm->provider    ?? '',
                $comm->provider_ref ?? '',
                $comm->cost        ?? '0',
                $comm->sent_at     ?? '',
            ] );
        }

        fclose( $comms_fh );

        // ── 3. Record archive + generate download token ───────────────────────
        $token        = bin2hex( random_bytes( 32 ) );
        $token_expiry = gmdate( 'Y-m-d H:i:s', strtotime( '+72 hours' ) );
        $file_size    = filesize( $leads_file ) + filesize( $comms_file );

        // Delete any existing archive for this client + period to avoid duplicates.
        $wpdb->delete(
            $p . 'ofp_archives',
            [ 'client_id' => $client_id, 'period' => $period_label ]
        );

        $wpdb->insert(
            $p . 'ofp_archives',
            [
                'client_id'      => $client_id,
                'period'         => $period_label,
                'file_path'      => $leads_file . '|' . $comms_file,
                'file_size'      => $file_size,
                'download_token' => $token,
                'token_expires'  => $token_expiry,
                'created_at'     => current_time( 'mysql' ),
            ]
        );

        // ── 4. Email client the download link ─────────────────────────────────
        $month_name   = gmdate( 'F', mktime( 0, 0, 0, $month, 1 ) );
        $period_human = $month_name . ' ' . $year;
        $download_url = add_query_arg( 'token', $token, home_url( '/reports' ) );

        OFP_Mailer::send_monthly_report( $client, $period_human, $download_url );

        return [
            'leads_file' => $leads_file,
            'comms_file' => $comms_file,
            'token'      => $token,
        ];
    }

    /**
     * Regenerate the download token for an existing archive.
     * Used when an admin regenerates a report from the admin reports page.
     *
     * @param  int $archive_id  Archive row ID.
     * @return string|false     New token, or false if archive not found.
     */
    public static function refresh_token( int $archive_id ): string|false {
        global $wpdb;

        $archive = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ofp_archives WHERE id = %d LIMIT 1",
                $archive_id
            )
        );

        if ( ! $archive ) return false;

        $token  = bin2hex( random_bytes( 32 ) );
        $expiry = gmdate( 'Y-m-d H:i:s', strtotime( '+72 hours' ) );

        $wpdb->update(
            $wpdb->prefix . 'ofp_archives',
            [ 'download_token' => $token, 'token_expires' => $expiry ],
            [ 'id' => $archive_id ]
        );

        return $token;
    }

    /**
     * Get a summary stats array for a client's monthly report.
     * Used in admin Reports view and client /reports page.
     *
     * @param  int $client_id  Client ID.
     * @param  int $month      Month number.
     * @param  int $year       Year.
     * @return array
     */
    public static function get_monthly_summary(
        int $client_id,
        int $month,
        int $year
    ): array {
        global $wpdb;
        $p = $wpdb->prefix;

        return [
            'leads'      => (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$p}ofp_leads
                 WHERE client_id = %d AND MONTH(created_at) = %d AND YEAR(created_at) = %d",
                $client_id, $month, $year
            ) ),
            'converted'  => (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$p}ofp_leads
                 WHERE client_id = %d AND status = 'converted'
                   AND MONTH(created_at) = %d AND YEAR(created_at) = %d",
                $client_id, $month, $year
            ) ),
            'sms_sent'   => (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$p}ofp_communications_log
                 WHERE client_id = %d AND type = 'sms'
                   AND MONTH(sent_at) = %d AND YEAR(sent_at) = %d",
                $client_id, $month, $year
            ) ),
            'calls_made' => (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$p}ofp_communications_log
                 WHERE client_id = %d AND type = 'voice'
                   AND MONTH(sent_at) = %d AND YEAR(sent_at) = %d",
                $client_id, $month, $year
            ) ),
            'credit_used' => (float) $wpdb->get_var( $wpdb->prepare(
                "SELECT SUM(cost) FROM {$p}ofp_communications_log
                 WHERE client_id = %d
                   AND MONTH(sent_at) = %d AND YEAR(sent_at) = %d",
                $client_id, $month, $year
            ) ),
        ];
    }
}
