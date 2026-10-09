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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_aisoftskills\local\labels;

/**
 * Saves the name labels of a scene picture (free: nothing is sent to LMS Labs).
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_labels extends base {
    /**
     * One label.
     *
     * @return external_single_structure
     */
    protected static function label_structure(): external_single_structure {
        return new external_single_structure([
            'text' => new external_value(PARAM_TEXT, 'Label text, such as Leo - Bartender'),
            'x' => new external_value(PARAM_FLOAT, 'Centre, percent of the picture width'),
            'y' => new external_value(PARAM_FLOAT, 'Centre, percent of the picture height'),
            'gender' => new external_value(PARAM_ALPHA, 'Voice: f, m or empty'),
            'you' => new external_value(PARAM_BOOL, 'This person is the learner'),
        ]);
    }

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sceneid' => new external_value(PARAM_INT, 'Scene id'),
            'labels' => new external_multiple_structure(self::label_structure(), 'Labels', VALUE_DEFAULT, []),
        ]);
    }

    /**
     * Saves.
     *
     * @param int $sceneid
     * @param array $labels
     * @return array the saved labels
     */
    public static function execute(int $sceneid, array $labels = []): array {
        $params = self::validate_parameters(self::execute_parameters(), ['sceneid' => $sceneid, 'labels' => $labels]);
        [, , , , $scene] = self::load_scene($params['sceneid']);
        return labels::save($scene, $params['labels']);
    }

    /**
     * Return structure.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(self::label_structure());
    }
}
