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

declare(strict_types=1);

namespace mod_aisoftskills\completion;

use core_completion\activity_custom_completion;

/**
 * Custom completion rules: study every scene, master every phrase, finish the Test.
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {
    /**
     * State of a custom rule for the user.
     *
     * @param string $rule
     * @return int COMPLETION_COMPLETE or COMPLETION_INCOMPLETE
     */
    public function get_state(string $rule): int {
        global $DB;
        $this->validate_rule($rule);
        $done = $DB->record_exists('aisoftskills_attempt', ['aisoftskillsid' => $this->cm->instance,
            'userid' => (int)$this->userid, 'state' => 'finished']);
        return $done ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * Custom rules.
     *
     * @return string[]
     */
    public static function get_defined_custom_rules(): array {
        return ['completionallscenes'];
    }

    /**
     * Rule descriptions.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        return ['completionallscenes' => get_string('completiondetail:allscenes', 'mod_aisoftskills')];
    }

    /**
     * Sort order of rules.
     *
     * @return string[]
     */
    public function get_sort_order(): array {
        return ['completionview', 'completionallscenes', 'completionusegrade', 'completionpassgrade'];
    }
}
