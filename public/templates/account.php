<?php
/**
 * Template: /account
 * Client profile and password management.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

OFP_Auth::require_client_login();
$client = OFP_Auth::current_client();

$success = '';
$error   = '';

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['ofp_account_nonce'] ) ) {
    if ( ! wp_verify_nonce(
        sanitize_text_field( wp_unslash( $_POST['ofp_account_nonce'] ) ),
        'ofp_account_' . $client->id
    ) ) {
        $error = 'Security check failed.';
    } elseif ( isset( $_POST['change_password'] ) ) {
        $current = wp_unslash( $_POST['current_password'] ?? '' );
        $new_pw  = wp_unslash( $_POST['new_password']     ?? '' );
        $confirm = wp_unslash( $_POST['confirm_password']  ?? '' );

        if ( strlen( $new_pw ) < 8 ) {
            $error = 'New password must be at least 8 characters.';
        } elseif ( $new_pw !== $confirm ) {
            $error = 'New passwords do not match.';
        } elseif ( ! OFP_Auth::change_password( $client->id, $current, $new_pw ) ) {
            $error = 'Current password is incorrect.';
        } else {
            $success = 'Password changed successfully. Please log in again.';
            OFP_Auth::logout();
            wp_safe_redirect( home_url( '/login?session_expired=1' ) );
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account — OFast Pipeline</title>
    <?php wp_head(); ?>
    <link rel="stylesheet" href="<?php echo esc_url( OFP_URL . 'assets/css/client-portal.css' ); ?>">
</head>
<body class="ofp-portal-body">

<?php include OFP_PATH . 'public/templates/partials/nav.php'; ?>

<div class="ofp-container" style="max-width:640px;">

    <div class="ofp-page-header">
        <h1>My Account</h1>
        <p>Your profile and security settings.</p>
    </div>

    <?php if ( $success ) : ?>
        <div class="ofp-alert ofp-alert-success"><?php echo esc_html( $success ); ?></div>
    <?php endif; ?>
    <?php if ( $error ) : ?>
        <div class="ofp-alert ofp-alert-error"><?php echo esc_html( $error ); ?></div>
    <?php endif; ?>

    <!-- Profile Info (read-only) -->
    <div class="ofp-card">
        <h3>Profile Information</h3>
        <p class="ofp-hint" style="margin-bottom:16px;">To update your business details, contact us directly.</p>

        <div style="display:flex;flex-direction:column;gap:14px;">
            <?php
            $fields = [
                'Business Name'  => $client->business_name,
                'Owner Name'     => $client->owner_name,
                'Email'          => $client->email,
                'Phone'          => $client->phone,
                'Business Phone' => $client->business_phone ?: '—',
                'WhatsApp'       => $client->whatsapp_number ?: '—',
                'Plan'           => strtoupper( $client->plan ?: '—' ),
                'Member Since'   => gmdate( 'F j, Y', strtotime( $client->created_at ) ),
            ];
            foreach ( $fields as $label => $value ) :
            ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #f1f5f9;">
                    <span style="font-size:13px;color:#6b7280;"><?php echo esc_html( $label ); ?></span>
                    <span style="font-size:14px;font-weight:500;color:#0f172a;"><?php echo esc_html( $value ); ?></span>
                </div>
            <?php endforeach; ?>

            <!-- Client ID and endpoint — needed for landing page form setup -->
            <div style="margin-top:20px;padding:16px;background:#f0fdf4;border-radius:8px;border-left:4px solid #22c55e;">
                <p style="font-size:12px;color:#166534;font-weight:600;margin-bottom:10px;text-transform:uppercase;letter-spacing:0.05em;">
                    Landing Page Form Credentials
                </p>
                <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #bbf7d0;">
                    <span style="font-size:13px;color:#166534;">Your Client ID</span>
                    <code style="font-size:13px;font-weight:700;color:#0f172a;background:#dcfce7;padding:2px 8px;border-radius:4px;">
                        <?php echo esc_html( $client->id ); ?>
                    </code>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:flex-start;padding:6px 0;">
                    <span style="font-size:13px;color:#166534;">Lead Capture Endpoint</span>
                    <code style="font-size:11px;color:#374151;background:#dcfce7;padding:2px 8px;border-radius:4px;word-break:break-all;max-width:240px;text-align:right;">
                        <?php echo esc_html( home_url( '/wp-json/ofp/v1/capture-lead' ) ); ?>
                    </code>
                </div>
                <p style="font-size:11px;color:#4ade80;margin-top:8px;line-height:1.5;">
                    Use these in your Elementor landing page form. Set <strong>client_id</strong>
                    as a hidden field with your Client ID value above.
                </p>
            </div>
        </div>
    </div>

    <!-- Change Password -->
    <div class="ofp-card">
        <h3>Change Password</h3>

        <form method="POST" action="">
            <?php wp_nonce_field( 'ofp_account_' . $client->id, 'ofp_account_nonce' ); ?>
            <input type="hidden" name="change_password" value="1">

            <div class="ofp-field">
                <label for="current_password">Current Password <span class="required">*</span></label>
                <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
            </div>

            <div class="ofp-field">
                <label for="new_password">New Password <span class="required">*</span></label>
                <input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password">
                <p class="ofp-hint">Minimum 8 characters.</p>
            </div>

            <div class="ofp-field">
                <label for="confirm_password">Confirm New Password <span class="required">*</span></label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
            </div>

            <div class="ofp-form-actions">
                <button type="submit" class="ofp-btn ofp-btn-primary">Update Password</button>
            </div>
        </form>
    </div>

    <!-- Danger zone -->
    <div class="ofp-card" style="border-color:#fecaca;">
        <h3 style="color:#dc2626;">Account Actions</h3>
        <p style="font-size:14px;color:#6b7280;margin-bottom:16px;">
            Need to close your account or have a billing issue? Contact us directly.
        </p>
        <a href="<?php echo esc_url( OFP_Client_Portal::logout_url() ); ?>" class="ofp-btn ofp-btn-danger">
            Log Out
        </a>
    </div>

</div>
</main>
</div><!-- .ofp-shell -->

<?php wp_footer(); ?>
</body>
</html>
