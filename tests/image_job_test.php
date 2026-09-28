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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.

namespace mod_aisoftskills;

use mod_aisoftskills\local\ai\image_job;
use mod_aisoftskills\local\ai\lmslabs;
use mod_aisoftskills\local\credentials;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Image request persistence/recovery, mocked transport and real Moodle database only; no paid calls.
 *
 * @package    mod_aisoftskills
 * @category   test
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_aisoftskills\local\ai\image_job
 */
#[CoversClass(image_job::class)]
final class image_job_test extends \advanced_testcase {
    /** Clear test transport after each test. */
    protected function tearDown(): void {
        lmslabs::$posttransport = null;
        credentials::$central = null;
        parent::tearDown();
    }

    /** @return array Activity, scene, teacher id. */
    private function fixture(): array {
        $this->resetAfterTest();
        credentials::$central = fn() => null;
        set_config('aiimages', 1, 'mod_aisoftskills');
        set_config('lmslabssiteid', 'site-a', 'mod_aisoftskills');
        set_config('lmslabsapikey', 'secret', 'mod_aisoftskills');
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $user = $gen->create_and_enrol($course, 'editingteacher');
        $module = $gen->get_plugin_generator('mod_aisoftskills');
        $instance = $module->create_instance(['course' => $course->id]);
        $scene = $module->create_scene($instance, 'Calm discussion');
        return [$instance, $scene, (int)$user->id];
    }

    /**
     * Same form and 202/429/5xx recovery reuse exact stored key/body, even after the scene changes.
     * @covers \mod_aisoftskills\local\ai\image_job
     */
    public function test_pending_recovery_and_410_without_image_replay(): void {
        global $DB;
        [$instance, $scene, $userid] = $this->fixture();
        $intent = \core\uuid::generate();
        $job = image_job::begin($instance, $scene, $userid, $intent);
        $this->assertSame($job->id, image_job::begin($instance, $scene, $userid, $intent)->id);
        $original = $job->requestbody;
        $DB->set_field('aisoftskills_scene', 'imageprompt', 'A DIFFERENT PROMPT', ['id' => $scene->id]);
        $seen = [];
        $responses = [
            [202, 'PENDING'], [429, 'RATE_LIMITED'], [503, 'SETTLEMENT_UNCONFIRMED'],
            [410, 'RESULT_NOT_RETAINED'],
        ];
        lmslabs::$posttransport = function ($url, $headers, $body) use (&$seen, &$responses) {
            $seen[] = [$url, $headers, $body];
            [$status, $code] = array_shift($responses);
            return [$status, ['content-type' => 'application/json', 'x-request-id' => 'ref-1'],
                json_encode(['error' => $code, 'requestId' => 'ref-1'])];
        };
        for ($i = 0; $i < 4; $i++) {
            try {
                image_job::send($job, new lmslabs());
                $this->fail('Image request returned bytes from an error envelope');
            } catch (\moodle_exception $e) {
                $this->assertSame($i === 3 ? 'aierror_result_not_retained' :
                    'aierror_' . strtolower(['PENDING', 'RATE_LIMITED', 'SETTLEMENT_UNCONFIRMED'][$i]), $e->errorcode);
            }
            $job = $DB->get_record('aisoftskills_imagejob', ['id' => $job->id]);
            $this->assertSame($i === 3 ? 'lost' : 'pending', $job->state);
            $this->assertSame('ref-1', $job->requestid);
            if ($i < 3) {
                $this->assertSame($job->id, image_job::begin($instance, $scene, $userid,
                    \core\uuid::generate())->id, 'A new nonce cannot replace a pending key.');
            }
        }
        $this->assertCount(4, $seen);
        $keys = [];
        foreach ($seen as [$url, $headers, $body]) {
            $this->assertSame('https://lms-labs.com/api/moodle/ai-softskills/images', $url);
            $this->assertSame($original, $body, 'Changed scene descriptions must not change a retry body.');
            $this->assertContains('X-Site-ID: site-a', $headers);
            $this->assertContains('X-API-Key: secret', $headers);
            $keys[] = current(preg_grep('/^Idempotency-Key: /', $headers));
            $this->assertStringNotContainsString('secret', $url . $body);
        }
        $this->assertCount(1, array_unique($keys));
        $this->assertNull(image_job::send($job, new lmslabs()), '410 cannot replay image bytes.');
        $this->assertCount(4, $seen);
        $another = image_job::begin($instance, $scene, $userid, \core\uuid::generate());
        $this->assertNotSame($job->requestkey, $another->requestkey,
            'After explicit 410, a new intentional operation can have a new key.');
    }

    /**
     * A 200 PNG stores a backend reference before Moodle file saving; edits/replays do not charge.
     * @covers \mod_aisoftskills\local\ai\image_job
     */
    public function test_success_and_moodle_save_loss(): void {
        global $DB;
        [$instance, $scene, $userid] = $this->fixture();
        $job = image_job::begin($instance, $scene, $userid, \core\uuid::generate());
        $png = file_get_contents(__DIR__ . '/fixtures/scene.png');
        $calls = 0;
        lmslabs::$posttransport = function () use ($png, &$calls) {
            $calls++;
            return [200, ['content-type' => 'image/png', 'x-request-id' => 'ref-2',
                'x-credits-charged' => '5', 'x-credits-balance' => '15'], $png];
        };
        $result = image_job::send($job, new lmslabs());
        $this->assertSame($png, $result['bytes']);
        $this->assertSame(5, $result['charged']);
        $saved = $DB->get_record('aisoftskills_imagejob', ['id' => $job->id]);
        $this->assertSame('saving', $saved->state);
        $this->assertSame('ref-2', $saved->requestid);
        image_job::finish($job, false);
        $this->assertNull(image_job::send($job, new lmslabs()), 'Charged PNG is NOT cached for replay.');
        $this->assertSame(1, $calls);
        $this->assertSame('lost', $DB->get_field('aisoftskills_imagejob', 'state', ['id' => $job->id]));
    }

    /**
     * Site identity is never switched during manual recovery; privacy deletion clears key and prompt.
     * @covers \mod_aisoftskills\local\ai\image_job
     */
    public function test_site_change_and_privacy(): void {
        global $DB;
        [$instance, $scene, $userid] = $this->fixture();
        $job = image_job::begin($instance, $scene, $userid, \core\uuid::generate());
        set_config('lmslabssiteid', 'site-b', 'mod_aisoftskills');
        try {
            image_job::send($job, new lmslabs());
            $this->fail('Changed billing site');
        } catch (\moodle_exception $e) {
            $this->assertSame('imageintent_sitechanged', $e->errorcode);
        }
        \mod_aisoftskills\local\learning::delete_user_data((int)$instance->id, $userid);
        $this->assertSame(0, $DB->count_records('aisoftskills_imagejob', ['userid' => $userid]));
    }
}