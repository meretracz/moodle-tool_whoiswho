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
 * Issues overview output class
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
use tool_whoiswho\form\issues_filter_form;
use tool_whoiswho\table\issues_table;

/**
 * Issues overview output class
 *
 * @package     tool_whoiswho
 * @copyright   2025 LdesignMedia.nl
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class issues_overview implements renderable, templatable {

    /** @var array Filter values */
    protected array $filters = [];

    /** @var moodle_url Page URL */
    protected moodle_url $pageurl;

    /** @var issues_filter_form Filter form */
    protected issues_filter_form $filterform;

    /** @var issues_table Table instance */
    protected issues_table $table;

    /**
     * Constructor
     *
     * @param array $filters      Filter values
     * @param moodle_url $pageurl Page URL
     */
    public function __construct(array $filters, moodle_url $pageurl) {
        $this->filters = $filters;
        $this->pageurl = $pageurl;

        // Initialize filter form.
        $this->filterform = new issues_filter_form($pageurl->out(false));
        $this->filterform->set_data([
            'fullname' => $filters['fullname'] ?? '',
            'contextlevel' => $filters['contextlevel'] ?? 0,
            'status' => $filters['status'] ?? '',
        ]);

        // Initialize table.
        $this->table = new issues_table('tool_whoiswho_issues', $filters);
        $this->table->define_baseurl($pageurl);
        $this->table->pagesize(25, 0);
    }

    /**
     * Export data for template
     *
     * @param renderer_base $output
     *
     * @return array
     */
    public function export_for_template(renderer_base $output): array {

        // Capture filter form.
        ob_start();
        $this->filterform->display();
        $filterformhtml = ob_get_clean();

        // Capture table output.
        ob_start();
        $this->table->out(25, true);
        $tablehtml = ob_get_clean();

        return [
            'dashboardurl' => (new moodle_url('/admin/tool/whoiswho/view/dashboard.php'))->out(false),
            'filterform' => $filterformhtml,
            'table' => $tablehtml,
            'hasfilters' => !empty($this->filters['fullname'])
                || !empty($this->filters['contextlevel'])
                || !empty($this->filters['userid'])
                || (!empty($this->filters['userids']) && is_array($this->filters['userids']))
                || ($this->filters['status'] ?? '') !== '',
        ];
    }

}
