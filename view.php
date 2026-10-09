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

/**
 * Activity home and the scene player.
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use mod_aisoftskills\local\catalogue;
use mod_aisoftskills\local\learning;
use mod_aisoftskills\local\manager;

$id = required_param('id', PARAM_INT);
[$course, $cm] = get_course_and_cm_from_cmid($id, 'aisoftskills');
$instance = $DB->get_record('aisoftskills', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/aisoftskills:view', $context);

$PAGE->set_url('/mod/aisoftskills/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));

$canmanage = has_capability('mod/aisoftskills:manage', $context);

// The whole plugin needs this site to be unlocked with LMS Labs (50 credits or a recognised Marketplace purchase).
if (!\mod_aisoftskills\local\unlock::active()) {
    echo $OUTPUT->header();
    echo \mod_aisoftskills\local\unlock::locked_notice($canmanage);
    echo $OUTPUT->footer();
    exit;
}

$ready = manager::ready_scenes($instance, $context);

// A new activity with nothing in it: take the teacher straight to the lesson builder.
if (!$ready && $canmanage && !manager::get_scenes((int)$instance->id)) {
    redirect(new moodle_url('/mod/aisoftskills/builder.php', ['id' => $cm->id]));
}

manager::view($instance, $course, $cm, $context);

$userid = (int)$USER->id;
$canattempt = has_capability('mod/aisoftskills:attempt', $context) && !isguestuser();
$finished = $canattempt ? learning::finished_attempts($instance, $userid) : [];
$modes = learning::modes($instance);
$graded = learning::graded_mode($instance);
$best = null;
foreach ($finished as $attempt) {
    if ($attempt->playmode === $graded) {
        $best = $best === null ? (float)$attempt->score : max($best, (float)$attempt->score);
    }
}
// One start button per way to play: practice, test, or both.
$starts = [];
foreach ($modes as $mode) {
    $inprogress = $canattempt && $DB->record_exists('aisoftskills_attempt', ['aisoftskillsid' => $instance->id,
        'userid' => $userid, 'state' => learning::STATE_INPROGRESS, 'playmode' => $mode]);
    if (!$canattempt || !$ready || (!$inprogress && !learning::can_start($instance, $userid, $mode))) {
        continue;
    }
    $both = count($modes) > 1;
    $starts[] = [
        'mode' => $mode,
        'label' => $both ? get_string(($inprogress ? 'resume_' : 'start_') . $mode, 'mod_aisoftskills')
            : get_string($inprogress ? 'resume' : 'start', 'mod_aisoftskills'),
        'intro' => $both || $mode === learning::MODE_TEST ? get_string($mode . '_intro', 'mod_aisoftskills') : '',
        'primary' => $mode === learning::MODE_TEST || !$both,
    ];
}
$canstart = (bool)$starts;
$used = count(array_filter($finished, fn($a) => $a->playmode === $graded));

// The career ladder with this activity's level highlighted.
$ladder = [];
$reached = true;
foreach (catalogue::LEVELS as $i => $key) {
    $current = $key === $instance->level;
    $ladder[] = ['key' => $key, 'name' => get_string('level_' . $key, 'mod_aisoftskills'), 'number' => $i + 1,
        'current' => $current, 'below' => $reached && !$current];
    if ($current) {
        $reached = false;
    }
}
$skills = [];
foreach ($ready as [$scene]) {
    $name = catalogue::skill_name((string)$scene->skill);
    if ($name !== '' && !in_array($name, $skills, true)) {
        $skills[] = $name;
    }
}
$history = [];
foreach ($finished as $attempt) {
    $history[] = ['number' => (int)$attempt->attempt, 'score' => (int)round((float)$attempt->score),
        'mode' => get_string('mode_' . ($attempt->playmode === learning::MODE_TEST ? 'test' : 'practice'), 'mod_aisoftskills'),
        'date' => userdate($attempt->timefinish, get_string('strftimedatetimeshort', 'langconfig'))];
}

$config = [
    'cmid' => (int)$cm->id,
    'sounds' => (int)$instance->sounds,
    'mustlisten' => (int)$instance->mustlisten,
    'level' => (string)$instance->level,
    'levelname' => get_string('level_' . $instance->level, 'mod_aisoftskills'),
    'contentlang' => (string)$instance->contentlang,
    'rtl' => catalogue::is_rtl((string)$instance->contentlang),
    'passmark' => in_array(learning::MODE_TEST, $modes, true) ? (int)$instance->passmark : 0,
    'graded' => $instance->grade != 0,
];

$templatedata = [
    'uniqid' => 'ss-' . $cm->id,
    'config' => json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
    'ready' => (bool)$ready,
    'canmanage' => $canmanage,
    'canattempt' => $canattempt,
    'canstart' => $canstart,
    'starts' => $starts,
    'showmode' => count($modes) > 1,
    'nomore' => $canattempt && $ready && !$canstart,
    'industry' => catalogue::industry_name((string)$instance->industry, (string)$instance->customindustry),
    'levelname' => get_string('level_' . $instance->level, 'mod_aisoftskills'),
    'leveldesc' => get_string('leveldesc_' . $instance->level, 'mod_aisoftskills'),
    'language' => catalogue::language_name((string)$instance->contentlang),
    'ladder' => $ladder,
    'skills' => $skills,
    'hasskills' => (bool)$skills,
    'scenes' => count($ready),
    'bestscore' => $best === null ? null : (int)round($best),
    'hasbest' => $best !== null,
    'history' => array_reverse($history),
    'hashistory' => (bool)$history,
    'attemptsinfo' => $instance->maxattempts
        ? get_string('attemptsused', 'mod_aisoftskills', ['used' => $used, 'max' => $instance->maxattempts])
        : '',
    'setupurl' => (new moodle_url('/mod/aisoftskills/builder.php', ['id' => $cm->id, 'step' => 'resume']))->out(false),
    'reporturl' => has_capability('mod/aisoftskills:viewreports', $context)
        ? (new moodle_url('/mod/aisoftskills/report.php', ['id' => $cm->id]))->out(false) : null,
    'dir' => catalogue::is_rtl((string)$instance->contentlang) ? 'rtl' : 'ltr',
    'contentlang' => (string)$instance->contentlang,
];

if ($canstart) {
    $PAGE->requires->js_call_amd('mod_aisoftskills/player', 'init', ['#ss-' . $cm->id]);
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_aisoftskills/view', $templatedata);
echo $OUTPUT->footer();
