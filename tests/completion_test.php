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

namespace mod_aisoftskills;

use mod_aisoftskills\completion\custom_completion;
use mod_aisoftskills\local\learning;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Custom completion rule tests.
 *
 * @package    mod_aisoftskills
 * @category   test
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_aisoftskills\completion\custom_completion
 */
#[CoversClass(custom_completion::class)]
final class completion_test extends \advanced_testcase {
    /**
     * The activity is complete once an attempt with every scene is finished.
     */
    public function test_all_scenes(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $student = $gen->create_and_enrol($course, 'student');
        $ss = $gen->get_plugin_generator('mod_aisoftskills');
        $instance = $ss->create_instance(['course' => $course->id, 'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionallscenes' => 1]);
        $scene = $ss->create_scene($instance, 'A');
        $cm = get_fast_modinfo($course)->get_cm(get_coursemodule_from_instance('aisoftskills', $instance->id)->id);
        $context = \context_module::instance($cm->id);
        $instance = $DB->get_record('aisoftskills', ['id' => $instance->id]);
        $check = fn() => (new custom_completion($cm, (int)$student->id))->get_state('completionallscenes');
        $this->assertSame(COMPLETION_INCOMPLETE, $check());
        $data = learning::start_attempt($instance, $context, (int)$student->id);
        $attempt = $DB->get_record('aisoftskills_attempt', ['id' => $data['attemptid']]);
        $option = (int)$DB->get_field('aisoftskills_option', 'id', ['sceneid' => $scene->id, 'best' => 0]);
        learning::choose($instance, $context, $attempt, (int)$scene->id, $option);
        $this->assertSame(COMPLETION_INCOMPLETE, $check());
        $option = (int)$DB->get_field('aisoftskills_option', 'id', ['sceneid' => $scene->id, 'best' => 1]);
        learning::choose($instance, $context, $attempt, (int)$scene->id, $option);
        learning::finish_attempt($instance, $cm, $course, $context, $attempt);
        $this->assertSame(COMPLETION_COMPLETE, $check());
        $this->assertSame(['completionallscenes'], custom_completion::get_defined_custom_rules());
        $this->assertNotEmpty((new custom_completion($cm, (int)$student->id))->get_custom_rule_descriptions());
    }
}
