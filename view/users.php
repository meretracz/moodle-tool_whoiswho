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
 * Users overview page
 *
 * @package     tool_whoiswho
 * @copyright   2025 LdesignMedia.nl
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

defined('MOODLE_INTERNAL') || die;

$context = context_system::instance();

require_login();
require_capability('tool/whoiswho:dashboardaccess', $context);

// Get filter parameters.
$fullname = optional_param('fullname', '', PARAM_TEXT);
$userid = optional_param('userid', 0, PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);

$PAGE->set_url(new moodle_url('/admin/tool/whoiswho/view/users.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('title:users', 'tool_whoiswho'));
$PAGE->set_heading(get_string('heading:users', 'tool_whoiswho'));

// Add navigation.
$PAGE->navbar->add(
    get_string('pluginname', 'tool_whoiswho'),
    new moodle_url('/admin/tool/whoiswho/view/dashboard.php')
);
$PAGE->navbar->add(get_string('heading:users', 'tool_whoiswho'));

// Filter form.
$filters = [
    'fullname' => $fullname,
];

// If specific userid provided, filter by that user.
if ($userid > 0) {
    $filters['userid'] = $userid;
}

// Create output object.
$page = new \tool_whoiswho\output\users_overview($filters, $PAGE->url, $download);

// Render the page before any output. Allow for redirects/form submissions easier.
$rendered = $OUTPUT->render($page);

// Handle download or display page.
if (!$page->is_downloading()) {
    echo $OUTPUT->header();
    echo $rendered;
    echo $OUTPUT->footer();
} else {
    // Output table for download.
    echo $page->get_table_output();
}
