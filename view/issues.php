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

$url = new moodle_url('/admin/tool/whoiswho/view/issues.php', [
    'fullname' => $fullname,
    'contextlevel' => $contextlevel,
]);
$PAGE->set_url($url);
$PAGE->set_title(get_string('title:issues', 'tool_whoiswho'));
$PAGE->set_heading(get_string('heading:issues', 'tool_whoiswho'));

// Filter form.
$mform = new \tool_whoiswho\form\issues_filter_form($url->out(false));
$mform->set_data(['fullname' => $fullname, 'contextlevel' => $contextlevel]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('heading:issues', 'tool_whoiswho'));

$mform->display();

// Build table.
$filters = ['fullname' => $fullname, 'contextlevel' => $contextlevel];
$table = new \tool_whoiswho\table\issues_table('tool_whoiswho_issues', $filters);
$table->define_baseurl($url);
$table->pagesize(25, 0);
$table->out(25, true);

echo $OUTPUT->footer();

