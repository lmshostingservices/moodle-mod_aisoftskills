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
use mod_aisoftskills\local\learning;

/**
 * Finishes the learner's attempt and returns the summary.
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class finish_attempt extends base {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid' => new external_value(PARAM_INT, 'Attempt id'),
        ]);
    }

    /**
     * Finishes the attempt.
     *
     * @param int $attemptid
     * @return array
     */
    public static function execute(int $attemptid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['attemptid' => $attemptid]);
        [$course, $cm, $instance, $context, $attempt] = self::load_attempt($params['attemptid']);
        return learning::finish_attempt($instance, $cm, $course, $context, $attempt);
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'score' => new external_value(PARAM_INT, 'Percentage of better first choices'),
            'best' => new external_value(PARAM_INT, 'Scenes where the first choice was the better response'),
            'total' => new external_value(PARAM_INT, 'Scenes'),
            'rating' => new external_value(PARAM_ALPHA, 'excellent, strong, developing or beginning'),
            'kpis' => new external_multiple_structure(new external_single_structure([
                'kpi' => new external_value(PARAM_ALPHA, 'Indicator key'),
                'name' => new external_value(PARAM_TEXT, 'Indicator name'),
                'value' => new external_value(PARAM_INT, 'Current value 0-100'),
                'start' => new external_value(PARAM_INT, 'Starting value'),
                'tone' => new external_value(PARAM_ALPHA, 'Traffic light: red, amber or green'),
                'tonename' => new external_value(PARAM_TEXT, 'The traffic light in words'),
            ])),
            'level' => new external_value(PARAM_ALPHA, 'Career level'),
            'takeaways' => new external_multiple_structure(new external_single_structure([
                'title' => new external_value(PARAM_TEXT, 'Scene'),
                'skill' => new external_value(PARAM_TEXT, 'Soft skill'),
                'better' => new external_value(PARAM_TEXT, 'The better response'),
                'reason' => new external_value(PARAM_TEXT, 'Why it works'),
            ]), 'Scenes to learn from: the first choice was the poorer response'),
            'recap' => new external_multiple_structure(new external_single_structure([
                'number' => new external_value(PARAM_INT, 'Scene number in the attempt'),
                'title' => new external_value(PARAM_TEXT, 'Scene'),
                'skill' => new external_value(PARAM_TEXT, 'Soft skill'),
                'image' => new external_value(PARAM_URL, 'Picture URL'),
                'firstbest' => new external_value(PARAM_BOOL, 'The first choice was the better response'),
                'firstworst' => new external_value(PARAM_BOOL, 'The first choice was the very poor response'),
                'better' => new external_value(PARAM_TEXT, 'The better response'),
                'reason' => new external_value(PARAM_TEXT, 'Why it works'),
                'yours' => new external_value(PARAM_TEXT, 'The learner\'s first response, empty when none'),
                'yourreason' => new external_value(PARAM_TEXT, 'Why the first response fell short, empty when it was better'),
            ]), 'Every scene of the attempt, in the order played'),
            'canretake' => new external_value(PARAM_BOOL, 'Another attempt is allowed'),
            'mode' => new external_value(PARAM_ALPHA, 'practice or test'),
            'passmark' => new external_value(PARAM_INT, 'Test pass mark in percent, 0 none'),
            'passed' => new external_value(PARAM_BOOL, 'The test was passed'),
            'cantest' => new external_value(PARAM_BOOL, 'After practice: the learner can take the test'),
            'attemptsleft' => new external_value(PARAM_INT, 'Attempts left, -1 unlimited'),
        ]);
    }
}
