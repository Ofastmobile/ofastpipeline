<?php
/**
 * Template: /pipeline-settings
 * Client customises their own pipeline messages and IVR configuration.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

OFP_Auth::require_client_login();
$client = OFP_Auth::current_client();
OFP_Auth::require_active_subscription( $client );

// Only CRM clients have pipeline settings.
if ( ! OFP_Subscription::has_active( 'crm', $client->id ) ) {
    wp_safe_redirect( home_url( '/dashboard' ) );
    exit;
}

global $wpdb;
$config = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ofp_pipeline_configs WHERE client_id = %d LIMIT 1",
        $client->id
    )
);

$saved   = false;
$error   = '';

// ── Handle form submission ─────────────────────────────────────────────────
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['ofp_pipeline_nonce'] ) ) {

    if ( ! wp_verify_nonce(
        sanitize_text_field( wp_unslash( $_POST['ofp_pipeline_nonce'] ) ),
        'ofp_save_pipeline_' . $client->id
    ) ) {
        $error = 'Security check failed. Please refresh and try again.';
    } else {
        $wpdb->update(
            $wpdb->prefix . 'ofp_pipeline_configs',
            [
                'instant_sms_enabled'    => ! empty( $_POST['instant_sms_enabled'] ) ? 1 : 0,
                'instant_sms_message'    => sanitize_textarea_field( wp_unslash( $_POST['instant_sms_message']    ?? '' ) ),
                'followup_1_delay_hours' => absint( $_POST['followup_1_delay_hours'] ?? 1 ),
                'followup_1_type'        => sanitize_text_field( wp_unslash( $_POST['followup_1_type'] ?? 'sms' ) ),
                'followup_1_message'     => sanitize_textarea_field( wp_unslash( $_POST['followup_1_message']     ?? '' ) ),
                'followup_2_delay_hours' => absint( $_POST['followup_2_delay_hours'] ?? 24 ),
                'followup_2_type'        => sanitize_text_field( wp_unslash( $_POST['followup_2_type'] ?? 'voice' ) ),
                'followup_2_message'     => sanitize_textarea_field( wp_unslash( $_POST['followup_2_message']     ?? '' ) ),
                'followup_3_delay_hours' => absint( $_POST['followup_3_delay_hours'] ?? 72 ),
                'followup_3_type'        => sanitize_text_field( wp_unslash( $_POST['followup_3_type'] ?? 'sms' ) ),
                'followup_3_message'     => sanitize_textarea_field( wp_unslash( $_POST['followup_3_message']     ?? '' ) ),
                'transfer_phone'         => OFP_Security::sanitize_phone( wp_unslash( $_POST['transfer_phone']   ?? '' ) ),
                'whatsapp_link'          => sanitize_text_field( wp_unslash( $_POST['whatsapp_link']             ?? '' ) ),
                'updated_at'             => current_time( 'mysql' ),
            ],
            [ 'client_id' => $client->id ]
        );

        // Reload updated config.
        $config = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ofp_pipeline_configs WHERE client_id = %d LIMIT 1",
                $client->id
            )
        );
        $saved = true;
    }
}

$type_options = [
    'sms'   => 'SMS',
    'voice' => 'Voice / IVR Call',
    'email' => 'Email',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pipeline Settings — OFast Pipeline</title>
    <?php wp_head(); ?>
    <link rel="stylesheet" href="<?php echo esc_url( OFP_URL . 'assets/css/client-portal.css' ); ?>">
</head>
<body class="ofp-portal-body">

<?php include OFP_PATH . 'public/templates/partials/nav.php'; ?>

    <div class="ofp-container">

        <div class="ofp-page-header">
            <h1>Pipeline Settings</h1>
            <p>Customise the automated messages sent to your leads.</p>
        </div>

        <?php if ( $saved ) : ?>
            <div class="ofp-alert ofp-alert-success">✅ Pipeline settings saved successfully.</div>
        <?php endif; ?>
        <?php if ( $error ) : ?>
            <div class="ofp-alert ofp-alert-error"><?php echo esc_html( $error ); ?></div>
        <?php endif; ?>

        <div class="ofp-info-box">
            <strong>Available placeholders:</strong>
            <code>{{name}}</code> — lead's name &nbsp;|&nbsp;
            <code>{{phone}}</code> — lead's phone &nbsp;|&nbsp;
            <code>{{business_name}}</code> — your business name
        </div>

        <form method="POST" action="" class="ofp-form">
            <?php wp_nonce_field( 'ofp_save_pipeline_' . $client->id, 'ofp_pipeline_nonce' ); ?>

            <!-- ── Instant SMS ──────────────────────────────────────────── -->
            <div class="ofp-card">
                <div class="ofp-card-header">
                    <h3>⚡ Instant SMS</h3>
                    <label class="ofp-toggle">
                        <input type="checkbox" name="instant_sms_enabled" value="1"
                            <?php checked( $config->instant_sms_enabled ?? 1, 1 ); ?>>
                        <span class="ofp-toggle-slider"></span>
                        <span class="ofp-toggle-label">Enabled</span>
                    </label>
                </div>
                <p class="ofp-hint">Sent within 5 minutes of a lead submitting your form.</p>
                <div class="ofp-field">
                    <label>Message</label>
                    <textarea name="instant_sms_message" rows="3" maxlength="320"
                              placeholder="Hi {{name}}, thank you for your interest! We will be in touch shortly. - {{business_name}}"><?php
                        echo esc_textarea( $config->instant_sms_message ?? '' );
                    ?></textarea>
                    <p class="ofp-hint">Keep under 160 characters for a single SMS credit. Current:
                        <span id="ofp-sms-count-0">0</span> characters.
                    </p>
                </div>
            </div>

            <!-- ── Follow-up 1 ─────────────────────────────────────────── -->
            <div class="ofp-card">
                <h3>📩 Follow-up 1</h3>
                <div class="ofp-form-row">
                    <div class="ofp-field">
                        <label>Delay (hours after lead capture)</label>
                        <input type="number" name="followup_1_delay_hours" min="1" max="168"
                               value="<?php echo esc_attr( $config->followup_1_delay_hours ?? 1 ); ?>">
                    </div>
                    <div class="ofp-field">
                        <label>Type</label>
                        <select name="followup_1_type">
                            <?php foreach ( $type_options as $val => $label ) : ?>
                                <option value="<?php echo esc_attr( $val ); ?>"
                                    <?php selected( $config->followup_1_type ?? 'sms', $val ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="ofp-field">
                    <label>Message</label>
                    <textarea name="followup_1_message" rows="3"
                              placeholder="Hi {{name}}, just checking in — did you get our earlier message? - {{business_name}}"><?php
                        echo esc_textarea( $config->followup_1_message ?? '' );
                    ?></textarea>
                </div>
            </div>

            <!-- ── Follow-up 2 ─────────────────────────────────────────── -->
            <div class="ofp-card">
                <h3>📞 Follow-up 2 (Voice Call / IVR)</h3>
                <div class="ofp-form-row">
                    <div class="ofp-field">
                        <label>Delay (hours after lead capture)</label>
                        <input type="number" name="followup_2_delay_hours" min="1" max="168"
                               value="<?php echo esc_attr( $config->followup_2_delay_hours ?? 24 ); ?>">
                    </div>
                    <div class="ofp-field">
                        <label>Type</label>
                        <select name="followup_2_type">
                            <?php foreach ( $type_options as $val => $label ) : ?>
                                <option value="<?php echo esc_attr( $val ); ?>"
                                    <?php selected( $config->followup_2_type ?? 'voice', $val ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="ofp-field">
                    <label>IVR Script (read aloud during the call)</label>
                    <textarea name="followup_2_message" rows="4"
                              placeholder="Hello, this is a message from {{business_name}}. Press 1 to speak with us now. Press 2 for our WhatsApp contact. Press 3 for a callback later."><?php
                        echo esc_textarea( $config->followup_2_message ?? '' );
                    ?></textarea>
                    <p class="ofp-hint">Write this as natural speech — it is read aloud by a text-to-speech engine.</p>
                </div>
            </div>

            <!-- ── Follow-up 3 ─────────────────────────────────────────── -->
            <div class="ofp-card">
                <h3>💬 Follow-up 3</h3>
                <div class="ofp-form-row">
                    <div class="ofp-field">
                        <label>Delay (hours after lead capture)</label>
                        <input type="number" name="followup_3_delay_hours" min="1" max="720"
                               value="<?php echo esc_attr( $config->followup_3_delay_hours ?? 72 ); ?>">
                    </div>
                    <div class="ofp-field">
                        <label>Type</label>
                        <select name="followup_3_type">
                            <?php foreach ( $type_options as $val => $label ) : ?>
                                <option value="<?php echo esc_attr( $val ); ?>"
                                    <?php selected( $config->followup_3_type ?? 'sms', $val ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="ofp-field">
                    <label>Message</label>
                    <textarea name="followup_3_message" rows="3"
                              placeholder="Hi {{name}}, we have been trying to reach you. We would love to help. Call or message us anytime. - {{business_name}}"><?php
                        echo esc_textarea( $config->followup_3_message ?? '' );
                    ?></textarea>
                </div>
            </div>

            <!-- ── IVR Actions ─────────────────────────────────────────── -->
            <div class="ofp-card">
                <h3>🔀 IVR Response Settings</h3>
                <p class="ofp-hint">Configure what happens when a lead presses a digit during the voice call.</p>
                <div class="ofp-form-row">
                    <div class="ofp-field">
                        <label>Transfer Phone (Digit 1)</label>
                        <input type="tel" name="transfer_phone"
                               value="<?php echo esc_attr( $config->transfer_phone ?? $client->business_phone ); ?>"
                               placeholder="e.g. 08012345678">
                        <p class="ofp-hint">Lead is live-transferred to this number when they press 1.</p>
                    </div>
                    <div class="ofp-field">
                        <label>WhatsApp Number (Digit 2)</label>
                        <input type="tel" name="whatsapp_link"
                               value="<?php echo esc_attr( $config->whatsapp_link ?? $client->whatsapp_number ); ?>"
                               placeholder="e.g. 2348012345678 (international format, no +)">
                        <p class="ofp-hint">Lead receives an SMS with your WhatsApp link when they press 2.</p>
                    </div>
                </div>
                <p class="ofp-hint"><strong>Digit 3:</strong> Lead is scheduled for a callback in 2 hours (automatic).</p>
            </div>

            <div class="ofp-form-actions">
                <button type="submit" class="ofp-btn ofp-btn-primary">Save Pipeline Settings</button>
                <a href="<?php echo esc_url( home_url( '/dashboard' ) ); ?>" class="ofp-btn ofp-btn-secondary">Cancel</a>
            </div>

        </form>
    </div>
</main>
</div><!-- .ofp-shell -->

<script>
// Live character counter for instant SMS.
var smsField = document.querySelector('[name="instant_sms_message"]');
var counter  = document.getElementById('ofp-sms-count-0');
if (smsField && counter) {
    function updateCount() { counter.textContent = smsField.value.length; }
    smsField.addEventListener('input', updateCount);
    updateCount();
}
</script>

<?php wp_footer(); ?>
</body>
</html>
