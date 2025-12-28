<?php
/**
 * Plugin Name: SB Punks Registry
 * Description: MuseumPunks registry + front-page mosaic + numeric permalinks + single punk layout.
 * Version: 0.4.0
 * Author: SB
 */

if (!defined('ABSPATH')) exit;

final class SB_Punks_Registry {
	const PT = 'sb_punk';
	const TAX_INSTITUTION = 'sb_institution';
	const OPT_KEY = 'sb_punks_registry_settings';

	// CryptoPunks site helpers
	const CP_DETAILS_BASE = 'https://cryptopunks.app/cryptopunks/details/';
	const CP_ACCOUNT_BASE = 'https://cryptopunks.app/cryptopunks/accountinfo?account=';

	// OpenSea V1 Wrapped Punks
	const OS_WRAPPED_BASE = 'https://opensea.io/assets/ethereum/0x282bdd42f4eb70e7a9d9f40c8fea0825b7f68c5d/';

	// Meta keys for MuseumPunks
	const META_PUNK_ID            = '_sbpr_punk_id';           // 0-9999 (kept in sync with title/slug)
	const META_ACQUISITION_DATE   = '_sbpr_acquisition_date';  // YYYY-MM-DD
	const META_ANNOUNCEMENT_URL   = '_sbpr_announcement_url';  // Acquisition announcement URL
	const META_MUSEUM_WALLET      = '_sbpr_museum_wallet';     // Wallet address
	const META_ACQUISITION_TYPE   = '_sbpr_acquisition_type';  // donation|purchase
	const META_DONOR_NAME         = '_sbpr_donor_name';        // If donated, donor name
	const META_DONOR_URL          = '_sbpr_donor_url';         // If donated, donor URL
	const META_V1_WRAPPED         = '_sbpr_v1_wrapped';        // '1'|'0' - is the V1 wrapped?

	public static function init() : void {
		add_action('init', [__CLASS__, 'register_cpt']);
		add_action('init', [__CLASS__, 'register_taxonomy']);
		add_action('init', [__CLASS__, 'register_rewrites'], 20);
		add_filter('query_vars', [__CLASS__, 'register_query_vars']);
		add_filter('template_include', [__CLASS__, 'template_override'], 50);

		add_action('init', [__CLASS__, 'force_no_comments'], 30);

		add_shortcode('sb_punks_home', [__CLASS__, 'shortcode_home']);
		add_shortcode('sb_punks_index', [__CLASS__, 'shortcode_index']);

		add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
		add_filter('body_class', [__CLASS__, 'body_class']);

		// Force pretty /####/ permalinks for sb_punk posts.
		add_filter('post_type_link', [__CLASS__, 'filter_sb_punk_link'], 10, 4);
		add_filter('post_link', [__CLASS__, 'filter_sb_punk_link'], 10, 3);

		// Use classic editor for this CPT.
		add_filter('use_block_editor_for_post_type', [__CLASS__, 'disable_block_editor_for_cpt'], 10, 2);

		// Hard-disable comments/pings for this CPT.
		add_filter('comments_open', [__CLASS__, 'comments_open'], 10, 2);
		add_filter('pings_open', [__CLASS__, 'pings_open'], 10, 2);

		// Admin UX
		add_action('admin_menu', [__CLASS__, 'register_settings_page']);
		add_action('admin_init', [__CLASS__, 'register_settings']);
		add_action('add_meta_boxes', [__CLASS__, 'add_meta_boxes']);
		add_action('save_post_' . self::PT, [__CLASS__, 'save_meta'], 10, 3);

		// Institution taxonomy custom fields
		add_action(self::TAX_INSTITUTION . '_add_form_fields', [__CLASS__, 'institution_add_fields']);
		add_action(self::TAX_INSTITUTION . '_edit_form_fields', [__CLASS__, 'institution_edit_fields'], 10);
		add_action('created_' . self::TAX_INSTITUTION, [__CLASS__, 'save_institution_fields']);
		add_action('edited_' . self::TAX_INSTITUTION, [__CLASS__, 'save_institution_fields']);

		// Disable thumbnail generation for punk images to preserve crisp pixels
		add_filter('intermediate_image_sizes_advanced', [__CLASS__, 'skip_punk_thumbnails'], 10, 3);
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

	public static function disable_block_editor_for_cpt($use, $post_type) {
		if ($post_type === self::PT) return false;
		return $use;
	}

	public static function force_no_comments() : void {
		remove_post_type_support(self::PT, 'comments');
		remove_post_type_support(self::PT, 'trackbacks');
		remove_post_type_support(self::PT, 'excerpt');
	}

	public static function comments_open($open, $post_id) {
		$post = get_post($post_id);
		if ($post && $post->post_type === self::PT) return false;
		return $open;
	}

	public static function pings_open($open, $post_id) {
		$post = get_post($post_id);
		if ($post && $post->post_type === self::PT) return false;
		return $open;
	}

	/**
	 * Skip thumbnail generation for punk images (filenames starting with "punk-").
	 * This preserves crisp pixel art by only using the full-size 480x480 image.
	 */
	public static function skip_punk_thumbnails($sizes, $image_meta, $attachment_id = 0) {
		if (!empty($image_meta['file']) && preg_match('/punk-\d{4}\.png$/i', $image_meta['file'])) {
			return []; // No intermediate sizes for punk images
		}
		return $sizes;
	}

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
				'search_items' => 'Search Institutions',
				'all_items' => 'All Institutions',
				'edit_item' => 'Edit Institution',
				'update_item' => 'Update Institution',
				'add_new_item' => 'Add New Institution',
				'new_item_name' => 'New Institution Name',
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

	// Institution taxonomy custom fields
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

	public static function register_rewrites() : void {
		// /5449/ -> sb_punk with slug/name 5449
		add_rewrite_rule(
			'^([0-9]{1,5})/?$',
			'index.php?post_type=' . self::PT . '&name=$matches[1]',
			'top'
		);

		// Force /the-punks/ to always render our index.
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

	public static function enqueue_assets() : void {
		$ver = '0.4.0';
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
				$out .= '<img class="sbpr-index__img" src="'.$thumb.'" alt="" loading="lazy" decoding="async" />';
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
	// Sorting helpers
	// -------------------------

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
			} else {
				$attached = get_attached_media('image', $id);
				if (!empty($attached)) {
					$first = reset($attached);
					$thumb = $first ? wp_get_attachment_image_url($first->ID, 'full') : '';
				}
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
	// Admin: settings + meta
	// -------------------------

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
			<p><strong>After updating:</strong> Settings &rarr; Permalinks &rarr; Save (once).</p>
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
			<label for="sbpr_v1_wrapped"><strong>V1 Wrapped?</strong></label><br/>
			<select id="sbpr_v1_wrapped" name="sbpr_v1_wrapped" style="width:100%;">
				<option value="0" <?php selected($v1_wrapped, '0'); ?>>No (Unwrapped)</option>
				<option value="1" <?php selected($v1_wrapped, '1'); ?>>Yes (Wrapped)</option>
			</select>
			<span class="description">If wrapped, shows OpenSea link</span>
		</p>
		<?php
	}

	public static function render_wallet_box($post) : void {
		$museum_wallet = (string)get_post_meta($post->ID, self::META_MUSEUM_WALLET, true);

		?>
		<p class="description" style="margin-top:0;">Institution is set via the "Institutions" taxonomy (see sidebar or below). Add wallet address here:</p>
		<?php
		self::field_row('Institution Wallet', 'sbpr_museum_wallet', $museum_wallet, '0x...');
	}

	public static function render_acquisition_box($post) : void {
		$acquisition_date   = (string)get_post_meta($post->ID, self::META_ACQUISITION_DATE, true);
		$announcement_url   = (string)get_post_meta($post->ID, self::META_ANNOUNCEMENT_URL, true);
		$acquisition_type   = (string)get_post_meta($post->ID, self::META_ACQUISITION_TYPE, true);
		$donor_name         = (string)get_post_meta($post->ID, self::META_DONOR_NAME, true);
		$donor_url          = (string)get_post_meta($post->ID, self::META_DONOR_URL, true);

		?>
		<p style="margin:0 0 12px;">
			<label for="sbpr_acquisition_date"><strong>Acquisition Date (YYYY-MM-DD)</strong></label><br/>
			<input type="text" id="sbpr_acquisition_date" name="sbpr_acquisition_date" value="<?php echo esc_attr($acquisition_date); ?>" placeholder="2024-01-15" style="width:100%;" />
			<span class="description">Controls ordering on /the-punks/ (newest first).</span>
		</p>

		<?php self::field_row('Acquisition Announcement URL', 'sbpr_announcement_url', $announcement_url, 'https://...'); ?>

		<p style="margin:0 0 12px;">
			<label for="sbpr_acquisition_type"><strong>Acquisition Type</strong></label><br/>
			<select id="sbpr_acquisition_type" name="sbpr_acquisition_type" style="width:100%;">
				<option value="" <?php selected($acquisition_type, ''); ?>>(not set)</option>
				<option value="donation" <?php selected($acquisition_type, 'donation'); ?>>Donation</option>
				<option value="purchase" <?php selected($acquisition_type, 'purchase'); ?>>Purchase</option>
			</select>
		</p>

		<div id="sbpr_donor_fields" style="<?php echo ($acquisition_type !== 'donation') ? 'display:none;' : ''; ?>">
			<hr/>
			<p class="description" style="margin-top:0;">Donor info (only shown if acquisition type is "Donation"):</p>
			<?php
			self::field_row('Donor Name', 'sbpr_donor_name', $donor_name, 'John Smith');
			self::field_row('Donor URL', 'sbpr_donor_url', $donor_url, 'https://twitter.com/...');
			?>
		</div>

		<script>
		(function(){
			var sel = document.getElementById('sbpr_acquisition_type');
			var fields = document.getElementById('sbpr_donor_fields');
			if(sel && fields){
				sel.addEventListener('change', function(){
					fields.style.display = (sel.value === 'donation') ? '' : 'none';
				});
			}
		})();
		</script>
		<?php
	}

	// -------------------------
	// Punk image generation
	// -------------------------

	// Larva Labs official punk images
	const PUNK_IMAGE_BASE = 'https://www.larvalabs.com/public/images/cryptopunks/punk';

	/**
	 * Fetch punk PNG from Larva Labs (returns original 24x24 image)
	 * Client-side JavaScript handles scaling for crisp pixel art display
	 */
	private static function fetch_punk_image(int $punk_id) : string {
		if ($punk_id < 0 || $punk_id > 9999) return '';

		// Format: punk0001.png, punk0123.png, punk9999.png
		$padded = str_pad((string)$punk_id, 4, '0', STR_PAD_LEFT);
		$url = self::PUNK_IMAGE_BASE . $padded . '.png';

		$response = wp_remote_get($url, [
			'timeout' => 30,
		]);

		if (is_wp_error($response)) {
			error_log('SBPR: Failed to fetch punk image - ' . $response->get_error_message());
			return '';
		}

		$code = wp_remote_retrieve_response_code($response);
		if ($code !== 200) {
			error_log('SBPR: Punk image returned HTTP ' . $code . ' for punk ' . $punk_id);
			return '';
		}

		$body = wp_remote_retrieve_body($response);
		if (empty($body)) {
			error_log('SBPR: Empty response for punk ' . $punk_id);
			return '';
		}

		// Return original 24x24 image - JavaScript will scale client-side
		return $body;
	}

	/**
	 * Scale PNG image data using nearest-neighbor interpolation
	 * Each source pixel becomes a scale x scale block in the destination
	 */
	private static function scale_image_nearest_neighbor(string $image_data, int $target_size) : string {
		if (!function_exists('imagecreatefromstring')) {
			error_log('SBPR: GD not available for scaling');
			return '';
		}

		$src = @imagecreatefromstring($image_data);
		if ($src === false) {
			error_log('SBPR: GD could not read image');
			return '';
		}

		$src_w = imagesx($src);
		$src_h = imagesy($src);
		$scale = (int)($target_size / $src_w); // Integer scale factor (e.g., 20 for 24->480)

		// Create truecolor destination for proper alpha support
		$dst = imagecreatetruecolor($target_size, $target_size);
		if ($dst === false) {
			imagedestroy($src);
			return '';
		}

		// CRITICAL: Disable alpha blending so colors are written exactly
		imagealphablending($dst, false);
		imagesavealpha($dst, true);

		// Scale using pure pixel-by-pixel copy (no GD drawing functions)
		for ($dy = 0; $dy < $target_size; $dy++) {
			$sy = (int)($dy / $scale);
			for ($dx = 0; $dx < $target_size; $dx++) {
				$sx = (int)($dx / $scale);

				// Get source pixel color index
				$src_color = imagecolorat($src, $sx, $sy);

				// Get RGBA components
				$rgba = imagecolorsforindex($src, $src_color);

				// Create color with exact RGBA values
				$dst_color = ($rgba['alpha'] << 24) | ($rgba['red'] << 16) | ($rgba['green'] << 8) | $rgba['blue'];

				// Set pixel directly
				imagesetpixel($dst, $dx, $dy, $dst_color);
			}
		}

		ob_start();
		imagepng($dst, null, 9);
		$result = ob_get_clean();

		imagedestroy($src);
		imagedestroy($dst);

		return $result;
	}

	/**
	 * Generate punk image and set as featured image
	 * Stores original 24x24 image - JavaScript handles client-side scaling
	 */
	public static function generate_punk_image(int $post_id, int $punk_id) : bool {
		// Don't regenerate if featured image already exists
		if (has_post_thumbnail($post_id)) {
			return true;
		}

		$image_data = self::fetch_punk_image($punk_id);
		if (empty($image_data)) {
			error_log('SBPR: Could not fetch image for punk ' . $punk_id);
			return false;
		}

		// Get uploads directory
		$upload_dir = wp_upload_dir();
		if (!empty($upload_dir['error'])) {
			error_log('SBPR: Upload dir error - ' . $upload_dir['error']);
			return false;
		}

		// Add timestamp to filename to bust any server/CDN cache
		$filename = 'punk-' . $punk_id . '-' . time() . '.png';
		$file_path = $upload_dir['path'] . '/' . $filename;
		$file_url = $upload_dir['url'] . '/' . $filename;

		// Write file directly to uploads folder (original 24x24)
		if (file_put_contents($file_path, $image_data) === false) {
			error_log('SBPR: Could not write file for punk ' . $punk_id);
			return false;
		}

		// Create attachment post manually (bypasses wp_generate_attachment_metadata)
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

		// Set metadata for original 24x24 image
		$metadata = [
			'width'  => 24,
			'height' => 24,
			'file'   => $upload_dir['subdir'] . '/' . $filename,
			'sizes'  => [], // No thumbnails - JS handles scaling
		];
		wp_update_attachment_metadata($attachment_id, $metadata);

		// Set as featured image
		set_post_thumbnail($post_id, $attachment_id);

		return true;
	}

	public static function save_meta($post_id, $post, $update) : void {
		if (!isset($_POST['sbpr_nonce']) || !wp_verify_nonce($_POST['sbpr_nonce'], 'sbpr_save_meta')) return;
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
		if (!current_user_can('edit_post', $post_id)) return;

		// Punk # (keep title/slug synced)
		$punk_id_to_generate = null;
		if (isset($_POST['sbpr_punk_id']) && $_POST['sbpr_punk_id'] !== '') {
			$punk_id = (int)$_POST['sbpr_punk_id'];
			if ($punk_id >= 0 && $punk_id <= 9999) {
				$old_punk_id = (string)get_post_meta($post_id, self::META_PUNK_ID, true);
				update_post_meta($post_id, self::META_PUNK_ID, (string)$punk_id);

				$desired = (string)$punk_id;
				if ($post->post_name !== $desired || $post->post_title !== $desired) {
					remove_action('save_post_' . self::PT, [__CLASS__, 'save_meta'], 10);
					wp_update_post([
						'ID' => $post_id,
						'post_name' => $desired,
						'post_title' => $desired,
					]);
					add_action('save_post_' . self::PT, [__CLASS__, 'save_meta'], 10, 3);
				}

				// Generate image if this is a new punk ID or no featured image exists
				if ($old_punk_id !== (string)$punk_id || !has_post_thumbnail($post_id)) {
					$punk_id_to_generate = $punk_id;
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

		// Donor info (only relevant if type is donation)
		$donor_name = isset($_POST['sbpr_donor_name']) ? sanitize_text_field((string)$_POST['sbpr_donor_name']) : '';
		$donor_url = isset($_POST['sbpr_donor_url']) ? esc_url_raw((string)$_POST['sbpr_donor_url']) : '';
		update_post_meta($post_id, self::META_DONOR_NAME, $donor_name);
		update_post_meta($post_id, self::META_DONOR_URL, $donor_url);

		// Generate on-chain punk image if needed
		if ($punk_id_to_generate !== null) {
			self::generate_punk_image($post_id, $punk_id_to_generate);
		}
	}
}

SB_Punks_Registry::init();
register_activation_hook(__FILE__, ['SB_Punks_Registry', 'activate']);
register_deactivation_hook(__FILE__, ['SB_Punks_Registry', 'deactivate']);
