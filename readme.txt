=== Testimonials ===
Contributors: carvalhorafael
Tags: custom-post-type, testimonials, content
Requires at least: 6.4
Tested up to: 6.5
Requires PHP: 8.1
Stable tag: 0.5.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Reusable WordPress content domain for testimonials.

== Description ==

Testimonials registers a portable WordPress content domain for publishing testimonials. It owns the custom post type, taxonomy, student approval metadata, video URL metadata and rewrites while allowing themes to handle presentation.

The plugin registers:

* `depoimento` custom post type.
* `depoimento_categoria` taxonomy.
* `_testimonials_student_name` metadata for the student name.
* `_testimonials_approved_at` metadata for where the student was approved.
* `_testimonials_placement` metadata for the student's placement.
* `_testimonials_course` metadata for the student's course.
* `_testimonials_institution` metadata for the approving institution.
* `_testimonials_approval_year` metadata for the approval year.
* Private editorial metadata for evidence, verification, publication consent and home proof selection.
* `_testimonials_video_url` metadata for YouTube or other video URLs.
* Rewrite rules for `/aprovados/` and `/aprovados/categoria/...`.
* Title-based slugs for newly created testimonials, such as `/aprovados/nome-do-aluno/`.

== Installation ==

1. Upload the plugin ZIP through Plugins > Add New > Upload Plugin.
2. Activate Testimonials.
3. Save Settings > Permalinks if rewrite rules need to be refreshed.

== Frequently Asked Questions ==

= Does this plugin render the public testimonial pages? =

No. The active theme should provide templates and styling. This plugin owns the portable content model.

= Which testimonial metadata does this plugin register? =

It registers public editorial fields for the video URL, student name, approval summary, placement, course, institution and approval year. Evidence references, verification, consent and home proof selection remain private.

== Changelog ==

= 0.5.1 =

* Changes the public testimonial path from `/depoimentos/` to `/aprovados/`.
* Derives newly created testimonial slugs from the post title without migrating existing records.

= 0.5.0 =

* Adds verified student fields and private evidence and consent controls for home proof.
* Prevents incomplete, unverified or unauthorized records from becoming eligible for home proof.

= 0.1.0 =

* Initial public plugin foundation.
