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
 * Profile field overview page
 *
 * @package    tool_whoiswho
 * @category   admin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  02/09/2025 LdesignMedia.nl - Luuk Verhoeven
 * @author     Vincent Cornelis
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();

$field = required_param('field', PARAM_ALPHANUMEXT);

$context = context_system::instance();
require_capability('tool/whoiswho:dashboardaccess', $context);

$url = new moodle_url('/admin/tool/whoiswho/view/profilefield_overview.php', ['field' => $field]);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('title:profilefield_overview', 'tool_whoiswho'));
$PAGE->set_heading(get_string('heading:profilefield_overview', 'tool_whoiswho', $field));

// Get profile field overview data using the manager.
$tabledata = \tool_whoiswho\local\manager\profilefield_manager::get_field_overview_data($field, $context);

// Determine field type for custom fields.
$fieldtype = null;
if (str_starts_with($field, 'profile_field_')) {
    $shortname = substr($field, strlen('profile_field_'));
    $customfield = $DB->get_record('user_info_field', ['shortname' => $shortname]);
    if ($customfield) {
        $fieldtype = $customfield->datatype;
    }
}

// Create output class.
$page = new \tool_whoiswho\output\profilefield_overview($field, $tabledata, $url, $fieldtype);

// Render the page.
$rendered = $OUTPUT->render($page);

echo $OUTPUT->header();
echo $rendered;
echo $OUTPUT->footer();
