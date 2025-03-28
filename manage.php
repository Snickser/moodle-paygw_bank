<?php

use core_payment\helper;
use core_reportbuilder\external\columns\sort\get;
use gwpayiments\bank_helper as GwpayimentsBank_helper;
use paygw_bank\bank_helper as Paygw_bankBank_helper;
use paygw_bank\bank_helper;

require_once __DIR__ . '/../../../config.php';
require_once './lib.php';
global $CFG, $USER, $DB;

require_login();

$confirm = optional_param('confirm', 0, PARAM_INT);
$id      = optional_param('id', 0, PARAM_INT);
$ids     = optional_param('ids', '', PARAM_TEXT);
$filter  = optional_param('filter', '', PARAM_TEXT);
$action  = optional_param('action', '', PARAM_TEXT);

$cid = optional_param('cid', 0, PARAM_INT);

$course = null;
if ($cid) {
    $course = $DB->get_record('course', ['id' => $cid], '*', MUST_EXIST);
    $context = context_course::instance($course->id, MUST_EXIST);
    require_capability('paygw/bank:manageincourse', $context, $USER->id);
} else {
    $context = context_system::instance(); // Because we "have no scope".
    require_capability('paygw/bank:managepayments', $context);
}

$PAGE->set_context($context);
$PAGE->set_url('/payment/gateway/bank/manage.php');
$PAGE->set_pagelayout('standard');
$pagetitle = get_string('manage', 'paygw_bank');
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);
$PAGE->set_cacheable(false);
$PAGE->set_secondary_navigation(false);
if ($cid) {
    $PAGE->navbar->add($course->fullname, '/course/view.php?id='.$course->id);
}
$PAGE->navbar->add(get_string('pluginname', 'paygw_bank'));

echo $OUTPUT->header();

$items = bank_helper::get_pending_item_collections($cid);

echo '<form name="filteritem" method="post" action="/payment/gateway/bank/manage.php">';
echo '<input type="hidden" name="cid" value="'.$cid.'">';
echo '<select class="custom-select" name="filter" id="filterkey">';
echo '<option value="">'.get_string('pendingrequests', 'paygw_bank').'</option>';
foreach ($items as $item) {
    echo '<option value="' . $item['key'] . '" >' . $item['description'] . '</option>';
}
echo '<option value="showarchived">'.get_string('group:archive', 'mimetypes').'</option>';
echo '</select>
&nbsp;<input type="submit" class="btn btn-primary" value="' . get_string('show') . '">
</form></br>';

echo $OUTPUT->heading(get_string('pending_payments', 'paygw_bank'), 2);
if ($confirm == 1 && $id > 0) {
    require_sesskey();
    // Check what has already been aprobed.
    if ( $DB->record_exists('paygw_bank', ['id' => $id, 'status' => 'P']) ){
     if ($action == 'A') {
        bank_helper::aprobe_pay($id);
        $OUTPUT->notification("aprobed");
        \core\notification::info(get_string('mail_confirm_pay_subject', 'paygw_bank'));
     } else if ($action == 'D') {
        bank_helper::deny_pay($id);
        $OUTPUT->notification("denied");
        \core\notification::info(get_string('mail_denied_pay_subject', 'paygw_bank'));
     }
    } else {
        \core\notification::info("Reloaded");
    }
    $id = 0;
}
if ($confirm==1 && $ids!='' && $action=='sendmail') {
    require_sesskey();
    $ids=explode(',', $ids);
    foreach ($ids as $i) {
        if ($i>0) {
            bank_helper::sendmail($i, optional_param('subject', '', PARAM_TEXT), optional_param('message', '', PARAM_TEXT));
        }
    }
    \core\notification::info(get_string('mails_sent', 'paygw_bank'));
    $OUTPUT->notification(get_string('mails_sent', 'paygw_bank'));
}
$post_url= new moodle_url($PAGE->url, array('sesskey'=>sesskey()));

$status = 'P';
if ($filter == 'showarchived') {
    $status = 'A';
}

$bank_entries = bank_helper::get_pending($status, $id);

if (!$bank_entries) {
    $match = array();
    echo '</br><h5>'.(get_string('noentriesfound', 'paygw_bank')).'</h5>';
    $table = null;
} else {
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
    array_push($table->head,
	$checkboxcheckall,
        get_string('timecreated'), get_string('code', 'paygw_bank'),
    );

    if(!$cid) {
	array_push($table->head,
    	    get_string('course'),
	);
    }

    array_push($table->head,
        get_string('fullnameuser'),
        get_string('email'),
        get_string('group'),
        get_string('concept', 'paygw_bank'),
        get_string('total_cost', 'paygw_bank'), get_string('today_cost', 'paygw_bank'), get_string('currency'), get_string('hasfiles', 'paygw_bank'),
    );

    if($filter != 'showarchived') {
	array_push($table->head,
    	    get_string('actions')
	);
    }

    foreach ($bank_entries as $bank_entry) {
        $bankentrykey = bank_helper::get_item_key($bank_entry->component, $bank_entry->paymentarea, $bank_entry->itemid);

        if ($filter != '' && ($bankentrykey != $filter)) {
    	    if ($filter != 'showarchived' || !$bank_entry->hasfiles) {
        	continue;
            }
        }

	// Check in course.
	if (!bank_helper::check_in_course($cid, $bank_entry->paymentarea, $bank_entry->component, $bank_entry->itemid)) {
	    continue;
	}

        $config = (object) helper::get_gateway_configuration($bank_entry->component, $bank_entry->paymentarea, $bank_entry->itemid, 'bank');
        $payable = helper::get_payable($bank_entry->component, $bank_entry->paymentarea, $bank_entry->itemid);
        $currency = $payable->get_currency();
        $customer = $DB->get_record('user', array('id' => $bank_entry->userid));
        $fullname = fullname($customer, false);

        $amount = helper::get_rounded_cost($bank_entry->totalamount, $currency, 0);
        $surcharge = helper::get_gateway_surcharge('bank');

$unpaid = '-';
$primary = 'primary';
// Check uninterrupted cost.
if ($bank_entry->component == "enrol_yafee") {
    $cs = $DB->get_record('enrol', ['id' => $bank_entry->itemid, 'enrol' => 'yafee']);
        if ($data = $DB->get_record('user_enrolments', ['userid' => $bank_entry->userid, 'enrolid' => $cs->id])) {
         if (isset($data->timeend) || isset($data->timestart)) {
            if ($cs->customint5 && $cs->enrolperiod && $data->timeend < time() && $data->timestart) {
                $unpaid = (round(((time() - $data->timeend) / $cs->enrolperiod)) * $cs->cost);
                // Add surcharge.
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

if (!$bank_entry->hasfiles) {
    $primary = 'secondary';
}

$buttonaprobe = '';
$buttondeny = '';
if($filter != 'showarchived') {
        $buttonaprobe = '<form name="formapprovepay' . $bank_entry->id . '" method="POST">
        <input type="hidden" name="sesskey" value="' .sesskey(). '">
        <input type="hidden" name="id" value="' . $bank_entry->id . '">
        <input type="hidden" name="action" value="A">
        <input type="hidden" name="confirm" value="1">
        <input class="btn btn-'.$primary.' form-submit" type="submit" value="' . get_string('approve', 'paygw_bank') . '"></input>
        </form>';
        $buttondeny = '<form name="formaprovepay' . $bank_entry->id . '" method="POST">
        <input type="hidden" name="sesskey" value="' .sesskey(). '">
        <input type="hidden" name="id" value="' . $bank_entry->id . '">
        <input type="hidden" name="action" value="D">
        <input type="hidden" name="confirm" value="1">
        <input class="btn btn-danger mt-2 form-submit" type="submit" value="' . get_string('deny', 'paygw_bank') . '"></input>
        </form>';
}
        $files = "-";
        $selectitemcheckbox = '<input type="checkbox" name="selectitem" value="' . $bank_entry->id . '">';
        $hasfiles = get_string('no');
        $fs = get_file_storage();
        $files = bank_helper::files($bank_entry->id);
        if ($bank_entry->hasfiles > 0 || count($files)>0) {
            $hasfiles = get_string('yes');
            $hasfiles = '<button type="button" class="btn btn-primary" data-toggle="modal" data-target="#staticBackdrop' . $bank_entry->id . '" id="launchmodal' . $bank_entry->id . '">
            '. get_string('view') .'
          </button>
            <div class="modal fade" id="staticBackdrop' . $bank_entry->id . '" aria-labelledby="staticBackdropLabel' . $bank_entry->id . '" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="staticBackdropLabel' . $bank_entry->id . '">' . get_string('files') . '</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
              ';
            foreach ($files as $f) {
                // $f is an instance of stored_file
                $url = moodle_url::make_pluginfile_url($f->get_contextid(), $f->get_component(), $f->get_filearea(), $f->get_itemid(), $f->get_filepath(), $f->get_filename(), false);
                if (str_ends_with($f->get_filename(), ".png") || str_ends_with($f->get_filename(), ".jpg") || str_ends_with($f->get_filename(), ".gif")) {
                    $hasfiles .= "<img src='$url'><br>";
                } else {
                    $hasfiles .= '<a href="' . $url . '" target="_blank">.....' . $f->get_filename() . '</a><br>';
                }
            }
            $hasfiles .= '
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
                </div>
            </div>
            </div>
            ';
        }

	$url = helper::get_success_url($bank_entry->component, $bank_entry->paymentarea, $bank_entry->itemid);

	$tabledata = [];
	array_push($tabledata,
    	    $selectitemcheckbox,
            date('d.m.Y, H:i', $bank_entry->timecreated), $bank_entry->code,
        );

$groupnames = null;
if(!$cid) {
    $courseid = bank_helper::get_courseid($bank_entry->paymentarea, $bank_entry->component, $bank_entry->itemid);
    $groupnames = bank_helper::get_course_usergroups($courseid, $bank_entry->userid);
    $course = get_course($courseid);
//    $courseurl = html_writer::link('/course/view.php?id='.$courseid, format_string($course->fullname), array('target' => '_blank'));
    $courseurl = format_string($course->fullname);
    array_push($tabledata, $courseurl);
} else {
    $groupnames = bank_helper::get_course_usergroups($cid, $bank_entry->userid);
}

	array_push($tabledata,
	    html_writer::link('/user/profile.php?id='.$customer->id, $fullname, array('target' => '_blank')),
            $customer->email,
    	    $groupnames,
            html_writer::link($url, $bank_entry->description, array('target' => '_blank')),
            $amount, $unpaid, $currency, $hasfiles,
        );

	if($filter != 'showarchived') {
	    array_push($tabledata,
        	$buttonaprobe . $buttondeny,
    	    );
	}

        $table->data[] = $tabledata;

    }
    if (count($table->data)) {
	echo html_writer::table($table);
    } else {
        echo '</br><h5>'.(get_string('noentriesfound', 'paygw_bank')).'</h5>';
    }
}

if ($bank_entries && count($items)) {
?>
<div class="row">
    <div class="col">
        <button type="button" class="btn btn-primary" onclick="sendmail()">
            <?php echo get_string('sendmailtoselected', 'paygw_bank'); ?>
        </button>
    </div>
</div>
<?php
}
?>
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
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sendmailmodalLabel"><?php echo get_string('sendmailtoselected', 'paygw_bank'); ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <form name="formsendmail" method="POST">
                    <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                    <input type="hidden" name="action" value="sendmail">
                    <input type="hidden" name="confirm" value="1">
                    <input type="hidden" name="ids" id="ids" value="">
        
                    <div class="form-group">
                        <label for="subject"><?php echo get_string('subject'); ?></label>
                        <input type="text" class="form-control" id="subject" name="subject" required>
                        </br><label for="message"><?php echo get_string('message'); ?></label>
                        <textarea class="form-textarea form-control" cols="40" rows="5" id="message" name="message" required></textarea>
                        </br><input type="submit" class="btn btn-primary" value="<?php echo get_string('send','paygw_bank'); ?>">
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
echo $OUTPUT->footer();
