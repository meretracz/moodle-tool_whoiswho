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
 * Dashboard output class
 *
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @package     tool_whoiswho
 * @copyright   02/09/2025 LdesignMedia.nl - Luuk Verhoeven
 * @author      Vincent Cornelis
 **/

namespace tool_whoiswho\output;

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use tool_whoiswho\local\manager\capability_manager;

/**
 * Class dashboard
 *
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @package     tool_whoiswho
 * @copyright   02/09/2025 LdesignMedia.nl - Luuk Verhoeven
 * @author      Vincent Cornelis
 **/
class dashboard implements renderable, templatable {

    /**
     * Gather data to export to the mustache template.
     *
     * @param renderer_base $output
     *
     * @return string[]
     */
    public function export_for_template(renderer_base $output): array {

        return [
            'items' => $this->get_items(),
        ];
    }

    /**
     * Get dashboard items with actual data
     *
     * @return array
     */
    public function get_items(): array {

        // Get actual statistics from capability manager.
        $stats = capability_manager::get_dashboard_stats();

        $issuelink = new \moodle_url('/admin/tool/whoiswho/view/issues.php');
        $userslink = new \moodle_url('/admin/tool/whoiswho/view/users.php');

        $items = [
            [
                'cardheader' => get_string('issues:dashboard', 'tool_whoiswho'),
                'cardvalue' => $stats['unresolved_issues'],
                'cardicon' => 'fa-exclamation-triangle',
                'overviewurl' => $issuelink->out(),
            ],
            [
                'cardheader' => get_string('users:dashboard', 'tool_whoiswho'),
                'cardvalue' => $stats['affected_users'],
                'cardicon' => 'fa-users',
                'overviewurl' => $userslink->out(),
            ],
            [
                'cardheader' => get_string('profilefield:dashboard', 'tool_whoiswho'),
                'cardvalue' => '20',
                'cardicon' => 'fa-id-badge',
                'overviewurl' => '',
            ],
        ];

        return array_map(function($item) {
            return [
                'cardheader' => $item['cardheader'],
                'cardvalue' => $item['cardvalue'],
                'cardicon' => $item['cardicon'],
                'overviewurl' => $item['overviewurl'],

            ];
        }, $items);

    }

}
