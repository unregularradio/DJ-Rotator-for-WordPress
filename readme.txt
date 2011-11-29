=== DJ Rotator For WordPress ===
Contributors: gregrickaby
Tags: dj, music, radio, scheduling
Requires at least: 3.0.0
Tested up to: 3.3
Stable tag: 0.0.4

Easily create a DJ Rotator to display which personality is currently on-air.

== Description ==

Requirements:

* Wordpress 3.0+
* PHP 5.1

Usage:
To display the DJ Rotator in your theme, use one of the following:

* A widget
* Template Tag: `<?php djwp(); ?>`
* Shortcode: [djwp]


Example Usage:

* [http://bamacountry.com](http://bamacountry.com)
* [http://newstalk1079.com](http://bamacountry.com)

== Installation ==

1. Install the plugin using the plugin manager in WordPress
2. Visit Settings --> DJ Rotator to configure
3. Upload the DJ Image
4. Enter a description, link, days, start and end time (24-hour time WITH LEADING ZEROS e.g.; 06:00)
5. Click update and save

== Frequently Asked Questions ==


= What is "24-hour time"? =
Military time, or "24-hour clock". (http://en.wikipedia.org/wiki/24-hour_clock)

= What does "use leading zero's mean"? =
DJ Rotator needs a two-digit hour and two-digit minute. All times from Midnight to 10AM require a zero first.
For example: 6AM = 06:00 or 9AM = 09:00. All times after 10AM don't require a zero e.g.; 15:00. 

= Can I use minutes? =
Absolutely! 3:30 equals 15:30 etc...

= Can I use HTML in the description field? =
No, use a Action Hook instead (http://codex.wordpress.org/Plugin_API#Actions)

== Changelog ==

= 0.0.4 =
* Added check for PHP 5.1
* Added Widget

= 0.0.3 =
* HTML optimizations

= 0.0.2 =
* Added hooks
* Added header class
* Added image class
* Added description class
* Added timezone select

= 0.0.1 =
* Initial build

== Upgrade Notice ==

= 0.0.4 =
First release to the WordPress.org Plug-in Repository 

== Arbitrary section ==

CSS: Use the options panel to set your custom CSS ID's and Classes. Then, customize the look of this plugin using your theme's style.css or custom.css.

Action Hooks: You can also take advantage of several hooks built into this plugin to extended functionality.

* djwp_before_header
* djwp_after_header
* djwp_before_image
* djwp_after_image
* djwp_before_description
* djwp_after_description