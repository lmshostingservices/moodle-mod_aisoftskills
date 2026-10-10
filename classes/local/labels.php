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

    /** @var string[] Capitalised words that are never a person's name (lower case). */
    private const COMMON = ['a', 'an', 'the', 'at', 'in', 'on', 'by', 'as', 'of', 'to', 'for', 'from', 'with', 'after',
        'before', 'during', 'when', 'while', 'if', 'but', 'and', 'or', 'so', 'yet', 'then', 'now', 'today', 'tonight',
        'later', 'meanwhile', 'however', 'although', 'because', 'since', 'until', 'once', 'every', 'each', 'all', 'both',
        'some', 'many', 'most', 'no', 'not', 'one', 'two', 'three', 'this', 'that', 'these', 'those', 'there', 'here',
        'it', 'its', 'i', 'you', 'your', 'we', 'our', 'they', 'their', 'he', 'his', 'him', 'she', 'her', 'who', 'what',
        'which', 'where', 'why', 'how', 'yes', 'please', 'thanks', 'sorry', 'hello', 'hi', 'okay', 'ok', 'monday',
        'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday', 'january', 'february', 'march', 'april',
        'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december', 'english', 'christmas',
        'easter', 'nurse', 'doctor', 'manager', 'supervisor', 'team', 'staff', 'customer', 'guest', 'patient',
        'resident', 'client', 'learner', 'scene', 'scenario', 'situation', 'emergency', 'department', 'everyone',
        'everybody', 'someone', 'somebody', 'anyone', 'anybody', 'nobody', 'none', 'nothing', 'something', 'everything',
        'just', 'still', 'also', 'even', 'only', 'again', 'soon', 'suddenly', 'finally', 'instead', 'tomorrow',
        'yesterday'];

    /** @var string[] Jobs and relationships that are never a person's name on a label (lower case). */
    private const ROLES = ['nurse', 'doctor', 'pharmacist', 'physio', 'physiotherapist', 'assistant', 'manager',
        'supervisor', 'coordinator', 'daughter', 'son', 'wife', 'husband', 'mother', 'father', 'sister', 'brother',
        'partner', 'guest', 'customer', 'patient', 'resident', 'client', 'chef', 'cook', 'bartender', 'waiter',
        'waitress', 'receptionist', 'cleaner', 'director', 'officer', 'worker', 'carer', 'caregiver', 'aide',
        'technician', 'therapist', 'surgeon', 'midwife', 'paramedic', 'porter', 'student', 'trainee', 'colleague',
        'lead', 'leader', 'administrator', 'clerk', 'agent', 'owner', 'host', 'server', 'driver', 'guard', 'teacher',
        'tutor', 'consultant', 'registrar', 'intern', 'specialist', 'advisor', 'adviser', 'representative', 'apprentice',
        'volunteer', 'visitor', 'relative', 'friend', 'boss', 'employee', 'staff', 'member'];

    /** @var string[] Words that make a capitalised run a place or organisation, not a person. */
    private const PLACES = ['Hospital', 'Department', 'Ward', 'Unit', 'Clinic', 'Centre', 'Center', 'Street', 'Road',
        'Avenue', 'Hotel', 'Bar', 'Restaurant', 'Cafe', 'Café', 'School', 'College', 'University', 'Bank', 'Group',
        'Company', 'Ltd', 'Inc', 'Council', 'Services', 'Service', 'Team', 'Office', 'Store', 'Shop', 'Airport',
        'Station', 'Park', 'House', 'Hall', 'Room', 'Building', 'Australia', 'Perth', 'Sydney', 'Melbourne'];

    /** @var float Height of the default row (percent from the top: roughly chest height in a half-body shot). */
    public const DEFAULT_Y = 55.0;

    /**
     * Cleans labels from the page or the database: text, and a position kept inside the picture.
     *
     * @param mixed $labels list of {text, x, y, gender (f, m or ''), voice (a voice type or ''), you}
     * @return array list of {text, x, y, gender, voice, you}
     */
    public static function clean($labels): array {
        $out = [];
        foreach (is_array($labels) ? $labels : [] as $label) {
            if (!is_array($label) && !is_object($label)) {
                continue;
            }
            $label = (array)$label;
            $text = self::tidy(manager::clean_line((string)($label['text'] ?? ''), self::MAX_TEXT));
            if ($text === '') {
                continue;
            }
            $pos = fn($v) => round(max(2.0, min(98.0, is_numeric($v) ? (float)$v : 50.0)), 1);
            $gender = in_array($label['gender'] ?? '', ['f', 'm'], true) ? $label['gender'] : '';
            // A voice the teacher chose for this person; it decides how they sound.
            $voice = in_array($label['voice'] ?? '', voiceover::VOICETYPES, true) ? $label['voice'] : '';
            if ($voice !== '') {
                $gender = in_array($voice, voiceover::GENDERS['f'], true) ? 'f' : 'm';
            }
            // Only one label can be the learner.
            $you = !empty($label['you']) && !in_array(true, array_column($out, 'you'), true);
            $out[] = ['text' => $text, 'x' => $pos($label['x'] ?? 50), 'y' => $pos($label['y'] ?? self::DEFAULT_Y),
                'gender' => $gender, 'voice' => $voice, 'you' => $you];
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
        // A person has one voice in the whole activity: a voice chosen here is given to their labels in other scenes.
        $voices = [];
        foreach ($clean as $label) {
            if (!$label['you'] && $label['voice'] !== '' && self::person($label['text']) !== '') {
                $voices[self::person($label['text'])] = [$label['voice'], $label['gender']];
            }
        }
        if ($voices && !empty($scene->aisoftskillsid)) {
            $others = $DB->get_records_select(
                'aisoftskills_scene',
                'aisoftskillsid = :aid AND id <> :id',
                ['aid' => $scene->aisoftskillsid, 'id' => $scene->id],
                '',
                'id, labels'
            );
            foreach ($others as $other) {
                $list = self::get($other);
                $changed = false;
                foreach ($list as $k => $label) {
                    $person = self::person($label['text']);
                    if (!$label['you'] && isset($voices[$person]) && $label['voice'] !== $voices[$person][0]) {
                        [$list[$k]['voice'], $list[$k]['gender']] = $voices[$person];
                        $changed = true;
                    }
                }
                if ($changed) {
                    $DB->set_field(
                        'aisoftskills_scene',
                        'labels',
                        json_encode($list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ['id' => $other->id]
                    );
                }
            }
        }
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
        foreach (manager::dialogue((string)($scene->script ?? '')) as $line) {
            $name = trim((string)$line['speaker']);
            if ($name !== '' && !in_array($name, $names, true)) {
                $names[] = $name;
            }
        }
        $script = json_decode((string)($scene->script ?? ''), true);
        foreach ((array)($script['characters'] ?? []) as $name) {
            $name = is_string($name) ? trim($name) : '';
            if ($name !== '' && !in_array($name, $names, true)) {
                $names[] = $name;
            }
        }
        $text = (string)($scene->context ?? '');
        foreach (self::named($text) as $name) {
            if (!in_array($name, $names, true)) {
                $names[] = $name;
            }
        }
        $people = [];
        foreach ($names as $name) {
            $role = self::role($text, $name);
            $gender = self::gender($text, $name);
            $people[] = ['text' => $role !== '' ? $name . ' - ' . $role : $name,
                'gender' => $gender !== '' ? $gender : self::picture_gender($scene, $name), 'you' => false];
        }
        // The learner: the person who answers, such as "You, the shift supervisor".
        $speaker = trim((string)($scene->speaker ?? ''));
        $role = learning::role_name($speaker, 'en');
        $you = $role !== '' ? get_string('label_you', 'mod_aisoftskills') . ' - '
            . \core_text::strtoupper(\core_text::substr($role, 0, 1)) . \core_text::substr($role, 1) : $speaker;
        array_unshift($people, ['text' => $you !== '' ? $you : get_string('label_you', 'mod_aisoftskills'),
            'gender' => $role !== '' ? self::picture_gender($scene, $role) : '', 'you' => true]);
        $people = array_slice($people, 0, self::MAX);
        $out = [];
        foreach ($people as $i => $person) {
            $out[] = $person + ['x' => round(100 * ($i + 1) / (count($people) + 1), 1), 'y' => self::DEFAULT_Y];
        }
        return self::clean($out);
    }

    /**
     * Repairs labels suggested by earlier versions: a role that is really an adverb or verb ("Priya - Quietly
     * mentions" becomes "Priya") and a title without its name ("Mrs - Resident" becomes "Mrs Tanaka - Resident" when
     * the scene text names Mrs Tanaka). Positions, voices and the learner are kept.
     *
     * @param stdClass $scene
     * @return bool whether anything changed (and was saved)
     */
    public static function repair(stdClass $scene): bool {
        global $DB;
        $list = self::get($scene);
        $text = (string)($scene->context ?? '');
        $changed = false;
        foreach ($list as $k => $label) {
            if ($label['you']) {
                continue;
            }
            [$name, $role] = self::split($label['text']);
            if (
                preg_match('/^(Mrs|Mr|Ms|Miss|Mx|Dr|Prof)\.?$/u', $name)
                    && preg_match('/\b' . preg_quote($name, '/') . '\.?\s+(\p{Lu}\p{Ll}+)\b/u', $text, $m)
            ) {
                $name .= ' ' . $m[1];
            }
            if ($role !== '' && !preg_match('/\p{Lu}{2}/u', $role)) {
                $words = explode(' ', \core_text::strtolower($role));
                $fixed = self::role(implode(' ', $words) . ' ' . $name, $name);
                $role = $fixed;
            }
            $new = $role !== '' ? $name . ' - ' . $role : $name;
            if ($new !== $label['text']) {
                $list[$k]['text'] = $new;
                if ($list[$k]['gender'] === '' && $list[$k]['voice'] === '') {
                    $list[$k]['gender'] = self::gender($text, $name);
                }
                $changed = true;
            }
        }
        if ($changed) {
            $DB->set_field('aisoftskills_scene', 'labels', json_encode(
                self::clean($list),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ), ['id' => $scene->id]);
        }
        return $changed;
    }

    /**
     * Every person the scene text names, in the order they first appear: "nurse Priya", "RN Fatima", "Dr Reeves",
     * "Priya Sharma" and "Fatima says ..." at the start of a sentence.
     *
     * A capitalised word is a name when it is not a common word (such as "At", "She" or "Monday"), is not part of a
     * place or organisation ("Royal Perth Hospital"), and, at the start of a sentence, is followed by a verb or is
     * named elsewhere too. A shorter form of a name found in full ("Reeves" in "Dr Reeves") is not listed twice.
     *
     * @param string $text
     * @return string[]
     */
    public static function named(string $text): array {
        $title = '(?:Mrs|Mr|Ms|Miss|Mx|Dr|Prof)\.?';
        $word = "\\p{Lu}\\p{Ll}+(?:['’-]\\p{Lu}?\\p{Ll}+)?";
        preg_match_all("/(?<![\\p{L}'’-])((?:$title\\s+)?$word(?:\\s+$word)*)/u", $text, $m, PREG_OFFSET_CAPTURE);
        $found = [];
        foreach ($m[1] as [$run, $offset]) {
            $words = preg_split('/\\s+/u', $run);
            $titled = (bool)preg_match("/^$title$/u", $words[0]);
            $body = $titled ? array_slice($words, 1) : $words;
            // A place or organisation, or a run of three or more capitalised words, is not a person.
            if (!$body || count($body) > 2 || array_intersect($body, self::PLACES)) {
                continue;
            }
            // Common words at the start of a run ("At", "When Priya") are dropped.
            while (!$titled && $body && in_array(\core_text::strtolower($body[0]), self::COMMON, true)) {
                array_shift($body);
                $offset = -1;
            }
            if (!$body || in_array(\core_text::strtolower(end($body)), self::COMMON, true)) {
                continue;
            }
            $name = ($titled ? $words[0] . ' ' : '') . implode(' ', $body);
            $before = $offset > 0 ? rtrim(substr($text, 0, $offset)) : '';
            $start = $offset === 0 || ($offset > 0 && ($before === '' || preg_match('/[.!?:"“”]$/u', $before)));
            if ($start && !$titled) {
                $after = substr($text, $offset + strlen($run));
                $verb = preg_match('/^\\s+(?:[a-z]+(?:s|ed)|is|was|has|had|can|will|would|says|tells)\\b/u', $after);
                $again = preg_match_all('/(?<![.!?]\\s)(?<![.!?]\\s\\s)\\b' . preg_quote($name, '/') . '\\b/u', $text) > 1;
                if (!$verb && !$again) {
                    continue;
                }
            }
            $found[$name] = true;
        }
        $names = array_keys($found);
        // A shorter form ("Reeves", "Priya") is the same person as "Dr Reeves" or "Priya Sharma".
        return array_values(array_filter($names, function ($name) use ($names) {
            foreach ($names as $other) {
                if ($other !== $name && preg_match('/(^|\\s)' . preg_quote($name, '/') . '(\\s|$)/u', $other)) {
                    return false;
                }
            }
            return true;
        }));
    }

    /**
     * Whether the scene text speaks of a person as he or she, read from the sentences that name them.
     *
     * @param string $text
     * @param string $name
     * @return string f, m or '' when the text does not say
     */
    public static function gender(string $text, string $name): string {
        if (preg_match('/^(Mrs|Ms|Miss)\b/u', $name)) {
            return 'f';
        }
        if (preg_match('/^Mr\b/u', $name)) {
            return 'm';
        }
        $he = 0;
        $she = 0;
        $sentences = preg_split('/(?<=[.!?])\s+/u', $text);
        $others = array_diff(self::named($text), [$name]);
        $mentions = function (string $sentence, string $who): bool {
            return (bool)preg_match('/\b' . preg_quote($who, '/') . '\b/u', $sentence);
        };
        $other = function (string $sentence) use ($others, $mentions): bool {
            foreach ($others as $who) {
                if ($mentions($sentence, $who)) {
                    return true;
                }
            }
            return false;
        };
        foreach ($sentences as $i => $sentence) {
            if (!$mentions($sentence, $name)) {
                continue;
            }
            // The sentence naming them, and the next one ("Leo hesitates. He says ..."), unless it is about someone
            // else ("Dr Reeves waits. Fatima says she is fine." says nothing about Dr Reeves).
            $next = (string)($sentences[$i + 1] ?? '');
            if ($next !== '' && ($other($next) && !$mentions($next, $name))) {
                $next = '';
            }
            $near = \core_text::strtolower($sentence . ' ' . $next);
            $he += preg_match_all('/\b(he|him|his|himself)\b/u', $near);
            $she += preg_match_all('/\b(she|her|hers|herself)\b/u', $near);
        }
        return $he > $she ? 'm' : ($she > $he ? 'f' : '');
    }

    /**
     * The name and the role of a label, in either order: "Leo - Bartender" and "Bartender - Leo" are both
     * ["Leo", "Bartender"]; "RN - Fatima" is ["Fatima", "RN"]. A role written before the name without a dash
     * ("RN Priya", "Nurse Priya") is split off too. Titles stay with the name ("Dr Reeves").
     *
     * @param string $text
     * @return array [name, role] (role '' when there is none)
     */
    public static function split(string $text): array {
        $parts = array_map('trim', preg_split('/\s+[-–—]\s+|,\s*/u', trim($text), 2));
        $name = $parts[0];
        $role = $parts[1] ?? '';
        if ($role !== '' && self::is_role($name) && !self::is_role($role)) {
            [$name, $role] = [$role, $name];
        }
        // Such as "RN Priya" or "Nurse Priya": the leading role words are the role, the rest is the name.
        $words = preg_split('/\s+/u', $name);
        $lead = [];
        while (count($words) > 1 && self::is_role($words[0])) {
            $lead[] = array_shift($words);
        }
        if ($lead) {
            $name = implode(' ', $words);
            $role = $role !== '' ? $role : implode(' ', $lead);
        }
        return [$name, $role];
    }

    /**
     * A label in the one format learners see: "Name - Role" ("RN - Fatima" and "RN Fatima" become "Fatima - RN").
     * A label without a role, or the learner's ("You - Shift supervisor"), is kept as it is.
     *
     * @param string $text
     * @return string
     */
    public static function tidy(string $text): string {
        [$name, $role] = self::split($text);
        if ($name === '' || $role === '' || \core_text::strtolower($name) === 'you') {
            return $text;
        }
        $tidy = $name . ' - ' . $role;
        return \core_text::strlen($tidy) <= self::MAX_TEXT ? $tidy : $text;
    }

    /**
     * Whether a label part is a job or relationship rather than a name: an abbreviation such as RN or GP, or words
     * such as "Junior nurse", "Pharmacist" or "Daughter".
     *
     * @param string $text
     * @return bool
     */
    public static function is_role(string $text): bool {
        $words = preg_split('/\s+/u', trim($text));
        if ($words === [''] || preg_match('/^(Mrs|Mr|Ms|Miss|Mx|Dr|Prof)\.?$/u', $words[0])) {
            return false;
        }
        foreach ($words as $word) {
            if (preg_match('/^\p{Lu}{2,4}s?$/u', $word)) {
                return true;
            }
        }
        return in_array(\core_text::strtolower(end($words)), self::ROLES, true);
    }

    /**
     * Whether the scene's picture description shows a person as a man or a woman, such as "Narin, a Thai manager in
     * her forties" or "the shift supervisor, a man in his fifties". The words right after the name or role are read,
     * up to the next person (a semicolon or full stop).
     *
     * @param stdClass $scene
     * @param string $who a name ("Dr Reeves") or a role ("shift supervisor")
     * @return string f, m or ''
     */
    public static function picture_gender(stdClass $scene, string $who): string {
        $text = (string)($scene->imageprompt ?? '');
        $who = trim($who);
        if ($text === '' || $who === '') {
            return '';
        }
        $he = 0;
        $she = 0;
        // The words just before ("a male nurse called Leo", same clause) and after ("Leo, a man in his forties").
        if (preg_match_all('/([^.;,]{0,30})\b' . preg_quote($who, '/') . '\b([^.;]{0,90})/iu', $text, $m, PREG_SET_ORDER)) {
            foreach ($m as $match) {
                $after = \core_text::strtolower($match[1] . ' ' . $match[2]);
                $he += preg_match_all('/\b(man|men|male|gentleman|boy|he|his|him|father|husband|son|brother)\b/u', $after);
                $she += preg_match_all('/\b(woman|women|female|lady|girl|she|her|hers|mother|wife|daughter|sister)\b/u', $after);
            }
        }
        return $he > $she ? 'm' : ($she > $he ? 'f' : '');
    }

    /**
     * The voice kind of a label: the one the teacher chose, else what the scene text says ("he", "she"), else what
     * the picture description says ("a man in his forties"). The learner's label is read by their role.
     *
     * @param stdClass $scene
     * @param array $label as {@see self::clean()} returns
     * @return string f, m or ''
     */
    public static function kind(stdClass $scene, array $label): string {
        if ($label['gender'] !== '') {
            return $label['gender'];
        }
        if ($label['you']) {
            $role = learning::role_name(trim((string)($scene->speaker ?? '')), 'en');
            [, $labelrole] = self::split($label['text']);
            foreach (array_filter([$labelrole, $role]) as $r) {
                if (($g = self::picture_gender($scene, $r)) !== '') {
                    return $g;
                }
            }
            return '';
        }
        $name = self::split($label['text'])[0];
        $g = self::gender((string)($scene->context ?? ''), $name);
        return $g !== '' ? $g : self::picture_gender($scene, $name);
    }

    /**
     * The person a label names, for matching voices across scenes: "Leo - Bartender", "Bartender - Leo" and
     * "Leo" are all "leo"; "RN - Fatima" and "RN Fatima" are "fatima".
     *
     * @param string $text
     * @return string
     */
    public static function person(string $text): string {
        return \core_text::strtolower(trim(self::split($text)[0]));
    }

    /**
     * The job or role written just before a name in the scene text, such as "bartender" in "bartender Leo".
     *
     * @param string $text
     * @param string $name
     * @return string the role with a capital first letter, or ''
     */
    public static function role(string $text, string $name): string {
        // An abbreviation right before the name, such as "RN Fatima" or "GP Lee".
        if (preg_match('/(?:^|[\s,(])(\p{Lu}{2,4})\s+' . preg_quote($name, '/') . '\b/u', $text, $m)) {
            return $m[1];
        }
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
        // Adverbs and verb forms are never part of a role: "quietly mentions Priya" has none, "nurse Priya" has one.
        $verbish = fn($w) => in_array($w, $notroles, true) || preg_match('/(ly|ed|ing)$/', $w)
            || (preg_match('/[^s]s$/', $w) && !in_array($w, ['boss', 'chef'], true));
        // Drop leading words that are not part of a role ("ask bartender" -> "bartender").
        while ($words && $verbish($words[0])) {
            array_shift($words);
        }
        if (!$words || $verbish(end($words))) {
            return '';
        }
        $role = implode(' ', $words);
        return \core_text::strtoupper(\core_text::substr($role, 0, 1)) . \core_text::substr($role, 1);
    }
}
