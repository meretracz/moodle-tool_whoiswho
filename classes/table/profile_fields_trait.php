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
 * Trait for shared profile field functionality in tables
 *
 * @package     tool_whoiswho
 * @copyright   02/09/2025 LdesignMedia.nl - Luuk Verhoeven
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_whoiswho\table;

/**
 * Trait providing shared functionality for handling profile fields in tables.
 */
trait profile_fields_trait {

    /** @var array IDs of custom profile fields to display */
    protected array $profilefieldids = [];

    /** @var array Mapping of field IDs to field names */
    protected array $profilefieldnames = [];

    /** @var array Mapping of field IDs to data types */
    protected array $profilefieldtypes = [];

    /**
     * Initialize custom profile fields from configuration.
     *
     * @return void
     */
    protected function init_profilefields(): void {
        global $DB;

        $cfg = get_config('tool_whoiswho');
        $this->profilefieldids = [];
        $this->profilefieldnames = [];
        $this->profilefieldtypes = [];

        if (!empty($cfg->profilefields)) {
            $ids = preg_split('/[,\s]+/', (string) $cfg->profilefields);
            $ids = array_values(array_filter(array_map('intval', (array) $ids)));

            foreach ($ids as $id) {
                $field = $DB->get_record('user_info_field', ['id' => $id], 'id, name, datatype', IGNORE_MISSING);
                if ($field) {
                    $this->profilefieldids[] = $id;
                    $this->profilefieldnames[$id] = $field->name;
                    $this->profilefieldtypes[$id] = $field->datatype;
                }
            }
        }
    }

    /**
     * Build WHERE clause for fullname filter.
     *
     * @param string $fullname The fullname to filter by
     *
     * @return array Array containing WHERE clause fragment and parameters
     */
    protected function build_fullname_filter(string $fullname): array {
        global $DB;

        $where = '';
        $params = [];

        $fullname = trim($fullname);
        if ($fullname !== '') {
            $like = '%' . $fullname . '%';
            $where = '(' . $DB->sql_like(
                    $DB->sql_concat('u.firstname', '" "', 'u.lastname'),
                    ':fn',
                    false
                ) . ')';
            $params['fn'] = $like;
        }

        return [$where, $params];
    }

}
