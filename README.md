=== MicroBlog ===
Contributors: Elisabetta Carrara
Tags: microblog, frontend, front-end
Requires CP: 2.0
Requires PHP: 8.1
Stable tag: 1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

MicroBlog adds a minimal front-end posting form on any page with the [microblog_form] shortcode.

== Description ==

Microblog adds a minimal front-end posting form on any page with the shortcode, [microblog_form]. It's meant for short updates or microblogging.

To display the Microblog loop you can use the [microblog_loop] shortcode.

The form submits redirecting the page. Site owners can define in the settings where to redirect users after submission (homepage or custom URL).

This plugin adds a Microblog CPT to your site, and a Status taxonomy. Site owners can define a list of taxonomies user can select from when publishing their Microblogs.

The form allows for WYSIWYG editing of the content, and file uploads also.

== Installation ==

1. Go to the install ClassicPress Plugins page within your admin dashboard
2. Search for MicroBlog
3. Install & Activate
4. Use the shortcode [microblog_form] and [microblog_loop] on any page to display the form and the loop
5. Go to Settings, and then Microblog to set redirect options and role permissions.

== Frequently Asked Questions ==

= Can I change the category? =

Yes. The form includes a select box to choose any of your categories.

= How can I change what happens after the post is saved? =

By default, you will be redirected to the homepage. But, if you don't like that you can set to redirect to a custom URL in the settings page

== Changelog ==

= 2.0 =
* total refactoring of plugin to add a microblog CPT, with custom fields and taxonomy, changed the form to allow WYSIWYG editing and added file upload feature. Also changed setting page to contain options for redirection and user role(s) permissions.

= 1.0 =
* Rebrand Narwhal Microblog and correct the code to work with PHP 8.1
