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
 * Queue an adhoc scan for a single user, then redirect back to the issues page.
 *
 * @package     tool_whoiswho
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
$sysctx = context_system::instance();
require_capability('tool/whoiswho:dashboardaccess', $sysctx);
require_sesskey();

$userid = required_param('userid', PARAM_INT);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

// Validate user exists.
if (!$DB->record_exists('user', ['id' => $userid])) {
    throw new \core\exception\moodle_exception('invaliduser');
}

// Run scan immediately for this user, respecting plugin settings.
try {
    \tool_whoiswho\local\scan_manager::run_users(null, [$userid], $USER->id);
    $message = get_string('notice:completed_user_scan', 'tool_whoiswho');

    if ($returnurl) {
        redirect(new moodle_url($returnurl), $message);
    } else {
        redirect(new moodle_url('/admin/tool/whoiswho/view/issues.php'), $message);
    }

} catch (Throwable $e) {
    $message = get_string('notice:failed_user_scan', 'tool_whoiswho', $e->getMessage());
    if ($returnurl) {
        redirect(
            new moodle_url($returnurl),
            $message,
            0,
            \core\output\notification::NOTIFY_ERROR
        );
    } else {
        redirect(
            new moodle_url('/admin/tool/whoiswho/view/issues.php'),
            $message,
            0,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}
