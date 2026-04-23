# BM1 Frontend Image Replace

WordPress plugin to replace images directly from the frontend. Upload a new image, and it replaces the old one in your content. The original image stays in the media library.

## Features

### Free
- Hover overlay on any media library image in the frontend
- Uploads new images to the WordPress media library
- Updates image references in the specific post (URL, attachment ID, dimensions)
- Supports Gutenberg, Classic Editor, and LiveCanvas
- Works with any theme
- Translation-ready (German included)

### Pro
- Guest access via temporary shareable links (with expiry)
- Activity log for all image replacements (Tools > Image Replace Log)
- Priority support

Get the Pro version at [wp-frontend-image-replace.com](https://wp-frontend-image-replace.com).

## Installation

1. Upload the `bm1-frontend-image-replace` folder to `/wp-content/plugins/`
2. Activate the plugin
3. Go to **Settings > BM1 Frontend Image Replace**

## Usage

- **Enable globally:** Check "Enable Image Replace" in settings — overlay appears for all visitors
- **Guest access (Pro):** Generate a temporary link in settings — works independently of the global toggle

## Excluding Images

The site logo (WordPress Custom Logo) is automatically excluded from replacement.

To exclude other images, add the CSS class `fir-no-replace` to the image or any parent element:

```html
<img src="banner.jpg" class="fir-no-replace">

<div class="fir-no-replace">
  <img src="hero.jpg">
  <img src="badge.jpg">
</div>
```

## Requirements

- WordPress 5.4+
- PHP 7.4+

## License

GPL v2 or later
