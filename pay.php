<?php

use core_payment\helper;
use paygw_bank\bank_helper;
use paygw_bank\pay_form;
use paygw_bank\attachtransfer_form;

require_once __DIR__ . '/../../../config.php';
require_once './lib.php';
$canuploadfiles = get_config('paygw_bank', 'usercanuploadfiles');
$maxnumberfiles = get_config('paygw_bank', 'maxnumberfiles');
if(!$maxnumberfiles) {
    $maxnumberfiles=3;
}
require_login();
$context = context_system::instance(); // Because we "have no scope".
$PAGE->set_context($context);
$component = required_param('component', PARAM_COMPONENT);
$paymentarea = required_param('paymentarea', PARAM_AREA);
$itemid = required_param('itemid', PARAM_INT);
$description = required_param('description', PARAM_TEXT);
$description=json_decode('"'.$description.'"');
$params = [
    'component' => $component,
    'paymentarea' => $paymentarea,
    'itemid' => $itemid,
    'description' => $description
];
$mform = new pay_form(null, array('confirm' => 1, 'component' => $component, 'paymentarea' => $paymentarea, 'itemid' => $itemid, 'description' => $description));
$mform->set_data($params);
$at_form = new attachtransfer_form();
$at_form->set_data($params);
$dataform = $mform->get_data();
$at_dataform = $at_form->get_data();
$confirm = 0;
if ($dataform != null) {
    $component = $dataform->component;
    $paymentarea = $dataform->paymentarea;
    $itemid = $dataform->itemid;
    $description = $dataform->description;
    $confirm = $dataform->confirm;
}
if ($at_dataform != null) {
    $component = $at_dataform->component;
    $paymentarea = $at_dataform->paymentarea;
    $itemid = $at_dataform->itemid;
    $description = $at_dataform->description;
    $confirm = $at_dataform->confirm;
}

$context = context_system::instance(); // Because we "have no scope".
$PAGE->set_context($context);

$PAGE->set_url('/payment/gateway/bank/pay.php', $params);
$PAGE->set_pagelayout('standard');
$pagetitle = $description;
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);
$PAGE->set_cacheable(false);

$config = (object) helper::get_gateway_configuration($component, $paymentarea, $itemid, 'bank');
$payable = helper::get_payable($component, $paymentarea, $itemid);
$currency = $payable->get_currency();
$bank_entry = null;

// Add support for enrol_yafee.
$cost = $payable->get_amount();
if ($component == "enrol_yafee") {
    $cs = $DB->get_record('enrol', ['id' => $itemid, 'enrol' => 'yafee']);
    // Check uninterrupted cost.
    if ($cs->customint5) {
        if ($data = $DB->get_record('user_enrolments', ['userid' => $USER->id, 'enrolid' => $cs->id])) {
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
                if ($cs->enrolperiod) {
                    $price = $cost / $cs->enrolperiod;
                    $delta = ceil((($ctime - $data->timestart) / $cs->enrolperiod)+0) * $cs->enrolperiod +
                             $data->timestart - $data->timeend;
                    $cost = $delta * $price;
                } else if ($cs->customchar1 == 'month' && $cs->customint7 > 0) {
                    $delta = ($t2['year'] - $t1['year']) * 12 + $t2['mon'] - $t1['mon'] + 1;
                    $cost = $delta * $cost;
                } else if ($cs->customchar1 == 'year' && $cs->customint7 > 0) {
                    $delta = ($t2['year'] - $t1['year']) + 1;
                    $cost = $delta * $cost;
                }
            }
        }
    }
}

// Add surcharge if there is any.
$surcharge = helper::get_gateway_surcharge('bank');
$amount = helper::get_rounded_cost($cost, $currency, $surcharge);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('gatewayname', 'paygw_bank'), 2);
echo '<div class="card">';
echo '<div class="card-body">';
echo '<ul class="list-group list-group-flush">';
echo '<li class="list-group-item"><h5 class="card-title">' . get_string('concept', 'paygw_bank') . ':</h5>';
echo '<div>' . $description . '</div>';
echo "</li>";
$aceptform = "";
$instructions = "";

$instructions = format_text($config->instructionstext['text']);

if (bank_helper::has_openbankentry($itemid, $USER->id)) {
    $bank_entry = bank_helper::get_openbankentry($itemid, $USER->id);
    $amount = $bank_entry->totalamount;
    $confirm = 0;
} else {
    if ($confirm != 0) {
        $totalamount = $amount;
        $data = $mform->get_data();
        $bank_entry = bank_helper::create_bankentry($itemid, $USER->id, $totalamount, $currency, $component, $paymentarea, $description);
        \core\notification::info(get_string('transfer_process_initiated', 'paygw_bank'));
        $confirm = 0;
    }
}

if ($surcharge && $surcharge > 0 && $bank_entry == null) {
    echo '<li class="list-group-item"><h4 class="card-title">' . get_string('cost', 'paygw_bank') . ':</h4>';
    echo '<div id="price">' . helper::get_cost_as_string($payable->get_amount(), $currency) . '</div>';
    echo '</li>';
    echo '<li class="list-group-item"><h4 class="card-title">' . get_string('surcharge', 'core_payment') . ':</h4>';
    echo '<div id="price">' . $surcharge. '%</div>';
    echo '<div id="explanation">' . get_string('surcharge_desc', 'core_payment') . '</div>';
    echo '</li>';
    
    echo '<li class="list-group-item"><h4 class="card-title">' . get_string('total_cost', 'paygw_bank') . ':</h4>';
    echo '<div id="price">' .helper::get_cost_as_string($amount, $currency). ' </div>';
    echo '</li>';
} else {
    echo '<li class="list-group-item"><h4 class="card-title">' . get_string('total_cost', 'paygw_bank') . ':</h4>';
    echo '<div id="price">' . helper::get_cost_as_string($amount, $currency). '</div>';
    echo '</li>';
}
if ($bank_entry != null) {
    echo '<li class="list-group-item"><h4 class="card-title">' . get_string('transfer_code', 'paygw_bank') . ':</h4>';
    echo '<div id="transfercode">' . $bank_entry->code . '</div>';
    echo '</li>';
    $instructions = format_text($config->postinstructionstext['text']);
}
echo "</ul>";
echo '<div id="bankinstructions">' . $instructions . '</div>';
if ($confirm == 0 && !bank_helper::has_openbankentry($itemid, $USER->id)) {
    $mform->display();
} else {
    if ($canuploadfiles) {
        if ($at_form != null) {
            $content = $at_form->get_file_content('userfile');

            $name = $at_form->get_new_filename('userfile');
            if ($name) {
                
                $fs = get_file_storage();
                $isalreadyuplooaded=false;
                $files=bank_helper::files($bank_entry->id);
                if(count($files)>=$maxnumberfiles) {
                    \core\notification::error(get_string('max_number_of_files_reached', 'paygw_bank'));
                }
                else
                {
                    foreach ($files as $f) {
                        
                        $filename= $f->get_filename();
                        if($name==$filename) {
                            $isalreadyuplooaded=true;
                        }
                    }
                    if($isalreadyuplooaded) {
                        \core\notification::warning(get_string('file_already_uploaded', 'paygw_bank'));
                    }
                    else
                    {
                        $tempdir = make_request_directory();
                        $fullpath = $tempdir . '/' . $name;
                        $override = false; // Define $override variable
                        $success = $at_form->save_file('userfile', $fullpath, $override);
                        $fileinfo = array(
                            'contextid' => context_system::instance()->id,
                            'component' => 'paygw_bank',
                            'filearea' => 'transfer',
                            'filepath' => '/',
                            'filename' =>  $name,
                            'itemid' => $bank_entry->id,
                            'userid' => $USER->id,
                            'author' => fullname($USER)
                        );
                        $fs->create_file_from_pathname($fileinfo, $fullpath);
                        bank_helper::check_hasfiles($bank_entry->id);
                        $send_email = get_config('paygw_bank', 'sendnewattachmentsmail');
                        $emailaddress = get_config('paygw_bank', 'notificationsaddress');
                        $sendteachermail = get_config('paygw_bank', 'sendteachermail');

			$cid = bank_helper::get_courseid($bank_entry->paymentarea, $bank_entry->component, $bank_entry->itemid);

                        if ($send_email) {
                            $supportuser = core_user::get_support_user();
                            $subject = get_string('email_notifications_subject_attachments', 'paygw_bank');
                            $contentmessage = new stdClass;
                            $contentmessage->code = $bank_entry->code;
                            $contentmessage->concept = $bank_entry->description;
                            $contentmessage->useremail = $USER->email;
                            $contentmessage->userfullname = fullname($USER);
		            $contentmessage->url = new moodle_url('/payment/gateway/bank/manage.php', ['cid' => $cid]);
			    $contentmessage->course = format_string($DB->get_field('course', 'fullname', ['id' => $cid])); 
                            $mailcontent = get_string('email_notifications_new_attachments', 'paygw_bank', $contentmessage);
if ($emailaddress) {
                            $emailuser = new stdClass();
                            $emailuser->email = $emailaddress;
                            $emailuser->id = -99;
                            email_to_user($emailuser, $supportuser, $subject, $mailcontent);
}
if ($sendteachermail) {
            $context = \context_course::instance($cid, MUST_EXIST);
            bank_helper::message_to_teachers($context, $supportuser, $subject, $mailcontent);
}


                        }
                        \core\notification::info(get_string('file_uploaded', 'paygw_bank'));
                    }
                }
            }
        }
        $files = bank_helper::files($bank_entry->id);
        if(count($files)>0) {
            echo '<h3>'.get_string('files').':</h3>';
            echo '<ul class="list-group">';
            foreach ($files as $f) {
                $hasfiles=true;
                // $f is an instance of stored_file
                echo '<li class="list-group-item">';
               
                $url = moodle_url::make_pluginfile_url($f->get_contextid(), $f->get_component(), $f->get_filearea(), $f->get_itemid(), $f->get_filepath(), $f->get_filename(), false);
                if (str_ends_with($f->get_filename(), ".png")|| str_ends_with($f->get_filename(), ".jpeg") || str_ends_with($f->get_filename(), ".jpg")|| str_ends_with($f->get_filename(), ".svg") || str_ends_with($f->get_filename(), ".gif")) {           
               
                    echo $f->get_filename();
                    echo "<br><img style='max-height:100px' src='".$url."'>";
                }
                else
                {
                    echo $f->get_filename();
                }
                echo '</li>';
            }
            echo "</ul>";
                
        }
        if(count($files)<$maxnumberfiles) {
            $at_form->display();
        }

    }
}
echo "</div>";
echo "</div>";
echo '<br><div align=center>';
if ($bank_entry) {
    echo $OUTPUT->single_button('/payment/gateway/bank/my_pending_pay.php',get_string('continue'));
} else {
    echo $OUTPUT->single_button('/',get_string('cancel'));
}
echo '</div>';
echo $OUTPUT->footer();
