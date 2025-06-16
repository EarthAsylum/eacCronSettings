<?php
/**
 * {eac}CronSettings - Site wide settings and actions for WP-Cron / Action Scheduler.
 *
 * @category    WordPress Plugin
 * @package     {eac}CronSettings
 * @author      Kevin Burkholder <KBurkholder@EarthAsylum.com>
 * @copyright   Copyright (c) 2025 EarthAsylum Consulting <www.earthasylum.com>
 *
 * @wordpress-plugin
 * Plugin Name:         {eac}CronSettings
 * Description:         {eac}CronSettings - Site wide settings and actions for WP-Cron / Action Scheduler
 * Version:             1.7.1
 * Last Updated:        05-Jun-2025
 * Requires at least:   5.8
 * Tested up to:        6.8
 * Requires PHP:        7.4
 * Author:              EarthAsylum Consulting
 * Author URI:          http://www.earthasylum.com
 * License:             GPLv3 or later
 * License URI:         https://www.gnu.org/licenses/gpl.html
 * Tags:                cron, wp-cron, action-scheduler
 * Github URI:          https://github.com/EarthAsylum/eacCronSettings
 */


/* *****
 *
    This is a self-contained piece of code - drop in to mu-plugins folder to invoke.

    --     REVIEW AND ADJUST BEFORE IMPLEMENTING      --
    -- Adjust the defined constants below as required --

    WP-Cron is built in to WordPress and is used to schedule and run jobs or tasks in the background.
    By default, WP-Cron is triggered when a page is loaded on your web site making it dependent on web site traffic.

    Action Scheduler is included with WooCommerce and other plugins and is a more advanced, scalable system for scheuling,
    running and logging large sets of background tasks. Action Scheduler is dependent on WP-Cron to initiate its
    queue runner process so is also dependent on web site traffic.

    The default WP-Cron behavior can be disabled and replaced with a more reliable time-based trigger, such as a server
    cron event or third-party cron service, making it more timely and less dependent on web site traffic.

    By default, this plugin...

    - Disables the normal WP-Cron behavior, assuming an external WP-Cron trigger (`DISABLE_WP_CRON`).
    - Caches WP-Cron events in memory and to a custom table, removing the 'cron' option from the WP options table (`WP_CRON_CACHE_EVENTS`).
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
 *
 ***** */

namespace EarthAsylumConsulting\CronSettings;

const VERSION       = '1.7.2';
const CACHE_ID      = 'eac_key_value';
const CACHE_KEY     = 'wp_cron_events';
$days_this_month    = (int)wp_date('t');


/* *****
 *
 * Define constants to control actions.
 *
 * set/change/remove these constants as needed
 * may instead be defined in wp-congif.php
 *
 ***** */


/*
 * Define constants controlling WP-Cron
 */
define_constants([
    /*
     * Internal wp-cron may be disabled when triggered by external request to /wp-cron.php?doing_wp_cron
     */

    'DISABLE_WP_CRON'                   => true,

    /*
     * Store WP-Cron events in a custom table rather than 'cron' option.
     * Caches to custom table and cache, removes 'cron' from options table and $alloptions array.
     * This will generate 'The cron event list could not be saved' error that can be ignored.
     * - set to 'revert' to revert this process and restore 'cron' option from cache -
     */

    'WP_CRON_CACHE_EVENTS'              => true,

    /*
     * Set minimum interval time for all wp-cron jobs.
     * Some wp-cron jobs (like AS queue runner) may be scheduled every minute,
     * this forces a minimum time between executions.
     */

    'WP_CRON_MINIMUM_INTERVAL'          => 5 * MINUTE_IN_SECONDS,

    /*
     * Add or change wp-cron schedule intervals.
     * Create new or override existing intervals (schedules)
     */

    'WP_CRON_SCHEDULE_INTERVALS'        => array(
            // add 'monthly' based on days this month
            'monthly'       => [
                'interval'  => $days_this_month * DAY_IN_SECONDS,
                'display'   => "Monthly ({$days_this_month} days)",
            ],
    ),

    /*
     * Log wp-cron scheduling errors
     */

    //'WP_CRON_LOG_ERRORS'              => true,

    /*
     * Debug certain wp-cron scheduling actions
     */

    //'WP_CRON_DEBUG'                   => true,
]);


/*
 * Define constants controlling Action Scheduler
 */
define_constants([
    /*
     * Disable the Action Scheduler queue runner.
     * Does not disable or change Action Scheduler functions but stops events from running via WP-Cron.
     */

    //'DISABLE_AS_QUEUE_RUNNER'         => true,

    /*
     * Set Action Scheduler run time limit (default = 30 seconds).
     */

    'AS_RUN_TIME_LIMIT'                 => MINUTE_IN_SECONDS,

    /*
     * Set Action Scheduler retention time (default = 1 month).
     * Action Scheduler retains completed & failed events for 1 month which could lead to a bloated database table.
     */

    'AS_CLEANUP_RETENTION_PERIOD'       => WEEK_IN_SECONDS,

    /*
     * Set Action Scheduler clean-up batch size (default = 20).
     * Action Scheduler purges only 20 records at a time in its clean-up process.
     */

    'AS_CLEANUP_BATCH_SIZE'             => 100,
]);


/* *****
 *
 * No further changes required
 *
 ***** */


/* *****
 *
 * Actions & filters triggered by above constants
 *
 ***** */


/*
 * error logging (always log internal errors)
 */
add_action("wpcron_log_error", function($message,$source)
{
    if (has_action("eacDoojigger_log_error")) {
        do_action("eacDoojigger_log_error",$message,$source);
    }
    error_log($source.': '.var_export($message,true));
},10,2);

/*
 * Debugging - if eacDoojigger is not installed, use error_log
 */
if (defined('WP_CRON_DEBUG') && WP_CRON_DEBUG)
{
    add_action("wpcron_log_debug", function($data,$source)
    {
        if (has_action("eacDoojigger_log_debug")) {
            do_action("eacDoojigger_log_debug",$data,$source);
        } else {
            error_log($source.': '.var_export($data,true));
        }
    },10,2);
}

/*
 * Store WP-Cron events in a custom table rather than 'cron' option.
 */
if (defined('WP_CRON_CACHE_EVENTS'))
{
    if (WP_CRON_CACHE_EVENTS === true) {
        store_wp_to_db();
    } else
    if (WP_CRON_CACHE_EVENTS === 'revert') {
        revert_wp_to_db();
    }
}


/*
 * set minimum interval time when scheduling
 */
if (defined('WP_CRON_MINIMUM_INTERVAL') && is_int(WP_CRON_MINIMUM_INTERVAL))
{
    add_filter( 'schedule_event', function($event)
    {
        if ($event->schedule && $event->interval < WP_CRON_MINIMUM_INTERVAL) {
            $event->interval = WP_CRON_MINIMUM_INTERVAL;
            $event->timestamp = time() + $event->interval;
        }
        return $event;
    },1000);
}


/*
 * add or change schedule intervals
 */
if (defined('WP_CRON_SCHEDULE_INTERVALS') && is_array(WP_CRON_SCHEDULE_INTERVALS))
{
    add_filter( 'cron_schedules', function($cron_intervals)
    {
        return array_merge($cron_intervals,WP_CRON_SCHEDULE_INTERVALS);
    },1000);
}


/*
 * When (if) Action Scheduler loads
 */
add_action('action_scheduler_init', function()
{
    /*
     * disable Action Scheduler queue runner
     */
    if (defined('DISABLE_AS_QUEUE_RUNNER') && DISABLE_AS_QUEUE_RUNNER)
    {
        remove_action( 'action_scheduler_run_queue', array( ActionScheduler::runner(), 'run' ) );
    }

    /*
     * set the maximum run time for Action Scheduler (default 30 seconds)
     */
    if (defined('AS_RUN_TIME_LIMIT') && is_int(AS_RUN_TIME_LIMIT))
    {
        add_filter( 'action_scheduler_queue_runner_time_limit', fn() => AS_RUN_TIME_LIMIT );
    }

    /*
     * Set Action Scheduler retention period
     * Add 'failed' actions to Action Scheduler automatic cleanup
     */
    if (defined('AS_CLEANUP_RETENTION_PERIOD') && is_int(AS_CLEANUP_RETENTION_PERIOD))
    {
        add_filter( 'action_scheduler_retention_period', fn() => AS_CLEANUP_RETENTION_PERIOD );
        add_filter( 'action_scheduler_default_cleaner_statuses', fn($s) => $s[] = \ActionScheduler_Store::STATUS_FAILED );
    }

    /*
     * set number of actions to purge in Action Scheduler automatic cleanup (default 20)
     */
    if (defined('AS_CLEANUP_BATCH_SIZE') && is_int(AS_CLEANUP_BATCH_SIZE))
    {
        add_filter( 'action_scheduler_cleanup_batch_size', fn() => AS_CLEANUP_BATCH_SIZE );
    }
}); // action_scheduler_init


/*
 * log scheduling errors
 */
if (defined('WP_CRON_LOG_ERRORS') && WP_CRON_LOG_ERRORS)
{
    /*
     * catch & log rescheduling errors
     */
    add_action( 'cron_reschedule_event_error', function($error, $hook, $event)
    {
        do_action( 'wpcron_log_error',get_defined_vars(),current_action() );
    },10,3);

    /*
     * catch & log unscheduling errors
     */
    add_action( 'cron_unschedule_event_error', function($error, $hook, $event)
    {
        do_action( 'wpcron_log_error',get_defined_vars(),current_action() );
    },10,3);

    /*
     * catch & log Action Scheduler errors
     */
    add_action( 'action_scheduler_failed_to_schedule_next_instance', function($action_id, $e, $action)
    {
        do_action( 'wpcron_log_error',[$action->get_hook(),$e->getMessage()],current_action() );
    },10,3);

    add_action( 'action_scheduler_failed_execution', function($action_id, $e, $context)
    {
        do_action( 'wpcron_log_error',$e->getMessage(),current_action() );
    },10,3);

    add_action( 'action_scheduler_failed_validation', function($action_id, $e, $context)
    {
        do_action( 'wpcron_log_error',$e->getMessage(),current_action() );
    },10,3);
}


/*
 * debugging filters
 */
if (defined('WP_CRON_DEBUG') && WP_CRON_DEBUG)
{
    add_filter( 'pre_reschedule_event',                 function($return,$event)
    {
        $_timestamp_ = wp_date('c',$event->timestamp);
        do_action('wpcron_log_debug',get_defined_vars(),current_filter());
        return $return;
    },PHP_INT_MAX,2);

    add_filter( 'pre_schedule_event',                   function($return,$event)
    {
        $_timestamp_ = wp_date('c',$event->timestamp);
        do_action('wpcron_log_debug',get_defined_vars(),current_filter());
        return $return;
    },PHP_INT_MAX,2);

    add_filter( 'pre_unschedule_event',                 function($return, $timestamp, $hook, $args, $wp_error)
    {
        $_timestamp_ = wp_date('c',$timestamp);
        do_action('wpcron_log_debug',get_defined_vars(),current_filter());
        return $return;
    },PHP_INT_MAX,5);


    add_filter( 'pre_as_schedule_single_action',        function($return, $timestamp, $hook, $args)
    {
        $_timestamp_ = wp_date('c',$timestamp);
        do_action('wpcron_log_debug',get_defined_vars(),current_filter());
        return $return;
    },PHP_INT_MAX,4);

    add_filter( 'pre_as_schedule_recurring_action',     function($return, $timestamp, $interval, $hook, $args)
    {
        $_timestamp_ = wp_date('c',$timestamp);
        do_action('wpcron_log_debug',get_defined_vars(),current_filter());
        return $return;
    },PHP_INT_MAX,5);

    add_filter( 'pre_as_schedule_cron_action',          function($return, $timestamp, $schedule, $hook, $args)
    {
        $_timestamp_ = wp_date('c',$timestamp);
        do_action('wpcron_log_debug',get_defined_vars(),current_filter());
        return $return;
    },PHP_INT_MAX,5);

    add_filter( 'pre_as_enqueue_async_action',          function($return, $hook, $args)
    {
        $_timestamp_ = wp_date('c',time());
        do_action('wpcron_log_debug',get_defined_vars(),current_filter());
        return $return;
    },PHP_INT_MAX,3);
}


/* *****
 *
 * Store WordPress cron events to custome table.
 *
 ***** */


/**
 * Add wp_cron hooks
 */
function store_wp_to_db()
{
    $version = get_option(__NAMESPACE__);
    if (VERSION != $version)
    {
        create_cache_table($version);
        update_option(__NAMESPACE__,VERSION,true);
    }

    add_filter( 'pre_option_cron',                  __NAMESPACE__ .'\\_get_option_cron',    10, 3 );
    add_filter( 'pre_update_option_cron',           __NAMESPACE__ .'\\_update_option_cron', 10, 3 );

    // only update cache at end of cron process
    if (defined('DOING_CRON') && DOING_CRON)
    {
        add_action( "delete_transient_doing_cron",  __NAMESPACE__ .'\\_flush_option_cron',  10, 1 );
    }
}

/**
 * When getting 'cron' option array
 */
function _get_option_cron($return, $option, $default_value)
{
    global $wpdb;
    $table = $wpdb->prefix.CACHE_ID;

    $result = _wp_cron_array();                 //wp_cache_get(CACHE_KEY, CACHE_ID);
    if (empty($result))
    {
        $result = $wpdb->get_var("SELECT `value` FROM `{$table}` WHERE `key` = '".CACHE_KEY."'");
        if ($result) {
            $result = unserialize($result);
            _wp_cron_array($result);            //wp_cache_set(CACHE_KEY,$result,CACHE_ID);
        }
    }

    if (!$result) {
        do_action('wpcron_log_error',[CACHE_KEY.' not found'=>$result],current_action());
    }

    return (empty($result)) ? $return : $result;
}

/**
 * When updating 'cron' option
 */
function _update_option_cron($value, $old_value, $option)
{
    if ( $value === $old_value || maybe_serialize( $value ) === maybe_serialize( $old_value ) ) {
        return $value;
    }

    _wp_cron_array($value);                     //wp_cache_set(CACHE_KEY,$value,CACHE_ID);

    if (!defined('DOING_CRON')) {
        if (!has_action("shutdown", __NAMESPACE__ .'\\_flush_option_cron')) {
            add_action( "shutdown", __NAMESPACE__ .'\\_flush_option_cron' );
        }
    }

    // return $old_value to prevent updating
    // triggers 'cron_reschedule_event_error' / 'cron_unschedule_event_error'
    return $old_value;
}

/**
 * Flush cron actions to db
 */
function _flush_option_cron($transient, $cron = false)
{
    global $wpdb;
    $table = $wpdb->prefix.CACHE_ID;

    $value = $cron ?: _wp_cron_array();         //wp_cache_get(CACHE_KEY,CACHE_ID);
    $result = $wpdb->query( $wpdb->prepare(
            "INSERT INTO `{$table}` (`key`, `value`) VALUES (%s, %s) " .
            "ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);",
        CACHE_KEY,
        serialize($value)
    ));

    if (!$result) {
        do_action('wpcron_log_error',$wpdb->last_error,current_action());
    }
}

/**
 * Create cron cache table
 */
function create_cache_table($version)
{
    global $wpdb;
    $table = $wpdb->prefix.CACHE_ID;

    $charset_collate = $wpdb->get_charset_collate();

    $result = $wpdb->query(
        "CREATE TABLE IF NOT EXISTS `{$table}` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `key` varchar(255) NOT NULL,
            `value` longblob NOT NULL,
            `expires` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY (`id`), UNIQUE `key` (`key`), key `expires` (expires)
        ) ENGINE=InnoDB {$charset_collate};"
    );
    if (!$result) {
        do_action('wpcron_log_error',$wpdb->last_error,__FUNCTION__);
    } else if ($cron = get_option('cron')) {
        // once we create the cron cache, we can delete the cron option
        _update_option_cron($cron, false, 'cron');
        delete_option('cron');
    }

    if ($version <= '1.7.1') {
        $result = $wpdb->query("ALTER TABLE `{$table}` MODIFY COLUMN `value` longblob");
        if (!$result) {
            do_action('wpcron_log_error',$wpdb->last_error,__FUNCTION__);
        }
    }
}

/**
 * Get/set the current cron array
 */
function _wp_cron_array($set=null)
{
    static $cron = null;
    if (!empty($set)) $cron = $set;
    return $cron;
}


/* *****
 *
 * Revert cached events back to 'cron' option
 *
 ***** */


function revert_wp_to_db()
{
    global $wpdb;
    $table = $wpdb->prefix.CACHE_ID;

    remove_filter( 'pre_update_option_cron',        __NAMESPACE__ .'\\_update_option_cron');
    remove_filter( 'pre_option_cron',               __NAMESPACE__ .'\\_get_option_cron');

    if ($value = _get_option_cron(false,'cron',false)) {
        update_option('cron', $value, true);
        //wp_cache_delete(CACHE_KEY,CACHE_ID);
        $wpdb->delete("{$table}",['key'=>CACHE_KEY]);
    }
}


/* *****
 *
 * Namespaced functions
 *
 ***** */


/**
 * Define constants
 *
 * @param array $constants [constant => value]
 */
function define_constants( $constants )
{
    foreach ($constants as $constant => $value)
    {
        if (!defined($constant)) define($constant,$value);
    }
}
