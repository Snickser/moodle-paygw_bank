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
//$PAGE->set_heading($pagetitle);
$PAGE->set_cacheable(false);

$cid = bank_helper::get_courseid($paymentarea, $component, $itemid);
$course = $DB->get_record('course', ['id' => $cid], '*', MUST_EXIST);
$PAGE->navbar->add($course->fullname, '/course/view.php?id='.$cid);
$PAGE->navbar->add(get_string('pluginname', 'paygw_bank'));

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
                    $timeend = strtotime("+$delta month", $data->timeend);
                } else if ($cs->customchar1 == 'year' && $cs->customint7 > 0) {
                    $delta = ($t2['year'] - $t1['year']) + 1;
                    $cost = $delta * $cost;
                    $timeend = strtotime("+$delta year", $data->timeend);
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

if (bank_helper::has_openbankentry($itemid, $USER->id)) {
    $bank_entry = bank_helper::get_openbankentry($itemid, $USER->id);
    $amount = $bank_entry->totalamount;
    $confirm = 0;


} else {
    if ($confirm != 0) {
        $totalamount = $amount;
///        $data = $mform->get_data();
        $bank_entry = bank_helper::create_bankentry($itemid, $USER->id, $totalamount, $currency, $component, $paymentarea, $description);
        \core\notification::info(get_string('transfer_process_initiated', 'paygw_bank'));
        $confirm = 0;
    }
}

// Check expired payment.
if (isset($cs->enrolperiod) && isset($data->timeend) && isset($bank_entry->totalamount)) {
    $timeend = $data->timeend + round($bank_entry->totalamount/(1+$surcharge/100), 2)/$cs->cost*$cs->enrolperiod;
    $timeend = round($timeend);
}

$unpaidnotice = false;
if(isset($timeend) && $timeend < time()) {
    $unpaidnotice = true;
}

echo '<div class="card">';
echo '<div class="card-body">';
echo '<ul class="list-group list-group-flush">';
echo '<li class="list-group-item"><h4 class="card-title">' . get_string('concept', 'paygw_bank') . ':</h4>';
echo '<div>' . $description . '</div>';
echo '</li>';

$aceptform = "";
$instructions = format_text($config->instructionstext['text']);

echo '<li class="list-group-item"><h4 class="card-title">' . get_string('total_cost', 'paygw_bank') . ':</h4>';
if ($surcharge > 0) {
    $a = ['fee' => helper::get_cost_as_string($amount, $currency), 'surcharge' => $surcharge];
    echo '<div id="price">' . get_string('feeincludesurcharge', 'payment', $a) . '</div>';
} else {
    echo '<div id="price">' .helper::get_cost_as_string($amount, $currency). ' </div>';
}
echo '</li>';

if ($bank_entry != null) {
    echo '<li class="list-group-item"><h4 class="card-title">' . get_string('transfer_code', 'paygw_bank') . ':</h4>';
    echo '<div id="transfercode">' . $bank_entry->code . '</div>';
    echo '</li>';

    $instructions = format_text($config->postinstructionstext['text']);

    if (isset($cs->customint5) && $cs->customint5) {
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

echo '<li class="list-group-item">';
echo '<div id="bankinstructions">' . $instructions . '</div>';
echo '</li>';
echo '</ul>';

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

                        if ($send_email) {
    			    $cid = bank_helper::get_courseid($bank_entry->paymentarea, $bank_entry->component, $bank_entry->itemid);
    			    $groups = bank_helper::get_course_usergroups($cid, $bank_entry->userid);

                            $contentmessage = new stdClass;
                            $contentmessage->code = $bank_entry->code;
                            $contentmessage->concept = $bank_entry->description;
                            $contentmessage->useremail = $USER->email;
                            $contentmessage->userfullname = fullname($USER);
		            $contentmessage->url = new moodle_url('/payment/gateway/bank/manage.php', ['cid' => $cid, 'id' => $bank_entry->id]);
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
        		    $teachers = get_enrolled_users($context,'paygw/bank:manageincourse');
        		    foreach ($teachers as $teacher){
        		        $flag = false;
    	        if ($config->onlyingroup) {
		    $tgs = bank_helper::get_course_usergroups($cid, $teacher->id);
		    foreach (explode(',', $tgs) as $tg) {
			foreach (explode(',', $groups) as $g) {
			    if ($tg == $g) {
				$flag = true;
				break;
			    }
			}
		    }
		    if (!$flag) {
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
    $url = new moodle_url('/payment/gateway/bank/my_pending_pay.php');
    echo $OUTPUT->single_button($url, get_string('continue'));
} else {
    $url = new moodle_url('/course/view.php', ['id' => $cid]);
    echo $OUTPUT->single_button($url, get_string('cancel'));
}
echo '</div>';
echo $OUTPUT->footer();
