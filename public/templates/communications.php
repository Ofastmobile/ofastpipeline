<?php
/**
 * Template: /communications
 * Client's full communication log - every SMS, voice call, and email sent.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

OFP_Auth::require_client_login();
$client = OFP_Auth::current_client();
OFP_Auth::require_active_subscription( $client );

if ( ! OFP_Subscription::has_active( 'crm', $client->id ) ) {
    wp_safe_redirect( home_url( '/dashboard' ) );
    exit;
}

global $wpdb;
$p = $wpdb->prefix;

$filter_type  = sanitize_text_field( $_GET['type'] ?? '' );
$per_page     = 25;
$current_page = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
$offset       = ( $current_page - 1 ) * $per_page;

$where = 'cl.client_id = %d';
$args  = [ $client->id ];

if ( $filter_type ) {
    $where .= ' AND cl.type = %s';
    $args[] = $filter_type;
}

$total = (int) $wpdb->get_var(
    $wpdb->prepare( "SELECT COUNT(*) FROM {$p}ofp_communications_log cl WHERE {$where}", ...$args )
);

$comms = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT cl.*, l.name as lead_name, l.phone as lead_phone
         FROM {$p}ofp_communications_log cl
         JOIN {$p}ofp_leads l ON l.id = cl.lead_id
         WHERE {$where}
         ORDER BY cl.sent_at DESC
         LIMIT %d OFFSET %d",
        ...array_merge( $args, [ $per_page, $offset ] )
    )
);

$total_pages = ceil( $total / $per_page );

// Summary counts
$sms_count   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$p}ofp_communications_log WHERE client_id = %d AND type = 'sms'",   $client->id ) );
$voice_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$p}ofp_communications_log WHERE client_id = %d AND type = 'voice'", $client->id ) );
$total_cost  = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(cost) FROM {$p}ofp_communications_log WHERE client_id = %d", $client->id ) );

$type_badges = [
    'sms'   => '<span class="ofp-badge ofp-badge-blue">SMS</span>',
    'voice' => '<span class="ofp-badge ofp-badge-green">Voice</span>',
    'email' => '<span class="ofp-badge ofp-badge-yellow">Email</span>',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Communications — OFast Pipeline</title>
    <?php wp_head(); ?>
    <link rel="stylesheet" href="<?php echo esc_url( OFP_URL . 'assets/css/client-portal.css' ); ?>">
</head>
<body class="ofp-portal-body">

<?php include OFP_PATH . 'public/templates/partials/nav.php'; ?>

<div class="ofp-container">

    <div class="ofp-page-header">
        <h1>Communications</h1>
        <p>Every message sent to your leads through the pipeline.</p>
    </div>

    <!-- Summary -->
    <div class="ofp-stats-grid">
        <div class="ofp-stat-card">
            <span class="ofp-stat-number accent"><?php echo esc_html( number_format( $sms_count ) ); ?></span>
            <span class="ofp-stat-label">SMS Sent</span>
        </div>
        <div class="ofp-stat-card">
            <span class="ofp-stat-number"><?php echo esc_html( number_format( $voice_count ) ); ?></span>
            <span class="ofp-stat-label">Calls Made</span>
        </div>
        <div class="ofp-stat-card">
            <span class="ofp-stat-number">₦<?php echo esc_html( number_format( $total_cost, 0 ) ); ?></span>
            <span class="ofp-stat-label">Total Credit Used</span>
        </div>
    </div>

    <!-- Filter -->
    <div style="display:flex;gap:4px;margin-bottom:16px;border-bottom:2px solid #e5e7eb;">
        <?php foreach ( [ '' => 'All', 'sms' => 'SMS', 'voice' => 'Voice', 'email' => 'Email' ] as $val => $label ) : ?>
            <a href="<?php echo esc_url( add_query_arg( 'type', $val, home_url( '/communications' ) ) ); ?>"
               style="padding:8px 14px;font-size:13px;font-weight:500;text-decoration:none;border-bottom:2px solid <?php echo $filter_type === $val ? '#1a73e8' : 'transparent'; ?>;margin-bottom:-2px;color:<?php echo $filter_type === $val ? '#1a73e8' : '#6b7280'; ?>;">
                <?php echo esc_html( $label ); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="ofp-card" style="padding:0;overflow:hidden;">
        <?php if ( empty( $comms ) ) : ?>
            <div class="ofp-empty" style="padding:48px;">
                <div class="ofp-empty-icon">💬</div>
                <h3>No communications yet</h3>
                <p>Messages sent to your leads will appear here.</p>
            </div>
        <?php else : ?>
            <div class="ofp-table-wrap">
                <table class="ofp-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Lead</th>
                            <th>Message Preview</th>
                            <th>Status</th>
                            <th>Cost</th>
                            <th>Sent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $comms as $comm ) : ?>
                            <tr>
                                <td><?php echo $type_badges[ $comm->type ] ?? esc_html( strtoupper( $comm->type ) ); ?></td>
                                <td>
                                    <strong><?php echo esc_html( $comm->lead_name ?: $comm->lead_phone ); ?></strong><br>
                                    <span style="font-size:12px;color:#9ca3af;"><?php echo esc_html( $comm->lead_phone ); ?></span>
                                </td>
                                <td style="max-width:240px;">
                                    <span style="font-size:13px;color:#374151;" title="<?php echo esc_attr( $comm->message ); ?>">
                                        <?php echo esc_html( mb_substr( $comm->message ?? '', 0, 60 ) . ( mb_strlen( $comm->message ?? '' ) > 60 ? '…' : '' ) ); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $sc = $comm->status === 'sent' ? 'ofp-badge-green' : 'ofp-badge-red';
                                    echo '<span class="ofp-badge ' . esc_attr( $sc ) . '">' . esc_html( ucfirst( $comm->status ) ) . '</span>';
                                    ?>
                                </td>
                                <td style="font-size:13px;">₦<?php echo esc_html( number_format( (float) $comm->cost, 2 ) ); ?></td>
                                <td style="font-size:12px;color:#9ca3af;white-space:nowrap;">
                                    <?php echo esc_html( human_time_diff( strtotime( $comm->sent_at ), current_time( 'timestamp' ) ) . ' ago' ); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ( $total_pages > 1 ) : ?>
                <div class="ofp-pagination" style="padding:16px;">
                    <?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
                        <a href="<?php echo esc_url( add_query_arg( [ 'paged' => $i, 'type' => $filter_type ], home_url( '/communications' ) ) ); ?>"
                           class="ofp-page-btn <?php echo $i === $current_page ? 'active' : ''; ?>">
                            <?php echo esc_html( $i ); ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

</div>
</main>
</div><!-- .ofp-shell -->

<?php wp_footer(); ?>
</body>
</html>
