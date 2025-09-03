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
 * @package     tool_whoiswho
 * @copyright   02/09/2025 LdesignMedia.nl - Luuk Verhoeven
 * @author      Luuk Verhoeven
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

namespace tool_whoiswho;

use advanced_testcase;
use context_course;
use context_module;
use context_system;
use tool_whoiswho\local\scan_manager;

/**
 * Tests for scan_manager capability overlap/conflict detection.
 *
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @package     tool_whoiswho
 * @copyright   02/09/2025 LdesignMedia.nl - Luuk Verhoeven
 * @author      Luuk Verhoeven
 **/
class scan_manager_test extends advanced_testcase {

    /**
     * Sets up the testing environment before each test is run.
     *
     * This method initializes the testing environment by calling the parent setup
     * method, resetting the state to ensure consistency between tests, and setting
     * the current user as the admin user.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
    }

    /**
     * Creates a new role with a specific capability and permission.
     *
     * This method creates a role with a given shortname, assigns a specified capability
     * to the role with a corresponding permission at the system context level, and
     * returns the ID of the created role.
     *
     * @param string $shortname The shortname for the role to be created.
     * @param string $cap       The capability to assign to the created role.
     * @param int $perm         The permission level to assign for the capability (e.g., CAP_ALLOW, CAP_PREVENT).
     * @return int The ID of the created role.
     */
    protected function create_role_with_cap(string $shortname, string $cap, int $perm): int {
        $sysctx = context_system::instance();
        $roleid = create_role($shortname, $shortname, $shortname . ' description');
        // Set capability at system level so it applies to all contexts.
        assign_capability($cap, $perm, $roleid, $sysctx->id, true);

        return (int) $roleid;
    }

    /**
     * Tests whether a conflict is correctly detected in a module context.
     *
     * The method checks if capability conflicts exist for a user within a specific
     * module context when conflicting role assignments are made. It ensures that
     * the detected conflicts for the given capability are categorized appropriately.
     *
     * @covers ::find_capability_issues_for_user
     * @return void
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
     * Tests that enabling the inclusion of parent contexts correctly adds parent contexts,
     * ensuring the presence of capabilities inherited from those contexts.
     *
     * @covers ::find_capability_issues_for_user
     * @return void
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
     * Tests that the fingerprinting process is order-insensitive and normalized.
     *
     * This method verifies that two sets of role capability assignments produce
     * identical fingerprints, even when their internal ordering differs or when
     * duplicate entries are present in the input data. It ensures consistency in
     * fingerprint computation for the given user, context, capability, and role sets.
     *
     * @covers ::fingerprint
     * @return void
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
            'allow' => [7, 5, 2], // Different order.
            'prevent' => [3],
            'prohibit' => [11], // Duplicates removed.
        ];

        $f1 = scan_manager::fingerprint($uid, $ctxid, $cap, $sets1);
        $f2 = scan_manager::fingerprint($uid, $ctxid, $cap, $sets2);

        $this->assertSame($f1, $f2, 'Fingerprint should be order-insensitive and normalized');
    }

    /**
     * Ensures no conflict is reported when a child-context ALLOW overrides a parent PREVENT.
     *
     * Scenario: user is assigned a PREVENT-capable role at course level (e.g., student)
     * and an ALLOW-capable role at a child module context (e.g., teacher). The tool should
     * not flag a conflict in the module context because the more specific ALLOW wins and
     * there is no PROHIBIT involved.
     *
     * @covers ::find_capability_issues_for_user
     * @return void
     */
    public function test_no_conflict_when_child_allow_overrides_parent_prevent(): void {
        $gen = $this->getDataGenerator();
        $user = $gen->create_user();
        $course = $gen->create_course();
        $coursectx = context_course::instance($course->id);

        // Create a module and get its context.
        $page = $gen->create_module('page', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('page', $page->id);
        $modctx = context_module::instance($cm->id);

        $cap = 'mod/assign:grade'; // Common teacher-only capability; students are typically prevented.

        // Create roles without system-level capabilities.
        $sysctx = context_system::instance();
        $roleprevent = create_role('r_parent_prevent', 'r_parent_prevent', 'r_parent_prevent description');
        $roleallow = create_role('r_child_allow', 'r_child_allow', 'r_child_allow description');

        // Set PREVENT at course level and ALLOW at module level to demonstrate override.
        assign_capability($cap, CAP_PREVENT, $roleprevent, $coursectx->id, true);
        assign_capability($cap, CAP_ALLOW, $roleallow, $modctx->id, true);

        // Assign roles to user.
        role_assign($roleprevent, $user->id, $coursectx->id);
        role_assign($roleallow, $user->id, $modctx->id);

        $issues = scan_manager::find_capability_issues_for_user($user->id, $modctx, false);
        $this->assertArrayHasKey($modctx->id, $issues['contexts']);
        $ctxpayload = $issues['contexts'][$modctx->id];

        // No conflict expected: child ALLOW is more specific than parent PREVENT.
        $this->assertArrayNotHasKey($cap, $ctxpayload['conflicts']);
    }

}
