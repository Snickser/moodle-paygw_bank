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
 * File         edit.php
 * Encoding     UTF-8
 *
 * @package paygw_bank
 * @copyright UNESCO/IESALC
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_bank;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Contains class for bank payment gateway.
 *
 */
class attachtransfer_form extends \moodleform {
    /** @var string */
    public $userfile;

    /** @var string */
    public $editfiles;

    /**
     * form definition
     */
    public function definition() {
        global $CFG;

        $maxfiles = get_config('paygw_bank', 'maxnumberfiles');
        $maxbytes = $CFG->maxbytes;
        $acceptedtypes = ['.zip', '.png', '.jpg', '.jpeg', '.doc', '.docx', '.pdf', '.odt'];
        $cfgallowedfiletypes = get_config('paygw_bank', 'allowedfiletypes');
        if (!empty($cfgallowedfiletypes)) {
            $acceptedtypes = explode(',', str_replace(' ', '', $cfgallowedfiletypes));
        }
        $mform = $this->_form;

	$mform->addElement('html', get_string('files_desc', 'paygw_bank'));

        $mform->setDisableShortforms(true);
        $mform->addElement('hidden', 'confirm');
        $mform->setDefault('confirm', 2);
        $mform->setType('confirm', PARAM_INT);
        $mform->addElement('hidden', 'component');
        $mform->setType('component', PARAM_TEXT);
        $mform->addElement('hidden', 'paymentarea');
        $mform->setType('paymentarea', PARAM_TEXT);
        $mform->addElement('hidden', 'itemid');
        $mform->setType('itemid', PARAM_INT);
        $mform->addElement('hidden', 'description');
        $mform->setType('description', PARAM_TEXT);
        $mform->addElement('hidden', 'editfiles');
        $mform->setType('editfiles', PARAM_INT);

        $mform->addElement(
            'filemanager',
            'userfile',
            get_string('file'),
            null,
            [
            'maxbytes' => $maxbytes,
            'accepted_types' => $acceptedtypes,
                'subdirs' => 0,
                'maxfiles' => $maxfiles,
            ]
        );
        $mform->addRule('userfile', null, 'required');

        $mform->addElement('submit', 'submitbutton', get_string('savefiles', 'paygw_bank'));
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
