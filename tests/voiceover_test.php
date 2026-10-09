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
use mod_aisoftskills\local\labels;
use mod_aisoftskills\local\manager;
use mod_aisoftskills\local\voiceover;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for voiceover: voices that follow the name labels, what is read, clips and the stored, charged request.
 * No network: the LMS Labs catalogue and speech route are faked.
 *
 * @package    mod_aisoftskills
 * @category   test
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_aisoftskills\local\voiceover
 */
#[CoversClass(voiceover::class)]
final class voiceover_test extends \advanced_testcase {
    /** @var \stdClass */
    protected $instance;
    /** @var \stdClass */
    protected $scene;
    /** @var \context_module */
    protected $context;
    /** @var array Answers of the fake speech route. */
    protected $answers = [];
    /** @var array Bodies sent to the speech route. */
    protected $sent = [];

    /**
     * One English activity with one labelled scene; LMS Labs offers the eight en-AU voices.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $ss = $gen->get_plugin_generator('mod_aisoftskills');
        $this->instance = $ss->create_instance(['course' => $course->id, 'contentlang' => 'en']);
        $this->context = \context_module::instance($this->instance->cmid);
        $this->scene = $ss->create_scene($this->instance, 'Roster', 'Better', 'Poorer', [
            'context' => 'You ask bartender Leo to stay. He hesitates.', 'question' => 'What do you say?']);
        labels::save($this->scene, [
            ['text' => 'You - Shift supervisor', 'x' => 60, 'y' => 50, 'gender' => 'f', 'you' => true],
            ['text' => 'Leo - Bartender', 'x' => 30, 'y' => 50, 'gender' => 'm', 'you' => false],
        ]);
        global $DB;
        $this->scene = $DB->get_record('aisoftskills_scene', ['id' => $this->scene->id]);
        set_config('aivoice', 1, 'mod_aisoftskills');
        set_config('lmslabssiteid', 'My Site', 'mod_aisoftskills');
        set_config('lmslabsapikey', 'secret-key', 'mod_aisoftskills');
        $voices = array_map(fn($t) => ['name' => 'en-AU-Chirp3-HD-' . $t], voiceover::VOICETYPES);
        lmslabs::$transport = fn() => [200, json_encode(['locales' => [
            ['locale' => 'en-AU', 'ttsLanguageCode' => 'en-AU', 'voices' => $voices]]])];
        $this->answers = [];
        $this->sent = [];
        lmslabs::$posttransport = function ($url, $headers, $body) {
            $this->assertStringEndsWith(lmslabs::VOICE_ROUTE, $url);
            $this->assertContains('Accept: audio/mpeg, application/json', $headers);
            $this->sent[] = json_decode($body, true);
            return array_shift($this->answers);
        };
        $this->setUser($teacher);
    }

    /**
     * Resets the transports.
     */
    protected function tearDown(): void {
        lmslabs::$transport = null;
        lmslabs::$posttransport = null;
        parent::tearDown();
    }

    /**
     * The narrator is the setting; the learner and the people sound as their labels say, never like the narrator.
     */
    public function test_config_follows_labels(): void {
        set_config('narratorvoice', 'Kore', 'mod_aisoftskills');
        $config = voiceover::config($this->instance);
        $this->assertSame('en-AU', $config['locale']);
        $this->assertSame('en-AU-Chirp3-HD-Kore', $config['narrator']);
        $this->assertSame('en-AU-Chirp3-HD-Aoede', voiceover::learner_voice($this->scene, $config));
        $this->assertSame(['leo' => 'en-AU-Chirp3-HD-Puck'], $config['people']);
        // A male narrator: a male learner never gets the narrator's voice.
        set_config('narratorvoice', 'Charon', 'mod_aisoftskills');
        $config = voiceover::config($this->instance);
        $this->assertSame('en-AU-Chirp3-HD-Puck', $config['learner']['m']);
        $this->assertNotContains('en-AU-Chirp3-HD-Charon', $config['people']);
        $this->assertNotContains('en-AU-Chirp3-HD-Puck', $config['people']);
    }

    /**
     * A person keeps their voice when someone new appears earlier in the activity, so made clips stay valid.
     */
    public function test_voices_are_kept(): void {
        global $DB;
        set_config('narratorvoice', 'Kore', 'mod_aisoftskills');
        $this->assertSame('en-AU-Chirp3-HD-Puck', voiceover::config($this->instance)['people']['leo']);
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_aisoftskills');
        $first = $gen->create_scene($this->instance, 'Earlier');
        labels::save($first, [['text' => 'Sam - Chef', 'x' => 50, 'y' => 50, 'gender' => 'm', 'you' => false]]);
        manager::move_scene($DB->get_record('aisoftskills_scene', ['id' => $first->id]), -1);
        $instance = $DB->get_record('aisoftskills', ['id' => $this->instance->id]);
        $config = voiceover::config($instance);
        $this->assertSame('en-AU-Chirp3-HD-Puck', $config['people']['leo']);
        $this->assertSame('en-AU-Chirp3-HD-Fenrir', $config['people']['sam']);
        // A learner's page reads the kept voices and never writes.
        $DB->set_field('aisoftskills', 'voicemap', null, ['id' => $instance->id]);
        $instance = $DB->get_record('aisoftskills', ['id' => $this->instance->id]);
        voiceover::learner_config($instance);
        $this->assertNull($DB->get_field('aisoftskills', 'voicemap', ['id' => $instance->id]));
    }

    /**
     * A clip that has been made is never bought again.
     */
    public function test_made_clip_not_bought_again(): void {
        global $USER;
        $this->answers = [[200, ['content-type' => 'audio/mpeg'], "ID3\x03\x00" . str_repeat("\x00", 64)]];
        requests::start_voice($this->instance, (int)$USER->id, $this->scene, 0);
        try {
            requests::start_voice($this->instance, (int)$USER->id, $this->scene, 0);
            $this->fail('The same clip was bought twice');
        } catch (\moodle_exception $e) {
            $this->assertSame('voice_alreadymade', $e->errorcode);
        }
        $this->assertCount(1, $this->sent);
    }

    /**
     * What is read follows the activity's choice; responses are in the learner's voice, feedback by the narrator.
     */
    public function test_segments_and_parts(): void {
        $config = voiceover::config($this->instance);
        $options = manager::get_options([$this->scene->id])[$this->scene->id];
        $all = voiceover::segments($this->scene, $config, $options);
        $this->assertSame(
            ['context', 'question', 'option', 'consequence', 'reason', 'option', 'consequence', 'reason'],
            array_column($all, 'part')
        );
        $this->assertSame('en-AU-Chirp3-HD-Aoede', $all[2]['voice']);
        $this->assertSame($config['narrator'], $all[3]['voice']);
        $this->assertSame(range(0, 7), array_column($all, 'index'));
        $some = voiceover::segments($this->scene, $config, $options, ['question', 'responses']);
        $this->assertSame(['question', 'option', 'option'], array_column($some, 'part'));
    }

    /**
     * Clips are at most 200 characters, split at sentence ends.
     */
    public function test_chunks(): void {
        $this->assertSame([], voiceover::chunks('  '));
        $long = str_repeat('This is a sentence of words. ', 12);
        $chunks = voiceover::chunks($long);
        $this->assertGreaterThan(1, count($chunks));
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(voiceover::MAX_CHARS, \core_text::strlen($chunk));
            $this->assertStringEndsWith('.', $chunk);
        }
        $this->assertSame(trim(preg_replace('/\s+/', ' ', $long)), implode(' ', $chunks));
    }

    /**
     * A delivered clip is saved and reused; a part plays only once all of its clips exist; nothing is charged twice.
     */
    public function test_request_flow(): void {
        global $DB, $USER;
        $mp3 = "ID3\x03\x00" . str_repeat("\x00", 64);
        $this->answers = [[200, ['content-type' => 'audio/mpeg', 'x-request-id' => 'v1'], $mp3]];
        $row = requests::start_voice($this->instance, (int)$USER->id, $this->scene, 0);
        $this->assertSame('completed', $row->status);
        $this->assertSame(lmslabs::VOICE_CREDITS, (int)$row->charged);
        $this->assertSame(['text', 'locale', 'speed', 'voice'], array_keys($this->sent[0]));
        $this->assertSame('normal', $this->sent[0]['speed']);
        $this->assertCount(7, voiceover::missing($this->instance, $this->context));
        $options = manager::get_options([$this->scene->id])[$this->scene->id];
        // The question has no clip yet, so the scene stays silent.
        $this->assertSame([], voiceover::playlist($this->instance, $this->context, $this->scene, $options)['scene']);
        $this->answers = [[200, ['content-type' => 'audio/mpeg'], $mp3]];
        requests::start_voice($this->instance, (int)$USER->id, $this->scene, 1);
        $playlist = voiceover::playlist($this->instance, $this->context, $this->scene, $options);
        $this->assertSame(['context', 'question'], array_column($playlist['scene'], 'part'));
        // Not audio: lost, nothing saved.
        $this->answers = [[200, ['content-type' => 'application/json'], '{"ok":true}']];
        $row = requests::start_voice($this->instance, (int)$USER->id, $this->scene, 2);
        $this->assertSame('lost', $row->status);
        $this->assertSame('unusable_audio', $row->errorcode);
        // The route is not live: nothing charged, a clear message.
        $this->answers = [[404, ['content-type' => 'application/json'], '{"error":{"code":"NOT_FOUND"}}']];
        $row = requests::start_voice($this->instance, (int)$USER->id, $this->scene, 3);
        $this->assertSame('failed', $row->status);
        $this->assertStringContainsString('not available yet', requests::export($row, $this->context)['message']);
        $this->assertSame(2, $DB->count_records_select('files', "component = 'mod_aisoftskills' AND filearea = 'voiceover'
            AND itemid = :id AND filename <> '.'", ['id' => $this->scene->id]));
    }

    /**
     * LMS Labs answers that say "not made" end the request; unknown outcomes keep the key for "Check again".
     */
    public function test_error_codes(): void {
        global $DB, $USER;
        $json = ['content-type' => 'application/json'];
        $cases = [
            [503, 'VOICEOVER_NOT_ENABLED', 'failed', 'not switched on'],
            [503, 'TARIFF_NOT_APPROVED', 'failed', 'not switched on'],
            [503, 'PROVIDER_NOT_CONFIGURED', 'failed', 'configuration problem'],
            [502, 'UNUSABLE_AUDIO', 'failed', 'not been charged'],
            [503, 'SETTLEMENT_UNCONFIRMED', 'uncertain', ''],
            [503, 'TTS_UNAVAILABLE', 'uncertain', ''],
            [500, '', 'uncertain', ''],
            [410, 'GONE', 'lost', 'contact LMS Labs support before'],
        ];
        foreach ($cases as $i => [$status, $code, $want, $text]) {
            // An unresolved request blocks the next one for the scene: clear it, as "Dismiss" would.
            $DB->delete_records_select('aisoftskills_aireq', "status IN ('uncertain', 'lost')");
            $body = json_encode(['requestId' => 'r' . $i, 'error' => ['code' => $code, 'message' => 'x']]);
            $this->answers = [[$status, $json, $body]];
            $row = requests::start_voice($this->instance, (int)$USER->id, $this->scene, $i);
            $this->assertSame($want, $row->status, $code);
            if ($text !== '') {
                $this->assertStringContainsString($text, requests::export($row, $this->context)['message'], $code);
            }
        }
    }

    /**
     * Voiceover off: no request can be made.
     */
    public function test_off(): void {
        global $USER;
        set_config('aivoice', 0, 'mod_aisoftskills');
        $this->expectException(\moodle_exception::class);
        requests::start_voice($this->instance, (int)$USER->id, $this->scene, 0);
    }

    /**
     * A learner's page never asks LMS Labs: it uses the catalogue kept from the last fetch.
     */
    public function test_learner_config_uses_kept_catalogue(): void {
        voiceover::config($this->instance);
        \cache::make('mod_aisoftskills', 'voicecatalog')->purge();
        lmslabs::$transport = function () {
            $this->fail('A learner page asked LMS Labs.');
        };
        $this->assertSame('en-AU', voiceover::learner_config($this->instance)['locale']);
    }
}
