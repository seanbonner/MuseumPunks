<?php
/**
 * Single Punk template (sb_punk) - MuseumPunks
 */
if (!defined('ABSPATH')) exit;

get_header();

the_post();
$post_id = get_the_ID();

$punk_num = get_the_title($post_id);
$punk_num_clean = preg_replace('/[^0-9]/', '', (string)$punk_num);

// Institution (taxonomy)
$institution_name = '';
$institution_url = '';
$institutions = get_the_terms($post_id, SB_Punks_Registry::TAX_INSTITUTION);
if ($institutions && !is_wp_error($institutions)) {
	$inst = $institutions[0]; // Use first institution
	$institution_name = $inst->name;
	// Check for custom URL in term meta, fallback to archive link
	$custom_url = get_term_meta($inst->term_id, 'institution_url', true);
	$institution_url = $custom_url ? $custom_url : get_term_link($inst);
}

// Other meta
$museum_wallet     = (string)get_post_meta($post_id, SB_Punks_Registry::META_MUSEUM_WALLET, true);
$acquisition_date  = (string)get_post_meta($post_id, SB_Punks_Registry::META_ACQUISITION_DATE, true);
$announcement_url  = (string)get_post_meta($post_id, SB_Punks_Registry::META_ANNOUNCEMENT_URL, true);
$acquisition_type  = (string)get_post_meta($post_id, SB_Punks_Registry::META_ACQUISITION_TYPE, true);
$donor_name        = (string)get_post_meta($post_id, SB_Punks_Registry::META_DONOR_NAME, true);
$donor_url         = (string)get_post_meta($post_id, SB_Punks_Registry::META_DONOR_URL, true);
$v1_wrapped        = (string)get_post_meta($post_id, SB_Punks_Registry::META_V1_WRAPPED, true);

// Format acquisition date for display
$acq_human = '';
if ($acquisition_date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $acquisition_date)) {
	$ts = strtotime($acquisition_date . ' 12:00:00');
	if ($ts) $acq_human = date_i18n('F j, Y', $ts);
}

// Label based on acquisition type
$label_type = 'MUSEUM ACQUISITION';
if ($acquisition_type === 'donation') $label_type = 'MUSEUM DONATION';
if ($acquisition_type === 'purchase') $label_type = 'MUSEUM PURCHASE';

$img_url = '';
if (has_post_thumbnail($post_id)) {
	$img_url = (string)get_the_post_thumbnail_url($post_id, 'large');
} else {
	// Try first image in content as fallback.
	$content_raw = (string)get_post_field('post_content', $post_id);
	if (preg_match('/<img[^>]+src="([^"]+)"/i', $content_raw, $m)) {
		$img_url = $m[1];
	}
}

$story_html = SB_Punks_Registry::extract_story_html((string)get_post_field('post_content', $post_id));

function sbpr_wallet_link($wallet, $name = '') {
	$wallet = trim((string)$wallet);
	if (!$wallet) return '';
	$text = $name ? $name : $wallet;
	$url = SB_Punks_Registry::cp_account_url($wallet);
	return '<a href="'.esc_url($url).'">'.esc_html($text).'</a>';
}

?>
<main id="primary" class="site-main sbpr-single__main">
	<div class="sbpr-single__wrap">
		<div class="sbpr-single__media">
			<?php if ($img_url): ?>
				<a class="sbpr-single__imglink" href="<?php echo SB_Punks_Registry::cp_details_url($punk_num_clean); ?>" aria-label="View on CryptoPunks">
					<img class="sbpr-single__img" src="<?php echo esc_url($img_url); ?>" alt="" decoding="async" loading="eager" />
				</a>
			<?php endif; ?>
		</div>

		<div class="sbpr-single__content">
			<h1 class="sbpr-single__num"><?php echo esc_html($punk_num_clean); ?></h1>
			<div class="sbpr-single__label"><?php echo esc_html($label_type); ?></div>

			<dl class="sbpr-single__facts">
				<?php if ($institution_name): ?>
					<div class="sbpr-single__fact">
						<dt>Institution:</dt>
						<dd>
							<?php if ($institution_url): ?>
								<a href="<?php echo esc_url($institution_url); ?>"><?php echo esc_html($institution_name); ?></a>
							<?php else: ?>
								<?php echo esc_html($institution_name); ?>
							<?php endif; ?>
						</dd>
					</div>
				<?php endif; ?>

				<?php if ($acq_human): ?>
					<div class="sbpr-single__fact">
						<dt>Acquired:</dt>
						<dd>
							<?php if ($announcement_url): ?>
								<a href="<?php echo esc_url($announcement_url); ?>"><?php echo esc_html($acq_human); ?></a>
							<?php else: ?>
								<?php echo esc_html($acq_human); ?>
							<?php endif; ?>
						</dd>
					</div>
				<?php endif; ?>

				<?php if ($acquisition_type): ?>
					<div class="sbpr-single__fact">
						<dt>Type:</dt>
						<dd><?php echo esc_html(ucfirst($acquisition_type)); ?></dd>
					</div>
				<?php endif; ?>

				<?php if ($acquisition_type === 'donation' && $donor_name): ?>
					<div class="sbpr-single__fact">
						<dt>Donated by:</dt>
						<dd>
							<?php if ($donor_url): ?>
								<a href="<?php echo esc_url($donor_url); ?>"><?php echo esc_html($donor_name); ?></a>
							<?php else: ?>
								<?php echo esc_html($donor_name); ?>
							<?php endif; ?>
						</dd>
					</div>
				<?php endif; ?>

				<?php if ($museum_wallet): ?>
					<div class="sbpr-single__fact">
						<dt>Wallet:</dt>
						<dd><?php echo sbpr_wallet_link($museum_wallet); ?></dd>
					</div>
				<?php endif; ?>

				<div class="sbpr-single__fact">
					<dt>V1:</dt>
					<dd>
						<?php if ($v1_wrapped === '1'): ?>
							<a href="<?php echo SB_Punks_Registry::os_wrapped_url($punk_num_clean); ?>">Wrapped</a>
						<?php else: ?>
							Unwrapped
						<?php endif; ?>
					</dd>
				</div>
			</dl></div>

		<div class="sbpr-single__story sbpr-single__story--full">
			<?php echo wp_kses_post($story_html); ?>
		</div>
	</div>
</main>
<?php get_footer(); ?>
