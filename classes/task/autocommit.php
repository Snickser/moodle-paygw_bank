<?php
// This file is part of Moodle - http://moodle.org/
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
 * Send expiry notifications task.
 *
 * @package   paygw_bank
 * @copyright 2024 Alex Orlov <snickser@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_bank\task;

use core_payment\helper;
use paygw_bank\bank_helper;

/**
 * Send expiry notifications task.
 *
 * @package   paygw_bank
 * @copyright 2024 Alex Orlov <snickser@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class autocommit extends \core\task\scheduled_task {
    /**
     * Name for this task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('autocommit', 'paygw_bank');
    }

    /**
     * Run task for autocommits.
     */
    public function execute() {
        global $DB, $CFG;

        mtrace('start');

	$items = bank_helper::get_pending('P');

	foreach($items as $item){
	    if (!$item->hasfiles) {
		continue;
	    }

    	    $config = (object) helper::get_gateway_configuration($item->component, $item->paymentarea, $item->itemid, 'bank');

	    $delay = 0;
    	    if (isset($config->delayautocommit)) {
    		$delay = $config->delayautocommit;
    	    }

            if (!$config->autocommit) {
		continue;
	    }

	    $files = $DB->get_records('files', ['component' => 'paygw_bank',
		'itemid' => $item->id], 'timecreated DESC');

	    $file = reset($files);

	    if ($file->timemodified + $delay < time()) {
		mtrace($item->id . ' commited');
		bank_helper::aprobe_pay($item->id);
	    }

	}
        mtrace('end.');
    }
}
