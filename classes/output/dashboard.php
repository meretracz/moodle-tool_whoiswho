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
        return [
            'exampledata' => $this->get_example_data(),
        ];
    }

    /**
     * Example method
     *
     * @return string
     */
    public function get_example_data(): string {
        return 'This is example data for the dashboard template.';
    }

}
