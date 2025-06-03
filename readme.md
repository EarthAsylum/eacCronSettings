## {eac}CronSettings & {eac}CronRouting
[![EarthAsylum Consulting](https://img.shields.io/badge/EarthAsylum-Consulting-0?&labelColor=6e9882&color=707070)](https://earthasylum.com/)
[![WordPress](https://img.shields.io/badge/WordPress-Plugins-grey?logo=wordpress&labelColor=blue)](https://wordpress.org/plugins/search/EarthAsylum/)
)

<details><summary>Document Header</summary>

Plugin URI:             https://github.com/EarthAsylum/eacCronSettings  
Author:                 [EarthAsylum Consulting](https://www.earthasylum.com)  
Stable tag:             1.7.0  
Last Updated:           03-Jun-2025  
Requires at least:      5.8  
Tested up to:           6.8  
Requires PHP:           8.1  
Contributors:           [earthasylum](https://github.com/earthasylum),[kevinburkholder](https://profiles.wordpress.org/kevinburkholder)  
License:				GPLv3 or later  
License URI:			https://www.gnu.org/licenses/gpl.html  
GitHub URI:             https://github.com/EarthAsylum/eacCronSettings  

</details>

**{eac}CronSettings** - Site wide settings and actions for WP-Cron / Action Scheduler.  

**{eac}CronRouting** - Reroute WP-Cron events to Action Scheduler or Action Scheduler actions to WP-Cron.  

### Description

*WP-Cron* is built in to WordPress and is used to schedule and run jobs or tasks in the background.
By default, WP-Cron is triggered when a page is loaded on your web site making it dependent on web site traffic.

See: https://developer.wordpress.org/plugins/cron/

WordPress checks for any events due to run on every page load. When an event is found, WordPress spawns a request to `wp-cron.php` to run the scheduled events.

If your site has infrequent visitors, scheduled tasks might run late or not at all. On busy sites, every page load triggering WP-Cron can add unnecessary overhead.

*Action Scheduler* is included with WooCommerce and other plugins and is a more advanced, scalable system for scheuling,
running and logging large sets of background tasks. Action Scheduler is dependent on WP-Cron to initiate its
queue runner process so is also dependent on web site traffic.

See: https://actionscheduler.org/

The default WP-Cron behavior can be disabled and replaced with a more reliable time-based trigger, such as a server
cron event or third-party cron service, making it more timely and less dependent on web site traffic.

**{eac}CronSettings** and **{eac}CronRouting** are used to provide some controls and efficiencies over WP-Cron and/or Action Scheduler.


#### {eac}CronSettings
- - -

By default, this plugin...

- Disables the normal WP-Cron behavior, assuming an external WP-Cron trigger (`DISABLE_WP_CRON`).
- Caches WP-Cron events to a custom table and wp_object_cache, removing the 'cron' option from the WP options table (`WP_CRON_CACHE_EVENTS`).
- Sets the minimum cron run interval to 5 minutes (`WP_CRON_MINIMUM_INTERVAL`).
- Adds a 'Monthly' interval based on the days in the current month (`WP_CRON_SCHEDULE_INTERVALS`).
- Increases Action Scheduler run time limit from 30 to 60 seconds (`AS_RUN_TIME_LIMIT`)
- Changes Action Scheduler clean-up retention period from 1 month to 1 week (`AS_CLEANUP_RETENTION_PERIOD`).
- Adds 'failed' actions to Action Scheduler's automatic clean-up.
- Changes Action Scheduler clean-up batch size from 20 to 100 (`AS_CLEANUP_BATCH_SIZE`).

Optionally...

- Disable the Action Scheduler queue runner (`DISABLE_AS_QUEUE_RUNNER`).
- Log scheduling errors (`WP_CRON_LOG_ERRORS`).
- Log scheduling events for debugging (`WP_CRON_DEBUG`).

#### Defined Constants

The constants used in {eac}CronSettings should be reviewed and changed to suit your requirements. Constants may be set as needed, deleted or 'commented-out' if not needed.

Alternatively, these constants may be defined in your `wp-config.php` file.

**DISABLE_WP_CRON**
`true` | `false` (default: true)

The internal wp-cron process may be disabled when triggered by an external request to `/wp-cron.php?doing_wp_cron` like:
 
- server-based crontab  
    `wget -q -O - https://domain.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1`
- [EasyCron](https://www.easycron.com)
- [UptimeRobot](https://www.uptimerobot.com/)
- [cron-job.org](https://cron-job.org/)
- [AWS EventBridge](https://aws.amazon.com/eventbridge/)
- [Google Cloud Scheduler](https://cloud.google.com/scheduler/)
- some other external trigger

**WP_CRON_CACHE_EVENTS**
`true` | `false` | `'revert'` (default: true)

Store WP-Cron events in a custom table rather than 'cron' option in the WP options table. 
* Caches to a custom table and [WP Object Cache](https://developer.wordpress.org/reference/classes/wp_object_cache/).
* Removes `cron` from the WP options table and `$alloptions` array.
* Lessens database reads/writes.
* Significantly reducing overhead.

*This will generate `The cron event list could not be saved` error in `wp-cron.php` when WordPress updates an event. This can be ignored since the event list has been saved to the custom table and cache.*

*Temporarily set this constant to `'revert'` to revert this process and restore the `cron` option from cache.*

**WP_CRON_MINIMUM_INTERVAL** 
`int (seconds)` (default: 5 minutes)

Set a minimum interval time for all wp-cron events.  
Some events (like Action Scheduler queue runner) may be scheduled every minute or less, which may be excessive for your environment. This option forces a minimum time between executions for all events.

**WP_CRON_SCHEDULE_INTERVALS**
`array` (default: monthly)

Add or change WP-Cron schedule intervals used when scheduling recurring events.  
Create new or override existing intervals (schedules).

The array format is:

    'schedule_name'    => [
        'interval'  => int (seconds),
        'display'   => "short description",
    ],

**WP_CRON_LOG_ERRORS**
`true` | `false` (default: undefined)

Log wp-cron scheduling errors.  
Logs to the {eac}Doojigger debugging log OR the system error log.

**WP_CRON_DEBUG**
`true` | `false` (default: undefined)

Debug certain wp-cron scheduling actions.  
Logs to the {eac}Doojigger debugging log OR the system error log.

**DISABLE_AS_QUEUE_RUNNER**
`true` | `false` (default: undefined)

Disable the Action Scheduler queue runner.  
Does not disable or change Action Scheduler functions but prevents actions from running. This can (should) be used after routing actions to WP-Cron events with {eac}CronRouting.

**AS_RUN_TIME_LIMIT**
`int (seconds)` (default: 60)

Set Action Scheduler run time limit (normally 30 seconds).  
Increase this if you have unusually long running actions.

**AS_CLEANUP_RETENTION_PERIOD**
`int (seconds)` (default: 1 week)

Set Action Scheduler retention time.  
Action Scheduler retains completed actions for 1 month, and failed actions indefinitely, which could lead to a bloated database table. This option can reduce the retention period for both types of actions.

**AS_CLEANUP_BATCH_SIZE**
`int (count)` (default: 100)

Set Action Scheduler clean-up batch size.  
Action Scheduler normally purges only 20 records at a time in its clean-up process.

#### {eac}CronRouting
- - -

This plugin facilitates...

- Routing WP-Cron events to Action Scheduler actions.
- Routing Action Scheduler actions to WP-Cron events.

#### Defined Constants

The `WP_CRON_REROUTE_EVENTS` constant used in {eac}CronRouting should be reviewed and changed to suit your requirements. 

Alternatively, this constant may be defined in your `wp-config.php` file.

**WP_CRON_REROUTE_EVENTS**
`WP_TO_AS` ('wp-as') | `AS_TO_WP` ('as-wp') | `false` (default: false)

Route WP-Cron events to Action Scheduler or Action Scheduler to WP-Cron.  
*Currently scheduled events are not changed.*

Action Scheduler is not available until the WordPress `init` action.
* WP-Cron events scheduled or checked before `init` are not routed through Action Scheduler.  

Action Scheduler doesn't provide hooks for several rescheduling/unscheduling functions.
* Only new Action Scheduler actions can be routed to WP-Cron, scheduled recuring events remain in Action Scheduler.  

*Neither option offers a complete solution but most events/actions will end up being rerouted to the desired process. You may need to manually remove events/actions from one process once they have been routed to the other process. Recurring Action Scheduler actions may need to be manually removed in order to force rescheduling to wp-Cron.*


### Installation

**{eac}CronSettings**
1. Edit `eacCronSettings.php` and set the defined constants to fit your configuration needs.
2. Drop the `eacCronSettings.php` file into your `wp-content/mu-plugins` folder.

**{eac}CronRouting**
1. Edit `eacCronRouting.php` and set the defined constant to fit your configuration needs.
2. Drop the `eacCronRouting.php` file into your `wp-content/mu-plugins` folder.


### Other Notes

Use the [WP Crontrol](https://wordpress.org/plugins/wp-crontrol/) plugin

WP Crontrol enables you to take control of the scheduled cron events on your WordPress website or WooCommerce store. From the admin screens you can:

- View all scheduled cron events along with their arguments, schedule, callback functions, and when they are next due.
- Edit, delete, pause, resume, and immediately run cron events.
- Add new cron events.
- Bulk delete cron events.
- Add and remove custom cron schedules.
- Export and download cron event lists as a CSV file.
