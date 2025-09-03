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
 * Capability manager for handling capability-related data operations
 *
 * @package     tool_whoiswho
 * @copyright   2025 LdesignMedia.nl - Luuk Verhoeven
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_whoiswho\local\manager;

/**
 * Capability manager class
 *
 * @package     tool_whoiswho
 * @copyright   2025 LdesignMedia.nl - Luuk Verhoeven
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class capability_manager {

    /**
     * Get the count of unresolved issues (excluding pure overlaps)
     *
     * @return int Number of unresolved issues
     */
    public static function get_unresolved_issues_count(): int {
        global $DB;

        // Exclude cap_overlap type to match the issues table filtering.
        $sql = "SELECT COUNT(*)
                FROM {tool_whoiswho_finding}
                WHERE resolved = 0
                AND type <> :excludeoverlap";

        return $DB->count_records_sql($sql, ['excludeoverlap' => 'cap_overlap']);
    }

    /**
     * Get the count of all issues
     *
     * @return int Total number of issues
     */
    public static function get_total_issues_count(): int {
        global $DB;

        return $DB->count_records('tool_whoiswho_finding');
    }

    /**
     * Get the count of resolved issues
     *
     * @return int Number of resolved issues
     */
    public static function get_resolved_issues_count(): int {
        global $DB;

        return $DB->count_records('tool_whoiswho_finding', ['resolved' => 1]);
    }

    /**
     * Get dashboard statistics
     *
     * @return array Dashboard statistics
     */
    public static function get_dashboard_stats(): array {
        global $DB;

        $stats = [];

        // Get unresolved issues count.
        $stats['unresolved_issues'] = self::get_unresolved_issues_count();

        // Get unique users with issues (excluding overlaps to match issues table).
        $sql = "SELECT COUNT(DISTINCT userid)
                FROM {tool_whoiswho_finding}
                WHERE resolved = 0
                AND type <> :excludeoverlap";
        $stats['affected_users'] = $DB->count_records_sql($sql, ['excludeoverlap' => 'cap_overlap']);

        // Get count by severity if needed (excluding overlaps).
        $sql = "SELECT severity, COUNT(*) as count
                FROM {tool_whoiswho_finding}
                WHERE resolved = 0
                AND type <> :excludeoverlap
                GROUP BY severity";
        $stats['by_severity'] = $DB->get_records_sql($sql, ['excludeoverlap' => 'cap_overlap']);

        return $stats;
    }

    /**
     * Get count of issues by type
     *
     * @param bool $unresolvedonly Whether to count only unresolved issues
     *
     * @return array Array of issue counts by type
     */
    public static function get_issues_by_type(bool $unresolvedonly = true): array {
        global $DB;

        $where = $unresolvedonly ? 'WHERE resolved = 0' : '';

        $sql = "SELECT type, COUNT(*) as count
                FROM {tool_whoiswho_finding}
                $where
                GROUP BY type";

        return $DB->get_records_sql($sql);
    }

    /**
     * Get statistics for a specific profile field
     *
     * @param int $profilefieldid The profile field ID
     *
     * @return array Statistics for the profile field
     */
    public static function get_profile_field_stats(int $profilefieldid): array {
        global $DB;

        $stats = [];

        // Get count of unique values for this profile field among users with issues.
        $sql = "SELECT COUNT(DISTINCT uid.data) as unique_values
                FROM {user_info_data} uid
                WHERE uid.fieldid = :fieldid
                AND EXISTS (
                    SELECT 1
                    FROM {tool_whoiswho_finding} f
                    WHERE f.userid = uid.userid
                    AND f.resolved = 0
                    AND f.type <> 'cap_overlap'
                )";

        $result = $DB->get_record_sql($sql, ['fieldid' => $profilefieldid]);
        $stats['unique_values'] = $result ? (int) $result->unique_values : 0;

        // Get count of users with this field filled who have issues.
        $sql = "SELECT COUNT(DISTINCT uid.userid) as user_count
                FROM {user_info_data} uid
                WHERE uid.fieldid = :fieldid
                AND uid.data IS NOT NULL
                AND uid.data <> ''
                AND EXISTS (
                    SELECT 1
                    FROM {tool_whoiswho_finding} f
                    WHERE f.userid = uid.userid
                    AND f.resolved = 0
                    AND f.type <> 'cap_overlap'
                )";

        $result = $DB->get_record_sql($sql, ['fieldid' => $profilefieldid]);
        $stats['user_count'] = $result ? (int) $result->user_count : 0;

        return $stats;
    }

}
