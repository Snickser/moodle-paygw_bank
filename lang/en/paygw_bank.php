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
 * Strings for component 'paygw_bank', language 'en'
 *
 * @package   paygw_bank
 * @copyright UNESCO/IESALC
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['gatewaydescription'] = 'bank is an authorised payment gateway provider for processing credit card transactions.';
$string['gatewayname'] = 'Bank Transfer';
$string['concept'] = 'Concept';
$string['cost'] = 'Cost';
$string['code'] = 'Code';
$string['total_cost'] = 'Invoice cost';
$string['today_cost'] = 'Today cost';
$string['transfer_code'] = 'Transfer code';
$string['internalerror'] = 'An internal error has occurred. Please contact us.';
$string['pluginname'] = 'Bank Transfer';
$string['pluginname_desc'] = 'The Bank Transfer plugin allows to pay courses by bank Transfer or other manual payment methods.';
$string['privacy:metadata'] = 'The Bank Transfer plugin does not store any personal data.';
$string['instructionstext'] = 'Instructions that show previously to acept transfer payment.';
$string['postinstructionstext'] = 'Instructions that show after acept transfer payment.';
$string['transfer_code_explanation'] = 'This is your code to include in your transfer concept {$a}.';
$string['the_price_is'] = 'The total price is {$a}.';
$string['surcharge_info'] = 'This payment method has a surcharge of {$a}.';
$string['start_process'] = 'Start process';
$string['cancel_process'] = 'Cancel process';
$string['are_you_sure_cancel'] = 'Are you sure you want to cancel the payment process?';
$string['allow_users_add_files'] = 'Allow users add files';
$string['hasfiles'] = 'Has files';
$string['transfer_process_initiated'] = 'Transfer process initiated';
$string['pending_payments'] = 'Pending transfer payments';
$string['payments'] = 'Payments';
$string['approve'] = 'Approve';
$string['deny'] = 'Deny';
$string['my_pending_payments'] = 'My pending Transfer payments';
$string['payment_denied'] = 'You have canceled the payment';
$string['transfer_process_initiated'] = 'Transfer process initiated';
$string['manage'] = 'Manage Transfers';
$string['bank:managepayments'] = 'Manage Transfers';
$string['bank:manageincourse'] = 'Manage Transfers';
$string['managepayments'] = 'Manage Transfers';
$string['noentriesfound'] = 'No entries found.';
$string['send_confirmation_mail'] = 'Send a confirmation message to the user';
$string['send_denied_mail'] = 'Send a denied message to the user';
$string['send_teacher_mail'] = 'Also send these messages to teachers in course';
$string['mail_confirm_pay'] = 'Dear {$a->username}.
Your payment for "{$a->concept}" is confirmed.<br/> code: {$a->code}';
$string['mail_denied_pay'] = 'Dear {$a->username}.
Your payment for "{$a->concept}" is denied. <br/> code: {$a->code}';
$string['mail_confirm_pay_subject'] = 'Payment confirmed';
$string['mail_denied_pay_subject'] = 'Payment denied';
$string['max_number_of_files'] = 'Max number of files';
$string['file_uploaded'] = 'File uploaded';
$string['file_already_uploaded'] = 'File already uploaded';
$string['email_notifications'] = 'Email internal notifications';
$string['email_notifications_help'] = 'An external email address can be notified when a new payment is queued or their status change';
$string['email_to_notify'] = 'Email to send notifications';
$string['email_notifications_subject_new'] = 'New bank payment entry';
$string['email_notifications_subject_attachments'] = 'A payment entry has new attachments';
$string['email_notifications_subject_confirm'] = 'A payment entry has approved';
$string['email_notifications_new_request'] = 'There is a new bank payment request. code: {$a->code} from {$a->userfullname} with email {$a->useremail} for {$a->concept}';
$string['email_notifications_new_attachments'] = 'The bank payment entry with code {$a->code} from {$a->userfullname} with email {$a->useremail} for {$a->concept} has new attachments';
$string['email_notifications_confirm'] = 'The bank payment entry with code {$a->code} from {$a->userfullname} with email {$a->useremail} for {$a->concept} has been approved';
$string['send_new_request_mail'] = 'Send email to every new request';
$string['send_new_attachments_mail'] = 'Send email to new files in request';
$string['send_confirm_mail_to_support'] = 'Send email when a payment is approved';
$string['sendmailtoselected'] = 'Send email to selected';
$string['send'] = 'Send';
$string['mails_sent'] = 'Emails sent';
$string['additional_currencies'] = 'Aditional Currencies';
$string['additional_currencies_help'] = 'A comma separated list of currency codes. You can consult the codes in https://en.wikipedia.org/wiki/ISO_4217#Active_codes';
$string['codeprefix'] = 'Code prefix';
$string['allow_users_cancel_payments'] = 'Allow users cancel payments';
$string['allowed_file_types'] = 'Allowed file types';
$string['allowed_file_types_help'] = 'A comma separated list of file extensions allowed to upload, like ".zip". If empty, predefined files document and web_image types are allowed.';
$string['donate'] = '<div>Plugin version: {$a->release} ({$a->versiondisk})<br>
You can find new versions of the plugin at <a href=https://github.com/Snickser/moodle-paygw_bank>GitHub.com</a>
<img src="https://img.shields.io/github/v/release/Snickser/moodle-paygw_bank.svg"><br>
Please send me some <a href="https://yoomoney.ru/fundraise/143H2JO3LLE.240720">donate</a>😊</div>
TRX TRGMc3b63Lus6ehLasbbHxsb2rHky5LbPe<br>
BTC 1GFTTPCgRTC8yYL1gU7wBZRfhRNRBdLZsq<br>
ETH 0x1bce7aadef39d328d262569e6194febe597cb2c9<br>
<iframe src="https://yoomoney.ru/quickpay/fundraise/button?billNumber=143H2JO3LLE.240720"
width="330" height="50" frameborder="0" allowtransparency="true" scrolling="no"></iframe>';
$string['messageprovider:payment_receipt'] = 'Payment receipt';
$string['unpaidnotice'] = 'Expired!';
