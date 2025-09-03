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
 * PHPUnit tests for capability issue detection in scan_manager.
 *
 * @package    tool_whoiswho
 * @category   test
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use tool_whoiswho\local\scan_manager;

/**
 * Tests for scan_manager capability overlap/conflict detection.
 */
class tool_whoiswho_scan_manager_testcase extends advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
    }

    /**
     * Creates a new role and sets a single capability permission at system level.
     *
     * @param string $shortname Shortname for role.
     * @param string $cap       Capability name.
     * @param int $perm         One of CAP_ALLOW, CAP_PREVENT, CAP_PROHIBIT.
     * @return int Role id.
     */
    protected function create_role_with_cap(string $shortname, string $cap, int $perm): int {
        $sysctx = context_system::instance();
        $roleid = create_role($shortname, $shortname, $shortname . ' description');
        // Set capability at system level so it applies to all contexts.
        assign_capability($cap, $perm, $roleid, $sysctx->id, true);

        return (int) $roleid;
    }

    /**
     * Ensures overlaps are detected when the same capability is ALLOWed by multiple roles
     * assigned to a user within the same course context.
     */
    public function test_detects_overlap_in_course_context(): void {
        $gen = $this->getDataGenerator();
        $user = $gen->create_user();
        $course = $gen->create_course();
        $coursectx = context_course::instance($course->id);

        $cap = 'moodle/course:view';
        $role1 = $this->create_role_with_cap('r1_overlap_allow', $cap, CAP_ALLOW);
        $role2 = $this->create_role_with_cap('r2_overlap_allow', $cap, CAP_ALLOW);

        role_assign($role1, $user->id, $coursectx->id);
        role_assign($role2, $user->id, $coursectx->id);

        $issues = scan_manager::find_capability_issues_for_user($user->id, $coursectx, false);
        $this->assertArrayHasKey($coursectx->id, $issues['contexts']);
        $ctxpayload = $issues['contexts'][$coursectx->id];

        $this->assertArrayHasKey($cap, $ctxpayload['overlaps']);
        $this->assertIsArray($ctxpayload['overlaps'][$cap]);
        $this->assertCount(2, $ctxpayload['overlaps'][$cap], 'Expected two roles causing an overlap');
        $this->assertEmpty($ctxpayload['conflicts'], 'No conflicts expected for pure overlap');
    }

    /**
     * Ensures conflicts are detected at activity (module) context when one role ALLOWs and another
     * PREVENTs/PROHIBITs the same capability.
     */
    public function test_detects_conflict_in_module_context(): void {
        $gen = $this->getDataGenerator();
        $user = $gen->create_user();
        $course = $gen->create_course();

        // Create a Page activity and get its module context.
        $page = $gen->create_module('page', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('page', $page->id);
        $modctx = context_module::instance($cm->id);

        $cap = 'mod/page:view';
        $roleallow = $this->create_role_with_cap('r_allow', $cap, CAP_ALLOW);
        $roleprevent = $this->create_role_with_cap('r_prevent', $cap, CAP_PREVENT);

        role_assign($roleallow, $user->id, $modctx->id);
        role_assign($roleprevent, $user->id, $modctx->id);

        $issues = scan_manager::find_capability_issues_for_user($user->id, $modctx, false);
        $this->assertArrayHasKey($modctx->id, $issues['contexts']);
        $ctxpayload = $issues['contexts'][$modctx->id];

        $this->assertArrayHasKey($cap, $ctxpayload['conflicts']);
        $conf = $ctxpayload['conflicts'][$cap];
        $this->assertNotEmpty($conf['allow']);
        $this->assertNotEmpty($conf['prevent']);
        $this->assertIsArray($conf['prohibit']);
        $this->assertEmpty($conf['prohibit'], 'No prohibit roles used in this scenario');
    }

    /**
     * When includeparents is true, the result set includes entries for parent contexts.
     */
    public function test_include_parents_adds_parent_contexts(): void {
        $gen = $this->getDataGenerator();
        $user = $gen->create_user();
        $course = $gen->create_course();
        $page = $gen->create_module('page', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('page', $page->id);

        $coursectx = context_course::instance($course->id);
        $modctx = context_module::instance($cm->id);

        // Assign a role only at course context.
        $cap = 'moodle/course:view';
        $role = $this->create_role_with_cap('r_parent', $cap, CAP_ALLOW);
        role_assign($role, $user->id, $coursectx->id);

        $issues = scan_manager::find_capability_issues_for_user($user->id, $modctx, true);

        // Expect both module and course contexts present.
        $this->assertArrayHasKey($modctx->id, $issues['contexts']);
        $this->assertArrayHasKey($coursectx->id, $issues['contexts']);

        // The module context entry should have no direct roles.
        $this->assertEmpty($issues['contexts'][$modctx->id]['roles']);
        // The parent course context should include the assigned role.
        $this->assertNotEmpty($issues['contexts'][$coursectx->id]['roles']);
    }

    /**
     * Fingerprint should be order-insensitive and stable for the same sets.
     */
    public function test_fingerprint_is_order_insensitive(): void {
        $uid = 42;
        $ctxid = 99;
        $cap = 'moodle/some:cap';

        $sets1 = [
            'allow' => [5, 2, 7],
            'prevent' => [3],
            'prohibit' => [11, 11],
        ];
        $sets2 = [
            'allow' => [7, 5, 2], // different order
            'prevent' => [3],
            'prohibit' => [11], // duplicates removed
        ];

        $f1 = scan_manager::fingerprint($uid, $ctxid, $cap, $sets1);
        $f2 = scan_manager::fingerprint($uid, $ctxid, $cap, $sets2);

        $this->assertSame($f1, $f2, 'Fingerprint should be order-insensitive and normalized');
    }

}

