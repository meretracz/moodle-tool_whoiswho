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
 * Issues view page
 *
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @package     tool_whoiswho
 * @copyright   02/09/2025 LdesignMedia.nl - Luuk Verhoeven
 * @author      Vincent Cornelis
 **/

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
$context = context_system::instance();
require_capability('tool/whoiswho:dashboardaccess', $context);

admin_externalpage_setup('tool_whoiswho_dashboard');

// Params.
$fullname = optional_param('fullname', '', PARAM_TEXT);
$contextlevel = optional_param('contextlevel', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$status = optional_param('status', '', PARAM_ALPHA);

// If userid is provided, get the user's name for pre-filling the filter.
if ($userid > 0) {
    global $DB;
    $user = $DB->get_record('user', ['id' => $userid], 'firstname, lastname', IGNORE_MISSING);
    if ($user && empty($fullname)) {
        $fullname = $user->firstname . ' ' . $user->lastname;
    }
}

$url = new moodle_url('/admin/tool/whoiswho/view/issues.php', [
    'fullname' => $fullname,
    'contextlevel' => $contextlevel,
    'userid' => $userid,
    'status' => $status,
]);

$PAGE->set_url($url);
$PAGE->set_title(get_string('title:issues', 'tool_whoiswho'));
$PAGE->set_heading(get_string('heading:issues', 'tool_whoiswho'));

// Build filters array.
$filters = [
    'fullname' => $fullname,
    'contextlevel' => $contextlevel,
    'status' => $status,
];
if ($userid > 0) {
    $filters['userid'] = $userid;
}

// Create output object.
$page = new \tool_whoiswho\output\issues_overview($filters, $url);

// Render the page.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('heading:issues', 'tool_whoiswho'));
echo $OUTPUT->render($page);
echo $OUTPUT->footer();
