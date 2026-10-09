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

use mod_aisoftskills\local\learning;
use mod_aisoftskills\local\lesson;
use mod_aisoftskills\local\manager;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the third, very poor response (C) and the traffic-light indicators.
 *
 * @package    mod_aisoftskills
 * @category   test
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_aisoftskills\local\manager
 * @covers     \mod_aisoftskills\local\learning
 */
#[CoversClass(manager::class)]
#[CoversClass(learning::class)]
final class worst_test extends \advanced_testcase {
    /**
     * Two or three responses: one better, and with three exactly one very poor that is not the better one.
     */
    public function test_options_ok(): void {
        $b = ['text' => 'b', 'best' => 1];
        $p = ['text' => 'p', 'best' => 0];
        $w = ['text' => 'w', 'best' => 0, 'worst' => 1];
        $this->assertTrue(manager::options_ok([$b, $p]));
        $this->assertTrue(manager::options_ok([$p, $w, $b]));
        $this->assertFalse(manager::options_ok([$b]));
        $this->assertFalse(manager::options_ok([$b, $p, $p]));
        $this->assertFalse(manager::options_ok([$b, $w]));
        $this->assertFalse(manager::options_ok([$b, $p, ['text' => '', 'best' => 0, 'worst' => 1]]));
        $this->assertFalse(manager::options_ok([$b, $p, $w, $w]));
        $this->assertFalse(manager::options_ok([['text' => 'x', 'best' => 1, 'worst' => 1], $p, $w]));
    }

    /**
     * The very poor response does double the harm; with none marked, the larger drop is taken.
     */
    public function test_mark_worst(): void {
        $clean = fn($list) => array_map(fn($o) => manager::clean_option($o), $list);
        $out = manager::mark_worst($clean([
            ['text' => 'b', 'best' => 1, 'kpidelta' => 20],
            ['text' => 'p', 'kpidelta' => -15],
            ['text' => 'w', 'worst' => 1, 'kpidelta' => -10],
        ]));
        $this->assertSame([0, 0, 1], array_map(fn($o) => $o->worst, $out));
        $this->assertSame(-30, $out[2]->kpidelta);
        $out = manager::mark_worst($clean([
            ['text' => 'w', 'kpidelta' => -40],
            ['text' => 'b', 'best' => 1, 'kpidelta' => 20],
            ['text' => 'p', 'kpidelta' => -10],
        ]));
        $this->assertSame([1, 0, 0], array_map(fn($o) => $o->worst, $out));
        $this->assertSame(-40, $out[0]->kpidelta);
        // Never beyond the largest drop allowed.
        $out = manager::mark_worst($clean([
            ['text' => 'b', 'best' => 1], ['text' => 'p', 'kpidelta' => -40], ['text' => 'w', 'worst' => 1, 'kpidelta' => -45],
        ]));
        $this->assertSame(-50, $out[2]->kpidelta);
        // Two responses: nothing is very poor.
        $out = manager::mark_worst($clean([['text' => 'b', 'best' => 1], ['text' => 'p', 'worst' => 1]]));
        $this->assertSame([0, 0], array_map(fn($o) => $o->worst, $out));
    }

    /**
     * The editor accepts a very poor response at the largest drop, and asks for a larger drop when it is too small.
     */
    public function test_editor_validation(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $form = new \mod_aisoftskills\form\scene_form(null, ['rtl' => false]);
        $data = ['best' => 0, 'worst' => 2, 'title' => 'T'];
        foreach (['Better', 'Poorer', 'Very poor'] as $i => $text) {
            $data += ["text{$i}" => $text, "kpidelta{$i}" => [20, -30, -50][$i], "consequence{$i}" => '', "reason{$i}" => ''];
        }
        $errors = $form->validation($data, []);
        $this->assertArrayNotHasKey('kpidelta2', $errors);
        $data['kpidelta2'] = -40;
        $this->assertArrayHasKey('kpidelta2', $form->validation($data, []));
        // C left empty: two responses, no very poor one needed.
        $data['text2'] = '';
        $data['worst'] = -1;
        $this->assertSame([], $form->validation($data, []));
        // Three responses without a very poor one.
        $data['text2'] = 'Very poor';
        $this->assertArrayHasKey('worstgroup', $form->validation($data, []));
    }

    /**
     * A pasted lesson keeps three responses, and scenes with two still work.
     */
    public function test_lesson_with_three_responses(): void {
        $three = ['title' => 'T', 'options' => [
            ['text' => 'p', 'best' => false, 'worst' => false, 'kpi' => 'trust', 'kpidelta' => -15],
            ['text' => 'b', 'best' => true, 'kpi' => 'trust', 'kpidelta' => 20],
            ['text' => 'w', 'best' => false, 'worst' => true, 'kpi' => 'trust', 'kpidelta' => -20],
        ]];
        $two = ['title' => 'U', 'options' => [['text' => 'b', 'best' => true], ['text' => 'p', 'best' => false]]];
        $out = lesson::clean(['scenes' => [$three, $two]]);
        $this->assertCount(2, $out['scenes']);
        $this->assertSame([0, 0, 1], array_column($out['scenes'][0]['options'], 'worst'));
        $this->assertSame(-30, $out['scenes'][0]['options'][2]['kpidelta']);
        $this->assertCount(2, $out['scenes'][1]['options']);
    }

    /**
     * A learner who picks the very poor response sees it, and the shuffle spreads the better response over A, B and C.
     */
    public function test_play_three_responses(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $student = $gen->create_and_enrol($course, 'student');
        $ss = $gen->get_plugin_generator('mod_aisoftskills');
        $instance = $ss->create_instance(['course' => $course->id, 'kpiamber' => 30, 'kpigreen' => 60, 'shuffleoptions' => 1]);
        for ($i = 1; $i <= 6; $i++) {
            $ss->create_scene($instance, 'Scene ' . $i, 'Better', 'Poorer', ['worst' => 'Very poor']);
        }
        $instance = $DB->get_record('aisoftskills', ['id' => $instance->id], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('aisoftskills', $instance->id, $course->id, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $this->setUser($student);
        $data = learning::start_attempt($instance, $context, (int)$student->id, learning::MODE_PRACTICE);
        $this->assertSame(30, $data['kpiamber']);
        $this->assertSame(60, $data['kpigreen']);
        $prompt = lesson::prompt($instance);
        $this->assertStringContainsString('exactly three responses', $prompt);
        $this->assertStringContainsString('"worst":true', $prompt);
        $places = [];
        foreach ($data['scenes'] as $scene) {
            $this->assertSame(['A', 'B', 'C'], array_column($scene['options'], 'letter'));
            $this->assertArrayNotHasKey('worst', $scene['options'][0]);
            foreach ($scene['options'] as $k => $o) {
                $row = $DB->get_record('aisoftskills_option', ['id' => $o['id']]);
                if ($row->best) {
                    $places[] = $k;
                }
            }
        }
        sort($places);
        $this->assertSame([0, 0, 1, 1, 2, 2], $places);
        $scene = $data['scenes'][0];
        $worst = (int)$DB->get_field('aisoftskills_option', 'id', ['sceneid' => $scene['sceneid'], 'worst' => 1]);
        $attempt = $DB->get_record('aisoftskills_attempt', ['id' => $data['attemptid']]);
        $res = learning::choose($instance, $context, $attempt, (int)$scene['sceneid'], $worst);
        $this->assertSame(1, $res['worst']);
        $this->assertSame(0, $res['best']);
        $this->assertSame(-40, $res['delta']);
        $this->assertSame(10, $res['after']);
        // Practice: every poorer response tried stays blocked, the first and the second.
        $attempt = $DB->get_record('aisoftskills_attempt', ['id' => $data['attemptid']]);
        $poor = (int)$DB->get_field('aisoftskills_option', 'id', ['sceneid' => $scene['sceneid'], 'best' => 0, 'worst' => 0]);
        learning::choose($instance, $context, $attempt, (int)$scene['sceneid'], $poor);
        foreach ([$worst, $poor] as $again) {
            try {
                learning::choose($instance, $context, $attempt, (int)$scene['sceneid'], $again);
                $this->fail('A poorer response was chosen twice.');
            } catch (\moodle_exception $e) {
                $this->assertSame('alreadytried', $e->errorcode);
            }
        }
        $again = learning::start_attempt($instance, $context, (int)$student->id, learning::MODE_PRACTICE);
        $this->assertEqualsCanonicalizing([$worst, $poor], $again['scenes'][0]['triedlist']);
        $this->assertSame('red', learning::kpi_tone($res['after'], $instance));
        $this->assertSame('amber', learning::kpi_tone(30, $instance));
        $this->assertSame('green', learning::kpi_tone(60, $instance));
    }
}
