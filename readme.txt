=== DJ Rotator For WordPress ===
Contributors: gregrickaby
Tags: dj,music,radio,scheduling,on air,broadcasting
Requires at least: 3.0.0
Tested up to: 3.3.4
Stable tag: 0.0.5

Easily create a DJ Rotator to display which personality is currently on-air.

== Description ==

No custom post-types or meta-boxes here! This is a true, stand-alone plugin which gives you complete point-and-click control to help you easily build a DJ Rotator to display anywhere on your WordPress web site. 

= Features =
* In-line image uploads and real-time resizing
* Schedule by day, hour, and minutes
* Schedule multiple DJ's per day - or just one
* Give the DJ's a description
* Add HTML to description
* Link to the DJ's website or web page
* Action Hooks
* Custom CSS
* Sidebar Widget
* Template Tag
* Shortcode

= Requirements =

* Wordpress 3.0+
* PHP 5.1+

= How-To Video =
http://www.youtube.com/watch?v=gFanaP1-e7U

[FAQ](http://wordpress.org/extend/plugins/dj-rotator-for-wordpress/faq/ "FAQ") |
[Support](http://wordpress.org/tags/dj-rotator-for-wordpress?forum_id=10 "Support Forum") |
[Contribute](https://github.com/gregrickaby/DJ-Rotator-for-WordPress "Contribute at github") |
[Demo](http://bamacountry.com "See a live DJ Rotator in action") |
[Twitter](http://twitter.com/gregrickaby "Follow me on Twitter")

== Installation ==

1. Install & Activate the plugin
2. Visit Settings --> DJ Rotator to configure
3. Upload a DJ Image
4. Enter a description, link, days, start and end time (24-hour time with leading zeros)
5. Click Update and Save

http://www.youtube.com/watch?v=gFanaP1-e7U

== Frequently Asked Questions ==

= How do I display the DJ Rotator in my theme? =
* Sidebar Widget
* Template Tag: `<?php djwp(); ?>`
* Shortcode: [djwp]

= Why is PHP 5.1 (or higher) Required? =
First, PHP 5.1 was released in November 2005. Second, PHP 5.1+ supports advanced date/time functionality which this plug-in requires to schedule DJ's. And finally, if you ask nicely...most hosting companies will happily upgrade your server to the latest version of PHP (and usually for free).

= What is "24-hour time"? =
Military time, or "[24-hour clock](http://en.wikipedia.org/wiki/24-hour_clock "Wikipedia entry about 24-hour clock")".

= What does "use leading zero's mean"? =
DJ Rotator needs a two-digit hour and two-digit minute. All hours from 1AM to 9AM require a zero first...for example: 2AM = 02:00, 6AM = 06:00 or 9AM = 09:00 etc... All hours after 10AM DO NOT require a zero first.

= Can I use minutes? =
Absolutely!

= Can I use HTML in the description field? =
Yes. You can also use an [Action Hook](http://codex.wordpress.org/Plugin_API#Actions "WordPress Codex on Action Hooks") too.

= What available Action Hooks are there? =
* djwp_before_header
* djwp_after_header
* djwp_before_image
* djwp_after_image
* djwp_before_description
* djwp_after_description

== Screenshots ==

1. Options Panel (Right-click --> New Tab for larger image)
2. Actual "in-use" options panel (Right-click --> New Tab for larger image)
3. Default Layout in a Sidebar (Right-click --> New Tab for larger image)

== Changelog ==

= 0.0.5 - 12.09.11 =
* Added Widget title
* Added ability to use HTML in description field
* Added HTML sanitization to Start Time and End Time

= 0.0.4 - 11.28.11 =
* First release to WordPress Plug-in Repository
* Added check for PHP 5.1
* Added Widget

= 0.0.3 - 11.23.11 =
* HTML optimizations

= 0.0.2 - 11.15.11 =
* Added hooks
* Added header class
* Added image class
* Added description class
* Added timezone select

= 0.0.1 - 11.13.11 =
* Initial build

== Upgrade Notice ==

= 0.0.5 =
Adds Widget Title and ability to use HTML in the description field

= 0.0.4 =
First release to the WordPress.org Plug-in Repository 

== Credits ==

Thank you to [@NathanRice](http://twitter.com/#!/nathanrice "Follow Nathan Rice on Twitter") and his [WP-Cycle](http://wordpress.org/extend/plugins/wp-cycle/ "WP-Cycle Plug-in") plug-in which got me started in the right direction! Also thank you to the folks at [Stack Overflow](http://stackoverflow.com) for answering my PHP questions.

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA