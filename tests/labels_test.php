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

use mod_aisoftskills\local\labels;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for name labels on scene pictures.
 *
 * @package    mod_aisoftskills
 * @category   test
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_aisoftskills\local\labels
 */
#[CoversClass(labels::class)]
final class labels_test extends \advanced_testcase {
    /**
     * Labels are cleaned: text required, positions kept inside the picture, one learner at most, six at most.
     */
    public function test_clean(): void {
        $clean = labels::clean([
            ['text' => ' Leo - Bartender ', 'x' => 150, 'y' => -3, 'gender' => 'm', 'you' => true],
            ['text' => '', 'x' => 10, 'y' => 10],
            ['text' => 'You', 'x' => '33.33', 'y' => 'bad', 'gender' => 'x', 'you' => true],
            'not a label',
        ]);
        $this->assertSame([
            ['text' => 'Leo - Bartender', 'x' => 98.0, 'y' => 2.0, 'gender' => 'm', 'voice' => '', 'you' => true],
            ['text' => 'You', 'x' => 33.3, 'y' => 50.0, 'gender' => '', 'voice' => '', 'you' => false],
        ], $clean);
        // A chosen voice decides how the person sounds; an unknown voice is dropped.
        $voiced = labels::clean([['text' => 'Priya', 'gender' => 'm', 'voice' => 'Leda'], ['text' => 'Leo', 'voice' => 'Bob']]);
        $this->assertSame(['f', ''], array_column($voiced, 'gender'));
        $this->assertSame(['Leda', ''], array_column($voiced, 'voice'));
        $many = array_fill(0, 10, ['text' => 'A']);
        $this->assertCount(labels::MAX, labels::clean($many));
    }

    /**
     * Suggestions: the learner from the speaker, people named with a role in the text, he or she from the text.
     */
    public function test_suggest(): void {
        $scene = (object)['script' => '', 'speaker' => 'You, the functions shift supervisor',
            'context' => 'At 4:00 pm a venue gets more guests. You ask bartender Leo to extend his shift, but he ' .
                'hesitates. The events manager expects full bar coverage.'];
        $labels = labels::suggest($scene);
        $this->assertSame(['You - Functions shift supervisor', 'Leo - Bartender'], array_column($labels, 'text'));
        $this->assertSame([true, false], array_column($labels, 'you'));
        $this->assertSame(['', 'm'], array_column($labels, 'gender'));
        $this->assertSame([33.3, 66.7], array_column($labels, 'x'));
    }

    /**
     * Roles, genders and people.
     */
    public function test_helpers(): void {
        $text = 'Nurse Priya tells charge nurse Maria that she is exhausted. Maria asks her to stay. Tell Sam now.';
        $this->assertSame('Charge nurse', labels::role($text, 'Maria'));
        $this->assertSame('', labels::role($text, 'Sam'));
        // Adverbs and verbs before a name are not a role.
        $this->assertSame('', labels::role('A colleague quietly mentions Priya is crying.', 'Priya'));
        $this->assertSame('', labels::role('The patient thanked Priya.', 'Priya'));
        $this->assertSame('Nurse', labels::role('The trained nurse Priya waits.', 'Priya'));
        $this->assertSame('Shift supervisor', labels::role('The shift supervisor Ana waits.', 'Ana'));
        // A title belongs to the name: "resident Mrs Tanaka" is Mrs Tanaka, who is a woman.
        $care = 'The resident Mrs Tanaka refuses her lunch. Her son calls.';
        $this->assertSame(['Mrs Tanaka'], labels::named($care));
        $this->assertSame('Resident', labels::role($care, 'Mrs Tanaka'));
        $this->assertSame('f', labels::gender($care, 'Mrs Tanaka'));
        // The picture prompt names everyone with their role and voice, and the learner's role.
        $scene = (object)['labels' => json_encode([['text' => 'You - Nursing shift supervisor', 'you' => true],
            ['text' => 'Priya - Nurse', 'voice' => 'Leda'], ['text' => 'Mrs Tanaka - Resident', 'gender' => 'f']])];
        $this->assertSame('the nursing shift supervisor (the person the learner plays); Priya, the nurse, a woman; '
            . 'Mrs Tanaka, the resident, a woman', \mod_aisoftskills\local\lesson::picture_people($scene));
        $this->assertSame('f', labels::gender($text, 'Maria'));
        $this->assertSame('', labels::gender('Sam arrives.', 'Sam'));
        $this->assertSame('leo', labels::person('Leo - Bartender'));
        $this->assertSame('you', labels::person('You, the supervisor'));
    }

    /**
     * Labels suggested by earlier versions are repaired; good labels, positions and voices are kept.
     */
    public function test_repair(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_aisoftskills');
        $instance = $gen->create_instance(['course' => $course->id]);
        $scene = $gen->create_scene($instance, 'Ward', 'B', 'P', ['context' =>
            'A junior nurse quietly mentions Priya made an error. The resident Mrs Tanaka is upset.']);
        $DB->set_field('aisoftskills_scene', 'labels', json_encode([
            ['text' => 'You - Nursing shift supervisor', 'x' => 70, 'y' => 50, 'gender' => '', 'you' => true],
            ['text' => 'Priya - Quietly mentions', 'x' => 30, 'y' => 40, 'gender' => 'f', 'voice' => 'Leda', 'you' => false],
            ['text' => 'Mrs - Resident', 'x' => 20, 'y' => 60, 'gender' => '', 'you' => false],
            ['text' => 'Leo - Bartender', 'x' => 50, 'y' => 50, 'gender' => 'm', 'you' => false],
        ]), ['id' => $scene->id]);
        $this->assertTrue(labels::repair($DB->get_record('aisoftskills_scene', ['id' => $scene->id])));
        $labels = labels::get($DB->get_record('aisoftskills_scene', ['id' => $scene->id]));
        $this->assertSame(
            ['You - Nursing shift supervisor', 'Priya', 'Mrs Tanaka - Resident', 'Leo - Bartender'],
            array_column($labels, 'text')
        );
        $this->assertSame(['', 'f', 'f', 'm'], array_column($labels, 'gender'));
        $this->assertSame('Leda', $labels[1]['voice']);
        $this->assertSame(30.0, $labels[1]['x']);
        $this->assertFalse(labels::repair($DB->get_record('aisoftskills_scene', ['id' => $scene->id])));
    }

    /**
     * Dialogue lines keep stable ids through edits: unchanged and edited lines keep theirs, new lines get new ones, and
     * deleting a line never moves another line's id.
     */
    public function test_dialogue_line_ids(): void {
        $before = \mod_aisoftskills\local\manager::text_to_script("Pat: One.\nAlex: Two.\nPat: Three.");
        $this->assertSame([1, 2, 3], array_column(\mod_aisoftskills\local\manager::dialogue($before), 'id'));
        $ids = fn($text) => array_column(\mod_aisoftskills\local\manager::dialogue(
            \mod_aisoftskills\local\manager::text_to_script($text, $before)
        ), 'id');
        $this->assertSame([2, 3], $ids("Alex: Two.\nPat: Three."), 'Line 1 deleted.');
        $this->assertSame([1, 2, 3], $ids("Pat: One.\nAlex: Two, edited.\nPat: Three."), 'Line 2 edited.');
        $this->assertSame([1, 4, 2, 3], $ids("Pat: One.\nSam: New.\nAlex: Two.\nPat: Three."), 'A line added.');
        $this->assertSame([1, 3], $ids("Pat: One.\nPat: Three."), 'Line 2 deleted.');
        // Scripts from before ids: 1, 2, 3 in order, the same every time.
        $old = json_encode(['dialogue' => [['speaker' => 'A', 'line' => 'x'], ['speaker' => 'B', 'line' => 'y']]]);
        $this->assertSame([1, 2], array_column(\mod_aisoftskills\local\manager::dialogue($old), 'id'));
    }

    /**
     * Saving stores clean JSON, and an empty list removes the labels.
     */
    public function test_save(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_aisoftskills');
        $instance = $gen->create_instance(['course' => $course->id]);
        $scene = $gen->create_scene($instance, 'One');
        labels::save($scene, [['text' => 'Leo', 'x' => 40, 'y' => 60, 'gender' => 'm', 'you' => false]]);
        $stored = $DB->get_record('aisoftskills_scene', ['id' => $scene->id]);
        $this->assertSame(
            [['text' => 'Leo', 'x' => 40.0, 'y' => 60.0, 'gender' => 'm', 'voice' => '', 'you' => false]],
            labels::get($stored)
        );
        // A voice chosen for a person is given to their labels in the other scenes too.
        $other = $gen->create_scene($instance, 'Two');
        labels::save($other, [['text' => 'Leo - Bartender', 'x' => 20, 'y' => 50, 'gender' => 'm', 'you' => false]]);
        labels::save($scene, [['text' => 'Leo', 'x' => 40, 'y' => 60, 'voice' => 'Orus', 'you' => false]]);
        $this->assertSame('Orus', labels::get($DB->get_record('aisoftskills_scene', ['id' => $other->id]))[0]['voice']);
        labels::save($scene, []);
        $this->assertNull($DB->get_field('aisoftskills_scene', 'labels', ['id' => $scene->id]));
    }
}
