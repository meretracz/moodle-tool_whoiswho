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

use tool_whoiswho\table\users_overview_table;

defined('MOODLE_INTERNAL') || die;

$context = context_system::instance();

require_login();
require_capability('tool/whoiswho:dashboardaccess', $context);

// Get filter parameters.
$fullname = optional_param('fullname', '', PARAM_TEXT);
$withissues = optional_param('withissues', 0, PARAM_INT);
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
    'withissues' => $withissues,
];

// If specific userid provided, filter by that user.
if ($userid > 0) {
    $filters['userid'] = $userid;
}

// Create and setup the table.
$table = new users_overview_table('whoiswho_users', $filters);
$table->define_baseurl($PAGE->url);
$table->is_downloadable(true);
$table->show_download_buttons_at([TABLE_P_BOTTOM]);

// Handle download.
if ($download) {
    $table->is_downloading($download, 'whoiswho_users_' . date('Ymd'));
}

if (!$table->is_downloading()) {
    echo $OUTPUT->header();

    // Add filter form.
    $filterurl = $PAGE->url;
    echo '<div class="whoiswho-filters mb-3">';
    echo '<form method="get" action="' . $filterurl . '" class="form-inline">';

    echo '<div class="form-group mx-2">';
    echo '<label for="fullname" class="sr-only">' . get_string('filter:fullname', 'tool_whoiswho') . '</label>';
    echo '<input type="text" name="fullname" id="fullname" class="form-control" placeholder="' .
        get_string('filter:fullname', 'tool_whoiswho') . '" value="' . s($fullname) . '">';
    echo '</div>';

    echo '<div class="form-group mx-2">';
    echo '<label class="form-check-label">';
    echo '<input type="checkbox" name="withissues" value="1" class="form-check-input" ' .
        ($withissues ? 'checked' : '') . '> ';
    echo get_string('filter:withissues', 'tool_whoiswho');
    echo '</label>';
    echo '</div>';

    echo '<button type="submit" class="btn btn-primary mx-2">' . get_string('filter') . '</button>';
    echo '<a href="' . $filterurl . '" class="btn btn-secondary">' . get_string('clear') . '</a>';

    echo '</form>';
    echo '</div>';

    // Add link back to dashboard.
    echo '<div class="mb-3">';
    echo html_writer::link(
        new moodle_url('/admin/tool/whoiswho/view/dashboard.php'),
        '← ' . get_string('backtodashboard', 'tool_whoiswho'),
        ['class' => 'btn btn-secondary']
    );
    echo '</div>';
}

// Display the table.
$table->out(50, true);

if (!$table->is_downloading()) {
    echo $OUTPUT->footer();
}