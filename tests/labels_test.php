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
            ['text' => 'Leo - Bartender', 'x' => 98.0, 'y' => 2.0, 'gender' => 'm', 'you' => true],
            ['text' => 'You', 'x' => 33.3, 'y' => 50.0, 'gender' => '', 'you' => false],
        ], $clean);
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
        $this->assertSame('f', labels::gender($text, 'Maria'));
        $this->assertSame('', labels::gender('Sam arrives.', 'Sam'));
        $this->assertSame('leo', labels::person('Leo - Bartender'));
        $this->assertSame('you', labels::person('You, the supervisor'));
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
        $this->assertSame([['text' => 'Leo', 'x' => 40.0, 'y' => 60.0, 'gender' => 'm', 'you' => false]], labels::get($stored));
        labels::save($scene, []);
        $this->assertNull($DB->get_field('aisoftskills_scene', 'labels', ['id' => $scene->id]));
    }
}
