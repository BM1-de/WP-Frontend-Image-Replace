=== BM1 Frontend Image Replace ===
Contributors: phillipbaumgaertner
Tags: images, replace, frontend, media, development
Requires at least: 5.4
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.2.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Replace images directly from the frontend. Upload a new image, and it replaces the old one in your content.

== Description ==

**BM1 Frontend Image Replace** lets you swap out images on your WordPress site without ever touching the admin panel. Simply hover over any image on the frontend, click, and upload a replacement.

Perfect for:

* Replacing demo or placeholder images during development
* Letting clients review and update images without backend access
* Quick image swaps during content reviews

**How it works:**

1. Enable the plugin in Settings > BM1 Frontend Image Replace
2. Visit any page on your site
3. Hover over an image — a replace overlay appears
4. Click and select a new image from your computer
5. The new image is uploaded to the media library and all references in your content are updated automatically

**Free features:**

* Works with any theme — no template modifications needed
* Uploads new images to the WordPress media library (the original stays untouched)
* Updates all references in post content
* Supports Gutenberg block editor, Classic editor, and LiveCanvas
* Lightweight — no jQuery dependency, minimal footprint
* Translation-ready (including German)

**Pro features (available at [wp-frontend-image-replace.com](https://wp-frontend-image-replace.com)):**

* Guest access via shareable temporary links (with expiry)
* Activity log for all image replacements (Tools > Image Replace Log)
* Priority support

A premium version with extended features is available at [wp-frontend-image-replace.com](https://wp-frontend-image-replace.com).

== Installation ==

1. Upload the `bm1-frontend-image-replace` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu in WordPress
3. Go to Settings > BM1 Frontend Image Replace
4. Check "Enable Image Replace" and save

**For guest access (Pro only):**

1. On the settings page, click "Generate Access Link"
2. Copy the generated link and share it
3. Anyone with the link can replace images without logging in

== Frequently Asked Questions ==

= Does this delete the original image? =

No. The original image remains in the media library. A new image is uploaded as a separate attachment, and all content references are updated to point to the new one.

= Which image references are updated? =

The plugin updates:
* Image URLs in post/page content (block editor and classic editor)
* Gutenberg block metadata (attachment IDs)
* Image dimensions (width/height attributes)

= Does it work with page builders? =

The plugin works best with the WordPress block editor and classic editor. Page builders that store content in serialized format (like Elementor, Beaver Builder) may not have their references fully updated. The image will still be uploaded to the media library.

= Which images can be replaced? =

Only images that are in the WordPress media library can be replaced. The plugin detects media library images by their CSS classes (e.g., `wp-image-123`) or by resolving their URL to an attachment ID. External images, SVGs, and very small images (under 50px) are excluded.

== Screenshots ==

1. Hover overlay on a replaceable image
2. Settings page with enable toggle and access link management
3. Activity log showing all image replacements (Pro)

== Changelog ==

= 1.2.2 =
* Remove daily replacement limit — image replacement is now unlimited for all users
* Remove unnecessary wp-admin/includes/media.php include
* Unify naming prefix to bm1fir across all global functions, variables, hooks and classes
* Wrap Pro-only code (guest tokens, license management, activity log) in build markers
* Add register_uninstall_hook for free version cleanup

= 1.2.1 =
* Fix Freemius is_premium flag for WordPress.org free build
* Sanitize POST input in batch URL resolver
* Escape Freemius upgrade URL output in admin settings
* Remove screenshot assets from plugin package (SVN-only)

= 1.2.0 =
* Renamed plugin to "BM1 Frontend Image Replace" for WordPress.org directory submission
* Refactored internal prefixes from fir_ to bm1fir_ (Text Domain, classes, options)
* Removed bundled Zammad support form — support now via https://wp-frontend-image-replace.com
* Improved SQL query preparation in uninstall cleanup
* Code-base split: free features hosted on WordPress.org, Pro version on wp-frontend-image-replace.com

= 1.1.2 =
* Add automated Freemius deployment via GitHub Actions

= 1.1.1 =
* Exclude the site logo from image replacement
* Added fir-no-replace CSS class to exclude arbitrary images

= 1.1.0 =
* Added image replacement activity log (Tools > Image Replace Log)
* Fixed image replacement for non-logged-in users when globally enabled
* Added German translations for log page

= 1.0.0 =
* First stable release
* Initial release with Free and Pro plans
* Guest access links (Pro feature)
* Gutenberg and Classic editor support
* Rate limiting and security hardening

== Upgrade Notice ==

= 1.2.2 =
Unlimited image replacements for all users. Naming prefix unified. Pro-only code properly separated.

= 1.2.1 =
Fixes WordPress.org plugin review issues: input sanitization, output escaping, Freemius compliance.

= 1.2.0 =
Plugin renamed to "BM1 Frontend Image Replace" for WordPress.org directory listing.

= 1.1.2 =
Automated Freemius deployment pipeline.

= 1.1.1 =
Site logo is now excluded from replacement. Use fir-no-replace class to exclude other images.

= 1.1.0 =
New activity log to track all image replacements. Bug fix for non-logged-in users.

= 1.0.0 =
First stable release with Free and Pro plans.
