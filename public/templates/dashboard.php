<?php
/**
 * Template: /dashboard
 * Client's main overview page.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

OFP_Auth::require_client_login();
$client = OFP_Auth::current_client();
OFP_Auth::require_active_subscription( $client );

$has_crm     = OFP_Subscription::has_active( 'crm',     $client->id );
$has_listing = OFP_Subscription::has_active( 'listing', $client->id );

// Stats
$stats   = OFP_Lead::get_stats( $client->id );
$credits = OFP_Credit::get( $client->id );

// Recent leads
$recent_leads = OFP_Lead::for_client( $client->id, null, 5 );

// Credit percentages
$sms_pct   = $credits && $credits->sms_loaded   > 0 ? round( ( $credits->sms_remaining   / $credits->sms_loaded )   * 100 ) : 0;
$voice_pct = $credits && $credits->voice_loaded > 0 ? round( ( $credits->voice_remaining / $credits->voice_loaded ) * 100 ) : 0;

$status_badges = [
    'new'        => '<span class="ofp-badge ofp-badge-blue">New</span>',
    'contacted'  => '<span class="ofp-badge ofp-badge-yellow">Contacted</span>',
    'interested' => '<span class="ofp-badge ofp-badge-orange">Interested</span>',
    'converted'  => '<span class="ofp-badge ofp-badge-green">✅ Converted</span>',
    'dead'       => '<span class="ofp-badge ofp-badge-grey">Dead</span>',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — OFast Pipeline</title>
    <?php wp_head(); ?>
    <link rel="stylesheet" href="<?php echo esc_url( OFP_URL . 'assets/css/client-portal.css' ); ?>">
</head>
<body class="ofp-portal-body">

<?php include OFP_PATH . 'public/templates/partials/nav.php'; ?>

<div class="ofp-container">

    <div class="ofp-page-header">
        <h1>Welcome back, <?php echo esc_html( $client->owner_name ); ?>!</h1>
        <p><?php echo esc_html( $client->business_name ); ?></p>
    </div>

    <?php if ( isset( $_GET['preview'] ) && $_GET['preview'] === '1' ) : ?>
        <div class="ofp-sub-banner" style="background:linear-gradient(135deg,#dbeafe,#fff);border-color:#bfdbfe;">
            <p style="color:#1e40af;">
                🔍 <strong>Admin Preview Mode</strong> — you are viewing this dashboard as
                <?php echo esc_html( $client->business_name ); ?> for debugging purposes.
            </p>
        </div>
    <?php endif; ?>

    <?php if ( in_array( $client->status, [ 'grace', 'pending_review' ], true ) ) : ?>
        <div class="ofp-sub-banner">
            <p>
                <?php if ( $client->status === 'grace' ) : ?>
                    ⚠️ Your subscription expired. You are in a <strong>5-day grace period</strong>.
                    Please renew to avoid suspension.
                <?php else : ?>
                    ⏳ Your account is <strong>pending review</strong>. We will notify you once approved.
                <?php endif; ?>
            </p>
            <?php if ( $client->virtual_account_number ) : ?>
                <div style="font-size:13px;color:#92400e;">
                    Pay to: <strong><?php echo esc_html( $client->virtual_bank_name ); ?></strong>
                    — <strong><?php echo esc_html( $client->virtual_account_number ); ?></strong>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ( $has_crm ) : ?>

        <!-- Lead Stats -->
        <div class="ofp-stats-grid">
            <div class="ofp-stat-card">
                <span class="ofp-stat-number accent"><?php echo esc_html( $stats['today'] ); ?></span>
                <span class="ofp-stat-label">Leads Today</span>
            </div>
            <div class="ofp-stat-card">
                <span class="ofp-stat-number"><?php echo esc_html( $stats['this_month'] ); ?></span>
                <span class="ofp-stat-label">This Month</span>
            </div>
            <div class="ofp-stat-card">
                <span class="ofp-stat-number"><?php echo esc_html( $stats['total'] ); ?></span>
                <span class="ofp-stat-label">Total Leads</span>
            </div>
            <div class="ofp-stat-card">
                <span class="ofp-stat-number"><?php echo esc_html( $stats['converted'] ); ?></span>
                <span class="ofp-stat-label">Converted</span>
            </div>
            <div class="ofp-stat-card">
                <span class="ofp-stat-number"><?php echo $stats['total'] > 0 ? esc_html( round( ( $stats['converted'] / $stats['total'] ) * 100 ) ) : 0; ?>%</span>
                <span class="ofp-stat-label">Conv. Rate</span>
            </div>
        </div>

        <!-- Credit Bars -->
        <div class="ofp-card">
            <h3>💳 Credit Balance</h3>

            <?php if ( $credits && $credits->paused ) : ?>
                <div class="ofp-alert ofp-alert-error" style="margin-bottom:16px;">
                    ⛔ Your pipeline is <strong>paused</strong> — credit balance exhausted.
                    Please top up to resume automation.
                </div>
            <?php endif; ?>

            <div class="ofp-credit-bar-wrap">
                <div class="ofp-credit-bar-label">
                    <strong>SMS Credit</strong>
                    <span>NGN <?php echo number_format( (float) ( $credits->sms_remaining ?? 0 ), 2 ); ?> remaining (<?php echo esc_html( $sms_pct ); ?>%)</span>
                </div>
                <div class="ofp-credit-bar-track">
                    <div class="ofp-credit-bar-fill <?php echo $sms_pct > 40 ? 'high' : ( $sms_pct > 15 ? 'medium' : 'low' ); ?>"
                         style="width:<?php echo esc_attr( $sms_pct ); ?>%"></div>
                </div>
            </div>

            <div class="ofp-credit-bar-wrap">
                <div class="ofp-credit-bar-label">
                    <strong>Voice Credit</strong>
                    <span>NGN <?php echo number_format( (float) ( $credits->voice_remaining ?? 0 ), 2 ); ?> remaining (<?php echo esc_html( $voice_pct ); ?>%)</span>
                </div>
                <div class="ofp-credit-bar-track">
                    <div class="ofp-credit-bar-fill <?php echo $voice_pct > 40 ? 'high' : ( $voice_pct > 15 ? 'medium' : 'low' ); ?>"
                         style="width:<?php echo esc_attr( $voice_pct ); ?>%"></div>
                </div>
            </div>

            <a href="<?php echo esc_url( home_url( '/credits' ) ); ?>" class="ofp-btn ofp-btn-secondary" style="margin-top:8px;font-size:13px;">
                View Credit Details →
            </a>
        </div>

        <!-- Recent Leads -->
        <div class="ofp-card">
            <div class="ofp-card-header">
                <h3>Recent Leads</h3>
                <a href="<?php echo esc_url( home_url( '/leads' ) ); ?>" style="font-size:13px;color:#1a73e8;text-decoration:none;">
                    View all →
                </a>
            </div>

            <?php if ( empty( $recent_leads ) ) : ?>
                <div class="ofp-empty">
                    <div class="ofp-empty-icon">📭</div>
                    <h3>No leads yet</h3>
                    <p>Leads will appear here as people submit your landing page form.</p>
                </div>
            <?php else : ?>
                <div class="ofp-table-wrap">
                    <table class="ofp-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $recent_leads as $lead ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $lead->name ?: '—' ); ?></td>
                                    <td><strong><?php echo esc_html( $lead->phone ); ?></strong></td>
                                    <td><?php echo $status_badges[ $lead->status ] ?? esc_html( $lead->status ); ?></td>
                                    <td><?php echo esc_html( human_time_diff( strtotime( $lead->created_at ), current_time( 'timestamp' ) ) . ' ago' ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    <?php else : ?>

        <!-- Upgrade CTA for non-CRM clients -->
        <div class="ofp-card" style="text-align:center;padding:48px;">
            <div style="font-size:48px;margin-bottom:16px;">🚀</div>
            <h2 style="margin-bottom:12px;">Activate Lead Automation</h2>
            <p style="color:#6b7280;margin-bottom:24px;max-width:480px;margin-left:auto;margin-right:auto;">
                Upgrade to a CRM plan to get automated SMS follow-ups, voice calls, and IVR — all running on autopilot so you never miss a lead.
            </p>
            <a href="mailto:<?php echo esc_attr( get_option( 'admin_email' ) ); ?>?subject=Upgrade Request"
               class="ofp-btn ofp-btn-primary">Contact Us to Upgrade</a>
        </div>

    <?php endif; ?>

    <!-- Subscription info -->
    <div class="ofp-card" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div>
            <div style="font-size:13px;color:#6b7280;margin-bottom:2px;">Subscription expires</div>
            <div style="font-weight:600;color:#0f172a;"><?php echo esc_html( $client->subscription_expires ?: '—' ); ?></div>
        </div>
        <?php if ( $client->virtual_account_number ) : ?>
            <div style="text-align:right;">
                <div style="font-size:13px;color:#6b7280;margin-bottom:2px;">Renew via bank transfer</div>
                <div style="font-weight:600;color:#0f172a;"><?php echo esc_html( $client->virtual_bank_name ); ?> — <?php echo esc_html( $client->virtual_account_number ); ?></div>
            </div>
        <?php endif; ?>
    </div>

</div>
</main>
</div><!-- .ofp-shell -->

<?php wp_footer(); ?>
</body>
</html>
