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
 * Drafts one scene with LMS Labs (3 credits per delivered draft; the request is stored first and never retried).
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class draft_scene extends base {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'brief' => new external_value(PARAM_TEXT, 'What the scene should be about (at most 2,000 characters)'),
            'audience' => new external_value(PARAM_TEXT, 'Who the learners are (at most 500 characters)', VALUE_DEFAULT, ''),
            'context' => new external_value(PARAM_TEXT, 'The workplace (at most 500 characters)', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Drafts.
     *
     * @param int $cmid
     * @param string $brief
     * @param string $audience
     * @param string $context
     * @return array
     */
    public static function execute(int $cmid, string $brief, string $audience = '', string $context = ''): array {
        global $USER;
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['cmid' => $cmid, 'brief' => $brief, 'audience' => $audience, 'context' => $context]
        );
        [, , $instance, $modcontext] = self::load_ai($params['cmid']);
        $row = requests::start_scene($instance, (int)$USER->id, $params['brief'], $params['audience'], $params['context']);
        return requests::export($row, $modcontext);
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
