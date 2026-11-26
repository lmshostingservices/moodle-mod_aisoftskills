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

use mod_aisoftskills\local\ai\lmslabs;
use mod_aisoftskills\local\ai\requests;
use mod_aisoftskills\local\byok\engine;
use mod_aisoftskills\local\byok\prompts;
use mod_aisoftskills\local\voiceover;

/**
 * Tests Bring your own AI: scenes, pictures and voiceover on the site's own AI, with the service's checks, at no charge.
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_aisoftskills\byok
 * @covers     \mod_aisoftskills\local\byok\engine
 * @covers     \mod_aisoftskills\local\ai\lmslabs
 * @covers     \mod_aisoftskills\local\ai\requests
 */
#[\PHPUnit\Framework\Attributes\CoversClass(byok::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(engine::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(lmslabs::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(requests::class)]
final class byok_test extends \advanced_testcase {
    /** @var array Requests the mocked providers received: [url, body]. */
    private array $requests = [];

    /** @var array Tool answers the mocked Anthropic gives, in order. */
    private array $answers = [];

    /** @var \stdClass */
    private $instance;

    /** @var \stdClass */
    private $scene;

    /**
     * Licence the site for Bring your own AI on a test key: text and vision on Anthropic, pictures and voice on OpenAI,
     * and no LMS Labs credentials at all.
     */
    protected function setUp(): void {
        parent::setUp();
        if (!class_exists('\local_aiconfig\byok\ai')) {
            $this->markTestSkipped('AI Grader Central Config with Bring your own AI is not installed.');
        }
        $this->resetAfterTest();
        $pair = sodium_crypto_sign_keypair();
        set_config('testpublickey', base64_encode(sodium_crypto_sign_publickey($pair)), 'local_aiconfig');
        $payload = json_encode(['v' => 1, 'id' => 'lic_t', 'site' => \local_aiconfig\byok\licence::site_host(),
            'plan' => 'pro', 'byok' => true, 'products' => ['*'], 'sub' => 'sub_t', 'exp' => time() + DAYSECS]);
        $b64 = fn(string $s): string => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        set_config('licencekey', $b64($payload) . '.' .
            $b64(sodium_crypto_sign_detached($payload, sodium_crypto_sign_secretkey($pair))), 'local_aiconfig');
        $vendors = ['text' => 'anthropic', 'vision' => 'anthropic', 'image' => 'openai', 'voice' => 'openai'];
        foreach ($vendors as $service => $vendor) {
            set_config('src_' . $service, 'ownkey', 'local_aiconfig');
            set_config('vendor_' . $service, $vendor, 'local_aiconfig');
        }
        set_config('key_anthropic', 'sk-test', 'local_aiconfig');
        set_config('key_openai', 'sk-test', 'local_aiconfig');
        foreach (['siteid', 'apikey'] as $name) {
            set_config($name, '', 'local_aiconfig');
            set_config('lmslabs' . $name, '', 'mod_aisoftskills');
        }
        foreach (['aidrafts', 'aiimages', 'aivoice'] as $name) {
            set_config($name, 1, 'mod_aisoftskills');
        }
        $this->requests = [];
        $this->answers = [];
        $GLOBALS['local_aiconfig_byok_mock'] = function ($url, $headers, $body, $vendor, $raw = false) {
            $this->requests[] = [$url, $body];
            if (strpos($url, '/audio/speech') !== false) {
                return "\xff\xf3\x84" . str_repeat("\0", 381);
            }
            if (strpos($url, '/images/') !== false) {
                $im = imagecreatetruecolor(1536, 1024);
                ob_start();
                imagepng($im);
                return ['data' => [['b64_json' => base64_encode((string)ob_get_clean())]]];
            }
            $answer = array_shift($this->answers);
            if ($answer === null) {
                throw new \moodle_exception('byok_callfailed', 'local_aiconfig', '', (object)['vendor' => $vendor,
                    'detail' => 'Overloaded']);
            }
            return ['content' => [['type' => 'tool_use', 'id' => 't', 'name' => $body['tools'][0]['name'],
                'input' => $answer]], 'stop_reason' => 'tool_use'];
        };
        // LMS Labs must never be called.
        $never = function ($url) {
            throw new \coding_exception('LMS Labs was called: ' . $url);
        };
        lmslabs::$transport = $never;
        lmslabs::$posttransport = $never;
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $ss = $gen->get_plugin_generator('mod_aisoftskills');
        $this->instance = $ss->create_instance(['course' => $course->id]);
        $this->scene = $ss->create_scene($this->instance, 'Roster', 'Better', 'Poorer', [
            'context' => 'You ask bartender Leo to stay. He hesitates.', 'question' => 'What do you say?']);
        $this->setUser($teacher);
    }

    /**
     * Clean up the mocks.
     */
    protected function tearDown(): void {
        unset($GLOBALS['local_aiconfig_byok_mock']);
        lmslabs::$transport = null;
        lmslabs::$posttransport = null;
        engine::$text = engine::$image = engine::$vision = engine::$speech = null;
        byok::$lasterror = null;
        byok::$answered = false;
        parent::tearDown();
    }

    /**
     * A complete scene as the AI writes it.
     *
     * @param int $worst the very poor response's KPI change
     * @return array
     */
    private function draft(int $worst = -30): array {
        return ['title' => 'Handover', 'setting' => 'A busy ward at shift change.', 'characters' => ['Fatima', 'Dr Reeves'],
            'dialogue' => [['speaker' => 'Fatima', 'line' => 'I have not finished the notes.'],
                ['speaker' => 'Dr Reeves', 'line' => 'We need them now.']],
            'teachingNote' => 'Calm, specific requests keep trust.', 'speaker' => 'You are the shift lead.',
            'question' => 'What do you say?', 'options' => [
                ['text' => 'Fatima, let us finish them together now.', 'best' => true, 'worst' => false, 'kpi' => 'trust',
                    'kpiDelta' => 20, 'consequence' => 'Fatima relaxes.', 'reason' => 'Support builds trust.'],
                ['text' => 'Fatima, just do it later.', 'best' => false, 'worst' => false, 'kpi' => 'quality',
                    'kpiDelta' => -10, 'consequence' => 'Notes stay incomplete.', 'reason' => 'Delay risks errors.'],
                ['text' => 'Fatima, this is your fault.', 'best' => false, 'worst' => true, 'kpi' => 'morale',
                    'kpiDelta' => $worst, 'consequence' => 'Fatima shuts down.', 'reason' => 'Blame hurts morale.'],
            ]];
    }

    /**
     * Scene writing, pictures and voiceover are offered with no LMS Labs connection, and nothing shows a price.
     */
    public function test_gates(): void {
        $provider = new lmslabs();
        $this->assertFalse($provider->is_connected());
        $this->assertTrue($provider->can_draft());
        $this->assertTrue($provider->can_generate());
        $this->assertTrue(voiceover::enabled());
        $this->assertNull(voiceover::price_hold());
        $this->assertNull(voiceover::remakes());
        $this->assertTrue(lmslabs::image_anchors());
        $this->assertTrue(local\unlock::active());
        $this->assertSame(0, aicoursebuilder\generator::native_estimate([]));
        set_config('src_text', 'lmslabs', 'local_aiconfig');
        $this->assertFalse($provider->can_draft());
    }

    /**
     * A scene is written on the site's own AI with the service's instructions and schema, at no charge.
     */
    public function test_scene(): void {
        global $DB;
        $this->answers = [$this->draft()];
        $row = requests::start_scene($this->instance, 0, 'A handover goes wrong.', 'Nurses', 'Hospital');
        $this->assertSame('completed', $row->status);
        $this->assertSame(0, (int)$row->charged);
        $this->assertCount(1, $this->requests);
        [$url, $sent] = $this->requests[0];
        $this->assertStringEndsWith('/v1/messages', $url);
        $this->assertStringStartsWith(prompts::SCENE . "\n\n" . prompts::SPOKEN, $sent['system']);
        $this->assertSame('scene_draft', $sent['tools'][0]['name']);
        $this->assertSame(2500, $sent['max_tokens']);
        $input = '{"brief":"A handover goes wrong.","audience":"Nurses","context":"Hospital"}';
        $this->assertSame($input, $sent['messages'][0]['content'][0]['text']);
        $scene = $DB->get_record('aisoftskills_scene', ['id' => $row->targetid], '*', MUST_EXIST);
        $this->assertSame('What do you say?', $scene->question);
        $options = $DB->get_records('aisoftskills_option', ['sceneid' => $scene->id], 'sortorder');
        $this->assertSame(['20', '-10', '-30'], array_values(array_column($options, 'kpidelta')));
    }

    /**
     * A scene that breaks the KPI rules is sent back once, with zod's list of problems, as the service does.
     */
    public function test_scene_repair(): void {
        $this->answers = [$this->draft(-12), $this->draft()];
        $row = requests::start_scene($this->instance, 0, 'A handover goes wrong.', '', '');
        $this->assertSame('completed', $row->status);
        $this->assertCount(2, $this->requests);
        $user = $this->requests[1][1]['messages'][0]['content'][0]['text'];
        $this->assertStringStartsWith('{"brief":"A handover goes wrong."}' . "\n\n" .
            engine::REPAIR_LABEL . "\n{\"title\":\"Handover\"", $user);
        $this->assertStringContainsString('Validation errors: [{"path":[],"code":"custom","message":"Require best +5..50, ' .
            'poorer -5..-25, worst -50..-10 and at least double the poorer drop"}]. Preserve the teaching intent.', $user);
    }

    /**
     * A scene that is still wrong after the repair, or an AI that fails, is refused: nothing is charged or created.
     */
    public function test_scene_refused(): void {
        global $DB;
        $this->answers = [$this->draft(-12), $this->draft(-12)];
        $row = requests::start_scene($this->instance, 0, 'A brief', '', '');
        $this->assertSame('failed', $row->status);
        $this->assertSame('byok_failed', $row->errorcode);
        $context = \context_module::instance($this->instance->cmid);
        $expected = get_string('byok_unusable', 'mod_aisoftskills');
        $this->assertSame($expected, requests::export($row, $context)['message']);
        $this->assertSame(1, $DB->count_records('aisoftskills_scene', ['aisoftskillsid' => $this->instance->id]));

        $row = requests::start_scene($this->instance, 0, 'A brief', '', '');
        $this->assertSame('failed', $row->status);
        $this->assertStringContainsString('Overloaded', requests::export($row, $context)['message']);

        [$status, , $details] = engine::scene('{"brief":"x","extra":1}');
        $this->assertSame(422, $status);
        $this->assertNull($details);
    }

    /**
     * The service's own problems list for wrong answers: zod's codes, paths, words and order.
     */
    public function test_scene_checks(): void {
        $draft = json_decode(json_encode($this->draft()));
        $draft->options[0]->kpiDelta = 5.5;
        $draft->options[1]->kpi = 'Trust';
        $draft->dialogue[1]->speaker = '';
        $draft->extra = true;
        [$scene, $issues] = engine::scene_output($draft);
        $this->assertNull($scene);
        $this->assertSame('[{"path":["dialogue",1,"speaker"],"code":"too_small","message":"String must contain at least 1 ' .
            'character(s)"},{"path":["dialogue",1,"speaker"],"code":"custom","message":"Invalid input"},{"path":["options",' .
            '0,"kpiDelta"],"code":"invalid_type","message":"Expected integer, received float"},{"path":["options",1,"kpi"],' .
            '"code":"invalid_enum_value","message":"Invalid enum value. Expected \'morale\' | \'motivation\' | ' .
            '\'productivity\' | \'trust\' | \'wellbeing\' | \'engagement\' | \'teamwork\' | \'customersatisfaction\' | ' .
            '\'quality\' | \'safety\', received \'Trust\'"},{"path":[],"code":"unrecognized_keys","message":"Unrecognized ' .
            'key(s) in object: \'extra\'"}]', local\byok\js::stringify($issues));
    }

    /**
     * Scenes from the teacher's own AI assistant are created at no charge, with no LMS Labs connection.
     */
    public function test_import(): void {
        global $DB;
        $before = $DB->count_records('aisoftskills_scene', ['aisoftskillsid' => $this->instance->id]);
        $draft = ['scenes' => [array_merge($this->draft(), ['options' => array_map(fn($o) => ['text' => $o['text'],
            'best' => $o['best'], 'worst' => $o['worst'], 'kpi' => $o['kpi'], 'kpidelta' => $o['kpiDelta'],
            'consequence' => $o['consequence'], 'reason' => $o['reason']], $this->draft()['options'])])]];
        $row = requests::start_import($this->instance, 0, $draft);
        $this->assertSame('completed', $row->status);
        $this->assertSame($before + 1, $DB->count_records('aisoftskills_scene', ['aisoftskillsid' => $this->instance->id]));
        $this->assertSame([], $this->requests);
    }

    /**
     * A scene picture is drawn on the site's own AI, cut to 1600 × 1000 and saved.
     */
    public function test_picture(): void {
        $row = requests::start_image($this->instance, 0, $this->scene);
        $this->assertSame('completed', $row->status);
        [$url, $sent] = $this->requests[0];
        $this->assertStringContainsString('/images/generations', $url);
        $this->assertStringStartsWith('Create one adult soft-skills educational scene as an illustration.', $sent['prompt']);
        $context = \context_module::instance($this->instance->cmid);
        $info = local\manager::get_scene_file($context, (int)$this->scene->id)->get_imageinfo();
        $this->assertSame([1600, 1000], [$info['width'], $info['height']]);
    }

    /**
     * Voiceover clips are made on the site's own voice: the closest OpenAI voice, MP3, from the own-voice catalogue.
     */
    public function test_voice(): void {
        $this->assertContains('en-AU', voiceover::locales('en'));
        $this->assertContains('cmn-CN', voiceover::locales('zh'));
        $row = requests::start_voice($this->instance, 0, $this->scene, 0);
        $this->assertSame('completed', $row->status);
        [$url, $sent] = $this->requests[0];
        $this->assertStringEndsWith('/audio/speech', $url);
        $this->assertSame('mp3', $sent['response_format']);
        $this->assertContains($sent['voice'], array_values(byok::OPENAI_VOICES));
        $this->assertNotSame('', $sent['input']);
    }
}
