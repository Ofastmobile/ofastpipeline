<?php
/**
 * Template: /properties/ — public archive, grid of cards.
 *
 * @package OFast_Pipeline
 */

if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

$paged = max( 1, get_query_var( 'paged' ) ?: 1 );

$query = new WP_Query( [
	'post_type'      => 'ofp_property',
	'post_status'    => 'publish',
	'posts_per_page' => 12,
	'paged'          => $paged,
	'meta_query'     => [
		[
			'key'     => 'ofp_status',
			'value'   => 'live',
			'compare' => '=',
		],
	],
] );

$type_filter = sanitize_text_field( $_GET['type'] ?? '' );
$city_filter = sanitize_text_field( $_GET['city'] ?? '' );
?>

<div class="ofp-properties-wrapper" style="max-width:1200px; margin:0 auto; padding:40px 20px;">
	<h1 style="margin-bottom:24px; font-size:32px; font-weight:700;">Properties</h1>

	<form method="GET" class="ofp-properties-filter" style="display:flex; gap:16px; margin-bottom:32px; flex-wrap:wrap;">
		<select name="type" style="padding:10px 16px; border:1px solid #e2e8f0; border-radius:8px;">
			<option value="">All Types</option>
			<option value="sale" <?php selected( $type_filter, 'sale' ); ?>>For Sale</option>
			<option value="rent" <?php selected( $type_filter, 'rent' ); ?>>For Rent</option>
		</select>
		<input type="text" name="city" placeholder="Location" value="<?php echo esc_attr( $city_filter ); ?>" style="padding:10px 16px; border:1px solid #e2e8f0; border-radius:8px; flex-grow:1; max-width:300px;">
		<button type="submit" style="background:#2563eb; color:white; border:none; padding:10px 24px; border-radius:8px; font-weight:600; cursor:pointer;">Filter</button>
	</form>

	<?php if ( $query->have_posts() ) : ?>
		<div class="ofp-properties-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap:24px;">
			<?php while ( $query->have_posts() ) : $query->the_post();
				$post_id       = get_the_ID();
				$price         = (float) get_post_meta( $post_id, 'ofp_price', true );
				$listing_type  = get_post_meta( $post_id, 'ofp_listing_type', true );
				$property_type = get_post_meta( $post_id, 'ofp_property_type', true );
				$bedrooms      = (int) get_post_meta( $post_id, 'ofp_bedrooms', true );
				$bathrooms     = (int) get_post_meta( $post_id, 'ofp_bathrooms', true );
				$location      = get_post_meta( $post_id, 'ofp_location_text', true );

				if ( $type_filter && $listing_type !== $type_filter ) continue;
				if ( $city_filter && stripos( $location, $city_filter ) === false ) continue;
				?>
				<a href="<?php the_permalink(); ?>" class="ofp-property-card" style="display:block; background:white; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; text-decoration:none; color:inherit; transition:transform 0.2s, box-shadow 0.2s;">
					<div class="ofp-property-card-image" style="position:relative; aspect-ratio:4/3; background:#f1f5f9; overflow:hidden;">
						<?php if ( has_post_thumbnail() ) : ?>
							<?php the_post_thumbnail( 'medium', ['style' => 'width:100%; height:100%; object-fit:cover;'] ); ?>
						<?php else : ?>
							<div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:#94a3b8;">No Image</div>
						<?php endif; ?>
						<span class="ofp-property-badge" style="position:absolute; top:12px; left:12px; background:white; color:#0f172a; padding:4px 10px; border-radius:100px; font-size:12px; font-weight:600; box-shadow:0 2px 4px rgba(0,0,0,0.1);">
                            <?php echo esc_html( ucfirst( $listing_type ?: 'Listing' ) ); ?>
                        </span>
					</div>
					<div class="ofp-property-card-body" style="padding:16px;">
						<h3 style="margin:0 0 8px 0; font-size:16px; font-weight:600; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php the_title(); ?></h3>
						<p style="margin:0 0 12px 0; font-size:18px; font-weight:700; color:#2563eb;">NGN <?php echo esc_html( number_format( $price, 2 ) ); ?></p>
						<p style="margin:0; font-size:13px; color:#64748b; display:flex; gap:12px;">
							<?php if ( $bedrooms ) : ?><span><?php echo esc_html( $bedrooms ); ?> bed</span><?php endif; ?>
							<?php if ( $bathrooms ) : ?><span><?php echo esc_html( $bathrooms ); ?> bath</span><?php endif; ?>
						</p>
                        <?php if ( $location ) : ?>
                            <p style="margin:8px 0 0 0; font-size:13px; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                📍 <?php echo esc_html( $location ); ?>
                            </p>
                        <?php endif; ?>
					</div>
				</a>
			<?php endwhile; ?>
		</div>

		<div class="ofp-properties-pagination" style="margin-top:40px; display:flex; justify-content:center; gap:8px;">
			<?php
			echo paginate_links( [
				'total'   => $query->max_num_pages,
				'current' => $paged,
			] );
			?>
		</div>

	<?php else : ?>
		<p class="ofp-muted" style="color:#64748b; font-size:16px; text-align:center; padding:40px 0;">No properties match your search right now — check back soon.</p>
	<?php endif; ?>

	<?php wp_reset_postdata(); ?>
</div>

<?php get_footer(); ?>
