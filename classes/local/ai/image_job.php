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
use mod_aisoftskills\local\lesson;
use moodle_exception;

/**
 * Durable Moodle billing intent for a 5-credit image. Never stores image bytes or claims backend replay of bytes.
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class image_job {
    /**
     * Last operation for the current scene and teacher (the key never leaves Moodle PHP).
     *
     * @param int $sceneid
     * @param int $userid
     * @return \stdClass|null
     */
    public static function latest(int $sceneid, int $userid): ?\stdClass {
        global $DB;
        $records = $DB->get_records('aisoftskills_imagejob',
            ['sceneid' => $sceneid, 'userid' => $userid], 'id DESC', '*', 0, 1);
        return $records ? reset($records) : null;
    }

    /**
     * Persist exact image key/body before the network call; resume uncertain/pending instead of starting anew.
     *
     * @param \stdClass $instance
     * @param \stdClass $scene
     * @param int $userid
     * @param string $intent Browser form nonce (not a credential or backend key).
     * @return \stdClass
     */
    public static function begin(\stdClass $instance, \stdClass $scene, int $userid, string $intent): \stdClass {
        global $DB;
        if (!preg_match('/^[0-9a-f-]{36}$/Di', $intent)) {
            throw new moodle_exception('imageintent_invalid', 'mod_aisoftskills');
        }
        $existing = $DB->get_record('aisoftskills_imagejob', ['userid' => $userid, 'intentkey' => $intent]);
        if ($existing) {
            if ((int)$existing->sceneid !== (int)$scene->id || (int)$existing->aisoftskillsid !== (int)$instance->id) {
                throw new moodle_exception('imageintent_invalid', 'mod_aisoftskills');
            }
            return $existing;
        }
        $latest = self::latest((int)$scene->id, $userid);
        if ($latest && in_array($latest->state, ['pending', 'saving'], true)) {
            // Pending includes 202, 429, 5xx and uncertain network delivery. A crash while
            // writing a charged image is also uncertain: never mint a replacement key.
            return $latest;
        }
        lesson::check_ai_rate($userid);
        $pair = credentials::resolve();
        if (strlen($pair['siteid']) > 512 || strlen($pair['apikey']) > 512 ||
                preg_match('/[\x00-\x1f\x7f]/', $pair['siteid'] . $pair['apikey'])) {
            throw new moodle_exception('imageintent_invalid', 'mod_aisoftskills');
        }
        $prompt = trim(lesson::image_prompt($instance, $scene));
        if ($prompt === '' || \core_text::strlen($prompt) > lmslabs::MAX_PROMPT) {
            throw new moodle_exception('aierror_prompt_too_long', 'mod_aisoftskills');
        }
        $style = in_array((string)$instance->imagestyle, lmslabs::STYLES, true) ?
            (string)$instance->imagestyle : 'illustration';
        $row = (object)[
            'aisoftskillsid' => (int)$instance->id,
            'sceneid' => (int)$scene->id,
            'userid' => $userid,
            'intentkey' => $intent,
            'requestkey' => \core\uuid::generate(),
            'siteid' => $pair['siteid'],
            'requestbody' => json_encode(['prompt' => $prompt, 'style' => $style],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'requestid' => null,
            'errorcode' => null,
            'state' => 'pending',
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        try {
            $row->id = $DB->insert_record('aisoftskills_imagejob', $row);
        } catch (\dml_write_exception $e) {
            // Concurrent double click of one form nonce: one DB row/key wins.
            $winner = $DB->get_record('aisoftskills_imagejob', ['userid' => $userid, 'intentkey' => $intent]);
            if (!$winner || (int)$winner->sceneid !== (int)$scene->id) {
                throw $e;
            }
            return $winner;
        }
        return $row;
    }

    /**
     * Call once using only persisted values. Failures retain the original key; images are NEVER replayable as bytes.
     *
     * @param \stdClass $row Persisted operation
     * @param provider $provider Image provider
     * @return array|null Image response, or null when this operation has no new bytes to deliver
     */
    public static function send(\stdClass $row, provider $provider): ?array {
        global $DB;
        if ($row->state !== 'pending') {
            return null;
        }
        $pair = credentials::resolve();
        if ($pair['siteid'] !== $row->siteid) {
            throw new moodle_exception('imageintent_sitechanged', 'mod_aisoftskills');
        }
        $body = json_decode((string)$row->requestbody, true);
        if (!is_array($body) || !isset($body['prompt'], $body['style'])) {
            throw new moodle_exception('imageintent_invalid', 'mod_aisoftskills');
        }
        try {
            $result = $provider->generate_image($body['prompt'], $body['style'],
                $row->requestkey, $row->requestbody);
        } catch (moodle_exception $e) {
            $row->errorcode = $e->errorcode;
            if (is_object($e->a ?? null) && !empty($e->a->requestid) && $e->a->requestid !== '-') {
                $row->requestid = clean_param((string)$e->a->requestid, PARAM_ALPHANUMEXT);
            }
            if ($e->errorcode === 'aierror_result_not_retained') {
                // 410: even a charged success has no cached image to return. Do not promise recovery.
                $row->state = 'lost';
            } else if (in_array($e->errorcode, ['aierror_invalid_credentials', 'aierror_no_entitlement',
                'aierror_insufficient_credits', 'aierror_idempotency_conflict', 'aierror_invalid_input'], true)) {
                $row->state = 'error';
            }
            if ($row->state !== 'pending') {
                $row->requestbody = '';
            }
            // Pending/429/5xx/network/unknown: preserve the SAME key and body for deliberate manual checking.
            $row->timemodified = time();
            $DB->update_record('aisoftskills_imagejob', $row);
            throw $e;
        }
        // Preserve the backend reference even if saving PNG in Moodle subsequently fails.
        $row->requestid = (string)$result['requestid'];
        $row->errorcode = null;
        $row->state = 'saving';
        $row->timemodified = time();
        $DB->update_record('aisoftskills_imagejob', $row);
        return $result;
    }

    /**
     * Mark Moodle file save complete, or explicitly record that a charged image could not be retained.
     *
     * @param \stdClass $row
     * @param bool $saved Whether PNG was stored in Moodle
     */
    public static function finish(\stdClass $row, bool $saved): void {
        global $DB;
        $row->state = $saved ? 'complete' : 'lost';
        $row->errorcode = $saved ? null : 'IMAGE_NOT_SAVED';
        $row->requestbody = '';
        $row->timemodified = time();
        $DB->update_record('aisoftskills_imagejob', $row);
    }
}