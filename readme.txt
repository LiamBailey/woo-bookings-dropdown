=== WooCommerce Bookings Dropdown ===
Contributors: Webby Scots
Tags: WooCommerce, Bookings
Requires at least: 3.0.1
Tested up to: 4.6
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Swaps the WooCommerce Bookings datepicker calendar for a dropdown list of available dates, with support for resources included.
== Description ==

Swaps the WooCommerce Bookings datepicker calendar for a dropdown list of available dates, with support for resources included.

== Installation ==

This section describes how to install the plugin and get it working.

e.g.

1. Upload the plugin files to the `/wp-content/plugins/woo-bookings-dropdown` directory, or install the plugin through the WordPress plugins screen directly.
1. Activate the plugin through the 'Plugins' screen in WordPress
1. Now your single product pages will show available dates in a dropdown not a datepicker

== Changelog ==

= 1.0.0 =
* Initial release

= 1.0.1 =
* Changes code to match new structure of availability_rules from WooCommerce Bookings
* Bugfix: causing dropdown to be incrorrectly empty

= 1.0.2 =
* Bump version number due to svn rebase error

= 1.0.3 =
* Bugfix inadvertent output bug

= 1.0.4 =
*  Makes previous *fix* from 1.0.1-1.0.3 backwards compatible

= 1.0.5 =
* Bugfix: dropdown now shows all days in each range
* Bugfix: fully booked days no longer show in dropdown
