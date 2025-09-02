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
 * Filter form for issues table.
 *
 * @package     tool_whoiswho
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_whoiswho\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

use moodleform;

/**
 * Form for filtering issues in the issues table.
 *
 * @package     tool_whoiswho
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class issues_filter_form extends moodleform {

    /**
     * Defines the form elements and their configuration.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('text', 'fullname', get_string('filter:fullname', 'tool_whoiswho'));
        $mform->setType('fullname', PARAM_TEXT);

        $contextoptions = [
            0 => get_string('filter:anycontext', 'tool_whoiswho'),
            CONTEXT_SYSTEM => get_string('contextsystem'),
            CONTEXT_COURSECAT => get_string('contextcoursecat'),
            CONTEXT_COURSE => get_string('contextcourse'),
            CONTEXT_MODULE => get_string('contextmodule'),
        ];
        $mform->addElement('select', 'contextlevel', get_string('filter:context', 'tool_whoiswho'), $contextoptions);
        $mform->setType('contextlevel', PARAM_INT);

        $this->add_action_buttons(false, get_string('filter:apply', 'tool_whoiswho'));
    }
}

