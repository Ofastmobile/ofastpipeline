<?php
/**
 * Client Portal — Top Bar + Collapsible Left Sidebar
 *
 * YOUR LOCAL CHANGES INCORPORATED (from removed.md):
 *  1. Top Up button in topbar — conditional on active CRM subscription
 *  2. SVG icons via Heroicons (stroke-based) instead of emojis
 *  3. echo $item['icon'] without esc_html — safe via wp_kses() with SVG allowlist
 *
 * SECURITY NOTE:
 *  Icons come from this hardcoded array only — never from user/DB input.
 *  wp_kses() with SVG allowlist is used instead of raw echo as a future-proof
 *  guard in case any icon ever becomes dynamic.
 *
 * TOP UP BUTTON:
 *  Only shown when client has an active CRM subscription.
 *  Listing-only clients have no SMS/voice credits so the button is hidden.
 *
 * MARKUP CONTRACT (every template must honour this structure):
 *  <header class="ofp-topbar">...</header>
 *  <div class="ofp-shell" id="ofp-shell">
 *      <aside class="ofp-sidebar">...</aside>
 *      <main class="ofp-main">
 *          <!-- template content -->
 *      </main>
 *  </div>  ← closed at very bottom of each template
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$has_crm     = OFP_Subscription::has_active( 'crm',     $client->id );
$has_listing = OFP_Subscription::has_active( 'listing', $client->id );
$current_url = home_url( parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH ) );

// ── SVG icon allowlist for wp_kses() ─────────────────────────────────────────
// Allows Heroicon-style inline SVG to render safely.
$allowed_svg = [
    'svg'  => [ 'xmlns' => true, 'fill' => true, 'viewbox' => true, 'stroke-width' => true, 'stroke' => true, 'class' => true, 'aria-hidden' => true ],
    'path' => [ 'stroke-linecap' => true, 'stroke-linejoin' => true, 'd' => true, 'fill' => true, 'stroke' => true ],
];

// ── Navigation items ──────────────────────────────────────────────────────────
$nav_items = [];

$nav_items[] = [
    'label'  => 'Dashboard',
    'icon'   => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" /></svg>',
    'url'    => home_url( '/dashboard' ),
    'slug'   => 'dashboard',
    'locked' => false,
];

if ( $has_crm ) {
    $nav_items[] = [
        'label'  => 'My Leads',
        'icon'   => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>',
        'url'    => home_url( '/leads' ),
        'slug'   => 'leads',
        'locked' => false,
    ];
    $nav_items[] = [
        'label'  => 'Pipeline Settings',
        'icon'   => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 011.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.893.149c-.425.07-.765.383-.93.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 01-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.397.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 01-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.505-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.108-1.204l-.526-.738a1.125 1.125 0 01.12-1.45l.773-.773a1.125 1.125 0 011.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>',
        'url'    => home_url( '/pipeline-settings' ),
        'slug'   => 'pipeline-settings',
        'locked' => false,
    ];
    $nav_items[] = [
        'label'  => 'Communications',
        'icon'   => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" /></svg>',
        'url'    => home_url( '/communications' ),
        'slug'   => 'communications',
        'locked' => false,
    ];
    $nav_items[] = [
        'label'  => 'Credits & Billing',
        'icon'   => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" /></svg>',
        'url'    => home_url( '/credits' ),
        'slug'   => 'credits',
        'locked' => false,
    ];
} else {
    $nav_items[] = [
        'label'  => 'Lead Automation',
        'icon'   => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 01-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 006.16-12.12A14.98 14.98 0 009.631 8.41m5.96 5.96a14.926 14.926 0 01-5.841 2.58m-.119-8.54a6 6 0 00-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 00-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 01-2.448-2.448 14.9 14.9 0 01.06-.312m-2.24 2.39a4.493 4.493 0 00-1.757 4.306 4.493 4.493 0 004.306-1.758M16.5 9a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" /></svg>',
        'url'    => home_url( '/dashboard?upgrade=crm' ),
        'slug'   => '',
        'locked' => true,
        'badge'  => 'Upgrade',
    ];
}

if ( $has_listing ) {
    $nav_items[] = [
        'label'  => 'My Listing',
        'icon'   => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" /></svg>',
        'url'    => home_url( '/my-listing' ),
        'slug'   => 'my-listing',
        'locked' => false,
    ];
} elseif ( $client->business_category === 'property' ) {
    $nav_items[] = [
        'label'  => 'List Property',
        'icon'   => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" /></svg>',
        'url'    => home_url( '/dashboard?upgrade=listing' ),
        'slug'   => '',
        'locked' => true,
        'badge'  => 'New',
    ];
}

$nav_items[] = [
    'label'  => 'Reports',
    'icon'   => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" /></svg>',
    'url'    => home_url( '/reports' ),
    'slug'   => 'reports',
    'locked' => false,
];

$nav_items[] = [
    'label'  => 'My Account',
    'icon'   => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" /></svg>',
    'url'    => home_url( '/account' ),
    'slug'   => 'account',
    'locked' => false,
];

$status_class = match( $client->status ) {
    'active'         => 'ofp-status-dot green',
    'grace'          => 'ofp-status-dot orange',
    'pending_review' => 'ofp-status-dot yellow',
    default          => 'ofp-status-dot red',
};
?>
<header class="ofp-topbar">
    <button class="ofp-sidebar-toggle" id="ofp-sidebar-toggle"
            aria-label="Toggle navigation" aria-expanded="true">
        <span></span><span></span><span></span>
    </button>

    <a class="ofp-nav-brand" href="<?php echo esc_url( home_url( '/dashboard' ) ); ?>">
        ⚡ <span>OFast Pipeline</span>
    </a>

    <div class="ofp-topbar-spacer"></div>

    <?php if ( $has_crm ) : ?>
        <a href="<?php echo esc_url( home_url( '/credits' ) ); ?>"
           class="ofp-topbar-topup"
           aria-label="Top up credit balance">
            Top Up
        </a>
    <?php endif; ?>

    <div class="ofp-nav-user">
        <span class="ofp-nav-username">
            <?php echo esc_html( $client->business_name ); ?>
        </span>
        <span class="<?php echo esc_attr( $status_class ); ?>"
              title="<?php echo esc_attr( ucfirst( str_replace( '_', ' ', $client->status ) ) ); ?>">
        </span>
    </div>
</header>

<div class="ofp-shell" id="ofp-shell">

    <div class="ofp-sidebar-backdrop" id="ofp-sidebar-backdrop"></div>

    <aside class="ofp-sidebar" id="ofp-sidebar">
        <nav class="ofp-sidebar-nav" aria-label="Client portal navigation">
            <ul>
                <?php foreach ( $nav_items as $item ) :
                    $is_active = ! $item['locked']
                        && $item['slug']
                        && strpos( $current_url, '/' . $item['slug'] ) !== false;
                    $classes = 'ofp-nav-item';
                    if ( $is_active )      $classes .= ' active';
                    if ( $item['locked'] ) $classes .= ' locked';
                ?>
                    <li>
                        <a href="<?php echo esc_url( $item['url'] ); ?>"
                           class="<?php echo esc_attr( $classes ); ?>"
                           <?php echo $item['locked'] ? 'aria-disabled="true"' : ''; ?>>
                            <span class="ofp-nav-icon">
                                <?php echo wp_kses( $item['icon'], $allowed_svg ); ?>
                            </span>
                            <span class="ofp-nav-label">
                                <?php echo esc_html( $item['label'] ); ?>
                            </span>
                            <?php if ( ! empty( $item['badge'] ) ) : ?>
                                <span class="ofp-nav-badge">
                                    <?php echo esc_html( $item['badge'] ); ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <div class="ofp-sidebar-footer">
            <a href="<?php echo esc_url( OFP_Client_Portal::logout_url() ); ?>"
               class="ofp-nav-item ofp-nav-logout">
                <span class="ofp-nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                    </svg>
                </span>
                <span class="ofp-nav-label">Logout</span>
            </a>
        </div>
    </aside>

    <main class="ofp-main">
