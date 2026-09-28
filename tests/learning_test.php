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

use mod_aisoftskills\local\catalogue;
use mod_aisoftskills\local\learning;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for playing scenes: first-choice marking, retries, indicators, grades and attempt limits.
 *
 * @package    mod_aisoftskills
 * @category   test
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_aisoftskills\local\learning
 */
#[CoversClass(learning::class)]
final class learning_test extends \advanced_testcase {
    /**
     * Creates a course, a learner and an activity with scenes.
     *
     * @param array $settings activity settings
     * @param int $scenes number of scenes
     * @return array [course, cm, context, instance, student, scenes]
     */
    protected function setup_activity(array $settings = [], int $scenes = 2): array {
        global $DB;
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $student = $gen->create_and_enrol($course, 'student');
        $ss = $gen->get_plugin_generator('mod_aisoftskills');
        $instance = $ss->create_instance(['course' => $course->id] + $settings);
        $list = [];
        for ($i = 1; $i <= $scenes; $i++) {
            $list[] = $ss->create_scene($instance, 'Scene ' . $i, 'Better ' . $i, 'Poorer ' . $i);
        }
        $cm = get_coursemodule_from_instance('aisoftskills', $instance->id, $course->id, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $instance = $DB->get_record('aisoftskills', ['id' => $instance->id], '*', MUST_EXIST);
        return [$course, $cm, $context, $instance, $student, $list];
    }

    /**
     * Option id of the better or poorer response in a scene.
     *
     * @param int $sceneid
     * @param bool $best
     * @return int
     */
    protected function option(int $sceneid, bool $best): int {
        global $DB;
        return (int)$DB->get_field('aisoftskills_option', 'id', ['sceneid' => $sceneid, 'best' => $best ? 1 : 0]);
    }

    /**
     * The player data never contains the answer key.
     * @covers \mod_aisoftskills\local\learning
     */
    public function test_start_hides_answer_key(): void {
        $this->resetAfterTest();
        [, , $context, $instance, $student] = $this->setup_activity();
        $data = learning::start_attempt($instance, $context, (int)$student->id);
        $this->assertCount(2, $data['scenes']);
        foreach ($data['scenes'] as $scene) {
            $this->assertCount(2, $scene['options']);
            foreach ($scene['options'] as $option) {
                $this->assertSame(['id', 'letter', 'text'], array_keys($option));
            }
        }
        $json = json_encode($data);
        foreach (['best', 'kpidelta', 'consequence', 'reason'] as $secret) {
            $this->assertStringNotContainsString('"' . $secret . '"', $json);
        }
        // Starting again resumes the same attempt.
        $again = learning::start_attempt($instance, $context, (int)$student->id);
        $this->assertSame($data['attemptid'], $again['attemptid']);
    }

    /**
     * Only the first choice counts; a retry after a poorer choice resolves the scene but not the mark.
     * @covers \mod_aisoftskills\local\learning
     */
    public function test_first_choice_marked_with_retry(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $cm, $context, $instance, $student, $scenes] = $this->setup_activity();
        $data = learning::start_attempt($instance, $context, (int)$student->id);
        $attempt = $DB->get_record('aisoftskills_attempt', ['id' => $data['attemptid']]);

        // Scene 1: better response first.
        $r = learning::choose($instance, $context, $attempt, (int)$scenes[0]->id, $this->option($scenes[0]->id, true));
        $this->assertSame(1, $r['best']);
        $this->assertSame(1, $r['first']);
        $this->assertSame(1, $r['resolved']);
        $this->assertSame(catalogue::KPI_START, $r['before']);
        $this->assertSame(catalogue::KPI_START + 20, $r['after']);
        $this->assertSame(0, $r['allresolved']);

        // Scene 2: poorer first, then the better one.
        $poor = learning::choose($instance, $context, $attempt, (int)$scenes[1]->id, $this->option($scenes[1]->id, false));
        $this->assertSame(0, $poor['best']);
        $this->assertSame(1, $poor['canretry']);
        $this->assertSame(0, $poor['resolved']);
        $this->assertSame('', $poor['better']);
        $this->assertSame(70, $poor['before']);
        $this->assertSame(50, $poor['after']);
        try {
            learning::choose($instance, $context, $attempt, (int)$scenes[1]->id, $this->option($scenes[1]->id, false));
            $this->fail('The same poorer response was accepted twice');
        } catch (\moodle_exception $e) {
            $this->assertSame('alreadytried', $e->errorcode);
        }
        $retry = learning::choose($instance, $context, $attempt, (int)$scenes[1]->id, $this->option($scenes[1]->id, true));
        $this->assertSame(1, $retry['best']);
        $this->assertSame(0, $retry['first']);
        $this->assertSame(1, $retry['allresolved']);
        $choice = $DB->get_record('aisoftskills_choice', ['attemptid' => $attempt->id, 'sceneid' => $scenes[1]->id]);
        $this->assertSame(0, (int)$choice->best);
        $this->assertSame(2, (int)$choice->tries);
        $this->assertSame($this->option($scenes[1]->id, false), (int)$choice->optionid);

        $summary = learning::finish_attempt($instance, $cm, $course, $context, $attempt);
        $this->assertSame(50, $summary['score']);
        $this->assertSame(1, $summary['best']);
        $this->assertSame(2, $summary['total']);
        $this->assertSame('developing', $summary['rating']);
        $this->assertSame([['kpi' => 'motivation', 'name' => 'Motivation', 'value' => 70, 'start' => 50]], $summary['kpis']);
        $grades = grade_get_grades($course->id, 'mod', 'aisoftskills', $instance->id, $student->id);
        $this->assertEquals(50, (float)$grades->items[0]->grades[$student->id]->grade);
    }

    /**
     * Without retries a poorer choice ends the scene and reveals the better response.
     * @covers \mod_aisoftskills\local\learning
     */
    public function test_no_retry_reveals_better(): void {
        global $DB;
        $this->resetAfterTest();
        [, , $context, $instance, $student, $scenes] = $this->setup_activity(['allowretry' => 0], 1);
        $data = learning::start_attempt($instance, $context, (int)$student->id);
        $attempt = $DB->get_record('aisoftskills_attempt', ['id' => $data['attemptid']]);
        $r = learning::choose($instance, $context, $attempt, (int)$scenes[0]->id, $this->option($scenes[0]->id, false));
        $this->assertSame(0, $r['canretry']);
        $this->assertSame(1, $r['resolved']);
        $this->assertSame('Better 1', $r['better']);
        $this->assertNotSame('', $r['betterreason']);
        $this->expectException(\moodle_exception::class);
        learning::choose($instance, $context, $attempt, (int)$scenes[0]->id, $this->option($scenes[0]->id, true));
    }

    /**
     * Indicators stay between 0 and 100.
     * @covers \mod_aisoftskills\local\learning
     */
    public function test_indicators_clamped(): void {
        global $DB;
        $this->resetAfterTest();
        [, , $context, $instance, $student] = $this->setup_activity([], 0);
        $ss = $this->getDataGenerator()->get_plugin_generator('mod_aisoftskills');
        $scenes = [];
        for ($i = 0; $i < 3; $i++) {
            $scenes[] = $ss->create_scene($instance, 'S' . $i, 'Good', 'Bad', ['kpi' => 'trust', 'betterdelta' => 50]);
        }
        $data = learning::start_attempt($instance, $context, (int)$student->id);
        $attempt = $DB->get_record('aisoftskills_attempt', ['id' => $data['attemptid']]);
        $last = null;
        foreach ($scenes as $scene) {
            $last = learning::choose($instance, $context, $attempt, (int)$scene->id, $this->option($scene->id, true));
        }
        $this->assertSame(100, $last['after']);
        $this->assertSame(['trust' => 100], learning::kpis($attempt));
    }

    /**
     * Wrong scene or option ids, unfinished scenes and attempt limits are refused.
     * @covers \mod_aisoftskills\local\learning
     */
    public function test_refusals(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $cm, $context, $instance, $student, $scenes] = $this->setup_activity(['maxattempts' => 1], 2);
        $data = learning::start_attempt($instance, $context, (int)$student->id);
        $attempt = $DB->get_record('aisoftskills_attempt', ['id' => $data['attemptid']]);
        $codes = [];
        foreach (
            [
                fn() => learning::choose($instance, $context, $attempt, (int)$scenes[0]->id, $this->option($scenes[1]->id, true)),
                fn() => learning::finish_attempt($instance, $cm, $course, $context, $attempt),
            ] as $call
        ) {
            try {
                $call();
            } catch (\moodle_exception $e) {
                $codes[] = $e->errorcode;
            }
        }
        $this->assertSame(['invalidoption', 'scenesleft'], $codes);
        foreach ($scenes as $scene) {
            learning::choose($instance, $context, $attempt, (int)$scene->id, $this->option($scene->id, true));
        }
        $summary = learning::finish_attempt($instance, $cm, $course, $context, $attempt);
        $this->assertSame(100, $summary['score']);
        $this->assertSame(0, $summary['canretake']);
        $this->assertSame(0, $summary['attemptsleft']);
        $this->assertFalse(learning::can_start($instance, (int)$student->id));
        $this->expectException(\moodle_exception::class);
        learning::start_attempt($instance, $context, (int)$student->id);
    }

    /**
     * Scenes without a picture are not played; with none ready the activity can't start.
     * @covers \mod_aisoftskills\local\learning
     */
    public function test_only_ready_scenes(): void {
        $this->resetAfterTest();
        [, , $context, $instance, $student] = $this->setup_activity([], 1);
        $ss = $this->getDataGenerator()->get_plugin_generator('mod_aisoftskills');
        $ss->create_scene($instance, 'No picture', 'A', 'B', [], '');
        $data = learning::start_attempt($instance, $context, (int)$student->id);
        $this->assertCount(1, $data['scenes']);
        $this->assertSame('Scene 1', $data['scenes'][0]['title']);
    }

    /**
     * Grading methods use each finished attempt's score.
     * @covers \mod_aisoftskills\local\learning
     */
    public function test_grade_methods(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $cm, $context, $instance, $student, $scenes] = $this->setup_activity([], 1);
        foreach ([false, true] as $better) {
            $data = learning::start_attempt($instance, $context, (int)$student->id);
            $attempt = $DB->get_record('aisoftskills_attempt', ['id' => $data['attemptid']]);
            learning::choose($instance, $context, $attempt, (int)$scenes[0]->id, $this->option($scenes[0]->id, $better));
            if (!$better) {
                learning::choose($instance, $context, $attempt, (int)$scenes[0]->id, $this->option($scenes[0]->id, true));
            }
            learning::finish_attempt($instance, $cm, $course, $context, $attempt);
        }
        $expected = [1 => 100, 2 => 50, 3 => 0, 4 => 100];
        foreach ($expected as $method => $grade) {
            $instance->grademethod = $method;
            $grades = local\manager::get_user_grades($instance, (int)$student->id);
            $this->assertEquals($grade, (float)$grades[$student->id]->rawgrade, 'Method ' . $method);
        }
    }
}
