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
 * Settings configuration for Who is who
 *
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @package     tool_whoiswho
 * @copyright   02/09/2025 LdesignMedia.nl - Luuk Verhoeven
 * @author      Vincent Cornelis
 **/

use tool_whoiswho\helper;

defined('MOODLE_INTERNAL') || die;

global $ADMIN;

if ($hassiteconfig) {

    $category = new admin_category(
        'tool_whoiswho',
        get_string('pluginname', 'tool_whoiswho'),
    );

    $ADMIN->add('tools', $category);

    $settings = new admin_settingpage(
        'tool_whoiswho_settings',
        get_string('settings:whoiswhoconfig', 'tool_whoiswho')
    );

    if ($ADMIN->fulltree) {

        $reportsheading = new admin_setting_heading(
            'tool_whoiswho/reportsheading',
            get_string('settings:heading:reports', 'tool_whoiswho'),
            get_string('settings:heading:reports_desc', 'tool_whoiswho')
        );
        $settings->add($reportsheading);

        // Add profilefield options.
        $profilefieldoptions = new admin_setting_configmultiselect(
            'tool_whoiswho/profilefields',
            get_string('settings:profilefields', 'tool_whoiswho'),
            get_string('settings:profilefields_desc', 'tool_whoiswho'),
            [],
            helper::get_profile_fields_menu(),
        );
        $settings->add($profilefieldoptions);

        // Scanner options.
        $scannerheading = new admin_setting_heading(
            'tool_whoiswho/scannerheading',
            get_string('settings:heading:scanner', 'tool_whoiswho'),
            get_string('settings:heading:scanner_desc', 'tool_whoiswho')
        );
        $settings->add($scannerheading);

        $settings->add(new admin_setting_configcheckbox(
            'tool_whoiswho/scan_overlap_enabled',
            get_string('settings:scan_overlap_enabled', 'tool_whoiswho'),
            get_string('settings:scan_overlap_enabled_desc', 'tool_whoiswho'),
            1
        ));

        $settings->add(new admin_setting_configcheckbox(
            'tool_whoiswho/scan_conflict_enabled',
            get_string('settings:scan_conflict_enabled', 'tool_whoiswho'),
            get_string('settings:scan_conflict_enabled_desc', 'tool_whoiswho'),
            1
        ));

        $settings->add(new admin_setting_configcheckbox(
            'tool_whoiswho/scan_include_parents',
            get_string('settings:scan_include_parents', 'tool_whoiswho'),
            get_string('settings:scan_include_parents_desc', 'tool_whoiswho'),
            0
        ));

        $contextoptions = [
            CONTEXT_SYSTEM => get_string('settings:context:system', 'tool_whoiswho'),
            CONTEXT_COURSE => get_string('settings:context:course', 'tool_whoiswho'),
            CONTEXT_MODULE => get_string('settings:context:module', 'tool_whoiswho'),
        ];

        $settings->add(new admin_setting_configmultiselect(
            'tool_whoiswho/scan_contextlevels',
            get_string('settings:scan_contextlevels', 'tool_whoiswho'),
            get_string('settings:scan_contextlevels_desc', 'tool_whoiswho'),
            [CONTEXT_SYSTEM, CONTEXT_COURSE, CONTEXT_MODULE],
            $contextoptions
        ));

    }

    $ADMIN->add('tool_whoiswho', $settings);

    $dashboard = new admin_externalpage(
        'tool_whoiswho_dashboard',
        get_string('externalpage:dashboard', 'tool_whoiswho'),
        new moodle_url(
            '/admin/tool/whoiswho/view/dashboard.php'
        ),
        'tool/whoiswho:dashboardaccess'
    );

    $ADMIN->add('tool_whoiswho', $dashboard);

}
