<?php
/**
 * Institution taxonomy archive template - MuseumPunks
 */
if (!defined('ABSPATH')) exit;

get_header();

$term = get_queried_object();
$institution_name = $term->name;
$institution_description = $term->description;
$institution_url = get_term_meta($term->term_id, 'institution_url', true);

// Get site logo from plugin settings
$sbpr_settings = SB_Punks_Registry::get_settings();
$logo_default = esc_url($sbpr_settings['logo_default_url']);
$logo_hover = esc_url($sbpr_settings['logo_hover_url']);

// Get all punks for this institution
$punks = new WP_Query([
	'post_type' => SB_Punks_Registry::PT,
	'posts_per_page' => -1,
	'tax_query' => [
		[
			'taxonomy' => SB_Punks_Registry::TAX_INSTITUTION,
			'field' => 'term_id',
			'terms' => $term->term_id,
		],
	],
	'orderby' => 'post_name',
	'order' => 'ASC',
]);

?>
<header class="sbpr-single__header">
	<a class="sbpr-logo" href="<?php echo esc_url(home_url('/')); ?>" aria-label="Home">
		<?php if ($logo_default): ?>
			<img class="sbpr-logo__img sbpr-logo__img--default" src="<?php echo $logo_default; ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>" />
		<?php else: ?>
			<span class="sbpr-logo__text"><?php echo esc_html(get_bloginfo('name')); ?></span>
		<?php endif; ?>
		<?php if ($logo_hover): ?>
			<img class="sbpr-logo__img sbpr-logo__img--hover" src="<?php echo $logo_hover; ?>" alt="" aria-hidden="true" />
		<?php endif; ?>
	</a>
</header>

<main id="primary" class="site-main sbpr-institution__main">
	<div class="sbpr-institution__wrap">
		<div class="sbpr-institution__header">
			<h1 class="sbpr-institution__name"><?php echo esc_html($institution_name); ?></h1>
			<?php if ($institution_url): ?>
				<p class="sbpr-institution__link">
					<a href="<?php echo esc_url($institution_url); ?>" target="_blank" rel="noopener"><?php echo esc_html(preg_replace('#^https?://#', '', $institution_url)); ?></a>
				</p>
			<?php endif; ?>
			<?php if ($institution_description): ?>
				<div class="sbpr-institution__desc">
					<?php echo wp_kses_post(wpautop($institution_description)); ?>
				</div>
			<?php endif; ?>
			<p class="sbpr-institution__count">
				<?php echo esc_html($punks->found_posts); ?> punk<?php echo $punks->found_posts !== 1 ? 's' : ''; ?> in collection
			</p>
		</div>

		<?php if ($punks->have_posts()): ?>
			<div class="sbpr-index">
				<?php while ($punks->have_posts()): $punks->the_post();
					$punk_id = get_the_ID();
					$slug = get_post_field('post_name', $punk_id);
					$href = home_url('/' . $slug . '/');
					$thumb = '';
					if (has_post_thumbnail($punk_id)) {
						$thumb = (string)get_the_post_thumbnail_url($punk_id, 'full');
					}
				?>
					<a class="sbpr-index__card" href="<?php echo esc_url($href); ?>">
						<?php if ($thumb): ?>
							<img class="sbpr-index__img" src="<?php echo esc_url($thumb); ?>" alt="" loading="lazy" decoding="async" />
						<?php else: ?>
							<span class="sbpr-index__ph" aria-hidden="true"></span>
						<?php endif; ?>
						<span class="sbpr-index__num"><?php echo esc_html($slug); ?></span>
					</a>
				<?php endwhile; ?>
			</div>
		<?php else: ?>
			<p class="sbpr-empty">No punks found in this collection yet.</p>
		<?php endif; ?>
		<?php wp_reset_postdata(); ?>
	</div>
</main>
<?php get_footer(); ?>
