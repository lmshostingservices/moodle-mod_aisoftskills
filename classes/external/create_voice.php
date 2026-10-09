<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_aisoftskills\external;

use core_external\external_function_parameters;
use core_external\external_value;
use mod_aisoftskills\local\ai\requests;

/**
 * Creates one voiceover clip of a scene with LMS Labs (stored first, never retried; 5 credits per clip LMS Labs settles).
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_voice extends base {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sceneid' => new external_value(PARAM_INT, 'Scene id'),
            'index' => new external_value(PARAM_INT, 'Clip number in the scene, from the voiceover step'),
        ]);
    }

    /**
     * Creates the clip.
     *
     * @param int $sceneid
     * @param int $index
     * @return array
     */
    public static function execute(int $sceneid, int $index): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['sceneid' => $sceneid, 'index' => $index]);
        [, , $instance, $context, $scene] = self::load_scene($params['sceneid'], 'useai');
        $row = requests::start_voice($instance, (int)$USER->id, $scene, $params['index']);
        return requests::export($row, $context);
    }

    /**
     * Return structure.
     *
     * @return \core_external\external_single_structure
     */
    public static function execute_returns(): \core_external\external_single_structure {
        return self::request_structure();
    }
}
