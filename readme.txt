=== WP FullCalendar ===
Contributors: netweblogic, mikelynn
Tags: calendar, calendars, jQuery calendar, ajax calendar, event calendars, events calendar
Requires at least: 3.3
Tested up to: 3.4
Stable tag: 0.6.1

Uses the jQuery FullCalendar plugin to create a stunning calendar view of events, posts and other custom post types

== Description ==

[FullCalendar](http://arshaw.com/fullcalendar/ "jQuery Calendar Plugin") is a free open source jQuery plugin by Adam Arshaw which generates a stunning calendar populated with your events, posts or any other custom post type.

This plugin combines the power of FullCalendar and WordPress to present your posts in a calendar format, which can be filtered by custom taxonomies such as categories and tags.

[Demo - See it in action](http://demo.wp-events-plugin.com/calendar/ "Events Manager Calendar Plugin")

= Features =

* AJAX powered
* Month/Week/Day views
* Style your calendar with dozens of themes or create your own with the jQuery ThemeRoller
* Filter by taxonomy, such as category, tag etc.
* Supports custom post types and custom taxonomies
* Popout post summaries and thumbnails when you hover over your calendar items using jQuery qTips
* Integrates seamlessly with [Events Manager](http://wordpress.org/extend/plugins/events-manager/)
* Various hooks and filters for added flexibility for developers

= Credits =

* Big thank you to Michael Lynn who generously gave us this plugin namespace after deciding not to go through with his implementation. One less confusing name on the plugin repo!
* This plugin was originally created for the Events Manager plugin Pro add-on, which has been moved over here so it can be used by the community for other post types.

= Roadmap =

Here's a rough roadmap of where we're heading, and will be ammended as time permits

* Add formats for custom post types (currently only possible with Events Manager)
* Colors for other custom post types (currently only possible with Events Manager)
* Multiple post types on one calendar
* More FullCalendar options integrated into the settings page

== Installation ==

Install this plugin like a normal WordPress plugin. Once activated, you'll see a new panel in the Settings section for editing the options for calendar display.

== Changelog ==
= 0.6.1 =
* jQuery/js - tiggered wpfc_fullcalendar_args event to document, passes on fullcalendar options object
* fixed limit and more text options being ignored (requires resave of settings)

= 0.6 =
* added taxonomy shortcode attributes
* added localization
* year/month shortcode arguments load the initial month shown on calendar 

= 0.1 - 0.5 =
* first version, ported from Events Manager Fullcalendar 1.4