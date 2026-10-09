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
use moodle_url;
use stdClass;

/**
 * Learner attempts: scenes in order, choices marked on the server, workplace indicators and grades.
 *
 * The browser only ever receives the two responses of a scene, never which one is better. A choice is marked here;
 * the first choice in each scene is what counts for the grade. Indicators follow every choice, so a poor choice
 * followed by the better one shows a dip and a recovery.
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class learning {
    /** @var string Attempt in progress. */
    public const STATE_INPROGRESS = 'inprogress';

    /** @var string Attempt finished. */
    public const STATE_FINISHED = 'finished';

    /** @var string Practice: a second try after a poorer choice, ungraded when the activity also has a test. */
    public const MODE_PRACTICE = 'practice';

    /** @var string Test: one choice per scene, a score and the pass mark at the end. */
    public const MODE_TEST = 'test';

    /**
     * Plain text for the browser (Mustache or textContent escapes it there).
     *
     * @param string|null $text
     * @param \context $context
     * @return string
     */
    protected static function line(?string $text, \context $context): string {
        return format_string((string)$text, true, ['context' => $context, 'escape' => false]);
    }

    /**
     * Multi-line plain text for the browser.
     *
     * @param string|null $text
     * @param \context $context
     * @return string
     */
    protected static function para(?string $text, \context $context): string {
        $lines = array_map(fn($l) => self::line($l, $context), preg_split('/\R/u', (string)$text));
        return implode("\n", $lines);
    }

    /**
     * The learner's attempt, checked to belong to them and to this activity.
     *
     * @param stdClass $instance
     * @param int $attemptid
     * @param int $userid
     * @return stdClass
     */
    public static function get_user_attempt(stdClass $instance, int $attemptid, int $userid): stdClass {
        global $DB;
        $attempt = $DB->get_record('aisoftskills_attempt', ['id' => $attemptid]);
        if (!$attempt || (int)$attempt->userid !== $userid || (int)$attempt->aisoftskillsid !== (int)$instance->id) {
            throw new moodle_exception('notyourattempt', 'mod_aisoftskills');
        }
        return $attempt;
    }

    /**
     * Finished attempts of a learner.
     *
     * @param stdClass $instance
     * @param int $userid
     * @param string|null $mode only attempts in this mode
     * @return stdClass[]
     */
    public static function finished_attempts(stdClass $instance, int $userid, ?string $mode = null): array {
        global $DB;
        $params = ['aisoftskillsid' => $instance->id, 'userid' => $userid, 'state' => self::STATE_FINISHED];
        if ($mode !== null) {
            $params['playmode'] = $mode;
        }
        return $DB->get_records('aisoftskills_attempt', $params, 'attempt ASC');
    }

    /**
     * The ways learners can play an activity: practice, test, or both.
     *
     * Practice: after a poorer choice the learner sees why and chooses the other response. Test: one choice per scene,
     * then a score (and the pass mark) at the end; to pass, the learner takes the test again.
     *
     * @param stdClass $instance
     * @return string[]
     */
    public static function modes(stdClass $instance): array {
        $modes = [];
        if (!empty($instance->practicemode)) {
            $modes[] = self::MODE_PRACTICE;
        }
        if (!empty($instance->testmode)) {
            $modes[] = self::MODE_TEST;
        }
        return $modes ?: [self::MODE_PRACTICE];
    }

    /**
     * The mode whose attempts are graded and limited: the test when there is one, otherwise practice.
     *
     * @param stdClass $instance
     * @return string
     */
    public static function graded_mode(stdClass $instance): string {
        return in_array(self::MODE_TEST, self::modes($instance), true) ? self::MODE_TEST : self::MODE_PRACTICE;
    }

    /**
     * Whether the learner may start another attempt.
     *
     * Practice next to a test is never limited; "Attempts allowed" counts the graded mode only.
     *
     * @param stdClass $instance
     * @param int $userid
     * @param string|null $mode default: the graded mode
     * @return bool
     */
    public static function can_start(stdClass $instance, int $userid, ?string $mode = null): bool {
        $graded = self::graded_mode($instance);
        $mode = $mode ?? $graded;
        if (!in_array($mode, self::modes($instance), true)) {
            return false;
        }
        if ($mode !== $graded) {
            return true;
        }
        return !$instance->maxattempts
            || count(self::finished_attempts($instance, $userid, $graded)) < (int)$instance->maxattempts;
    }

    /**
     * Indicator values of an attempt.
     *
     * @param stdClass $attempt
     * @return array kpi => value
     */
    public static function kpis(stdClass $attempt): array {
        $values = json_decode((string)$attempt->kpis, true);
        return is_array($values) ? array_map('intval', $values) : [];
    }

    /**
     * Resumes the learner's attempt in progress, or starts a new one, and returns the player data.
     *
     * @param stdClass $instance
     * @param \context $context
     * @param int $userid
     * @param string $mode practice or test; '' for the activity's first mode
     * @return array
     */
    public static function start_attempt(stdClass $instance, \context $context, int $userid, string $mode = ''): array {
        global $DB;
        $mode = $mode !== '' ? $mode : self::modes($instance)[0];
        if (!in_array($mode, self::modes($instance), true)) {
            throw new moodle_exception('nomoreattempts', 'mod_aisoftskills');
        }
        $attempt = $DB->get_record('aisoftskills_attempt', ['aisoftskillsid' => $instance->id, 'userid' => $userid,
            'state' => self::STATE_INPROGRESS, 'playmode' => $mode]);
        if (!$attempt) {
            if (!self::can_start($instance, $userid, $mode)) {
                throw new moodle_exception('nomoreattempts', 'mod_aisoftskills');
            }
            $ready = manager::ready_scenes($instance, $context);
            if (!$ready) {
                throw new moodle_exception('noscenes', 'mod_aisoftskills');
            }
            $order = [];
            $firsts = self::better_first(count($ready));
            foreach (array_values($ready) as $i => [$scene, $options]) {
                $ids = array_map(fn($o) => (int)$o->id, $options);
                if ($instance->shuffleoptions) {
                    $ids = self::place_better($options, $firsts[$i]);
                }
                $order[] = ['scene' => (int)$scene->id, 'options' => $ids];
            }
            $number = (int)$DB->get_field_sql(
                'SELECT MAX(attempt) FROM {aisoftskills_attempt} WHERE aisoftskillsid = :aid AND userid = :userid',
                ['aid' => $instance->id, 'userid' => $userid]
            ) + 1;
            $now = time();
            $attempt = (object)[
                'aisoftskillsid' => $instance->id,
                'userid' => $userid,
                'attempt' => $number,
                'state' => self::STATE_INPROGRESS,
                'playmode' => $mode,
                'sceneorder' => json_encode($order),
                'kpis' => json_encode(new \stdClass()),
                'timestart' => $now,
                'timemodified' => $now,
            ];
            $attempt->id = $DB->insert_record('aisoftskills_attempt', $attempt);
        }
        return self::player_data($instance, $context, $attempt);
    }

    /**
     * Where the better response goes in each scene of a new attempt: first (A) in half of the scenes and second (B)
     * in the other half, in a random order, so the answer is never "always B". With an odd number of scenes the
     * extra one is random.
     *
     * A plain shuffle of two responses keeps their stored order half of the time, and drafted scenes often store
     * the better response in the same place, so learners could see the same letter win many times in a row.
     *
     * @param int $count number of scenes
     * @return bool[] true where the better response is shown first
     */
    public static function better_first(int $count): array {
        $half = intdiv($count, 2);
        $out = array_merge(array_fill(0, $half, true), array_fill(0, $half, false));
        if ($count % 2) {
            $out[] = (bool)random_int(0, 1);
        }
        shuffle($out);
        return $out;
    }

    /**
     * The response ids of a scene with the better response first or second.
     *
     * @param stdClass[] $options
     * @param bool $first
     * @return int[]
     */
    public static function place_better(array $options, bool $first): array {
        $best = [];
        $other = [];
        foreach ($options as $o) {
            if ((int)$o->best === 1) {
                $best[] = (int)$o->id;
            } else {
                $other[] = (int)$o->id;
            }
        }
        shuffle($other);
        return $first ? array_merge($best, $other) : array_merge($other, $best);
    }

    /**
     * The scene order of an attempt.
     *
     * @param stdClass $attempt
     * @return array list of ['scene' => id, 'options' => ids]
     */
    protected static function order(stdClass $attempt): array {
        $order = json_decode((string)$attempt->sceneorder, true);
        return is_array($order) ? $order : [];
    }

    /**
     * Player data for an attempt (no answer key).
     *
     * @param stdClass $instance
     * @param \context $context
     * @param stdClass $attempt
     * @return array
     */
    public static function player_data(stdClass $instance, \context $context, stdClass $attempt): array {
        global $DB;
        $order = self::order($attempt);
        $sceneids = array_column($order, 'scene');
        $scenes = $sceneids ? $DB->get_records_list('aisoftskills_scene', 'id', $sceneids) : [];
        $options = manager::get_options($sceneids);
        $choices = $DB->get_records(
            'aisoftskills_choice',
            ['attemptid' => $attempt->id],
            '',
            'sceneid, id, attemptid, optionid, best, tries, resolved, timecreated, timemodified'
        );
        $out = [];
        $number = 0;
        $voiceconfig = voiceover::enabled_for_learners() ? voiceover::learner_config($instance) : null;
        foreach ($order as $item) {
            $scene = $scenes[$item['scene']] ?? null;
            if (!$scene) {
                continue;
            }
            $number++;
            $byid = [];
            foreach ($options[$scene->id] as $o) {
                $byid[(int)$o->id] = $o;
            }
            $voice = $voiceconfig ? voiceover::playlist($instance, $context, $scene, $options[$scene->id], $voiceconfig)
                : ['scene' => [], 'options' => []];
            $list = [];
            $letter = 0;
            foreach ($item['options'] as $optionid) {
                if (isset($byid[$optionid])) {
                    $list[] = ['id' => $optionid, 'letter' => chr(65 + $letter++),
                        'text' => self::para($byid[$optionid]->text, $context),
                        'voice' => array_map(fn($u) => ['url' => $u], $voice['options'][$optionid] ?? [])];
                }
            }
            $labels = array_map(fn($l) => ['text' => self::line($l['text'], $context), 'x' => $l['x'], 'y' => $l['y'],
                'you' => $l['you']], labels::get($scene));
            [$image] = manager::get_scene_image($context, (int)$scene->id);
            $choice = $choices[$scene->id] ?? null;
            $out[] = [
                'sceneid' => (int)$scene->id,
                'number' => $number,
                'title' => self::line($scene->title, $context),
                'skill' => self::line(catalogue::skill_name((string)$scene->skill), $context),
                'context' => self::para($scene->context, $context),
                'cards' => self::context_cards(self::para($scene->context, $context), (string)$instance->contentlang),
                'labels' => $labels,
                'voice' => $voice['scene'],
                'dialogue' => array_map(fn($d) => ['speaker' => self::line($d['speaker'], $context),
                    'line' => self::para($d['line'], $context)], manager::dialogue($scene->script)),
                'speaker' => self::line($scene->speaker, $context),
                'roleline' => self::role_line(self::line($scene->speaker, $context), (string)$instance->contentlang),
                'question' => self::line($scene->question, $context),
                'image' => (string)$image,
                'options' => $list,
                'resolved' => $choice && $choice->resolved ? 1 : 0,
                'answered' => $choice ? 1 : 0,
                // A poor first choice waiting for a second try stays crossed out when the learner comes back.
                'tried' => $choice && !$choice->resolved ? (int)$choice->optionid : 0,
            ];
        }
        $kpis = self::kpis($attempt);
        return [
            'attemptid' => (int)$attempt->id,
            'attempt' => (int)$attempt->attempt,
            'mode' => (string)$attempt->playmode,
            'scenes' => $out,
            'kpis' => self::kpi_list($kpis),
        ];
    }

    /**
     * The line at the top of a scene that tells learners who they are: "As the bar shift supervisor, how would you
     * handle this situation?" for "You, the bar shift supervisor". Other languages show the role as written.
     *
     * @param string $speaker who responds, such as "You, the bar shift supervisor"
     * @param string $lang content language
     * @return string '' when there is no role
     */
    public static function role_line(string $speaker, string $lang): string {
        $speaker = trim($speaker);
        if ($speaker === '') {
            return '';
        }
        $role = self::role_name($speaker, $lang);
        if ($role === '') {
            return $speaker;
        }
        return get_string_manager()->get_string('roleline', 'mod_aisoftskills', $role, 'en');
    }

    /**
     * The role in an English "You, the ..." speaker, such as "bar shift supervisor"; '' when it is not one.
     *
     * @param string $speaker
     * @param string $lang
     * @return string
     */
    public static function role_name(string $speaker, string $lang): string {
        if ($lang !== 'en' || !preg_match('/^you\b[,:\s-]*(?:are\s+)?(?:the\s+|an?\s+)?(.+)$/iu', trim($speaker), $m)) {
            return '';
        }
        return trim($m[1], " .,:;-");
    }

    /**
     * The scene setting split into short cards, so it is easy to read: the situation (the first sentence), what you
     * did or have to do (sentences about "you", in English), and the background (everything else).
     *
     * @param string $text plain text
     * @param string $lang content language
     * @return array list of {kind (situation, action or context), lines}
     */
    public static function context_cards(string $text, string $lang): array {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if ($text === '') {
            return [];
        }
        $sentences = preg_split('/(?<=[.!?…])\s+(?=["“‘(\p{Lu}\p{Lo}\d])|(?<=[。！？])/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $sentences = array_values(array_filter(array_map('trim', $sentences), fn($v) => $v !== ''));
        // A title such as "Mr. Smith" stays in one sentence.
        $joined = [];
        foreach ($sentences as $sentence) {
            if ($joined && preg_match('/\b(Mr|Mrs|Ms|Dr|St|Prof|Sr|Jr|vs|etc)\.$/i', end($joined))) {
                $joined[count($joined) - 1] .= ' ' . $sentence;
            } else {
                $joined[] = $sentence;
            }
        }
        $sentences = $joined;
        $cards = ['situation' => [array_shift($sentences)], 'action' => [], 'context' => []];
        foreach ($sentences as $sentence) {
            $you = $lang === 'en' && preg_match('/\b(you|your)\b/i', $sentence);
            $cards[$you ? 'action' : 'context'][] = $sentence;
        }
        $out = [];
        foreach ($cards as $kind => $lines) {
            if ($lines) {
                $out[] = ['kind' => $kind, 'lines' => array_map(fn($l) => ['text' => $l], $lines)];
            }
        }
        return $out;
    }

    /**
     * Indicators as a list for display.
     *
     * @param array $kpis kpi => value
     * @return array
     */
    public static function kpi_list(array $kpis): array {
        $list = [];
        foreach ($kpis as $key => $value) {
            if (catalogue::is_kpi((string)$key)) {
                $list[] = ['kpi' => (string)$key, 'name' => catalogue::kpi_name((string)$key), 'value' => (int)$value,
                    'start' => catalogue::KPI_START];
            }
        }
        return $list;
    }

    /**
     * Marks the learner's choice in a scene.
     *
     * @param stdClass $instance
     * @param \context $context
     * @param stdClass $attempt
     * @param int $sceneid
     * @param int $optionid
     * @return array result: best, consequence, reason, kpi, before, after, canretry, resolved, better
     */
    public static function choose(
        stdClass $instance,
        \context $context,
        stdClass $attempt,
        int $sceneid,
        int $optionid
    ): array {
        global $DB;
        if ($attempt->state !== self::STATE_INPROGRESS) {
            throw new moodle_exception('attemptfinished', 'mod_aisoftskills');
        }
        $item = null;
        foreach (self::order($attempt) as $entry) {
            if ((int)$entry['scene'] === $sceneid) {
                $item = $entry;
            }
        }
        if (!$item || !in_array($optionid, array_map('intval', $item['options']), true)) {
            throw new moodle_exception('invalidoption', 'mod_aisoftskills');
        }
        $option = $DB->get_record('aisoftskills_option', ['id' => $optionid, 'sceneid' => $sceneid], '*', MUST_EXIST);
        $choice = $DB->get_record('aisoftskills_choice', ['attemptid' => $attempt->id, 'sceneid' => $sceneid]);
        if ($choice && $choice->resolved) {
            throw new moodle_exception('alreadyanswered', 'mod_aisoftskills');
        }
        $best = (int)$option->best === 1;
        // Practice: a second try after a poorer choice. Test: one choice per scene.
        $retry = $attempt->playmode !== self::MODE_TEST;
        $now = time();
        $transaction = $DB->start_delegated_transaction();
        if (!$choice) {
            $choice = (object)[
                'attemptid' => $attempt->id,
                'sceneid' => $sceneid,
                'optionid' => $optionid,
                'best' => $best ? 1 : 0,
                'tries' => 1,
                'resolved' => ($best || !$retry) ? 1 : 0,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $choice->id = $DB->insert_record('aisoftskills_choice', $choice);
        } else {
            if ((int)$choice->optionid === $optionid && !$best) {
                throw new moodle_exception('alreadytried', 'mod_aisoftskills');
            }
            $choice->tries++;
            $choice->resolved = $best ? 1 : 0;
            $choice->timemodified = $now;
            $DB->update_record('aisoftskills_choice', $choice);
        }
        // Indicators follow every choice.
        $kpis = self::kpis($attempt);
        $key = (string)$option->kpi;
        $before = $kpis[$key] ?? catalogue::KPI_START;
        $after = max(0, min(100, $before + (int)$option->kpidelta));
        $kpis[$key] = $after;
        $DB->update_record('aisoftskills_attempt', (object)['id' => $attempt->id, 'kpis' => json_encode($kpis),
            'timemodified' => $now]);
        $attempt->kpis = json_encode($kpis);
        $transaction->allow_commit();

        $result = [
            'best' => $best ? 1 : 0,
            'first' => (int)$choice->tries === 1 ? 1 : 0,
            'consequence' => self::para($option->consequence, $context),
            'reason' => self::para($option->reason, $context),
            'kpi' => $key,
            'kpiname' => catalogue::kpi_name($key),
            'delta' => (int)$option->kpidelta,
            'before' => (int)$before,
            'after' => (int)$after,
            'resolved' => (int)$choice->resolved,
            'canretry' => !$best && $retry ? 1 : 0,
            'better' => '',
            'betterreason' => '',
            'allresolved' => self::all_resolved($attempt) ? 1 : 0,
            'voice' => [],
        ];
        if (voiceover::enabled_for_learners()) {
            // The feedback read out: what happened, then why (when the teacher chose those parts and they are made).
            $scene = $DB->get_record('aisoftskills_scene', ['id' => $sceneid], '*', MUST_EXIST);
            $all = manager::get_options([$sceneid])[$sceneid];
            $voice = voiceover::playlist($instance, $context, $scene, $all, voiceover::learner_config($instance));
            $result['voice'] = $voice['feedback'][$optionid] ?? [];
        }
        return $result;
    }

    /**
     * Whether every scene of an attempt has been resolved.
     *
     * @param stdClass $attempt
     * @return bool
     */
    public static function all_resolved(stdClass $attempt): bool {
        global $DB;
        $total = count(self::order($attempt));
        $done = $DB->count_records('aisoftskills_choice', ['attemptid' => $attempt->id, 'resolved' => 1]);
        return $total > 0 && $done >= $total;
    }

    /**
     * Finishes an attempt: score, grade, completion and event.
     *
     * @param stdClass $instance
     * @param \cm_info|stdClass $cm
     * @param stdClass $course
     * @param \context $context
     * @param stdClass $attempt
     * @return array summary
     */
    public static function finish_attempt(stdClass $instance, $cm, stdClass $course, \context $context, stdClass $attempt): array {
        global $DB, $CFG;
        if ($attempt->state === self::STATE_INPROGRESS) {
            if (!self::all_resolved($attempt)) {
                throw new moodle_exception('scenesleft', 'mod_aisoftskills');
            }
            $total = count(self::order($attempt));
            $best = $DB->count_records('aisoftskills_choice', ['attemptid' => $attempt->id, 'best' => 1]);
            $attempt->score = $total ? round(100 * $best / $total, 2) : 0;
            $attempt->state = self::STATE_FINISHED;
            $attempt->timefinish = time();
            $attempt->timemodified = $attempt->timefinish;
            $DB->update_record('aisoftskills_attempt', $attempt);
            require_once($CFG->dirroot . '/mod/aisoftskills/lib.php');
            aisoftskills_update_grades($instance, (int)$attempt->userid);
            require_once($CFG->libdir . '/completionlib.php');
            $completion = new \completion_info($course);
            if ($completion->is_enabled($cm)) {
                $completion->update_state($cm, COMPLETION_UNKNOWN, (int)$attempt->userid);
            }
            \mod_aisoftskills\event\attempt_finished::create([
                'objectid' => $attempt->id,
                'context' => $context,
                'relateduserid' => $attempt->userid,
                'other' => ['score' => (float)$attempt->score],
            ])->trigger();
        }
        return self::summary($instance, $context, $attempt);
    }

    /**
     * Summary of a finished attempt.
     *
     * @param stdClass $instance
     * @param \context $context
     * @param stdClass $attempt
     * @return array
     */
    public static function summary(stdClass $instance, \context $context, stdClass $attempt): array {
        global $DB;
        $total = count(self::order($attempt));
        $best = $DB->count_records('aisoftskills_choice', ['attemptid' => $attempt->id, 'best' => 1]);
        $score = (int)round((float)$attempt->score);
        if ($score >= 90) {
            $rating = 'excellent';
        } else if ($score >= 70) {
            $rating = 'strong';
        } else if ($score >= 50) {
            $rating = 'developing';
        } else {
            $rating = 'beginning';
        }
        $mode = (string)$attempt->playmode;
        $finished = count(self::finished_attempts($instance, (int)$attempt->userid, $mode));
        $limited = $mode === self::graded_mode($instance) && $instance->maxattempts;
        $passmark = $mode === self::MODE_TEST ? (int)$instance->passmark : 0;
        // Debrief: where the first choice was the poorer one, what the better response was and why it works.
        $takeaways = [];
        $sql = 'SELECT ch.id, s.id AS sceneid, s.title, s.skill, o.text, o.reason
                  FROM {aisoftskills_choice} ch
                  JOIN {aisoftskills_scene} s ON s.id = ch.sceneid
                  JOIN {aisoftskills_option} o ON o.sceneid = s.id AND o.best = 1
                 WHERE ch.attemptid = :attemptid AND ch.best = 0
              ORDER BY s.sortorder, ch.id';
        foreach ($DB->get_records_sql($sql, ['attemptid' => $attempt->id]) as $row) {
            $takeaways[] = [
                'title' => self::line($row->title, $context),
                'skill' => self::line(catalogue::skill_name((string)$row->skill), $context),
                'better' => self::para($row->text, $context),
                'reason' => self::para($row->reason, $context),
            ];
        }
        // Every scene, in the order played, for the results slides: picture, first choice and the better response.
        $order = self::order($attempt);
        $sceneids = array_column($order, 'scene');
        $scenes = $sceneids ? $DB->get_records_list('aisoftskills_scene', 'id', $sceneids) : [];
        $options = manager::get_options($sceneids);
        $choices = $DB->get_records('aisoftskills_choice', ['attemptid' => $attempt->id], '', 'sceneid, best');
        $recap = [];
        foreach ($sceneids as $sceneid) {
            $scene = $scenes[$sceneid] ?? null;
            if (!$scene) {
                continue;
            }
            $better = null;
            foreach ($options[$sceneid] as $o) {
                $better = (int)$o->best === 1 ? $o : $better;
            }
            [$image] = manager::get_scene_image($context, (int)$sceneid);
            $recap[] = [
                'number' => count($recap) + 1,
                'title' => self::line($scene->title, $context),
                'skill' => self::line(catalogue::skill_name((string)$scene->skill), $context),
                'image' => (string)$image,
                'firstbest' => !empty($choices[$sceneid]->best),
                'better' => $better ? self::para($better->text, $context) : '',
                'reason' => $better ? self::para($better->reason, $context) : '',
            ];
        }
        return [
            'takeaways' => $takeaways,
            'recap' => $recap,
            'score' => $score,
            'best' => $best,
            'total' => $total,
            'rating' => $rating,
            'kpis' => self::kpi_list(self::kpis($attempt)),
            'level' => (string)$instance->level,
            'canretake' => in_array($mode, self::modes($instance), true)
                && (!$limited || $finished < (int)$instance->maxattempts) ? 1 : 0,
            'attemptsleft' => $limited ? max(0, (int)$instance->maxattempts - $finished) : -1,
            'mode' => $mode,
            'passmark' => $passmark,
            'passed' => $passmark > 0 && (float)$attempt->score >= $passmark ? 1 : 0,
            'cantest' => $mode === self::MODE_PRACTICE && self::can_start($instance, (int)$attempt->userid, self::MODE_TEST)
                ? 1 : 0,
        ];
    }

    /**
     * Review of an attempt for teachers: each scene with the first choice.
     *
     * @param stdClass $attempt
     * @param \context $context
     * @return array
     */
    public static function review(stdClass $attempt, \context $context): array {
        global $DB;
        $order = self::order($attempt);
        $sceneids = array_column($order, 'scene');
        $scenes = $sceneids ? $DB->get_records_list('aisoftskills_scene', 'id', $sceneids) : [];
        $choices = $DB->get_records(
            'aisoftskills_choice',
            ['attemptid' => $attempt->id],
            '',
            'sceneid, id, attemptid, optionid, best, tries, resolved, timecreated, timemodified'
        );
        $optionids = array_map(fn($c) => (int)$c->optionid, $choices);
        $options = $optionids ? $DB->get_records_list('aisoftskills_option', 'id', $optionids) : [];
        $rows = [];
        foreach ($order as $i => $item) {
            $scene = $scenes[$item['scene']] ?? null;
            if (!$scene) {
                continue;
            }
            $choice = $choices[$scene->id] ?? null;
            $option = $choice ? ($options[$choice->optionid] ?? null) : null;
            $rows[] = [
                'number' => $i + 1,
                'title' => self::line($scene->title, $context),
                'choice' => $option ? self::para($option->text, $context) : '',
                'best' => $choice && $choice->best ? 1 : 0,
                'answered' => $choice ? 1 : 0,
                'tries' => $choice ? (int)$choice->tries : 0,
            ];
        }
        return $rows;
    }

    /**
     * Deletes all learner data of an activity.
     *
     * @param int $aid
     */
    public static function delete_all_user_data(int $aid): void {
        global $DB;
        $ids = $DB->get_fieldset_select('aisoftskills_attempt', 'id', 'aisoftskillsid = :aid', ['aid' => $aid]);
        if ($ids) {
            [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('aisoftskills_choice', "attemptid $insql", $params);
        }
        $DB->delete_records('aisoftskills_attempt', ['aisoftskillsid' => $aid]);
    }

    /**
     * Deletes one learner's data in an activity.
     *
     * @param int $aid
     * @param int $userid
     */
    public static function delete_user_data(int $aid, int $userid): void {
        global $DB;
        $ids = $DB->get_fieldset_select(
            'aisoftskills_attempt',
            'id',
            'aisoftskillsid = :aid AND userid = :userid',
            ['aid' => $aid, 'userid' => $userid]
        );
        if ($ids) {
            [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('aisoftskills_choice', "attemptid $insql", $params);
            $DB->delete_records_select('aisoftskills_attempt', "id $insql", $params);
        }
        $DB->delete_records('aisoftskills_ailog', ['aisoftskillsid' => $aid, 'userid' => $userid]);
        $DB->delete_records('aisoftskills_aireq', ['aisoftskillsid' => $aid, 'userid' => $userid]);
    }

    /**
     * Deletes attempts chosen on the report.
     *
     * @param stdClass $instance
     * @param int[] $attemptids
     * @return int number deleted
     */
    public static function delete_attempts(stdClass $instance, array $attemptids): int {
        global $DB, $CFG;
        if (!$attemptids) {
            return 0;
        }
        [$insql, $params] = $DB->get_in_or_equal($attemptids, SQL_PARAMS_NAMED);
        $params['aid'] = $instance->id;
        $attempts = $DB->get_records_select('aisoftskills_attempt', "id $insql AND aisoftskillsid = :aid", $params);
        if (!$attempts) {
            return 0;
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($attempts), SQL_PARAMS_NAMED);
        $DB->delete_records_select('aisoftskills_choice', "attemptid $insql", $params);
        $DB->delete_records_select('aisoftskills_attempt', "id $insql", $params);
        require_once($CFG->dirroot . '/mod/aisoftskills/lib.php');
        foreach (array_unique(array_map(fn($a) => (int)$a->userid, $attempts)) as $userid) {
            aisoftskills_update_grades($instance, $userid);
        }
        return count($attempts);
    }

    /**
     * URL of the activity.
     *
     * @param int $cmid
     * @return moodle_url
     */
    public static function view_url(int $cmid): moodle_url {
        return new moodle_url('/mod/aisoftskills/view.php', ['id' => $cmid]);
    }
}
