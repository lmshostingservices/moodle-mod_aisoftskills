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
 * Lesson builder: workplace, level, skills and language, then draft and create the scenes.
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use mod_aisoftskills\local\ai\factory;
use mod_aisoftskills\local\ai\text_draft;
use mod_aisoftskills\local\catalogue;
use mod_aisoftskills\local\lesson;
use mod_aisoftskills\local\manager;

$id = required_param('id', PARAM_INT);
$step = optional_param('step', '', PARAM_ALPHA);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'aisoftskills');
$instance = $DB->get_record('aisoftskills', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/aisoftskills:manage', $context);

$baseurl = new moodle_url('/mod/aisoftskills/builder.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($instance->name) . ': ' . get_string('buildlesson', 'mod_aisoftskills'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->activityheader->disable();
if ($node = $PAGE->settingsnav->find('aisoftskills_builder', navigation_node::TYPE_SETTING)) {
    $node->make_active();
}

// Save the choices from steps 1 to 4.
if (optional_param('savechoices', 0, PARAM_BOOL)) {
    require_sesskey();
    $data = (object)[
        'id' => $instance->id,
        'industry' => required_param('industry', PARAM_ALPHA),
        'customindustry' => optional_param('customindustry', '', PARAM_TEXT),
        'level' => required_param('level', PARAM_ALPHA),
        'contentlang' => required_param('contentlang', PARAM_ALPHA),
        'imagestyle' => optional_param('imagestyle', 'illustration', PARAM_ALPHA),
    ];
    $data = manager::prepare_instance_data($data);
    $keys = array_values(array_filter(
        optional_param_array('skills', [], PARAM_ALPHA),
        fn($k) => catalogue::is_skill($k)
    ));
    $custom = [];
    foreach (optional_param_array('custom', [], PARAM_TEXT) as $name) {
        $name = manager::clean_line($name, 120);
        if ($name !== '' && count($custom) < catalogue::MAX_CUSTOM) {
            $custom[] = $name;
        }
    }
    $data->skills = json_encode([
        'keys' => $keys,
        'custom' => $custom,
        'scenes' => max(1, min(lesson::MAX_SCENES_PER_LESSON, optional_param('scenes', 8, PARAM_INT))),
    ]);
    $data->timemodified = time();
    $DB->update_record('aisoftskills', $data);
    redirect(new moodle_url($baseurl, ['step' => 'build']));
}

$instance = $DB->get_record('aisoftskills', ['id' => $cm->instance], '*', MUST_EXIST);
$str = fn($k, $a = null) => get_string($k, 'mod_aisoftskills', $a);
$choices = lesson::choices($instance);

if ($step === 'build') {
    $draftaction = optional_param('draftaction', '', PARAM_ALPHA);
    if ($draftaction !== '') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new moodle_exception('invalidparameter');
        }
        require_sesskey();
        require_capability('mod/aisoftskills:useai', $context);
        if ($draftaction === 'new') {
            $row = text_draft::create((int)$instance->id, (int)$USER->id,
                required_param('intent', PARAM_ALPHANUMEXT), required_param('brief', PARAM_TEXT));
            $row = text_draft::send($row);
        } else if ($draftaction === 'resume' || $draftaction === 'save') {
            $row = $DB->get_record('aisoftskills_draft', [
                'id' => required_param('draftid', PARAM_INT),
                'aisoftskillsid' => $instance->id,
                'userid' => $USER->id,
            ], '*', MUST_EXIST);
            if ($draftaction === 'resume') {
                $row = text_draft::send($row);
            } else {
                text_draft::save_edit($row, required_param('editedbody', PARAM_TEXT));
            }
        } else {
            throw new moodle_exception('invalidparameter');
        }
        redirect(new moodle_url($baseurl, ['step' => 'build', 'draftid' => $row->id]));
    }
    $provider = factory::get();
    $balance = $provider->balance();
    $names = array_merge(array_map(fn($k) => catalogue::skill_name($k), $choices['keys']), $choices['custom']);
    $draftid = optional_param('draftid', 0, PARAM_INT);
    $lastdraft = $draftid ? $DB->get_record('aisoftskills_draft', [
        'id' => $draftid, 'aisoftskillsid' => $instance->id, 'userid' => $USER->id,
    ], '*', MUST_EXIST) : false;
    $drafts = $DB->get_records('aisoftskills_draft',
        ['aisoftskillsid' => $instance->id, 'userid' => $USER->id], 'id DESC', '*', 0, 20);
    if (!$lastdraft) {
        $lastdraft = reset($drafts);
    }
    $draftlinks = [];
    foreach ($drafts as $draftrow) {
        $draftlinks[] = [
            'url' => (new moodle_url($baseurl, ['step' => 'build', 'draftid' => $draftrow->id]))->out(false),
            'title' => userdate($draftrow->timecreated) . ' — ' . $str('textdraft_state_' . $draftrow->state),
        ];
    }
    $templatedata = [
        'cmid' => (int)$cm->id,
        'backurl' => $baseurl->out(false),
        'scenesurl' => (new moodle_url('/mod/aisoftskills/scenes.php', ['id' => $cm->id]))->out(false),
        'summary' => [
            'industry' => catalogue::industry_name($instance->industry, (string)$instance->customindustry),
            'level' => $str('level_' . $instance->level),
            'skills' => $names ? implode(', ', $names) : $str('skills_any'),
            'language' => catalogue::language_name($instance->contentlang),
            'scenes' => $choices['scenes'],
        ],
        'hasbalance' => $balance !== null,
        'balance' => $balance === null ? '' : ($balance['unlimited'] ? $str('balance_unlimited')
            : $str('balance_credits', $balance['credits'])),
        'prompt' => lesson::prompt($instance),
        'existing' => count(manager::get_scenes($instance->id)),
        'showdraft' => has_capability('mod/aisoftskills:useai', $context),
        'candraft' => \mod_aisoftskills\local\credentials::find() !== null,
        'draftactionurl' => (new moodle_url($baseurl, ['step' => 'build']))->out(false),
        'draftsesskey' => sesskey(),
        'draftintent' => \core\uuid::generate(),
        'draftid' => $lastdraft ? (int)$lastdraft->id : 0,
        'draftpending' => $lastdraft && $lastdraft->state === 'pending',
        'draftcomplete' => $lastdraft && $lastdraft->state === 'complete',
        'draftstatus' => $lastdraft ? $str('textdraft_state_' . $lastdraft->state) : '',
        'draftreference' => $lastdraft ? (string)$lastdraft->requestid : '',
        'drafterror' => $lastdraft ? (string)$lastdraft->errorcode : '',
        'draftedited' => $lastdraft && $lastdraft->state === 'complete' ? (string)$lastdraft->editedbody : '',
        'draftoriginal' => $lastdraft && $lastdraft->state === 'complete' ?
            json_encode(json_decode((string)$lastdraft->responsebody, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '',
        'draftlinks' => $draftlinks,
    ];
    $PAGE->requires->js_call_amd('mod_aisoftskills/builder', 'initBuild', ['#ss-build']);
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('mod_aisoftskills/builder_build', $templatedata);
    echo $OUTPUT->footer();
    exit;
}

// Steps 1 to 4.
$industries = [];
foreach (array_merge(catalogue::INDUSTRIES, ['custom']) as $key) {
    $industries[] = ['key' => $key, 'name' => $str('industry_' . $key), 'selected' => $key === $instance->industry,
        'custom' => $key === 'custom'];
}
$levels = [];
foreach (catalogue::LEVELS as $i => $level) {
    $levels[] = ['key' => $level, 'number' => $i + 1, 'name' => $str('level_' . $level),
        'desc' => $str('leveldesc_' . $level), 'selected' => $level === $instance->level];
}
$groups = [];
foreach (catalogue::SKILLS as $group => $keys) {
    $items = [];
    foreach ($keys as $key) {
        $items[] = ['key' => $key, 'name' => catalogue::skill_name($key),
            'checked' => in_array($key, $choices['keys'], true)];
    }
    $groups[] = ['key' => $group, 'name' => $str('skillgroup_' . $group), 'items' => $items];
}
$customfields = [];
for ($i = 0; $i < catalogue::MAX_CUSTOM; $i++) {
    $customfields[] = ['index' => $i + 1, 'value' => $choices['custom'][$i] ?? '',
        'hidden' => $i > max(0, count($choices['custom']))];
}
$languages = [];
foreach (catalogue::LANGUAGES as $code => [$endonym, $rtl]) {
    $name = catalogue::language_name($code);
    $languages[] = ['code' => $code, 'name' => $name, 'endonym' => $endonym, 'showendonym' => $name !== $endonym,
        'rtl' => $rtl, 'selected' => $code === $instance->contentlang];
}
$templatedata = [
    'cmid' => (int)$cm->id,
    'action' => $baseurl->out(false),
    'sesskey' => sesskey(),
    'industries' => $industries,
    'customindustry' => (string)$instance->customindustry,
    'levels' => $levels,
    'groups' => $groups,
    'custom' => $customfields,
    'languages' => $languages,
    'scenes' => array_map(
        fn($n) => ['value' => $n, 'selected' => $n === $choices['scenes']],
        range(1, lesson::MAX_SCENES_PER_LESSON)
    ),
    'illustration' => $instance->imagestyle !== 'photo',
    'photo' => $instance->imagestyle === 'photo',
    'hasscenes' => (bool)manager::get_scenes($instance->id),
    'buildurl' => (new moodle_url($baseurl, ['step' => 'build']))->out(false),
];
$PAGE->requires->js_call_amd('mod_aisoftskills/builder', 'initWizard', ['#ss-wizard']);
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_aisoftskills/builder', $templatedata);
echo $OUTPUT->footer();
