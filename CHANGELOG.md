## Changelog

-- develop ---

* issue#139: WEBUI WARNING: Attempt to use GET method for POST operations
* issue#142: Monitor generates PHP Backtrace Errors

--- 2.5 ---

* issue#140: Spelling Error in Reboot Email Subject
* issue: Unset variable warnings causing emails to be sent to Cacti admin

--- 2.4 ---

* issue#100: Long names in devices in monitor.php in (title view)
* issue#102: Doubled footer in monitor page (tile view)
* issue#104: Broken responsive design
* issue#105: Host description contains multibyte character set may cause the garbled text
* issue#109: Reboot history being removed incorrectly for removed devices
* issue#114: Monitor legend can not expand to a single line
* issue#123: When sending emails, monitor reports config variable not found
* issue#124: SQL Errors when removing hosts that no longer exist
* issue#125: Notification history is not being updated
* issue: Add more checks for removed Device conditions
* issue: Dont attempt to send an device down email if there is none set for a device
* issue: When remote pollers are enabled, monitor can remove all your devices
* feature: PHP 7.2 compatibility
* feature#134: Added option to Loop the alarm sound

--- 2.3.5 ---

* issue#48: Reboot notifications did not properly use Monitor sender settings
* issue#66: Correct missing variable warnings when sending emails
* issue#71: Only one tile being displayed per row being displayed #71
* issue#72: Internet Explorer fails to apply filters
* issue#76: Monitor attempts to get status for non-server elements
* issue#77: Device status information in popup window may contains quotes
* issue#78: Arranging hosts by Device Template is not working
* issue#89: When there are no group level items, SQL filters can become badly formatted
* issue#94: When grouping by tree, Devices added to the root of a tree appear on every tree
* issue#95: When grouping by site, devices with no site never appear
* feature: Support for Cacti 1.2
* feature: Align Status with Thold Status
* feature: Implement Cacti Responsive Filter for Mobile
* feature: Allow Alert sounds on Threshold Breach or Triggers
* feature: Support text based email generation

--- 2.3.4 ---

* issue#61: Fixed issue where monitor_list was not being used
* issue#64: Fixed issue with overlapping rows in tree view
* feature: Added option to disable inclusion of Threshold alert lists

--- 2.3.3 ---

* issue#57: Fixed issue with mute not working
* issue#60: Fixed issue with themes
* issue: Fixed issue with audio constantly playing
* issue: Fixed issue with audio attempting to play files that do not exist

--- 2.3.2 ---

* feature: Allow "from" email address/name to be set
* feature: Requires a minimum of Cacti 1.1.36

--- 2.3.1 ---

* issue: fixed issue where criticalities was not being correctly referenced

--- 2.3 ---

* feature: improved header/filter layout for more consistent view
* feature: Added List View mode * issue: fixed issue where hosts where not always evaluated properly in render_perms()

--- 2.2 ---

* issue#23: Notify admins on a system reboot detection
* issue#31: Tree rendering not functioning as expected
* issue#43: Modified plugin_monitor reporting and updating behavior when cacti host table indicates device uptime values of 0
* issue: Properly handle text domain for internationalization
* issue: List of plugin_monitors, sound files, in setup, is now sorted
* feature: Update Spanish Translation

--- 2.1 ---

* issue#26: Monitor is not showing triggered colors
* issue#27: Time in State not reported correctly in some instances
* issue: Correct several smaller visual issues with monitor
* feature: Convert Monitor to use CSS for skin developers

--- 2.0 ---

* feature: Support for Cacti 1.0
* feature: Complete redesign using font awesome
* feature: Allow specification of device criticality
* feature: Allow specification of warning and alert ping round trip latency number
* feature: All GUI interactions using ajax
* feature: Specification of settings using filter
* feature: Save user based settings
* feature: Using new Cacti permissions system
* feature: Generalized code cleanup
* feature: Integrate better with Thold and Syslog

--- 1.3 ---

* compat: Fix general header

--- 1.2 ---

* issue#0001654: a little update for monitor plugin
* issue: Correct some undefined offset errors

--- 1.1 ---

* compat: Allow proper navigation text generation
* issue: User would see console as a pick even if they were guest
* issue: Mute button does not work properly
* issue: Text fields in MySQL can not include a default

--- 1.0 ---

* feature: Add Grouping by Host Template
* feature: Adding 0.8.7f features

--- 0.9 ---

* compat: Monitor is now only PA 2.0 compatible
* issue: Fix for mass enabling / disabling monitoring of hosts

--- 0.8.2 ---

* feature: Change from JS Status popup to pure CSS
* feature: Remove "Details" view

--- 0.8.1 ---

* feature: Fix compatibility issue between monitor and thold v0.3.6

--- 0.8 ---

* issue: Add fix for not showing disabled thresholds
* issue: Lots of code cleanup
* issue: Fix Cacti 0.8.7 compatibility
* feature: Add a muted icon to show what hosts are currently muted
* feature: Only show hosts that have had at least 2 pollings.  This stops it from alerting on new hosts that havent been properly polled yet.
* feature: All the selecting of "None" as a sound to not have it play an alert
* feature: Move from using cookies to using session variables
* feature: Allow the display of a Host Down Message
* feature: Add a patch by fri that allows grouping by tree / header and does user auth checking
* feature: Use new "api_user_realm_auth" from Plugin Architecture
* feature: Display host down time under the hostname

--- 0.7 ---

* issue#0000044 - Modify device image to link to devices graphs
* issue#0000052 - If the Threshold plugin is running, change the host color to Orange if a threshold is breached
* issue: Fixes to the fast poller
* feature: Add option to select an different alarm sound from the available wav and mp3 files in the sounds directory
* feature: Update tab image to better resemble the original cacti images
* feature: Add option to add an icon legend to the Monitor display
* feature: Moved sounds to their own folder

--- 0.6 ---

* issue#0000013 - Fix issues with database names with uncommon characters by enclosing in back-ticks
* feature: Allow guest access to the Monitor Tab

--- 0.5 ---

* issue: Fixed an issue with the mute button action url (thanks Tut!)
* issue: Fixed an issue with the monitor page and includes
* issue: Fixed an issue with the Fast Poller paths

--- 0.4 ---

* feature: Added Javscript Mouseover Tooltips.  This replaces the title attribute which I used before (Newlines didnt work in Firefox)

--- 0.3 ---

* feature: Added Settings for Refresh Rate and Width of Hosts See Settings >> Misc
* feature: Added Faster Poller so that you can know immediately if something is down

--- 0.2 ---

* issue: Fix for navigational line

--- 0.1 ---

* Initial release
