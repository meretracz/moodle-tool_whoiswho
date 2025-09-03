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
 * Profile field overview table
 *
 * @package     tool_whoiswho
 * @copyright   2025 LdesignMedia.nl
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_whoiswho\table;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/tablelib.php');

use html_writer;
use moodle_url;
use flexible_table;

/**
 * Profile field overview table class
 *
 * @package     tool_whoiswho
 * @copyright   2025 LdesignMedia.nl
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profilefield_overview_table extends flexible_table {

    /** @var string Field name */
    protected string $fieldname;

    /** @var string Field type (for custom fields) */
    protected ?string $fieldtype;

    /**
     * Constructor
     *
     * @param string $uniqueid   Unique ID for this table
     * @param string $fieldname  Field name
     * @param ?string $fieldtype Field type (for custom fields)
     */
    public function __construct(string $uniqueid, string $fieldname, ?string $fieldtype = null) {
        parent::__construct($uniqueid);

        $this->fieldname = $fieldname;
        $this->fieldtype = $fieldtype;

        // Define columns.
        $columns = [
            'value',
            'usercount',
            'roles',
            'issuecount',
            'action',
        ];
        $this->define_columns($columns);

        // Define headers.
        $headers = [
            get_string('profilefield_overview:value', 'tool_whoiswho'),
            get_string('profilefield_overview:totalusers', 'tool_whoiswho'),
            get_string('profilefield_overview:allroles', 'tool_whoiswho'),
            get_string('profilefield_overview:numberofissues', 'tool_whoiswho'),
            get_string('col:action', 'tool_whoiswho'),
        ];
        $this->define_headers($headers);

        // Setup table properties.
        $this->collapsible(false);
        $this->sortable(true, 'usercount', SORT_DESC);
        $this->no_sorting('action');
        $this->no_sorting('roles');
        $this->pageable(true);
        $this->set_attribute('class', 'table-striped');
    }

    /**
     * Format value column
     *
     * @param object $row
     *
     * @return string
     */
    public function col_value($row): string {
        // For checkbox fields, display badges.
        if ($this->fieldtype === 'checkbox') {
            if ($row->value === '1') {
                return html_writer::tag('span', '✓', ['class' => 'badge badge-success', 'title' => get_string('yes')]);
            } else {
                return html_writer::tag('span', '✗', ['class' => 'badge badge-danger', 'title' => get_string('no')]);
            }
        }

        // For other fields, return formatted string.
        return format_string($row->value);
    }

    /**
     * Format user count column
     *
     * @param object $row
     *
     * @return string
     */
    public function col_usercount($row): string {
        return (string) $row->usercount;
    }

    /**
     * Format roles column
     *
     * @param object $row
     *
     * @return string
     */
    public function col_roles($row): string {
        return !empty($row->roles) ? $row->roles : get_string('noroles', 'tool_whoiswho');
    }

    /**
     * Format issue count column
     *
     * @param object $row
     *
     * @return string
     */
    public function col_issuecount($row): string {
        if ($row->issuecount > 0) {
            return html_writer::tag('span', $row->issuecount, ['class' => 'badge badge-warning']);
        }

        return html_writer::tag('span', '0', ['class' => 'text-muted']);
    }

    /**
     * Format action column
     *
     * @param object $row
     *
     * @return string
     */
    public function col_action($row): string {
        // TODO: Update URL when view users page is created
        $url = new moodle_url('#');

        return html_writer::link($url, get_string('profilefield_overview:viewusers', 'tool_whoiswho'),
            ['class' => 'btn btn-sm btn-outline-primary']);
    }

}
