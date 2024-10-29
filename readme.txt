=== bbPress Move Topics ===
Contributors: casiepa
Tags: bbpress,bbp move topics, post to topic, comments to replies
Requires at least: 3.9
Tested up to: 4.9.4
Stable tag: 1.1.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Move topics from one forum to another, convert post/comments into topic/replies in the same site. For the admin backend.

== Description ==

Discussions start as comments on a post but you want them to continue on your forum? bbPress topics are not always created in the correct forum by users?
Then use this tool to deal with it!

= Move topics to another forum =

* Move topic to another forum
* Hide topics so you do not need to deal with them again

= Convert (custom) post or page comments to topic with replies =

* Delete comments after conversion to replies
* Add a new comment with the link to the forum
* Convert unapproved comments into unapproved replies
* Deal with anonymous user comments
* Cut topic or replies after a certain number of words

= Using =

* Start from your dashboard on "Forum > Move Topics"

PS. Check out other plugins like [bbP Toolkit](https://wordpress.org/plugins/bbp-toolkit/) and [more from me](https://profiles.wordpress.org/casiepa#content-plugins) to complete your bbPress experience

== Installation ==
Option 1:

1. On your dashboard, go to Plugins > Add new
1. search for *bbP Move Topics*
1. install and activate the plugin

Option 2:

1. Unzip the contents to the "/wp-content/plugins/" directory.
1. Activate the plugin through the "Plugins" menu in WordPress.

Now use the plugin from your dashboard on "Forum > Move Topics"

== Screenshots ==

1. Move topics to another forum
2. Convert post to topic

== Frequently Asked Questions ==
= How do I use this plugin =
Start from your dashboard on "Forum > Move Topics"

= Can I make feature requests =
Of course ! Use the support tab.

= I love your product =
What are you waiting for to add a review ? Or a [small donation](http://casier.eu/wp-dev/) for my coffees ?

== Changelog ==
= 1.1.6 =
* Fix: Avoid possible code injection and cross-site request forgery (CSRF)
* Fix: Only admin and moderator should access
* Improved: Remove source forum from the destination list

= 1.1.4 =
* Fix: Check for bbPress presence before activating the plugin

= 1.1.3 =
* Fix: Move dropdown AFTER the filter button on the posts screen
* Fix: Inverting the logic of 'Do not close for comment' to 'Close for comment'
* New: Possibility to also convert pages and custom posts
* New: Cut topic or replies after a certain number of words

= 1.1.2 =
* Fix: Call to undefined function is_plugin_active()
* New: convert post with comments to topic with replies
* Improved: Rename 'zapping' to 'hiding'

= 1.1.1 =
* New: Create topic/replies from a post with comments
* New: Translation ready

= 1.1.0 =
* Public Release

= 1.0.1 =
* Fixed issues with number of topics and replies after the move

= 1.0.0 =
* Initial release.