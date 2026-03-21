=== Frontend Image Replace ===
Contributors: phillipbaumgaertner
Tags: images, replace, frontend, media, development
Requires at least: 5.4
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Replace images directly from the frontend. Upload a new image, and it replaces the old one in your content.

== Description ==

**Frontend Image Replace** lets you swap out images on your WordPress site without ever touching the admin panel. Simply hover over any image on the frontend, click, and upload a replacement.

Perfect for:

* Replacing demo or placeholder images during development
* Letting clients review and update images without backend access
* Quick image swaps during content reviews

**How it works:**

1. Enable the plugin in Settings > Frontend Image Replace
2. Visit any page on your site
3. Hover over an image — a replace overlay appears
4. Click and select a new image from your computer
5. The new image is uploaded to the media library and all references in your content are updated automatically

**Free features:**

* 3 image replacements per day
* Works with any theme — no template modifications needed
* Uploads new images to the WordPress media library (the original stays untouched)
* Updates all references in post content
* Supports Gutenberg block editor, Classic editor, and LiveCanvas
* Lightweight — no jQuery dependency, minimal footprint
* Translation-ready

**Pro features:**

* Unlimited image replacements
* Guest access via shareable temporary links (with expiry)
* Priority support

== Installation ==

1. Upload the `frontend-image-replace` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu in WordPress
3. Go to Settings > Frontend Image Replace
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

= What is the daily limit? =

Free users can replace up to 3 images per day. Upgrade to Pro for unlimited replacements.

== Screenshots ==

1. Hover overlay on a replaceable image
2. Settings page with enable toggle and access link management

== Changelog ==

= 1.0.0 =
* First stable release
* Freemium model: 3 replacements/day (Free), unlimited (Pro)
* Guest access links (Pro feature)
* Gutenberg and Classic editor support
* Rate limiting and security hardening

== Upgrade Notice ==

= 1.0.0 =
First stable release with Free and Pro plans.
