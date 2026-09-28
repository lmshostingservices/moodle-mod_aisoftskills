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
$ready = manager::ready_scenes($instance, $context);

// A new activity with nothing in it: take the teacher straight to the lesson builder.
if (!$ready && $canmanage && !manager::get_scenes((int)$instance->id)) {
    redirect(new moodle_url('/mod/aisoftskills/builder.php', ['id' => $cm->id]));
}

manager::view($instance, $course, $cm, $context);

$userid = (int)$USER->id;
$canattempt = has_capability('mod/aisoftskills:attempt', $context) && !isguestuser();
$finished = $canattempt ? learning::finished_attempts($instance, $userid) : [];
$inprogress = $canattempt && $DB->record_exists('aisoftskills_attempt', ['aisoftskillsid' => $instance->id,
    'userid' => $userid, 'state' => learning::STATE_INPROGRESS]);
$best = null;
foreach ($finished as $attempt) {
    $best = $best === null ? (float)$attempt->score : max($best, (float)$attempt->score);
}
$canstart = $canattempt && $ready && ($inprogress || learning::can_start($instance, $userid));

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
        'date' => userdate($attempt->timefinish, get_string('strftimedatetimeshort', 'langconfig'))];
}

$config = [
    'cmid' => (int)$cm->id,
    'sounds' => (int)$instance->sounds,
    'level' => (string)$instance->level,
    'levelname' => get_string('level_' . $instance->level, 'mod_aisoftskills'),
    'contentlang' => (string)$instance->contentlang,
    'rtl' => catalogue::is_rtl((string)$instance->contentlang),
    'allowretry' => (int)$instance->allowretry,
    'graded' => $instance->grade != 0,
];

$templatedata = [
    'uniqid' => 'ss-' . $cm->id,
    'config' => json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
    'ready' => (bool)$ready,
    'canmanage' => $canmanage,
    'canattempt' => $canattempt,
    'canstart' => $canstart,
    'resume' => $inprogress,
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
        ? get_string('attemptsused', 'mod_aisoftskills', ['used' => count($finished), 'max' => $instance->maxattempts])
        : '',
    'builderurl' => (new moodle_url('/mod/aisoftskills/builder.php', ['id' => $cm->id]))->out(false),
    'scenesurl' => (new moodle_url('/mod/aisoftskills/scenes.php', ['id' => $cm->id]))->out(false),
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
