=== Testimonials ===
Contributors: carvalhorafael
Tags: custom-post-type, testimonials, content
Requires at least: 6.4
Tested up to: 6.5
Requires PHP: 8.1
Stable tag: 0.6.0
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
* `_testimonials_preparation_time` metadata for the student's preparation time.
* `_testimonials_main_tip` metadata for the student's main approval tip.
* Private editorial metadata for evidence, verification, publication consent, home proof and featured story selection.
* `_testimonials_video_url` metadata for YouTube or other video URLs.
* A reusable CSV importer with validation, duplicate protection, image sideloading and per-row reports.
* Automatic publication for eligible imports and a direct bulk publish action for existing drafts.
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

It registers public editorial fields for the video URL, student name, approval summary, placement, course, institution, approval year, preparation time and main approval tip. Evidence references, verification, consent, home proof and featured story selection remain private.

== Changelog ==

= 0.6.0 =

* Adds a reusable CSV import workflow with validation, preview and per-row reports.
* Imports compatible external images into the media library and applies eligible editorial selections.
* Prevents duplicates through stable external IDs and records private import audit metadata.
* Adds automatic publication for eligible imports and a direct bulk publish action for drafts.

= 0.5.4 =

* Adds an editorial selection for up to three eligible testimonials in the approved students hero.
* Automatically keeps the hero selection limited to the three most recently selected testimonials.

= 0.5.3 =

* Adds a single featured story selection with verified-publication eligibility.
* Replaces the previous featured selection when a new testimonial is chosen.

= 0.5.2 =

* Adds preparation time and a concise main approval tip as public testimonial metadata.

= 0.5.1 =

* Changes the public testimonial path from `/depoimentos/` to `/aprovados/`.
* Derives newly created testimonial slugs from the post title without migrating existing records.

= 0.5.0 =

* Adds verified student fields and private evidence and consent controls for home proof.
* Prevents incomplete, unverified or unauthorized records from becoming eligible for home proof.

= 0.1.0 =

* Initial public plugin foundation.
