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
use core_external\external_single_structure;
use core_external\external_value;
use mod_aisoftskills\local\learning;

/**
 * Marks the learner's choice of response in a scene.
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class choose_option extends base {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid' => new external_value(PARAM_INT, 'Attempt id'),
            'sceneid' => new external_value(PARAM_INT, 'Scene id'),
            'optionid' => new external_value(PARAM_INT, 'Chosen response id'),
        ]);
    }

    /**
     * Marks the choice.
     *
     * @param int $attemptid
     * @param int $sceneid
     * @param int $optionid
     * @return array
     */
    public static function execute(int $attemptid, int $sceneid, int $optionid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['attemptid' => $attemptid, 'sceneid' => $sceneid,
            'optionid' => $optionid]);
        [, , $instance, $context, $attempt] = self::load_attempt($params['attemptid']);
        return learning::choose($instance, $context, $attempt, $params['sceneid'], $params['optionid']);
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'best' => new external_value(PARAM_BOOL, 'The better response was chosen'),
            'first' => new external_value(PARAM_BOOL, 'This was the first choice in the scene (the one marked)'),
            'consequence' => new external_value(PARAM_TEXT, 'What happens next'),
            'reason' => new external_value(PARAM_TEXT, 'Why'),
            'kpi' => new external_value(PARAM_ALPHA, 'Indicator key'),
            'kpiname' => new external_value(PARAM_TEXT, 'Indicator name'),
            'delta' => new external_value(PARAM_INT, 'Change'),
            'before' => new external_value(PARAM_INT, 'Value before'),
            'after' => new external_value(PARAM_INT, 'Value after'),
            'resolved' => new external_value(PARAM_BOOL, 'Scene done'),
            'canretry' => new external_value(PARAM_BOOL, 'The learner may try again'),
            'better' => new external_value(PARAM_TEXT, 'The better response, when it is revealed'),
            'betterreason' => new external_value(PARAM_TEXT, 'Why the better response works'),
            'allresolved' => new external_value(PARAM_BOOL, 'Every scene is done'),
        ]);
    }
}
