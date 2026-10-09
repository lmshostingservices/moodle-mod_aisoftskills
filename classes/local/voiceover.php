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

use mod_aisoftskills\local\ai\lmslabs;
use moodle_url;
use stdClass;

/**
 * Voiceover: each scene's lead-in read aloud by LMS Labs (Google Chirp 3 HD), created once by the teacher in the
 * Voices step, stored in Moodle and played to learners.
 *
 * What is read, in order: the scene setting ("What is happening") and the question, by the narrator voice chosen in
 * the plugin settings; the lines of the conversation, each by its speaker's voice; and, when the learner presses the
 * play button on a response, that response in the learner's voice. Voices are the eight Google Chirp 3 HD voice
 * types used across the LMS Labs plugins (female: Aoede, Kore, Leda, Zephyr; male: Charon, Fenrir, Orus, Puck).
 *
 * The voices follow the picture: each name label says whether the person is female or male, and the label marked as
 * the learner gives the responses' voice. Nobody but the narrator uses the narrator's voice, and each named person
 * keeps the same voice in every scene.
 * Each clip is at most 200 characters (longer text is split at sentence ends), and each clip LMS Labs makes costs 2
 * LMS Labs credits. A clip is identified by its locale, voice and exact text, so it is made once and reused, and changing
 * a line only makes that line again.
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class voiceover {
    /** @var string File area for voiceover clips (item id: scene id). */
    public const FILEAREA = 'voiceover';

    /** @var string[] Headings read before their cards; the position is the heading's place in its shared clipRef. */
    public const HEADINGS = ['ctx_situation', 'ctx_action', 'ctx_context', 'fb_whathappened', 'fb_whyworks', 'fb_whyfalls'];

    /** @var int Longest clip LMS Labs bills as one, in characters. */
    public const MAX_CHARS = 200;

    /** @var int How long the voice catalogue is kept, in seconds. */
    public const CATALOG_TTL = 3600;

    /** @var int How long a failed catalogue fetch is remembered, in seconds. */
    public const CATALOG_RETRY = 300;

    /** @var string[] What the voiceover can read; each activity chooses (all by default). */
    public const PARTS = ['scenario', 'question', 'responses', 'consequence', 'why'];

    /** @var string[] The eight Google Chirp 3 HD voice types. */
    public const VOICETYPES = ['Kore', 'Charon', 'Aoede', 'Puck', 'Leda', 'Fenrir', 'Zephyr', 'Orus'];

    /** @var string[][] Voice types by how they sound, in the order they are given out. */
    public const GENDERS = ['f' => ['Aoede', 'Leda', 'Zephyr', 'Kore'], 'm' => ['Charon', 'Puck', 'Fenrir', 'Orus']];

    /** @var string Narrator voice type when the setting is not set. */
    public const DEFAULT_NARRATOR = 'Kore';

    /** @var string[] Preferred locale per content language, when LMS Labs offers several. */
    public const PREFERRED = ['en' => 'en-AU', 'es' => 'es-ES', 'fr' => 'fr-FR', 'pt' => 'pt-BR', 'zh' => 'cmn-CN',
        'ar' => 'ar-XA', 'de' => 'de-DE'];

    /**
     * Whether voiceover can be made on this site: switched on, and connected to LMS Labs.
     *
     * @return bool
     */
    public static function enabled(): bool {
        return (bool)get_config('mod_aisoftskills', 'aivoice') && credentials::find() !== null;
    }

    /**
     * Whether learners hear voiceover: switched on. Playing clips already made costs nothing and never calls LMS Labs.
     *
     * @return bool
     */
    public static function enabled_for_learners(): bool {
        return (bool)get_config('mod_aisoftskills', 'aivoice');
    }

    /**
     * Why no new clip may be asked for: the price LMS Labs publishes per clip when it differs from the approved price,
     * 0 when LMS Labs publishes no price (it is then unknown, and a clip sent without a ceiling could cost more), or
     * null when it publishes the approved price. The teacher is never charged a price they were not shown.
     *
     * @return int|null
     */
    public static function price_hold(): ?int {
        $price = (string)get_config('mod_aisoftskills', 'voiceprice');
        if ($price === '' || !ctype_digit($price)) {
            return 0;
        }
        return (int)$price !== lmslabs::VOICE_CREDITS ? (int)$price : null;
    }

    /** @var bool Whether {@see self::catalog()} may ask LMS Labs (false while building a learner's page). */
    protected static $fetch = true;

    /**
     * The LMS Labs voice catalogue, kept in Moodle's cache for an hour (a failure for five minutes).
     *
     * The last good catalogue is also kept in the plugin settings, so a learner's page never waits for LMS Labs.
     *
     * @return array locales
     */
    public static function catalog(): array {
        $cache = \cache::make('mod_aisoftskills', 'voicecatalog');
        // The key names the catalogue format, so a new format is read again, never a stale copy (1.4.4: the tariff;
        // 1.4.9: its price per clip).
        $key = sha1('tariff2|' . (string)(credentials::find()['siteid'] ?? ''));
        $cached = $cache->get($key);
        $kept = json_decode((string)get_config('mod_aisoftskills', 'voicecatalog'), true);
        $kept = is_array($kept) ? $kept : [];
        if (!self::$fetch) {
            return is_array($cached) && $cached['locales'] ? $cached['locales'] : $kept;
        }
        if (is_array($cached) && ($cached['time'] ?? 0) > time() - ($cached['locales'] ? self::CATALOG_TTL : self::CATALOG_RETRY)) {
            return $cached['locales'] ?: $kept;
        }
        lmslabs::$lasttariff = null;
        $locales = lmslabs::voice_catalog() ?? [];
        $cache->set($key, ['time' => time(), 'locales' => $locales]);
        if ($locales && $locales !== $kept) {
            set_config('voicecatalog', json_encode($locales, JSON_UNESCAPED_SLASHES), 'mod_aisoftskills');
        }
        if ($locales && lmslabs::$lasttariff !== null) {
            // The price LMS Labs publishes per clip: voiceover waits while it differs from the approved price.
            $price = lmslabs::$lasttariff['tts'] ?? null;
            set_config('voiceprice', is_int($price) ? (string)$price : '', 'mod_aisoftskills');
            // Free remakes are used only once LMS Labs says it takes the new fields (until then it refuses them).
            $remakes = !empty(lmslabs::$lasttariff['clipRefSupported']) && !empty(lmslabs::$lasttariff['maxCreditsRequired']);
            set_config('voiceremakes', $remakes ? json_encode([
                'on' => 1,
                'limit' => (int)(lmslabs::$lasttariff['ttsRemakeLimit'] ?? 10),
                'days' => (int)(lmslabs::$lasttariff['ttsRemakeWindowDays'] ?? 30),
            ]) : '', 'mod_aisoftskills');
        }
        return $locales ?: $kept;
    }

    /**
     * The free remake rule when LMS Labs supports it (clips are then sent with clipRef and maxCredits).
     *
     * @return array|null {limit, days}, or null when LMS Labs has not said it supports remakes
     */
    public static function remakes(): ?array {
        $rule = json_decode((string)get_config('mod_aisoftskills', 'voiceremakes'), true);
        return is_array($rule) && !empty($rule['on'])
            ? ['limit' => (int)($rule['limit'] ?? 0), 'days' => (int)($rule['days'] ?? 0)] : null;
    }

    /**
     * The stable reference of one place in a scene, sent with each clip so LMS Labs can make a clip again for free
     * after an edit. It never contains text or voice, and its serialisation must never change: a JSON array of the
     * component, this site, the activity, the scene, the part, the line (the dialogue line's stable id, the response
     * id, or -1 for none) and the clip number within that text.
     *
     * @param stdClass $instance
     * @param int $sceneid
     * @param array $segment from {@see self::segments()}
     * @return string 64 lower-case hex characters
     */
    public static function clipref(stdClass $instance, int $sceneid, array $segment): string {
        // A shared heading belongs to the activity (item 0), not to one scene.
        $sceneid = isset($segment['item']) ? (int)$segment['item'] : $sceneid;
        return hash('sha256', json_encode(['mod_aisoftskills', (string)get_site_identifier(), (int)$instance->id, $sceneid,
            (string)$segment['part'], (int)($segment['ref'] ?? $segment['line']), (int)($segment['clip'] ?? 0)]));
    }

    /**
     * The voices for a learner's page: from the kept catalogue only, never asking LMS Labs.
     *
     * @param stdClass $instance
     * @return array as {@see self::config()}
     */
    public static function learner_config(stdClass $instance): array {
        self::$fetch = false;
        try {
            return self::config($instance);
        } finally {
            self::$fetch = true;
        }
    }

    /**
     * The locales LMS Labs offers for a content language.
     *
     * @param string $lang content language code, such as en or zh
     * @return string[]
     */
    public static function locales(string $lang): array {
        $codes = $lang === 'zh' ? ['zh', 'cmn', 'yue'] : [$lang];
        $out = [];
        foreach (self::catalog() as $entry) {
            $locale = (string)($entry['locale'] ?? '');
            if (in_array(strtok($locale, '-'), $codes, true) && self::voices_in($entry)) {
                $out[] = $locale;
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * Chirp 3 HD voice names of a catalogue entry.
     *
     * @param array $entry
     * @return string[]
     */
    protected static function voices_in(array $entry): array {
        $code = (string)($entry['ttsLanguageCode'] ?? $entry['locale'] ?? '');
        $names = [];
        foreach ((array)($entry['voices'] ?? []) as $voice) {
            $name = is_array($voice) ? (string)($voice['name'] ?? '') : '';
            if (str_starts_with($name, $code . '-Chirp3-HD-') && preg_match('/^[a-zA-Z-]+-Chirp3-HD-[a-zA-Z]+$/', $name)) {
                $names[] = $name;
            }
        }
        return $names;
    }

    /**
     * Voice names for one locale.
     *
     * @param string $locale
     * @return string[]
     */
    public static function voices(string $locale): array {
        foreach (self::catalog() as $entry) {
            if (($entry['locale'] ?? '') === $locale) {
                return self::voices_in($entry);
            }
        }
        return [];
    }

    /**
     * The activity's voices.
     *
     * The narrator is the voice type chosen in the plugin settings. Everyone else gets a voice that sounds as their
     * name label says (female or male) and is never the narrator's: the learner the first such voice, and each named
     * person, in order of first appearance across the scenes, the next one, so the same person sounds the same in
     * every scene. Only voices LMS Labs offers for the locale are used.
     *
     * @param stdClass $instance
     * @return array {locale, narrator, types: type => voice name, people: person => voice, learner: f/m/'' => voice};
     *     locale '' when LMS Labs has no voices for the content language
     */
    public static function config(stdClass $instance): array {
        $none = ['locale' => '', 'narrator' => '', 'types' => [], 'people' => [], 'learner' => []];
        $locales = self::locales((string)$instance->contentlang);
        $preferred = self::PREFERRED[(string)$instance->contentlang] ?? '';
        $locale = in_array($preferred, $locales, true) ? $preferred : ($locales[0] ?? '');
        $types = [];
        foreach ($locale !== '' ? self::voices($locale) : [] as $name) {
            $types[preg_replace('/^.*-Chirp3-HD-/', '', $name)] = $name;
        }
        $types = array_intersect_key($types, array_flip(self::VOICETYPES));
        if (!$types) {
            return $none;
        }
        $setting = (string)get_config('mod_aisoftskills', 'narratorvoice') ?: self::DEFAULT_NARRATOR;
        $narrator = isset($types[$setting]) ? $setting : array_key_first($types);
        // The voices each kind of person may get, never the narrator's (unless LMS Labs offers nothing else).
        $pool = [];
        foreach (['f', 'm'] as $g) {
            $pool[$g] = array_values(array_filter(self::GENDERS[$g], fn($t) => isset($types[$t]) && $t !== $narrator));
        }
        $pool[''] = array_values(array_filter(self::VOICETYPES, fn($t) => isset($types[$t]) && $t !== $narrator));
        foreach ($pool as $g => $list) {
            $pool[$g] = $list ?: [$narrator];
        }
        // Voices the teacher chose for people on the name labels come first; the learner's default voices avoid them.
        $chosen = array_filter(self::chosen_voices($instance), fn($t) => isset($types[$t]) && $t !== $narrator);
        $free = fn($list) => array_values(array_diff($list, $chosen)) ?: $list;
        $learner = ['f' => $types[$free($pool['f'])[0]], 'm' => $types[$free($pool['m'])[0]],
            '' => $types[$free($pool[''])[0]]];
        // Named people keep the voice they were first given, so clips already made stay valid when people are added
        // or scenes are moved; a new person gets the least used voice of their kind, never the narrator's.
        $stored = json_decode((string)($instance->voicemap ?? ''), true);
        $stored = is_array($stored) ? $stored : [];
        $kept = $stored;
        $used = array_fill_keys(array_keys($types), 0);
        foreach ($learner as $voice) {
            $used[preg_replace('/^.*-Chirp3-HD-/', '', $voice)]++;
        }
        // A voice chosen for the learner on a label is kept away from everyone else.
        $learnerchosen = array_values(array_filter(
            self::chosen_voices($instance, true),
            fn($t) => isset($types[$t]) && $t !== $narrator
        ));
        foreach ($learnerchosen as $type) {
            $used[$type] += 100;
        }
        $everyone = self::people($instance);
        $people = [];
        foreach ($chosen as $person => $type) {
            if (isset($everyone[$person])) {
                $used[$type]++;
                $people[$person] = $types[$type];
                $kept[$person] = $type;
            }
        }
        // First everyone who keeps their voice, so a newcomer never takes a voice that is in use.
        foreach ($everyone as $person => $gender) {
            if (isset($people[$person])) {
                continue;
            }
            $type = (string)($kept[$person] ?? '');
            $fits = isset($types[$type]) && $type !== $narrator && !in_array($types[$type], $learner, true)
                && !in_array($type, $learnerchosen, true)
                && !in_array($type, $chosen, true)
                && ($gender === '' || in_array($type, self::GENDERS[$gender], true));
            if ($fits) {
                $used[$type]++;
                $people[$person] = $types[$type];
            }
        }
        foreach ($everyone as $person => $gender) {
            if (isset($people[$person])) {
                continue;
            }
            $list = $pool[$gender];
            $order = array_flip($list);
            usort($list, fn($a, $b) => [$used[$a], $order[$a]] <=> [$used[$b], $order[$b]]);
            $type = $list[0];
            $used[$type]++;
            $people[$person] = $types[$type];
            $kept[$person] = $type;
        }
        // In order of first appearance.
        $people = array_replace(array_intersect_key($everyone, $people), $people);
        if (self::$fetch && !empty($instance->id) && $kept !== $stored) {
            // Remembered from the teacher's pages only: a learner's page never writes.
            global $DB;
            $instance->voicemap = json_encode($kept, JSON_UNESCAPED_UNICODE);
            $DB->set_field('aisoftskills', 'voicemap', $instance->voicemap, ['id' => $instance->id]);
        }
        return ['locale' => $locale, 'narrator' => $types[$narrator], 'types' => $types, 'people' => $people,
            'learner' => $learner, 'lang' => (string)$instance->contentlang];
    }

    /**
     * The voice type each saved name label of a scene really gets, in label order ('' when not known yet).
     *
     * @param stdClass $scene
     * @param array $config from {@see self::config()}
     * @return string[]
     */
    public static function label_voices(stdClass $scene, array $config): array {
        $out = [];
        $type = fn($name) => (string)preg_replace('/^.*-Chirp3-HD-/', '', $name);
        foreach (labels::get($scene) as $label) {
            if ($label['you']) {
                $out[] = $type(self::learner_voice($scene, $config));
            } else {
                $out[] = $type($config['people'][labels::person($label['text'])] ?? '');
            }
        }
        return $out;
    }

    /**
     * The voice types the teacher chose on name labels, by person (the first choice for a person wins).
     *
     * @param stdClass $instance
     * @param bool $learner true for the voices chosen for the learner instead (scene id => voice type)
     * @return string[] person => voice type
     */
    public static function chosen_voices(stdClass $instance, bool $learner = false): array {
        $out = [];
        foreach (manager::get_scenes((int)$instance->id) as $scene) {
            foreach (labels::get($scene) as $label) {
                if ($learner) {
                    if ($label['you'] && $label['voice'] !== '') {
                        $out[(int)$scene->id] = $label['voice'];
                    }
                    continue;
                }
                $person = labels::person($label['text']);
                if (!$label['you'] && $person !== '' && $label['voice'] !== '' && !isset($out[$person])) {
                    $out[$person] = $label['voice'];
                }
            }
        }
        return $out;
    }

    /**
     * Everyone in the activity apart from the learner, in order of first appearance: name labels first, then the
     * speakers of each conversation, with how they sound (f, m, or '' when nobody said).
     *
     * @param stdClass $instance
     * @return string[] person => gender
     */
    public static function people(stdClass $instance): array {
        $out = [];
        foreach (manager::get_scenes((int)$instance->id) as $scene) {
            foreach (labels::get($scene) as $label) {
                $person = labels::person($label['text']);
                if (!$label['you'] && $person !== '' && (!isset($out[$person]) || $out[$person] === '')) {
                    $out[$person] = $label['gender'];
                }
            }
            foreach (manager::dialogue($scene->script) as $line) {
                $person = labels::person((string)$line['speaker']);
                if ($person !== '' && !isset($out[$person])) {
                    $out[$person] = labels::gender((string)$scene->context, trim((string)$line['speaker']));
                }
            }
        }
        return $out;
    }

    /**
     * The learner's voice in one scene: from the label marked as the learner in that scene's picture.
     *
     * @param stdClass $scene
     * @param array $config
     * @return string
     */
    public static function learner_voice(stdClass $scene, array $config): string {
        foreach (labels::get($scene) as $label) {
            if ($label['you']) {
                $chosen = $config['types'][$label['voice']] ?? '';
                if ($chosen !== '' && $chosen !== $config['narrator']) {
                    return $chosen;
                }
                return $config['learner'][$label['gender']];
            }
        }
        return $config['learner'][''];
    }

    /**
     * Splits text into clips of at most 200 characters, at sentence ends where possible, then at spaces.
     *
     * @param string $text
     * @return string[]
     */
    public static function chunks(string $text): array {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if ($text === '') {
            return [];
        }
        if (\core_text::strlen($text) <= self::MAX_CHARS) {
            return [$text];
        }
        $out = [];
        $current = '';
        $pieces = preg_split('/(?<=[.!?…。！？])\s*/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($pieces as $piece) {
            while (\core_text::strlen($piece) > self::MAX_CHARS) {
                // One very long sentence: cut at the last space that fits.
                $cut = \core_text::substr($piece, 0, self::MAX_CHARS);
                $space = \core_text::strrpos($cut, ' ');
                $cut = $space ? \core_text::substr($cut, 0, $space) : $cut;
                if ($current !== '') {
                    $out[] = $current;
                    $current = '';
                }
                $out[] = trim($cut);
                $piece = trim(\core_text::substr($piece, \core_text::strlen($cut)));
            }
            $joined = $current === '' ? $piece : $current . ' ' . $piece;
            if (\core_text::strlen($joined) <= self::MAX_CHARS) {
                $current = $joined;
            } else {
                $out[] = $current;
                $current = $piece;
            }
        }
        if ($current !== '') {
            $out[] = $current;
        }
        return array_values(array_filter($out, fn($c) => $c !== ''));
    }

    /**
     * What an activity's voiceover reads.
     *
     * @param stdClass $instance
     * @return string[] of {@see self::PARTS}
     */
    public static function parts(stdClass $instance): array {
        $stored = isset($instance->voiceparts) ? explode(',', (string)$instance->voiceparts) : self::PARTS;
        return array_values(array_intersect(self::PARTS, $stored));
    }

    /**
     * The clips of one scene, in playing order, then the clips of each response.
     *
     * @param stdClass $scene
     * @param array $config from {@see self::config()}
     * @param stdClass[] $options the scene's responses (read in the learner's voice)
     * @param string[]|null $parts what to read (default: all)
     * @return array list of {index, text, voice, locale, part (context, line, question, option, consequence or reason),
     *     line (dialogue index, option id, or -1), clip (number of the clip within that text, from 0)}
     */
    public static function segments(stdClass $scene, array $config, array $options = [], ?array $parts = null): array {
        if ($config['locale'] === '') {
            return [];
        }
        $parts = $parts ?? self::PARTS;
        $out = [];
        $add = function (string $text, string $voice, string $part, int $line, ?int $ref = null) use (&$out): void {
            foreach (self::chunks($text) as $clip => $chunk) {
                $out[] = ['text' => $chunk, 'voice' => $voice, 'part' => $part, 'line' => $line, 'clip' => $clip,
                    'ref' => $ref ?? $line];
            }
        };
        // A heading ("The situation", "What happened") is read before its card. Its clip is the same in every scene,
        // so it is made once for the activity (kept with item 0) and shared: it costs one clip, not one per scene.
        $lang = (string)($config['lang'] ?? 'en');
        $heading = function (string $key, string $mark, int $line, string $group) use (&$out, $config, $lang): void {
            $text = get_string_manager()->get_string($key, 'mod_aisoftskills', null, $lang);
            $out[] = ['text' => $text, 'voice' => $config['narrator'], 'part' => 'heading', 'line' => $line, 'clip' => 0,
                'ref' => (int)array_search($key, self::HEADINGS, true), 'item' => 0, 'mark' => $mark, 'group' => $group];
        };
        if (in_array('scenario', $parts, true)) {
            // What is happening is read card by card, each after its heading, as the learner sees it.
            foreach (learning::context_cards((string)$scene->context, $lang) as $c => $card) {
                $heading('ctx_' . $card['kind'], 'context', $c, 'scene');
                $add(implode(' ', array_column($card['lines'], 'text')), $config['narrator'], 'context', $c);
            }
            foreach (manager::dialogue($scene->script) as $i => $line) {
                $person = labels::person((string)$line['speaker']);
                // Dialogue clips are placed by the line's stable id, so removing a line never moves another's place.
                $add(
                    (string)$line['line'],
                    $config['people'][$person] ?? $config['learner'][''],
                    'line',
                    $i,
                    (int)($line['id'] ?? $i + 1)
                );
            }
        }
        if (in_array('question', $parts, true)) {
            // The default question is read in the activity's language, so every viewer finds the same clip.
            $question = trim((string)$scene->question) !== '' ? (string)$scene->question
                : get_string_manager()->get_string('whatdoyousay', 'mod_aisoftskills', null, $config['lang'] ?? 'en');
            $add($question, $config['narrator'], 'question', -1);
        }
        $learner = self::learner_voice($scene, $config);
        foreach ($options as $option) {
            if (in_array('responses', $parts, true)) {
                // The responses are what the learner says, so they are read in the learner's voice.
                $add((string)$option->text, $learner, 'option', (int)$option->id);
            }
            if (in_array('consequence', $parts, true) && trim((string)$option->consequence) !== '') {
                $heading('fb_whathappened', 'consequence', (int)$option->id, 'feedback:' . (int)$option->id);
                $add((string)$option->consequence, $config['narrator'], 'consequence', (int)$option->id);
            }
            if (in_array('why', $parts, true) && trim((string)$option->reason) !== '') {
                $why = !empty($option->best) ? 'fb_whyworks' : 'fb_whyfalls';
                $heading($why, 'reason', (int)$option->id, 'feedback:' . (int)$option->id);
                $add((string)$option->reason, $config['narrator'], 'reason', (int)$option->id);
            }
        }
        foreach (array_keys($out) as $i) {
            $out[$i]['index'] = $i;
            $out[$i]['locale'] = $config['locale'];
        }
        return $out;
    }

    /**
     * The request body for one clip, exactly as sent.
     *
     * @param array $segment
     * @param string|null $clipref the clip's place ({@see self::clipref()}), when LMS Labs supports free remakes
     * @param int|null $maxcredits the most the teacher confirmed for this clip: 0 or the price per clip
     * @return array
     */
    public static function body(array $segment, ?string $clipref = null, ?int $maxcredits = null): array {
        $body = ['text' => $segment['text'], 'locale' => $segment['locale'], 'speed' => 'normal', 'voice' => $segment['voice']];
        if ($clipref !== null && $maxcredits !== null) {
            // Both or neither: LMS Labs refuses one without the other.
            $body += ['clipRef' => $clipref, 'maxCredits' => $maxcredits];
        }
        return $body;
    }

    /**
     * The file name of a clip: a hash of everything that changes the audio.
     *
     * @param array $segment
     * @return string
     */
    public static function filename(array $segment): string {
        return 'clip-' . sha1($segment['locale'] . '|' . $segment['voice'] . '|' . $segment['text']) . '.mp3';
    }

    /**
     * The URL of a clip that has been made, or ''.
     *
     * @param \context $context
     * @param int $sceneid
     * @param array $segment
     * @return string
     */
    public static function url(\context $context, int $sceneid, array $segment): string {
        $sceneid = isset($segment['item']) ? (int)$segment['item'] : $sceneid;
        $filename = self::filename($segment);
        if (!get_file_storage()->file_exists($context->id, 'mod_aisoftskills', self::FILEAREA, $sceneid, '/', $filename)) {
            return '';
        }
        return moodle_url::make_pluginfile_url($context->id, 'mod_aisoftskills', self::FILEAREA, $sceneid, '/', $filename)
            ->out(false);
    }

    /**
     * The clips still to make for an activity, as "sceneid:index".
     *
     * @param stdClass $instance
     * @param \context $context
     * @return string[]
     */
    public static function missing(stdClass $instance, \context $context): array {
        $config = self::config($instance);
        $scenes = manager::get_scenes((int)$instance->id);
        $options = manager::get_options(array_keys($scenes));
        $out = [];
        $shared = [];
        foreach ($scenes as $scene) {
            foreach (self::segments($scene, $config, $options[$scene->id] ?? [], self::parts($instance)) as $segment) {
                if (self::url($context, (int)$scene->id, $segment) === '') {
                    // A shared heading is made once, with the first scene that reads it.
                    if (isset($segment['item'])) {
                        $name = self::filename($segment);
                        if (isset($shared[$name])) {
                            continue;
                        }
                        $shared[$name] = true;
                    }
                    $out[] = $scene->id . ':' . $segment['index'];
                }
            }
        }
        return $out;
    }

    /**
     * Why a scene's voiceover does not play, for the teacher trying the activity, or '' when all of it plays.
     *
     * @param stdClass $instance
     * @param \context $context
     * @param stdClass $scene
     * @param array $options the scene's responses
     * @param array|null $config the learner voice settings, null when voiceover is turned off
     * @return string
     */
    public static function silent_reason(
        stdClass $instance,
        \context $context,
        stdClass $scene,
        array $options,
        ?array $config
    ): string {
        if ($config === null) {
            return get_string('voicenote_off', 'mod_aisoftskills');
        }
        if ($config['locale'] === '') {
            return get_string('voicenote_nocatalogue', 'mod_aisoftskills');
        }
        $missing = 0;
        foreach (self::segments($scene, $config, $options, self::parts($instance)) as $segment) {
            if (self::url($context, (int)$scene->id, $segment) === '') {
                $missing++;
            }
        }
        return $missing ? get_string('voicenote_missing', 'mod_aisoftskills', $missing) : '';
    }

    /**
     * What learners hear for a scene. A part plays only when all of its clips have been made: the scene (setting,
     * conversation and question) as one playlist, each response on its own, and the feedback of each response (what
     * happened, then why).
     *
     * @param stdClass $instance
     * @param \context $context
     * @param stdClass $scene
     * @param stdClass[] $options
     * @param array|null $config
     * @return array {scene: list of {url, part, line}, options: option id => urls, feedback: option id => list of
     *     {url, part}}
     */
    public static function playlist(
        stdClass $instance,
        \context $context,
        stdClass $scene,
        array $options,
        ?array $config = null
    ): array {
        $config = $config ?? self::config($instance);
        $groups = [];
        foreach (self::segments($scene, $config, $options, self::parts($instance)) as $segment) {
            $part = $segment['part'];
            $key = $segment['group'] ?? (in_array($part, ['context', 'line', 'question'], true) ? 'scene'
                : ($part === 'option' ? 'option:' . $segment['line'] : 'feedback:' . $segment['line']));
            // A heading lights up the card it introduces.
            $groups[$key][] = ['url' => self::url($context, (int)$scene->id, $segment), 'part' => $segment['mark'] ?? $part,
                'line' => $segment['line']];
        }
        $out = ['scene' => [], 'options' => [], 'feedback' => []];
        foreach ($groups as $key => $clips) {
            if (in_array('', array_column($clips, 'url'), true)) {
                // Not every clip has been made yet: this part stays silent rather than half read.
                continue;
            }
            [$kind, $id] = array_pad(explode(':', $key), 2, 0);
            if ($kind === 'scene') {
                $out['scene'] = $clips;
            } else if ($kind === 'option') {
                $out['options'][(int)$id] = array_column($clips, 'url');
            } else {
                $out['feedback'][(int)$id] = array_map(fn($c) => ['url' => $c['url'], 'part' => $c['part']], $clips);
            }
        }
        return $out;
    }

    /**
     * Saves a delivered clip.
     *
     * @param \context $context
     * @param stdClass $instance
     * @param stdClass $scene
     * @param array $segment
     * @param string $mp3
     */
    public static function save(\context $context, stdClass $instance, stdClass $scene, array $segment, string $mp3): void {
        // Paid clips are never deleted here: a clip that is not used now (a part switched off for a while, an edit
        // undone) is used again for free. They are deleted with the scene.
        $fs = get_file_storage();
        // A shared heading is kept for the whole activity (item 0); a delivered request body names only its text.
        $itemid = isset($segment['item']) ? (int)$segment['item'] : (int)$scene->id;
        if (!isset($segment['item']) && self::is_heading((string)($segment['text'] ?? ''), (string)$instance->contentlang)) {
            $itemid = 0;
        }
        if (!$fs->file_exists($context->id, 'mod_aisoftskills', self::FILEAREA, $itemid, '/', self::filename($segment))) {
            $fs->create_file_from_string(['contextid' => $context->id, 'component' => 'mod_aisoftskills',
                'filearea' => self::FILEAREA, 'itemid' => $itemid, 'filepath' => '/',
                'filename' => self::filename($segment), 'mimetype' => 'audio/mpeg'], $mp3);
        }
    }

    /**
     * Whether a clip's text is one of the shared headings ("The situation", "What happened") in the activity's language.
     *
     * @param string $text
     * @param string $lang
     * @return bool
     */
    public static function is_heading(string $text, string $lang): bool {
        foreach (self::HEADINGS as $key) {
            if (get_string_manager()->get_string($key, 'mod_aisoftskills', null, $lang ?: 'en') === $text) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether bytes are MP3 audio (an ID3 tag or an MPEG audio frame) of at most 2 MB.
     *
     * @param string $bytes
     * @return bool
     */
    public static function is_mp3(string $bytes): bool {
        if ($bytes === '' || strlen($bytes) > 2 * 1048576) {
            return false;
        }
        $frame = strlen($bytes) > 1 && ord($bytes[0]) === 255 && (ord($bytes[1]) & 0xe0) === 0xe0;
        return substr($bytes, 0, 3) === 'ID3' || $frame;
    }
}
