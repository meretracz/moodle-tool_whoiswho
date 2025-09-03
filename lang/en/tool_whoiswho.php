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
 * EN language file
 *
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @package     tool_whoiswho
 * @copyright   02/09/2025 LdesignMedia.nl - Luuk Verhoeven
 * @author      Vincent Cornelis
 **/

// phpcs:disable moodle.Files.LangFilesOrdering.UnexpectedComment
// phpcs:disable moodle.Files.LangFilesOrdering.IncorrectOrder

// Default.
$string['pluginname'] = 'Who is who';

// Capabilities.
$string['whoiswho:dashboardaccess'] = 'Dasboard access';
$string['whoiswho:receiveconflictnotification'] = 'Receive conflict notifications';

// Setting headings.
$string['settings:heading:reports'] = 'Report configuration';
$string['settings:heading:reports_desc'] = 'Configure options here that will be reflected in the reports.';

// Settings.
$string['settings:whoiswhoconfig'] = 'Configuration';
$string['settings:profilefields'] = 'Profile fields';
$string['settings:profilefields_desc'] = 'Select the profile fields you want to display on the dashboard.';

// Contexts.
$string['contextsystem'] = 'System context';
$string['contextcoursecat'] = 'Course category context';
$string['contextcourse'] = 'Course context';
$string['contextmodule'] = 'Module context';
$string['contextuser'] = 'User context';
$string['contextblock'] = 'Block context';

// Scanner settings.
$string['settings:heading:scanner'] = 'Scanner options';
$string['settings:heading:scanner_desc'] = 'Control what the background scans check and where they run.';
$string['settings:scan_overlap_enabled'] = 'Detect overlapping capabilities';
$string['settings:scan_overlap_enabled_desc'] = 'Flag when the same capability is ALLOWed by multiple roles in the same context.';
$string['settings:scan_conflict_enabled'] = 'Detect conflicting capabilities';
$string['settings:scan_conflict_enabled_desc'] = 'Flag when a capability is both ALLOWed and PREVENT/PROHIBITed by assigned roles.';
$string['settings:scan_include_parents'] = 'Check parent contexts separately';
$string['settings:scan_include_parents_desc'] = 'For each user/context pair, also analyse each parent context.';
$string['settings:scan_contextlevels'] = 'Context levels to scan';
$string['settings:scan_contextlevels_desc'] = 'Limit scans to these context levels.';
$string['settings:context:system'] = 'System context';
$string['settings:context:course'] = 'Course context';
$string['settings:context:module'] = 'Module context';

// External pages.
$string['externalpage:dashboard'] = 'Dashboard';
$string['externalpage:runoverlap'] = 'Run overlap scan now';
$string['externalpage:issues'] = 'Issues';

// New page.
$string['title:issues'] = 'Issues overview';
$string['heading:issues'] = 'Issues overview';

// Titles.
$string['title:dashboard'] = 'Who is who dashboard';

// Headings.
$string['heading:dashboard'] = 'Who is who dashboard';

// Tasks.
$string['task:scan'] = 'Permission Dashboard: scan for user-based issues';
// Unified task names.
$string['task_scheduled_security_scan'] = 'Who is who: scheduled security scan';
$string['task_adhoc_security_scan'] = 'Who is who: adhoc security scan';

// Scanner names/descriptions.
$string['capability_scanner'] = 'Core capability risk scanner';
$string['capability_scanner_desc'] = 'Checks for dangerous core capabilities granted to roles, guests, authenticated users, orphaned capabilities, and context risks.';
$string['capability_issue_scanner'] = 'User capability conflict scanner';
$string['capability_issue_scanner_desc'] = 'Detects conflicts at user/context level based on current role assignments and plugin settings.';

// Notices.
$string['notice:queued_overlap_scan'] = 'Overlap scan queued. It will run shortly via cron.';
$string['notice:queued_user_scan'] = 'User recheck queued. It will run shortly via cron.'; // Legacy
$string['notice:completed_user_scan'] = 'User recheck completed.';
$string['notice:failed_user_scan'] = 'User recheck failed: {$a}';

// Card Titles.
$string['issues:dashboard'] = 'Issues';
$string['users:dashboard'] = 'Users';
$string['profilefield:dashboard'] = 'profile field';
$string['overviewlinktitle:dashboard'] = 'More Details';

// Users overview page.
$string['title:users'] = 'Users overview';
$string['heading:users'] = 'Users overview';
$string['backtodashboard'] = 'Back to dashboard';
$string['filter:withissues'] = 'Only users with issues';
$string['issues'] = 'issues';

// Table & filter labels.
$string['col:issue'] = 'Issue';
$string['col:context'] = 'Context';
$string['col:issues'] = 'Issues';
$string['col:profilefield'] = 'Profile field';
$string['col:roles'] = 'All roles of user';
$string['col:location'] = 'Location of issue';
$string['col:action'] = 'Action';
$string['col:allcapabilities'] = 'All Capabilities';
$string['col:status'] = 'Status';

$string['filter:fullname'] = 'Fullname contains';
$string['filter:context'] = 'Context';
$string['filter:anycontext'] = 'Any context';
$string['filter:status'] = 'Status';
$string['filter:status:any'] = 'Any status';
$string['filter:apply'] = 'Filter';

// Issue labels.
$string['issue:overlap'] = 'Issue: overlap';
$string['issue:conflict'] = 'Issue: conflict';

// Actions.
$string['action:changepermission'] = 'change permission';
$string['action:changerole'] = 'change role';
$string['action:recheck'] = 'recheck issues';
$string['action:capoverview'] = 'capability overview';
$string['action:checkpermissions'] = 'check permissions';
$string['quicklinks'] = 'Quick links';
$string['quicklinks:roles'] = 'Edit roles';

// Scanner messages (capability_scanner).
$string['guest_dangerous_caps'] = 'Guest role has dangerous capabilities assigned';
$string['auth_user_dangerous_caps'] = 'Authenticated user role has dangerous capabilities assigned';
$string['orphaned_capability_found'] = 'Capability referenced in role permissions no longer exists';
$string['context_permission_risk'] = 'Risky permission in non-system context with many users';
$string['user_override_found'] = 'User capability override detected';
$string['title:fix_issue'] = 'Fix issue permissions';
$string['heading:fix_issue'] = 'Fix issue permissions';
$string['notice:issue_permissions_updated'] = 'Permissions updated and issues rechecked.';
$string['form:current'] = 'Current';
$string['form:effective'] = 'Effective in context';
$string['status:pending'] = 'Pending';
$string['status:resolved'] = 'Resolved';
$string['status:ignored'] = 'Ignored';

// Profile field overview page.
$string['title:profilefield_overview'] = 'Profile field overview';
$string['heading:profilefield_overview'] = 'Profile field overview: {$a}';
$string['profilefield_overview:field'] = 'Profile field';
$string['profilefield_overview:value'] = 'Value';
$string['profilefield_overview:totalusers'] = 'Total users';
$string['profilefield_overview:allroles'] = 'All roles';
$string['profilefield_overview:numberofissues'] = 'Number of issues';
$string['profilefield_overview:viewusers'] = 'View users';
$string['profilefield_overview:nodata'] = 'No data available for this profile field';
$string['noroles'] = 'No roles';
