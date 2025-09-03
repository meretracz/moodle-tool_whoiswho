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
 * Profile field overview output class
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
use tool_whoiswho\table\profilefield_overview_table;

/**
 * Profile field overview output class
 *
 * @package     tool_whoiswho
 * @copyright   2025 LdesignMedia.nl
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profilefield_overview implements renderable, templatable {

    /** @var string Field name */
    protected string $fieldname;

    /** @var array Table data */
    protected array $tabledata;

    /** @var moodle_url Current URL */
    protected moodle_url $url;

    /** @var ?string Field type */
    protected ?string $fieldtype;

    /**
     * Constructor
     *
     * @param string $fieldname  Field name
     * @param array $tabledata   Table data
     * @param moodle_url $url    Current URL
     * @param ?string $fieldtype Field type
     */
    public function __construct(string $fieldname, array $tabledata, moodle_url $url, ?string $fieldtype = null) {
        $this->fieldname = $fieldname;
        $this->tabledata = $tabledata;
        $this->url = $url;
        $this->fieldtype = $fieldtype;
    }

    /**
     * Export data for template
     *
     * @param renderer_base $output
     *
     * @return array
     */
    public function export_for_template(renderer_base $output): array {

        // Get field display name using the manager.
        $displayname = \tool_whoiswho\local\manager\profilefield_manager::get_field_display_name($this->fieldname);

        // Create and setup table.
        $table = new profilefield_overview_table('profilefield_overview', $this->fieldname, $this->fieldtype);
        $table->define_baseurl($this->url);
        $table->setup();

        // Render table with data.
        ob_start();
        $table->start_output();

        // Add data rows.
        foreach ($this->tabledata as $row) {
            $rowobj = (object) $row;
            $rowdata = [
                $table->col_value($rowobj),
                $table->col_usercount($rowobj),
                $table->col_roles($rowobj),
                $table->col_issuecount($rowobj),
                $table->col_action($rowobj),
            ];
            $table->add_data($rowdata);
        }

        $table->finish_output();
        $tablehtml = ob_get_clean();

        // Back to dashboard URL.
        $dashboardurl = new moodle_url('/admin/tool/whoiswho/view/dashboard.php');

        return [
            'fieldname' => $displayname,
            'dashboardurl' => $dashboardurl->out(false),
            'tablehtml' => $tablehtml,
        ];
    }

}
