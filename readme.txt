=== Testimonials ===
Contributors: carvalhorafael
Tags: custom-post-type, testimonials, content
Requires at least: 6.4
Tested up to: 6.5
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Reusable WordPress content domain for testimonials.

== Description ==

Testimonials registers a portable WordPress content domain for publishing testimonials. It owns the custom post type, taxonomy and rewrites while allowing themes to handle presentation.

The plugin registers:

* `depoimento` custom post type.
* `depoimento_categoria` taxonomy.
* Rewrite rules for `/depoimentos/` and `/depoimentos/categoria/...`.

== Installation ==

1. Upload the plugin ZIP through Plugins > Add New > Upload Plugin.
2. Activate Testimonials.
3. Save Settings > Permalinks if rewrite rules need to be refreshed.

== Frequently Asked Questions ==

= Does this plugin render the public testimonial pages? =

No. The active theme should provide templates and styling. This plugin owns the portable content model.

= Does this plugin register testimonial metadata? =

No. Metadata is intentionally out of scope for now. A separate plugin or future version can add fields without re-registering the content domain.

== Changelog ==

= 0.1.0 =

* Initial public plugin foundation.
