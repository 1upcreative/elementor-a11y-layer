<?php
/**
 * Accessibility Layer: settings, the main landmark, and loading the CSS and JS.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class A11y_Layer {

	/** @var array Final settings, after the prefix constant and the filter. */
	public static $config = [];

	/** @var bool True while our <main> is open and still needs its closing tag. */
	private static $main_open = false;

	/** @var string Full path of the loader file, for asset URLs. */
	private static $loader = '';

	public static function defaults() {
		return [
			// Short project name. Used for class names, style and script handles. Letters, digits, dashes.
			'prefix'       => defined( 'A11Y_LAYER_PREFIX' ) ? A11Y_LAYER_PREFIX : 'a11y',
			// The id the theme's "Skip to content" link points at. Hello Elementor uses "content".
			'main_id'      => 'content',
			// Turn any single fix off by setting it to false.
			'features'     => [
				'landmark'       => true, // <main> between the Elementor Pro header and footer.
				'headings'       => true, // Heading levels never skip; old sizes kept.
				'galleries'      => true, // "View photo 3 of 12" on gallery links.
				'post_cards'     => true, // Posts widget: no doubled photo link, valid list items.
				'nav_names'      => true, // Menus get names that tell them apart.
				'carousel_names' => true, // Repeated "Carousel" regions get numbered.
				'tables'         => true, // Bold first row becomes real column headers.
				'color_swaps'    => true, // Inline text colors swapped per the color_swaps list.
				'focus_ring'     => true, // Two-tone keyboard focus ring everywhere.
				'link_underline' => true, // Links inside running text are underlined.
				'reflow'         => true, // Long words wrap on phones instead of pushing the page sideways.
				'heading_fit'    => true, // Phone headings shrink only when a word would split.
				'wp_rocket'      => true, // Keep our CSS and JS out of WP Rocket's delay and unused-CSS steps.
			],
			// Focus ring: a solid ring with a light halo inside it, so it shows on light and dark.
			'focus'        => [
				'ring' => '#1A1A1A',
				'halo' => '#FFFFFF',
			],
			// Font sizes that headings keep after they move up a level (Hello Elementor's defaults).
			'heading_size' => [
				2 => '2rem',
				3 => '1.75rem',
				4 => '1.5rem',
				5 => '1.25rem',
				6 => '1rem',
			],
			// Names for menus. Elementor calls every menu "Menu".
			'nav_names'    => [
				'mega'    => 'Main menu', // Elementor Pro "Menu" widget (mega menu).
				'classic' => 'Site menu', // Elementor Pro "Nav Menu" widget.
			],
			// %1$d = this item, %2$d = how many, %3$s = the carousel's own name.
			'labels'       => [
				'gallery_link'  => 'View photo %1$d of %2$d',
				'gallery_image' => 'Photo',
				'carousel'      => '%3$s %1$d of %2$d',
			],
			// Inline text colors to replace, e.g. [ '#048bd1' => '#0379B5' ] for a link blue that fails contrast.
			'color_swaps'  => [],
			// Your own CSS, printed after ours (contrast tweaks for brand colors go here).
			'extra_css'    => '',
		];
	}

	public static function boot( $loader ) {
		self::$loader = $loader;
		add_action( 'init', [ __CLASS__, 'load_config' ], 1 );
		add_action( 'elementor/theme/after_do_header', [ __CLASS__, 'open_main' ] );
		add_action( 'get_footer', [ __CLASS__, 'close_main' ], 0 );
		add_action( 'elementor/theme/before_do_footer', [ __CLASS__, 'close_main' ], 0 );
		add_action( 'template_redirect', [ __CLASS__, 'start_buffer' ], PHP_INT_MAX );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue' ], 99 );
		add_filter( 'rocket_delay_js_exclusions', [ __CLASS__, 'rocket_keep_js' ] );
		add_filter( 'rocket_rucss_inline_atts_exclusions', [ __CLASS__, 'rocket_keep_css' ] );
	}

	public static function load_config() {
		$config = array_replace_recursive( self::defaults(), (array) apply_filters( 'a11y_layer_config', [] ) );

		$prefix           = strtolower( preg_replace( '/[^A-Za-z0-9-]/', '', (string) $config['prefix'] ) );
		$config['prefix'] = preg_match( '/^[a-z]/', $prefix ) ? $prefix : 'a11y';
		$config['main_id'] = sanitize_html_class( $config['main_id'], 'content' );

		self::$config = $config;
	}

	public static function on( $feature ) {
		return ! empty( self::$config['features'][ $feature ] );
	}

	/* ---------- The <main> landmark (and the "Skip to content" target) ---------- */

	/**
	 * Elementor Pro's header template just printed. Everything from here to the footer is the
	 * page's main content. If the theme prints its own <main> too, the HTML pass removes ours.
	 */
	public static function open_main() {
		if ( is_admin() || ! self::on( 'landmark' ) || self::$main_open ) {
			return;
		}
		self::$main_open = true;
		printf(
			'<main id="%s" class="%s-main" tabindex="-1">',
			esc_attr( self::$config['main_id'] ),
			esc_attr( self::$config['prefix'] )
		);
	}

	/** Closes on whichever comes first: the theme asking for its footer, or the Elementor footer. */
	public static function close_main() {
		if ( self::$main_open ) {
			self::$main_open = false;
			echo '</main>';
		}
	}

	/* ---------- Final-HTML pass ---------- */

	/**
	 * Started late on template_redirect so it sits inside page-cache buffers (WP Rocket and
	 * friends): cached pages are stored already fixed.
	 */
	public static function start_buffer() {
		if ( is_admin() || wp_doing_ajax() || is_feed() || is_embed() || isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		ob_start( [ 'A11y_Layer_Html', 'process' ] );
	}

	/* ---------- CSS and JS ---------- */

	private static function asset_url( $file ) {
		return plugin_dir_url( self::$loader ) . 'a11y-layer/' . $file;
	}

	private static function handle() {
		return 'a11y' === self::$config['prefix'] ? 'a11y-layer' : self::$config['prefix'] . '-a11y';
	}

	/** Only plain color values reach the stylesheet. */
	private static function css_value( $value, $fallback ) {
		$value = trim( (string) $value );
		return preg_match( '/^[#A-Za-z0-9(),.%\s-]+$/', $value ) ? $value : $fallback;
	}

	public static function css() {
		$css = (string) file_get_contents( __DIR__ . '/a11y-layer.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		// Drop the blocks for fixes that are turned off.
		$css = preg_replace_callback(
			'#/\* @feature (\w+) \*/(.*?)/\* @end \*/#s',
			function ( $m ) {
				return self::on( $m[1] ) ? $m[2] : '';
			},
			$css
		);

		$sizes = [];
		foreach ( (array) self::$config['heading_size'] as $level => $size ) {
			$level = (int) $level;
			if ( $level >= 2 && $level <= 6 ) {
				$sizes[] = '.' . self::$config['prefix'] . '-h' . $level . '{font-size:' . self::css_value( $size, 'inherit' ) . '}';
			}
		}

		$css = strtr(
			$css,
			[
				'__PREFIX__'       => self::$config['prefix'],
				'__MAIN_ID__'      => self::$config['main_id'],
				'__RING__'         => self::css_value( self::$config['focus']['ring'], '#1A1A1A' ),
				'__HALO__'         => self::css_value( self::$config['focus']['halo'], '#FFFFFF' ),
				'/* __SIZES__ */'  => self::on( 'headings' ) ? implode( "\n", $sizes ) : '',
			]
		);

		$extra = (string) self::$config['extra_css'];
		if ( '' !== $extra && false === stripos( $extra, '</style' ) ) {
			$css .= "\n" . $extra;
		}

		// Squeeze out comments and blank space: this prints inline on every page.
		$css = preg_replace( '#/\*.*?\*/#s', '', $css );
		return trim( preg_replace( '/\s+/', ' ', $css ) );
	}

	public static function enqueue() {
		// Inline CSS: no extra request, and it loads after Elementor's, so plain selectors win.
		wp_register_style( self::handle(), false, [], '1.0.0' );
		wp_enqueue_style( self::handle() );
		wp_add_inline_style( self::handle(), self::css() );

		if ( self::on( 'heading_fit' ) ) {
			$file = __DIR__ . '/a11y-layer.js';
			wp_enqueue_script(
				self::handle(),
				self::asset_url( 'a11y-layer.js' ),
				[],
				(string) filemtime( $file ),
				[
					'in_footer' => true,
					'strategy'  => 'defer',
				]
			);
		}
	}

	/* ---------- WP Rocket: run our script right away and keep our whole stylesheet ---------- */

	public static function rocket_keep_js( $exclusions ) {
		if ( self::on( 'wp_rocket' ) ) {
			$exclusions[] = 'a11y-layer/a11y-layer.js';
		}
		return $exclusions;
	}

	public static function rocket_keep_css( $exclusions ) {
		if ( self::on( 'wp_rocket' ) ) {
			$exclusions[] = self::handle() . '-inline-css';
		}
		return $exclusions;
	}
}
