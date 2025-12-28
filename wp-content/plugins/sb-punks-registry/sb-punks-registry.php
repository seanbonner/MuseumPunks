<?php
/**
 * Plugin Name: SB Punks Registry
 * Description: MuseumPunks registry + front page mosaic + numeric permalinks + crisp pixel rendering.
 * Version: 0.4.1
 * Author: SB
 */

if (!defined('ABSPATH')) exit;

final class SB_Punks_Registry {
	const PT = 'sb_punk';
	const TAX_INSTITUTION = 'sb_institution';
	const OPT_KEY = 'sb_punks_registry_settings';

	// Larva Labs official punk images (24x24 PNGs)
	const PUNK_IMAGE_BASE = 'https://www.larvalabs.com/public/images/cryptopunks/punk';

	// CryptoPunks helpers
	const CP_DETAILS_BASE = 'https://cryptopunks.app/cryptopunks/details/';
	const CP_ACCOUNT_BASE = 'https://cryptopunks.app/cryptopunks/accountinfo?account=';
	const OS_WRAPPED_BASE = 'https://opensea.io/assets/ethereum/0x282bdd42f4eb70e7a9d9f40c8fea0825b7f68c5d/';

	// Meta keys
	const META_PUNK_ID            = '_sbpr_punk_id';
	const META_ACQUISITION_DATE   = '_sbpr_acquisition_date';
	const META_ANNOUNCEMENT_URL   = '_sbpr_announcement_url';
	const META_MUSEUM_WALLET      = '_sbpr_museum_wallet';
	const META_ACQUISITION_TYPE   = '_sbpr_acquisition_type'; // donation|purchase
	const META_DONOR_NAME         = '_sbpr_donor_name';
	const META_DONOR_URL          = '_sbpr_donor_url';
	const META_V1_WRAPPED         = '_sbpr_v1_wrapped';

	// Internal marker for auto-generated attachments
	const META_GEN_MARKER         = '_sbpr_generated';
	const META_GEN_PUNK_ID        = '_sbpr_generated_punk_id';

	public static function init() : void {
		add_action('init', [__CLASS__, 'register_cpt']);
		add_action('init', [__CLASS__, 'register_taxonomy']);
		add_action('init', [__CLASS__, 'register_rewrites'], 20);

		add_filter('query_vars', [__CLASS__, 'register_query_vars']);
		add_filter('template_include', [__CLASS__, 'template_override'], 50);

		add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
		add_filter('body_class', [__CLASS__, 'body_class']);

		// Numeric permalinks for sb_punk
		add_filter('post_type_link', [__CLASS__, 'filter_sb_punk_link'], 10, 4);
		add_filter('post_link', [__CLASS__, 'filter_sb_punk_link'], 10, 3);

		// Admin
		add_action('admin_menu', [__CLASS__, 'register_settings_page']);
		add_action('admin_init', [__CLASS__, 'register_settings']);
		add_action('add_meta_boxes', [__CLASS__, 'add_meta_boxes']);
		add_action('save_post_' . self::PT, [__CLASS__, 'save_meta'], 10, 3);

		// Institution taxonomy URL field
		add_action(self::TAX_INSTITUTION . '_add_form_fields', [__CLASS__, 'institution_add_fields']);
		add_action(self::TAX_INSTITUTION . '_edit_form_fields', [__CLASS__, 'institution_edit_fields'], 10);
		add_action('created_' . self::TAX_INSTITUTION, [__CLASS__, 'save_institution_fields']);
		add_action('edited_' . self::TAX_INSTITUTION, [__CLASS__, 'save_institution_fields']);

		// Keep WP from generating resized variants for punk images
		add_filter('intermediate_image_sizes_advanced', [__CLASS__, 'skip_punk_thumbnails'], 10, 3);
		add_filter('wp_calculate_image_srcset', [__CLASS__, 'disable_srcset_for_punks'], 10, 5);

		// Shortcodes
		add_shortcode('sb_punks_home', [__CLASS__, 'shortcode_home']);
		add_shortcode('sb_punks_index', [__CLASS__, 'shortcode_index']);
	}

	public static function activate() : void {
		self::register_cpt();
		self::register_taxonomy();
		self::register_rewrites();
		flush_rewrite_rules();
	}

	public static function deactivate() : void {
		flush_rewrite_rules();
	}

	// -------------------------
	// CPT + Taxonomy
	// -------------------------

	public static function register_cpt() : void {
		register_post_type(self::PT, [
			'labels' => [
				'name' => 'Punks',
				'singular_name' => 'Punk',
			],
			'public' => true,
			'publicly_queryable' => true,
			'has_archive' => false,
			'show_in_rest' => false,
			'menu_icon' => 'dashicons-art',
			'supports' => ['title','editor','thumbnail','revisions'],
			'rewrite' => false,
			'query_var' => 'sb_punk',
			'taxonomies' => [self::TAX_INSTITUTION],
		]);
	}

	public static function register_taxonomy() : void {
		register_taxonomy(self::TAX_INSTITUTION, self::PT, [
			'labels' => [
				'name' => 'Institutions',
				'singular_name' => 'Institution',
				'menu_name' => 'Institutions',
			],
			'hierarchical' => false,
			'public' => true,
			'show_ui' => true,
			'show_admin_column' => true,
			'show_in_rest' => false,
			'rewrite' => ['slug' => 'institution', 'with_front' => false],
			'query_var' => 'institution',
		]);
	}

	// -------------------------
	// Rewrites / Templates
	// -------------------------

	public static function register_rewrites() : void {
		// /4018/ -> sb_punk with name=4018
		add_rewrite_rule(
			'^([0-9]{1,5})/?$',
			'index.php?post_type=' . self::PT . '&name=$matches[1]',
			'top'
		);

		// /the-punks/ index
		add_rewrite_rule(
			'^the-punks/?$',
			'index.php?sbpr_punks=1',
			'top'
		);
	}

	public static function register_query_vars($vars) {
		$vars[] = 'sbpr_punks';
		return $vars;
	}

	public static function template_override($template) {
		if ((int)get_query_var('sbpr_punks') === 1) {
			$t = plugin_dir_path(__FILE__) . 'templates/the-punks.php';
			if (file_exists($t)) return $t;
		}

		if (is_singular(self::PT)) {
			$t = plugin_dir_path(__FILE__) . 'templates/single-sb_punk.php';
			if (file_exists($t)) return $t;
		}

		return $template;
	}

	public static function filter_sb_punk_link($permalink, $post, $leavename = false) {
		if (is_object($post) && isset($post->post_type) && $post->post_type === self::PT) {
			$slug = (string)$post->post_name;
			if (preg_match('/^[0-9]{1,5}$/', $slug)) {
				return home_url('/' . $slug . '/');
			}
		}
		return $permalink;
	}

	// -------------------------
	// Assets
	// -------------------------

	public static function enqueue_assets() : void {
		$ver = '0.4.1';
		wp_enqueue_style('sbpr', plugins_url('assets/sbpr.css', __FILE__), [], $ver);
		wp_enqueue_script('sbpr', plugins_url('assets/sbpr.js', __FILE__), [], $ver, true);
	}

	public static function body_class($classes) {
		if ((int)get_query_var('sbpr_punks') === 1) $classes[] = 'sbpr-punks-index';

		if (is_front_page()) {
			$post_id = get_queried_object_id();
			if ($post_id) {
				$content = (string)get_post_field('post_content', $post_id);
				if ($content && has_shortcode($content, 'sb_punks_home')) $classes[] = 'sbpr-front';
			}
		}

		if (is_singular(self::PT)) $classes[] = 'sbpr-single';
		return $classes;
	}

	// -------------------------
	// Settings
	// -------------------------

	public static function get_settings() : array {
		$defaults = [
			'about_url' => '/about/',
			'logo_default_url' => '',
			'logo_hover_url' => '',
		];
		$raw = get_option(self::OPT_KEY, []);
		if (!is_array($raw)) $raw = [];
		return array_merge($defaults, $raw);
	}

	public static function register_settings_page() : void {
		add_options_page(
			'SB Punks Registry',
			'SB Punks Registry',
			'manage_options',
			'sb-punks-registry',
			[__CLASS__, 'render_settings_page']
		);
	}

	public static function register_settings() : void {
		register_setting('sb_punks_registry', self::OPT_KEY, [
			'type' => 'array',
			'sanitize_callback' => [__CLASS__, 'sanitize_settings'],
			'default' => [],
		]);

		add_settings_section('sbpr_main', 'Settings', '__return_false', 'sb-punks-registry');
		add_settings_field('about_url', 'About URL', [__CLASS__, 'field_about_url'], 'sb-punks-registry', 'sbpr_main');
		add_settings_field('logo_default_url', 'Logo image URL (default)', [__CLASS__, 'field_logo_default'], 'sb-punks-registry', 'sbpr_main');
		add_settings_field('logo_hover_url', 'Logo image URL (hover)', [__CLASS__, 'field_logo_hover'], 'sb-punks-registry', 'sbpr_main');
	}

	public static function sanitize_settings($in) : array {
		if (!is_array($in)) return [];
		return [
			'about_url' => esc_url_raw((string)($in['about_url'] ?? '/about/')),
			'logo_default_url' => esc_url_raw((string)($in['logo_default_url'] ?? '')),
			'logo_hover_url' => esc_url_raw((string)($in['logo_hover_url'] ?? '')),
		];
	}

	public static function render_settings_page() : void {
		if (!current_user_can('manage_options')) return;
		?>
		<div class="wrap">
			<h1>SB Punks Registry - MuseumPunks</h1>
			<p><strong>After updating:</strong> Settings → Permalinks → Save (once).</p>
			<form method="post" action="options.php">
				<?php
				settings_fields('sb_punks_registry');
				do_settings_sections('sb-punks-registry');
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public static function field_about_url() : void {
		$s = self::get_settings();
		?>
		<input type="text" class="regular-text" name="<?php echo esc_attr(self::OPT_KEY); ?>[about_url]" value="<?php echo esc_attr($s['about_url']); ?>" />
		<?php
	}

	public static function field_logo_default() : void {
		$s = self::get_settings();
		?>
		<input type="text" class="regular-text" name="<?php echo esc_attr(self::OPT_KEY); ?>[logo_default_url]" value="<?php echo esc_attr($s['logo_default_url']); ?>" />
		<?php
	}

	public static function field_logo_hover() : void {
		$s = self::get_settings();
		?>
		<input type="text" class="regular-text" name="<?php echo esc_attr(self::OPT_KEY); ?>[logo_hover_url]" value="<?php echo esc_attr($s['logo_hover_url']); ?>" />
		<?php
	}

	// -------------------------
	// Shortcodes
	// -------------------------

	public static function shortcode_home($atts = []) : string {
		$s = self::get_settings();
		$about = esc_url($s['about_url'] ?: '/about/');
		$logo_default = esc_url($s['logo_default_url']);
		$logo_hover = esc_url($s['logo_hover_url']);

		$items = self::get_punk_items(false);

		ob_start(); ?>
		<div class="sbpr-home">
			<header class="sbpr-header">
				<a class="sbpr-logo" href="<?php echo $about; ?>" aria-label="About">
					<?php if ($logo_default): ?>
						<img class="sbpr-logo__img sbpr-logo__img--default" src="<?php echo $logo_default; ?>" alt="About" />
					<?php else: ?>
						<span class="sbpr-logo__text">About</span>
					<?php endif; ?>
					<?php if ($logo_hover): ?>
						<img class="sbpr-logo__img sbpr-logo__img--hover" src="<?php echo $logo_hover; ?>" alt="" aria-hidden="true" />
					<?php endif; ?>
				</a>
			</header>

			<section class="sbpr-mosaic" aria-label="Punks mosaic">
				<div class="sbpr-mosaic__grid"
					 data-sbpr-items="<?php echo esc_attr(wp_json_encode($items)); ?>"
					 data-sbpr-mode="home"></div>
			</section>
		</div>
		<?php
		return (string)ob_get_clean();
	}

	public static function shortcode_index($atts = []) : string {
		$items = self::get_punk_items(true);
		if (empty($items)) return '<p class="sbpr-empty">No punks found yet.</p>';

		$out = '<div class="sbpr-index">';
		foreach ($items as $it) {
			$href = esc_url($it['href']);
			$thumb = esc_url($it['thumb']);
			$num = esc_html($it['num']);
			$out .= '<a class="sbpr-index__card" href="'.$href.'">';
			if ($thumb) {
				// Keep <img> for no-JS fallback; JS will replace with crisp canvas.
				$out .= '<img class="sbpr-index__img sbpr-pixelimg" src="'.$thumb.'" alt="" loading="lazy" decoding="async" />';
			} else {
				$out .= '<span class="sbpr-index__ph" aria-hidden="true"></span>';
			}
			$out .= '<span class="sbpr-index__num">'.$num.'</span>';
			$out .= '</a>';
		}
		$out .= '</div>';

		return $out;
	}

	// -------------------------
	// Data helpers
	// -------------------------

	private static function get_punk_items(bool $sort_by_acquisition_date) : array {
		global $wpdb;

		$sql = "
			SELECT ID, post_name, post_date
			FROM {$wpdb->posts}
			WHERE post_status='publish'
			  AND post_type = %s
			  AND post_name REGEXP '^[0-9]{1,5}$'
			ORDER BY CAST(post_name AS UNSIGNED) ASC
			LIMIT 2000
		";
		$rows = $wpdb->get_results($wpdb->prepare($sql, self::PT));
		if (!$rows) return [];

		$items = [];
		foreach ($rows as $r) {
			$id = (int)$r->ID;
			$slug = (string)$r->post_name;

			$href = home_url('/' . $slug . '/');

			$thumb = '';
			if (has_post_thumbnail($id)) {
				$thumb = (string)get_the_post_thumbnail_url($id, 'full');
			}

			$acquisition_date = self::normalize_date((string)get_post_meta($id, self::META_ACQUISITION_DATE, true));

			$items[] = [
				'num' => $slug,
				'href' => $href,
				'thumb' => $thumb,
				'acquisition_date' => $acquisition_date,
				'post_date' => (string)$r->post_date,
			];
		}

		if ($sort_by_acquisition_date && count($items) > 1) {
			usort($items, function($a, $b){
				$ak = self::date_key($a['acquisition_date']);
				$bk = self::date_key($b['acquisition_date']);

				if ($ak === 0 || $bk === 0) {
					$ap = strtotime((string)$a['post_date']) ?: 0;
					$bp = strtotime((string)$b['post_date']) ?: 0;
					if ($ap !== $bp) return $bp <=> $ap;
				}

				if ($ak === 0 && $bk !== 0) return 1;
				if ($bk === 0 && $ak !== 0) return -1;

				if ($ak !== $bk) return $bk <=> $ak;
				return (int)$b['num'] <=> (int)$a['num'];
			});
		}

		foreach ($items as &$it) { unset($it['post_date']); }
		return $items;
	}

	private static function normalize_date($s) : string {
		$s = (string)$s;
		if (preg_match('/\b(20\d{2}-\d{2}-\d{2})\b/', $s, $m)) return $m[1];
		return '';
	}

	private static function date_key($s) : int {
		$d = self::normalize_date($s);
		if (!$d) return 0;
		return (int) str_replace('-', '', $d);
	}

	// -------------------------
	// Image generation (STORE ORIGINAL 24x24 — DO NOT SCALE)
	// -------------------------

	private static function is_generated_punk_attachment(int $attachment_id, int $punk_id) : bool {
		$gen = (string)get_post_meta($attachment_id, self::META_GEN_MARKER, true);
		$gen_id = (int)get_post_meta($attachment_id, self::META_GEN_PUNK_ID, true);
		if ($gen !== '1') return false;
		if ($gen_id !== $punk_id) return false;

		$file = get_attached_file($attachment_id);
		if (!$file) return false;
		$base = basename((string)$file);

		// punk-4018-1700000000.png
		return (bool)preg_match('/^punk-' . preg_quote((string)$punk_id, '/') . '-\d+\.png$/i', $base);
	}

	/**
	 * Fetch the ORIGINAL 24x24 PNG from Larva Labs (no scaling).
	 */
	private static function fetch_punk_image(int $punk_id) : string {
		if ($punk_id < 0 || $punk_id > 9999) return '';

		$padded = str_pad((string)$punk_id, 4, '0', STR_PAD_LEFT);
		$url = self::PUNK_IMAGE_BASE . $padded . '.png';

		$response = wp_remote_get($url, ['timeout' => 30]);
		if (is_wp_error($response)) {
			error_log('SBPR: Failed to fetch punk image - ' . $response->get_error_message());
			return '';
		}

		$code = wp_remote_retrieve_response_code($response);
		if ($code !== 200) {
			error_log('SBPR: Punk image returned HTTP ' . $code . ' for punk ' . $punk_id);
			return '';
		}

		$body = (string)wp_remote_retrieve_body($response);
		if ($body === '') {
			error_log('SBPR: Empty response for punk ' . $punk_id);
			return '';
		}

		return $body;
	}

	/**
	 * Generate punk image and set as featured image.
	 * IMPORTANT: stores ORIGINAL 24x24 to avoid baking in blur.
	 */
	public static function generate_punk_image(int $post_id, int $punk_id) : bool {
		// If there IS a featured image and it's ours but not 24x24, replace it.
		if (has_post_thumbnail($post_id)) {
			$thumb_id = (int)get_post_thumbnail_id($post_id);
			if ($thumb_id && self::is_generated_punk_attachment($thumb_id, $punk_id)) {
				$meta = wp_get_attachment_metadata($thumb_id);
				$w = (int)($meta['width'] ?? 0);
				$h = (int)($meta['height'] ?? 0);
				if ($w !== 24 || $h !== 24) {
					wp_delete_attachment($thumb_id, true);
					delete_post_thumbnail($post_id);
				} else {
					return true; // already correct
				}
			} else {
				return true; // not ours, don't touch
			}
		}

		$image_data = self::fetch_punk_image($punk_id);
		if ($image_data === '') {
			error_log('SBPR: Could not fetch image for punk ' . $punk_id);
			return false;
		}

		$upload_dir = wp_upload_dir();
		if (!empty($upload_dir['error'])) {
			error_log('SBPR: Upload dir error - ' . $upload_dir['error']);
			return false;
		}

		$filename  = 'punk-' . $punk_id . '-' . time() . '.png';
		$file_path = $upload_dir['path'] . '/' . $filename;
		$file_url  = $upload_dir['url'] . '/' . $filename;

		if (file_put_contents($file_path, $image_data) === false) {
			error_log('SBPR: Could not write file for punk ' . $punk_id);
			return false;
		}

		$attachment = [
			'post_mime_type' => 'image/png',
			'post_title'     => 'CryptoPunk #' . $punk_id,
			'post_content'   => '',
			'post_status'    => 'inherit',
			'guid'           => $file_url,
		];

		$attachment_id = wp_insert_attachment($attachment, $file_path, $post_id);
		if (is_wp_error($attachment_id) || !$attachment_id) {
			error_log('SBPR: Failed to create attachment for punk ' . $punk_id);
			@unlink($file_path);
			return false;
		}

		// Detect true dimensions (should be 24x24)
		$w = 24; $h = 24;
		if (function_exists('getimagesizefromstring')) {
			$info = @getimagesizefromstring($image_data);
			if (is_array($info) && !empty($info[0]) && !empty($info[1])) {
				$w = (int)$info[0];
				$h = (int)$info[1];
			}
		}

		$metadata = [
			'width'  => $w,
			'height' => $h,
			'file'   => $upload_dir['subdir'] . '/' . $filename,
			'sizes'  => [],
		];
		wp_update_attachment_metadata($attachment_id, $metadata);

		update_post_meta($attachment_id, self::META_GEN_MARKER, '1');
		update_post_meta($attachment_id, self::META_GEN_PUNK_ID, (string)$punk_id);

		set_post_thumbnail($post_id, $attachment_id);
		return true;
	}

	// -------------------------
	// Prevent WP resized variants / srcset for punk attachments
	// -------------------------

	private static function is_punk_file($file) : bool {
		$base = basename((string)$file);
		// punk-4018-1700000000.png
		return (bool)preg_match('/^punk-\d{1,5}-\d+\.png$/i', $base);
	}

	public static function skip_punk_thumbnails($sizes, $image_meta, $attachment_id = 0) {
		if (!empty($image_meta['file']) && self::is_punk_file($image_meta['file'])) {
			return [];
		}
		return $sizes;
	}

	public static function disable_srcset_for_punks($sources, $size_array, $image_src, $image_meta, $attachment_id) {
		if (!empty($image_meta['file']) && self::is_punk_file($image_meta['file'])) {
			return false;
		}
		return $sources;
	}

	// -------------------------
	// Single rendering helpers
	// -------------------------

	public static function cp_details_url($punk_num) : string {
		$n = preg_replace('/[^0-9]/', '', (string)$punk_num);
		return esc_url(self::CP_DETAILS_BASE . $n);
	}

	public static function cp_account_url($wallet) : string {
		$w = strtolower(trim((string)$wallet));
		return esc_url(self::CP_ACCOUNT_BASE . $w);
	}

	public static function os_wrapped_url($punk_num) : string {
		$n = preg_replace('/[^0-9]/', '', (string)$punk_num);
		return esc_url(self::OS_WRAPPED_BASE . $n);
	}

	public static function extract_story_html($content) : string {
		$content = (string)$content;
		if (!$content) return '';

		$pos = stripos($content, '</h4>');
		if ($pos !== false) {
			$after = substr($content, $pos + 5);
			return $after;
		}
		return $content;
	}

	// -------------------------
	// Admin UI: Institution URL field
	// -------------------------

	public static function institution_add_fields() : void {
		?>
		<div class="form-field">
			<label for="institution_url">Website URL</label>
			<input type="url" name="institution_url" id="institution_url" value="" />
			<p class="description">The institution's website (e.g., https://moma.org)</p>
		</div>
		<?php
	}

	public static function institution_edit_fields($term) : void {
		$url = get_term_meta($term->term_id, 'institution_url', true);
		?>
		<tr class="form-field">
			<th scope="row"><label for="institution_url">Website URL</label></th>
			<td>
				<input type="url" name="institution_url" id="institution_url" value="<?php echo esc_attr($url); ?>" />
				<p class="description">The institution's website (e.g., https://moma.org)</p>
			</td>
		</tr>
		<?php
	}

	public static function save_institution_fields($term_id) : void {
		if (isset($_POST['institution_url'])) {
			$url = esc_url_raw($_POST['institution_url']);
			update_term_meta($term_id, 'institution_url', $url);
		}
	}

	// -------------------------
	// Admin UI: Meta boxes
	// -------------------------

	public static function add_meta_boxes() : void {
		add_meta_box('sbpr_meta', 'Punk Details', [__CLASS__, 'render_meta_box'], self::PT, 'side', 'high');
		add_meta_box('sbpr_wallet', 'Wallet Info', [__CLASS__, 'render_wallet_box'], self::PT, 'normal', 'high');
		add_meta_box('sbpr_acquisition', 'Acquisition Details', [__CLASS__, 'render_acquisition_box'], self::PT, 'normal', 'default');
	}

	private static function field_row($label, $name, $value, $placeholder = '') {
		?>
		<p style="margin:0 0 12px;">
			<label><strong><?php echo esc_html($label); ?></strong></label><br/>
			<input type="text" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>" placeholder="<?php echo esc_attr($placeholder); ?>" style="width:100%;" />
		</p>
		<?php
	}

	public static function render_meta_box($post) : void {
		wp_nonce_field('sbpr_save_meta', 'sbpr_nonce');

		$punk_id = (string)get_post_meta($post->ID, self::META_PUNK_ID, true);
		$v1_wrapped = (string)get_post_meta($post->ID, self::META_V1_WRAPPED, true);
		?>
		<p>
			<label for="sbpr_punk_id"><strong>Punk #</strong></label><br/>
			<input type="number" min="0" max="9999" id="sbpr_punk_id" name="sbpr_punk_id" value="<?php echo esc_attr($punk_id); ?>" style="width:100%;" />
		</p>
		<p>
			<label><strong>V1 status</strong></label><br/>
			<label><input type="radio" name="sbpr_v1_wrapped" value="0" <?php checked($v1_wrapped !== '1'); ?> /> Unwrapped</label><br/>
			<label><input type="radio" name="sbpr_v1_wrapped" value="1" <?php checked($v1_wrapped === '1'); ?> /> Wrapped</label>
		</p>
		<p style="opacity:.8;margin:0;">
			Image is stored as <strong>original 24×24 PNG</strong>. Front-end scales via canvas (no smoothing).
		</p>
		<?php
	}

	public static function render_wallet_box($post) : void {
		$museum_wallet = (string)get_post_meta($post->ID, self::META_MUSEUM_WALLET, true);
		self::field_row('Museum Wallet', 'sbpr_museum_wallet', $museum_wallet, '0x...');
	}

	public static function render_acquisition_box($post) : void {
		$acq_date = (string)get_post_meta($post->ID, self::META_ACQUISITION_DATE, true);
		$ann_url = (string)get_post_meta($post->ID, self::META_ANNOUNCEMENT_URL, true);
		$type = (string)get_post_meta($post->ID, self::META_ACQUISITION_TYPE, true);
		$donor_name = (string)get_post_meta($post->ID, self::META_DONOR_NAME, true);
		$donor_url = (string)get_post_meta($post->ID, self::META_DONOR_URL, true);

		?>
		<p>
			<label><strong>Acquisition date</strong></label><br/>
			<input type="date" name="sbpr_acquisition_date" value="<?php echo esc_attr($acq_date); ?>" />
		</p>
		<?php self::field_row('Announcement URL', 'sbpr_announcement_url', $ann_url, 'https://...'); ?>

		<p>
			<label><strong>Type</strong></label><br/>
			<select name="sbpr_acquisition_type">
				<option value="" <?php selected($type, ''); ?>>—</option>
				<option value="purchase" <?php selected($type, 'purchase'); ?>>Purchase</option>
				<option value="donation" <?php selected($type, 'donation'); ?>>Donation</option>
			</select>
		</p>

		<?php self::field_row('Donor name (if donation)', 'sbpr_donor_name', $donor_name, 'Name'); ?>
		<?php self::field_row('Donor URL (optional)', 'sbpr_donor_url', $donor_url, 'https://...'); ?>
		<?php
	}

	// -------------------------
	// Save Meta
	// -------------------------

	public static function save_meta($post_id, $post, $update) : void {
		if (!isset($_POST['sbpr_nonce']) || !wp_verify_nonce($_POST['sbpr_nonce'], 'sbpr_save_meta')) return;
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
		if (!current_user_can('edit_post', $post_id)) return;

		$punk_id_to_generate = null;

		if (isset($_POST['sbpr_punk_id']) && $_POST['sbpr_punk_id'] !== '') {
			$punk_id = (int)$_POST['sbpr_punk_id'];
			if ($punk_id >= 0 && $punk_id <= 9999) {
				$old_punk_id = (string)get_post_meta($post_id, self::META_PUNK_ID, true);
				update_post_meta($post_id, self::META_PUNK_ID, (string)$punk_id);

				// Keep title + slug synced to the number
				$new_title = (string)$punk_id;
				if ($post->post_title !== $new_title || $post->post_name !== $new_title) {
					remove_action('save_post_' . self::PT, [__CLASS__, 'save_meta'], 10);
					wp_update_post([
						'ID' => $post_id,
						'post_title' => $new_title,
						'post_name' => $new_title,
					]);
					add_action('save_post_' . self::PT, [__CLASS__, 'save_meta'], 10, 3);
				}

				// Always ensure image exists (and will auto-replace old 480x480 generated ones)
				if ($old_punk_id !== (string)$punk_id || !has_post_thumbnail($post_id)) {
					$punk_id_to_generate = $punk_id;
				} else {
					// If it has a thumbnail, still verify it isn't one of our old 480x480 generated ones
					$thumb_id = (int)get_post_thumbnail_id($post_id);
					if ($thumb_id && self::is_generated_punk_attachment($thumb_id, $punk_id)) {
						$meta = wp_get_attachment_metadata($thumb_id);
						$w = (int)($meta['width'] ?? 0);
						$h = (int)($meta['height'] ?? 0);
						if ($w !== 24 || $h !== 24) $punk_id_to_generate = $punk_id;
					}
				}
			}
		}

		// V1 Wrapped status
		$v1_wrapped = isset($_POST['sbpr_v1_wrapped']) ? ((string)$_POST['sbpr_v1_wrapped'] === '1' ? '1' : '0') : '0';
		update_post_meta($post_id, self::META_V1_WRAPPED, $v1_wrapped);

		// Wallet info
		$museum_wallet = isset($_POST['sbpr_museum_wallet']) ? sanitize_text_field((string)$_POST['sbpr_museum_wallet']) : '';
		if ($museum_wallet) update_post_meta($post_id, self::META_MUSEUM_WALLET, strtolower($museum_wallet));

		// Acquisition details
		$acquisition_date_in = isset($_POST['sbpr_acquisition_date']) ? (string)$_POST['sbpr_acquisition_date'] : '';
		$acquisition_date = self::normalize_date($acquisition_date_in);
		if ($acquisition_date) update_post_meta($post_id, self::META_ACQUISITION_DATE, $acquisition_date);

		$announcement_url = isset($_POST['sbpr_announcement_url']) ? esc_url_raw((string)$_POST['sbpr_announcement_url']) : '';
		update_post_meta($post_id, self::META_ANNOUNCEMENT_URL, $announcement_url);

		$acquisition_type = isset($_POST['sbpr_acquisition_type']) ? sanitize_text_field((string)$_POST['sbpr_acquisition_type']) : '';
		if (!in_array($acquisition_type, ['donation', 'purchase', ''], true)) $acquisition_type = '';
		update_post_meta($post_id, self::META_ACQUISITION_TYPE, $acquisition_type);

		$donor_name = isset($_POST['sbpr_donor_name']) ? sanitize_text_field((string)$_POST['sbpr_donor_name']) : '';
		$donor_url  = isset($_POST['sbpr_donor_url']) ? esc_url_raw((string)$_POST['sbpr_donor_url']) : '';
		update_post_meta($post_id, self::META_DONOR_NAME, $donor_name);
		update_post_meta($post_id, self::META_DONOR_URL, $donor_url);

		// Generate image (original 24x24)
		if ($punk_id_to_generate !== null) {
			self::generate_punk_image($post_id, $punk_id_to_generate);
		}
	}
}

SB_Punks_Registry::init();
register_activation_hook(__FILE__, ['SB_Punks_Registry', 'activate']);
register_deactivation_hook(__FILE__, ['SB_Punks_Registry', 'deactivate']);
