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

namespace mod_aisoftskills\aicoursebuilder;

defined('MOODLE_INTERNAL') || die();

/**
 * AI Course Builder adapter using Soft Skills' idempotent draft request and native lesson import.
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS-Labs
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class generator {
    /** @var string The plugin's component name. */
    private const COMPONENT = 'mod_aisoftskills';

    /**
     * Why AI Course Builder cannot use this activity on this site, or null when it can.
     *
     * @return string|null
     */
    public static function native_ready(): ?string {
        return (new \mod_aisoftskills\local\ai\lmslabs())->can_draft() ? null : get_string('aicb_needssetup', 'mod_aisoftskills');
    }

    /**
     * Whether this plugin can build activities for AI Course Builder.
     *
     * @return bool
     */
    public static function is_available(): bool {
        return class_exists('\local_aicoursebuilder\local\generator_runtime')
            && \local_aicoursebuilder\local\generator_runtime::component_available(self::COMPONENT);
    }

    /**
     * Credits one activity will use.
     *
     * @param array $brief
     * @return int
     */
    public static function estimate(array $brief): int {
        return \local_aicoursebuilder\local\generator_runtime::estimate(self::COMPONENT, $brief);
    }

    /**
     * Start generating one activity.
     *
     * @param int $cmid
     * @param array $brief
     * @return string
     */
    public static function generate(int $cmid, array $brief): string {
        return \local_aicoursebuilder\local\generator_runtime::generate(self::COMPONENT, $cmid, $brief);
    }

    /**
     * Where a generation job is up to.
     *
     * @param int $cmid
     * @param string $jobid
     * @return array
     */
    public static function status(int $cmid, string $jobid): array {
        return \local_aicoursebuilder\local\generator_runtime::status(self::COMPONENT, $cmid, $jobid);
    }

    /**
     * The existing scene-draft endpoint is priced per scene.
     *
     * @param array $brief
     * @return int
     */
    public static function native_estimate(array $brief): int {
        // The scene's text and its picture: a scene without a picture cannot be played.
        return \mod_aisoftskills\local\ai\lmslabs::TEXT_CREDITS + \mod_aisoftskills\local\ai\lmslabs::IMAGE_CREDITS;
    }

    /**
     * Start one scene through the plugin's existing idempotent request record.
     *
     * @param int $cmid
     * @param array $brief
     * @return string request-row ID (the endpoint's native replay/status key)
     */
    public static function native_generate(int $cmid, array $brief): string {
        global $DB;
        $cm = get_coursemodule_from_id('aisoftskills', $cmid, 0, false, MUST_EXIST);
        $instance = $DB->get_record('aisoftskills', ['id' => $cm->instance], '*', MUST_EXIST);
        $program = $brief['program'] ?? [];
        $lesson = $brief['lesson'] ?? [];
        $outcomes = self::clean_outcomes(
            // AI Course Builder sends the lesson outcomes as a list and the lesson
            // source as text; accept either so the adapter never starts empty.
            !empty($brief['outcomes']) ? $brief['outcomes']
                : (!empty($brief['lesson']['outcomes']) ? $brief['lesson']['outcomes']
                : preg_split('/[\r\n]+/', (string)($brief['sourcecontent'] ?? '')))
        );
        if (!$outcomes) {
            throw new \moodle_exception('generationfailed', 'local_aicoursebuilder');
        }
        // A new activity starts on the form's defaults (office, supervisor). Use the course's
        // workplace and the learners' level instead, so a new retail starter is not cast as an
        // office supervisor.
        if (
            (string)$instance->industry === 'office' && trim((string)($instance->customindustry ?? '')) === ''
                && (string)$instance->level === 'supervisor'
        ) {
            [$instance->industry, $instance->customindustry, $instance->level] = self::workplace($program);
            $DB->update_record('aisoftskills', (object)['id' => $instance->id, 'industry' => $instance->industry,
                'customindustry' => $instance->customindustry, 'level' => $instance->level]);
        }
        $title = trim((string)($lesson['title'] ?? $instance->name));
        $name = trim((string)($program['name'] ?? ''));
        $level = trim((string)($program['level'] ?? ''));
        $subject = trim((string)($program['subject'] ?? ''));
        $outcometext = \core_text::substr(implode('; ', $outcomes), 0, 1200);
        $title = str_replace(['<', '>'], '', $title);
        $brieftext = 'Create exactly one practical, learner-facing soft-skills scenario for this lesson: '
            . $title . '. Use the listed learning outcomes as the teaching objective. '
            . 'Make the choices realistic for the learners and show the consequences of each response. '
            . 'Do not add a second scenario. Outcomes: ' . $outcometext;
        $audience = str_replace(['<', '>'], '', trim(implode(', ', array_filter([
            $level,
            trim((string)($program['sector'] ?? '')),
            trim((string)($program['learnerProfile'] ?? '')),
        ]))));
        $audience = \core_text::substr($audience, 0, \mod_aisoftskills\local\ai\requests::MAX_DETAIL);
        $context = trim(implode(' | ', array_filter([
            $name !== '' ? 'Course: ' . $name : '',
            $subject !== '' ? 'Subject: ' . $subject : '',
            'Lesson: ' . $title,
        ])));
        $context = str_replace(['<', '>'], '', $context);
        $context = \core_text::substr($context, 0, \mod_aisoftskills\local\ai\requests::MAX_DETAIL);
        $userid = (int)$DB->get_field('local_aicoursebuilder_program', 'userid', ['id' => (int)$brief['programid']]);
        if ($userid < 1) {
            throw new \moodle_exception('generationfailed', 'local_aicoursebuilder');
        }
        $row = \mod_aisoftskills\local\ai\requests::start_scene(
            $instance,
            $userid,
            $brieftext,
            $audience,
            $context
        );
        return (string)$row->id;
    }

    /**
     * Check the plugin's stored request; pending checks reuse its original idempotency key.
     *
     * This endpoint does not expose /api/jobs: its documented recovery contract is same-key
     * request replay, so this adapter deliberately uses that native path instead.
     *
     * @param int $cmid
     * @param string $jobid
     * @return array
     */
    public static function native_status(int $cmid, string $jobid): array {
        global $DB;
        if (!ctype_digit($jobid)) {
            return ['status' => 'failed', 'retryable' => false, 'error' => 'The Soft Skills request reference is invalid.'];
        }
        $cm = get_coursemodule_from_id('aisoftskills', $cmid, 0, false, MUST_EXIST);
        $instance = $DB->get_record('aisoftskills', ['id' => $cm->instance], '*', MUST_EXIST);
        try {
            $row = \mod_aisoftskills\local\ai\requests::get((int)$instance->id, (int)$jobid);
            if (in_array($row->status, ['pending', 'uncertain'], true)) {
                $row = \mod_aisoftskills\local\ai\requests::check($row, $instance);
            }
        } catch (\Throwable $e) {
            return ['status' => 'failed', 'retryable' => false, 'error' => 'The saved Soft Skills request could not be read.'];
        }
        if ($row->status === 'completed') {
            return self::picture_status($cmid, $instance, $row);
        }
        if (in_array($row->status, ['failed', 'expired', 'conflict', 'dismissed'], true)) {
            // The service could not make a valid draft, or was briefly unavailable: nothing was
            // charged, so AI Course Builder may try again with a new request.
            $transient = in_array((string)$row->errorcode, ['invalid_provider_result', 'provider_unavailable',
                'provider_failed', 'provider_rate_limited', 'serviceunavailable', 'deadline_exceeded'], true);
            return [
                'status' => 'failed',
                'retryable' => $transient,
                'error' => clean_param((string)($row->errorcode ?? 'Soft Skills scene drafting failed.'), PARAM_TEXT),
            ];
        }
        return ['status' => 'running'];
    }

    /**
     * Confirm that the native request/import completed and persisted at least one scene.
     *
     * @param int $cmid
     * @param array $result
     * @return bool
     */
    public static function native_save_result(int $cmid, array $result): bool {
        global $DB;
        if (empty($result['requestRowId']) || empty($result['sceneId'])) {
            return false;
        }
        $cm = get_coursemodule_from_id('aisoftskills', $cmid, 0, false, MUST_EXIST);
        $instance = $DB->get_record('aisoftskills', ['id' => $cm->instance], '*', MUST_EXIST);
        $row = \mod_aisoftskills\local\ai\requests::get((int)$instance->id, (int)$result['requestRowId']);
        return $row->status === 'completed' && (int)$row->targetid > 0
            && (int)$row->targetid === (int)$result['sceneId']
            && $DB->record_exists('aisoftskills_scene', [
                'id' => (int)$result['sceneId'],
                'aisoftskillsid' => (int)$instance->id,
            ]);
    }

    /**
     * Clean list of outcome texts.
     *
     * @param mixed $value
     * @return string[]
     */
    private static function clean_outcomes($value): array {
        if (!is_array($value)) {
            return [];
        }
        $outcomes = [];
        foreach ($value as $item) {
            $text = is_array($item) ? trim((string)($item['text'] ?? '')) : trim((string)$item);
            if ($text !== '') {
                $outcomes[] = str_replace(['<', '>'], '', clean_param($text, PARAM_TEXT));
            }
        }
        return $outcomes;
    }

    /**
     * A drafted scene is playable only with its picture. Make the picture as the editor does, then report complete.
     *
     * @param int $cmid
     * @param \stdClass $instance
     * @param \stdClass $row the completed scene-draft request
     * @return array
     */
    private static function picture_status(int $cmid, \stdClass $instance, \stdClass $row): array {
        global $DB;
        $requests = '\\mod_aisoftskills\\local\\ai\\requests';
        $done = ['status' => 'complete', 'result' => ['requestRowId' => (int)$row->id, 'sceneId' => (int)$row->targetid]];
        $scene = $DB->get_record('aisoftskills_scene', ['id' => (int)$row->targetid, 'aisoftskillsid' => (int)$instance->id]);
        if (!$scene) {
            return ['status' => 'failed', 'retryable' => false, 'error' => 'The drafted scene was not saved.'];
        }
        $context = \context_module::instance($cmid);
        if (\mod_aisoftskills\local\manager::get_scene_file($context, (int)$scene->id) !== null) {
            return $done;
        }
        try {
            $image = $DB->get_records('aisoftskills_aireq', ['aisoftskillsid' => (int)$instance->id,
                'operation' => $requests::IMAGE, 'targetid' => (int)$scene->id], 'id DESC', '*', 0, 1);
            $image = $image ? reset($image) : null;
            if (!$image || in_array($image->status, ['failed', 'expired', 'conflict', 'dismissed'], true)) {
                $tries = (int)$DB->count_records('aisoftskills_aireq', ['aisoftskillsid' => (int)$instance->id,
                    'operation' => $requests::IMAGE, 'targetid' => (int)$scene->id]);
                if ($tries >= 3) {
                    return ['status' => 'failed', 'retryable' => true,
                        'error' => 'The scene picture could not be made: ' . clean_param((string)($image->errorcode ?? ''), PARAM_TEXT)];
                }
                $requests::start_image($instance, (int)$row->userid, $scene);
                return ['status' => 'running'];
            }
            if (in_array($image->status, ['pending', 'uncertain'], true)) {
                $image = $requests::check($image, $instance);
            }
        } catch (\Throwable $e) {
            return ['status' => 'failed', 'retryable' => true, 'error' => 'The scene picture could not be made: ' . $e->getMessage()];
        }
        if (\mod_aisoftskills\local\manager::get_scene_file($context, (int)$scene->id) !== null) {
            return $done;
        }
        return ['status' => 'running'];
    }

    /**
     * The catalogue industry (or a custom one) and career level that fit the course.
     *
     * @param array $program
     * @return array [industry, custom industry name or null, level]
     */
    public static function workplace(array $program): array {
        // The subject and course name say where the learners work; the sector (school, university)
        // only says where they study, so it is not used.
        $text = \core_text::strtolower(implode(' ', [(string)($program['subject'] ?? ''), (string)($program['name'] ?? '')]));
        $map = [
            'agedcare' => '/aged|elder|older people|disability|individual support|community care/',
            'healthcare' => '/health|nurs|medic|clinic|hospital|pharmac|dental/',
            'hospitality' => '/hospitality|kitchen|cook|chef|caf|restaurant|hotel|bar |barista|food/',
            'retail' => '/retail|shop|store|sales|customer service|hardware/',
            'construction' => '/construct|building|carpent|plumb|electric|civil/',
            'education' => '/teach|school|education|tutor|early childhood|childcare/',
            'callcentre' => '/call cent|contact cent/',
            'technology' => '/information technology|software|programming|cyber|network admin/',
            'finance' => '/financ|bank|account|bookkeep|insurance/',
            'logistics' => '/logistic|warehouse|transport|supply chain|forklift/',
            'manufacturing' => '/manufactur|factory|production/',
            'government' => '/government|public sector|council/',
            'mining' => '/mining|mine site/',
            'agriculture' => '/agricult|farm|horticult/',
            'trades' => '/trade|mechanic|automotive|welding/',
            'office' => '/office|admin|business/',
        ];
        $industry = 'custom';
        foreach ($map as $key => $pattern) {
            if (preg_match($pattern, $text)) {
                $industry = $key;
                break;
            }
        }
        $custom = null;
        if ($industry === 'custom') {
            $custom = \core_text::substr(trim(str_replace(['<', '>'], '', clean_param((string)($program['subject'] ?? ''), PARAM_TEXT)))
                ?: 'General', 0, 255);
        }
        $level = \core_text::strtolower((string)($program['level'] ?? ''));
        $role = preg_match('/leader|executive|director|senior manage/', $level) ? 'leader'
            : (preg_match('/manager|management|diploma/', $level) ? 'manager'
            : (preg_match('/supervis|team lead|certificate iv|cert iv/', $level) ? 'supervisor' : 'worker'));
        return [$industry, $custom, $role];
    }
}
