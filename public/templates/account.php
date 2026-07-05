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

global $wpdb;
$wpdb->query( "ALTER TABLE {$wpdb->prefix}ofp_clients ADD COLUMN IF NOT EXISTS logo_url VARCHAR(255) DEFAULT NULL AFTER business_category" );
// MySQL 8+ supports IF NOT EXISTS on ADD COLUMN. If MariaDB/older MySQL, it will just fail silently which is fine.

// Better yet, just check manually:
$has_logo = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}ofp_clients LIKE 'logo_url'");
if (empty($has_logo)) {
    $wpdb->query("ALTER TABLE {$wpdb->prefix}ofp_clients ADD COLUMN logo_url VARCHAR(255) DEFAULT NULL AFTER business_category");
}

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
    } elseif ( isset( $_POST['upload_logo'] ) && isset( $_FILES['logo'] ) ) {
        $file = $_FILES['logo'];
        
        if ( $file['error'] !== UPLOAD_ERR_OK ) {
            $error = 'There was an error uploading the file. Please try again.';
        } elseif ( $file['size'] > 300 * 1024 ) { // 300KB
            $error = 'File is too large. Maximum size is 300KB.';
        } else {
            $allowed_types = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ];
            $file_type = wp_check_filetype( $file['name'] );
            
            if ( ! in_array( $file_type['type'], $allowed_types ) ) {
                $error = 'Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.';
            } else {
                if ( ! function_exists( 'wp_handle_upload' ) ) {
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                }
                
                $upload_overrides = [ 'test_form' => false ];
                $movefile = wp_handle_upload( $file, $upload_overrides );
                
                if ( $movefile && ! isset( $movefile['error'] ) ) {
                    global $wpdb;
                    $wpdb->update(
                        $wpdb->prefix . 'ofp_clients',
                        [ 'logo_url' => $movefile['url'] ],
                        [ 'id' => $client->id ]
                    );
                    
                    // Post-Redirect-Get pattern to avoid "Confirm Form Resubmission"
                    wp_safe_redirect( add_query_arg( 'success', 'logo', home_url( '/account' ) ) );
                    exit;
                } else {
                    $error = $movefile['error'] ?? 'Failed to move uploaded file.';
                }
            }
        }
    }
}

// Handle success messages from redirects
if ( isset( $_GET['success'] ) && $_GET['success'] === 'logo' ) {
    $success = 'Logo updated successfully.';
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

        </div>
    </div>

    <!-- Business Logo -->
    <div class="ofp-card">
        <h3>Business Logo</h3>
        <p class="ofp-hint" style="margin-bottom:16px;">Upload a square image. Max size 300KB. Formats: JPG, PNG, GIF, WebP.</p>
        
        <?php if ( ! empty( $client->logo_url ) ) : ?>
            <div style="margin-bottom:16px;">
                <img src="<?php echo esc_url( $client->logo_url ); ?>" alt="Current Logo" style="width:80px;height:80px;object-fit:cover;border-radius:50%;border:2px solid var(--border-light);">
                <p class="ofp-hint" style="margin-top:8px;">Current image: <strong><?php echo esc_html( basename( $client->logo_url ) ); ?></strong></p>
            </div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data" id="ofp-logo-form">
            <?php wp_nonce_field( 'ofp_account_' . $client->id, 'ofp_account_nonce' ); ?>
            <input type="hidden" name="upload_logo" value="1">

            <div class="ofp-field">
                <input type="file" name="logo" id="ofp-logo-input" accept="image/jpeg,image/png,image/gif,image/webp" required style="font-size:14px;">
            </div>

            <div class="ofp-form-actions">
                <button type="submit" class="ofp-btn ofp-btn-primary" id="ofp-logo-submit">Upload Logo</button>
            </div>
        </form>

        <script>
            document.getElementById('ofp-logo-input').addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (!file) return;

                // 1. Strict client-side validation (300KB)
                if (file.size > 300 * 1024) {
                    alert('File is too large! Please select an image under 300KB.');
                    e.target.value = ''; // Clear the input
                    return;
                }

                // 3. Update the nav avatar immediately upon selection
                const objectUrl = URL.createObjectURL(file);
                const avatarContainer = document.getElementById('ofp-user-avatar');
                if (avatarContainer) {
                    avatarContainer.innerHTML = '<img src="' + objectUrl + '" alt="Logo Preview" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">';
                }
            });
        </script>
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
