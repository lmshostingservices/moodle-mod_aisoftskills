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
 * Starts or resumes the learner's attempt and returns the scenes (without the answer key).
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class start_attempt extends base {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
        ]);
    }

    /**
     * Starts or resumes an attempt.
     *
     * @param int $cmid
     * @return array
     */
    public static function execute(int $cmid): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);
        [, , $instance, $context] = self::load_cm($params['cmid'], 'attempt');
        return learning::start_attempt($instance, $context, (int)$USER->id);
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'attemptid' => new external_value(PARAM_INT, 'Attempt id'),
            'attempt' => new external_value(PARAM_INT, 'Attempt number'),
            'scenes' => new external_multiple_structure(new external_single_structure([
                'sceneid' => new external_value(PARAM_INT, 'Scene id'),
                'number' => new external_value(PARAM_INT, 'Scene number'),
                'title' => new external_value(PARAM_TEXT, 'Title'),
                'skill' => new external_value(PARAM_TEXT, 'Soft skill'),
                'context' => new external_value(PARAM_TEXT, 'What is happening'),
                'speaker' => new external_value(PARAM_TEXT, 'Who responds'),
                'question' => new external_value(PARAM_TEXT, 'Question to the learner'),
                'image' => new external_value(PARAM_URL, 'Picture URL'),
                'options' => new external_multiple_structure(new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Response id'),
                    'letter' => new external_value(PARAM_ALPHA, 'A or B'),
                    'text' => new external_value(PARAM_TEXT, 'Response'),
                ])),
                'resolved' => new external_value(PARAM_BOOL, 'Scene done'),
                'answered' => new external_value(PARAM_BOOL, 'A choice has been made'),
                'tried' => new external_value(PARAM_INT, 'Response already tried without success, 0 none'),
            ])),
            'kpis' => new external_multiple_structure(new external_single_structure([
                'kpi' => new external_value(PARAM_ALPHA, 'Indicator key'),
                'name' => new external_value(PARAM_TEXT, 'Indicator name'),
                'value' => new external_value(PARAM_INT, 'Current value 0-100'),
                'start' => new external_value(PARAM_INT, 'Starting value'),
            ])),
        ]);
    }
}
