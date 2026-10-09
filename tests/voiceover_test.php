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
            ['locale' => 'en-AU', 'ttsLanguageCode' => 'en-AU', 'voices' => $voices]], 'tariff' => ['tts' => 2]])];
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
     * A voice the teacher chose on a label wins: the person gets it, the learner's default moves away from it, and the
     * editor is told which voice each label really has.
     */
    public function test_chosen_voices(): void {
        global $DB;
        set_config('narratorvoice', 'Kore', 'mod_aisoftskills');
        labels::save($this->scene, [
            ['text' => 'You - Shift supervisor', 'x' => 60, 'y' => 50, 'gender' => 'f', 'you' => true],
            ['text' => 'Leo - Bartender', 'x' => 30, 'y' => 50, 'gender' => 'm', 'voice' => 'Orus', 'you' => false],
            ['text' => 'Priya - Nurse', 'x' => 80, 'y' => 50, 'voice' => 'Aoede', 'you' => false],
            ['text' => 'Ana', 'x' => 10, 'y' => 50, 'gender' => 'f', 'you' => false],
        ]);
        $scene = $DB->get_record('aisoftskills_scene', ['id' => $this->scene->id]);
        $config = voiceover::config($this->instance);
        $this->assertSame('en-AU-Chirp3-HD-Orus', $config['people']['leo']);
        $this->assertSame('en-AU-Chirp3-HD-Aoede', $config['people']['priya']);
        // Aoede was the learner's female default; it is taken, so the learner gets the next female voice.
        $this->assertSame('en-AU-Chirp3-HD-Leda', voiceover::learner_voice($scene, $config));
        $voices = voiceover::label_voices($scene, $config);
        $this->assertSame(['Leda', 'Orus', 'Aoede'], array_slice($voices, 0, 3));
        $this->assertNotContains($voices[3], ['Leda', 'Aoede', 'Kore', ''], 'Ana gets a voice nobody else has.');
        // The learner can be given a voice too; the narrator's voice can never be chosen.
        labels::save($scene, [['text' => 'You', 'x' => 50, 'y' => 50, 'voice' => 'Zephyr', 'you' => true],
            ['text' => 'Leo', 'x' => 30, 'y' => 50, 'voice' => 'Kore', 'you' => false]]);
        $scene = $DB->get_record('aisoftskills_scene', ['id' => $this->scene->id]);
        $instance = $DB->get_record('aisoftskills', ['id' => $this->instance->id]);
        $config = voiceover::config($instance);
        $this->assertSame('en-AU-Chirp3-HD-Zephyr', voiceover::learner_voice($scene, $config));
        $this->assertNotContains('en-AU-Chirp3-HD-Zephyr', $config['people'], 'Nobody else gets the learner\'s voice.');
        $this->assertNotSame('en-AU-Chirp3-HD-Kore', $config['people']['leo']);
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
        // Each card is read after its heading; each feedback card too. Headings are shared by the activity.
        $this->assertSame(
            ['heading', 'context', 'heading', 'context', 'question', 'option', 'heading', 'consequence', 'heading',
                'reason', 'option', 'heading', 'consequence', 'heading', 'reason'],
            array_column($all, 'part')
        );
        $first = array_slice($all, 0, 4);
        $texts = ['The situation', 'You ask bartender Leo to stay.', 'Good to know', 'He hesitates.'];
        $this->assertSame($texts, array_column($first, 'text'));
        $this->assertSame([0, 0, 1, 1], array_column(array_slice($all, 0, 4), 'line'));
        $this->assertSame(0, $all[0]['item']);
        $this->assertArrayNotHasKey('item', $all[1]);
        $this->assertSame(['What happened', 'Why this works'], [$all[6]['text'], $all[8]['text']]);
        $this->assertSame('Why this falls short', $all[13]['text']);
        $this->assertSame('en-AU-Chirp3-HD-Aoede', $all[5]['voice']);
        $this->assertSame($config['narrator'], $all[6]['voice']);
        $this->assertSame(range(0, 14), array_column($all, 'index'));
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
        // No x-credits-charged header: the charge is not known, and is never assumed.
        $this->assertNull($row->charged);
        $this->assertStringContainsString(
            'did not say what it charged: at most 2 credits',
            requests::export($row, $this->context)['message']
        );
        $this->assertSame(['text', 'locale', 'speed', 'voice'], array_keys($this->sent[0]));
        $this->assertSame('normal', $this->sent[0]['speed']);
        // 15 clips, less the one made, less the second "What happened" (a shared heading is made once).
        $missing = voiceover::missing($this->instance, $this->context);
        $this->assertCount(13, $missing);
        $this->assertNotContains($this->scene->id . ':11', $missing, 'A shared heading is listed once.');
        // The shared heading was saved for the activity (item 0), not for the scene.
        $this->assertSame(1, $DB->count_records_select('files', "component = 'mod_aisoftskills' AND filearea = 'voiceover'
            AND itemid = 0 AND filename <> '.'"));
        $options = manager::get_options([$this->scene->id])[$this->scene->id];
        // The question has no clip yet, so the scene stays silent.
        $this->assertSame([], voiceover::playlist($this->instance, $this->context, $this->scene, $options)['scene']);
        foreach ([1, 2, 3, 4] as $index) {
            $this->answers = [[200, ['content-type' => 'audio/mpeg'], $mp3]];
            requests::start_voice($this->instance, (int)$USER->id, $this->scene, $index);
        }
        $playlist = voiceover::playlist($this->instance, $this->context, $this->scene, $options);
        // A heading lights up the card it introduces.
        $this->assertSame(['context', 'context', 'context', 'context', 'question'], array_column($playlist['scene'], 'part'));
        $this->assertSame([0, 0, 1, 1, -1], array_column($playlist['scene'], 'line'));
        // Not audio: lost, nothing saved.
        $this->answers = [[200, ['content-type' => 'application/json'], '{"ok":true}']];
        $row = requests::start_voice($this->instance, (int)$USER->id, $this->scene, 5);
        $this->assertSame('lost', $row->status);
        $this->assertSame('unusable_audio', $row->errorcode);
        // The route is not live: nothing charged, a clear message.
        $this->answers = [[404, ['content-type' => 'application/json'], '{"error":{"code":"NOT_FOUND"}}']];
        $row = requests::start_voice($this->instance, (int)$USER->id, $this->scene, 7);
        $this->assertSame('failed', $row->status);
        $this->assertStringContainsString('not available yet', requests::export($row, $this->context)['message']);
        $this->assertSame(3, $DB->count_records_select('files', "component = 'mod_aisoftskills' AND filearea = 'voiceover'
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
     * Free remakes: once LMS Labs says it supports them, every clip carries its place (clipRef) and the price the
     * teacher confirmed (maxCredits); the place stays the same when the text changes; a price that went up is refused.
     */
    public function test_free_remakes(): void {
        global $DB, $USER;
        $mp3 = "ID3\x03\x00" . str_repeat("\x00", 64);
        // Not supported yet: the body is as before.
        $this->answers = [[200, ['content-type' => 'audio/mpeg'], $mp3]];
        requests::start_voice($this->instance, (int)$USER->id, $this->scene, 0, 0);
        $this->assertSame(['text', 'locale', 'speed', 'voice'], array_keys($this->sent[0]));
        // The catalogue says LMS Labs takes clipRef and maxCredits.
        $voices = array_map(fn($t) => ['name' => 'en-AU-Chirp3-HD-' . $t], voiceover::VOICETYPES);
        lmslabs::$transport = fn() => [200, json_encode(['locales' => [['locale' => 'en-AU', 'ttsLanguageCode' => 'en-AU',
            'voices' => $voices]], 'tariff' => ['tts' => 2, 'ttsRemake' => 0, 'ttsRemakeLimit' => 10,
            'ttsRemakeWindowDays' => 30, 'clipRefSupported' => true, 'maxCreditsRequired' => true]])];
        \cache::make('mod_aisoftskills', 'voicecatalog')->purge();
        voiceover::catalog();
        $this->assertSame(['limit' => 10, 'days' => 30], voiceover::remakes());
        $this->answers = [[200, ['content-type' => 'audio/mpeg', 'x-credits-charged' => '0'], $mp3]];
        $row = requests::start_voice($this->instance, (int)$USER->id, $this->scene, 1, 0);
        $this->assertSame('completed', $row->status);
        $this->assertSame(0, (int)$row->charged);
        $body = $this->sent[1];
        $this->assertSame(['text', 'locale', 'speed', 'voice', 'clipRef', 'maxCredits'], array_keys($body));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $body['clipRef']);
        $this->assertSame(0, $body['maxCredits']);
        // The same place keeps its reference when its text changes; other places differ.
        $config = voiceover::config($this->instance);
        $options = manager::get_options([$this->scene->id])[$this->scene->id];
        $before = voiceover::segments($this->scene, $config, $options);
        $DB->set_field('aisoftskills_scene', 'question', 'What do you say to Leo now?', ['id' => $this->scene->id]);
        $scene = $DB->get_record('aisoftskills_scene', ['id' => $this->scene->id]);
        $after = voiceover::segments($scene, $config, $options);
        $this->assertNotSame($before[4]['text'], $after[4]['text']);
        $this->assertSame(
            voiceover::clipref($this->instance, (int)$scene->id, $before[4]),
            voiceover::clipref($this->instance, (int)$scene->id, $after[4])
        );
        $this->assertNotSame(
            voiceover::clipref($this->instance, (int)$scene->id, $after[1]),
            voiceover::clipref($this->instance, (int)$scene->id, $after[4])
        );
        // A dialogue line's place follows its stable id: deleting an earlier line does not move it.
        $DB->set_field(
            'aisoftskills_scene',
            'script',
            manager::text_to_script("Leo: First line.\nLeo: Second line."),
            ['id' => $scene->id]
        );
        $scene = $DB->get_record('aisoftskills_scene', ['id' => $scene->id]);
        $line = fn($s) => array_values(array_filter(voiceover::segments($s, $config, $options), fn($g) => $g['part'] === 'line'));
        $second = voiceover::clipref($this->instance, (int)$scene->id, $line($scene)[1]);
        $DB->set_field(
            'aisoftskills_scene',
            'script',
            manager::text_to_script('Leo: Second line.', (string)$scene->script),
            ['id' => $scene->id]
        );
        $scene = $DB->get_record('aisoftskills_scene', ['id' => $scene->id]);
        $this->assertSame($second, voiceover::clipref($this->instance, (int)$scene->id, $line($scene)[0]));
        // A free clip that became paid: refused before anything was made; the teacher is asked again.
        $this->answers = [[409, ['content-type' => 'application/json'],
            json_encode(['requestId' => 'r9', 'error' => ['code' => 'PRICE_CHANGED', 'message' => 'x']])]];
        $row = requests::start_voice($this->instance, (int)$USER->id, $scene, 4, 0);
        $this->assertSame('failed', $row->status);
        $this->assertStringContainsString('no longer free', requests::export($row, $this->context)['message']);
        // Without a confirmed price the ceiling is the full price.
        $this->answers = [[200, ['content-type' => 'audio/mpeg', 'x-credits-charged' => '2'], $mp3]];
        requests::start_voice($this->instance, (int)$USER->id, $scene, 2);
        $this->assertSame(2, end($this->sent)['maxCredits']);
    }

    /**
     * While LMS Labs publishes another price per clip than the approved 2 credits, no new clip is asked for, and the
     * Voiceover step says why; once it publishes 2, clips are made again.
     */
    public function test_price_hold(): void {
        global $USER;
        $this->assertSame(2, lmslabs::VOICE_CREDITS);
        $voices = array_map(fn($t) => ['name' => 'en-AU-Chirp3-HD-' . $t], voiceover::VOICETYPES);
        $catalogue = fn($tts) => fn() => [200, json_encode(['locales' => [['locale' => 'en-AU', 'ttsLanguageCode' => 'en-AU',
            'voices' => $voices]], 'tariff' => ['tts' => $tts]])];
        lmslabs::$transport = $catalogue(5);
        \cache::make('mod_aisoftskills', 'voicecatalog')->purge();
        voiceover::catalog();
        $this->assertSame(5, voiceover::price_hold());
        $this->sent = [];
        try {
            requests::start_voice($this->instance, (int)$USER->id, $this->scene, 0);
            $this->fail('No clip may be made at a price the teacher was not shown.');
        } catch (\moodle_exception $e) {
            $this->assertSame('voice_pricehold', $e->errorcode);
        }
        $this->assertSame([], $this->sent);
        lmslabs::$transport = $catalogue(2);
        \cache::make('mod_aisoftskills', 'voicecatalog')->purge();
        voiceover::catalog();
        $this->assertNull(voiceover::price_hold());
        // A clip asked for before 1.4.9, with no charge reported, is shown with the price of the time (5).
        set_config('voicepricefrom', time(), 'mod_aisoftskills');
        $row = (object)['id' => 0, 'operation' => requests::VOICE, 'status' => 'completed', 'charged' => null,
            'balance' => null, 'requestid' => 'r-old', 'timecreated' => time() - 60, 'timemodified' => time() - 60,
            'targetid' => (int)$this->scene->id, 'retryafter' => null, 'error' => null,
            'body' => json_encode(['text' => 'x', 'locale' => 'en-AU', 'speed' => 'normal', 'voice' => 'v'])];
        $this->assertStringContainsString('at most 5 credits', requests::export($row, $this->context)['message']);
        $row->timecreated = time() + 1;
        $this->assertStringContainsString('at most 2 credits', requests::export($row, $this->context)['message']);
        // No published price: the price is unknown, so nothing new is made either (a clip sent without a ceiling to an
        // older LMS Labs could cost more than the teacher was shown).
        lmslabs::$transport = fn() => [200, json_encode(['locales' => [['locale' => 'en-AU', 'ttsLanguageCode' => 'en-AU',
            'voices' => $voices]], 'tariff' => []])];
        \cache::make('mod_aisoftskills', 'voicecatalog')->purge();
        voiceover::catalog();
        $this->assertSame(0, voiceover::price_hold());
        try {
            requests::start_voice($this->instance, (int)$USER->id, $this->scene, 0);
            $this->fail('No clip may be made while its price is unknown.');
        } catch (\moodle_exception $e) {
            $this->assertSame('voice_priceunknown', $e->errorcode);
        }
        $this->assertSame([], $this->sent);
    }

    /**
     * Prices come from the free quote route; no price means the full price.
     */
    public function test_quote(): void {
        $voices = array_map(fn($t) => ['name' => 'en-AU-Chirp3-HD-' . $t], voiceover::VOICETYPES);
        lmslabs::$transport = fn() => [200, json_encode(['locales' => [['locale' => 'en-AU', 'ttsLanguageCode' => 'en-AU',
            'voices' => $voices]], 'tariff' => ['tts' => 2, 'clipRefSupported' => true, 'maxCreditsRequired' => true]])];
        \cache::make('mod_aisoftskills', 'voicecatalog')->purge();
        voiceover::catalog();
        $quotes = [];
        lmslabs::$posttransport = function ($url, $headers, $body) use (&$quotes) {
            $this->assertStringEndsWith(lmslabs::VOICE_QUOTE_ROUTE, $url);
            $quotes[] = json_decode($body, true);
            return count($quotes) === 1
                ? [200, ['content-type' => 'application/json'], json_encode(['requestId' => 'q', 'credits' => 0,
                    'firstClip' => false, 'freeRemakesRemaining' => 7, 'windowDays' => 30, 'freeRemakeLimit' => 10])]
                : [503, ['content-type' => 'application/json'], '{}'];
        };
        $this->assertSame(0, requests::quote_voice($this->instance, $this->scene, 0));
        $this->assertSame(2, requests::quote_voice($this->instance, $this->scene, 1));
        $this->assertSame(['clipRef'], array_keys($quotes[0]));
        set_config('voiceremakes', '', 'mod_aisoftskills');
        $this->assertSame(2, requests::quote_voice($this->instance, $this->scene, 0));
        $this->assertCount(2, $quotes, 'No quote is asked for while LMS Labs does not support remakes.');
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
