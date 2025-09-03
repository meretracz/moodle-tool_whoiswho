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
 * Adhoc task to (re)scan capability issues for a single user.
 *
 * @package     tool_whoiswho
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_whoiswho\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Adhoc task to scan overlaps and conflicts for a single user.
 */
class scan_user_adhoc extends \core\task\adhoc_task {

    /**
     * Name for admin UI.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_adhoc_security_scan', 'tool_whoiswho');
    }

    /**
     * Execute adhoc user scan.
     */
    public function execute(): void {
        $data = (object) ($this->get_custom_data() ?? []);
        $userid = isset($data->userid) ? (int) $data->userid : 0;
        if ($userid <= 0) {
            return;
        }

        \tool_whoiswho\local\scan_manager::run_users(null, [$userid], (int) $this->get_userid());
    }
}

