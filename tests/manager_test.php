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

use mod_aisoftskills\local\manager;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for scenes, responses and pictures.
 *
 * @package    mod_aisoftskills
 * @category   test
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_aisoftskills\local\manager
 */
#[CoversClass(manager::class)]
final class manager_test extends \advanced_testcase {
    /**
     * A scene must have exactly two responses with one better response.
     */
    public function test_save_scene_rules(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('aisoftskills', ['course' => $course->id]);
        $scene = $DB->get_record('aisoftskills_scene', ['id' => manager::add_scene($instance, ['title' => 'S'])]);
        $bad = [
            [['text' => 'A', 'best' => 1]],
            [['text' => 'A', 'best' => 1], ['text' => 'B', 'best' => 1]],
            [['text' => 'A', 'best' => 0], ['text' => 'B', 'best' => 0]],
            [['text' => 'A', 'best' => 1], ['text' => '', 'best' => 0]],
        ];
        foreach ($bad as $options) {
            try {
                manager::save_scene($scene, [], $options);
                $this->fail('Saved ' . json_encode($options));
            } catch (\moodle_exception $e) {
                $this->assertSame('twooptionsrequired', $e->errorcode);
            }
        }
        $this->assertSame(0, $DB->count_records('aisoftskills_option', ['sceneid' => $scene->id]));
        manager::save_scene(
            $scene,
            ['title' => ' New title ', 'question' => 'What now?'],
            [['text' => 'A', 'best' => 0, 'kpidelta' => -5], ['text' => 'B', 'best' => 1, 'kpidelta' => 15]]
        );
        manager::save_scene($scene, [], [['text' => 'A2', 'best' => 1], ['text' => 'B2', 'best' => 0]]);
        $options = array_values($DB->get_records('aisoftskills_option', ['sceneid' => $scene->id], 'sortorder'));
        $this->assertCount(2, $options);
        $this->assertSame(['A2', 'B2'], [$options[0]->text, $options[1]->text]);
        $this->assertSame('New title', $DB->get_field('aisoftskills_scene', 'title', ['id' => $scene->id]));
    }

    /**
     * A scene is ready with a picture and valid responses; deleting it removes its data.
     */
    public function test_ready_and_delete(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $ss = $this->getDataGenerator()->get_plugin_generator('mod_aisoftskills');
        $instance = $ss->create_instance(['course' => $course->id]);
        $cm = get_coursemodule_from_instance('aisoftskills', $instance->id);
        $context = \context_module::instance($cm->id);
        $with = $ss->create_scene($instance, 'With picture');
        $ss->create_scene($instance, 'Without picture', 'A', 'B', [], '');
        $ready = manager::ready_scenes($instance, $context);
        $this->assertCount(1, $ready);
        $this->assertSame((int)$with->id, (int)$ready[0][0]->id);
        manager::delete_scene($context, $with);
        $this->assertFalse($DB->record_exists('aisoftskills_scene', ['id' => $with->id]));
        $this->assertSame(0, $DB->count_records('aisoftskills_option', ['sceneid' => $with->id]));
        $this->assertNull(manager::get_scene_file($context, (int)$with->id));
    }

    /**
     * Settings are normalised.
     */
    public function test_prepare_instance_data(): void {
        $data = manager::prepare_instance_data((object)['industry' => 'space', 'level' => 'king', 'contentlang' => 'xx',
            'imagestyle' => 'oil', 'allowretry' => 'yes', 'maxattempts' => 500, 'grademethod' => 9]);
        $this->assertSame('office', $data->industry);
        $this->assertSame('supervisor', $data->level);
        $this->assertSame('en', $data->contentlang);
        $this->assertSame('illustration', $data->imagestyle);
        $this->assertSame(1, $data->allowretry);
        $this->assertSame(100, $data->maxattempts);
        $this->assertSame(manager::GRADE_HIGHEST, $data->grademethod);
    }
}
