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

namespace mod_aisoftskills\local;

use moodle_exception;
use stdClass;

/**
 * Lesson drafts: the AI prompt, reading pasted AI replies, importing scenes, picture prompts and AI rate limits.
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lesson {
    /** @var int Most scenes one import may create. */
    public const MAX_SCENES = 40;

    /** @var int Most scenes the builder asks for at once. */
    public const MAX_SCENES_PER_LESSON = 20;

    /**
     * Builder choices saved on the activity.
     *
     * @param stdClass $instance
     * @return array keys (skill keys), custom (custom skills), scenes (count)
     */
    public static function choices(stdClass $instance): array {
        $data = json_decode((string)$instance->skills, true) ?: [];
        $keys = array_values(array_filter((array)($data['keys'] ?? []), fn($k) => catalogue::is_skill((string)$k)));
        $custom = array_values(array_filter(
            array_map(fn($c) => manager::clean_line($c), (array)($data['custom'] ?? [])),
            'strlen'
        ));
        return [
            'keys' => $keys,
            'custom' => array_slice($custom, 0, catalogue::MAX_CUSTOM),
            'scenes' => max(1, min(self::MAX_SCENES_PER_LESSON, (int)($data['scenes'] ?? 8))),
        ];
    }

    /**
     * Names of the chosen skills, in English for the AI prompt.
     *
     * @param stdClass $instance
     * @return string[]
     */
    public static function chosen_skills(stdClass $instance): array {
        $choices = self::choices($instance);
        $names = array_map(
            fn($k) => get_string_manager()->get_string('skill_' . $k, 'mod_aisoftskills', null, 'en'),
            $choices['keys']
        );
        return array_merge($names, $choices['custom']);
    }

    /**
     * The industry, in English for prompts.
     *
     * @param stdClass $instance
     * @return string
     */
    public static function industry_english(stdClass $instance): string {
        if ($instance->industry === 'custom') {
            return trim((string)$instance->customindustry) !== '' ? trim((string)$instance->customindustry)
                : get_string_manager()->get_string('industry_custom', 'mod_aisoftskills', null, 'en');
        }
        return get_string_manager()->get_string('industry_' . $instance->industry, 'mod_aisoftskills', null, 'en');
    }

    /**
     * The instructions given to an AI assistant to draft the lesson.
     *
     * @param stdClass $instance
     * @return string
     */
    public static function prompt(stdClass $instance): string {
        $choices = self::choices($instance);
        $skills = self::chosen_skills($instance);
        $sm = get_string_manager();
        $a = (object)[
            'industry' => self::industry_english($instance),
            'level' => $sm->get_string('level_' . $instance->level, 'mod_aisoftskills', null, 'en'),
            'levelguide' => $sm->get_string('levelguide_' . $instance->level, 'mod_aisoftskills', null, 'en'),
            'language' => catalogue::language_english((string)$instance->contentlang),
            'skills' => $skills ? '- ' . implode("\n- ", $skills)
                : $sm->get_string('prompt_anyskills', 'mod_aisoftskills', null, 'en'),
            'scenes' => $choices['scenes'],
            'kpis' => implode(', ', catalogue::KPIS),
            'maxdelta' => catalogue::MAX_DELTA,
            'style' => $sm->get_string('imagestyle_' . $instance->imagestyle, 'mod_aisoftskills', null, 'en'),
        ];
        return $sm->get_string('lessonprompt', 'mod_aisoftskills', $a, 'en');
    }

    /**
     * The people a scene's picture must show, from its name labels (or the labels suggested from the scene text):
     * "Priya, the nurse, a woman; the bar shift supervisor (the person the learner plays)".
     *
     * @param stdClass $scene
     * @return string English, '' when the scene names nobody
     */
    public static function picture_people(stdClass $scene): string {
        $labels = labels::get($scene) ?: labels::suggest($scene);
        $out = [];
        foreach (array_slice($labels, 0, labels::MAX) as $label) {
            [$name, $role] = labels::split((string)$label['text']);
            // An abbreviation such as RN stays in capitals; other roles are written in lower case.
            $role = preg_match('/\p{Lu}{2}/u', $role) ? $role : \core_text::strtolower($role);
            $gender = ['f' => 'a woman', 'm' => 'a man'][$label['gender'] ?? ''] ?? '';
            if (!empty($label['you'])) {
                if ($role !== '') {
                    $out[] = 'the ' . $role . ($gender !== '' ? ', ' . $gender : '') . ' (the person the learner plays)';
                }
                continue;
            }
            $out[] = implode(', ', array_filter([$name, $role !== '' ? 'the ' . $role : '', $gender]));
        }
        return $out ? implode('; ', $out) : 'the people the scene describes';
    }

    /**
     * The full picture prompt for a scene.
     *
     * @param stdClass $instance
     * @param stdClass $scene
     * @return string
     */
    public static function image_prompt(stdClass $instance, stdClass $scene): string {
        $description = trim((string)$scene->imageprompt) !== '' ? trim((string)$scene->imageprompt) : trim((string)$scene->title);
        $sm = get_string_manager();
        $people = self::picture_people($scene);
        $build = fn($d) => $sm->get_string('imageprompt_full', 'mod_aisoftskills', (object)[
            'description' => $d,
            'people' => $people,
            'industry' => self::industry_english($instance),
            'style' => $sm->get_string('imagestyle_' . $instance->imagestyle, 'mod_aisoftskills', null, 'en'),
        ], 'en');
        $prompt = $build($description);
        // The picture route accepts at most 2,000 characters: shorten the description, never the rules.
        $over = \core_text::strlen($prompt) - \mod_aisoftskills\local\ai\lmslabs::MAX_PROMPT;
        if ($over > 0) {
            $keep = max(0, \core_text::strlen($description) - $over - 1);
            $prompt = $build(rtrim(\core_text::substr($description, 0, $keep)) . '…');
        }
        return $prompt;
    }

    /**
     * Reads a lesson draft from AI output, tolerating code fences and text around the JSON.
     *
     * @param string $raw
     * @return array cleaned draft
     */
    public static function parse(string $raw): array {
        $text = trim($raw);
        // Remove a Markdown code fence (three backticks, written as \x60) around the JSON.
        $text = preg_replace('/^\x60{3}[a-z]*\s*/i', '', $text);
        $text = preg_replace('/\x60{3}\s*$/', '', $text);
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            throw new moodle_exception('lessoninvalid', 'mod_aisoftskills');
        }
        $data = json_decode(substr($text, $start, $end - $start + 1), true);
        if (!is_array($data)) {
            throw new moodle_exception('lessoninvalid', 'mod_aisoftskills');
        }
        return self::clean($data);
    }

    /**
     * Validates and cleans a decoded draft. A scene is kept only with exactly two responses, one of them the better one.
     *
     * @param array $data
     * @return array
     */
    public static function clean(array $data): array {
        $scenes = [];
        foreach (array_slice((array)($data['scenes'] ?? []), 0, self::MAX_SCENES) as $scene) {
            if (!is_array($scene)) {
                continue;
            }
            $title = manager::clean_line($scene['title'] ?? '');
            $options = [];
            foreach (array_slice((array)($scene['options'] ?? []), 0, manager::MAX_OPTIONS) as $option) {
                if (is_array($option)) {
                    $clean = manager::clean_option($option);
                    if ($clean->text !== '') {
                        $options[] = $clean;
                    }
                }
            }
            $options = manager::mark_worst($options);
            if ($title === '' || !manager::options_ok($options)) {
                continue;
            }
            $options = array_map(fn($o) => (array)$o, $options);
            $scenes[] = [
                'title' => $title,
                'skill' => manager::clean_line($scene['skill'] ?? ''),
                'context' => manager::clean_text($scene['context'] ?? '', 2000),
                'speaker' => manager::clean_line($scene['speaker'] ?? ''),
                'question' => manager::clean_line($scene['question'] ?? ''),
                'imageprompt' => manager::clean_text($scene['imageprompt'] ?? '', 2000),
                'options' => $options,
            ];
        }
        if (!$scenes) {
            throw new moodle_exception('lessonempty', 'mod_aisoftskills');
        }
        return ['scenes' => $scenes];
    }

    /**
     * Creates scenes from a cleaned draft.
     *
     * @param stdClass $instance
     * @param array $draft
     * @return array counts: scenes
     */
    public static function import(stdClass $instance, array $draft): array {
        global $DB;
        $count = 0;
        $transaction = $DB->start_delegated_transaction();
        foreach (self::clean($draft)['scenes'] as $scene) {
            $sceneid = manager::add_scene($instance, $scene);
            manager::save_scene($DB->get_record('aisoftskills_scene', ['id' => $sceneid], '*', MUST_EXIST), [], $scene['options']);
            $count++;
        }
        $transaction->allow_commit();
        return ['scenes' => $count];
    }

    /**
     * Records an AI request.
     *
     * @param int $aid
     * @param int $userid
     * @param string $action
     * @param string $status
     */
    public static function log_ai(int $aid, int $userid, string $action, string $status): void {
        global $DB;
        $DB->insert_record('aisoftskills_ailog', (object)['aisoftskillsid' => $aid, 'userid' => $userid,
            'action' => $action, 'status' => $status, 'timecreated' => time()]);
    }

    /**
     * Stops a teacher making too many AI requests.
     *
     * @param int $userid
     */
    public static function check_ai_rate(int $userid): void {
        global $DB;
        $limit = (int)(get_config('mod_aisoftskills', 'airate') ?: 30);
        $count = $DB->count_records_select(
            'aisoftskills_ailog',
            // Voiceover clips have their own limit.
            "userid = :userid AND timecreated > :since AND action <> 'voice'",
            ['userid' => $userid, 'since' => time() - HOURSECS]
        );
        if ($count >= $limit) {
            throw new moodle_exception('airatelimit', 'mod_aisoftskills');
        }
    }
}
