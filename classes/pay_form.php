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
 * Contains form to apply for PAYNL services through Sebsoft
 *
 * @package    paygw_bank
 * @copyright  UNESCO/IESALC
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_bank;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form class for bank payment gateway
 *
 * This form collects necessary information for processing bank payments
 * through the Moodle payment gateway system.
 */
class pay_form extends \moodleform {

    /**
     * Define the form elements
     *
     * Adds hidden fields for payment processing and a submit button.
     * The form includes:
     * - confirmation flag
     * - component identifier
     * - payment area
     * - item ID
     * - cost
     * - description
     */
    public function definition() {
        $mform = $this->_form;

        $mform->setDisableShortforms(true);
        $mform->addElement('hidden', 'confirm');

        $mform->setDefault('confirm', 1);
        $mform->setType('confirm', PARAM_INT);

        $mform->addElement('hidden', 'component');
        $mform->setType('component', PARAM_TEXT);

        $mform->addElement('hidden', 'paymentarea');
        $mform->setType('paymentarea', PARAM_TEXT);

        $mform->addElement('hidden', 'itemid');
        $mform->setType('itemid', PARAM_INT);

        $mform->addElement('hidden', 'costself');
        $mform->setType('costself', PARAM_FLOAT);

        $mform->addElement('hidden', 'description');
        $mform->setType('description', PARAM_TEXT);

        $mform->addElement('submit', 'submitbutton', get_string('start_process', 'paygw_bank'));
    }

    /**
     * Validate form data
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK.
     */
    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);
        return $errors;
    }
}
