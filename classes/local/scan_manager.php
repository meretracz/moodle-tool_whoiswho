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
 * Scan manager
 *
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @package   tool_whoiswho
 * @copyright 02/09/2025 Mfreak.nl | LdesignMedia.nl - Luuk Verhoeven
 * @author    Luuk Verhoeven
 **/

namespace tool_whoiswho\local;

/**
 * Class scan_manager
 *
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @package   tool_whoiswho
 * @copyright 02/09/2025 Mfreak.nl | LdesignMedia.nl - Luuk Verhoeven
 * @author    Luuk Verhoeven
 **/
class scan_manager {

    /**
     * Executes a scan to analyze capability issues, identify conflicts and overlaps,
     * and records findings in the database for the provided context.
     *
     * @param \context|null $rootcontext The root context to limit the scan to, or null for a global scan.
     * @param int|null $initiatedby      The ID of the user who initiated the scan, or null if unspecified.
     * @return void No return value.
     * @throws \Throwable If an error occurs during the execution of the scan.
     */
    public static function run(?\context $rootcontext = null, ?int $initiatedby = null): void {
        global $DB;

        $cfg = get_config('tool_whoiswho');
        $overlapenabled = !empty($cfg->scan_overlap_enabled);
        $conflictenabled = !empty($cfg->scan_conflict_enabled);
        $includeparents = !empty($cfg->scan_include_parents);
        $levels = [];
        if (!empty($cfg->scan_contextlevels)) {
            $levels = array_values(
                array_filter(
                    array_map(
                        'intval',
                        preg_split('/[,\s]+/', (string) $cfg->scan_contextlevels)
                    )
                )
            );
        }

        $now = time();
        $scan = (object) [
            'startedat' => $now,
            'finishedat' => null,
            'status' => 'running',
            'initiatedby' => $initiatedby,
            'scopecontextid' => $rootcontext ? $rootcontext->id : null,
            'meta' => null,
        ];
        $scanid = $DB->insert_record('tool_whoiswho_scan', $scan);

        try {
            $params = [];
            $wheres = [];
            if ($rootcontext) {
                // Limit role assignments to the specified context subtree.
                $wheres[] = 'ra.contextid IN (SELECT ctx.id FROM {context} ctx WHERE ctx.path LIKE :cxpath)';
                $params['cxpath'] = $rootcontext->path . '%';
            }

            if (!empty($levels)) {
                [$insql, $inparams] = $DB->get_in_or_equal($levels, SQL_PARAMS_NAMED, 'lvl');
                $wheres[] = "ctx.contextlevel $insql";
                $params = array_merge($params, $inparams);
            }

            $where = $wheres ? ('WHERE ' . implode(' AND ', $wheres)) : '';

            $sql = "SELECT DISTINCT ra.userid, ra.contextid
                      FROM {role_assignments} ra
                      JOIN {context} ctx ON ctx.id = ra.contextid
                    $where";

            $rs = $DB->get_recordset_sql($sql, $params);
            $count = 0;
            $newfind = 0;
            $updfind = 0;
            foreach ($rs as $rec) {
                $count++;
                $user = (int) $rec->userid;
                $cx = \context::instance_by_id((int) $rec->contextid, MUST_EXIST);
                $issues = self::find_capability_issues_for_user($user, $cx, $includeparents);

                if (!isset($issues['contexts'][$cx->id])) {
                    // No issues found - clean up any existing resolved findings for this user/context.
                    self::cleanup_resolved_findings($user, $cx->id);
                    continue;
                }

                $cxpayload = $issues['contexts'][$cx->id];

                // Track which issues are still active.
                $activeissues = [];

                // Conflicts first (higher severity).
                if ($conflictenabled) {
                    foreach ($cxpayload['conflicts'] as $cap => $sets) {
                        $activeissues[] = self::fingerprint($user, $cx->id, $cap, $sets);
                        [$created] = self::upsert_finding(
                            $scanid,
                            $user,
                            $cx->id,
                            $cap,
                            'cap_conflict',
                            3 + (empty($sets['prohibit']) ? 0 : 1),
                            $sets
                        );
                        $created ? $newfind++ : $updfind++;
                    }
                }
                // Overlaps next (lower severity).
                if ($overlapenabled) {
                    foreach ($cxpayload['overlaps'] as $cap => $roleids) {
                        $sets = ['allow' => array_values($roleids), 'prevent' => [], 'prohibit' => []];
                        $activeissues[] = self::fingerprint($user, $cx->id, $cap, $sets);
                        [$created] = self::upsert_finding(
                            $scanid,
                            $user,
                            $cx->id,
                            $cap,
                            'cap_overlap',
                            2,
                            $sets
                        );
                        $created ? $newfind++ : $updfind++;
                    }
                }

                // Clean up resolved findings that no longer have issues.
                self::cleanup_resolved_findings($user, $cx->id, $activeissues);
            }
            $rs->close();

            // Finish scan.
            $scanupd = (object) [
                'id' => $scanid,
                'finishedat' => time(),
                'status' => 'success',
                'meta' => json_encode(['pairs' => $count, 'new' => $newfind, 'updated' => $updfind]),
            ];
            $DB->update_record('tool_whoiswho_scan', $scanupd);

        } catch (\Throwable $e) {
            $DB->update_record(
                'tool_whoiswho_scan',
                (object) [
                    'id' => $scanid,
                    'finishedat' => time(),
                    'status' => 'failed',
                    'meta' => json_encode(['error' => $e->getMessage()]),
                ]
            );
            throw $e;
        }
    }

    /**
     * Upsert a finding in the system by either creating a new record or updating an existing one.
     *
     * @param int $scanid        The ID of the scan associated with the finding.
     * @param int $userid        The ID of the user related to the finding.
     * @param int $contextid     The ID of the context associated with the finding.
     * @param string $capability The capability linked to the finding.
     * @param string $type       The type of the finding (e.g., issue or observation classification).
     * @param int $severity      The severity level of the finding.
     * @param array $sets        An array containing capability sets for allow, prevent, and prohibit.
     * @return array Returns an array with a boolean indicating if a new record was created
     *                           and the ID of the finding.
     */
    public static function upsert_finding(
        int $scanid,
        int $userid,
        int $contextid,
        string $capability,
        string $type,
        int $severity,
        array $sets
    ): array {
        global $DB;

        $finger = self::fingerprint(
            $userid,
            $contextid,
            $capability,
            $sets
        );
        $now = time();

        $existing = $DB->get_record(
            'tool_whoiswho_finding',
            ['fingerprint' => $finger],
        );

        if ($existing) {
            $keepmanual = property_exists($existing, 'issuestate') && in_array($existing->issuestate, ['resolved', 'ignored'], true);
            $existing->lastseenat = $now;
            if ($keepmanual) {
                // Honor manual resolution/ignored: keep flags and state.
            } else {
                $existing->resolved = 0;
                if (property_exists($existing, 'issuestate')) {
                    $existing->issuestate = 'pending';
                }
            }
            $DB->update_record('tool_whoiswho_finding', $existing);
            $findingid = (int) $existing->id;

            // Refresh detail rows.
            $DB->delete_records('tool_whoiswho_finding_cap', ['findingid' => $findingid]);
            self::insert_cap_rows($findingid, $capability, $sets);

            return [false, $findingid];
        }

        $details = json_encode([
            'allow' => array_values($sets['allow'] ?? []),
            'prevent' => array_values($sets['prevent'] ?? []),
            'prohibit' => array_values($sets['prohibit'] ?? []),
        ]);

        $finding = (object) [
            'fingerprint' => $finger,
            'scanid' => $scanid,
            'type' => $type,
            'severity' => $severity,
            'userid' => $userid,
            'contextid' => $contextid,
            'capability' => $capability,
            'issuestate' => 'pending',
            'firstseenat' => $now,
            'lastseenat' => $now,
            'resolved' => 0,
            'resolvedby' => null,
            'resolvedat' => null,
            'details' => $details,
        ];

        $findingid = $DB->insert_record('tool_whoiswho_finding', $finding);
        self::insert_cap_rows($findingid, $capability, $sets);

        return [true, (int) $findingid];
    }

    /**
     * Inserts capability rows into the database for a specific finding.
     *
     * @param int $findingid  The ID of the finding record for which capability rows are being added.
     * @param string $capname The name of the capability associated with the roles.
     * @param array $sets     An associative array containing role IDs categorized by permission types
     *                        (e.g., 'allow', 'prevent', 'prohibit').
     *                        Each key maps to an array of role IDs with the corresponding permission.
     * @return void
     */
    public static function insert_cap_rows(int $findingid, string $capname, array $sets): void {
        global $DB;

        $capvalues = [
            'allow' => CAP_ALLOW,
            'prevent' => CAP_PREVENT,
            'prohibit' => CAP_PROHIBIT,
        ];

        foreach ($capvalues as $label => $perm) {
            $roleids = array_values($sets[$label] ?? []);
            foreach ($roleids as $rid) {
                $DB->insert_record(
                    'tool_whoiswho_finding_cap',
                    (object) [
                        'findingid' => $findingid,
                        'roleid' => (int) $rid,
                        'permission' => (int) $perm,
                        'capname' => $capname,
                        'label' => $label,
                    ]
                );
            }
        }
    }

    /**
     * Compute capability overlaps and conflicts for a user within a context (optionally parents).
     *
     * @param int $userid
     * @param \context|null $context
     * @param bool $includeparents
     *
     * @return array
     */
    public static function find_capability_issues_for_user(
        int $userid,
        ?\context $context = null,
        bool $includeparents = false
    ): array {
        global $DB;

        $context = $context ?: \context_system::instance();
        $contexts = [$context];

        if ($includeparents) {
            $parents = $context->get_parent_contexts(true);
            foreach ($parents as $p) {
                if ($p instanceof \context) {
                    $contexts[] = $p;
                } else {
                    $contexts[] = \context::instance_by_id((int) $p, MUST_EXIST);
                }
            }
        }

        $result = [
            'userid' => $userid,
            'contexts' => [],
        ];

        foreach ($contexts as $cx) {
            // Include roles inherited from parent contexts so module-level checks
            // see course/category/system assignments too.
            $assigns = get_user_roles($cx, $userid, true);
            if (empty($assigns)) {
                $result['contexts'][$cx->id] = [
                    'contextid' => $cx->id,
                    'contextname' => self::context_name($cx),
                    'roles' => [],
                    'overlaps' => [],
                    'conflicts' => [],
                    'stats' => ['roles' => 0, 'caps_checked' => 0, 'overlap_caps' => 0, 'conflict_caps' => 0],
                ];
                continue;
            }

            $roles = [];
            $capmatrix = [];

            foreach ($assigns as $a) {
                $roleid = (int) $a->roleid;
                $roles[$roleid] = $roles[$roleid] ?? self::role_name($roleid);

                // Only check capabilities at this specific context level.
                // Conflicts should be detected based on direct overrides at this context,
                // not effective permissions that include inheritance.
                $caps = [];
                $records = $DB->get_records('role_capabilities', [
                    'roleid' => $roleid,
                    'contextid' => $cx->id
                ]);
                foreach ($records as $rc) {
                    // Only include non-inherit permissions.
                    if ((int) $rc->permission !== CAP_INHERIT) {
                        $caps[$rc->capability] = (int) $rc->permission;
                    }
                }

                foreach ($caps as $capname => $perm) {
                    $capmatrix[$capname][$roleid] = (int) $perm;
                }
            }

            $overlaps = [];
            $conflicts = [];
            $capschecked = 0;

            foreach ($capmatrix as $cap => $roleperms) {
                $capschecked++;

                $allow = [];
                $prevent = [];
                $prohibit = [];

                foreach ($roleperms as $rid => $perm) {
                    if ($perm === CAP_ALLOW) {
                        $allow[] = $rid;
                    } else if ($perm === CAP_PROHIBIT) {
                        $prohibit[] = $rid;
                    } else if ($perm === CAP_PREVENT) {
                        $prevent[] = $rid;
                    }
                }

                if (count($allow) > 1) {
                    $overlaps[$cap] = $allow;
                }

                if (!empty($allow) && (!empty($prevent) || !empty($prohibit))) {
                    $conflicts[$cap] = [
                        'allow' => $allow,
                        'prevent' => $prevent,
                        'prohibit' => $prohibit,
                    ];
                }
            }

            $result['contexts'][$cx->id] = [
                'contextid' => $cx->id,
                'contextname' => self::context_name($cx),
                'roles' => $roles,
                'overlaps' => $overlaps,
                'conflicts' => $conflicts,
                'stats' => [
                    'roles' => count($roles),
                    'caps_checked' => $capschecked,
                    'overlap_caps' => count($overlaps),
                    'conflict_caps' => count($conflicts),
                ],
            ];
        }

        return $result;
    }

    /**
     * Generates a unique fingerprint for a specified user, context, capability, and roles mapping.
     *
     * @param int $userid        The user ID for which the fingerprint is being generated.
     * @param int $contextid     The context ID related to the fingerprint.
     * @param string $capability The capability associated with the context and user.
     * @param array $rolesmap    An associative array of roles mapping, containing 'allow', 'prevent', and 'prohibit' keys with
     *                           arrays of role IDs.
     * @return string A SHA-1 hash representing a unique fingerprint of the provided data.
     */
    public static function fingerprint(int $userid, int $contextid, string $capability, array $rolesmap): string {
        $norm = [
            'a' => array_values(array_unique(array_map('intval', $rolesmap['allow'] ?? []))),
            'p' => array_values(array_unique(array_map('intval', $rolesmap['prevent'] ?? []))),
            'x' => array_values(array_unique(array_map('intval', $rolesmap['prohibit'] ?? []))),
        ];
        sort($norm['a']);
        sort($norm['p']);
        sort($norm['x']);
        $payload = json_encode([$userid, $contextid, $capability, $norm]);

        return sha1((string) $payload);
    }

    /**
     * Retrieves the name of a role based on its ID.
     *
     * This method fetches the role record from the database and returns its name.
     * If the role cannot be found, a default string with the role ID is returned.
     *
     * @param int $roleid The unique identifier of the role.
     * @return string The name of the role, or a default placeholder string if the role is missing.
     */
    private static function role_name(int $roleid): string {
        global $DB;
        $role = $DB->get_record('role', ['id' => $roleid], '*', IGNORE_MISSING);
        if ($role) {
            return role_get_name($role, \context_system::instance());
        }

        return "role:{$roleid}";
    }

    /**
     * Retrieves the name of a given context.
     *
     * @param \context $context The context instance for which the name is to be retrieved.
     * @return string The name of the provided context.
     */
    private static function context_name(\context $context): string {
        return $context->get_context_name(false, true);
    }

    /**
     * Initiates an overlap scan, processes user/context pairs, and records any findings in the database.
     *
     * @param \context|null $rootcontext The root context to define the scope of the scan. If null, the scan will not be limited to
     *                                   a specific context.
     * @param array|null $userids        An optional array of user IDs to limit the scan. If null, the scan will include all users.
     * @param int|null $initiatedby      The ID of the user who initiated the scan. Can be null for system-level initiation.
     * @return void This method does not return a value but performs database operations related to the scan.
     */
    public static function run_overlap(?\context $rootcontext = null, ?array $userids = null, ?int $initiatedby = null): void {
        global $DB;

        $cfg = get_config('tool_whoiswho');
        $includeparents = !empty($cfg->scan_include_parents);
        $levels = [];
        if (!empty($cfg->scan_contextlevels)) {
            $levels = array_values(
                array_filter(
                    array_map(
                        'intval',
                        preg_split('/[,\s]+/', (string) $cfg->scan_contextlevels)
                    )
                )
            );
        }

        $scan = (object) [
            'startedat' => time(),
            'finishedat' => null,
            'status' => 'running',
            'initiatedby' => $initiatedby,
            'scopecontextid' => $rootcontext ? $rootcontext->id : null,
            'meta' => json_encode(['mode' => 'adhoc-overlap']),
        ];
        $scanid = $DB->insert_record('tool_whoiswho_scan', $scan);

        try {
            [$pairs, $meta] = self::select_pairs($rootcontext, (array) $userids, $levels);
            $new = 0;
            $upd = 0;
            $count = 0;
            foreach ($pairs as $p) {
                $count++;
                $userid = (int) $p['userid'];
                $cx = \context::instance_by_id((int) $p['contextid'], MUST_EXIST);
                $issues = self::find_capability_issues_for_user($userid, $cx, $includeparents);
                if (empty($issues['contexts'][$cx->id])) {
                    continue;
                }
                $cxpayload = $issues['contexts'][$cx->id];
                foreach ($cxpayload['overlaps'] as $cap => $roleids) {
                    $sets = ['allow' => array_values($roleids), 'prevent' => [], 'prohibit' => []];
                    [$created] = self::upsert_finding($scanid, $userid, $cx->id, $cap, 'cap_overlap', 2, $sets);
                    $created ? $new++ : $upd++;
                }
            }

            $DB->update_record(
                'tool_whoiswho_scan',
                (object) [
                    'id' => $scanid,
                    'finishedat' => time(),
                    'status' => 'success',
                    'meta' => json_encode(array_merge(['mode' => 'adhoc-overlap'], $meta, [
                        'pairs' => $count,
                        'new' => $new,
                        'updated' => $upd,
                    ])),
                ]
            );

        } catch (\Throwable $e) {
            $DB->update_record(
                'tool_whoiswho_scan',
                (object) [
                    'id' => $scanid,
                    'finishedat' => time(),
                    'status' => 'failed',
                    'meta' => json_encode(['mode' => 'adhoc-overlap', 'error' => $e->getMessage()]),
                ]
            );
            throw $e;
        }
    }

    /**
     * Run an adhoc scan for specific users, detecting both conflicts and overlaps.
     *
     * @param \context|null $rootcontext Optional root context to limit scope.
     * @param array|null $userids         List of user IDs to evaluate. If empty, nothing is scanned.
     * @param int|null $initiatedby       User ID who initiated the scan (for audit purposes).
     * @return void
     */
    public static function run_users(?\context $rootcontext = null, ?array $userids = null, ?int $initiatedby = null): void {
        global $DB;

        $userids = array_values(array_filter(array_map('intval', (array) $userids)));
        if (empty($userids)) {
            return;
        }

        $cfg = get_config('tool_whoiswho');
        $overlapenabled = !empty($cfg->scan_overlap_enabled);
        $conflictenabled = !empty($cfg->scan_conflict_enabled);
        $includeparents = !empty($cfg->scan_include_parents);
        $levels = [];
        if (!empty($cfg->scan_contextlevels)) {
            $levels = array_values(
                array_filter(
                    array_map(
                        'intval',
                        preg_split('/[,\s]+/', (string) $cfg->scan_contextlevels)
                    )
                )
            );
        }

        $scan = (object) [
            'startedat' => time(),
            'finishedat' => null,
            'status' => 'running',
            'initiatedby' => $initiatedby,
            'scopecontextid' => $rootcontext ? $rootcontext->id : null,
            'meta' => json_encode(['mode' => 'adhoc-users', 'users' => count($userids)]),
        ];
        $scanid = $DB->insert_record('tool_whoiswho_scan', $scan);

        try {
            [$pairs, $meta] = self::select_pairs($rootcontext, $userids, $levels);
            $new = 0;
            $upd = 0;
            $count = 0;
            foreach ($pairs as $p) {
                $count++;
                $userid = (int) $p['userid'];
                $cx = \context::instance_by_id((int) $p['contextid'], MUST_EXIST);
                $issues = self::find_capability_issues_for_user($userid, $cx, $includeparents);
                if (empty($issues['contexts'][$cx->id])) {
                    // No issues found - clean up any existing resolved findings for this user/context.
                    self::cleanup_resolved_findings($userid, $cx->id);
                    continue;
                }
                $cxpayload = $issues['contexts'][$cx->id];

                // Track which issues are still active.
                $activeissues = [];

                if ($conflictenabled) {
                    foreach ($cxpayload['conflicts'] as $cap => $sets) {
                        $activeissues[] = self::fingerprint($userid, $cx->id, $cap, $sets);
                        [$created] = self::upsert_finding(
                            $scanid,
                            $userid,
                            $cx->id,
                            $cap,
                            'cap_conflict',
                            3 + (empty($sets['prohibit']) ? 0 : 1),
                            $sets
                        );
                        $created ? $new++ : $upd++;
                    }
                }

                if ($overlapenabled) {
                    foreach ($cxpayload['overlaps'] as $cap => $roleids) {
                        $sets = ['allow' => array_values($roleids), 'prevent' => [], 'prohibit' => []];
                        $activeissues[] = self::fingerprint($userid, $cx->id, $cap, $sets);
                        [$created] = self::upsert_finding(
                            $scanid,
                            $userid,
                            $cx->id,
                            $cap,
                            'cap_overlap',
                            2,
                            $sets
                        );
                        $created ? $new++ : $upd++;
                    }
                }

                // Clean up resolved findings that no longer have issues.
                self::cleanup_resolved_findings($userid, $cx->id, $activeissues);
            }

            $DB->update_record(
                'tool_whoiswho_scan',
                (object) [
                    'id' => $scanid,
                    'finishedat' => time(),
                    'status' => 'success',
                    'meta' => json_encode(array_merge(['mode' => 'adhoc-users'], $meta, [
                        'pairs' => $count,
                        'new' => $new,
                        'updated' => $upd,
                    ])),
                ]
            );

        } catch (\Throwable $e) {
            $DB->update_record(
                'tool_whoiswho_scan',
                (object) [
                    'id' => $scanid,
                    'finishedat' => time(),
                    'status' => 'failed',
                    'meta' => json_encode(['mode' => 'adhoc-users', 'error' => $e->getMessage()]),
                ]
            );
            throw $e;
        }
    }

    /**
     * Selects distinct user/context pairs based on various criteria and returns the results along with metadata.
     *
     * @param \context|null $rootctx The context to limit the selection to (can include all its subcontexts). If null, no context
     *                               restriction is applied.
     * @param array $userids         A list of user IDs to filter the pairs by. If empty, no user restriction is applied.
     * @param array $levels          A list of context levels to filter the pairs by. If empty, no level restriction is applied.
     * @return array An array containing two elements:
     *                               [0] An array of selected pairs, where each pair is an associative array with 'userid' and
     *                               'contextid' keys.
     *                               [1] Metadata describing the scope of the query and additional information.
     */
    public static function select_pairs(?\context $rootctx, array $userids, array $levels): array {
        global $DB;

        if (!empty($userids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
            $params = $inparams;
            $where = "ra.userid $insql";
            if ($rootctx) {
                $where .= " AND ctx.path LIKE :cxpath";
                $params['cxpath'] = $rootctx->path . '%';
            }

            // Fetch base role assignment contexts without restricting by level,
            // so we can expand to descendant module contexts if requested.
            $records = $DB->get_records_sql(
                "SELECT DISTINCT ra.userid, ra.contextid, ctx.contextlevel AS ctxlevel, ctx.path AS ctxpath
                   FROM {role_assignments} ra
                   JOIN {context} ctx ON ctx.id = ra.contextid
                  WHERE $where",
                $params
            );

            $needmodules = in_array(CONTEXT_MODULE, $levels ?? [], true);
            $uniq = [];
            $pairs = [];
            $childcache = [];

            foreach ($records as $r) {
                $uid = (int) $r->userid;
                $cxid = (int) $r->contextid;
                $cxlevel = (int) $r->ctxlevel;

                // Include the base context if no level filter or level is requested.
                if (empty($levels) || in_array($cxlevel, $levels, true)) {
                    $key = $uid . '-' . $cxid;
                    if (!isset($uniq[$key])) {
                        $uniq[$key] = true;
                        $pairs[] = ['userid' => $uid, 'contextid' => $cxid];
                    }
                }

                // Expand to descendant module contexts when requested.
                if ($needmodules) {
                    $ppath = (string) $r->ctxpath;
                    if (!isset($childcache[$ppath])) {
                        $childcache[$ppath] = $DB->get_fieldset_sql(
                            "SELECT id FROM {context} WHERE path LIKE :pp AND contextlevel = :lvl",
                            ['pp' => $ppath . '%', 'lvl' => CONTEXT_MODULE]
                        );
                    }
                    foreach ($childcache[$ppath] as $childid) {
                        $key = $uid . '-' . (int) $childid;
                        if (!isset($uniq[$key])) {
                            $uniq[$key] = true;
                            $pairs[] = ['userid' => $uid, 'contextid' => (int) $childid];
                        }
                    }
                }
            }

            // Always include the explicit root context itself when provided,
            // so module-level checks run even if role assignments are only in parents.
            if ($rootctx) {
                foreach ($userids as $uid) {
                    $key = ((int) $uid) . '-' . $rootctx->id;
                    if (!isset($uniq[$key])) {
                        $uniq[$key] = true;
                        $pairs[] = ['userid' => (int) $uid, 'contextid' => (int) $rootctx->id];
                    }
                }
            }

            $meta = ['scope' => 'users' . ($rootctx ? '+ctx' : ''), 'users' => count($userids)];
            return [$pairs, $meta];
        }

        if ($rootctx) {
            $where = 'ctx.path LIKE :cxpath';
            $params = ['cxpath' => $rootctx->path . '%'];
            if (!empty($levels)) {
                [$lvlsql, $lvlparams] = $DB->get_in_or_equal($levels, SQL_PARAMS_NAMED, 'lvl');
                $where .= " AND ctx.contextlevel $lvlsql";
                $params = array_merge($params, $lvlparams);
            }
            $records = $DB->get_records_sql(
                "SELECT DISTINCT ra.userid, ra.contextid
                   FROM {role_assignments} ra
                   JOIN {context} ctx ON ctx.id = ra.contextid
                  WHERE $where",
                $params
            );
            $pairs = array_map(function ($r) {
                return ['userid' => (int) $r->userid, 'contextid' => (int) $r->contextid];
            }, $records);
            $meta = ['scope' => 'ctx', 'contextid' => $rootctx->id];

            return [$pairs, $meta];
        }

        // All pairs.
        $where = '';
        $params = [];
        if (!empty($levels)) {
            [$lvlsql, $lvlparams] = $DB->get_in_or_equal($levels, SQL_PARAMS_NAMED, 'lvl');
            $where = "WHERE ctx.contextlevel $lvlsql";
            $params = $lvlparams;
        }
        $records = $DB->get_records_sql(
            "SELECT DISTINCT ra.userid, ra.contextid
               FROM {role_assignments} ra
               JOIN {context} ctx ON ctx.id = ra.contextid
             $where",
            $params
        );
        $pairs = array_map(function ($r) {
            return ['userid' => (int) $r->userid, 'contextid' => (int) $r->contextid];
        }, $records);
        $meta = ['scope' => 'all'];

        return [$pairs, $meta];
    }

    /**
     * Clean up resolved findings that no longer have active issues.
     *
     * @param int $userid The user ID.
     * @param int $contextid The context ID.
     * @param array $activeissues Array of fingerprints for issues that are still active.
     * @return void
     */
    private static function cleanup_resolved_findings(int $userid, int $contextid, array $activeissues = []): void {
        global $DB;

        // Get all existing findings for this user/context that are marked as resolved.
        $sql = "SELECT id, fingerprint, issuestate
                  FROM {tool_whoiswho_finding}
                 WHERE userid = :userid
                   AND contextid = :contextid
                   AND issuestate IN ('resolved', 'pending')";

        $findings = $DB->get_records_sql($sql, ['userid' => $userid, 'contextid' => $contextid]);

        foreach ($findings as $finding) {
            // If this finding's fingerprint is not in the list of active issues,
            // it means the issue has been fixed and the finding should be removed.
            if (!in_array($finding->fingerprint, $activeissues)) {
                // Delete the finding and its related capability records.
                $DB->delete_records('tool_whoiswho_finding_cap', ['findingid' => $finding->id]);
                $DB->delete_records('tool_whoiswho_finding', ['id' => $finding->id]);
            }
        }
    }

}
