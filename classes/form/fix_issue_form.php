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
 * Form to adjust capability permissions for roles involved in a finding.
 *
 * @package     tool_whoiswho
 * @copyright   02/09/2025 LdesignMedia.nl - Luuk Verhoeven
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_whoiswho\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

use moodleform;

/**
 * Form to select permission (inherit/allow/prevent/prohibit) per role for a capability.
 */
class fix_issue_form extends moodleform {

    /**
     * Define the form elements.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;

        // Expected custom data: capability, roles (id => ['name' =>, 'current' => int]), contextname, status.
        $data = $this->_customdata ?? [];
        $cap = (string) ($data['capability'] ?? '');
        $contextname = (string) ($data['contextname'] ?? '');
        $roles = (array) ($data['roles'] ?? []);
        $status = (string) ($data['status'] ?? 'pending');

        $capbadge = \html_writer::tag('span', s($cap), ['class' => 'badge badge-warning p-2']);
        $mform->addElement('static', 'caplabel', get_string('capability', 'role'), $capbadge);
        $mform->addElement('static', 'ctxlabel', get_string('context', 'role'), s($contextname));

        $permoptions = [
            0 => get_string('inherit', 'role'),
            CAP_ALLOW => get_string('allow', 'role'),
            CAP_PREVENT => get_string('prevent', 'role'),
            CAP_PROHIBIT => get_string('prohibit', 'role'),
        ];

        foreach ($roles as $rid => $info) {
            $rid = (int) $rid;
            $label = (string) ($info['name'] ?? ("role:$rid"));
            $current = (int) ($info['current'] ?? 0);
            $effective = $info['effective'] ?? null;

            // Build enhanced label showing current setting.
            $currenttext = $permoptions[$current];
            $enhancedlabel = $label;

            // Add visual indicator for current setting.
            if ($current !== CAP_INHERIT) {
                $badge = match ($current) {
                    CAP_ALLOW => 'success',
                    CAP_PREVENT => 'warning',
                    CAP_PROHIBIT => 'danger',
                    default => 'secondary'
                };
                $enhancedlabel .= ' ' . \html_writer::tag(
                        'span',
                        get_string('form:current', 'tool_whoiswho') . ': ' . $currenttext,
                        ['class' => "badge badge-$badge ml-2"]
                    );
            } else {
                $enhancedlabel .= ' ' . \html_writer::tag(
                        'span',
                        get_string('form:current', 'tool_whoiswho') . ': ' . $currenttext,
                        ['class' => 'text-muted ml-2']
                    );
            }

            $mform->addElement('select', "perm[$rid]", $enhancedlabel, $permoptions);
            $mform->setDefault("perm[$rid]", $current);
            $mform->setType("perm[$rid]", PARAM_INT);

            // Show effective value if different from current.
            if ($effective !== null && $effective !== $current && isset($permoptions[(int) $effective])) {
                $effbadge = match ((int) $effective) {
                    CAP_ALLOW => 'info',
                    CAP_PREVENT => 'secondary',
                    CAP_PROHIBIT => 'danger',
                    default => 'light'
                };
                $mform->addElement(
                    'static',
                    "eff_$rid",
                    '',
                    \html_writer::tag(
                        'div',
                        \html_writer::tag('small', get_string('form:effective', 'tool_whoiswho') . ': ') .
                        \html_writer::tag(
                            'span',
                            $permoptions[(int) $effective],
                            ['class' => "badge badge-$effbadge"]
                        ),
                        ['class' => 'text-muted ml-4']
                    )
                );
            }
        }

        // Status selector.
        $mform->addElement('select', 'status', get_string('col:status', 'tool_whoiswho'), [
            'pending' => get_string('status:pending', 'tool_whoiswho'),
            'resolved' => get_string('status:resolved', 'tool_whoiswho'),
            'ignored' => get_string('status:ignored', 'tool_whoiswho'),
        ]);
        $mform->setDefault('status', $status);
        $mform->setType('status', PARAM_ALPHA);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons(true, get_string('savechanges'));
    }

}
