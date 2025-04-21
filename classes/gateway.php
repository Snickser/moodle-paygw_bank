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
 * Contains class for bank payment gateway.
 *
 * @package   paygw_bank
 * @copyright UNESCO/IESALC
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_bank;

/**
 * Contains class for bank payment gateway.
 *
 * @package   paygw_bank
 * @copyright UNESCO/IESALC
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gateway extends \core_payment\gateway {
    /**
     * Get supported_currencies.
     *
     */
    public static function get_supported_currencies(): array {
        $alternatecurrencies = get_config('paygw_bank', 'aditionalcurrencies');
        $alternatecurrencies = trim($alternatecurrencies);
        $altcurrenc = [];
        if (strlen($alternatecurrencies) > 2) {
            $altcurrenc = explode(',', $alternatecurrencies);
        }
        $initialcurrencies = [
            'USD', 'EUR', 'RUB', 'BYR',
        ];
        return array_merge($initialcurrencies, $altcurrenc);
    }

    /**
     * Configuration form for the gateway instance
     *
     * Use $form->get_mform() to access the \MoodleQuickForm instance
     *
     * @param \core_payment\form\account_gateway $form
     */
    public static function add_configuration_to_gateway_form(\core_payment\form\account_gateway $form): void {
        $mform = $form->get_mform();

        $mform->addElement('advcheckbox', 'autocommit', get_string('autocommit', 'paygw_bank'));

        $mform->addElement('duration', 'delayautocommit', get_string('delayautocommit', 'paygw_bank'));
        $mform->disabledIf('delayautocommit', 'autocommit', "ne", 1);
        $mform->setDefault('delayautocommit', 300);
        $mform->addHelpButton('delayautocommit', 'delayautocommit', 'paygw_bank');

        $mform->addElement('text', 'fixdesc', get_string('fixdesc', 'paygw_bank'), ['size' => 50]);
        $mform->setType('fixdesc', PARAM_TEXT);
        $mform->addRule('fixdesc', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('fixdesc', 'fixdesc', 'paygw_bank');

        $mform->addElement('editor', 'instructionstext', get_string('instructionstext', 'paygw_bank'));
        $mform->setType('instructionstext', PARAM_RAW);

        $mform->addElement('editor', 'postinstructionstext', get_string('postinstructionstext', 'paygw_bank'));
        $mform->setType('postinstructionstext', PARAM_RAW);

        $mform->addElement('text', 'codeprefix', get_string('codeprefix', 'paygw_bank'));
        $mform->setType('codeprefix', PARAM_RAW);

        $mform->setDefault('codeprefix', 'code');

        $mform->addElement(
            'advcheckbox',
            'sendnewrequestmail',
            get_string('send_new_request_mail', 'paygw_bank')
        );
        $mform->addElement(
            'advcheckbox',
            'sendnewattachmentsmail',
            get_string('send_new_attachments_mail', 'paygw_bank')
        );
        $mform->addElement(
            'advcheckbox',
            'sendconfirmailtosupport',
            get_string('send_confirm_mail_to_support', 'paygw_bank')
        );
        $mform->addElement(
            'advcheckbox',
            'sendteachermail',
            get_string('send_teacher_mail', 'paygw_bank')
        );

        $mform->addElement('advcheckbox', 'onlyingroup', get_string('onlyingroup', 'paygw_bank'));

        $mform->addElement(
            'advcheckbox',
            'unfixcost',
            get_string('unfixcost', 'paygw_bank')
        );
        $mform->setType('unfixcost', PARAM_INT);
        $mform->addHelpButton('unfixcost', 'unfixcost', 'paygw_bank');

        $mform->addElement('text', 'suggest', get_string('suggest', 'paygw_bank'), ['size' => 10]);
        $mform->setType('suggest', PARAM_TEXT);
        $mform->addHelpButton('suggest', 'suggest', 'paygw_bank');

        $mform->addElement('text', 'maxcost', get_string('maxcost', 'paygw_bank'), ['size' => 10]);
        $mform->setType('maxcost', PARAM_TEXT);

        $mform->addElement('html', '<hr>');
        $plugininfo = \core_plugin_manager::instance()->get_plugin_info('paygw_bank');
        $donate = get_string('donate', 'paygw_bank', $plugininfo);
        $mform->addElement('html', $donate);
    }

    /**
     * Validates the gateway configuration form.
     *
     * @param \core_payment\form\account_gateway $form
     * @param \stdClass                          $data
     * @param array                              $files
     * @param array                              $errors form errors (passed by reference)
     */
    public static function validate_gateway_form(
        \core_payment\form\account_gateway $form,
        \stdClass $data,
        array $files,
        array &$errors
    ): void {
        if (!$data->enabled) {
            $errors['enabled'] = get_string('gatewaycannotbeenabled', 'payment');
        }
        if ($data->suggest < 0 && $data->suggest) {
            $errors['suggest'] = get_string('suggesterror', 'paygw_bank');
        }
        if ($data->maxcost < 0 && $data->maxcost || $data->maxcost < $data->suggest) {
            $errors['maxcost'] = get_string('maxcosterror', 'paygw_bank');
        }
    }
}
