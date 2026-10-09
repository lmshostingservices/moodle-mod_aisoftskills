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
     */
    public function test_start_hides_answer_key(): void {
        $this->resetAfterTest();
        [, , $context, $instance, $student] = $this->setup_activity();
        $data = learning::start_attempt($instance, $context, (int)$student->id);
        $this->assertCount(2, $data['scenes']);
        foreach ($data['scenes'] as $scene) {
            $this->assertCount(2, $scene['options']);
            foreach ($scene['options'] as $option) {
                $this->assertSame(['id', 'letter', 'text', 'voice'], array_keys($option));
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
     * A test allows one choice per scene and keeps the better response for the results slides.
     */
    public function test_test_mode_one_choice(): void {
        global $DB;
        $this->resetAfterTest();
        [, , $context, $instance, $student, $scenes] = $this->setup_activity(['practicemode' => 0, 'testmode' => 1,
            'passmark' => 70], 1);
        $data = learning::start_attempt($instance, $context, (int)$student->id);
        $this->assertSame('test', $data['mode']);
        $attempt = $DB->get_record('aisoftskills_attempt', ['id' => $data['attemptid']]);
        $r = learning::choose($instance, $context, $attempt, (int)$scenes[0]->id, $this->option($scenes[0]->id, false));
        $this->assertSame(0, $r['canretry']);
        $this->assertSame(1, $r['resolved']);
        $this->assertSame('', $r['better']);
        $summary = learning::finish_attempt(
            $instance,
            get_coursemodule_from_instance('aisoftskills', $instance->id),
            get_course($instance->course),
            $context,
            $DB->get_record('aisoftskills_attempt', ['id' => $attempt->id])
        );
        $this->assertSame(0, $summary['score']);
        $this->assertSame(70, $summary['passmark']);
        $this->assertSame(0, $summary['passed']);
        $this->assertSame('Better 1', $summary['recap'][0]['better']);
        $this->assertFalse($summary['recap'][0]['firstbest']);
        $this->expectException(\moodle_exception::class);
        learning::choose($instance, $context, $attempt, (int)$scenes[0]->id, $this->option($scenes[0]->id, true));
    }

    /**
     * Practice and test together: practice is never limited, attempts allowed and grades count the test only.
     */
    public function test_both_modes(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $cm, $context, $instance, $student, $scenes] = $this->setup_activity(['practicemode' => 1,
            'testmode' => 1, 'passmark' => 50, 'maxattempts' => 1], 1);
        $this->assertSame(['practice', 'test'], learning::modes($instance));
        $this->assertSame('test', learning::graded_mode($instance));
        $play = function (string $mode, bool $best) use ($instance, $context, $student, $scenes, $cm, $course, $DB) {
            $data = learning::start_attempt($instance, $context, (int)$student->id, $mode);
            $attempt = $DB->get_record('aisoftskills_attempt', ['id' => $data['attemptid']]);
            $r = learning::choose($instance, $context, $attempt, (int)$scenes[0]->id, $this->option($scenes[0]->id, $best));
            if (!$r['resolved']) {
                $this->assertSame(1, $r['canretry']);
                learning::choose($instance, $context, $attempt, (int)$scenes[0]->id, $this->option($scenes[0]->id, true));
            }
            return learning::finish_attempt(
                $instance,
                $cm,
                $course,
                $context,
                $DB->get_record('aisoftskills_attempt', ['id' => $attempt->id])
            );
        };
        $practice = $play('practice', false);
        $this->assertSame(1, $practice['cantest']);
        $play('practice', true);
        $this->assertTrue(learning::can_start($instance, (int)$student->id, 'practice'));
        // Practice does not count towards the grade.
        $this->assertSame([], \mod_aisoftskills\local\manager::get_user_grades($instance, (int)$student->id));
        $test = $play('test', true);
        $this->assertSame(1, $test['passed']);
        $this->assertFalse(learning::can_start($instance, (int)$student->id, 'test'));
        $this->assertTrue(learning::can_start($instance, (int)$student->id, 'practice'));
        $grades = \mod_aisoftskills\local\manager::get_user_grades($instance, (int)$student->id);
        $this->assertEquals(100, $grades[$student->id]->rawgrade);
    }

    /**
     * The better response is first in half of the scenes and second in the other half.
     */
    public function test_better_first_is_balanced(): void {
        for ($n = 1; $n <= 9; $n++) {
            $firsts = learning::better_first($n);
            $this->assertCount($n, $firsts);
            $this->assertLessThanOrEqual(1, abs(count(array_filter($firsts)) * 2 - $n));
        }
        $options = [(object)['id' => 5, 'best' => 0], (object)['id' => 7, 'best' => 1]];
        $this->assertSame([7, 5], learning::place_better($options, true));
        $this->assertSame([5, 7], learning::place_better($options, false));
    }

    /**
     * The role line and the scene cards.
     */
    public function test_role_line_and_cards(): void {
        $this->resetAfterTest();
        $this->assertSame(
            'As the bar shift supervisor, how would you handle this situation?',
            learning::role_line('You, the bar shift supervisor', 'en')
        );
        $this->assertSame('Tú, la supervisora', learning::role_line('Tú, la supervisora', 'es'));
        $this->assertSame('', learning::role_line('', 'en'));
        $cards = learning::context_cards('At 4 pm the venue fills up. You ask Leo to stay. He hesitates. ' .
            'The manager expects full cover.', 'en');
        $this->assertSame(['situation', 'action', 'context'], array_column($cards, 'kind'));
        $this->assertSame([['text' => 'He hesitates.'], ['text' => 'The manager expects full cover.']], $cards[2]['lines']);
        $this->assertSame(['situation', 'context'], array_column(learning::context_cards('Uno. Dos. Tres.', 'es'), 'kind'));
    }

    /**
     * Indicators stay between 0 and 100.
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
