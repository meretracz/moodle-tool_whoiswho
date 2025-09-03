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

    /** @var array Profile field IDs from config */
    protected array $profilefieldids = [];

    /** @var array Profile field names for headers */
    protected array $profilefieldnames = [];

    /** @var array Profile field types for formatting */
    protected array $profilefieldtypes = [];

    /** @var array Filter values */
    protected array $filters = [];

    /**
     * Constructor for the table class.
     *
     * @param string $uniqueid The unique identifier for the table instance.
     * @param array $filters   Optional filters to initialize the table with.
     *
     * @return void
     */
    public function __construct(string $uniqueid, array $filters = []) {
        parent::__construct($uniqueid);
        $this->filters = $filters;
        $this->useridfield = 'uid';  // Set the correct user ID field name.
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
            'contextbadge',
            'fullname',
        ];

        $headers = [
            get_string('col:issue', 'tool_whoiswho'),
            get_string('col:context', 'tool_whoiswho'),
            get_string('fullname'),
        ];

        // Add profile field columns.
        foreach ($this->profilefieldids as $pfid) {
            $columns[] = 'profilefield_' . $pfid;
            $headers[] = s($this->profilefieldnames[$pfid]);
        }

        // Add remaining columns.
        $columns[] = 'status';
        $columns[] = 'roles';
        $columns[] = 'location';
        $columns[] = 'action';

        $headers[] = get_string('col:status', 'tool_whoiswho');
        $headers[] = get_string('col:roles', 'tool_whoiswho');
        $headers[] = get_string('col:location', 'tool_whoiswho');
        $headers[] = get_string('col:action', 'tool_whoiswho');
        $this->define_columns($columns);
        $this->define_headers($headers);

        $fields = 'f.id, f.type, f.capability, f.userid, f.contextid, f.issuestate, '
            . 'u.id AS uid, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, '
            . 'u.middlename, u.alternatename, '
            . 'c.contextlevel, c.instanceid';

        $from = '{tool_whoiswho_finding} f '
            . 'JOIN {user} u ON u.id = f.userid '
            . 'JOIN {context} c ON c.id = f.contextid ';
        $params = [];

        // Add profile field data joins and fields.
        foreach ($this->profilefieldids as $index => $pfid) {
            $alias = 'pf' . $index;
            $fields .= ", {$alias}.data AS profilefield_{$pfid}";
            $from .= " LEFT JOIN {user_info_data} {$alias} ON ({$alias}.userid = u.id AND {$alias}.fieldid = {$pfid})";
        }

        [$where, $wparams] = $this->build_filters_where();
        $params = array_merge($params, $wparams);

        $this->set_sql($fields, $from, $where ?: '1=1', $params);
        $this->set_count_sql('SELECT COUNT(1) FROM ' . $from . ' WHERE ' . ($where ?: '1=1'), $params);
    }

    /**
     * Initializes the profile field configuration by retrieving and setting
     * profile field IDs and names based on the provided configuration.
     *
     * @return void
     */
    protected function init_profilefield(): void {
        global $DB;

        $cfg = get_config('tool_whoiswho');
        $this->profilefieldids = [];
        $this->profilefieldnames = [];
        $this->profilefieldtypes = [];

        if (!empty($cfg->profilefields)) {
            $ids = preg_split('/[,\s]+/', (string) $cfg->profilefields);
            $ids = array_values(array_filter(array_map('intval', (array) $ids)));

            foreach ($ids as $id) {
                $field = $DB->get_record('user_info_field', ['id' => $id], 'id, name, datatype', IGNORE_MISSING);
                if ($field) {
                    $this->profilefieldids[] = $id;
                    $this->profilefieldnames[$id] = $field->name;
                    $this->profilefieldtypes[$id] = $field->datatype;
                }
            }
        }
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

        // Status filter.
        $status = trim((string) ($this->filters['status'] ?? ''));
        if ($status !== '') {
            $where[] = 'f.issuestate = :fstatus';
            $params['fstatus'] = $status;
        }

        // User ID filter.
        $userid = (int) ($this->filters['userid'] ?? 0);
        if ($userid > 0) {
            $where[] = 'u.id = :userid';
            $params['userid'] = $userid;
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * Generates and returns a formatted issue string based on the type and capability
     * properties of the provided row object.
     *
     * @param object $row An object containing issue details, including its type and capability.
     *
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
        $capurl = new moodle_url('/admin/roles/capability.php', [
            'capability' => $cap,
            'contextid' => (int) $row->contextid,
        ]);

        return html_writer::link($capurl, s($label . ' - ' . $cap));
    }

    /**
     * Display profile field column.
     *
     * @param object $row The row data.
     * @param int $pfid   The profile field ID.
     *
     * @return string The formatted output.
     */
    protected function col_profilefield(object $row, int $pfid): string {
        $fieldname = 'profilefield_' . $pfid;

        if (!isset($row->$fieldname) || $row->$fieldname === null || $row->$fieldname === '') {
            return html_writer::tag('span', '-');
        }

        $value = (string) $row->$fieldname;
        $fieldtype = $this->profilefieldtypes[$pfid] ?? 'text';

        // Format checkbox fields as checkmarks or crosses.
        if ($fieldtype === 'checkbox') {
            if ($value === '1') {
                return html_writer::tag('span', '✓', ['class' => 'badge badge-success', 'title' => get_string('yes')]);
            }

            return html_writer::tag('span', '✗', ['class' => 'badge badge-danger', 'title' => get_string('no')]);
        }

        // Default formatting for other field types.
        return format_string($value, true, [
            'context' => \context_system::instance(),
        ]);
    }

    /**
     * Override the other_cols method to handle dynamic profile field columns.
     *
     * @param string $column The column name.
     * @param object $row    The row data.
     *
     * @return string The formatted column value.
     */
    public function other_cols($column, $row): string {
        if (preg_match('/^profilefield_(\d+)$/', $column, $matches)) {
            $pfid = (int) $matches[1];

            return $this->col_profilefield($row, $pfid);
        }

        return parent::other_cols($column, $row);
    }

    /**
     * Render status label for a finding.
     *
     * @param object $row Table row data containing status.
     *
     * @return string
     */
    public function col_status(object $row): string {
        $status = (string) ($row->issuestate ?? 'pending');

        return match ($status) {
            'resolved' => get_string('status:resolved', 'tool_whoiswho'),
            'ignored' => get_string('status:ignored', 'tool_whoiswho'),
            default => get_string('status:pending', 'tool_whoiswho'),
        };
    }

    /**
     * Retrieves and returns a comma-separated string of role names assigned to a user
     * within a specific context, based on the provided row data.
     *
     * @param object $row An object containing the context ID and user ID for which
     *                    the roles need to be determined.
     *
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
                $rname = role_get_name($role, $ctx, ROLENAME_ALIAS);
                $editurl = new moodle_url('/admin/roles/define.php', ['action' => 'edit', 'roleid' => (int) $role->id]);
                $names[] = html_writer::link($editurl, s($rname));
            }
        }

        return implode(', ', $names);
    }

    /**
     * Generates a location string for a given row object, including context name
     * and a possible link to the context if applicable.
     *
     * @param object $row A data object containing the context ID to determine the location.
     *
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
                    debugging($e->getMessage(), DEBUG_DEVELOPER);
                }
            }
        }

        $token = '[' . $name . ']';
        if ($url) {
            return html_writer::link($url, $name, ['class' => 'btn btn-sm btn-outline-info']);
        }

        return html_writer::tag('span', $name, ['class' => 'text-muted']);
    }

    /**
     * Generates action links for changing permissions and roles within a given context.
     *
     * @param object $row An object containing the context ID for the required action links.
     *
     * @return string A concatenated string of HTML links for changing permissions and roles.
     */
    public function col_action(object $row): string {
        global $OUTPUT;
        $ctxid = (int) $row->contextid;

        $fixurl = new moodle_url('/admin/tool/whoiswho/view/fix_issue.php', [
            'id' => (int) $row->id,
            'returnurl' => (new moodle_url('/admin/tool/whoiswho/view/issues.php', $this->filters))->out_as_local_url(false),
        ]);
        $roleurl = new moodle_url('/admin/roles/assign.php', ['contextid' => $ctxid]);
        $capurl = new moodle_url('/admin/roles/capability.php', [
            'capability' => (string) $row->capability,
            'contextid' => $ctxid,
        ]);
        $checkurl = new moodle_url('/admin/roles/check.php', [
            'contextid' => $ctxid,
            'userid' => (int) $row->userid,
        ]);
        $returnurl = new moodle_url('/admin/tool/whoiswho/view/issues.php', $this->filters);
        $recheckurl = new moodle_url('/admin/tool/whoiswho/view/recheck_user.php', [
            'userid' => (int) $row->userid,
            'sesskey' => sesskey(),
            'returnurl' => $returnurl->out_as_local_url(false),
        ]);

        $out = [];
        $out[] = html_writer::link($fixurl, get_string('action:changepermission', 'tool_whoiswho'),
            ['class' => 'btn btn-sm btn-outline-primary mb-1']);
        $out[] = html_writer::link($roleurl, get_string('action:changerole', 'tool_whoiswho'),
            ['class' => 'btn btn-sm btn-outline-secondary mb-1']);
        $out[] = html_writer::link($recheckurl, get_string('action:recheck', 'tool_whoiswho'),
            ['class' => 'btn btn-sm btn-outline-info mb-1']);

        return implode(' ', $out);
    }

    /**
     * Generates a badge showing the context level for the issue.
     *
     * @param object $row An object containing the context level information.
     *
     * @return string HTML badge showing the context level.
     */
    public function col_contextbadge(object $row): string {
        $contextlevel = (int) $row->contextlevel;

        // Map context levels to badge labels and colors.
        $contextinfo = $this->get_context_badge_info($contextlevel);

        $badgeclass = 'badge badge-' . $contextinfo['color'];
        $badgelabel = $contextinfo['label'];

        return html_writer::tag('span', $badgelabel, ['class' => $badgeclass]);
    }

    /**
     * Get badge information for a context level.
     *
     * @param int $contextlevel The context level constant.
     *
     * @return array Array with 'label' and 'color' keys.
     */
    protected function get_context_badge_info(int $contextlevel): array {

        return match ($contextlevel) {
            CONTEXT_SYSTEM => ['label' => get_string('contextsystem', 'tool_whoiswho'), 'color' => 'danger'],
            CONTEXT_USER => ['label' => get_string('contextuser', 'tool_whoiswho'), 'color' => 'info'],
            CONTEXT_COURSECAT => ['label' => get_string('contextcoursecat', 'tool_whoiswho'), 'color' => 'warning'],
            CONTEXT_COURSE => ['label' => get_string('contextcourse', 'tool_whoiswho'), 'color' => 'primary'],
            CONTEXT_MODULE => ['label' => get_string('contextmodule', 'tool_whoiswho'), 'color' => 'success'],
            CONTEXT_BLOCK => ['label' => get_string('contextblock', 'tool_whoiswho'), 'color' => 'secondary'],
            default => ['label' => get_string('unknown'), 'color' => 'light'],
        };
    }

}
