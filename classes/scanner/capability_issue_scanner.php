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
 * Scanner that detects user-based capability overlaps and conflicts using scan_manager.
 * Capability overlap/conflict scanner backed by scan_manager.
 * * - Respects plugin settings for overlaps/conflicts and context scoping.
 * * - Can be scoped via config (contextid, userids, includeparents, levels, overlap_only).
 *
 * @package     tool_whoiswho
 * @copyright   2025 LdesignMedia.nl - Luuk Verhoeven
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_whoiswho\scanner;

use tool_whoiswho\local\scan_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Class capability_issue_scanner
 *
 * @package     tool_whoiswho
 * @copyright   2025 LdesignMedia.nl - Luuk Verhoeven
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class capability_issue_scanner extends base_scanner {

    /** @var int|null Current scanid used for persistence */
    protected ?int $scanid = null;

    /**
     * @return string
     */
    public function get_name(): string {
        return get_string('capability_issue_scanner', 'tool_whoiswho');
    }

    /**
     * Get the scanner description
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('capability_issue_scanner_desc', 'tool_whoiswho');
    }

    /**
     * Perform the scan
     *
     * @return void
     */
    protected function perform_scan(): void {
        global $DB;

        // 1) Resolve configuration and scope.
        $resolved = $this->resolve_config();
        $rootctx = $resolved['rootctx'];
        $overlapenabled = $resolved['overlapenabled'];
        $conflictenabled = $resolved['conflictenabled'];
        $overlaponly = $resolved['overlaponly'];
        $includeparents = $resolved['includeparents'];

        // 2) Start scan run record.
        $this->scanid = $this->start_scan_run(
            $resolved['initiatedby'],
            $rootctx ? $rootctx->id : null
        );

        try {
            // 3) Build scope pairs via manager helper.
            [$pairs, $meta] = scan_manager::select_pairs(
                $rootctx,
                $resolved['userids'],
                $resolved['levels']
            );

            $counters = [
                'newfind' => 0,
                'updfind' => 0,
                'paircount' => 0,
                'issuecount' => 0,
            ];

            foreach ($pairs as $p) {
                $counters['paircount']++;
                $userid = (int) $p['userid'];
                $cx = \context::instance_by_id((int) $p['contextid'], MUST_EXIST);

                $issues = scan_manager::find_capability_issues_for_user($userid, $cx, $includeparents);
                if (empty($issues['contexts'][$cx->id])) {
                    continue;
                }
                $cxpayload = $issues['contexts'][$cx->id];

                // 4) Handle conflicts and overlaps per pair.
                $this->process_conflicts(
                    $cxpayload,
                    $userid,
                    $cx,
                    $conflictenabled,
                    $overlaponly,
                    $counters
                );

                $this->process_overlaps(
                    $cxpayload,
                    $userid,
                    $cx,
                    $overlapenabled,
                    $counters
                );
            }

            // 5) Finalize scan success.
            $this->finalize_scan_success($this->scanid, $meta, $counters);

        } catch (\Throwable $e) {
            // 6) Finalize scan failure and rethrow.
            $this->finalize_scan_failure($this->scanid, $e);
            throw $e;
        }
    }

    /**
     * Resolve and normalize configuration and scope into a structured array.
     *
     * @return array{overlapenabled:bool,conflictenabled:bool,contextid:int|null,userids:array,includeparents:bool,levels:array,overlaponly:bool,initiatedby:int|null,rootctx:?\context}
     */
    private function resolve_config(): array {
        // 1) Read plugin-level defaults/flags.
        $cfg = get_config('tool_whoiswho');
        [$overlapenabled, $conflictenabled, $defaultIncludeParents, $defaultlevelsstr] = $this->get_plugin_flags($cfg);

        // 2) Read ad-hoc overrides supplied to the scanner.
        [$contextid, $useridsRaw, $includeparentsOverride, $levelsoverride, $overlaponly, $initiatedby] =
            $this->get_override_values($this->config);

        // 3) Normalize arrays and decide effective values.
        $userids = $this->normalize_ids_array($useridsRaw);
        $includeparents = ($includeparentsOverride !== null)
            ? (bool) $includeparentsOverride
            : (bool) $defaultIncludeParents;

        $levels = [];
        if (!empty($levelsoverride)) {
            $levels = $this->normalize_ids_array($levelsoverride);
        } else if (!empty($defaultLevelsStr)) {
            $levels = $this->normalize_ids_array($this->parse_levels_from_string((string) $defaultLevelsStr));
        }

        // 4) Resolve root context if provided.
        $rootctx = $this->resolve_root_context($contextid);

        // 5) Return normalized config payload used by perform_scan.
        return [
            'overlapenabled' => (bool) $overlapenabled,
            'conflictenabled' => (bool) $conflictenabled,
            'contextid' => $contextid,
            'userids' => $userids,
            'includeparents' => (bool) $includeparents,
            'levels' => $levels,
            'overlaponly' => (bool) $overlaponly,
            'initiatedby' => $initiatedby,
            'rootctx' => $rootctx,
        ];
    }

    /**
     * Extract plugin configuration flags and defaults.
     *
     * @param object $cfg
     * @return array{bool,bool,bool,string|null}
     */
    private function get_plugin_flags(object $cfg): array {
        $overlapenabled = !empty($cfg->scan_overlap_enabled);
        $conflictenabled = !empty($cfg->scan_conflict_enabled);
        $defaultincludeparents = !empty($cfg->scan_include_parents);
        $defaultlevelsstr = isset($cfg->scan_contextlevels) ? (string) $cfg->scan_contextlevels : null;

        return [
            $overlapenabled,
            $conflictenabled,
            $defaultincludeparents,
            $defaultlevelsstr,
        ];
    }

    /**
     * Extract ad-hoc override values passed into the scanner config.
     *
     * @param array $config
     * @return array{int|null, array, bool|null, array, bool, int|null}
     */
    private function get_override_values(array $config): array {
        $contextid = isset($config['contextid']) ? (int) $config['contextid'] : null;
        $useridsRaw = (array) ($config['userids'] ?? []);
        $includeparentsOverride = array_key_exists('includeparents', $config) ? (bool) $config['includeparents'] : null;
        $levelsoverride = (array) ($config['levels'] ?? []);
        $overlaponly = !empty($config['overlap_only']);
        $initiatedby = isset($config['initiatedby']) ? (int) $config['initiatedby'] : null;

        return [
            $contextid,
            $useridsRaw,
            $includeparentsOverride,
            $levelsoverride,
            $overlaponly,
            $initiatedby,
        ];
    }

    /**
     * Normalize a list of IDs to integers, remove empties, and reindex.
     *
     * @param array $ids
     * @return array
     */
    private function normalize_ids_array(array $ids): array {
        return array_values(array_filter(array_map('intval', $ids)));
    }

    /**
     * Parse a CSV/whitespace list of levels into an array of strings (to be normalized later).
     *
     * @param string $levels
     * @return array
     */
    private function parse_levels_from_string(string $levels): array {
        if ($levels === '') {
            return [];
        }

        return preg_split('/[,\s]+/', $levels) ?: [];
    }

    /**
     * Resolve a Moodle context instance or return null if not provided.
     *
     * @param int|null $contextid
     * @return \context|null
     */
    private function resolve_root_context(?int $contextid): ?\context {
        if ($contextid) {
            return \context::instance_by_id($contextid);
        }

        return null;
    }

    /**
     * Starts a new scan run and records it in the database.
     *
     * @param int|null $initiatedby    The ID of the user who initiated the scan, or null if not applicable.
     * @param int|null $scopecontextid The ID of the context defining the scope of the scan, or null if not specified.
     * @return int The ID of the newly created scan record.
     */
    private function start_scan_run(?int $initiatedby, ?int $scopecontextid): int {
        global $DB;
        $now = time();
        $scanrec = (object) [
            'startedat' => $now,
            'finishedat' => null,
            'status' => 'running',
            'initiatedby' => $initiatedby,
            'scopecontextid' => $scopecontextid,
            'meta' => null,
        ];

        return (int) $DB->insert_record('tool_whoiswho_scan', $scanrec);
    }

    /**
     * Processes capability conflicts if conflict processing is enabled and overlap-only mode is disabled.
     *
     * @param array $cxpayload      The payload containing conflict data.
     * @param int $userid           The ID of the user being processed.
     * @param \context $cx          The context in which the conflicts are evaluated.
     * @param bool $conflictenabled Determines whether conflict processing is enabled.
     * @param bool $overlaponly     Indicates whether only overlaps should be processed, bypassing conflicts.
     * @param array &$counters      Reference to an array tracking counts for new findings, updates, and issues.
     *
     * @return void
     */
    private function process_conflicts(
        array $cxpayload,
        int $userid,
        \context $cx,
        bool $conflictenabled,
        bool $overlaponly,
        array &$counters
    ): void {
        if (!$conflictenabled || $overlaponly) {
            return;
        }
        foreach ($cxpayload['conflicts'] as $cap => $sets) {
            [$created, $fid] = scan_manager::upsert_finding(
                $this->scanid,
                $userid,
                $cx->id,
                $cap,
                'cap_conflict',
                3 + (empty($sets['prohibit']) ? 0 : 1),
                $sets
            );
            $created ? $counters['newfind']++ : $counters['updfind']++;
            $counters['issuecount']++;
            $this->add_issue(
                'cap_conflict',
                'Capability conflict detected',
                [
                    'userid' => $userid,
                    'contextid' => $cx->id,
                    'capability' => $cap,
                    'sets' => $sets,
                ],
                'warning'
            );
        }
    }

    /**
     * Processes capability overlaps for the provided context and updates findings.
     *
     * @param array $cxpayload     The payload containing overlap data.
     * @param int $userid          The ID of the user initiating the process.
     * @param \context $cx         The context in which the overlaps are being processed.
     * @param bool $overlapenabled Indicates whether overlap processing is enabled.
     * @param array &$counters     Reference to an array used to track counters for findings and issues.
     *
     * @return void
     */
    private function process_overlaps(
        array $cxpayload,
        int $userid,
        \context $cx,
        bool $overlapenabled,
        array &$counters
    ): void {
        if (!$overlapenabled) {
            return;
        }
        foreach ($cxpayload['overlaps'] as $cap => $roleids) {
            $sets = ['allow' => array_values($roleids), 'prevent' => [], 'prohibit' => []];
            [$created, $fid] = scan_manager::upsert_finding(
                $this->scanid,
                $userid,
                $cx->id,
                $cap,
                'cap_overlap',
                2,
                $sets
            );
            $created ? $counters['newfind']++ : $counters['updfind']++;
            $counters['issuecount']++;
            $this->add_issue(
                'cap_overlap',
                'Capability overlap detected',
                [
                    'userid' => $userid,
                    'contextid' => $cx->id,
                    'capability' => $cap,
                    'sets' => $sets,
                ],
                'info'
            );
        }
    }

    /**
     * Finalizes a successful scan by updating the scan record with metadata and status.
     *
     * @param int $scanid     The ID of the scan to finalize.
     * @param array $meta     The metadata associated with the scan.
     * @param array $counters An array of counters tracking scan results such as pairs, issues, new findings, and updates.
     *
     * @return void
     */
    private function finalize_scan_success(int $scanid, array $meta, array $counters): void {
        global $DB;
        $DB->update_record(
            'tool_whoiswho_scan',
            (object) [
                'id' => $scanid,
                'finishedat' => time(),
                'status' => 'success',
                'meta' => json_encode(array_merge($meta, [
                    'pairs' => $counters['paircount'],
                    'issues' => $counters['issuecount'],
                    'new' => $counters['newfind'],
                    'updated' => $counters['updfind'],
                ])),
            ]
        );
    }

    /**
     * Finalizes a failed scan by updating the scan record with error details.
     *
     * @param int $scanid   The unique identifier of the scan to finalize.
     * @param \Throwable $e The exception containing error details for the failed scan.
     *
     * @return void
     */
    private function finalize_scan_failure(int $scanid, \Throwable $e): void {
        global $DB;
        $DB->update_record(
            'tool_whoiswho_scan',
            (object) [
                'id' => $scanid,
                'finishedat' => time(),
                'status' => 'failed',
                'meta' => json_encode(['error' => $e->getMessage()]),
            ]
        );
    }

    /**
     * Storage is handled during perform_scan via scan_manager; override to no-op.
     */
    public function store_results(): void {
        // No-op: capability_issue_scanner persists through scan_manager during execution.
    }

}

