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
 * Scheduled task that executes the whoiswho scan.
 *
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @package     tool_whoiswho
 * @copyright   02/09/2025 LdesignMedia.nl - Luuk Verhoeven
 * @author      Vincent Cornelis
 **/

namespace tool_whoiswho\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task that executes the whoiswho scan.
 *
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @package     tool_whoiswho
 * @copyright   02/09/2025 LdesignMedia.nl - Luuk Verhoeven
 * @author      Vincent Cornelis
 **/
class scan_scheduled extends \core\task\scheduled_task {

    /**
     * Retrieves the name of the task.
     *
     * @return string The localized name of the task as defined in the language pack.
     */
    public function get_name(): string {
        return get_string('task:scan', 'tool_whoiswho');
    }

    /**
     *
     * Executes the scan process by invoking the scan manager's run method.
     *
     * @return void
     */
    public function execute(): void {
        \tool_whoiswho\local\scan_manager::run();
    }

}
