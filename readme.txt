DJ ROTATOR FOR WORDPRESS

######################################
Requirements, Installation, Usage, CSS and Hooks

Requirements:
Wordpress 3.0+
PHP 5.1

Installation:

Step 1. Install the plugin using the plugin manager in WordPress
Step 2. Visit Settings --> DJ Rotator to configure
Step 3. Upload the DJ Image
Step 4. Enter a description, link, days, start and end time (24-hour time WITH LEADING ZEROS e.g.; 06:00)
Step 5. Click update and save

Usage:
To display the DJ Rotator in your theme, use one of the following:
 1. A widget
 2. Template Tag: <?php djwp(); ?>
 3. Shortcode: [djwp]

CSS:
Use the options panel to set your custom CSS ID's and Classes. Then, customize
the look of this plugin using your theme's style.css or custom.css.

Hooks:
You can also take advantage of several hooks built into this plugin to
extended functionality.

djwp_before_header
djwp_after_header
djwp_before_image
djwp_after_image
djwp_before_description
djwp_after_description

######################################
Example Usage:

http://bamacountry.com
http://1049thegump.com

######################################
Change Log

Version 0.0.4 - November 24 2011
- Added check for PHP 5.1
- Added Widget

Version 0.0.3 - November 23 2011
- HTML optimizations

Version 0.0.2 – November 18 2011
- Added hooks
- Added header class
- Added image class
- Added description class
- Added timezone select

Version 0.0.1 – November 16 2011
- Initial build

######################################
To-Do

- Add target="_blank" to DJ Links
- Add different languages