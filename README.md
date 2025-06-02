=== MicroBlog ===
Contributors: Elisabetta Carrara
Tags: microblog, frontend, front-end
Requires CP: 1.0
Requires PHP: 8.1
Stable tag: 2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

=== Microblog ===
Contributors: Elisabetta Carrara
Requires at least: 2.0
Tested up to: 2.4.1
Requires PHP: 7.4
Stable tag: 2.0
License: License: GPL2

Post Microblogs from the front-end and show them on a dedicated page.

== UPDATE NOTICE ==
Please note that this version introduces breaking changes. Plugin architecture and logic have been reworked, and at present backward compatibility is not ensured. I am working on a way to allow importing content from Microblog v1 to Microblog v2 safely.

== Description ==
Post Microblogs from the front-end and show them on a dedicated page.

Use shortcode [microblog_form] to display the form on a page and [microblog_display] to show the Microblog Page.

Microblog uses a Custom Post Type and Taxonomy, you can chenge the CPT to reflect your own needs, if you decide to do so you will have to adjust the form shortcode and the display shortcode logic, and the CPT and Custom Taxonomy as well and check it does not conflict with the rest of the code. As of now Microblog CPT supports Title, Content, Custom Taxonomy (Microblog Category) set to "status" as default, and Thumbnail.

The setting page includes the following options:
- Default Category Selection for the Form
- Post character limit
- Max File Size
- Redirection logic (Home, Custom URL and Same Page) - You can use it to set the redirection to the page where the [microblog_display] shortcode is
- Role(s) selection (you can set the user roles that are allowed to submit the form)
- Number of Microblogs to display per page
- Show Pagination

== Installation ==

1. Go to the install ClassicPress plugins page within your admin dashboard
2. Search for MicroBlog
3. Install & Activate
4. Use the shortcode [microblog_form] and [microblog_display] on any page
5. Go to Settings to configure The aforementioned settings

== Changelog ==

= 2.0 =
* Complete plugin logic and architecture rework. BREAKING CHANGES

= 1.0 =
* Rebrand Narwhal Microblog and correct the code to work with PHP 8.1
