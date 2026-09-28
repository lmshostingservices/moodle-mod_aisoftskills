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

use core_external\external_api;
use mod_aisoftskills\local\learning;

/**
 * Shared loading and permission checks for mod_aisoftskills external functions.
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base extends external_api {
    /**
     * Loads a course module, validates its context and checks a capability.
     *
     * @param int $cmid
     * @param string $capability capability name without the mod/aisoftskills: prefix
     * @return array [course, cm, instance, context]
     */
    protected static function load_cm(int $cmid, string $capability): array {
        global $DB;
        [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'aisoftskills');
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/aisoftskills:' . $capability, $context);
        $instance = $DB->get_record('aisoftskills', ['id' => $cm->instance], '*', MUST_EXIST);
        return [$course, $cm, $instance, $context];
    }

    /**
     * Loads the current user's attempt and checks they may attempt the activity.
     *
     * @param int $attemptid
     * @return array [course, cm, instance, context, attempt]
     */
    protected static function load_attempt(int $attemptid): array {
        global $DB, $USER;
        $record = $DB->get_record('aisoftskills_attempt', ['id' => $attemptid]);
        if (!$record) {
            throw new \moodle_exception('notyourattempt', 'mod_aisoftskills');
        }
        $cm = get_coursemodule_from_instance('aisoftskills', $record->aisoftskillsid, 0, false, MUST_EXIST);
        [$course, $cm, $instance, $context] = self::load_cm((int)$cm->id, 'attempt');
        $attempt = learning::get_user_attempt($instance, $attemptid, (int)$USER->id);
        return [$course, $cm, $instance, $context, $attempt];
    }

    /**
     * Loads a scene for a teacher.
     *
     * @param int $sceneid
     * @param string $capability extra capability to require besides manage
     * @return array [course, cm, instance, context, scene]
     */
    protected static function load_scene(int $sceneid, string $capability = 'manage'): array {
        global $DB;
        $scene = $DB->get_record('aisoftskills_scene', ['id' => $sceneid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('aisoftskills', $scene->aisoftskillsid, 0, false, MUST_EXIST);
        [$course, $cm, $instance, $context] = self::load_cm((int)$cm->id, 'manage');
        if ($capability !== 'manage') {
            require_capability('mod/aisoftskills:' . $capability, $context);
        }
        return [$course, $cm, $instance, $context, $scene];
    }
}
