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

use mod_aisoftskills\local\setuppath;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the set-up path.
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_aisoftskills\local\setuppath
 */
#[CoversClass(setuppath::class)]
final class setuppath_test extends \advanced_testcase {
    /**
     * Each step has one page; steps 1 to 4 share the builder page.
     */
    public function test_urls_and_bar(): void {
        $this->assertSame('/mod/aisoftskills/builder.php?id=3&start=2', setuppath::url(3, 2)->out_as_local_url(false));
        $this->assertSame('/mod/aisoftskills/builder.php?id=3&step=build', setuppath::url(3, 5)->out_as_local_url(false));
        $this->assertSame('/mod/aisoftskills/scenes.php?id=3&step=pictures', setuppath::url(3, 6)->out_as_local_url(false));
        $this->assertSame('/mod/aisoftskills/scenes.php?id=3&step=check', setuppath::url(3, 7)->out_as_local_url(false));
        $this->assertSame('/mod/aisoftskills/builder.php?id=3&step=finish', setuppath::url(3, 8)->out_as_local_url(false));
        $bar = setuppath::bar(6);
        $this->assertCount(8, $bar['steps']);
        $this->assertSame([true, true, true, true, true, false, false, false], array_column($bar['steps'], 'done'));
        $this->assertTrue($bar['steps'][5]['current']);
        $this->assertSame('Pictures', $bar['steps'][5]['name']);
    }

    /**
     * Set-up resumes at the first step that is not done: pictures come before the responses check.
     */
    public function test_state_and_resume(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $plugin = $gen->get_plugin_generator('mod_aisoftskills');
        $instance = $plugin->create_instance(['course' => $course->id]);
        $context = \context_module::instance($instance->cmid);

        $state = setuppath::state($instance, $context);
        $this->assertSame(0, $state['scenes']);
        $this->assertSame(1, setuppath::resume_step($state, false));
        $this->assertSame(setuppath::CREATE, setuppath::resume_step($state, true));

        $ready = $plugin->create_scene($instance, 'Ready');
        $nopicture = $plugin->create_scene($instance, 'No picture', 'Better', 'Poorer', [], '');
        $state = setuppath::state($instance, $context);
        $this->assertSame(2, $state['scenes']);
        $this->assertSame([(int)$nopicture->id], $state['nopicture']);
        $this->assertSame(1, $state['ready']);
        $this->assertSame(setuppath::PICTURES, setuppath::resume_step($state, true));

        local\manager::save_scene_image_bytes($context, $nopicture, file_get_contents(__DIR__ . '/fixtures/scene.png'));
        global $DB;
        $draftid = local\manager::add_scene($instance, ['title' => 'From your own text']);
        $draft = $DB->get_record('aisoftskills_scene', ['id' => $draftid]);
        local\manager::save_scene_image_bytes($context, $draft, file_get_contents(__DIR__ . '/fixtures/scene.png'));
        $state = setuppath::state($instance, $context);
        $this->assertSame([], $state['nopicture']);
        $this->assertSame([(int)$draft->id], $state['noresponses']);
        $this->assertSame(setuppath::CHECK, setuppath::resume_step($state, true));
        $this->assertNotEmpty($ready);
    }
}
