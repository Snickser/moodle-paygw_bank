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
use paygw_bank\pay_form;
use paygw_bank\attachtransfer_form;

require_once __DIR__ . '/../../../config.php';
require_once './lib.php';

defined('MOODLE_INTERNAL') || die();

$canuploadfiles = get_config('paygw_bank', 'usercanuploadfiles');
$maxnumberfiles = get_config('paygw_bank', 'maxnumberfiles');
if (!$maxnumberfiles) {
    $maxnumberfiles = 3;
}

require_login();
require_sesskey();

$context = context_system::instance(); // Because we "have no scope".
$PAGE->set_context($context);

$component = required_param('component', PARAM_COMPONENT);
$paymentarea = required_param('paymentarea', PARAM_AREA);
$itemid = required_param('itemid', PARAM_INT);
$description = required_param('description', PARAM_TEXT);
$description = json_decode('"' . $description . '"');

$costself = optional_param('costself', 0, PARAM_FLOAT);
$editfiles = optional_param('editfiles', 0, PARAM_INT);

$params = [
    'sesskey' => sesskey(),
    'component' => $component,
    'paymentarea' => $paymentarea,
    'itemid' => $itemid,
    'description' => $description,
    'editfiles' => $editfiles,
];

$PAGE->set_url(new moodle_url('/payment/gateway/bank/pay.php', $params));
$PAGE->set_title(format_string(get_string('pluginname', 'paygw_bank')));
// $PAGE->set_heading($description);
$PAGE->set_cacheable(false);
$PAGE->set_pagelayout('standard');

$mform = new pay_form(null, ['confirm' => 1, 'component' => $component, 'paymentarea' => $paymentarea, 'itemid' => $itemid, 'description' => $description]);
$mform->set_data($params);
$atform = new attachtransfer_form();
$atform->set_data($params);
$dataform = $mform->get_data();
$atdataform = $atform->get_data();
$confirm = 0;

if ($dataform != null) {
    $component = $dataform->component;
    $paymentarea = $dataform->paymentarea;
    $itemid = $dataform->itemid;
    $description = $dataform->description;
    $confirm = $dataform->confirm;
}
if ($atdataform != null) {
    $component = $atdataform->component;
    $paymentarea = $atdataform->paymentarea;
    $itemid = $atdataform->itemid;
    $description = $atdataform->description;
    $confirm = $atdataform->confirm;
}

$cid = bank_helper::get_courseid($paymentarea, $component, $itemid);
$course = $DB->get_record('course', ['id' => $cid], '*', MUST_EXIST);
$groups = bank_helper::get_course_usergroups($cid, $USER->id);

$PAGE->navbar->add($course->fullname, '/course/view.php?id=' . $cid);
$PAGE->navbar->add(get_string('pluginname', 'paygw_bank'));

$config = (object) helper::get_gateway_configuration($component, $paymentarea, $itemid, 'bank');
$payable = helper::get_payable($component, $paymentarea, $itemid);
$currency = $payable->get_currency();
$bankentry = null;

// Set default.
if (!isset($config->autocommit)) {
    $config->autocommit = false;
}

// if (!$config->unfixcost) {
// $PAGE->set_periodic_refresh_delay(180);
// }

$cost = $payable->get_amount();

// Add surcharge if there is any.
$surcharge = helper::get_gateway_surcharge('bank');
$amount = helper::get_rounded_cost($cost, $currency, $surcharge);

// Check suggest.
if (isset($config->suggest) && $config->suggest && $cost < $config->suggest) {
    $amount = helper::get_rounded_cost($config->suggest, $currency, $surcharge);
}
// Check maxcost.
if (isset($config->maxcost) && $config->maxcost && $cost > $config->maxcost) {
    $amount = helper::get_rounded_cost($config->maxcost, $currency, $surcharge);
}

// Set fixdesc.
if (isset($config->fixdesc) && $config->fixdesc) {
    $description = $config->fixdesc;
}

// Add support for enrol_yafee.
$uninterrupted = false;
if ($component == "enrol_yafee") {
    $cs = $DB->get_record('enrol', ['id' => $itemid, 'enrol' => 'yafee']);
    if ($cs->customint5) {
        if ($data = $DB->get_record('user_enrolments', ['userid' => $USER->id, 'enrolid' => $cs->id])) {
            $uninterrupted = true;
            // Prepare month and year.
            $ctime = time();
            $timeend = $ctime;
            if (isset($data->timeend)) {
                $timeend = $data->timeend;
            }
            $t1 = getdate($timeend);
            $t2 = getdate($ctime);
            // Check periods.
            if ($data->timeend < $ctime && $data->timestart) {
                if ($cs->customchar1 == 'month' && $cs->customint7 > 0) {
                    $delta = ($t2['year'] - $t1['year']) * 12 + $t2['mon'] - $t1['mon'] + 1;
                    $timeend = strtotime("+$delta month", $data->timeend);
                } else if ($cs->customchar1 == 'year' && $cs->customint7 > 0) {
                    $delta = ($t2['year'] - $t1['year']) + 1;
                    $timeend = strtotime("+$delta year", $data->timeend);
                }
            }
        }
    }
}


echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('gatewayname', 'paygw_bank'), 2);

if (bank_helper::has_openbankentry($itemid, $USER->id)) {
    $bankentry = bank_helper::get_openbankentry($itemid, $USER->id);
    $amount = $bankentry->totalamount;
    $confirm = 0;
} else {
    if ($confirm != 0) {
        $totalamount = $amount;
        if ($costself && isset($config->unfixcost) && $config->unfixcost) {
            $totalamount = $costself;
            $amount = $costself;
        }
        $bankentry = bank_helper::create_bankentry($itemid, $USER->id, $totalamount, $currency, $component, $paymentarea, $description);
        \core\notification::info(get_string('transfer_process_initiated', 'paygw_bank'));
        $confirm = 0;
    }
}

// Check expired payment.
if (isset($cs->enrolperiod) && isset($data->timeend) && isset($bankentry->totalamount)) {
    $timeend = $data->timeend + round($bankentry->totalamount / (1 + $surcharge / 100), 2) / $cs->cost * $cs->enrolperiod;
    $timeend = round($timeend);
}

$unpaidnotice = false;
if (isset($timeend) && $timeend < time()) {
    $unpaidnotice = true;
}

echo '<div class="card">';
echo '<div class="card-body">';

if ($bankentry != null) {
    $instructions = format_text($config->postinstructionstext['text']);
    echo '<div class="ml-2 mr-2" id="bankinstructions">' . $instructions . '</div>';
    echo '<ul class="list-group">';
} else {
    echo '<ul class="list-group list-group-flush">';
}

echo '<li class="list-group-item"><h4 class="card-title">' . get_string('concept', 'paygw_bank') . '</h4>';
echo '<div>' . $description . '</div>';
echo '</li>';

$aceptform = "";

echo '<li class="list-group-item"><h4 class="card-title">' . get_string('amount', 'paygw_bank') . '</h4>';

if (isset($config->unfixcost) && $config->unfixcost && $bankentry == null) {
    if ($uninterrupted) {
        $config->suggest = $amount;
    } else {
        $config->suggest = $cost;
    }

    echo '<input class="form-control" type="number" id="inputcostself"
 value="' . $amount . '" min="' . $config->suggest . '" max="' . $config->maxcost . '" step="0.01"
 style="width: 9em;">';
} else {
    if ($surcharge > 0) {
        $a = ['fee' => helper::get_cost_as_string($amount, $currency), 'surcharge' => $surcharge];
        echo '<div id="price">' . get_string('feeincludesurcharge', 'payment', $a) . '</div>';
    } else {
        echo '<div id="price">' . helper::get_cost_as_string($amount, $currency) . ' </div>';
    }
    echo '</li>';
}

if ($bankentry != null) {
    echo '<li class="list-group-item"><h4 class="card-title">' . get_string('transfer_code', 'paygw_bank') . ':</h4>';
    echo '<div id="transfercode">' . $bankentry->code . '</div>';
    echo '</li>';

    if (isset($cs->customint5) && $cs->customint5 && isset($timeend)) {
        echo '<li class="list-group-item"><h4 class="card-title">' . get_string('unpaidtimeend', 'paygw_bank') . ':</h4>';
        echo '<div id="transfercode">';
        echo userdate($timeend, get_string('strftimedate', 'core_langconfig')) . ' ' . date('H:i', $timeend);
        if ($unpaidnotice) {
            echo '<h5><font color=red>' . get_string('unpaidnotice', 'paygw_bank') . '</font></h5>';
        }
        echo '</div>';
        echo '</li>';
    }
}

if ($bankentry == null) {
    $instructions = format_text($config->instructionstext['text']);
    echo '<li class="list-group-item">';
    echo '<div id="bankinstructions">' . $instructions . '</div>';
    echo '</li>';
}

echo '</ul><br><div class="ml-2 mr-2">';

if ($confirm == 0 && !bank_helper::has_openbankentry($itemid, $USER->id)) {
    $mform->display();

    if (isset($config->unfixcost) && $config->unfixcost) {
        ?>
<script>
const inputcostself = document.querySelector('#inputcostself');
const costself = document.querySelector('input[name=costself]');
costself.value = Number(inputcostself.value);
inputcostself.addEventListener('input', function() {
        <?php
        if ($config->maxcost) {
            echo "
    if(inputcostself.value > $config->maxcost){
	inputcostself.value = $config->maxcost;
    }";
        }
        ?>
    if(inputcostself.value < 0.01){
    inputcostself.value = 0.01;
    }
    costself.value = Number(inputcostself.value);
});
</script>
        <?php
    }
} else {
    if ($canuploadfiles) {
        if ($atform != null) {
            $isuploaded = false;

            $fs = get_file_storage();
            $files = $fs->get_area_files(context_system::instance()->id, 'paygw_bank', 'transfer', $bankentry->id);

            if ((count($files) - 1) <= 0 || $editfiles) {
                $draftitemid = file_get_submitted_draft_itemid('userfile');

                if (isset($bankentry->hasfiles) && $bankentry->hasfiles) {
                    file_prepare_draft_area(
                        $draftitemid,
                        context_system::instance()->id,
                        'paygw_bank',
                        'transfer',
                        $bankentry->id,
                        ['subdirs' => 0, 'maxfiles' => $maxnumberfiles]
                    );
                    $atform->userfile = $draftitemid;
                    $atform->set_data($atform);
                }

                file_save_draft_area_files(
                    $draftitemid,
                    context_system::instance()->id,
                    'paygw_bank',
                    'transfer',
                    $bankentry->id,
                    ['subdirs' => 0]
                );

                $files = $fs->get_area_files(context_system::instance()->id, 'paygw_bank', 'transfer', $bankentry->id);

                if ((count($files) - 1) > 0) {
                    $isuploaded = true;
                    $i = 0;
                    foreach ($files as $f) {
                        if ($f->get_filename() === '.') {
                            continue;
                        }
                        $i++;
                        $ext = pathinfo($f->get_filename())['extension'];
                        $newname = format_string($course->shortname) . " - $groups - " . $bankentry->id . " - file" .
                        sprintf("%02d", $i) . " - " . bin2hex(random_bytes(5)) . "." . $ext;
                        if ($f->get_filename() != $newname) {
                                $f->rename('/', $newname);
                        }
                    }

                    bank_helper::check_hasfiles($bankentry->id);
                } else {
                    $DB->update_record('paygw_bank', ['id' => $bankentry->id, 'hasfiles' => 0]);
                }
            }

            if ($isuploaded && ($editfiles == 0 || $editfiles == 2)) {
                        $sendemail = $config->sendnewattachmentsmail;
                        $emailaddress = get_config('paygw_bank', 'notificationsaddress');
                        $sendteachermail = $config->sendteachermail;

                if ($sendemail) {
                    $cid = bank_helper::get_courseid($bankentry->paymentarea, $bankentry->component, $bankentry->itemid);
                    $groups = bank_helper::get_course_usergroups($cid, $bankentry->userid);

                    $contentmessage = new stdClass();
                    $contentmessage->code = $bankentry->code;
                    $contentmessage->concept = $bankentry->description;
                    $contentmessage->useremail = $USER->email;
                    $contentmessage->userfullname = fullname($USER);
                    $contentmessage->url = new moodle_url('/payment/gateway/bank/manage.php', ['cid' => $cid, 'id' => $bankentry->id]);
                    $contentmessage->groups = $groups;
                    if ($emailaddress) {
                            $supportuser = core_user::get_support_user();
                            $subject = get_string('email_notifications_subject_attachments', 'paygw_bank');
                                    $contentmessage->course = format_string($DB->get_field('course', 'fullname', ['id' => $cid]));
                            $mailcontent = get_string('email_notifications_new_attachments', 'paygw_bank', $contentmessage);
                            $emailuser = new stdClass();
                            $emailuser->email = $emailaddress;
                            $emailuser->id = -99;
                            email_to_user($emailuser, $supportuser, $subject, $mailcontent);
                    }
                    if ($sendteachermail) {
                                        $context = \context_course::instance($cid, MUST_EXIST);
                                        $teachers = get_enrolled_users($context, 'paygw/bank:manageincourse');
                        foreach ($teachers as $teacher) {
                            if ($config->onlyingroup) {
                                if (!bank_helper::check_teacheringroup($cid, $teacher->id, $groups)) {
                                    continue;
                                }
                            }

                            $oldforcelang = force_current_language($teacher->lang);
                            $supportuser = core_user::get_support_user();
                            $subject = get_string('email_notifications_subject_attachments', 'paygw_bank');
                            $contentmessage->course = format_string($DB->get_field('course', 'fullname', ['id' => $cid]));
                            $mailcontent = get_string('email_notifications_new_attachments', 'paygw_bank', $contentmessage);
                            bank_helper::message_to_user($teacher->id, $supportuser, $subject, $mailcontent);
                            force_current_language($oldforcelang);
                        }
                    }
                }
                        \core\notification::info(get_string('file_uploaded', 'paygw_bank'));
            }
        }

        if (count($files)) {
            /*
            if ($config->autocommit) {
            $url = helper::get_success_url($component, $paymentarea, $itemid);
            echo '<h3>'.get_string('autocommittext', 'paygw_bank').'</h3><br>';
            bank_helper::aprobe_pay($bank_entry->id);
            echo $OUTPUT->single_button($url, get_string('continue'), 'get', ['type' => 'primary']);
            echo "
            <script>
            var timer = setTimeout(function() {
            window.location='$url'
            }, 300000);
            </script>
            ";
            echo $OUTPUT->footer();
            die; // End.
            }
            */
            echo '<h5>' . get_string('files') . ':</h5>';
            echo '<ul class="list-group mb-1">';
            $i = 0;
            foreach ($files as $f) {
                if ($f->get_filename() === '.') {
                            continue;
                }
                $i++;
                $hasfiles = true;
                // $f is an instance of stored_file
                echo '<li class="list-group-item">';
                $url = moodle_url::make_pluginfile_url($f->get_contextid(), $f->get_component(), $f->get_filearea(), $f->get_itemid(), $f->get_filepath(), $f->get_filename(), false);
                if (str_ends_with($f->get_filename(), ".png") || str_ends_with($f->get_filename(), ".jpeg") || str_ends_with($f->get_filename(), ".jpg") || str_ends_with($f->get_filename(), ".gif")) {
                    echo $i . ". <img style='max-height:100px' src='" . $url . "'><br>";
                } else {
                    echo $i . '. ' . $f->get_mimetype();
                }
                echo '</li>';
            }
            echo '</ul><br>';
        }

        if ((count($files) - 1) <= 0 || $editfiles == 1) {
            if ($editfiles) {
                $atform->editfiles = 2;
                $atform->set_data($atform);
            }
            $atform->display();
        } else {
            $params['editfiles'] = 1;
            $url = new moodle_url('/payment/gateway/bank/pay.php', $params);
            echo $OUTPUT->single_button($url, get_string('editfiles'), 'post', ['type' => 'primary']);
        }
    }
}
echo "</div>";
echo "</div>";
echo "</div><br>";

if ($bankentry) {
    echo '<div align=center>';
    $url = new moodle_url('/payment/gateway/bank/my_pending_pay.php');
    echo $OUTPUT->single_button($url, get_string('continue'));
} else {
    echo '<div align=right>';
    $url = new moodle_url('/course/view.php', ['id' => $cid]);
    echo $OUTPUT->single_button($url, get_string('cancel'));
}
echo '</div>';

echo $OUTPUT->footer();
