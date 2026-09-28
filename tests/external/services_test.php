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

namespace mod_aisoftskills\external;

use core_external\external_api;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Web service permission and behaviour tests.
 *
 * @package    mod_aisoftskills
 * @category   test
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_aisoftskills\external\start_attempt
 * @covers     \mod_aisoftskills\external\choose_option
 * @covers     \mod_aisoftskills\external\finish_attempt
 * @covers     \mod_aisoftskills\external\import_lesson
 * @covers     \mod_aisoftskills\external\generate_image
 */
#[CoversClass(start_attempt::class)]
#[CoversClass(choose_option::class)]
#[CoversClass(finish_attempt::class)]
#[CoversClass(import_lesson::class)]
#[CoversClass(generate_image::class)]
final class services_test extends \advanced_testcase {
    /** @var array */
    protected $u = [];
    /** @var \stdClass */
    protected $cm;
    /** @var \stdClass */
    protected $scene;

    /**
     * A course with a teacher, a non-editing teacher, two learners and an outsider.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        foreach (['editingteacher', 'teacher', 'student', 'student2'] as $role) {
            $this->u[$role] = $gen->create_and_enrol($course, $role === 'student2' ? 'student' : $role);
        }
        $this->u['outsider'] = $gen->create_user();
        $ss = $gen->get_plugin_generator('mod_aisoftskills');
        $instance = $ss->create_instance(['course' => $course->id]);
        $this->scene = $ss->create_scene($instance, 'Behind on target');
        $this->cm = get_coursemodule_from_instance('aisoftskills', $instance->id);
    }

    /**
     * Calls a service as a user and returns the cleaned result.
     *
     * @param string $user
     * @param string $class
     * @param array $args
     * @return mixed
     */
    protected function call(string $user, string $class, array $args) {
        $this->setUser($this->u[$user]);
        $class = '\\mod_aisoftskills\\external\\' . $class;
        $result = $class::execute(...$args);
        return external_api::clean_returnvalue($class::execute_returns(), $result);
    }

    /**
     * Option id of the better or poorer response.
     *
     * @param bool $best
     * @return int
     */
    protected function option(bool $best): int {
        global $DB;
        return (int)$DB->get_field('aisoftskills_option', 'id', ['sceneid' => $this->scene->id, 'best' => $best ? 1 : 0]);
    }

    /**
     * A learner plays a whole attempt through the services.
     * @covers \mod_aisoftskills\external\start_attempt
     * @covers \mod_aisoftskills\external\choose_option
     * @covers \mod_aisoftskills\external\finish_attempt
     */
    public function test_play(): void {
        $data = $this->call('student', 'start_attempt', [(int)$this->cm->id]);
        $this->assertCount(1, $data['scenes']);
        $this->assertArrayNotHasKey('best', $data['scenes'][0]['options'][0]);
        $poor = $this->call('student', 'choose_option', [$data['attemptid'], (int)$this->scene->id, $this->option(false)]);
        $this->assertFalse($poor['best']);
        $this->assertTrue($poor['canretry']);
        $this->assertSame(30, $poor['after']);
        $best = $this->call('student', 'choose_option', [$data['attemptid'], (int)$this->scene->id, $this->option(true)]);
        $this->assertTrue($best['best']);
        $this->assertFalse($best['first']);
        $this->assertTrue($best['allresolved']);
        $summary = $this->call('student', 'finish_attempt', [$data['attemptid']]);
        $this->assertSame(0, $summary['score']);
        $this->assertSame('beginning', $summary['rating']);
    }

    /**
     * Nobody else can play or finish a learner's attempt, and outsiders and teachers cannot start one.
     * @covers \mod_aisoftskills\external\start_attempt
     * @covers \mod_aisoftskills\external\choose_option
     */
    public function test_permissions(): void {
        $data = $this->call('student', 'start_attempt', [(int)$this->cm->id]);
        foreach (['student2', 'teacher', 'outsider'] as $user) {
            try {
                $this->call($user, 'choose_option', [$data['attemptid'], (int)$this->scene->id, $this->option(true)]);
                $this->fail($user . ' chose in another learner\'s attempt');
            } catch (\moodle_exception $e) {
                $this->assertNotEmpty($e->errorcode);
            }
        }
        foreach (['outsider', 'teacher'] as $user) {
            try {
                $this->call($user, 'start_attempt', [(int)$this->cm->id]);
                $this->fail($user . ' started an attempt');
            } catch (\moodle_exception $e) {
                $this->assertNotEmpty($e->errorcode);
            }
        }
    }

    /**
     * Teachers import drafts; learners and non-editing teachers cannot; pictures by AI are not available.
     * @covers \mod_aisoftskills\external\import_lesson
     * @covers \mod_aisoftskills\external\generate_image
     */
    public function test_teacher_services(): void {
        $draft = json_encode(['scenes' => [['title' => 'New', 'options' => [['text' => 'A', 'best' => true],
            ['text' => 'B', 'best' => false]]]]]);
        $this->assertSame(['scenes' => 1], $this->call('editingteacher', 'import_lesson', [(int)$this->cm->id, $draft]));
        foreach (['student', 'teacher'] as $user) {
            try {
                $this->call($user, 'import_lesson', [(int)$this->cm->id, $draft]);
                $this->fail($user . ' imported scenes');
            } catch (\required_capability_exception $e) {
                $this->assertSame('nopermissions', $e->errorcode);
            }
        }
        try {
            $this->call('editingteacher', 'generate_image', [(int)$this->scene->id]);
            $this->fail('A picture was generated');
        } catch (\moodle_exception $e) {
            $this->assertSame('ainotavailable', $e->errorcode);
        }
    }

    /**
     * A teacher creates a scene picture through LMS Labs: it is stored, and the charge and balance are reported.
     * @covers \mod_aisoftskills\external\generate_image
     */
    public function test_generate_image_success(): void {
        global $DB;
        set_config('aiimages', 1, 'mod_aisoftskills');
        set_config('lmslabssiteid', 'site', 'mod_aisoftskills');
        set_config('lmslabsapikey', 'key', 'mod_aisoftskills');
        $png = file_get_contents(__DIR__ . '/../fixtures/scene.png');
        $sent = [];
        \mod_aisoftskills\local\ai\lmslabs::$posttransport = function ($url, $headers, $body) use ($png, &$sent) {
            $sent[] = json_decode($body, true);
            return [200, ['content-type' => 'image/png', 'x-request-id' => 'abc-1', 'x-credits-charged' => '5',
                'x-credits-balance' => '42'], $png];
        };
        try {
            $long = str_repeat('A long description. ', 200);
            $DB->set_field('aisoftskills_scene', 'imageprompt', $long, ['id' => $this->scene->id]);
            $result = $this->call('editingteacher', 'generate_image', [(int)$this->scene->id]);
        } finally {
            \mod_aisoftskills\local\ai\lmslabs::$posttransport = null;
        }
        $this->assertSame(5, $result['charged']);
        $this->assertSame(42, $result['balance']);
        $this->assertSame('abc-1', $result['requestid']);
        $this->assertStringContainsString('pluginfile.php', $result['url']);
        $this->assertCount(1, $sent);
        $this->assertLessThanOrEqual(2000, \core_text::strlen($sent[0]['prompt']), 'The prompt is shortened to the route limit.');
        $this->assertStringContainsString('No text', $sent[0]['prompt']);
        $this->assertSame('illustration', $sent[0]['style']);
        $this->assertSame(1, $DB->count_records('aisoftskills_ailog', ['action' => 'image', 'status' => 'ok']));
        try {
            $this->call('teacher', 'generate_image', [(int)$this->scene->id]);
            $this->fail('A non-editing teacher created a picture');
        } catch (\required_capability_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }
    }
}
