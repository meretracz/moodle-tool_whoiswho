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

        // TODO: Get actual data to send to the dashboard.mustache template.
        return  $this->get_example_data();
    }

    /**
     * Example method
     *
     * @return array
     */
    public function get_example_data(): array {
        //return 'This is example data for the dashboard template.';
        // Add the Cards
        // TODO: Get actual data to send to the card.mustache template.
        // first two fields are fixed others will be based on profile field
        // replace this URL with one for each detail page
        $url_to_link = new \moodle_url('/user/profile.php');

        $items = [
            ['cardheader' => get_string('issues:dashboard', 'tool_whoiswho'),'cardvalue' => '100','cardicon' => 'fa-exclamation-triangle','overviewurl' => $url_to_link->out()],
            ['cardheader' => get_string('users:dashboard', 'tool_whoiswho'),'cardvalue' => '50','cardicon' => 'fa-users','overviewurl' => ''],
            ['cardheader' => get_string('profilefield:dashboard', 'tool_whoiswho'),'cardvalue' => '20','cardicon' => 'fa-id-badge','overviewurl' => ''],
        ];

        $templatecard = [
            'items' => array_map(function($item) {
                return [
                    'cardheader' => $item['cardheader'],
                    'cardvalue' => $item['cardvalue'],
                    'cardicon' => $item['cardicon'],
                    'overviewurl' => $item['overviewurl']

                ];
            }, $items)
        ];

        return $templatecard;

    }

}
