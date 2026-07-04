# Testimonials

Testimonials is a WordPress plugin that owns a reusable testimonials content domain. It registers the custom post type, taxonomy, video URL metadata and rewrites needed to publish testimonials while leaving visual presentation to the active theme.

## What It Provides

- Custom post type: `depoimento`
- Custom taxonomy: `depoimento_categoria`
- REST-enabled metadata:
  - `_testimonials_video_url`
- A WordPress admin meta box for the testimonial video URL
- A dynamic Gutenberg block: `testimonials/testimonials-display`
- Rewrite rules for `/depoimentos/` and `/depoimentos/categoria/...`
- GitHub Releases update integration through the plugin `Update URI`

## What It Does Not Provide

This plugin does not own a site's visual identity. Themes and integration plugins should consume the content domain and decide how to style or extend it.

For example:

- A theme may provide `single-depoimento.php` and `taxonomy-depoimento_categoria.php`.
- A theme may style the neutral block classes emitted by `testimonials/testimonials-display`.
- A companion plugin may add metadata or import logic without re-registering the content domain.

## Public Contract

The plugin keeps these identifiers stable so existing WordPress content remains portable:

```php
testimonials_post_type(); // depoimento
testimonials_taxonomy(); // depoimento_categoria
testimonials_video_url_meta_key(); // _testimonials_video_url
```

The reusable display block is registered by the plugin and rendered on the server:

```text
testimonials/testimonials-display
```

It emits neutral classes such as `.testimonials-block`, `.testimonials-block--cards`, `.testimonials-block--grid`, `.testimonials-block--slider`, `.testimonials-block--video-slider`, `.testimonials-card`, `.testimonials-card__quote`, `.testimonials-card__media`, `.testimonials-card__video`, `.testimonials-card__person` and `.testimonials-card__category`.

## Installation

1. Download the latest `testimonials-X.Y.Z.zip` release asset.
2. In WordPress admin, go to Plugins > Add New > Upload Plugin.
3. Upload and activate the ZIP.
4. Flush permalinks if needed by visiting Settings > Permalinks and saving.

## Development

Requirements:

- PHP 8.1+
- Composer
- MySQL for WordPress integration tests
- Subversion for installing the WordPress PHPUnit test suite

Install dependencies:

```bash
composer install
```

Run unit tests:

```bash
composer test:unit
```

Install the WordPress test suite and run integration tests:

```bash
composer install:wp-tests
composer test:wordpress
```

Run the full test suite:

```bash
composer test
```

Build a public ZIP package:

```bash
composer package
```

## Release Flow

Releases are prepared from `develop` and published when the prepared version reaches `main`.

1. Run the `Prepare Release` workflow with `patch`, `minor`, `major` or an explicit version.
2. Merge the generated `release/vX.Y.Z` PR into `develop`.
3. Merge `develop` into `main`.
4. The `Release` workflow validates the plugin, creates tag `vX.Y.Z`, publishes a GitHub Release and uploads `testimonials-X.Y.Z.zip`.

## License

GPL-2.0-or-later.
