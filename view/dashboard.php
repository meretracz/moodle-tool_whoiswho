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
 * Dashboard view page
 *
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @package     tool_whoiswho
 * @copyright   02/09/2025 LdesignMedia.nl - Luuk Verhoeven
 * @author      Vincent Cornelis
 **/

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

defined('MOODLE_INTERNAL') || die;

$context = context_system::instance();

require_login();
require_capability('moodle/site:config', $context); // TODO: Update to own capability.

$PAGE->set_url(new moodle_url('/admin/tool/whoiswho/view/dashboard.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('title:dashboard', 'tool_whoiswho'));
$PAGE->set_heading(get_string('heading:dashboard', 'tool_whoiswho'));

$page = new \tool_whoiswho\output\dashboard();

// Render the page before any output. Allow for redirects/form submissions easier.
$rendered = $OUTPUT->render($page);

echo $OUTPUT->header();
echo $rendered;
echo $OUTPUT->footer();
