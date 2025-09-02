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
 * Issues table based on table_sql.
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
use html_writer;
use moodle_url;
use table_sql;

/**
 * Lists findings with user and context info.
 *
 * @package     tool_whoiswho
 * @copyright   2025 LdesignMedia.nl
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class issues_table extends table_sql {

    /** @var int|null Profile field id for value column */
    protected ?int $profilefieldid = null;

    /** @var string|null Name of profile field for column header */
    protected ?string $profilefieldname = null;

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
        $this->init_profilefield();
        $this->define_columns_and_headers();
        $this->set_attribute('class', 'generaltable whoiswho-issues-table');
        $this->sortable(true, 'lastname', SORT_ASC);
    }

    /**
     * Defines the columns and headers for the table, as well as the SQL query for data retrieval.
     *
     * This method initializes the table structure by specifying the columns and their respective headers.
     * It also constructs the SQL query required to fetch the desired data, including optional filtering logic
     * based on profile field configurations.
     *
     * @return void
     */
    protected function define_columns_and_headers(): void {
        $columns = [
            'issue',
            'firstname',
            'lastname',
            'profilefield',
            'roles',
            'location',
            'action',
        ];

        $headers = [
            get_string('col:issue', 'tool_whoiswho'),
            get_string('firstname'),
            get_string('lastname'),
            $this->profilefieldname
                ? s($this->profilefieldname)
                : get_string('col:profilefield', 'tool_whoiswho'),
            get_string('col:roles', 'tool_whoiswho'),
            get_string('col:location', 'tool_whoiswho'),
            get_string('col:action', 'tool_whoiswho'),
        ];
        $this->define_columns($columns);
        $this->define_headers($headers);

        $fields = 'f.id, f.type, f.capability, f.userid, f.contextid, '
            . 'u.firstname, u.lastname, u.id AS uid, '
            . 'c.contextlevel, c.instanceid';

        if ($this->profilefieldid) {
            $fields .= ', uidata.data AS profiledata';
        } else {
            $fields .= ', NULL AS profiledata';
        }

        $from = '{tool_whoiswho_finding} f '
            . 'JOIN {user} u ON u.id = f.userid '
            . 'JOIN {context} c ON c.id = f.contextid ';
        $params = [];

        if ($this->profilefieldid) {
            $from .= 'LEFT JOIN {user_info_data} uidata ON (uidata.userid = u.id AND uidata.fieldid = :pfid) ';
            $params['pfid'] = $this->profilefieldid;
        }

        [$where, $wparams] = $this->build_filters_where();
        $params = array_merge($params, $wparams);

        $this->set_sql($fields, $from, $where ?: '1=1', $params);
        $this->set_count_sql('SELECT COUNT(1) FROM ' . $from . ' WHERE ' . ($where ?: '1=1'), $params);
    }

    /**
     * Initializes the profile field configuration by retrieving and setting
     * profile field ID and name based on the provided configuration.
     *
     * @return void
     */
    protected function init_profilefield(): void {
        $cfg = get_config('tool_whoiswho');
        $this->profilefieldid = null;
        $this->profilefieldname = null;
        if (!empty($cfg->profilefields)) {
            $ids = preg_split('/[,\s]+/', (string) $cfg->profilefields);
            $ids = array_values(array_filter(array_map('intval', (array) $ids)));
            if (!empty($ids)) {
                $this->profilefieldid = (int) $ids[0];
                $this->profilefieldname = $this->load_profilefield_name($this->profilefieldid);
            }
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
     * Builds the WHERE clause and corresponding parameters for filtering database queries
     * based on provided filters such as fullname and context level.
     *
     * @return array An array containing two elements: the WHERE clause string and the
     *               associative array of query parameters.
     */
    protected function build_filters_where(): array {
        global $DB;
        $where = [];
        $params = [];

        // Always hide pure overlaps (same capability allowed by multiple roles).
        // Requested behavior: "Dont show any capabilities that overlap and has the same value".
        $where[] = 'f.type <> :excludeoverlap';
        $params['excludeoverlap'] = 'cap_overlap';

        // Fullname filter: match firstname or lastname.
        $fullname = trim((string) ($this->filters['fullname'] ?? ''));
        if ($fullname !== '') {
            $like = '%' . $fullname . '%';
            $where[] = '(' . $DB->sql_like('u.firstname', ':fn', false) . ' OR ' . $DB->sql_like('u.lastname', ':ln', false) . ')';
            $params['fn'] = $like;
            $params['ln'] = $like;
        }

        // Context level filter.
        $contextlevel = (int) ($this->filters['contextlevel'] ?? 0);
        if ($contextlevel > 0) {
            $where[] = 'c.contextlevel = :cxlevel';
            $params['cxlevel'] = $contextlevel;
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * Generates and returns a formatted issue string based on the type and capability
     * properties of the provided row object.
     *
     * @param object $row An object containing issue details, including its type and capability.
     * @return string The formatted issue string, escaped for output.
     */
    public function col_issue(object $row): string {
        $type = (string) $row->type;
        $cap = (string) $row->capability;
        $label = $type;
        if ($type === 'cap_overlap') {
            $label = get_string('issue:overlap', 'tool_whoiswho');
        } else if ($type === 'cap_conflict') {
            $label = get_string('issue:conflict', 'tool_whoiswho');
        }

        return s($label . ' - ' . $cap);
    }

    /**
     * Generates and returns the formatted output for a specific profile field,
     * based on the data row provided.
     *
     * @param object $row An object containing the data row that includes profile data.
     * @return string The formatted profile field value or a placeholder indicating "not available".
     */
    public function col_profilefield(object $row): string {
        if ($this->profilefieldid && isset($row->profiledata) && $row->profiledata !== null && $row->profiledata !== '') {
            return format_string((string) $row->profiledata, true, [
                'context' => \context_system::instance(),
            ]);
        }

        return html_writer::tag('span', '-');
    }

    /**
     * Retrieves and returns a comma-separated string of role names assigned to a user
     * within a specific context, based on the provided row data.
     *
     * @param object $row An object containing the context ID and user ID for which
     *                    the roles need to be determined.
     * @return string A string containing the names of the roles, separated by commas,
     *                    or an empty string if no roles are assigned or the context is invalid.
     */
    public function col_roles(object $row): string {
        $ctx = context::instance_by_id((int) $row->contextid, IGNORE_MISSING);
        if (!$ctx) {
            return '';
        }
        $assigns = get_user_roles($ctx, (int) $row->userid, false);
        if (empty($assigns)) {
            return '';
        }
        $names = [];
        $rcache = [];
        foreach ($assigns as $a) {
            $rid = (int) $a->roleid;
            if (!isset($rcache[$rid])) {
                global $DB;
                $rcache[$rid] = $DB->get_record('role', ['id' => $rid], '*', IGNORE_MISSING);
            }
            $role = $rcache[$rid];
            if ($role) {
                $names[] = role_get_name($role, $ctx, ROLENAME_ALIAS);
            }
        }

        return s(implode(', ', $names));
    }

    /**
     * Generates a location string for a given row object, including context name
     * and a possible link to the context if applicable.
     *
     * @param object $row A data object containing the context ID to determine the location.
     * @return string The location string, potentially including a clickable link if the context supports it.
     */
    public function col_location(object $row): string {
        $ctx = context::instance_by_id((int) $row->contextid, IGNORE_MISSING);
        if (!$ctx) {
            return '';
        }
        $name = $ctx->get_context_name(false, true);

        // Build a deep link to the context if reasonable.
        $url = null;
        if ($ctx->contextlevel == CONTEXT_COURSE) {
            $url = new moodle_url('/course/view.php', ['id' => (int) $ctx->instanceid]);
        } else if ($ctx->contextlevel == CONTEXT_MODULE) {
            // Try to link to module view if the helper exists.
            if (function_exists('get_coursemodule_from_id')) {
                try {
                    $cm = get_coursemodule_from_id(
                        null,
                        (int) $ctx->instanceid,
                        0,
                        false,
                        IGNORE_MISSING
                    );
                    if ($cm && !empty($cm->url)) {
                        $url = $cm->url;
                    }
                } catch (\Throwable $e) {
                    // Ignore linking errors.
                }
            }
        }

        $token = '[' . $name . ']';
        if ($url) {
            return html_writer::link($url, $token);
        }

        return s($token);
    }

    /**
     * Generates action links for changing permissions and roles within a given context.
     *
     * @param object $row An object containing the context ID for the required action links.
     * @return string A concatenated string of HTML links for changing permissions and roles.
     */
    public function col_action(object $row): string {
        $ctxid = (int) $row->contextid;

        $permurl = new moodle_url('/admin/roles/override.php', ['contextid' => $ctxid]);
        $roleurl = new moodle_url('/admin/roles/assign.php', ['contextid' => $ctxid]);

        $out = [];
        $out[] = html_writer::link($permurl, '[' . get_string('action:changepermission', 'tool_whoiswho') . ']');
        $out[] = html_writer::link($roleurl, '[' . get_string('action:changerole', 'tool_whoiswho') . ']');

        return implode(' ', $out);
    }

}
