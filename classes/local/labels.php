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

use stdClass;

/**
 * Name labels on a scene picture, such as "Leo - Bartender", placed by the teacher.
 *
 * Labels are not drawn into the picture: they are stored as text with a position (percent of the picture's width and
 * height, the label's centre) and shown over the picture, so they stay sharp, readable by screen readers, and can be
 * moved at any time without making the picture again. An AI picture model cannot be relied on to put a name on the
 * right person, so the teacher places them; new labels start in a row across the middle of the picture.
 *
 * Each label also says whether the person sounds female or male, so the voiceover matches the person in the picture,
 * and one label may be the learner ("you"): the two responses are read in that person's voice.
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class labels {
    /** @var int Most labels on one picture. */
    public const MAX = 6;

    /** @var int Longest label, in characters. */
    public const MAX_TEXT = 60;

    /** @var float Height of the default row (percent from the top: roughly chest height in a half-body shot). */
    public const DEFAULT_Y = 55.0;

    /**
     * Cleans labels from the page or the database: text, and a position kept inside the picture.
     *
     * @param mixed $labels list of {text, x, y, gender (f, m or ''), you}
     * @return array list of {text, x, y, gender, you}
     */
    public static function clean($labels): array {
        $out = [];
        foreach (is_array($labels) ? $labels : [] as $label) {
            if (!is_array($label) && !is_object($label)) {
                continue;
            }
            $label = (array)$label;
            $text = manager::clean_line((string)($label['text'] ?? ''), self::MAX_TEXT);
            if ($text === '') {
                continue;
            }
            $pos = fn($v) => round(max(2.0, min(98.0, is_numeric($v) ? (float)$v : 50.0)), 1);
            $gender = in_array($label['gender'] ?? '', ['f', 'm'], true) ? $label['gender'] : '';
            // Only one label can be the learner.
            $you = !empty($label['you']) && !in_array(true, array_column($out, 'you'), true);
            $out[] = ['text' => $text, 'x' => $pos($label['x'] ?? 50), 'y' => $pos($label['y'] ?? self::DEFAULT_Y),
                'gender' => $gender, 'you' => $you];
            if (count($out) === self::MAX) {
                break;
            }
        }
        return $out;
    }

    /**
     * The saved labels of a scene.
     *
     * @param stdClass $scene
     * @return array list of {text, x, y}
     */
    public static function get(stdClass $scene): array {
        return self::clean(json_decode((string)($scene->labels ?? ''), true));
    }

    /**
     * Saves a scene's labels (an empty list removes them).
     *
     * @param stdClass $scene
     * @param mixed $labels
     * @return array the saved labels
     */
    public static function save(stdClass $scene, $labels): array {
        global $DB;
        $clean = self::clean($labels);
        $DB->update_record('aisoftskills_scene', (object)['id' => $scene->id, 'timemodified' => time(),
            'labels' => $clean ? json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null]);
        $scene->labels = $clean ? json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        return $clean;
    }

    /**
     * Suggested labels for a scene that has none yet: the characters of its conversation, in a row.
     *
     * A role found in the scene text right before the name ("bartender Leo") is added: "Leo - Bartender".
     *
     * @param stdClass $scene
     * @return array list of {text, x, y}
     */
    public static function suggest(stdClass $scene): array {
        $names = [];
        foreach (manager::dialogue($scene->script) as $line) {
            $name = trim((string)$line['speaker']);
            if ($name !== '' && !in_array($name, $names, true)) {
                $names[] = $name;
            }
        }
        $script = json_decode((string)$scene->script, true);
        foreach ((array)($script['characters'] ?? []) as $name) {
            $name = is_string($name) ? trim($name) : '';
            if ($name !== '' && !in_array($name, $names, true)) {
                $names[] = $name;
            }
        }
        $text = (string)$scene->context;
        foreach (self::named($text) as $name) {
            if (!in_array($name, $names, true)) {
                $names[] = $name;
            }
        }
        $people = [];
        foreach ($names as $name) {
            $role = self::role($text, $name);
            $people[] = ['text' => $role !== '' ? $name . ' - ' . $role : $name, 'gender' => self::gender($text, $name),
                'you' => false];
        }
        // The learner: the person who answers, such as "You, the shift supervisor".
        $speaker = trim((string)$scene->speaker);
        $role = learning::role_name($speaker, 'en');
        $you = $role !== '' ? get_string('label_you', 'mod_aisoftskills') . ' - '
            . \core_text::strtoupper(\core_text::substr($role, 0, 1)) . \core_text::substr($role, 1) : $speaker;
        array_unshift($people, ['text' => $you !== '' ? $you : get_string('label_you', 'mod_aisoftskills'),
            'gender' => '', 'you' => true]);
        $people = array_slice($people, 0, self::MAX);
        $out = [];
        foreach ($people as $i => $person) {
            $out[] = $person + ['x' => round(100 * ($i + 1) / (count($people) + 1), 1), 'y' => self::DEFAULT_Y];
        }
        return self::clean($out);
    }

    /**
     * People named with a role in the scene text, such as Leo in "bartender Leo".
     *
     * @param string $text
     * @return string[]
     */
    public static function named(string $text): array {
        preg_match_all('/(?:^|[\s,(])[a-z][a-z-]{2,}\s+(\p{Lu}\p{Ll}+)\b/u', $text, $m);
        $out = [];
        foreach (array_unique($m[1]) as $name) {
            if (self::role($text, $name) !== '') {
                $out[] = $name;
            }
        }
        return $out;
    }

    /**
     * Whether the scene text speaks of a person as he or she, read from the sentences that name them.
     *
     * @param string $text
     * @param string $name
     * @return string f, m or '' when the text does not say
     */
    public static function gender(string $text, string $name): string {
        $he = 0;
        $she = 0;
        $sentences = preg_split('/(?<=[.!?])\s+/u', $text);
        foreach ($sentences as $i => $sentence) {
            if (!preg_match('/\b' . preg_quote($name, '/') . '\b/u', $sentence)) {
                continue;
            }
            // The sentence naming them, and the next one ("Leo hesitates. He says ...").
            $near = \core_text::strtolower($sentence . ' ' . ($sentences[$i + 1] ?? ''));
            $he += preg_match_all('/\b(he|him|his|himself)\b/u', $near);
            $she += preg_match_all('/\b(she|her|hers|herself)\b/u', $near);
        }
        return $he > $she ? 'm' : ($she > $he ? 'f' : '');
    }

    /**
     * The person a label names, for matching voices across scenes: "Leo - Bartender" is "leo".
     *
     * @param string $text
     * @return string
     */
    public static function person(string $text): string {
        $name = preg_split('/\s+[-–—]\s+|,/u', $text)[0];
        return \core_text::strtolower(trim($name));
    }

    /**
     * The job or role written just before a name in the scene text, such as "bartender" in "bartender Leo".
     *
     * @param string $text
     * @param string $name
     * @return string the role with a capital first letter, or ''
     */
    public static function role(string $text, string $name): string {
        // Only a lower-case word (or two, like "events manager") right before the name, never a verb such as "ask".
        $pattern = '/(?:^|[\s,(])((?:[a-z][a-z-]+ )?[a-z][a-z-]{2,})\s+' . preg_quote($name, '/') . '\b/u';
        if (!preg_match($pattern, $text, $m)) {
            return '';
        }
        $words = explode(' ', $m[1]);
        $notroles = ['ask', 'asks', 'asked', 'tell', 'tells', 'told', 'and', 'but', 'the', 'with', 'from', 'for', 'to',
            'call', 'calls', 'see', 'sees', 'meet', 'meets', 'help', 'helps', 'thank', 'thanks', 'when', 'while', 'that',
            'says', 'said', 'than', 'then', 'where', 'who', 'about', 'after', 'before', 'because', 'into', 'onto', 'your',
            'their', 'his', 'her', 'our', 'its', 'are', 'was', 'were', 'has', 'have', 'had', 'you', 'not', 'new'];
        // Drop leading words that are not part of a role ("ask bartender" -> "bartender").
        while ($words && in_array($words[0], $notroles, true)) {
            array_shift($words);
        }
        if (!$words || in_array(end($words), $notroles, true)) {
            return '';
        }
        $role = implode(' ', $words);
        return \core_text::strtoupper(\core_text::substr($role, 0, 1)) . \core_text::substr($role, 1);
    }
}
