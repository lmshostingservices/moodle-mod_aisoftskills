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
 * Set-up steps 1 to 5 and 8: workplace, level, skills, language, create the scenes; finish. Steps 6 and 7 are scenes.php.
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use mod_aisoftskills\local\ai\factory;
use mod_aisoftskills\local\ai\requests;
use mod_aisoftskills\local\catalogue;
use mod_aisoftskills\local\lesson;
use mod_aisoftskills\local\manager;
use mod_aisoftskills\local\setuppath;

$id = required_param('id', PARAM_INT);
$step = optional_param('step', '', PARAM_ALPHA);
$start = optional_param('start', 1, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'aisoftskills');
$instance = $DB->get_record('aisoftskills', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/aisoftskills:manage', $context);

$baseurl = new moodle_url('/mod/aisoftskills/builder.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($instance->name) . ': ' . get_string('setup_title', 'mod_aisoftskills'));
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
$nav = fn(int $back, ?int $next, string $blocked = '') => ['nav' => [
    'backurl' => setuppath::url((int)$cm->id, $back)->out(false),
    'backlabel' => $str('setup_back'),
    'nexturl' => $next ? setuppath::url((int)$cm->id, $next)->out(false) : null,
    'nextlabel' => $next ? $str('setup_next', $str('setupstep_' . setuppath::STEPS[$next - 1])) : '',
    'blocked' => $blocked,
]];

if ($step === 'resume') {
    $state = setuppath::state($instance, $context);
    redirect(setuppath::url((int)$cm->id, setuppath::resume_step($state, (bool)($choices['keys'] || $choices['custom']))));
}

if ($step === 'finish') {
    $state = setuppath::state($instance, $context);
    $todo = [];
    if (!$state['scenes']) {
        $todo[] = $str('finish_noscenes');
    }
    if ($state['nopicture']) {
        $todo[] = $str('finish_nopicture', count($state['nopicture']));
    }
    if ($state['noresponses']) {
        $todo[] = $str('finish_noresponses', count($state['noresponses']));
    }
    $data = [
        'bar' => setuppath::bar(setuppath::FINISH),
        'title' => $str('setupstep_finish'),
        'ready' => $state['ready'],
        'scenes' => $state['scenes'],
        'allready' => !$todo,
        'todo' => $todo,
        'viewurl' => (new moodle_url('/mod/aisoftskills/view.php', ['id' => $cm->id]))->out(false),
        'courseurl' => course_get_url($course, $cm->sectionnum)->out(false),
    ] + $nav(setuppath::CHECK, null);
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('mod_aisoftskills/setup_finish', $data);
    echo $OUTPUT->footer();
    exit;
}

if ($step === 'build') {
    $provider = factory::get();
    $balance = $provider->balance();
    $names = array_merge(array_map(fn($k) => catalogue::skill_name($k), $choices['keys']), $choices['custom']);
    $templatedata = [
        'cmid' => (int)$cm->id,
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
        'bar' => setuppath::bar(setuppath::CREATE),
        'nexturl' => setuppath::url((int)$cm->id, setuppath::PICTURES)->out(false),
    ];
    $templatedata += $nav(4, setuppath::PICTURES, $templatedata['existing'] ? '' : $str('setup_needscene'));
    if ($provider->can_draft() && has_capability('mod/aisoftskills:useai', $context)) {
        $sm = get_string_manager();
        $skills = lesson::chosen_skills($instance);
        $level = $sm->get_string('level_' . $instance->level, 'mod_aisoftskills', null, 'en');
        $industry = lesson::industry_english($instance);
        $templatedata += [
            'canai' => true,
            'draftcredits' => \mod_aisoftskills\local\ai\lmslabs::TEXT_CREDITS,
            'brief' => $sm->get_string('aidraft_briefdefault', 'mod_aisoftskills', (object)[
                'level' => $level,
                'skills' => $skills ? implode(', ', $skills) : $sm->get_string('aidraft_anyskills', 'mod_aisoftskills', null, 'en'),
                'industry' => $industry,
                'language' => catalogue::language_english((string)$instance->contentlang),
            ], 'en'),
            'audience' => $level,
            'context' => $industry,
            'requests' => array_map(
                fn($r) => requests::export($r, $context),
                requests::open((int)$instance->id, requests::SCENE)
            ),
        ];
    }
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
    'bar' => setuppath::bar(max(1, min(4, $start))),
];
$PAGE->requires->js_call_amd('mod_aisoftskills/builder', 'initWizard', ['#ss-wizard', max(1, min(4, $start))]);
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_aisoftskills/builder', $templatedata);
echo $OUTPUT->footer();
