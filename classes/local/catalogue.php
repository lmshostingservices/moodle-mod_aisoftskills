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

/**
 * Industries, soft skills, career levels, workplace indicators and content languages.
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catalogue {
    /** @var string[] Industries a lesson can be set in. */
    public const INDUSTRIES = ['office', 'retail', 'hospitality', 'healthcare', 'agedcare', 'construction', 'manufacturing',
        'logistics', 'education', 'callcentre', 'technology', 'finance', 'government', 'trades', 'mining', 'agriculture'];

    /** @var string[] Career levels, first to last. */
    public const LEVELS = ['worker', 'supervisor', 'manager', 'leader'];

    /** @var array Soft skills, grouped. */
    public const SKILLS = [
        'communication' => ['activelistening', 'clearinstructions', 'difficultconversations', 'feedbackgiving',
            'feedbackreceiving', 'customerservice'],
        'people' => ['motivation', 'teamwork', 'empathy', 'conflict', 'inclusion', 'coaching', 'delegation'],
        'self' => ['timemanagement', 'accountability', 'adaptability', 'stress', 'integrity'],
        'thinking' => ['problemsolving', 'decisionmaking', 'negotiation', 'change', 'safety'],
    ];

    /** @var string[] Workplace indicators a choice can move. */
    public const KPIS = ['morale', 'motivation', 'productivity', 'trust', 'wellbeing', 'engagement', 'teamwork',
        'customersatisfaction', 'quality', 'safety'];

    /** @var int Indicator value every attempt starts at. */
    public const KPI_START = 50;

    /** @var int Largest change one choice can make to an indicator. */
    public const MAX_DELTA = 50;

    /** @var array Content languages: code => [endonym, right to left]. */
    public const LANGUAGES = [
        'en' => ['English', false],
        'es' => ['Español', false],
        'fr' => ['Français', false],
        'de' => ['Deutsch', false],
        'it' => ['Italiano', false],
        'pt' => ['Português', false],
        'nl' => ['Nederlands', false],
        'ru' => ['Русский', false],
        'pl' => ['Polski', false],
        'tr' => ['Türkçe', false],
        'ar' => ['العربية', true],
        'hi' => ['हिन्दी', false],
        'zh' => ['中文', false],
        'ja' => ['日本語', false],
        'ko' => ['한국어', false],
        'th' => ['ไทย', false],
        'vi' => ['Tiếng Việt', false],
        'id' => ['Bahasa Indonesia', false],
        'ms' => ['Bahasa Melayu', false],
        'fil' => ['Filipino', false],
    ];

    /** @var int Custom skills a teacher can add. */
    public const MAX_CUSTOM = 3;

    /**
     * Whether a code is a supported content language.
     *
     * @param string $code
     * @return bool
     */
    public static function is_language(string $code): bool {
        return isset(self::LANGUAGES[$code]);
    }

    /**
     * Language name in the current interface language.
     *
     * @param string $code
     * @return string
     */
    public static function language_name(string $code): string {
        return self::is_language($code) ? get_string('lang_' . $code, 'mod_aisoftskills') : $code;
    }

    /**
     * Language name in English (for AI prompts).
     *
     * @param string $code
     * @return string
     */
    public static function language_english(string $code): string {
        return self::is_language($code)
            ? get_string_manager()->get_string('lang_' . $code, 'mod_aisoftskills', null, 'en') : $code;
    }

    /**
     * Whether a content language is written right to left.
     *
     * @param string $code
     * @return bool
     */
    public static function is_rtl(string $code): bool {
        return (bool)(self::LANGUAGES[$code][1] ?? false);
    }

    /**
     * Content language menu (name and endonym).
     *
     * @return array
     */
    public static function language_options(): array {
        $options = [];
        foreach (self::LANGUAGES as $code => [$endonym]) {
            $name = self::language_name($code);
            $options[$code] = $name === $endonym ? $name : $name . ' (' . $endonym . ')';
        }
        return $options;
    }

    /**
     * Industry name for an activity (a catalogue industry or the teacher's own).
     *
     * @param string $key
     * @param string $custom
     * @return string
     */
    public static function industry_name(string $key, string $custom = ''): string {
        if ($key === 'custom') {
            return trim($custom) !== '' ? trim($custom) : get_string('industry_custom', 'mod_aisoftskills');
        }
        return in_array($key, self::INDUSTRIES, true) ? get_string('industry_' . $key, 'mod_aisoftskills') : $key;
    }

    /**
     * Industry menu.
     *
     * @return array
     */
    public static function industry_options(): array {
        $options = [];
        foreach (self::INDUSTRIES as $key) {
            $options[$key] = get_string('industry_' . $key, 'mod_aisoftskills');
        }
        $options['custom'] = get_string('industry_custom', 'mod_aisoftskills');
        return $options;
    }

    /**
     * Level menu.
     *
     * @return array
     */
    public static function level_options(): array {
        $options = [];
        foreach (self::LEVELS as $key) {
            $options[$key] = get_string('level_' . $key, 'mod_aisoftskills');
        }
        return $options;
    }

    /**
     * Whether a key is a catalogue skill.
     *
     * @param string $key
     * @return bool
     */
    public static function is_skill(string $key): bool {
        foreach (self::SKILLS as $keys) {
            if (in_array($key, $keys, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Skill name.
     *
     * @param string $key
     * @return string
     */
    public static function skill_name(string $key): string {
        return self::is_skill($key) ? get_string('skill_' . $key, 'mod_aisoftskills') : $key;
    }

    /**
     * Whether a key is a known indicator.
     *
     * @param string $key
     * @return bool
     */
    public static function is_kpi(string $key): bool {
        return in_array($key, self::KPIS, true);
    }

    /**
     * Indicator name.
     *
     * @param string $key
     * @return string
     */
    public static function kpi_name(string $key): string {
        return self::is_kpi($key) ? get_string('kpi_' . $key, 'mod_aisoftskills') : $key;
    }

    /**
     * Indicator menu.
     *
     * @return array
     */
    public static function kpi_options(): array {
        $options = [];
        foreach (self::KPIS as $key) {
            $options[$key] = self::kpi_name($key);
        }
        return $options;
    }
}
