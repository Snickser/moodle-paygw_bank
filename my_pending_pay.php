<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

use core_payment\helper;
use paygw_bank\bank_helper;

require_once __DIR__ . '/../../../config.php';
require_once './lib.php';
require_login();

$context = context_system::instance(); // Because we "have no scope".
$PAGE->set_context(context_user::instance($USER->id));
$canuploadfiles = get_config('paygw_bank', 'usercanuploadfiles');
$allowusercancel = get_config('paygw_bank', 'allowusercancel');
// $PAGE->set_url('/payment/gateway/bank/my_pending_pay.php', $params);
$PAGE->set_url($SCRIPT);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('my_pending_payments', 'paygw_bank'));
// $PAGE->navigation->extend_for_user($USER->id);
// $PAGE->set_heading(get_string('my_pending_payments', 'paygw_bank'));
$PAGE->navbar->add(get_string('profile'), new moodle_url('/user/profile.php', ['id' => $USER->id]));
$PAGE->navbar->add(get_string('my_pending_payments', 'paygw_bank'));
$action = optional_param('action', '', PARAM_TEXT);
$confirm = optional_param('confirm', 0, PARAM_INT);
$id = optional_param('id', 0, PARAM_INT);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('my_pending_payments', 'paygw_bank'), 2);
// if request method is POST
$requestmethod = $_SERVER['REQUEST_METHOD'];

if ($requestmethod == 'POST') {
    if ($confirm == 1 && $id > 0 && $allowusercancel) {
        require_sesskey();
        if ($action == 'D') {
            bank_helper::deny_pay($id, true);
            \core\notification::info(get_string('payment_denied', 'paygw_bank'));
            $OUTPUT->notification(get_string('payment_denied', 'paygw_bank'));
        }
    }
}

$bankentries = bank_helper::get_user_pending($USER->id);

if (!$bankentries) {
    $match = [];
    echo '</br><h5>' . (get_string('noentriesfound', 'paygw_bank')) . '</h5>';
    $table = null;
} else {
    $table = new html_table();
    $canuploadfiles = get_config('paygw_bank', 'usercanuploadfiles');
    $headarray = [get_string('timecreated'), get_string('code', 'paygw_bank'), get_string('course'), get_string('concept', 'paygw_bank'), get_string('total_cost', 'paygw_bank'), get_string('status')];
    if ($canuploadfiles) {
        array_push($headarray, get_string('hasfiles', 'paygw_bank'));
    }
    array_push($headarray, get_string('actions'));
    $table->head = $headarray;
    foreach ($bankentries as $bankentry) {
        $config = (object) helper::get_gateway_configuration($bankentry->component, $bankentry->paymentarea, $bankentry->itemid, 'bank');
        $payable = helper::get_payable($bankentry->component, $bankentry->paymentarea, $bankentry->itemid);
        $currency = $payable->get_currency();
        $customer = $DB->get_record('user', ['id' => $bankentry->userid]);
        $fullname = fullname($customer, true);
        $amount = helper::get_cost_as_string($bankentry->totalamount, $currency, 0);
        $surcharge = helper::get_gateway_surcharge('bank');

        $unpaid = '-';
        // Check uninterrupted cost.
        if ($bankentry->component == "enrol_yafee") {
            $cs = $DB->get_record('enrol', ['id' => $bankentry->itemid, 'enrol' => 'yafee']);
            if ($data = $DB->get_record('user_enrolments', ['userid' => $bankentry->userid, 'enrolid' => $cs->id])) {
                if (isset($data->timeend) || isset($data->timestart)) {
                    if ($cs->customint5 && $cs->enrolperiod && $data->timeend < time() && $data->timestart) {
                        $unpaid = (ceil(((time() - $data->timeend) / $cs->enrolperiod)) * $cs->cost);
                        // Add surcharge.
                        $unpaid = helper::get_rounded_cost($unpaid, $currency, $surcharge);
                    }
                }
            }
            if ($bankentry->totalamount < $unpaid) {
                $unpaid = '<font color=red><b>' . get_string('unpaidnotice', 'paygw_bank') . '</b></font>';
            } else {
                $unpaid = '<font color=green>' . get_string('ok') . '</font>';
            }
        }

        $maxnumberfiles = get_config('paygw_bank', 'maxnumberfiles');
        $files = bank_helper::files($bankentry->id);

        $component = $bankentry->component;
        $paymentarea = $bankentry->paymentarea;
        $itemid = $bankentry->itemid;
        $description = $bankentry->description;

        if ($bankentry->status == 'P') {
                $urlpay = new moodle_url('/payment/gateway/bank/pay.php', ['sesskey' => sesskey(), 'component' => $component, 'paymentarea' => $paymentarea, 'itemid' => $itemid, 'description' => $description, 'editfiles' => 1]);
            if (count($files) < $maxnumberfiles) {
                $buttongo = '<a class="btn btn-primary btn-block" href="' . $urlpay . '">' . get_string('edit') . '</a>';
            } else {
                $buttongo = '<a class="btn btn-secondary btn-block" href="' . $urlpay . '">' . get_string('view') . '</a>';
            }

            $buttondeny = '<form action="my_pending_pay.php" id="cancel_' . $bankentry->id . '" method="POST">
        <input type="hidden" name="sesskey" value="' . sesskey() . '">
        <input type="hidden" name="id" value="' . $bankentry->id . '">
        <input type="hidden" name="action" value="D">
        <input type="hidden" name="confirm" value="1">
        <input class="btn btn-danger mt-3 btn-block" type="submit" data-modal="confirmation" data-modal-title-str=\'["cancel_process", "paygw_bank"]\'
        data-modal-content-str=\'["are_you_sure_cancel","paygw_bank"]\' data-modal-destination="javascript:document.getElementById(\'cancel_' . $bankentry->id . '\').submit()" data-modal-yes-button-str=\'["yes", "core"]\' value="' . get_string("cancel_process", "paygw_bank") . '"></input>
        </form>';
        } else {
            $buttongo = '';
            $buttondeny = '';
        }

        $courseid = bank_helper::get_courseid($bankentry->paymentarea, $bankentry->component, $bankentry->itemid);
        $course = get_course($courseid);

        $buttons = $buttongo;
        if ($allowusercancel) {
            $buttons = $buttongo . $buttondeny;
        }
        $buttons = '<div class="d-grid gap-2">' . $buttons . '</div>';
        $dataarray = [date('Y.m.d, H:i', $bankentry->timecreated), $bankentry->code,
        format_string($course->fullname),
        $bankentry->description,
        $amount, $unpaid];

        if ($canuploadfiles) {
            $hasfiles = "<font color=red><b>" . get_string('no') . "</b></font>";
            if (count($files)) {
                $hasfiles = '<font color=green>' . get_string('yes') . '</font>';
            }
            array_push($dataarray, $hasfiles);
        }
        array_push($dataarray, $buttons);
        $table->data[] = $dataarray;
    }
    echo html_writer::table($table);
}

echo '<br><div align=center>';
// echo $OUTPUT->single_button('/user/profile.php', get_string('back'));
echo '</div>';

echo $OUTPUT->footer();
