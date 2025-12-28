<?php
/**
 * Institutions index page - MuseumPunks
 */
if (!defined('ABSPATH')) exit;

get_header();

// Get all institutions with at least one punk
$institutions = get_terms([
	'taxonomy' => SB_Punks_Registry::TAX_INSTITUTION,
	'hide_empty' => true,
	'orderby' => 'name',
	'order' => 'ASC',
]);

?>
<main id="primary" class="site-main sbpr-institutions__main">
	<div class="sbpr-institutions__wrap">
		<h1 class="sbpr-institutions__title">Institutions</h1>

		<?php if (!empty($institutions) && !is_wp_error($institutions)): ?>
			<div class="sbpr-institutions__grid">
				<?php foreach ($institutions as $inst):
					$logo = get_term_meta($inst->term_id, 'institution_logo', true);
					$link = get_term_link($inst);
					if (is_wp_error($link)) continue;
				?>
					<a class="sbpr-institutions__card" href="<?php echo esc_url($link); ?>">
						<?php if ($logo): ?>
							<img class="sbpr-institutions__logo" src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($inst->name); ?>" loading="lazy" decoding="async" />
						<?php else: ?>
							<span class="sbpr-institutions__placeholder"><?php echo esc_html(mb_substr($inst->name, 0, 1)); ?></span>
						<?php endif; ?>
						<span class="sbpr-institutions__name"><?php echo esc_html($inst->name); ?></span>
						<span class="sbpr-institutions__count"><?php echo esc_html($inst->count); ?> punk<?php echo $inst->count !== 1 ? 's' : ''; ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		<?php else: ?>
			<p class="sbpr-empty">No institutions found yet.</p>
		<?php endif; ?>
	</div>
</main>
<?php get_footer(); ?>
