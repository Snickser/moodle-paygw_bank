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

/**
 * Bank payment gateway management interface.
 *
 * @package    paygw_bank
 * @copyright  2023 Your Name <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_payment\helper;
use core_reportbuilder\external\columns\sort\get;
use gwpayments\bank_helper as GwpaymentsBank_helper;
use paygw_bank\bank_helper as Paygw_bankBank_helper;
use paygw_bank\bank_helper;

require_once(__DIR__ . '/../../../config.php');
require_once('./lib.php');

global $CFG, $USER, $DB;

// Require login and get parameters.
require_login();

$confirm = optional_param('confirm', 0, PARAM_INT);
$id      = optional_param('id', 0, PARAM_INT);
$ids     = optional_param('ids', '', PARAM_TEXT);
$filter  = optional_param('filter', '', PARAM_TEXT);
$action  = optional_param('action', '', PARAM_TEXT);
$cid     = optional_param('cid', 0, PARAM_INT);

// Set up course context if course ID is provided.
$course = null;
if ($cid) {
    $course = $DB->get_record('course', ['id' => $cid], '*', MUST_EXIST);
    $context = context_course::instance($course->id, MUST_EXIST);
    require_capability('paygw/bank:manageincourse', $context, $USER->id);
} else {
    $context = context_system::instance();
    require_capability('paygw/bank:managepayments', $context);
}

// Set up page.
$PAGE->set_context($context);
$PAGE->set_url('/payment/gateway/bank/manage.php');
$PAGE->set_pagelayout('standard');
$pagetitle = get_string('manage', 'paygw_bank');
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);
$PAGE->set_cacheable(false);
$PAGE->set_secondary_navigation(false);

// Add breadcrumbs.
if ($cid) {
    $PAGE->navbar->add($course->fullname, '/course/view.php?id=' . $course->id);
}
$PAGE->navbar->add(get_string('pluginname', 'paygw_bank'));

echo $OUTPUT->header();

// Get pending items and display filter dropdown.
$items = bank_helper::get_pending_item_collections($cid);

echo '<form name="filteritem" method="post" action="/payment/gateway/bank/manage.php">';
echo '<input type="hidden" name="cid" value="' . $cid . '">';
echo '<select class="custom-select" name="filter" id="filterkey">';
echo '<option value="">' . get_string('pendingrequests', 'paygw_bank') . '</option>';
foreach ($items as $item) {
    echo '<option value="' . $item['key'] . '" >' . $item['description'] . '</option>';
}
echo '<option value="showarchived">' . get_string('group:archive', 'mimetypes') . '</option>';
echo '</select>
&nbsp;<input type="submit" class="btn btn-primary" value="' . get_string('show') . '">
</form></br>';

// Display appropriate heading based on filter.
if ($filter == 'showarchived') {
    echo $OUTPUT->heading(get_string('group:archive', 'mimetypes'), 4);
} else {
    echo $OUTPUT->heading(get_string('pending_payments', 'paygw_bank'), 4);
}

// Handle file deletion action.
if ($action == 'deletefiles' && $id && has_capability('paygw/bank:managepayments', $context)) {
    require_sesskey();
    bank_helper::deletefiles($id);
    $id = 0;
}

// Handle payment approval/denial.
if ($confirm && $id) {
    require_sesskey();
    // Check if payment is still pending.
    if ($DB->record_exists('paygw_bank', ['id' => $id, 'status' => 'P'])) {
        if ($action == 'A') {
            bank_helper::approve_pay($id);
            $OUTPUT->notification("approved");
            \core\notification::info(get_string('mail_confirm_pay_subject', 'paygw_bank'));
        } else if ($action == 'D') {
            bank_helper::deny_pay($id);
            $OUTPUT->notification("denied");
            \core\notification::info(get_string('mail_denied_pay_subject', 'paygw_bank'));
        }
    } else {
        \core\notification::warning("Reloaded");
    }
    $id = 0;
}

// Handle bulk email sending.
if ($confirm == 1 && $ids != '' && $action == 'sendmail') {
    require_sesskey();
    $ids = explode(',', $ids);
    foreach ($ids as $i) {
        if ($i > 0) {
            bank_helper::sendmail($i, optional_param('subject', '', PARAM_TEXT), optional_param('message', '', PARAM_TEXT));
        }
    }
    \core\notification::info(get_string('mails_sent', 'paygw_bank'));
    $OUTPUT->notification(get_string('mails_sent', 'paygw_bank'));
}

$posturl = new moodle_url($PAGE->url, ['sesskey' => sesskey()]);

// Determine status filter.
$status = 'P';
if ($filter == 'showarchived') {
    $status = 'A';
}

// Get pending/archived payments.
$bankentries = bank_helper::get_pending($status, $id);

if (!$bankentries) {
    $match = [];
    echo '</br><h5>' . (get_string('noentriesfound', 'paygw_bank')) . '</h5>';
    $table = null;
} else {
    // Create and display payments table.
    $table = new html_table();
    $checkboxcheckall = '<input type="checkbox" id="checkall" name="checkall" value="checkall" onchange="checkAll(this)">';
    ?>
    <script>
    function checkAll(ele) {
        var checkboxes = document.getElementsByTagName("input");
        if (ele.checked) {
            for (var i = 0; i < checkboxes.length; i++) {
                if (checkboxes[i].type == "checkbox" && checkboxes[i].name == "selectitem") {
                    checkboxes[i].checked = true;
                }
            }
        } else {
            for (var i = 0; i < checkboxes.length; i++) {
                if (checkboxes[i].type == "checkbox" && checkboxes[i].name == "selectitem") {
                    checkboxes[i].checked = false;
                }
            }
        }
    }
    </script>
    <?php

    $table->head = [];
    array_push(
        $table->head,
        $checkboxcheckall,
        get_string('timecreated'),
    );

    if ($filter == 'showarchived') {
        array_push(
            $table->head,
            get_string('recordapproved', 'data'),
        );
    }

    array_push(
        $table->head,
        get_string('code', 'paygw_bank'),
    );

    if (!$cid) {
        array_push(
            $table->head,
            get_string('course'),
        );
    }

    array_push(
        $table->head,
        get_string('fullnameuser'),
        get_string('email'),
        get_string('group'),
        get_string('concept', 'paygw_bank'),
    );

    if ($filter == 'showarchived') {
        array_push(
            $table->head,
            get_string('total_cost', 'paygw_bank'),
            get_string('hasfiles', 'paygw_bank'),
        );
    } else {
        array_push(
            $table->head,
            get_string('total_cost', 'paygw_bank'),
            get_string('today_cost', 'paygw_bank'),
            get_string('currency'),
            get_string('hasfiles', 'paygw_bank'),
        );
    }

    if ($filter != 'showarchived') {
        array_push(
            $table->head,
            get_string('actions')
        );
    }

    // Populate table with payment data.
    foreach ($bankentries as $bankentry) {
        $bankentrykey = bank_helper::get_item_key($bankentry->component, $bankentry->paymentarea, $bankentry->itemid);

        // Apply filters.
        if ($filter != '' && ($bankentrykey != $filter)) {
            if ($filter != 'showarchived' || !$bankentry->hasfiles) {
                continue;
            }
        }

        // Check if payment belongs to current course.
        if (!bank_helper::check_in_course($cid, $bankentry->paymentarea, $bankentry->component, $bankentry->itemid)) {
            continue;
        }

        $config = (object) helper::get_gateway_configuration($bankentry->component, $bankentry->paymentarea, $bankentry->itemid, 'bank');

        $groups = bank_helper::get_course_usergroups($cid, $bankentry->userid);
        if (isset($config->onlyingroup) && $config->onlyingroup && !has_capability('moodle/site:accessallgroups', $context)) {
            if (!bank_helper::check_teacheringroup($cid, $USER->id, $groups)) {
                continue;
            }
        }

        // Get payment details.
        $payable = helper::get_payable($bankentry->component, $bankentry->paymentarea, $bankentry->itemid);
        $currency = $payable->get_currency();
        $customer = $DB->get_record('user', ['id' => $bankentry->userid]);
        $fullname = fullname($customer, false);

        $amount = helper::get_rounded_cost($bankentry->totalamount, $currency, 0);
        $surcharge = helper::get_gateway_surcharge('bank');

        $unpaid = '-';
        $primary = 'primary';
        
        // Check for unpaid fees (specific to enrol_yafee).
        if ($bankentry->component == "enrol_yafee" && $filter != 'showarchived') {
            $cs = $DB->get_record('enrol', ['id' => $bankentry->itemid, 'enrol' => 'yafee']);
            if ($data = $DB->get_record('user_enrolments', ['userid' => $bankentry->userid, 'enrolid' => $cs->id])) {
                if (isset($data->timeend) || isset($data->timestart)) {
                    if ($cs->customint5 && $cs->enrolperiod && $data->timeend < time() && $data->timestart) {
                        $unpaid = (round(((time() - $data->timeend) / $cs->enrolperiod)) * $cs->cost);
                        $unpaid = helper::get_rounded_cost($unpaid, $currency, $surcharge);
                    }
                }
            }
            if ($amount < $unpaid) {
                $unpaid = '<font color=red><b>' . $unpaid . '</b></br>' . get_string('unpaidnotice', 'paygw_bank') . '</font>';
                $primary = 'secondary';
            } else {
                $unpaid = '<font color=green>' . get_string('ok') . '</font>';
            }
        }

        if (!$bankentry->hasfiles) {
            $primary = 'secondary';
        }

        // Create approve/deny buttons for pending payments.
        $buttonapprove = '';
        $buttondeny = '';
        if ($filter != 'showarchived') {
            $buttonapprove = '<form name="formapprovepay' . $bankentry->id . '" method="POST">
                <input type="hidden" name="sesskey" value="' . sesskey() . '">
                <input type="hidden" name="id" value="' . $bankentry->id . '">
                <input type="hidden" name="action" value="A">
                <input type="hidden" name="confirm" value="1">
                <input class="btn btn-block btn-' . $primary . ' mb-2 form-submit" type="submit" value="' . get_string('approve', 'paygw_bank') . '"></input>
            </form>';
            
            $buttondeny = '<form name="formaprovepay' . $bankentry->id . '" method="POST">
                <input type="hidden" name="sesskey" value="' . sesskey() . '">
                <input type="hidden" name="id" value="' . $bankentry->id . '">
                <input type="hidden" name="action" value="D">
                <input type="hidden" name="confirm" value="1">
                <button type="submit" class="btn btn-danger" data-modal="confirmation"
                    data-modal-title-str=\'["deny", "paygw_bank"]\' data-modal-content-str=\'["areyousure"]\'
                    data-modal-yes-button-str=\'["confirm", "core"]\'">' . get_string('deny', 'paygw_bank') . '</button>
            </form>';
        }

        // Handle file attachments.
        $files = "-";
        $selectitemcheckbox = '<input type="checkbox" name="selectitem" value="' . $bankentry->id . '">';
        $hasfiles = get_string('no');
        $fs = get_file_storage();
        $files = bank_helper::files($bankentry->id);
        
        if ($bankentry->hasfiles > 0 || count($files) > 0) {
            $hasfiles = '<button type="button" class="btn btn-primary btn-block mb-2" data-toggle="modal" data-target="#staticBackdrop' . $bankentry->id . '" id="launchmodal' . $bankentry->id . '">&nbsp;' . get_string('view') . '&nbsp;</button>';

            if ($filter == 'showarchived' && has_capability('paygw/bank:managepayments', $context)) {
                $hasfiles .= '
                <form action="manage.php" id="deletefiles_' . $bankentry->id . '" method="POST">
                    <input type="hidden" name="sesskey" value="' . sesskey() . '">
                    <input type="hidden" name="id" value="' . $bankentry->id . '">
                    <input type="hidden" name="filter" value="showarchived">
                    <input type="hidden" name="action" value="deletefiles">
                    <button type="submit" class="btn btn-secondary" data-modal="confirmation"
                        data-modal-title-str=\'["delete", "core"]\' data-modal-content-str=\'["areyousure"]\'
                        data-modal-yes-button-str=\'["confirm", "core"]\'">' . get_string('delete') . '</button>
                </form>';
            }

            // Create modal for file viewing.
            $hasfiles .= '
            <div class="modal fade" id="staticBackdrop' . $bankentry->id . '" aria-labelledby="staticBackdropLabel' . $bankentry->id . '" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="staticBackdropLabel' . $bankentry->id . '">' . get_string('files') . '</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        </div>
                        <div class="modal-body">';

            $hasfilesbody = '<ol class="pt-3 pb-3 rounded" style="background-color: #f2f3f4; font-size: 1.15em;">';
            $hasfilesimg = false;
            foreach ($files as $f) {
                $url = moodle_url::make_pluginfile_url($f->get_contextid(), $f->get_component(), $f->get_filearea(), $f->get_itemid(), $f->get_filepath(), $f->get_filename(), false);
                $hasfilesbody .= '<li class="mb-2"><a href="' . $url . '" download><b>' . $f->get_filename() . '</b></a><br>';
                $hasfilesbody .= get_string('size') . ': ' . round($f->get_filesize() / 1024, 2) . ' KB</li>';
                
                // Handle image and PDF previews.
                if (str_ends_with($f->get_filename(), ".png") || str_ends_with($f->get_filename(), ".jpeg") || 
                    str_ends_with($f->get_filename(), ".jpg") || str_ends_with($f->get_filename(), ".gif")) {
                    $hasfilesimg .= "<p align=center class='pt-3'><img class='rounded shadow' style='max-width: 100%; max-height: 800px; object-fit: contain;' src='$url'></p>";
                }
                if (str_ends_with($f->get_filename(), ".pdf")) {
                    $hasfilesimg .= "<p align=center class='pt-3'><object type='application/pdf' class='rounded shadow' style='width: 96%; height: 800px;' data='$url'></object></p>";
                }
            }

            $hasfiles .= $hasfilesbody . '</ol>';
            if ($hasfilesimg) {
                $hasfiles .= $hasfilesimg;
            }
            $hasfiles .= '
                        </div>
                        <div class="modal-footer">
                        </div>
                    </div>
                </div>
            </div>';
        }

        $url = helper::get_success_url($bankentry->component, $bankentry->paymentarea, $bankentry->itemid);

        // Build table row data.
        $tabledata = [];
        array_push(
            $tabledata,
            $selectitemcheckbox,
            date('d.m.Y, H:i', $bankentry->timecreated),
        );

        if ($filter == 'showarchived') {
            array_push(
                $tabledata,
                date('d.m.Y, H:i', $bankentry->timechecked),
            );
        }
        
        array_push(
            $tabledata,
            $bankentry->code,
        );

        $groupnames = null;
        if (!$cid) {
            $courseid = bank_helper::get_courseid($bankentry->paymentarea, $bankentry->component, $bankentry->itemid);
            $groupnames = bank_helper::get_course_usergroups($courseid, $bankentry->userid);
            $course = get_course($courseid);
            $courseurl = format_string($course->fullname);
            array_push($tabledata, $courseurl);
        } else {
            $groupnames = bank_helper::get_course_usergroups($cid, $bankentry->userid);
        }

        array_push(
            $tabledata,
            html_writer::link('/user/profile.php?id=' . $customer->id, $fullname, ['target' => '_blank']),
            $customer->email,
            $groupnames,
            html_writer::link($url, $bankentry->description, ['target' => '_blank']),
        );
        
        if ($filter == 'showarchived') {
            array_push(
                $tabledata,
                helper::get_cost_as_string($amount, $currency, 0),
            );
        } else {
            array_push(
                $tabledata,
                $amount,
                $unpaid,
                $currency,
            );
        }
        
        array_push(
            $tabledata,
            $hasfiles,
        );

        if ($filter != 'showarchived') {
            array_push(
                $tabledata,
                $buttonapprove . $buttondeny,
            );
        }

        $table->data[] = $tabledata;
    }
    
    // Display the table if there's data.
    if (count($table->data)) {
        echo html_writer::table($table);
    } else {
        echo '</br><h5>' . (get_string('noentriesfound', 'paygw_bank')) . '</h5>';
    }
}

// Add bulk email sending functionality if there are entries.
if (count($bankentries)) {
    ?>
<div class="row">
    <div class="col">
        <button type="button" class="btn btn-secondary" onclick="sendmail()">
            <?php echo get_string('sendmailtoselected', 'paygw_bank'); ?>
        </button>
    </div>
</div>
<script>
function sendmail() {
    var ids = '';
    var checkboxes = document.getElementsByTagName("input");
    for (var i = 0; i < checkboxes.length; i++) {
        if (checkboxes[i].type == "checkbox" && checkboxes[i].name == "selectitem" && checkboxes[i].checked) {
            ids += checkboxes[i].value + ',';
        }
    }
    if (ids == '') {
        return;
    }
    document.getElementById('ids').value = ids;
    $('#sendmailmodal').modal('show');
}
</script>
<div class="modal fade" id="sendmailmodal"  aria-labelledby="sendmailmodalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sendmailmodalLabel"><?php echo get_string('sendmailtoselected', 'paygw_bank'); ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <form name="formsendmail" method="POST">
                    <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                    <input type="hidden" name="action" value="sendmail">
                    <input type="hidden" name="confirm" value="1">
                    <input type="hidden" name="ids" id="ids" value="">
                    <div class="form-group">
                        <label for="subject"><?php echo get_string('subject'); ?></label>
                        <input type="text" class="form-control" id="subject" name="subject" value="<?php echo get_string('messegesubject', 'paygw_bank'); ?>" required>
                        <br>
                        <label for="message"><?php echo get_string('message'); ?></label>
                        <textarea class="form-textarea form-control" cols="40" rows="10" id="message" name="message" required></textarea>
                        <br>
                        <input type="submit" class="btn btn-primary" value="<?php echo get_string('send', 'paygw_bank'); ?>">
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
    <?php
}

echo $OUTPUT->footer();
