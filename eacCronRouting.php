<?php
/**
 * {eac}CronRouting - Reroute WP-Cron events to Action Scheduler or Action Scheduler actions to WP-Cron.
 *
 * @category    WordPress Plugin
 * @package     {eac}CronSettings
 * @author      Kevin Burkholder <KBurkholder@EarthAsylum.com>
 * @copyright   Copyright (c) 2025 EarthAsylum Consulting <www.earthasylum.com>
 *
 * @wordpress-plugin
 * Plugin Name:         {eac}CronRouting
 * Description:         {eac}CronRouting - Reroute WP-Cron events to Action Scheduler or Action Scheduler actions to WP-Cron.
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

    This plugin facilitates...

    - Routing WP-Cron events to Action Scheduler actions.
    - Routing Action Scheduler actions to WP-Cron events.

    Neither option offers a complete solution but most events/actions will end up being rerouted to the desired process.
    You may need to manually remove events/actions from one process once they have been routed to the other process.
    Recurring Action Scheduler actions may need to be manually removed in order to force rescheduling to wp-Cron.
 *
 ***** */

namespace EarthAsylumConsulting\CronSettings;

const WP_TO_AS      = 'wp-as';
const AS_TO_WP      = 'as-wp';
const AS_CRON_HOOK  = 'action_scheduler_run_queue';


/* *****
 *
 * Define constants to control actions.
 *
 * set/change/remove these constants as needed
 * may instead be defined in wp-congif.php
 *
 ***** */


/*
 * Define constants controlling event routing
 */
 if (!defined('WP_CRON_REROUTE_EVENTS'))
 {
    // no routing of events or actions
    define('WP_CRON_REROUTE_EVENTS',false);

    // wp-cron -> action scheduler
//  define('WP_CRON_REROUTE_EVENTS',WP_TO_AS);

    // action scheduler -> wp-cron
//  define('WP_CRON_REROUTE_EVENTS',AS_TO_WP);
 }


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


if (defined('WP_CRON_REROUTE_EVENTS') && WP_CRON_REROUTE_EVENTS)
{
    /*
     * add schedules translated from Action Scheduler intervals when rerouting events
     */
    if ($schedules = get_option('as_wp_cron_schedules'))
    {
        add_filter('cron_schedules', function($cron_intervals) use($schedules)
        {
            return array_merge($cron_intervals,$schedules);
        },1000);
    }

    /*
     * Route WP-Cron to Action Scheduler or Action Scheduler to WP-Cron.
     * Events scheduled before WordPress 'init' are not rerouted.
     */
    add_action('action_scheduler_init', function()
    {
        // Route WP-Cron to Action Scheduler
        if (WP_CRON_REROUTE_EVENTS == WP_TO_AS) {
            route_wp_to_as();
        } else
        // Route Action Scheduler to WP-Cron
        if (WP_CRON_REROUTE_EVENTS == AS_TO_WP) {
            route_as_to_wp();
        }
    },5);
}


/* *****
 *
 * Route WordPress events to Action Scheduler schedules.
 * WP-Cron may call these filters before Action Scheduler initializes
 *
 ***** */


/**
 * Add wp_cron hooks
 */
function route_wp_to_as()
{
    add_filter( 'pre_schedule_event',           __NAMESPACE__.'\\_wp_schedule_event',        10,3 );
    add_filter( 'pre_reschedule_event',         __NAMESPACE__.'\\_wp_reschedule_event',      10,3 );
    add_filter( 'pre_unschedule_event',         __NAMESPACE__.'\\_wp_unschedule_event',      10,5 );
    add_filter( 'pre_clear_scheduled_hook',     __NAMESPACE__.'\\_wp_clear_scheduled_hook',  10,4 );
    add_filter( 'pre_unschedule_hook',          __NAMESPACE__.'\\_wp_unschedule_hook',       10,3 );
    add_filter( 'pre_get_scheduled_event',      __NAMESPACE__.'\\_wp_get_scheduled_event',   10,4 );
}

/**
 * Shortcut to check wp-to-as validity
 *
 * @param callable  $callback function to execute
 * @param array     $args function argument ([0]=default return value)
 * @param string    $hook event hook name
 */
function whenReady( $callback, $args, $hook)
{
    // allow external filters or Action Scheduler cron to fall through
    return (!empty($args[0]) || !($hook == AS_CRON_HOOK))
        ? $args[0]
        : call_user_func_array($callback, $args);
}

/**
 * Route single/recurring events to Action Scheduler
 */
function _wp_schedule_event( $return, $event, $wp_error )
{
    return whenReady(function( $return, $event, $wp_error )
        {
            $event = (array)$event;
            return ($event['schedule'] == false)
                ? \as_schedule_single_action( $event['timestamp'], $event['hook'], $event['args'], 'wp-cron' )
                : \as_schedule_recurring_action( $event['timestamp'], $event['interval'], $event['hook'], $event['args'], 'wp-cron' );
        },
        [$return, $event, $wp_error],
        $event->hook
    );
}

/**
 * Route reschedule recurring events to Action Scheduler
 */
function _wp_reschedule_event( $return, $event, $wp_error )
{
    return whenReady(function( $return, $event, $wp_error )
        {
            $event = (array)$event;
            \as_unschedule_action( $event['hook'], $event['args'] );
            return \as_schedule_recurring_action( $event['timestamp'], $event['interval'], $event['hook'], $event['args'], 'wp-cron' );
        },
        [$return, $event, $wp_error],
        $event->hook
    );
}

/**
 * Route unschedule event to Action Scheduler
 */
function _wp_unschedule_event( $return, $timestamp, $hook, $args, $wp_error )
{
    whenReady(function( $return, $timestamp, $hook, $args, $wp_error )
        {
            return \as_unschedule_action( $hook, $args );
        },
        [$return, $timestamp, $hook, $args, $wp_error],
        $hook
    );
    return $return;
}

/**
 * Route clear events to Action Scheduler
 */
function _wp_clear_scheduled_hook( $return, $hook, $args, $wp_error )
{
    whenReady(function( $return, $hook, $args, $wp_error )
        {
            return \as_unschedule_all_actions( $hook, $args );
        },
        [$return, $hook, $args, $wp_error],
        $hook
    );
    return $return;
}

/**
 * Route unschedule hook to Action Scheduler
 */
function _wp_unschedule_hook( $return, $hook, $wp_error )
{
    whenReady(function( $return, $hook, $wp_error )
        {
            return \as_unschedule_all_actions( $hook );
        },
        [$return, $hook, $wp_error],
        $hook
    );
    return $return;
}

/**
 * Get next event from Action Scheduler
 */
function _wp_get_scheduled_event( $return, $hook, $args, $timestamp )
{
    return whenReady(function( $return, $hook, $args, $timestamp )
        {
            $query_args = [
                'hook'          => $hook,
                'status'        => \ActionScheduler_Store::STATUS_PENDING,
                'per_page'      => 1,
            ];
            if ($args && is_array($args)) {
                $query_args['args']          = $args;
            }
            if ($timestamp && is_numeric($timestamp)) {
                $query_args['date']          = $timestamp;
                $query_args['date_compare']  = '>=';
            }
            $result =  \as_get_scheduled_actions($query_args);

            return (empty($result)) ? false : event_from_action(current($result));
        },
        [$return, $hook, $args, $timestamp],
        $hook
    );
}


/* *****
 *
 * Route Action Scheduler events to WordPress schedules
 * Action Scheduler initializes itself on 'init' with priority 1 and can not be used until then.
 *
 ***** */


/**
 * Add Action Scheduler hooks
 */
function route_as_to_wp()
{
    add_filter( 'pre_as_schedule_single_action',    __NAMESPACE__.'\\_as_schedule_single_action',    10,4 );
    add_filter( 'pre_as_schedule_recurring_action', __NAMESPACE__.'\\_as_schedule_recurring_action', 10,5 );
//  add_filter( 'pre_as_schedule_cron_action',      __NAMESPACE__.'\\_as_schedule_cron_action',      10,5 );
    add_filter( 'pre_as_enqueue_async_action',      __NAMESPACE__.'\\_as_enqueue_async_action',      10,3 );

// no such filters...
//  add_filter( 'as_unschedule_action',             __NAMESPACE__.'\\_as_unschedule_action',         10,2 );
//  add_filter( 'as_unschedule_all_actions',        __NAMESPACE__.'\\_as_unschedule_all_actions',    10,2 );
//  add_filter( 'as_next_scheduled_action',         __NAMESPACE__.'\\_as_next_scheduled_action',     10,2 );
//  add_filter( 'as_has_scheduled_action',          __NAMESPACE__.'\\_as_has_scheduled_action',      10,2 );
//  add_filter( 'as_get_scheduled_actions',         __NAMESPACE__.'\\_as_get_scheduled_actions',     10,2 );
}

/**
 * Route single events to wp-cron
 */
function _as_schedule_single_action( $return, $timestamp, $hook, $args )
{
    return \wp_schedule_single_event( $timestamp, $hook, $args );
}

/**
 * Route recurring events to wp-cron
 */
function _as_schedule_recurring_action( $return, $timestamp, $interval, $hook, $args )
{
    $recurrence = find_wp_schedule($interval);
    return \wp_schedule_event( $timestamp, $recurrence, $hook, $args );
}

/**
 * @todo - Route recurring cron events to wp-cron
 */
function _as_schedule_cron_action( $return, $timestamp, $schedule, $hook, $args )
{
    return false;
}

/**
 * Route single events to wp-cron
 */
function _as_enqueue_async_action( $return, $hook, $args )
{
    return \wp_schedule_single_event( time(), $hook, $args );
}


/* *****
 *
 * Namespaced functions
 *
 ***** */


/**
 * Find a WordPress schedule from Action Scheduler interval
 *
 * @param int $interval number of seconds between executions
 */
function find_wp_schedule( $interval )
{
    $cron_schedules = wp_get_schedules();
    uasort($cron_schedules, function($a,$b)
        {
            return ($a['interval'] == $b['interval']) ? 0 : (($a['interval'] < $b['interval']) ? -1 : 1);
        }
    );
    // look for matching interval from any schedule
    foreach($cron_schedules as $name => $schedule)
    {
        if ($interval == $schedule['interval']) {
            return $name;
        }
    }
    return add_wp_schedule($interval);
}

/**
 * Add a new WordPress schedule from Action Scheduler interval
 *
 * @param int $interval number of seconds between executions
 */
function add_wp_schedule( $interval )
{
    $schedule =  ["as_every_{$interval}_seconds"  => [
        'interval'  => $interval,
        'display'   => "Action Scheduler, every {$interval} seconds",
    ]];
    $schedules = \get_option('as_wp_cron_schedules',[]);
    array_push($schedules,$schedule);
    \update_option('as_wp_cron_schedules',$schedules);
    return key($schedule);
}

/**
 * Action Schedule action to WP-Cron event
 *
 * @param object $action Action Scheduler action
 */
function event_from_action( $action )
{
    $schedule           = $action->get_schedule();
    $timestamp          = $schedule->get_date()->getTimestamp();
    if ($schedule->is_recurring()) {
        $interval       = $schedule->get_recurrence();
        $schedule       = find_wp_schedule($interval);
    } else {
        $interval       = null;
        $schedule       = false;
    }

    return (object) array(
        'hook'          => $action->get_hook(),
        'timestamp'     => $timestamp,
        'schedule'      => $schedule,
        'args'          => $action->get_args(),
        'interval'      => $interval,
    );
}
