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
use mod_aisoftskills\local\ai\factory;
use mod_aisoftskills\local\lesson;
use mod_aisoftskills\local\manager;
use moodle_exception;

/**
 * Creates a scene picture with LMS Labs (5 credits per successful picture; never retried automatically).
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_image extends base {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sceneid' => new external_value(PARAM_INT, 'Scene id'),
        ]);
    }

    /**
     * Generates.
     *
     * @param int $sceneid
     * @return array
     */
    public static function execute(int $sceneid): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['sceneid' => $sceneid]);
        [, , $instance, $context, $scene] = self::load_scene($params['sceneid'], 'useai');
        $provider = factory::get();
        if (!$provider->can_generate()) {
            throw new moodle_exception('ainotavailable', 'mod_aisoftskills');
        }
        lesson::check_ai_rate((int)$USER->id);
        try {
            $result = $provider->generate_image(lesson::image_prompt($instance, $scene), (string)$instance->imagestyle);
            manager::save_scene_image_bytes($context, $scene, $result['bytes']);
        } catch (\Throwable $e) {
            lesson::log_ai((int)$instance->id, (int)$USER->id, 'image', 'error');
            throw $e;
        }
        lesson::log_ai((int)$instance->id, (int)$USER->id, 'image', 'ok');
        [$url] = manager::get_scene_image($context, (int)$scene->id);
        return [
            'url' => (string)$url,
            'charged' => (int)$result['charged'],
            'balance' => $result['balance'] === null ? -1 : (int)$result['balance'],
            'requestid' => (string)$result['requestid'],
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'url' => new external_value(PARAM_URL, 'Picture URL'),
            'charged' => new external_value(PARAM_INT, 'LMS Labs credits charged'),
            'balance' => new external_value(PARAM_INT, 'LMS Labs credits left (-1 when unknown or unlimited)'),
            'requestid' => new external_value(PARAM_ALPHANUMEXT, 'LMS Labs request id'),
        ]);
    }
}
