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
     * @return stdClass[]
     */
    public static function finished_attempts(stdClass $instance, int $userid): array {
        global $DB;
        return $DB->get_records(
            'aisoftskills_attempt',
            ['aisoftskillsid' => $instance->id, 'userid' => $userid, 'state' => self::STATE_FINISHED],
            'attempt ASC'
        );
    }

    /**
     * Whether the learner may start another attempt.
     *
     * @param stdClass $instance
     * @param int $userid
     * @return bool
     */
    public static function can_start(stdClass $instance, int $userid): bool {
        return !$instance->maxattempts || count(self::finished_attempts($instance, $userid)) < (int)$instance->maxattempts;
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
     * @return array
     */
    public static function start_attempt(stdClass $instance, \context $context, int $userid): array {
        global $DB;
        $attempt = $DB->get_record('aisoftskills_attempt', ['aisoftskillsid' => $instance->id, 'userid' => $userid,
            'state' => self::STATE_INPROGRESS]);
        if (!$attempt) {
            if (!self::can_start($instance, $userid)) {
                throw new moodle_exception('nomoreattempts', 'mod_aisoftskills');
            }
            $ready = manager::ready_scenes($instance, $context);
            if (!$ready) {
                throw new moodle_exception('noscenes', 'mod_aisoftskills');
            }
            $order = [];
            foreach ($ready as [$scene, $options]) {
                $ids = array_map(fn($o) => (int)$o->id, $options);
                if ($instance->shuffleoptions) {
                    shuffle($ids);
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
            $list = [];
            $letter = 0;
            foreach ($item['options'] as $optionid) {
                if (isset($byid[$optionid])) {
                    $list[] = ['id' => $optionid, 'letter' => chr(65 + $letter++),
                        'text' => self::para($byid[$optionid]->text, $context)];
                }
            }
            [$image] = manager::get_scene_image($context, (int)$scene->id);
            $choice = $choices[$scene->id] ?? null;
            $out[] = [
                'sceneid' => (int)$scene->id,
                'number' => $number,
                'title' => self::line($scene->title, $context),
                'skill' => self::line(catalogue::skill_name((string)$scene->skill), $context),
                'context' => self::para($scene->context, $context),
                'speaker' => self::line($scene->speaker, $context),
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
            'scenes' => $out,
            'kpis' => self::kpi_list($kpis),
        ];
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
    public static function choose(stdClass $instance, \context $context, stdClass $attempt, int $sceneid, int $optionid): array {
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
        $now = time();
        $transaction = $DB->start_delegated_transaction();
        if (!$choice) {
            $choice = (object)[
                'attemptid' => $attempt->id,
                'sceneid' => $sceneid,
                'optionid' => $optionid,
                'best' => $best ? 1 : 0,
                'tries' => 1,
                'resolved' => ($best || !$instance->allowretry) ? 1 : 0,
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
            'canretry' => !$best && $instance->allowretry ? 1 : 0,
            'better' => '',
            'betterreason' => '',
            'allresolved' => self::all_resolved($attempt) ? 1 : 0,
        ];
        if (!$best && !$instance->allowretry) {
            // No second try: show the better response now.
            foreach ($DB->get_records('aisoftskills_option', ['sceneid' => $sceneid, 'best' => 1]) as $better) {
                $result['better'] = self::para($better->text, $context);
                $result['betterreason'] = self::para($better->reason, $context);
            }
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
        $finished = count(self::finished_attempts($instance, (int)$attempt->userid));
        return [
            'score' => $score,
            'best' => $best,
            'total' => $total,
            'rating' => $rating,
            'kpis' => self::kpi_list(self::kpis($attempt)),
            'level' => (string)$instance->level,
            'canretake' => !$instance->maxattempts || $finished < (int)$instance->maxattempts ? 1 : 0,
            'attemptsleft' => $instance->maxattempts ? max(0, (int)$instance->maxattempts - $finished) : -1,
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
        $DB->delete_records('aisoftskills_draft', ['aisoftskillsid' => $aid, 'userid' => $userid]);
        $DB->delete_records('aisoftskills_imagejob', ['aisoftskillsid' => $aid, 'userid' => $userid]);
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
