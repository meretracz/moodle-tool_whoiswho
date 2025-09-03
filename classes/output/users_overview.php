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
 * Users overview output class
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
use tool_whoiswho\form\users_filter_form;
use tool_whoiswho\table\users_overview_table;

/**
 * Users overview output class
 *
 * @package     tool_whoiswho
 * @copyright   2025 LdesignMedia.nl
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class users_overview implements renderable, templatable {

    /** @var array Filter values */
    protected array $filters = [];

    /** @var moodle_url Page URL */
    protected moodle_url $pageurl;

    /** @var users_filter_form Filter form */
    protected users_filter_form $filterform;

    /** @var users_overview_table Table instance */
    protected users_overview_table $table;

    /** @var string Download format if downloading */
    protected string $download = '';

    /**
     * Constructor
     *
     * @param array $filters      Filter values
     * @param moodle_url $pageurl Page URL
     * @param string $download    Download format
     */
    public function __construct(array $filters, moodle_url $pageurl, string $download = '') {
        $this->filters = $filters;
        $this->pageurl = $pageurl;
        $this->download = $download;

        // Initialize filter form.
        $this->filterform = new users_filter_form($pageurl->out(false));
        $this->filterform->set_data([
            'fullname' => $filters['fullname'] ?? '',
        ]);

        // Initialize table.
        $this->table = new users_overview_table('whoiswho_users', $filters);
        $this->table->define_baseurl($pageurl);
        $this->table->is_downloadable(true);
        $this->table->show_download_buttons_at([TABLE_P_BOTTOM]);

        // Handle download.
        if ($download) {
            $this->table->is_downloading($download, 'whoiswho_users_' . date('Ymd'));
        }
    }

    /**
     * Check if table is downloading
     *
     * @return bool
     */
    public function is_downloading(): bool {
        return $this->table->is_downloading();
    }

    /**
     * Get table output
     *
     * @return string
     */
    public function get_table_output(): string {
        ob_start();
        $this->table->out(50, true);

        return ob_get_clean();
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

        return [
            'dashboardurl' => (new moodle_url('/admin/tool/whoiswho/view/dashboard.php'))->out(false),
            'filterform' => $filterformhtml,
            'table' => $this->get_table_output(),
            'hasfilters' => !empty($this->filters['fullname']),
        ];
    }

}
