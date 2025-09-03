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
 * Fix an issue for a single user, then redirect back to the issues page.
 *
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @package     tool_whoiswho
 * @copyright   02/09/2025 LdesignMedia.nl - Luuk Verhoeven
 * @author      Vincent Cornelis
 **/
require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_whoiswho\form\fix_issue_form;

require_login();

$id = required_param('id', PARAM_INT); // finding id
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

// Load finding.
global $DB;
$finding = $DB->get_record('tool_whoiswho_finding', ['id' => $id], '*', MUST_EXIST);
$context = context::instance_by_id((int) $finding->contextid, MUST_EXIST);

// Permissions: dashboard + role override in this context.
require_capability('tool/whoiswho:dashboardaccess', $context);
require_capability('moodle/role:override', $context);

$url = new moodle_url('/admin/tool/whoiswho/view/fix_issue.php', ['id' => $id]);
if ($returnurl) {
    $url->param('returnurl', $returnurl);
}

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('title:fix_issue', 'tool_whoiswho'));
$PAGE->set_heading(get_string('heading:fix_issue', 'tool_whoiswho'));

// Collect roles assigned to this user in this context.
$assigns = get_user_roles($context, (int) $finding->userid, true);
$rolesdata = [];
foreach ($assigns as $a) {
    $rid = (int) $a->roleid;
    $role = $DB->get_record('role', ['id' => $rid], '*', MUST_EXIST);
    $name = role_get_name($role, $context, ROLENAME_ALIAS);

    // Determine current DIRECT override for this context only (not effective value).
    // This ensures saved changes are immediately reflected in the form defaults.
    $rc = $DB->get_record('role_capabilities', [
        'roleid' => $rid,
        'capability' => $finding->capability,
        'contextid' => $context->id,
    ], '*', IGNORE_MISSING);
    $current = $rc ? (int) $rc->permission : CAP_INHERIT;

    // Effective value (may include parent contexts, prohibitions etc.).
    $effective = null;
    if (function_exists('role_context_capabilities')) {
        $caps = role_context_capabilities($rid, $context);
        if (isset($caps[$finding->capability])) {
            $effective = (int) $caps[$finding->capability];
        }
    }

    $rolesdata[$rid] = ['name' => $name, 'current' => $current, 'effective' => $effective];
}

$mform = new fix_issue_form($url->out(false), [
    'capability' => $finding->capability,
    'contextname' => $context->get_context_name(false, true),
    'roles' => $rolesdata,
    'status' => (string) ($finding->issuestate ?? 'pending'),
]);

if ($mform->is_cancelled()) {
    if ($returnurl) {
        redirect(new moodle_url($returnurl));
    } else {
        redirect(new moodle_url('/admin/tool/whoiswho/view/issues.php'));
    }
}

if ($data = $mform->get_data()) {
    require_sesskey();
    $perm = (array) ($data->perm ?? []);
    $capname = (string) $finding->capability;

    foreach ($rolesdata as $rid => $info) {
        $selected = isset($perm[$rid]) ? (int) $perm[$rid] : 0;
        if ($selected === 0) {
            // Inherit: remove any override at this context.
            if (function_exists('unassign_capability')) {
                unassign_capability($capname, $rid, $context->id);
            } else {
                // Overwrite with inherit (0) if removal helper not available.
                assign_capability($capname, CAP_INHERIT, $rid, $context->id, true);
            }
        } else if (in_array($selected, [CAP_ALLOW, CAP_PREVENT, CAP_PROHIBIT], true)) {
            assign_capability($capname, $selected, $rid, $context->id, true);
        }
    }

    // Best-effort cache invalidation so newly set overrides are visible immediately.
    if (function_exists('accesslib_clear_all_caches')) {
        accesslib_clear_all_caches(true);
    }

    // Update status and resolved flags on the finding (manual control).
    $newstatus = isset($data->status) ? (string) $data->status : 'pending';
    $updated = (object) [
        'id' => $finding->id,
        'issuestate' => in_array($newstatus, ['resolved', 'pending', 'ignored'], true) ? $newstatus : 'pending',
        'resolved' => $newstatus === 'resolved' ? 1 : 0,
        'resolvedby' => $newstatus === 'resolved' ? $USER->id : null,
        'resolvedat' => $newstatus === 'resolved' ? time() : null,
    ];
    $DB->update_record('tool_whoiswho_finding', $updated);

    // Re-scan this user in this context to refresh findings immediately.
    \tool_whoiswho\local\scan_manager::run_users($context, [(int) $finding->userid], $USER->id);

    $msg = get_string('notice:issue_permissions_updated', 'tool_whoiswho');
    if ($returnurl) {
        redirect(new moodle_url($returnurl), $msg);
    } else {
        redirect(new moodle_url('/admin/tool/whoiswho/view/issues.php'), $msg);
    }
}

// Output form.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('heading:fix_issue', 'tool_whoiswho'));
$recheckurl = new moodle_url('/admin/tool/whoiswho/view/recheck_user.php', [
    'userid' => (int) $finding->userid,
    'sesskey' => sesskey(),
    'returnurl' => $url->out_as_local_url(false),
]);
$recheckbtn = new single_button($recheckurl, get_string('action:recheck', 'tool_whoiswho'), 'post');
echo $OUTPUT->render($recheckbtn);
$mform->set_data(['id' => $id]);
$mform->display();
echo $OUTPUT->footer();
