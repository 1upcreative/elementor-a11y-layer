<?php
/**
 * Accessibility Layer: final-HTML pass.
 *
 * Elementor widgets print markup you can't reach from the editor (heading levels, gallery labels,
 * menu names). This pass runs once on the finished page, before the page cache stores it, so every
 * page is fixed the same way no matter how it was built. Each fix is a small, targeted rewrite,
 * and each one can be turned off in the settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class A11y_Layer_Html {

	/** Blocks whose insides are never page content (scripts, styles, templates, inline SVG). */
	const RAW_BLOCKS = '#(<(script|style|template|noscript|textarea|svg)\b[^>]*>.*?</\2>)#is';

	public static function process( $html ) {
		if ( ! is_string( $html ) || false === stripos( $html, '<html' ) || empty( A11y_Layer::$config ) ) {
			return $html; // Not a full page (feeds, JSON, partial AJAX): leave it alone.
		}

		// Work only on the markup between raw blocks; put the raw blocks back untouched.
		$parts = preg_split( self::RAW_BLOCKS, $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( ! is_array( $parts ) ) {
			return $html;
		}
		$markup = [];
		$i      = 0;
		$count  = count( $parts );
		$tokens = [];
		while ( $i < $count ) {
			$markup[] = $parts[ $i ];
			$i++;
			if ( $i < $count ) {
				$tokens[ count( $markup ) - 1 ] = $parts[ $i ]; // The raw block after this markup chunk.
				$i += 2; // Skip the captured tag name.
			}
		}
		if ( false !== strpos( implode( '', $markup ), "\x1B" ) ) {
			return $html; // Our chunk separator is already in the page: don't risk it.
		}

		$joined = implode( "\x1B", $markup );
		$steps  = [
			'landmark'       => 'one_main',
			'headings'       => 'relevel_headings',
			'galleries'      => 'label_galleries',
			'post_cards'     => 'fix_post_cards',
			'nav_names'      => 'name_menus',
			'carousel_names' => 'name_carousels',
			'color_swaps'    => 'swap_colors',
			'tables'         => 'table_headers',
		];
		foreach ( $steps as $feature => $method ) {
			if ( A11y_Layer::on( $feature ) ) {
				$next = self::$method( $joined );
				if ( is_string( $next ) ) {
					$joined = $next; // A regex that gave up returns null: keep the last good markup.
				}
			}
		}
		$fixed = explode( "\x1B", $joined );

		if ( count( $fixed ) !== count( $markup ) ) {
			return $html; // Something unexpected: ship the original rather than a broken page.
		}

		$result = '';
		foreach ( $fixed as $n => $chunk ) {
			$result .= $chunk;
			if ( isset( $tokens[ $n ] ) ) {
				$result .= $tokens[ $n ];
			}
		}
		return $result;
	}

	private static function prefix() {
		return A11y_Layer::$config['prefix'];
	}

	/**
	 * A page gets one <main>. When the theme printed its own inside ours (Hello Elementor does when
	 * no Elementor single or archive template matches), ours steps aside. Ours is the outer one,
	 * so its closing tag is the last </main> on the page.
	 */
	private static function one_main( $html ) {
		$open = '#<main id="[^"]*" class="' . preg_quote( self::prefix(), '#' ) . '-main" tabindex="-1">#';
		if ( ! preg_match( $open, $html ) || preg_match_all( '#<main\b#i', $html ) < 2 ) {
			return $html;
		}
		$html = preg_replace( $open, '', $html, 1 );
		$last = strripos( $html, '</main>' );
		return false === $last ? $html : substr_replace( $html, '', $last, 7 );
	}

	/**
	 * Headings must never skip a level going down (h2 then h5 reads as missing sections).
	 * A skipped heading moves up to the next allowed level and keeps its old look through a
	 * {prefix}-hN class, so the page looks exactly the same.
	 */
	private static function relevel_headings( $html ) {
		$hidden_mark = ' data-' . self::prefix() . '-hidden';
		$top_mark    = ' data-' . self::prefix() . '-top';

		// Headings in widgets hidden on every device are never seen or read: they don't count.
		if ( false !== strpos( $html, 'elementor-hidden-mobile' ) ) {
			$html = preg_replace_callback(
				'#<div\s[^>]*class="([^"]*)"[^>]*>\s*(?:<div[^>]*>\s*)?<h[1-6]\b#i',
				function ( $m ) use ( $hidden_mark ) {
					$classes = ' ' . $m[1] . ' ';
					foreach ( [ 'desktop', 'tablet', 'mobile' ] as $device ) {
						if ( false === strpos( $classes, ' elementor-hidden-' . $device . ' ' ) ) {
							return $m[0];
						}
					}
					return $m[0] . $hidden_mark;
				},
				$html
			);
		}

		// Every page needs one top heading: when none exists, the first heading in <main> is it.
		if ( ! preg_match( '#<h1\b#i', $html ) ) {
			$html = preg_replace( '#(<main\b.*?<h)([2-6])\b#is', '${1}${2}' . $top_mark, $html, 1 );
		}

		$outline = []; // Open sections as [ original level, new level ], so sibling headings stay siblings.
		$stack   = []; // New levels of headings still open, to rename their closing tags.
		$prefix  = self::prefix();
		return preg_replace_callback(
			'#<(/?)h([1-6])\b([^>]*)>#i',
			function ( $m ) use ( &$outline, &$stack, $hidden_mark, $top_mark, $prefix ) {
				$level = (int) $m[2];
				if ( '/' === $m[1] ) {
					$new = array_pop( $stack );
					return '</h' . ( $new ? $new : $level ) . '>';
				}
				if ( false !== strpos( $m[3], $hidden_mark ) ) {
					$stack[] = $level;
					return '<h' . $level . str_replace( $hidden_mark, '', $m[3] ) . '>';
				}
				$top = false !== strpos( $m[3], $top_mark );
				if ( $top ) {
					$m[3] = str_replace( $top_mark, '', $m[3] );
				}
				while ( $outline && end( $outline )[0] >= $level ) {
					array_pop( $outline );
				}
				$new       = $top ? 1 : ( $outline ? min( $level, end( $outline )[1] + 1 ) : $level );
				$outline[] = [ $level, $new ];
				$stack[]   = $new;
				if ( $new === $level ) {
					return '<h' . $level . $m[3] . '>';
				}
				$attrs = $m[3];
				$look  = $prefix . '-h' . $level;
				if ( preg_match( '#\sclass=(["\'])(.*?)\1#i', $attrs ) ) {
					$attrs = preg_replace( '#\sclass=(["\'])(.*?)\1#i', ' class=$1$2 ' . $look . '$1', $attrs, 1 );
				} else {
					$attrs .= ' class="' . $look . '"';
				}
				return '<h' . $new . $attrs . '>';
			},
			$html
		);
	}

	/**
	 * Elementor galleries: each photo link gets a spoken name ("View photo 3 of 12"), and the
	 * background-image div inside it stops claiming to be an unlabeled image.
	 */
	private static function label_galleries( $html ) {
		if ( false === strpos( $html, 'e-gallery-item' ) && false === strpos( $html, 'aria-label="" role="img"' ) ) {
			return $html;
		}
		$labels = A11y_Layer::$config['labels'];
		$link   = '#<a\s[^>]*class="[^"]*\be-gallery-item\b[^"]*"[^>]*>#i';
		$totals = [];
		if ( preg_match_all( $link, $html, $all ) ) {
			foreach ( $all[0] as $tag ) {
				$set            = preg_match( '#data-elementor-lightbox-slideshow="([^"]*)"#', $tag, $s ) ? $s[1] : '';
				$totals[ $set ] = isset( $totals[ $set ] ) ? $totals[ $set ] + 1 : 1;
			}
		}
		$seen = [];
		$html = preg_replace_callback(
			$link,
			function ( $m ) use ( $totals, &$seen, $labels ) {
				$tag = $m[0];
				$set = preg_match( '#data-elementor-lightbox-slideshow="([^"]*)"#', $tag, $s ) ? $s[1] : '';
				$seen[ $set ] = isset( $seen[ $set ] ) ? $seen[ $set ] + 1 : 1;
				if ( preg_match( '#\saria-label="[^"]+"#', $tag ) ) {
					return $tag;
				}
				$label = sprintf( $labels['gallery_link'], $seen[ $set ], $totals[ $set ] );
				return substr( $tag, 0, -1 ) . ' aria-label="' . esc_attr( $label ) . '">';
			},
			$html
		);
		// Inside a labeled link the photo is decoration; outside one it gets a plain description.
		$html = preg_replace(
			'#(<a\s[^>]*\be-gallery-item\b[^>]*>\s*<div\s[^>]*?)\saria-label=""\s+role="img"#i',
			'$1 aria-hidden="true"',
			$html
		);
		return str_replace( 'aria-label="" role="img"', 'aria-label="' . esc_attr( $labels['gallery_image'] ) . '" role="img"', $html );
	}

	/**
	 * Elementor Posts cards: the photo link repeats the title link and is skipped by keyboard,
	 * so hide it from screen readers too; <article role="listitem"> becomes a <div> list item.
	 */
	private static function fix_post_cards( $html ) {
		if ( false === strpos( $html, 'elementor-post' ) ) {
			return $html;
		}
		$html = preg_replace(
			'#<a\s+class="elementor-post__thumbnail__link"([^>]*)tabindex="-1"(?![^>]*aria-hidden)#i',
			'<a class="elementor-post__thumbnail__link"$1tabindex="-1" aria-hidden="true"',
			$html
		);
		if ( false === strpos( $html, 'role="listitem"' ) ) {
			return $html;
		}
		$parts = preg_split( '#(<article\s[^>]*\belementor-post\b[^>]*role="listitem"[^>]*>)#i', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		$out   = $parts[0];
		for ( $i = 1, $n = count( $parts ); $i < $n; $i += 2 ) {
			$out .= '<div' . substr( $parts[ $i ], 8 );
			$body = $parts[ $i + 1 ];
			$pos  = stripos( $body, '</article>' );
			$out .= false === $pos ? $body : substr_replace( $body, '</div>', $pos, 10 );
		}
		return $out;
	}

	/**
	 * Elementor names every menu "Menu". The mega menu and the classic nav menu get the names in
	 * the settings, then any name still used twice gets a number ("Site menu 2").
	 */
	private static function name_menus( $html ) {
		if ( false === stripos( $html, '<nav' ) ) {
			return $html;
		}
		$names = A11y_Layer::$config['nav_names'];
		$html  = preg_replace_callback(
			'#<nav\b[^>]*>#i',
			function ( $m ) use ( $names ) {
				$tag = $m[0];
				if ( false === strpos( $tag, 'aria-label="Menu"' ) || ! preg_match( '#\sclass="([^"]*)"#', $tag, $c ) ) {
					return $tag;
				}
				$classes = ' ' . $c[1] . ' ';
				if ( false !== strpos( $classes, ' e-n-menu ' ) && ! empty( $names['mega'] ) ) {
					return str_replace( 'aria-label="Menu"', 'aria-label="' . esc_attr( $names['mega'] ) . '"', $tag );
				}
				if ( false !== strpos( $classes, ' elementor-nav-menu--main ' ) && ! empty( $names['classic'] ) ) {
					return str_replace( 'aria-label="Menu"', 'aria-label="' . esc_attr( $names['classic'] ) . '"', $tag );
				}
				return $tag;
			},
			$html
		);

		// Count names on menus screen readers can reach (hidden copies don't count).
		$visible = '#<nav\b(?![^>]*aria-hidden="true")[^>]*\saria-label="([^"]+)"[^>]*>#i';
		if ( ! preg_match_all( $visible, $html, $all ) ) {
			return $html;
		}
		$totals = array_count_values( $all[1] );
		$seen   = [];
		return preg_replace_callback(
			$visible,
			function ( $m ) use ( $totals, &$seen ) {
				$name = $m[1];
				if ( $totals[ $name ] < 2 ) {
					return $m[0];
				}
				$seen[ $name ] = isset( $seen[ $name ] ) ? $seen[ $name ] + 1 : 1;
				if ( 1 === $seen[ $name ] ) {
					return $m[0];
				}
				return str_replace( 'aria-label="' . $name . '"', 'aria-label="' . $name . ' ' . $seen[ $name ] . '"', $m[0] );
			},
			$html
		);
	}

	/** Repeated carousel names ("Carousel", "Image Carousel") get numbers that tell them apart. */
	private static function name_carousels( $html ) {
		$label = '#aria-label="((?:[A-Z][a-z]+ )?Carousel)"#';
		if ( ! preg_match_all( $label, $html, $all ) ) {
			return $html;
		}
		$totals  = array_count_values( $all[1] );
		$pattern = A11y_Layer::$config['labels']['carousel'];
		$seen    = [];
		return preg_replace_callback(
			$label,
			function ( $m ) use ( $totals, &$seen, $pattern ) {
				$name = $m[1];
				if ( $totals[ $name ] < 2 ) {
					return $m[0];
				}
				$seen[ $name ] = isset( $seen[ $name ] ) ? $seen[ $name ] + 1 : 1;
				return 'aria-label="' . esc_attr( sprintf( $pattern, $seen[ $name ], $totals[ $name ], $name ) ) . '"';
			},
			$html
		);
	}

	/**
	 * Text colors typed right into the content (pasted styles) that fail contrast get the
	 * readable shade from the color_swaps list. Only "color", never "background-color".
	 */
	private static function swap_colors( $html ) {
		$swaps = (array) A11y_Layer::$config['color_swaps'];
		if ( ! $swaps ) {
			return $html;
		}
		$rules = [];
		foreach ( $swaps as $from => $to ) {
			if ( preg_match( '/^#[0-9a-f]{3,8}$/i', (string) $from ) && preg_match( '/^#[0-9a-f]{3,8}$/i', (string) $to ) ) {
				$rules[ '#((?<![-\w])color:\s*)' . preg_quote( $from, '#' ) . '(?![0-9a-f])#i' ] = '${1}' . $to;
			}
		}
		if ( ! $rules ) {
			return $html;
		}
		return preg_replace_callback(
			'#\sstyle="[^"]*"#i',
			function ( $m ) use ( $rules ) {
				return preg_replace( array_keys( $rules ), array_values( $rules ), $m[0] );
			},
			$html
		);
	}

	/**
	 * Tables that mark their header row (and first column) with bold cells only.
	 * A bold first row becomes real column headers; a bold first cell in later rows becomes a row header.
	 */
	private static function table_headers( $html ) {
		if ( false === stripos( $html, '<table' ) ) {
			return $html;
		}
		$th = ' class="' . self::prefix() . '-th"';
		return preg_replace_callback(
			'#<table\b[^>]*>.*?</table>#is',
			function ( $m ) use ( $th ) {
				$table = $m[0];
				if ( false !== stripos( $table, '<th' ) ) {
					return $table;
				}
				$bold_cell = '<td>\s*<strong>(.*?)</strong>\s*</td>';
				if ( ! preg_match( '#<tbody>\s*<tr>((?:\s*' . $bold_cell . ')+)\s*</tr>#is', $table, $first ) ) {
					return $table;
				}
				$head  = preg_replace( '#' . $bold_cell . '#is', '<th scope="col"' . $th . '>$1</th>', $first[1] );
				$table = str_replace( $first[0], '<thead><tr>' . $head . '</tr></thead><tbody>', $table );
				return preg_replace( '#<tr>\s*' . $bold_cell . '#is', '<tr><th scope="row"' . $th . '>$1</th>', $table );
			},
			$html
		);
	}
}
