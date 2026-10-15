# Accessibility Layer for Elementor

You build sites in Elementor by hand. Elementor prints some markup you can't change from the editor. Screen readers trip on it, and so do accessibility checkers like Lighthouse and axe.

This drop-in fixes that markup for you. You copy three files into your site. You don't need a build step or a settings page, and you don't edit any pages.

We built it for a real client site with 128 pages. After it went in, every page scored 100 for accessibility in Lighthouse on phone and desktop, and axe found 0 WCAG 2.2 AA problems.

## What it fixes

| Problem | What you see in a checker | What the layer does |
|---|---|---|
| The page has no `<main>` area | "Document should have one main landmark" | It wraps everything between your Elementor Pro header and footer in `<main id="content">`. Your theme's "Skip to content" link now has a place to land. If your theme already prints a `<main>`, the layer steps aside. |
| Headings skip levels (an H2 and then an H5) | "Heading levels should only increase by one" | It moves the heading up to the right level. A class keeps the old font size, so the page looks the same. |
| The page has no H1 | "Page should contain a level-one heading" | The first heading in `<main>` becomes the H1. Its size stays the same. |
| Gallery photos have no names | "Links must have discernible text" | Each photo link says "View photo 3 of 12". |
| Post cards link the same post twice | Extra links and invalid list markup | It hides the photo link from screen readers and fixes the list items. |
| Every menu is called "Menu" | "Landmarks should have a unique name" | Menus get names like "Main menu" and "Site menu". If two menus still share a name, it adds a number. |
| Carousels share one name | "Landmarks should have a unique name" | It numbers them: "Image Carousel 1 of 2". |
| Tables use bold text for headers | Screen readers can't tell headers from data | A bold first row becomes real column headers. |
| Keyboard focus is hard to see | Focus rings missing on some widgets | You get a two-tone ring (dark ring, light halo) on every link, button and field. It shows on light and dark backgrounds. |
| Links in paragraphs look like plain text | "Links must be distinguishable" | Links inside text blocks get an underline. |
| Long words push the page sideways on phones | Page scrolls sideways at 320px wide | Long words wrap on small screens. Headings shrink a little, only when a word would break in half. |

The layer fixes the page once, right before WordPress sends it. Your Elementor edits can't undo it. If you use WP Rocket, the layer runs inside the cache, so cached pages come out fixed too.

## What it can't fix

You still own these:

- **Colors.** Elementor's starter colors fail contrast (the light blue `#6EC1E4` and the gray `#7A7A7A`). Pick colors that pass in your Site Settings, or add CSS with the `extra_css` setting below.
- **Image alt text.** Write it in the Media Library.
- **Video captions.** Add them where you host the video.
- **Forms and iframes from other services.** Their owners have to fix those.

Always run a checker after you install it, and test with a keyboard and a screen reader. Tools only catch about a third of real problems.

## Install

1. Open your site's `wp-content` folder. Make a `mu-plugins` folder if you don't have one.
2. Copy `a11y-layer.php` and the `a11y-layer` folder into `mu-plugins`.
3. Open `wp-config.php` and add a short name for your project:

   ```php
   define( 'A11Y_LAYER_PREFIX', 'acme' );
   ```

That's it. WordPress loads files in `mu-plugins` on its own, so you have nothing to turn on.

The prefix goes on the class names and file handles the layer adds, like `acme-main` and `acme-h3`. Your markup then reads like your project's own code. Use letters, numbers and dashes, and start with a letter. If you skip this step, the prefix is `a11y`.

## Settings

You change settings with one filter. Put it in your theme's `functions.php` or in another file in `mu-plugins`. Only list the settings you want to change.

```php
add_filter( 'a11y_layer_config', function () {
	return [
		'prefix'      => 'acme',
		'focus'       => [ 'ring' => '#002F5C', 'halo' => '#FFFFFF' ],
		'color_swaps' => [ '#048bd1' => '#0379B5' ],
		'extra_css'   => 'body[class*="elementor-kit-"]{--e-global-color-text:#595959}',
	];
} );
```

| Setting | Default | What it does |
|---|---|---|
| `prefix` | `a11y` | Your project's short name. The constant `A11Y_LAYER_PREFIX` sets it too. |
| `main_id` | `content` | The id on `<main>`. Match the link your theme's "Skip to content" points at. Hello Elementor uses `content`. |
| `focus` | `#1A1A1A` ring, `#FFFFFF` halo | Focus ring colors. |
| `heading_size` | Hello Elementor's sizes | The font size each moved heading keeps, by its old level (2 to 6). |
| `nav_names` | `Main menu`, `Site menu` | Names for the Elementor Pro Menu widget (`mega`) and Nav Menu widget (`classic`). |
| `labels` | see below | The words screen readers hear. |
| `color_swaps` | none | Text colors to replace when someone pasted them right into the content. It only changes `color`, never `background-color`. |
| `extra_css` | none | Your own CSS. It prints after the layer's CSS. |

The `labels` setting holds three phrases:

- `gallery_link`: `View photo %1$d of %2$d`
- `gallery_image`: `Photo`
- `carousel`: `%3$s %1$d of %2$d`

`%1$d` is this item's number, and `%2$d` is how many there are. In `carousel`, `%3$s` is the name Elementor gave it. Translate these if your site isn't in English.

### Turn off one fix

Each fix has a switch. Set it to `false` to skip that fix:

```php
add_filter( 'a11y_layer_config', function () {
	return [ 'features' => [ 'link_underline' => false ] ];
} );
```

The switches are: `landmark`, `headings`, `galleries`, `post_cards`, `nav_names`, `carousel_names`, `tables`, `color_swaps`, `focus_ring`, `link_underline`, `reflow`, `heading_fit` and `wp_rocket`.

## Works with

- **Theme:** Hello Elementor, the theme we test on. The `<main>` fix needs a header and footer made with the Elementor Pro Theme Builder. Without Elementor Pro, the other fixes still work.
- **Elementor and Elementor Pro:** tested on 4.3.
- **WP Rocket:** built for 3.23. The layer keeps its script out of "Delay JavaScript" and keeps its CSS out of "Remove Unused CSS", so the focus ring shows before anyone taps the page.
- **WordPress:** 6.3 or newer.
- **PHP:** 7.4 or newer.

If your theme opens a wrapper `<div>` after the header and closes it in the footer, test the `<main>` fix first. It could close in the wrong spot. Turn off `landmark` if it does.

## Check your work

After you install it, run these and look for zero problems:

1. Lighthouse in Chrome DevTools, Accessibility only, on phone and desktop.
2. The axe DevTools browser extension.
3. Press Tab through the page. You should see the focus ring on every link and button.
4. Shrink the window to 320 pixels wide. The page shouldn't scroll sideways.

## How it works

The code lives in three files:

- `a11y-layer.php` loads the layer.
- `a11y-layer/class-a11y-layer.php` holds the settings, adds `<main>` and loads the CSS and script.
- `a11y-layer/class-a11y-layer-html.php` fixes the finished page.

It skips scripts, styles and SVG, so it only touches page markup. If anything looks wrong, it sends the original page instead. The CSS prints inline and weighs under 2 KB, so it costs no extra download. The small script only changes headings on screens 480 pixels wide or less.

## License

GPL-2.0-or-later, the same as WordPress. See `LICENSE`.
