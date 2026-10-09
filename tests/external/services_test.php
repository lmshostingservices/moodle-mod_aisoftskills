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
 * @covers     \mod_aisoftskills\external\draft_scene
 * @covers     \mod_aisoftskills\external\check_request
 * @covers     \mod_aisoftskills\external\dismiss_request
 */
#[CoversClass(start_attempt::class)]
#[CoversClass(choose_option::class)]
#[CoversClass(finish_attempt::class)]
#[CoversClass(import_lesson::class)]
#[CoversClass(generate_image::class)]
#[CoversClass(draft_scene::class)]
#[CoversClass(check_request::class)]
#[CoversClass(dismiss_request::class)]
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
        // The plugin is unlocked on this site; test_locked_site covers the opposite.
        set_config('unlockstate', json_encode(['status' => 'unlocked', 'checkedat' => time()]), 'mod_aisoftskills');
    }

    /**
     * Resets the fake LMS Labs transport.
     */
    protected function tearDown(): void {
        \mod_aisoftskills\local\ai\lmslabs::$posttransport = null;
        parent::tearDown();
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
        // The debrief shows the better response for the scene where the first choice was the poorer one.
        $this->assertCount(1, $summary['takeaways']);
        $this->assertSame('Behind on target', $summary['takeaways'][0]['title']);
    }

    /**
     * Teachers save name labels (free); learners and other users cannot; voiceover needs the site setting.
     */
    public function test_labels_and_voice_services(): void {
        global $DB;
        $labels = [['text' => 'Leo - Bartender', 'x' => 30.5, 'y' => 55, 'gender' => 'm', 'you' => false],
            ['text' => 'You - Supervisor', 'x' => 70, 'y' => 55, 'gender' => 'f', 'you' => true]];
        $saved = $this->call('editingteacher', 'save_labels', [(int)$this->scene->id, $labels]);
        $this->assertSame(['Leo - Bartender', 'You - Supervisor'], array_column($saved, 'text'));
        $this->assertNotNull($DB->get_field('aisoftskills_scene', 'labels', ['id' => $this->scene->id]));
        foreach (['student', 'outsider'] as $user) {
            try {
                $this->call($user, 'save_labels', [(int)$this->scene->id, []]);
                $this->fail($user . ' saved labels');
            } catch (\moodle_exception $e) {
                $this->assertNotEmpty($e->errorcode);
            }
        }
        // The learner's page carries the labels, and no voiceover while it is off.
        $data = $this->call('student', 'start_attempt', [(int)$this->cm->id, 'practice']);
        $this->assertSame('practice', $data['mode']);
        $this->assertCount(2, $data['scenes'][0]['labels']);
        $this->assertSame([], $data['scenes'][0]['voice']);
        $this->assertSame('As the supervisor, how would you handle this situation?', $data['scenes'][0]['roleline']);
        try {
            $this->call('editingteacher', 'create_voice', [(int)$this->scene->id, 0]);
            $this->fail('A clip was created while voiceover is off');
        } catch (\moodle_exception $e) {
            $this->assertSame('ainotavailable', $e->errorcode);
        }
    }

    /**
     * Nobody else can play or finish a learner's attempt, and outsiders and teachers cannot start one.
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
     * Teachers create scenes from an AI assistant's draft, charged per scene like LMS Labs drafts; learners and
     * non-editing teachers cannot; pictures by AI are not available while switched off.
     */
    public function test_teacher_services(): void {
        global $DB;
        $draft = json_encode(['scenes' => [['title' => 'New', 'options' => [['text' => 'A', 'best' => true],
            ['text' => 'B', 'best' => false]]]]]);
        // Without an LMS Labs connection nothing is created.
        try {
            $this->call('editingteacher', 'import_lesson', [(int)$this->cm->id, $draft]);
            $this->fail('Scenes were created without LMS Labs');
        } catch (\moodle_exception $e) {
            $this->assertSame('ainotavailable', $e->errorcode);
        }
        set_config('lmslabssiteid', 'site', 'mod_aisoftskills');
        set_config('lmslabsapikey', 'key', 'mod_aisoftskills');
        $sent = [];
        \mod_aisoftskills\local\ai\lmslabs::$posttransport = function ($url, $headers, $body) use (&$sent) {
            $sent[] = [$url, json_decode($body, true)];
            return [200, ['content-type' => 'application/json'], json_encode(['requestId' => 'imp-1',
                'creditsCharged' => 5, 'creditsBalance' => 47])];
        };
        $before = $DB->count_records('aisoftskills_scene');
        $result = $this->call('editingteacher', 'import_lesson', [(int)$this->cm->id, $draft]);
        $this->assertSame('completed', $result['status']);
        $this->assertSame('import', $result['operation']);
        $this->assertSame($before + 1, $DB->count_records('aisoftskills_scene'));
        $this->assertSame('https://lms-labs.com/api/moodle/ai-softskills/scenes/import', $sent[0][0]);
        $this->assertSame(['sceneCount' => 1, 'titles' => ['New']], $sent[0][1]);
        foreach (['student', 'teacher'] as $user) {
            try {
                $this->call($user, 'import_lesson', [(int)$this->cm->id, $draft]);
                $this->fail($user . ' imported scenes');
            } catch (\required_capability_exception $e) {
                $this->assertSame('nopermissions', $e->errorcode);
            }
        }
        set_config('aiimages', 0, 'mod_aisoftskills');
        try {
            $this->call('editingteacher', 'generate_image', [(int)$this->scene->id]);
            $this->fail('A picture was generated');
        } catch (\moodle_exception $e) {
            $this->assertSame('ainotavailable', $e->errorcode);
        }
    }

    /**
     * Until LMS Labs has unlocked the plugin, nothing can be set up or played; requests already made can still be
     * looked at and dismissed.
     */
    public function test_locked_site(): void {
        unset_config('unlockstate', 'mod_aisoftskills');
        $draft = json_encode(['scenes' => [['title' => 'New', 'options' => [['text' => 'A', 'best' => true],
            ['text' => 'B', 'best' => false]]]]]);
        foreach (
            [['editingteacher', 'import_lesson', [(int)$this->cm->id, $draft]],
                ['editingteacher', 'draft_scene', [(int)$this->cm->id, 'A brief']],
                ['editingteacher', 'generate_image', [(int)$this->scene->id]],
                ['student', 'start_attempt', [(int)$this->cm->id]]] as [$user, $class, $args]
        ) {
            try {
                $this->call($user, $class, $args);
                $this->fail($class . ' worked on a locked site');
            } catch (\moodle_exception $e) {
                $this->assertSame('notactivated', $e->errorcode, $class);
            }
        }
        // A check that got no answer keeps an unlocked site usable; a definite "locked" does not.
        set_config('unlockstate', json_encode(['status' => 'unknown', 'wasunlocked' => true]), 'mod_aisoftskills');
        $this->assertTrue(\mod_aisoftskills\local\unlock::active());
        set_config('unlockstate', json_encode(['status' => 'locked']), 'mod_aisoftskills');
        $this->assertFalse(\mod_aisoftskills\local\unlock::active());
    }

    /**
     * A teacher creates a scene picture through LMS Labs: it is stored, and the charge and balance are reported.
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
        $this->assertSame('completed', $result['status']);
        $this->assertSame('abc-1', $result['requestid']);
        $this->assertStringContainsString('5 LMS Labs credits used; 42 left', $result['message']);
        [$url] = \mod_aisoftskills\local\manager::get_scene_image(
            \context_module::instance($this->cm->id),
            (int)$this->scene->id
        );
        $this->assertStringContainsString('pluginfile.php', $url);
        $this->assertCount(1, $sent);
        $this->assertLessThanOrEqual(2000, \core_text::strlen($sent[0]['prompt']), 'The prompt is shortened to the route limit.');
        $this->assertStringContainsString('No text', $sent[0]['prompt']);
        $this->assertSame('illustration', $sent[0]['style']);
        $this->assertSame(1, $DB->count_records('aisoftskills_ailog', ['action' => 'image', 'status' => 'completed']));
        try {
            $this->call('teacher', 'generate_image', [(int)$this->scene->id]);
            $this->fail('A non-editing teacher created a picture');
        } catch (\required_capability_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }
    }

    /**
     * Scene drafts: only teachers who manage the activity and may use AI; checking again reuses the stored request.
     */
    public function test_draft_scene_services(): void {
        global $DB;
        set_config('lmslabssiteid', 'site', 'mod_aisoftskills');
        set_config('lmslabsapikey', 'key', 'mod_aisoftskills');
        $keys = [];
        $answers = [
            [202, ['retry-after' => '5'], json_encode(['requestId' => 'r1', 'error' => ['code' => 'PENDING',
                'message' => 'PENDING']])],
            [200, [], json_encode(['requestId' => 'r1', 'model' => 'gpt-4o-2024-08-06', 'creditsCharged' => 5,
                'creditsBalance' => 97, 'draft' => ['title' => 'A clash', 'setting' => 'An office', 'characters' => ['Pat', 'Alex'],
                'dialogue' => [['speaker' => 'Pat', 'line' => 'We need to talk.'], ['speaker' => 'Alex', 'line' => 'Now?']],
                'teachingNote' => 'Listen first.']])],
        ];
        \mod_aisoftskills\local\ai\lmslabs::$posttransport = function ($url, $headers, $body) use (&$keys, &$answers) {
            $keys[] = array_values(preg_grep('/^Idempotency-Key: /', $headers))[0] . '|' . $body;
            return array_shift($answers);
        };
        try {
            foreach (['student', 'teacher'] as $user) {
                try {
                    $this->call($user, 'draft_scene', [(int)$this->cm->id, 'A clash about rotas']);
                    $this->fail($user . ' drafted a scene');
                } catch (\required_capability_exception $e) {
                    $this->assertSame('nopermissions', $e->errorcode);
                }
            }
            $first = $this->call('editingteacher', 'draft_scene', [(int)$this->cm->id, 'A clash about rotas', 'Leaders', '']);
            $this->assertSame('pending', $first['status']);
            $this->assertTrue($first['poll']);
            $this->assertSame(5, $first['retryafter']);
            try {
                $this->call('teacher', 'check_request', [(int)$this->cm->id, $first['id']]);
                $this->fail('A non-editing teacher checked a request');
            } catch (\required_capability_exception $e) {
                $this->assertSame('nopermissions', $e->errorcode);
            }
            $done = $this->call('editingteacher', 'check_request', [(int)$this->cm->id, $first['id']]);
        } finally {
            \mod_aisoftskills\local\ai\lmslabs::$posttransport = null;
        }
        $this->assertSame('completed', $done['status']);
        $this->assertTrue($done['openscene']);
        $this->assertCount(2, $keys);
        $this->assertSame($keys[0], $keys[1], 'Checking again sends the same key and body.');
        $this->assertSame(1, $DB->count_records('aisoftskills_scene', ['title' => 'A clash']));
        $dismissed = $this->call('editingteacher', 'dismiss_request', [(int)$this->cm->id, $first['id']]);
        $this->assertSame('completed', $dismissed['status'], 'A completed request is simply no longer listed.');
    }
}
