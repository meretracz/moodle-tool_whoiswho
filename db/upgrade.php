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
 * Moodle database upgrade script
 *
 * @package     tool_whoiswho
 * @copyright   2025 LdesignMedia.nl - Luuk Verhoeven
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_tool_whoiswho_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    // 2024090202: introduce core tables for scans and findings.
    if ($oldversion < 2025090300) {
        // Table: tool_whoiswho_scan.
        $table = new xmldb_table('tool_whoiswho_scan');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('startedat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('finishedat', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'queued');
        $table->add_field('initiatedby', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('scopecontextid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('meta', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        // Indexes.
        $dbman->add_index($table, new xmldb_index('status_ix', XMLDB_INDEX_NOTUNIQUE, ['status']));
        $dbman->add_index($table, new xmldb_index('scope_ix', XMLDB_INDEX_NOTUNIQUE, ['scopecontextid']));
        $dbman->add_index($table, new xmldb_index('started_ix', XMLDB_INDEX_NOTUNIQUE, ['startedat']));

        // Table: tool_whoiswho_finding.
        $table = new xmldb_table('tool_whoiswho_finding');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('fingerprint', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
        $table->add_field('scanid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('type', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
        $table->add_field('severity', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('capability', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('firstseenat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lastseenat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('resolved', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('resolvedby', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('resolvedat', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('details', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fingerprint_uk', XMLDB_KEY_UNIQUE, ['fingerprint']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        // Indexes.
        $dbman->add_index($table, new xmldb_index('user_ctx_ix', XMLDB_INDEX_NOTUNIQUE, ['userid', 'contextid']));
        $dbman->add_index($table, new xmldb_index('type_ix', XMLDB_INDEX_NOTUNIQUE, ['type']));
        $dbman->add_index($table, new xmldb_index('resolved_ix', XMLDB_INDEX_NOTUNIQUE, ['resolved']));
        $dbman->add_index($table, new xmldb_index('lastseen_ix', XMLDB_INDEX_NOTUNIQUE, ['lastseenat']));
        $dbman->add_index($table, new xmldb_index('cap_res_ix', XMLDB_INDEX_NOTUNIQUE, ['capability', 'resolved']));

        // Table: tool_whoiswho_finding_cap.
        $table = new xmldb_table('tool_whoiswho_finding_cap');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('findingid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('roleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('permission', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, null);
        $table->add_field('capname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('label', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        // Indexes.
        $dbman->add_index($table, new xmldb_index('finding_ix', XMLDB_INDEX_NOTUNIQUE, ['findingid']));
        $dbman->add_index($table, new xmldb_index('cap_ix', XMLDB_INDEX_NOTUNIQUE, ['capname']));
        $dbman->add_index($table, new xmldb_index('perm_ix', XMLDB_INDEX_NOTUNIQUE, ['permission']));

        upgrade_plugin_savepoint(true, 2025090300, 'tool', 'whoiswho');
    }

    return true;
}

