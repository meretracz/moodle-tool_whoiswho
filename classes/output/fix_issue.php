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
 * Fix issue output class
 *
 * @package     tool_whoiswho
 * @copyright   2025 LdesignMedia.nl
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_whoiswho\output;

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use moodle_url;
use single_button;
use tool_whoiswho\form\fix_issue_form;

/**
 * Fix issue output class
 *
 * @package     tool_whoiswho
 * @copyright   2025 LdesignMedia.nl
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fix_issue implements renderable, templatable {

    /** @var object Finding record */
    protected object $finding;

    /** @var object Context */
    protected object $context;

    /** @var fix_issue_form Form instance */
    protected fix_issue_form $form;

    /** @var array Role data */
    protected array $rolesdata;

    /** @var moodle_url Current URL */
    protected moodle_url $url;

    /**
     * Constructor
     *
     * @param object $finding Finding record
     * @param object $context Context
     * @param fix_issue_form $form Form instance
     * @param array $rolesdata Role data
     * @param moodle_url $url Current URL
     */
    public function __construct(object $finding, object $context, fix_issue_form $form, array $rolesdata, moodle_url $url) {
        $this->finding = $finding;
        $this->context = $context;
        $this->form = $form;
        $this->rolesdata = $rolesdata;
        $this->url = $url;
    }

    /**
     * Export data for template
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {

        // Recheck URL.
        $recheckurl = new moodle_url('/admin/tool/whoiswho/view/recheck_user.php', [
            'userid' => (int) $this->finding->userid,
            'sesskey' => sesskey(),
            'returnurl' => $this->url->out_as_local_url(false),
        ]);

        // Quick navigation links.
        $capurl = new moodle_url('/admin/roles/capability.php', [
            'capability' => (string) $this->finding->capability,
            'contextid' => (int) $this->finding->contextid,
        ]);
        $checkurl = new moodle_url('/admin/roles/check.php', [
            'contextid' => (int) $this->finding->contextid,
            'userid' => (int) $this->finding->userid,
        ]);

        // Per-role edit links.
        $rolelinks = [];
        foreach ($this->rolesdata as $rid => $info) {
            $editurl = new moodle_url('/admin/roles/define.php', ['action' => 'edit', 'roleid' => (int) $rid]);
            $rolelinks[] = [
                'url' => $editurl->out(false),
                'name' => s($info['name']),
            ];
        }

        // Capture form.
        ob_start();
        $this->form->display();
        $formhtml = ob_get_clean();

        return [
            'recheckurl' => $recheckurl->out(false),
            'sesskey' => sesskey(),
            'capoverviewurl' => $capurl->out(false),
            'checkpermissionsurl' => $checkurl->out(false),
            'rolelinks' => $rolelinks,
            'hasrolelinks' => !empty($rolelinks),
            'form' => $formhtml,
        ];
    }
}
