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

use mod_aisoftskills\local\lesson;
use mod_aisoftskills\local\manager;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the AI prompt, reading pasted drafts and importing scenes.
 *
 * @package    mod_aisoftskills
 * @category   test
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_aisoftskills\local\lesson
 */
#[CoversClass(lesson::class)]
final class lesson_test extends \advanced_testcase {
    /**
     * A draft with one good scene and three that must be dropped.
     *
     * @return string
     */
    protected function draft(): string {
        $good = ['skill' => 'Motivating others', 'title' => 'Behind on target', 'context' => 'An hour to go.',
            'speaker' => 'You, the supervisor', 'question' => 'What do you say?', 'imageprompt' => 'A tired team',
            'options' => [
                ['text' => 'Is there anything I can get you to help you reach your goals faster?', 'best' => true,
                    'kpi' => 'motivation', 'kpidelta' => 25, 'consequence' => 'They ask for a second scanner.',
                    'reason' => 'Help removes blockers.'],
                ['text' => '<b>Hurry up!</b>', 'best' => false, 'kpi' => 'morale', 'kpidelta' => 30,
                    'consequence' => 'People rush.', 'reason' => 'Pressure alone backfires.'],
            ]];
        $twobest = $good;
        $twobest['options'][1]['best'] = true;
        $oneoption = $good;
        $oneoption['options'] = [$good['options'][0]];
        $notitle = $good;
        $notitle['title'] = '';
        return "Here you go:\n\x60\x60\x60json\n" . json_encode(['scenes' => [$good, $twobest, $oneoption, $notitle]]) .
            "\n\x60\x60\x60\nEnjoy!";
    }

    /**
     * Only scenes with two responses and exactly one better response are kept; deltas follow the better flag.
     * @covers \mod_aisoftskills\local\lesson
     */
    public function test_parse(): void {
        $this->resetAfterTest();
        $draft = lesson::parse($this->draft());
        $this->assertCount(1, $draft['scenes']);
        [$better, $poorer] = $draft['scenes'][0]['options'];
        $this->assertSame(1, $better['best']);
        $this->assertSame(25, $better['kpidelta']);
        $this->assertSame(0, $poorer['best']);
        $this->assertSame(-30, $poorer['kpidelta'], 'A poorer response can never raise an indicator.');
        $this->assertSame('Hurry up!', $poorer['text']);
        foreach (['nothing', '{"scenes": []}'] as $bad) {
            try {
                lesson::parse($bad);
                $this->fail('Accepted ' . $bad);
            } catch (\moodle_exception $e) {
                $this->assertContains($e->errorcode, ['lessoninvalid', 'lessonempty']);
            }
        }
    }

    /**
     * Unknown indicators fall back and deltas are limited.
     * @covers \mod_aisoftskills\local\manager
     */
    public function test_clean_option(): void {
        $o = manager::clean_option(['text' => 'x', 'best' => 1, 'kpi' => 'nonsense', 'kpidelta' => 400]);
        $this->assertSame('morale', $o->kpi);
        $this->assertSame(50, $o->kpidelta);
        $o = manager::clean_option(['text' => 'x', 'best' => 1, 'kpidelta' => -10]);
        $this->assertSame(10, $o->kpidelta);
        $o = manager::clean_option(['text' => 'x', 'best' => 0]);
        $this->assertSame(-20, $o->kpidelta);
    }

    /**
     * Imported scenes land in order with their two responses.
     * @covers \mod_aisoftskills\local\lesson
     */
    public function test_import(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('aisoftskills', ['course' => $course->id]);
        $result = lesson::import($instance, lesson::parse($this->draft()));
        $this->assertSame(['scenes' => 1], $result);
        $scene = $DB->get_record('aisoftskills_scene', ['aisoftskillsid' => $instance->id], '*', MUST_EXIST);
        $this->assertSame('Behind on target', $scene->title);
        $this->assertSame('You, the supervisor', $scene->speaker);
        $options = $DB->get_records('aisoftskills_option', ['sceneid' => $scene->id], 'sortorder');
        $this->assertCount(2, $options);
        $this->assertSame([1, 0], array_values(array_map(fn($o) => (int)$o->best, $options)));
    }

    /**
     * The prompt carries the industry, level, language and skills, in English whatever the teacher's language.
     * @covers \mod_aisoftskills\local\lesson
     */
    public function test_prompt(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('aisoftskills', ['course' => $course->id,
            'industry' => 'custom', 'customindustry' => 'Veterinary clinic', 'level' => 'leader', 'contentlang' => 'es',
            'skills' => json_encode(['keys' => ['empathy', 'nonsense'], 'custom' => ['Calming pet owners'], 'scenes' => 5])]);
        $prompt = lesson::prompt($instance);
        foreach (
            ['Industry: Veterinary clinic', 'Leader', 'Spanish', '- Empathy', '- Calming pet owners', 'Create 5 scenes',
                'customersatisfaction', '"options"'] as $needle
        ) {
            $this->assertStringContainsString($needle, $prompt);
        }
        $this->assertStringNotContainsString('nonsense', $prompt);
        $scene = (object)['title' => 'T', 'imageprompt' => 'A worried dog owner at the counter'];
        $image = lesson::image_prompt($instance, $scene);
        $this->assertStringContainsString('A worried dog owner at the counter', $image);
        $this->assertStringContainsString('Industry: Veterinary clinic', $image);
        $this->assertStringContainsString('No text', $image);
    }
}
