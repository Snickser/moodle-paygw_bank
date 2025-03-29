<?php
// This file is part of the bank paymnts module for Moodle - http://moodle.org/
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
 * Contains helper class to work with PayPal REST API.
 *
 * @package   paygw_bank
 * @copyright UNESCO/IESALC
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace paygw_bank;

use curl;
use core_user;

defined('MOODLE_INTERNAL') || die();

require_once $CFG->libdir . '/filelib.php';

use core_payment\helper as payment_helper;
use stdClass;
use moodle_url;

class bank_helper
{
    public static function get_course_usergroups($courseid = null, $userid = 0): string
    {
	$groupnames = null;
	if (!empty($courseid)) {
	    if ($gs = groups_get_user_groups($courseid, $userid, true)) {
    		foreach ($gs as $gr) {
        	    foreach ($gr as $g) {
            		$groups[$g] = groups_get_group_name($g);
        	    }
    		}
    		if (isset($groups)) {
        	    $groupnames = implode(',', $groups);
    		}
	    }
	}
	return $groupnames;
    }
    public static function get_courseid($paymentarea, $component, $itemid): string
    {
	global $DB;

	$cid = false;
        if ($paymentarea == 'fee') {
	    $cid = $DB->get_field('enrol', 'courseid', ['id' => $itemid]);
    	} else if ($component == 'mod_gwpayments') {
	    $cid = $DB->get_field('gwpayments', 'course', ['id' => $itemid]);
    	}
        return $cid;
    }
    public static function message_to_teachers($context, $from, $subject, $text): bool
    {
        $teachers = get_enrolled_users($context,'paygw/bank:manageincourse');
        foreach ($teachers as $teacher){
    	    self::message_to_user($teacher->id, $from, $subject, $text);
        }
        return true;
    }
    public static function message_to_user($userid, $from, $subject, $text): bool
    {
	global $CFG;

        // Get the user object for messaging and fullname.
        $user = \core_user::get_user($userid);
        if (empty($user) || isguestuser($user) || !empty($user->deleted)) {
            return false;
        }

	$message = new \core\message\message();
	$message->component = 'paygw_bank'; // Your plugin's name.
	$message->name = 'payment_receipt'; // Your notification name from message.php.
	$message->userfrom = $from;
	$message->userto = $user;
	$message->subject = $subject;
	$message->fullmessage = $text;
	$message->fullmessageformat = FORMAT_MARKDOWN;
	$message->notification = 1; // Because this is a notification generated from Moodle, not a user-to-user message.

	message_send($message);

	return true;
    }
    public static function get_openbankentry($itemid, $userid): \stdClass
    {
        global $DB;
        $record = $DB->get_record('paygw_bank', ['itemid' => $itemid, 'userid' => $userid, 'status' => 'P']);
        return $record;
    }
    public static function check_hasfiles($id): \stdClass
    {
        global $DB, $USER;
        $transaction = $DB->start_delegated_transaction();
        $record = $DB->get_record('paygw_bank', ['id' => $id]);
        if ($record->userid == $USER->id) {
            $record->hasfiles = 1;
            $DB->update_record('paygw_bank', $record);
            $transaction->allow_commit();
            return $record;
        }
        $transaction->rollback();
        return null;
    }
    public static function aprobe_pay($id): \stdClass
    {
        global $DB, $USER;
        $transaction = $DB->start_delegated_transaction();
        $record = $DB->get_record('paygw_bank', ['id' => $id]);
        $config = (object) payment_helper::get_gateway_configuration($record->component, $record->paymentarea, $record->itemid, 'bank');
        $payable = payment_helper::get_payable($record->component, $record->paymentarea, $record->itemid);
        $paymentid = payment_helper::save_payment(
            $payable->get_account_id(),
            $record->component,
            $record->paymentarea,
            $record->itemid,
            (int) $record->userid,
            $record->totalamount,
            $payable->get_currency(),
            'bank'
        );
        $record->timechecked = time();
        $record->status = 'A';
        $record->usercheck = $USER->id;
        $record->paymentid = $paymentid;
        $DB->update_record('paygw_bank', $record);
        payment_helper::deliver_order($record->component, $record->paymentarea, $record->itemid, $paymentid, (int) $record->userid);
        $transaction->allow_commit();

	$cid = self::get_courseid($record->paymentarea, $record->component, $record->itemid);

        $send_email = get_config('paygw_bank', 'sendconfmail');
        if ($send_email) {
            $supportuser = core_user::get_support_user();
            $paymentuser = bank_helper::get_user($record->userid);
            $fullname = fullname($paymentuser, true);
            $oldforcelang = force_current_language($paymentuser->lang);
            $subject = get_string('mail_confirm_pay_subject', 'paygw_bank');
            $contentmessage = new stdClass;
            $contentmessage->username = $fullname;
            $contentmessage->code = $record->code;
            $contentmessage->concept = $record->description;
            $contentmessage->useremail = $paymentuser->email;
	    $contentmessage->userfullname = fullname($paymentuser, true);
	    $contentmessage->url = new moodle_url('/course/view.php', ['id' => $cid]);
            $mailcontent = get_string('mail_confirm_pay', 'paygw_bank', $contentmessage);
            self::message_to_user($record->userid, $supportuser, $subject, $mailcontent);
	    force_current_language($oldforcelang);
        }

        $send_email = get_config('paygw_bank', 'senconfirmailtosupport');
        $emailaddress = get_config('paygw_bank', 'notificationsaddress');
	$sendteachermail = get_config('paygw_bank', 'sendteachermail');

        if ($send_email) {
            $contentmessage = new stdClass;
            $paymentuser = bank_helper::get_user($record->userid);
            $contentmessage->code = $record->code;
            $contentmessage->concept = $record->description;
            $contentmessage->useremail = $paymentuser->email;
            $contentmessage->userfullname = fullname($paymentuser, true);
	    $contentmessage->teacher = fullname($USER);
if ($emailaddress) {
            $supportuser = core_user::get_support_user();
            $subject = get_string('email_notifications_subject_confirm', 'paygw_bank');
	    $contentmessage->course = format_string($DB->get_field('course', 'fullname', ['id' => $cid]));
            $mailcontent = get_string('email_notifications_confirm', 'paygw_bank', $contentmessage);
            $emailuser = new stdClass();
            $emailuser->email = $emailaddress;
            $emailuser->id = -99;
            email_to_user($emailuser, $supportuser, $subject, $mailcontent);
}
if ($sendteachermail) {
            $context = \context_course::instance($cid, MUST_EXIST);
    	    $teachers = get_enrolled_users($context,'paygw/bank:manageincourse');
    	    foreach ($teachers as $teacher){
	        $oldforcelang = force_current_language($teacher->lang);
        	$supportuser = core_user::get_support_user();
        	$subject = get_string('email_notifications_subject_confirm', 'paygw_bank');
		$contentmessage->course = format_string($DB->get_field('course', 'fullname', ['id' => $cid]));
        	$mailcontent = get_string('email_notifications_confirm', 'paygw_bank', $contentmessage);
    		self::message_to_user($teacher->id, $supportuser, $subject, $mailcontent);
    		force_current_language($oldforcelang);
    	    }
}
        }

        return $record;
    }
    public static function files($id): array
    {
        $fs = get_file_storage();
        $files = $fs->get_area_files(\context_system::instance()->id, 'paygw_bank', 'transfer', $id);
        $realfiles=array();
        foreach ($files as $f) {
            if($f->get_filename()!='.') {
                array_push($realfiles, $f);
            }
        }
        return $realfiles;
    }
    public static function get_user($userid)
    {
        global $DB;
        return $DB->get_record('user', ['id' => $userid]);
    }
    public static function deny_pay($id,$canceledbyuser=false): \stdClass
    {
        global $DB, $USER;
        $transaction = $DB->start_delegated_transaction();;
        $record = $DB->get_record('paygw_bank', ['id' => $id]);
        $config = (object) payment_helper::get_gateway_configuration($record->component, $record->paymentarea, $record->itemid, 'bank');
        $payable = payment_helper::get_payable($record->component, $record->paymentarea, $record->itemid);
        $paymentuser = bank_helper::get_user($record->userid);
        $record->timechecked = time();
        $record->status = 'D';
        $record->usercheck = $USER->id;
        $record->canceledbyuser = $canceledbyuser;
        $DB->update_record('paygw_bank', $record);
        $transaction->allow_commit();
        $send_email = get_config('paygw_bank', 'senddenmail');
        if ($send_email && $record->userid != $USER->id) {
            $oldforcelang = force_current_language($paymentuser->lang);
            $supportuser = core_user::get_support_user();
            $fullname = fullname($paymentuser, true);
            $subject = get_string('mail_denied_pay_subject', 'paygw_bank');
            $contentmessage = new stdClass;
            $contentmessage->username = $fullname;
            $contentmessage->useremail = $paymentuser->email;
            $contentmessage->code = $record->code;
            $contentmessage->concept = $record->description;
            $mailcontent = get_string('mail_denied_pay', 'paygw_bank', $contentmessage);
            self::message_to_user($record->userid, $supportuser, $subject, $mailcontent);
	    force_current_language($oldforcelang);
        }
        return $record;
    }

    public static function get_pending($status = 'P'): array
    {
        global $DB;
        $order = 'id ASC';
        if($status == 'A') {
    	    $order = 'timecreated DESC';
        }
        $records = $DB->get_records('paygw_bank', ['status' => $status], $order);
        return $records;
    }
    public static function get_user_pending($userid): array
    {
        global $DB;
        $records = $DB->get_records('paygw_bank', ['status' => 'P', 'userid' => $userid]);
        return $records;
    }
    public static function has_openbankentry($itemid, $userid): bool
    {
        global $DB;
        if ($DB->count_records('paygw_bank', ['itemid' => $itemid, 'userid' => $userid, 'status' => 'P']) > 0) {
            return true;
        } else {
            return false;
        }
    }
    public static function create_bankentry($itemid, $userid, $totalamount, $currency, $component, $paymentarea, $description): \stdClass
    {
        global $DB;
        if (bank_helper::has_openbankentry($itemid, $userid)) {
            return null;
        }
        $config = (object) payment_helper::get_gateway_configuration($component, $paymentarea, $itemid, 'bank');

        $user = bank_helper::get_user($userid);

        $record = new \stdClass();
        $record->itemid = $itemid;
        $record->component = $component;
        $record->paymentarea = $paymentarea;
        $record->description = $description;
        $record->userid = $userid;
        $record->totalamount = $totalamount;
        $record->currency = $currency;
        $record->code = $record->timemodified = time();
        $record->usercheck = 0;
        $record->status = 'P';
        $record->timecreated = $record->timemodified = time();
        $id = $DB->insert_record('paygw_bank', $record);
        $record->id = $id;
        $codeprefix = $config->codeprefix;
        $record->code = bank_helper::create_code($id, $codeprefix);
        $DB->update_record('paygw_bank', $record);

        $send_email = get_config('paygw_bank', 'sendnewrequestmail');
        $emailaddress = get_config('paygw_bank', 'notificationsaddress');
	$sendteachermail = get_config('paygw_bank', 'sendteachermail');

        if ($send_email) {
	    $cid = self::get_courseid($record->paymentarea, $record->component, $record->itemid);
	    $groups = self::get_course_usergroups($cid, $userid);

            $contentmessage = new stdClass;
            $contentmessage->code = $record->code;
            $contentmessage->concept = $record->description;
            $contentmessage->useremail = $user->email;
            $contentmessage->userfullname = fullname($user, true);
            $contentmessage->groups = $groups;
            $contentmessage->url = new moodle_url('/payment/gateway/bank/manage.php', ['cid' => $cid]);
if ($emailaddress) {
            $supportuser = core_user::get_support_user();
            $subject = get_string('email_notifications_subject_new', 'paygw_bank');
	    $contentmessage->course = format_string($DB->get_field('course', 'fullname', ['id' => $cid]));
            $mailcontent = get_string('email_notifications_new_request', 'paygw_bank', $contentmessage);
            $emailuser = new stdClass();
            $emailuser->email = $emailaddress;
            $emailuser->id = -99;
            email_to_user($emailuser, $supportuser, $subject, $mailcontent);
}
if ($sendteachermail) {
            $context = \context_course::instance($cid, MUST_EXIST);
    	    $teachers = get_enrolled_users($context,'paygw/bank:manageincourse');
    	    foreach ($teachers as $teacher){
	        $oldforcelang = force_current_language($teacher->lang);
        	$supportuser = core_user::get_support_user();
        	$subject = get_string('email_notifications_subject_new', 'paygw_bank');
		$contentmessage->course = format_string($DB->get_field('course', 'fullname', ['id' => $cid]));
        	$mailcontent = get_string('email_notifications_new_request', 'paygw_bank', $contentmessage);
    		self::message_to_user($teacher->id, $supportuser, $subject, $mailcontent);
    		force_current_language($oldforcelang);
    	    }
}
        }
        return $record;
    }
    public static function create_code($id,$codeprefix=null): string
    {
        if($codeprefix) {
            return $codeprefix . "_" . $id;
        }
        else
        {
            return "code_" . $id;
        }
    }
    public static function get_item_key($component, $paymentarea, $itemid): string
    {
        return $component . "." . $paymentarea . "." . $itemid;
    }
    public static function split_item_key($key): array
    {
        $keyexplode= explode(".", $key);
        return ['component' => $keyexplode[0], 'paymentarea' => $keyexplode[1], 'itemid' => $keyexplode[2]];
    }

    public static function check_in_course($cid, $paymentarea, $component, $itemid): bool
    {
	global $DB;
	if ($cid) {
	    if ($paymentarea == 'fee') {
		$cs = $DB->get_record('enrol', ['id' => $itemid]);
    	    } else if ($component == 'mod_gwpayments') {
		$cs = $DB->get_record('gwpayments', ['id' => $itemid]);
		$cs->courseid = $cs->course;
	    } else {
    	        return false;
    	    }
	    if (!isset($cs->courseid) || $cid != $cs->courseid) {
    	        return false;
    	    }
	}
	return true;
    }

    public static function get_pending_item_collections($cid = false): array
    {
        global $DB;
        $records = $DB->get_records('paygw_bank', ['status' => 'P']);
        $items = [];
        $itemsstringarray = [];
        foreach ($records as $record) {
            $component = $record->component;
            $paymentarea = $record->paymentarea;
            $itemid = $record->itemid;

if (!self::check_in_course($cid, $paymentarea, $component, $itemid)) {
    continue;
}

            $description = $record->description;
            $key = bank_helper::get_item_key($component, $paymentarea, $itemid);
            if (!in_array($key, $itemsstringarray)) {
                array_push($itemsstringarray, $key);
                array_push($items, ['component' => $component, 'paymentarea' => $paymentarea, 'itemid' => $itemid, 'description' => $description, 'key' => $key]);
            }
        }
        return $items;
    }
    public static function sendmail($id, $subject, $message): bool
    {
        global $DB;
        $record = $DB->get_record('paygw_bank', ['id' => $id]);
	$paymentuser = bank_helper::get_user($record->userid);
//        $fullname = fullname($paymentuser, true);
//        $mailcontent = $message;
	if (isset($record->userid)) {
    	    $oldforcelang = force_current_language($paymentuser->lang);
            $supportuser = core_user::get_support_user();
            bank_helper::message_to_user($record->userid, $supportuser, $subject, $message);
	    force_current_language($oldforcelang);
        }
        return true;
    }
}
