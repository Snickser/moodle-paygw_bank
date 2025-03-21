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
//$PAGE->set_secondary_navigation(false);
if ($cid) {
    $PAGE->navbar->add($course->fullname, '/course/view.php?id='.$course->id);
}
$PAGE->navbar->add(get_string('pluginname', 'paygw_bank'));
$confirm = optional_param('confirm', 0, PARAM_INT);
$id  = optional_param('id', 0, PARAM_INT);
$ids = optional_param('ids', '', PARAM_TEXT);
$filter = optional_param('filter', '', PARAM_TEXT);
$action = optional_param('action', '', PARAM_TEXT);

echo $OUTPUT->header();

$items = bank_helper::get_pending_item_collections($cid);

echo '<form name="filteritem" method="POST">
<select class="custom-select" name="filter" id="filterkey">';
echo '<option value="">'.get_string('all').'</option>';
foreach ($items as $item) {
    echo '<option value="' . $item['key'] . '" >' . $item['description'] . '</option>';
}
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
        \core\notification::info("Reload");
    }
}
if ($confirm==1 && $ids!='' && $action=='sendmail') {
    require_sesskey();
    $ids=explode(',', $ids);
    foreach ($ids as $id) {
        if ($id>0) {
            bank_helper::sendmail($id, optional_param('subject', '', PARAM_TEXT), optional_param('message', '', PARAM_TEXT));
        }
    }
    \core\notification::info(get_string('mails_sent', 'paygw_bank'));
    $OUTPUT->notification(get_string('mails_sent', 'paygw_bank'));
}
$post_url= new moodle_url($PAGE->url, array('sesskey'=>sesskey()));

$bank_entries = bank_helper::get_pending();
if (!$bank_entries || !count($items)) {
    $match = array();
    echo '</br>'.(get_string('noentriesfound', 'paygw_bank'));
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
    $table->head = array($checkboxcheckall,
        get_string('date'), get_string('code', 'paygw_bank'),
        get_string('fullnameuser'),
        get_string('email'),
//        get_string('course'),
        get_string('concept', 'paygw_bank'), get_string('total_cost', 'paygw_bank'), get_string('today_cost', 'paygw_bank'), get_string('currency'), get_string('hasfiles', 'paygw_bank'), get_string('actions')
    );
    //$headarray=array(get_string('date'),get_string('code', 'paygw_bank'), get_string('concept', 'paygw_bank'),get_string('amount', 'paygw_bank'),get_string('currency'));

    foreach ($bank_entries as $bank_entry) {
        $bankentrykey = bank_helper::get_item_key($bank_entry->component, $bank_entry->paymentarea, $bank_entry->itemid);
        if ($filter != '' && ($bankentrykey != $filter)) {
            continue;
        }

// Check in course.
if (!bank_helper::check_in_course($cid, $bank_entry->paymentarea, $bank_entry->component, $bank_entry->itemid)) {
    continue;
}

        $config = (object) helper::get_gateway_configuration($bank_entry->component, $bank_entry->paymentarea, $bank_entry->itemid, 'bank');
        $payable = helper::get_payable($bank_entry->component, $bank_entry->paymentarea, $bank_entry->itemid);
        $currency = $payable->get_currency();
        $customer = $DB->get_record('user', array('id' => $bank_entry->userid));
        $fullname = fullname($customer, true);

        // Add surcharge if there is any.
        $surcharge = helper::get_gateway_surcharge('paypal');
        $amount = helper::get_rounded_cost($bank_entry->totalamount, $currency, $surcharge);

$unpaid = '-';
$primary = 'primary';
// Check uninterrupted cost.
if ($bank_entry->component == "enrol_yafee") {
    $cs = $DB->get_record('enrol', ['id' => $bank_entry->itemid, 'enrol' => 'yafee']);
        if ($data = $DB->get_record('user_enrolments', ['userid' => $bank_entry->userid, 'enrolid' => $cs->id])) {
         if (isset($data->timeend) || isset($data->timestart)) {
            if ($cs->customint5 && $cs->enrolperiod && $data->timeend < time() && $data->timestart) {
                $unpaid = (round(((time() - $data->timeend) / $cs->enrolperiod)) * $cs->cost) ;
            }
         }
        }
 if ($amount < $unpaid) {
    $unpaid = '<font color=red><b>' . $unpaid . '</b></font>';
    $primary = 'secondary';
 } else {
    $unpaid = '<font color=green>' . $unpaid . '</font>';
 }
}

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

        $table->data[] = array($selectitemcheckbox,
            date('Y-m-d', $bank_entry->timecreated), $bank_entry->code,
	    html_writer::link('/user/profile.php?id='.$customer->id, $fullname, array('target' => '_blank')),
            $customer->email,
//            $cs->courseid,
            html_writer::link($url, $bank_entry->description, array('target' => '_blank')),
            $amount, $unpaid, $currency, $hasfiles, $buttonaprobe . $buttondeny
        );
    }
    echo html_writer::table($table);
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
