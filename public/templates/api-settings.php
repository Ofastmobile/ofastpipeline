<?php
/**
 * Template: /api-settings
 * Client API Settings and Webhook Credentials.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

OFP_Auth::require_client_login();
$client = OFP_Auth::current_client();
OFP_Auth::require_active_subscription( $client );

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Settings — OFast Pipeline</title>
    <!-- Dark theme script to avoid FOUC -->
    <script>
        (function() {
            var currentTheme = localStorage.getItem('ofp_theme') || 'dark';
            if (currentTheme === 'light') { document.documentElement.setAttribute('data-theme', 'light'); }
        })();
    </script>
    <?php wp_head(); ?>
    <link rel="stylesheet" href="<?php echo esc_url( OFP_URL . 'assets/css/client-portal.css' ); ?>">
</head>
<body class="ofp-portal-body">

<?php include OFP_PATH . 'public/templates/partials/nav.php'; ?>

    <div class="ofp-container">

        <div class="ofp-page-header">
            <h1>API Settings</h1>
            <p>Manage your webhook credentials and endpoint integrations.</p>
        </div>

        <!-- Client ID and endpoint — needed for landing page form setup -->
        <div class="ofp-card" style="border-left: 4px solid var(--accent-green); margin-bottom: 24px;">
            <h3 style="color:var(--accent-green); font-size: 13px; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px;">
                Landing Page Form Credentials
            </h3>
            <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid var(--border-color);">
                <span style="font-size:13px; color:var(--text-muted);">Your Client ID</span>
                <code style="font-size:13px; font-weight:700; color:var(--text-main); background:var(--bg-body); padding:4px 8px; border-radius:4px;">
                    <?php echo esc_html( $client->id ); ?>
                </code>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:flex-start; padding:8px 0;">
                <span style="font-size:13px; color:var(--text-muted);">Lead Capture Endpoint</span>
                <code style="font-size:11px; color:var(--text-main); background:var(--bg-body); padding:4px 8px; border-radius:4px; word-break:break-all; max-width:280px; text-align:right;">
                    <?php echo esc_html( home_url( '/wp-json/ofp/v1/capture-lead' ) ); ?>
                </code>
            </div>
            <p class="ofp-hint" style="margin-top:12px;">
                Use these in your Elementor landing page form. Set <strong>client_id</strong>
                as a hidden field with your Client ID value above.
            </p>
        </div>

</div>
</main>
</div><!-- .ofp-shell -->

<?php wp_footer(); ?>
</body>
</html>
