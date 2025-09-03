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
 * Upgrade steps for tool_whoiswho
 *
 * @package     tool_whoiswho
 * @copyright   2025
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute tool_whoiswho upgrade steps
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_tool_whoiswho_upgrade(int $oldversion): bool {
    global $DB;

    // Cleanup: remove any stored cap_overlap findings as overlaps are no longer tracked.
    if ($oldversion < 2025090304) {
        // Delete child rows first.
        $sql = "DELETE FROM {tool_whoiswho_finding_cap}
                 WHERE findingid IN (
                   SELECT id FROM {tool_whoiswho_finding} WHERE type = :t
                 )";
        $DB->execute($sql, ['t' => 'cap_overlap']);
        // Delete the findings themselves.
        $DB->delete_records('tool_whoiswho_finding', ['type' => 'cap_overlap']);

        upgrade_plugin_savepoint(true, 2025090304, 'tool', 'whoiswho');
    }

    return true;
}

