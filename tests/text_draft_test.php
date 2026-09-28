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

use mod_aisoftskills\local\ai\text_draft;
use mod_aisoftskills\local\credentials;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Local Moodle persistence and test-only transport; no live or paid calls.
 *
 * @package    mod_aisoftskills
 * @category   test
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_aisoftskills\local\ai\text_draft
 */
#[CoversClass(text_draft::class)]
final class text_draft_test extends \advanced_testcase {
    /** @var string Test response id. */
    private const REQUESTID = '01234567-89ab-4cde-8fab-0123456789ab';

    /** Reset static test hooks. */
    protected function tearDown(): void {
        text_draft::$transport = null;
        credentials::$central = null;
        parent::tearDown();
    }

    /** @return array Activity and teacher ids. */
    private function fixture(): array {
        $this->resetAfterTest();
        credentials::$central = fn() => null;
        set_config('lmslabssiteid', 'site-a', 'mod_aisoftskills');
        set_config('lmslabsapikey', 'secret', 'mod_aisoftskills');
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $user = $gen->create_and_enrol($course, 'editingteacher');
        $activity = $gen->get_plugin_generator('mod_aisoftskills')->create_instance(['course' => $course->id]);
        return [(int)$activity->id, (int)$user->id];
    }

    /**
     * Pending and 429 preserve the original PHP-only billing key and exact body; completion saves both copies.
     * @covers \mod_aisoftskills\local\ai\text_draft
     */
    public function test_draft_pending_replay_and_edit(): void {
        global $DB;
        [$aid, $userid] = $this->fixture();
        $intent = \core\uuid::generate();
        $row = text_draft::create($aid, $userid, $intent, 'Colleagues resolve a scheduling conflict.');
        $this->assertSame($row->id, text_draft::create($aid, $userid, $intent,
            'Colleagues resolve a scheduling conflict.')->id);
        $seen = [];
        $status = 202;
        $payload = ['requestId' => self::REQUESTID, 'error' => ['code' => 'PENDING', 'message' => 'PENDING']];
        text_draft::$transport = function ($url, $headers, $body) use (&$seen, &$status, &$payload) {
            $seen[] = [$url, $headers, $body];
            return [$status, json_encode($payload)];
        };
        $row = text_draft::send($row);
        $this->assertSame('pending', $row->state);
        $status = 429;
        $payload['error']['code'] = 'RATE_LIMITED';
        $row = text_draft::send($DB->get_record('aisoftskills_draft', ['id' => $row->id]));
        $this->assertSame('pending', $row->state);
        $status = 503;
        $payload['error']['code'] = 'SETTLEMENT_UNCONFIRMED';
        $row = text_draft::send($row);
        $this->assertSame('pending', $row->state);
        $status = 200;
        $payload = [
            'requestId' => self::REQUESTID,
            'model' => 'gpt-4o-2024-08-06',
            'creditsCharged' => 3,
            'creditsBalance' => 97,
            'draft' => [
                'title' => 'Scheduling conflict', 'setting' => 'Office',
                'characters' => ['Pat', 'Alex'],
                'dialogue' => [['speaker' => 'Pat', 'line' => 'Can we talk?'],
                    ['speaker' => 'Alex', 'line' => 'Yes.']],
                'teachingNote' => 'Listen to both sides.',
            ],
        ];
        $row = text_draft::send($row);
        $this->assertSame('complete', $row->state);
        $this->assertSame('', $row->requestbody);
        $this->assertSame($payload['draft'], json_decode($row->responsebody, true));
        $this->assertStringContainsString('Pat: Can we talk?', $row->editedbody);
        $this->assertCount(4, $seen);
        $keys = [];
        foreach ($seen as [$url, $headers, $body]) {
            $this->assertSame('https://lms-labs.com/api/moodle/ai-softskills/scenes/draft', $url);
            $this->assertSame(['brief' => 'Colleagues resolve a scheduling conflict.'], json_decode($body, true));
            $this->assertStringNotContainsString('secret', $url . $body);
            $this->assertContains('X-Site-ID: site-a', $headers);
            $this->assertContains('X-API-Key: secret', $headers);
            $keys[] = current(preg_grep('/^Idempotency-Key: /', $headers));
        }
        $this->assertCount(1, array_unique($keys));
        text_draft::save_edit($row, 'Teacher revised script.');
        $this->assertSame('Teacher revised script.', $DB->get_field('aisoftskills_draft', 'editedbody', ['id' => $row->id]));
        text_draft::send($DB->get_record('aisoftskills_draft', ['id' => $row->id]));
        $this->assertCount(4, $seen, 'A completed operation never invokes the provider again.');
    }

    /**
     * Invalid input is rejected before the transport; site identity and expired keys fail closed.
     * @covers \mod_aisoftskills\local\ai\text_draft
     */
    public function test_validation_site_change_and_expiry(): void {
        global $DB;
        [$aid, $userid] = $this->fixture();
        try {
            text_draft::create($aid, $userid, \core\uuid::generate(), '<learner>');
            $this->fail('Accepted invalid brief');
        } catch (\moodle_exception $e) {
            $this->assertSame('textdraft_invalid', $e->errorcode);
        }
        $row = text_draft::create($aid, $userid, \core\uuid::generate(), 'Two adult coworkers talk.');
        set_config('lmslabssiteid', 'site-b', 'mod_aisoftskills');
        try {
            text_draft::send($row);
            $this->fail('Silently changed the billing site');
        } catch (\moodle_exception $e) {
            $this->assertSame('textdraft_sitechanged', $e->errorcode);
        }
        $row->timecreated = time() - 86401;
        $DB->update_record('aisoftskills_draft', $row);
        $row = text_draft::send($row);
        $this->assertSame('expired', $row->state);
        $this->assertSame('', $row->requestbody);
    }

    /**
     * Server error envelopes and malformed success are explicit; no accidental 3-credit success is invented.
     * @covers \mod_aisoftskills\local\ai\text_draft
     */
    public function test_server_envelopes(): void {
        $pending = text_draft::parse_response(202, json_encode([
            'requestId' => self::REQUESTID, 'error' => ['code' => 'PENDING', 'message' => 'PENDING'],
        ]));
        $this->assertSame('pending', $pending['state']);
        $this->assertSame('expired', text_draft::parse_response(410, json_encode([
            'requestId' => self::REQUESTID, 'error' => ['code' => 'EXPIRED', 'message' => 'EXPIRED'],
        ]))['state']);
        $this->assertSame('error', text_draft::parse_response(402, json_encode([
            'requestId' => self::REQUESTID,
            'error' => ['code' => 'INSUFFICIENT_CREDITS', 'message' => 'INSUFFICIENT_CREDITS'],
            'creditsBalance' => 2,
        ]))['state']);
        $this->expectException(\moodle_exception::class);
        text_draft::parse_response(200, json_encode([
            'requestId' => self::REQUESTID, 'creditsCharged' => 3, 'draft' => [],
        ]));
    }

    /**
     * Privacy deletion removes drafts, including text and billing-recovery metadata.
     * @covers \mod_aisoftskills\local\ai\text_draft
     */
    public function test_privacy_deletion(): void {
        global $DB;
        [$aid, $userid] = $this->fixture();
        text_draft::create($aid, $userid, \core\uuid::generate(), 'Two colleagues talk.');
        $this->assertSame(1, $DB->count_records('aisoftskills_draft', ['aisoftskillsid' => $aid]));
        \mod_aisoftskills\local\learning::delete_user_data($aid, $userid);
        $this->assertSame(0, $DB->count_records('aisoftskills_draft', ['aisoftskillsid' => $aid]));
    }
}