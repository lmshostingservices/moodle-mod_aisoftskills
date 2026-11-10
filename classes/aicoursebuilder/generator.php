<?php
namespace mod_aisoftskills\aicoursebuilder;

defined('MOODLE_INTERNAL') || die();

/**
 * AI Course Builder adapter using Soft Skills' idempotent draft request and native lesson import.
 */
final class generator {
    private const COMPONENT = 'mod_aisoftskills';

    /**
     * Why AI Course Builder cannot use this activity on this site, or null when it can.
     *
     * @return string|null
     */
    public static function native_ready(): ?string {
        return (new \mod_aisoftskills\local\ai\lmslabs())->can_draft() ? null : get_string('aicb_needssetup', 'mod_aisoftskills');
    }

    public static function is_available(): bool {
        return class_exists('\local_aicoursebuilder\local\generator_runtime')
            && \local_aicoursebuilder\local\generator_runtime::component_available(self::COMPONENT);
    }

    public static function estimate(array $brief): int {
        return \local_aicoursebuilder\local\generator_runtime::estimate(self::COMPONENT, $brief);
    }

    public static function generate(int $cmid, array $brief): string {
        return \local_aicoursebuilder\local\generator_runtime::generate(self::COMPONENT, $cmid, $brief);
    }

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
        return \mod_aisoftskills\local\ai\lmslabs::TEXT_CREDITS;
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
            return [
                'status' => 'complete',
                'result' => ['requestRowId' => (int)$row->id, 'sceneId' => (int)$row->targetid],
            ];
        }
        if (in_array($row->status, ['failed', 'expired', 'conflict', 'dismissed'], true)) {
            return [
                'status' => 'failed',
                'retryable' => false,
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
}
