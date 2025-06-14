![Microblog Banner](images/microblog.png)
=== MicroBlog ===
Contributors: Elisabetta Carrara
Tags: microblog, frontend, front-end
Requires CP: 1.0
Tested up to CP: 2.4.1
Requires PHP: 8.1
Stable tag: 1.9
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Write short status updates from the front-end of your site and display them on a dedicated page. This plugin can be used to build a social networking site, a microblog site or even a private diary.

== Update Notice ==
Please note that this version introduces breaking changes. You will need to update your shortcodes to [microblog_form] for the submission form and [microblog_display] for the Microblogs liting page.
I am preparing v1 to be able to provide a smooth migration to v2, as of now I am working on migration from v1.0 to v1.9 - after that I will ensure that migration runs smoothly from v1.9 to v2.0.

== Description ==
Post Microblogs from the front-end and show them on a dedicated page.

Use shortcode [microblog_form] to display the form on a page and [microblog_display] to show the Microblog Page.

The Microblog form parses content in a particular format.

Content in round brakets will be parsed as Title.
Content without brakets will be parsed as Microblog Content.
Hashtags will be parsed as Tags

EXAMPLE:

(My awesome Microblog)
This is my very first Microblog on this site!
#firstpost

You will also be able to select a category from the dropdown before submitting your Microblog.

== Installation ==

1. Go to the install ClassicPress plugins page within your admin dashboard
2. Search for MicroBlog
3. Install & Activate
4. Use the shortcode [microblog_form] and [microblog_display] on the pages where you want the Form and Display to be

== Donate ==
If you find my software useful please consider [donating to support its maintenance](https://donate.stripe.com/3cI14n0hv1PCcx7ccS9ws01).

== Changelog ==

= 2.0 =
* Complete plugin logic and architecture rework. BREAKING CHANGES

= 1.9 =
* Code refactor and improved compatibility with v2 to allow migration. BREAKING CHANGES

= 1.0 =
* Rebrand Narwhal Microblog and correct the code to work with PHP 8.1
