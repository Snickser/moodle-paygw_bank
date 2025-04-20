<?php
// This file is part of the bank payments module for Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Plugin version and other meta-data are defined here.
 *
 * @package    paygw_bank
 * @copyright  UNESCO/IESALC
 * @author     Carlos Vicente Corral <c.vicente@unesco.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds navigation items to user profile.
 *
 * @param core_user\output\myprofile\tree $tree The myprofile tree object
 * @param stdClass $user The user object
 * @param bool $iscurrentuser Whether the user is the current user
 * @param stdClass $course The course object
 */
function paygw_bank_myprofile_navigation(core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    $url = new moodle_url('/payment/gateway/bank/my_pending_pay.php');
    $category = new core_user\output\myprofile\category('payments', get_string('payments', 'paygw_bank'), null);
    $node = new core_user\output\myprofile\node(
        'payments',
        'my_pending_payments',
        get_string('my_pending_payments', 'paygw_bank'),
        null,
        $url
    );
    $tree->add_category($category);
    $tree->add_node($node);
}

/**
 * Handles plugin file serving.
 *
 * @param stdClass $course The course object
 * @param stdClass $cm The course module object
 * @param context $context The context object
 * @param string $filearea The file area
 * @param array $args Additional arguments
 * @param bool $forcedownload Whether to force download
 * @param array $options Additional options
 * @return bool
 */
function paygw_bank_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($filearea !== 'transfer') {
        return false;
    }

    // Require login and check access to the module.
    require_login();
    $itemid = array_shift($args); // The first item in the $args array.

    // Extract the filename/filepath from the $args array.
    $filename = array_pop($args);
    if (!$args) {
        $filepath = '/';
    } else {
        $filepath = '/' . implode('/', $args) . '/';
    }

    // Retrieve the file from the Files API.
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'paygw_bank', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false; // The file does not exist.
    }

    // Send the file back to the browser.
    send_stored_file($file, 86400, 0, $forcedownload, $options);
}

if (!function_exists('str_ends_with')) {
/**
 * Polyfill for str_ends_with for PHP versions that don't have it.
 *
 * @param string $str The string to check
 * @param string $end The ending to check for
 * @return bool
 */
    function str_ends_with($str, $end) {
        return (@substr_compare($str, $end, -strlen($end)) == 0);
    }
}

/**
 * Extends course navigation with bank payment management link.
 *
 * @param navigation_node $navigation The navigation node
 * @param stdClass $course The course object
 * @param context $context The context object
 */
function paygw_bank_extend_navigation_course($navigation, $course, $context) {
    global $PAGE;

    // Check if user has capability to manage payments in course.
    if (has_capability('paygw/bank:manageincourse', $context)) {
        $url = new moodle_url('/payment/gateway/bank/manage.php', ['cid' => $course->id]);
        $navigation->add(
            get_string('pluginname', 'paygw_bank'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'paygw_bank',
            new pix_icon('icon', '', 'paygw_bank')
        );
    }
}
