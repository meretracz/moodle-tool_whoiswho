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
 * Profile field manager class
 *
 * @package     tool_whoiswho
 * @copyright   2025 LdesignMedia.nl
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_whoiswho\local\manager;

use context;
use stdClass;

/**
 * Profile field manager class
 *
 * Handles profile field data processing and analysis
 *
 * @package     tool_whoiswho
 * @copyright   2025 LdesignMedia.nl
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profilefield_manager {

    /**
     * Get overview data for a profile field
     *
     * @param string $field    Field name
     * @param context $context Context for role name resolution
     *
     * @return array Array of data rows for the overview table
     */
    public static function get_field_overview_data(string $field, context $context): array {
        global $DB;

        $iscustomfield = str_starts_with($field, 'profile_field_');

        // Get field type if it's a custom field.
        $fieldtype = null;
        if ($iscustomfield) {
            $shortname = substr($field, strlen('profile_field_'));
            $customfield = $DB->get_record('user_info_field', ['shortname' => $shortname]);
            if ($customfield) {
                $fieldtype = $customfield->datatype;
            }
        }

        // Get distinct values and counts.
        $values = self::get_field_values($field, $iscustomfield);

        // Process each value to get additional data.
        $tabledata = [];
        foreach ($values as $value) {
            $userids = self::get_users_with_value($field, $value->value, $iscustomfield);
            $roles = self::get_user_roles($userids, $context);
            $issuecount = self::get_issue_count($userids);

            $tabledata[] = [
                'value' => $value->value,
                'usercount' => $value->usercount,
                'roles' => implode(', ', $roles),
                'issuecount' => $issuecount,
                // Provide user IDs so the action can link to the issues table filtered on these users.
                'userids' => array_map('intval', $userids),
            ];
        }

        return $tabledata;
    }

    /**
     * Get distinct values and user counts for a field
     *
     * @param string $field       Field name
     * @param bool $iscustomfield Whether it's a custom profile field
     *
     * @return array Array of value objects with value and usercount
     */
    private static function get_field_values(string $field, bool $iscustomfield): array {
        global $DB;

        if ($iscustomfield) {
            $shortname = substr($field, strlen('profile_field_'));
            $customfield = $DB->get_record('user_info_field', ['shortname' => $shortname], '*', MUST_EXIST);

            $sql = "SELECT DISTINCT uid.data as value, COUNT(DISTINCT uid.userid) as usercount
                    FROM {user_info_data} uid
                    WHERE uid.fieldid = :fieldid
                      AND uid.data != ''
                    GROUP BY uid.data
                    ORDER BY usercount DESC";

            return $DB->get_records_sql($sql, ['fieldid' => $customfield->id]);
        } else {
            $sql = "SELECT DISTINCT u.$field as value, COUNT(u.id) as usercount
                    FROM {user} u
                    WHERE u.$field != ''
                      AND u.deleted = 0
                    GROUP BY u.$field
                    ORDER BY usercount DESC";

            return $DB->get_records_sql($sql);
        }
    }

    /**
     * Get user IDs with a specific field value
     *
     * @param string $field       Field name
     * @param string $value       Field value
     * @param bool $iscustomfield Whether it's a custom profile field
     *
     * @return array Array of user IDs
     */
    private static function get_users_with_value(string $field, string $value, bool $iscustomfield): array {
        global $DB;

        if ($iscustomfield) {
            $shortname = substr($field, strlen('profile_field_'));
            $customfield = $DB->get_record('user_info_field', ['shortname' => $shortname], '*', MUST_EXIST);

            $sql = "SELECT u.id
                    FROM {user} u
                    JOIN {user_info_data} uid ON uid.userid = u.id
                    WHERE uid.fieldid = :fieldid
                      AND uid.data = :value
                      AND u.deleted = 0";

            return $DB->get_fieldset_sql($sql, ['fieldid' => $customfield->id, 'value' => $value]);
        } else {
            $sql = "SELECT u.id
                    FROM {user} u
                    WHERE u.$field = :value
                      AND u.deleted = 0";

            return $DB->get_fieldset_sql($sql, ['value' => $value]);
        }
    }

    /**
     * Get all distinct roles for a set of users
     *
     * @param array $userids   Array of user IDs
     * @param context $context Context for role name resolution
     *
     * @return array Array of role names
     */
    private static function get_user_roles(array $userids, context $context): array {
        global $DB;

        if (empty($userids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $sql = "SELECT DISTINCT r.id, r.shortname, r.name
                FROM {role_assignments} ra
                JOIN {role} r ON r.id = ra.roleid
                WHERE ra.userid $insql
                ORDER BY r.sortorder";

        $rolerecords = $DB->get_records_sql($sql, $params);

        $roles = [];
        foreach ($rolerecords as $role) {
            $roles[] = role_get_name($role, $context);
        }

        return $roles;
    }

    /**
     * Get count of unresolved issues for a set of users
     *
     * @param array $userids Array of user IDs
     *
     * @return int Issue count
     */
    private static function get_issue_count(array $userids): int {
        global $DB;

        if (empty($userids)) {
            return 0;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $sql = "SELECT COUNT(DISTINCT f.id) as count
                FROM {tool_whoiswho_finding} f
                WHERE f.userid $insql
                  AND (f.resolved = 0 OR f.resolved IS NULL)";

        return (int) $DB->get_field_sql($sql, $params);
    }

    /**
     * Get display name for a field
     *
     * @param string $field Field name
     *
     * @return string Display name
     */
    public static function get_field_display_name(string $field): string {
        global $DB;

        if (str_starts_with($field, 'profile_field_')) {
            $shortname = substr($field, strlen('profile_field_'));
            $customfield = $DB->get_record('user_info_field', ['shortname' => $shortname]);
            if ($customfield) {
                return format_string($customfield->name);
            }
        } else {
            // Try to get a nice name for standard fields.
            $displayname = get_string($field, 'moodle', null, null);
            if ($displayname !== '[[' . $field . ']]') {
                return $displayname;
            }
        }

        return $field;
    }

}
