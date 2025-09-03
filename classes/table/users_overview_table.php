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
 * Users overview table based on table_sql.
 *
 * @package     tool_whoiswho
 * @copyright   2025 LdesignMedia.nl
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_whoiswho\table;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/tablelib.php');

use context;
use context_system;
use html_writer;
use moodle_url;
use table_sql;

/**
 * Lists users with their profile fields, capabilities and issues.
 *
 * @package     tool_whoiswho
 * @copyright   2025 LdesignMedia.nl
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class users_overview_table extends table_sql {

    /** @var array Profile field ids for columns */
    protected array $profilefieldids = [];

    /** @var array Names of profile fields for column headers */
    protected array $profilefieldnames = [];

    /** @var array Filter values */
    protected array $filters = [];

    /**
     * Constructor for the table class.
     *
     * @param string $uniqueid The unique identifier for the table instance.
     * @param array $filters   Optional filters to initialize the table with.
     * @return void
     */
    public function __construct(string $uniqueid, array $filters = []) {
        parent::__construct($uniqueid);
        $this->filters = $filters;
        $this->init_profilefields();
        $this->define_columns_and_headers();
        $this->set_attribute('class', 'generaltable whoiswho-users-overview-table');
        $this->sortable(true, 'lastname', SORT_ASC);
    }

    /**
     * Defines the columns and headers for the table, as well as the SQL query for data retrieval.
     *
     * @return void
     */
    protected function define_columns_and_headers(): void {
        $columns = ['firstname', 'lastname'];
        $headers = [get_string('firstname'), get_string('lastname')];
        
        // Add profile field columns.
        foreach ($this->profilefieldids as $index => $pfid) {
            $columns[] = 'profilefield_' . $pfid;
            $headers[] = isset($this->profilefieldnames[$pfid]) 
                ? s($this->profilefieldnames[$pfid])
                : get_string('col:profilefield', 'tool_whoiswho') . ' ' . ($index + 1);
        }
        
        // Add capabilities and issues columns.
        $columns[] = 'capabilities';
        $columns[] = 'issues';
        
        $headers[] = get_string('col:allcapabilities', 'tool_whoiswho');
        $headers[] = get_string('col:issues', 'tool_whoiswho');
        
        $this->define_columns($columns);
        $this->define_headers($headers);

        // Build SQL query.
        $fields = 'DISTINCT u.id, u.firstname, u.lastname';
        
        // Add profile field data to the select.
        foreach ($this->profilefieldids as $pfid) {
            $fields .= ", pf{$pfid}.data AS profiledata_{$pfid}";
        }
        
        // Add issue count subquery - exclude overlaps as they are not real issues.
        $fields .= ', (SELECT COUNT(*) FROM {tool_whoiswho_finding} f2 WHERE f2.userid = u.id AND f2.type <> \'cap_overlap\') AS issuecount';

        $from = '{user} u ';
        $params = [];

        // Join profile field data tables.
        foreach ($this->profilefieldids as $pfid) {
            $from .= "LEFT JOIN {user_info_data} pf{$pfid} ON (pf{$pfid}.userid = u.id AND pf{$pfid}.fieldid = :pfid_{$pfid}) ";
            $params["pfid_{$pfid}"] = $pfid;
        }

        // Build WHERE clause.
        [$where, $wparams] = $this->build_filters_where();
        $params = array_merge($params, $wparams);

        // Exclude deleted users and guest.
        $where = $where ? $where . ' AND ' : '';
        $where .= 'u.deleted = 0 AND u.id > 1';

        $this->set_sql($fields, $from, $where, $params);
        $this->set_count_sql('SELECT COUNT(DISTINCT u.id) FROM ' . $from . ' WHERE ' . $where, $params);
    }

    /**
     * Initializes the profile field configuration.
     *
     * @return void
     */
    protected function init_profilefields(): void {
        $cfg = get_config('tool_whoiswho');
        $this->profilefieldids = [];
        $this->profilefieldnames = [];
        
        if (!empty($cfg->profilefields)) {
            $ids = preg_split('/[,\s]+/', (string) $cfg->profilefields);
            $ids = array_values(array_filter(array_map('intval', (array) $ids)));
            
            // Get up to 2 profile fields for columns.
            $maxfields = min(2, count($ids));
            for ($i = 0; $i < $maxfields; $i++) {
                $pfid = (int) $ids[$i];
                $this->profilefieldids[] = $pfid;
                $this->profilefieldnames[$pfid] = $this->load_profilefield_name($pfid);
            }
        }
        
        // Ensure we always have 2 profile field slots.
        while (count($this->profilefieldids) < 2) {
            $this->profilefieldids[] = 0;
        }
    }

    /**
     * Loads the name of a user profile field based on its ID.
     *
     * @param int $id The ID of the profile field to retrieve the name for.
     * @return string|null The name of the profile field if it exists, or null if not found.
     */
    protected function load_profilefield_name(int $id): ?string {
        global $DB;
        $name = $DB->get_field('user_info_field', 'name', ['id' => $id], IGNORE_MISSING);
        return $name ?: null;
    }

    /**
     * Builds the WHERE clause and corresponding parameters for filtering.
     *
     * @return array An array containing the WHERE clause string and parameters.
     */
    protected function build_filters_where(): array {
        global $DB;
        $where = [];
        $params = [];

        // Fullname filter.
        $fullname = trim((string) ($this->filters['fullname'] ?? ''));
        if ($fullname !== '') {
            $like = '%' . $fullname . '%';
            $where[] = '(' . $DB->sql_like('u.firstname', ':fn', false) . ' OR ' . $DB->sql_like('u.lastname', ':ln', false) . ')';
            $params['fn'] = $like;
            $params['ln'] = $like;
        }

        // Filter by user with issues only (exclude overlaps as they are not real issues).
        if (!empty($this->filters['withissues'])) {
            $where[] = 'EXISTS (SELECT 1 FROM {tool_whoiswho_finding} f WHERE f.userid = u.id AND f.type <> \'cap_overlap\')';
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * Generates the profile field column output.
     *
     * @param object $row The data row.
     * @param int $pfid The profile field ID.
     * @return string The formatted profile field value.
     */
    protected function col_profilefield(object $row, int $pfid): string {
        $prop = 'profiledata_' . $pfid;
        
        if ($pfid && isset($row->$prop) && $row->$prop !== null && $row->$prop !== '') {
            return format_string((string) $row->$prop, true, [
                'context' => context_system::instance(),
            ]);
        }

        return html_writer::tag('span', 'x', ['class' => 'text-muted']);
    }

    /**
     * Dynamic column method for profile fields.
     *
     * @param string $column Column name.
     * @param object $row The data row.
     * @return string The formatted output.
     */
    public function other_cols($column, $row) {
        if (preg_match('/^profilefield_(\d+)$/', $column, $matches)) {
            $pfid = (int) $matches[1];
            return $this->col_profilefield($row, $pfid);
        }
        return '';
    }

    /**
     * Generates the capabilities column output.
     *
     * @param object $row The data row.
     * @return string The formatted capabilities list.
     */
    public function col_capabilities(object $row): string {
        global $DB;
        
        // Get all capabilities for this user from system context.
        $systemcontext = context_system::instance();
        
        // Get user roles in system context.
        $assigns = get_user_roles($systemcontext, (int) $row->id, false);
        
        if (empty($assigns)) {
            return html_writer::tag('span', '-', ['class' => 'text-muted']);
        }
        
        $roleids = array_column($assigns, 'roleid');
        $rolenames = [];
        
        foreach ($roleids as $roleid) {
            $role = $DB->get_record('role', ['id' => $roleid], '*', IGNORE_MISSING);
            if ($role) {
                $rolenames[] = role_get_name($role, $systemcontext, ROLENAME_ALIAS);
            }
        }
        
        if (empty($rolenames)) {
            return html_writer::tag('span', '-', ['class' => 'text-muted']);
        }
        
        // Format as "Teacher, Student" etc.
        return s(implode(', ', $rolenames));
    }

    /**
     * Generates the issues column output.
     *
     * @param object $row The data row.
     * @return string The formatted issues link.
     */
    public function col_issues(object $row): string {
        $issuecount = (int) ($row->issuecount ?? 0);
        
        if ($issuecount === 0) {
            return html_writer::tag('span', '-', ['class' => 'text-muted']);
        }
        
        // Create link to issues page filtered by this user.
        $url = new moodle_url('/admin/tool/whoiswho/view/issues.php', [
            'userid' => $row->id,
        ]);
        
        $text = '[' . $issuecount . ' ' . get_string('issues', 'tool_whoiswho') . ']';
        
        return html_writer::link($url, $text);
    }

    /**
     * Override first name column to make it sortable.
     *
     * @param object $row The data row.
     * @return string The formatted first name.
     */
    public function col_firstname(object $row): string {
        return format_string($row->firstname, true, ['context' => context_system::instance()]);
    }

    /**
     * Override last name column to make it sortable.
     *
     * @param object $row The data row.
     * @return string The formatted last name.
     */
    public function col_lastname(object $row): string {
        return format_string($row->lastname, true, ['context' => context_system::instance()]);
    }
}