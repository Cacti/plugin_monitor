# monitor

This plugin allows you to view at a glance all your critical Cacti hosts, and
will alert you audibly and via Email when a Device or Devices go down.

Some audio clips have been added to this plugin courtesy of SoundBible.com and
are licensed under Creative Commons and other Free to use Licenses.  If you
create an MP3 file called 'First Order Chorus.mp3' and place it in the sound
folder, you may get a surprise if you choose to use it.

The Monitor plugin has been around for well over a decade.  This very old plugin
has recently gone through major recent enhancements through it's lifetime.  The
version of Monitor included with Cacti 1.0 is almost unrecognizable from earlier
versions of the plugin.  It's is essentially a finished project, though if there
are any feature requests, we won't push them away.

## Features

* Data Center Dashboard

* Audible and Visual Alerting

* Respects Cacti's user permissions

* Monitoring can be enabled or disabled at the Device level

* Supports Monitoring Devices by Criticality

## Installation

To install the Monitor plugin, simply copy the plugin_monitor directory to
Cacti's plugins directory and rename it to simply 'monitor'. Once you have done
this, goto Cacti's Plugin Management page, Install and Enable the webseer. Once
this is complete, you can grant users permission to view the Monitor tab.

It would be advisable to view Monitors email notification settings under
Settings in Cacti.  Monitor is configured from the Monitor tab in Settings, and
Devices can have their Criticality in the Cacti Device Management page.  Monitor
includes a Device filter to show Devices of differing criticalities.

## Bugs and Feature Enhancements

Bug and feature enhancements for the webseer plugin are handled in GitHub. If
you find a first search the Cacti forums for a solution before creating an issue
in GitHub.

