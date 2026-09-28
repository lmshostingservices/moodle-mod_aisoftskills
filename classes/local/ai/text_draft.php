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

namespace mod_aisoftskills\local\ai;

use mod_aisoftskills\local\credentials;
use moodle_exception;

/**
 * Teacher-only scene/script text drafts. This is NOT the branching lesson importer or image API.
 *
 * The Moodle request is persisted before the paid POST. A manual recovery always reuses the original
 * body and key, including after a lost response, 202, 429 or 5xx. Only a new explicit form intent creates
 * a new key. The server may return the same saved draft without a second provider call or charge.
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class text_draft {
    /** @var string Approved separately billed text endpoint. */
    public const ROUTE = '/api/moodle/ai-softskills/scenes/draft';

    /** @var callable|null Unit-test transport: fn($url, $headers, $body) => [$status, $response]. */
    public static $transport = null;

    /**
     * Create or recover the same intentional form submission without ever changing its key/body.
     *
     * @param int $activityid
     * @param int $userid
     * @param string $intent Non-secret server-generated form token.
     * @param string $brief Teacher text, not learner data.
     * @return \stdClass
     */
    public static function create(int $activityid, int $userid, string $intent, string $brief): \stdClass {
        global $DB;
        if (!preg_match('/^[0-9a-f-]{36}$/D', $intent)) {
            throw new moodle_exception('textdraft_invalid', 'mod_aisoftskills');
        }
        $brief = trim($brief);
        if ($brief === '' || \core_text::strlen($brief) > 2000 ||
                preg_match('/[\x00-\x08\x0b-\x1f\x7f<>]/u', $brief) !== 0) {
            throw new moodle_exception('textdraft_invalid', 'mod_aisoftskills');
        }
        $body = json_encode(['brief' => $brief], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (strlen($body) > 16384) {
            throw new moodle_exception('textdraft_invalid', 'mod_aisoftskills');
        }
        $existing = $DB->get_record('aisoftskills_draft', ['userid' => $userid, 'intentkey' => $intent]);
        if ($existing) {
            if ((int)$existing->aisoftskillsid !== $activityid || $existing->bodyhash !== hash('sha256', $body)) {
                throw new moodle_exception('textdraft_intentconflict', 'mod_aisoftskills');
            }
            return $existing;
        }
        $pair = credentials::resolve();
        if (strlen($pair['siteid']) > 512 || strlen($pair['apikey']) > 512 ||
                preg_match('/[\x00-\x1f\x7f]/', $pair['siteid'] . $pair['apikey'])) {
            throw new moodle_exception('textdraft_invalid', 'mod_aisoftskills');
        }
        $row = (object)[
            'aisoftskillsid' => $activityid,
            'userid' => $userid,
            'requestkey' => \core\uuid::generate(),
            'intentkey' => $intent,
            'siteid' => $pair['siteid'],
            'requestbody' => $body,
            'bodyhash' => hash('sha256', $body),
            'responsebody' => null,
            'editedbody' => null,
            'requestid' => null,
            'errorcode' => null,
            'state' => 'pending',
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        try {
            $row->id = $DB->insert_record('aisoftskills_draft', $row);
        } catch (\dml_write_exception $e) {
            // A concurrent submission of the same form token might have inserted it.
            $winner = $DB->get_record('aisoftskills_draft', ['userid' => $userid, 'intentkey' => $intent]);
            if (!$winner || (int)$winner->aisoftskillsid !== $activityid || $winner->bodyhash !== hash('sha256', $body)) {
                throw $e;
            }
            return $winner;
        }
        return $row;
    }

    /**
     * Parse a server envelope and classify an operation without exposing provider details.
     *
     * @param int $status HTTP status
     * @param string $response JSON body
     * @return array Operation state, reference and optional immutable successful draft
     */
    public static function parse_response(int $status, string $response): array {
        if (strlen($response) > 65536) {
            throw new moodle_exception('textdraft_uncertain', 'mod_aisoftskills');
        }
        $data = json_decode($response, true);
        $requestid = is_array($data) ? ($data['requestId'] ?? '') : '';
        if (!is_string($requestid) || !preg_match('/^[0-9a-f-]{36}$/Di', $requestid)) {
            throw new moodle_exception('textdraft_uncertain', 'mod_aisoftskills');
        }
        if ($status === 200) {
            $draft = $data['draft'] ?? null;
            if (!is_array($draft) || !is_array($draft['characters'] ?? null) ||
                    !is_array($draft['dialogue'] ?? null) || count($draft['characters']) < 2 ||
                    count($draft['characters']) > 6 || count($draft['dialogue']) < 2 ||
                    count($draft['dialogue']) > 20 || ($data['creditsCharged'] ?? null) !== 3 ||
                    !is_int($data['creditsBalance'] ?? null) ||
                    ($data['model'] ?? null) !== 'gpt-4o-2024-08-06') {
                throw new moodle_exception('textdraft_uncertain', 'mod_aisoftskills');
            }
            foreach (['title', 'setting', 'teachingNote'] as $field) {
                if (!is_string($draft[$field] ?? null) || trim($draft[$field]) === '') {
                    throw new moodle_exception('textdraft_uncertain', 'mod_aisoftskills');
                }
            }
            foreach ($draft['characters'] as $character) {
                if (!is_string($character) || trim($character) === '') {
                    throw new moodle_exception('textdraft_uncertain', 'mod_aisoftskills');
                }
            }
            foreach ($draft['dialogue'] as $line) {
                if (!is_array($line) || !in_array($line['speaker'] ?? null, $draft['characters'], true) ||
                        !is_string($line['line'] ?? null) || trim($line['line']) === '') {
                    throw new moodle_exception('textdraft_uncertain', 'mod_aisoftskills');
                }
            }
            return ['state' => 'complete', 'requestid' => $requestid, 'draft' => $draft, 'error' => null];
        }
        $code = is_array($data['error'] ?? null) ? ($data['error']['code'] ?? '') : '';
        $allowed = ['PENDING', 'EXPIRED', 'INVALID_CREDENTIALS', 'NO_ENTITLEMENT', 'INSUFFICIENT_CREDITS',
            'IDEMPOTENCY_CONFLICT', 'BODY_TOO_LARGE', 'INVALID_JSON', 'UNEXPECTED_FIELDS', 'INVALID_INPUT',
            'INVALID_IDEMPOTENCY_KEY', 'RATE_LIMITED', 'PROVIDER_RATE_LIMITED', 'PROVIDER_FAILED',
            'INVALID_PROVIDER_RESULT', 'PROVIDER_UNAVAILABLE', 'DEADLINE_EXCEEDED', 'SETTLEMENT_UNCONFIRMED',
            'REQUEST_NOT_PENDING'];
        if (!is_string($code) || !in_array($code, $allowed, true)) {
            throw new moodle_exception('textdraft_uncertain', 'mod_aisoftskills');
        }
        if ($status === 410 && $code === 'EXPIRED') {
            return ['state' => 'expired', 'requestid' => $requestid, 'draft' => null, 'error' => $code];
        }
        if (($status === 202 && $code === 'PENDING') || $status === 429 || $status >= 500) {
            // Even a failed provider attempt may have settled; keep the original key for manual replay.
            return ['state' => 'pending', 'requestid' => $requestid, 'draft' => null, 'error' => $code];
        }
        if (in_array($status, [400, 401, 402, 403, 409, 413, 422], true)) {
            return ['state' => 'error', 'requestid' => $requestid, 'draft' => null, 'error' => $code];
        }
        throw new moodle_exception('textdraft_uncertain', 'mod_aisoftskills');
    }

    /**
     * Send one teacher-directed call with the original persisted body/key. NEVER automatically retry.
     *
     * @param \stdClass $row Fetched for the authenticated author and activity.
     * @return \stdClass
     */
    public static function send(\stdClass $row): \stdClass {
        global $CFG, $DB;
        if ($row->state !== 'pending') {
            return $row;
        }
        if (time() - (int)$row->timecreated >= 86400) {
            // The server's replay expires after 24h, even if cleanup has not run.
            $row->state = 'expired';
            $row->requestbody = '';
            $row->timemodified = time();
            $DB->update_record('aisoftskills_draft', $row);
            return $row;
        }
        $pair = credentials::resolve();
        if ($pair['siteid'] !== $row->siteid) {
            throw new moodle_exception('textdraft_sitechanged', 'mod_aisoftskills');
        }
        if (strlen($pair['siteid']) > 512 || strlen($pair['apikey']) > 512 ||
                preg_match('/[\x00-\x1f\x7f]/', $pair['siteid'] . $pair['apikey'])) {
            throw new moodle_exception('textdraft_invalid', 'mod_aisoftskills');
        }
        $url = lmslabs::BASE_URL . self::ROUTE;
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Site-ID: ' . $pair['siteid'],
            'X-API-Key: ' . $pair['apikey'],
            'Idempotency-Key: ' . $row->requestkey,
        ];
        if (self::$transport !== null) {
            [$status, $response] = (self::$transport)($url, $headers, $row->requestbody);
        } else {
            require_once($CFG->libdir . '/filelib.php');
            $curl = new \curl(['ignoresecurity' => false]);
            $curl->setHeader($headers);
            $response = $curl->post($url, $row->requestbody, [
                'CURLOPT_CONNECTTIMEOUT' => 10,
                'CURLOPT_TIMEOUT' => 88,
                'CURLOPT_FOLLOWLOCATION' => false,
            ]);
            if ($curl->get_errno()) {
                throw new moodle_exception('textdraft_uncertain', 'mod_aisoftskills');
            }
            $status = (int)($curl->get_info()['http_code'] ?? 0);
        }
        $parsed = self::parse_response((int)$status, (string)$response);
        $row->requestid = $parsed['requestid'];
        $row->errorcode = $parsed['error'];
        $row->state = $parsed['state'];
        if ($row->state === 'complete') {
            $row->responsebody = json_encode($parsed['draft'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $row->editedbody = self::readable($parsed['draft']);
            $row->requestbody = '';
        } else if ($row->state === 'expired' || $row->state === 'error') {
            $row->requestbody = '';
        }
        $row->timemodified = time();
        $DB->update_record('aisoftskills_draft', $row);
        return $row;
    }

    /**
     * Format a teacher-editable script without changing the original server draft.
     *
     * @param array $draft Structured verified result.
     * @return string
     */
    public static function readable(array $draft): string {
        $lines = [$draft['title'], $draft['setting'], ''];
        foreach ($draft['dialogue'] as $line) {
            $lines[] = $line['speaker'] . ': ' . $line['line'];
        }
        $lines[] = '';
        $lines[] = $draft['teachingNote'];
        return implode("\n", $lines);
    }

    /**
     * Edit a completed Moodle copy, without ever contacting the paid endpoint.
     *
     * @param \stdClass $row Owned completed draft
     * @param string $text Teacher's revision
     */
    public static function save_edit(\stdClass $row, string $text): void {
        global $DB;
        if ($row->state !== 'complete' || trim($text) === '' || \core_text::strlen($text) > 32000) {
            throw new moodle_exception('textdraft_invalidedit', 'mod_aisoftskills');
        }
        $row->editedbody = $text;
        $row->timemodified = time();
        $DB->update_record('aisoftskills_draft', $row);
    }
}