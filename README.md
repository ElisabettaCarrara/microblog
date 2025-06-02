=== MicroBlog ===
Contributors: Elisabetta Carrara
Tags: microblog, frontend, front-end
Requires CP: 2.0
Tested up to CP: 2.4.1
Requires PHP: 8.1
Stable tag: 1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

MicroBlog adds a minimal front-end posting form on any page with the [microblog] shortcode.

== Description ==

Microblog adds a minimal front-end posting form on any page with the shortcode, [microblog]. It's meant for short updates or microblogging.

The form submits without refreshing the page and then redirects to the post that was created. If you don't quite like this behavior, check out the FAQs.

Unlike other front-end forms that have you type out the title, post content, and tags separately, this plugin takes an original approach by using a special format. Think of it like using markdown in a way (if you're familiar with that). Once you commit the format to memory it makes posting updates even faster.


What you type into the textarea is:

(Title in parentheses)
Content on a new line with any word #hashtagged.

Example:

(This is an amazing title)
But, the #content is even better!

The above would create a post titled, "This is an amazing title," with "content" as a tag.

== Installation ==

1. Go to the install ClassicPress plugins page within your admin dashboard
2. Search for MicroBlog
3. Install & Activate
4. Use the shortcode [microblog] on any page
5. Go to Settings, and then Writing. Confirm which post type and category combination to use, and save the settings.

== Frequently Asked Questions ==

= Can I change the category? =

Yes. The form includes a select box to choose any of your categories.

= Can I change the post type? =

Yes. Go to Settings, and then Writing. Don't forget to also change to a matching category type/name within the same settings area.

= How can I change what happens after the post is saved? =

By default, you will be redirected to the post. But, if you don't like that and have some coding knowledge, there is a manual option for now. Open microblog.js and replace line 76 with an alert message or nothing. Then no page refreshing or redirects will happen.

== Changelog ==

= 1.0 =
* Rebrand Narwhal Microblog and correct the code to work with PHP 8.1
