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
 * Set-up steps 6 (pictures for every scene) and 7 (check each scene: responses, order, add or remove).
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use mod_aisoftskills\local\ai\factory;
use mod_aisoftskills\local\ai\requests;
use mod_aisoftskills\local\catalogue;
use mod_aisoftskills\local\manager;
use mod_aisoftskills\local\setuppath;

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$sceneid = optional_param('sceneid', 0, PARAM_INT);
$step = optional_param('step', 'check', PARAM_ALPHA) === 'pictures' ? setuppath::PICTURES : setuppath::CHECK;

[$course, $cm] = get_course_and_cm_from_cmid($id, 'aisoftskills');
$instance = $DB->get_record('aisoftskills', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/aisoftskills:manage', $context);

$baseurl = setuppath::url((int)$cm->id, $step);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($instance->name) . ': ' .
    get_string('setupstep_' . setuppath::STEPS[$step - 1], 'mod_aisoftskills'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->activityheader->disable();
if ($node = $PAGE->settingsnav->find('aisoftskills_builder', navigation_node::TYPE_SETTING)) {
    $node->make_active();
}

$scene = null;
if ($sceneid) {
    $scene = $DB->get_record(
        'aisoftskills_scene',
        ['id' => $sceneid, 'aisoftskillsid' => $instance->id],
        '*',
        MUST_EXIST
    );
}

if (($action === 'up' || $action === 'down') && $scene) {
    require_sesskey();
    manager::move_scene($scene, $action === 'up' ? -1 : 1);
    redirect($baseurl);
}
if ($action === 'delete' && $scene) {
    if (optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
        manager::delete_scene($context, $scene);
        redirect(
            $baseurl,
            get_string('scenedeleted', 'mod_aisoftskills'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('confirmdeletescene', 'mod_aisoftskills', format_string($scene->title)),
        new moodle_url($baseurl, ['action' => 'delete', 'sceneid' => $scene->id, 'confirm' => 1, 'sesskey' => sesskey()]),
        $baseurl
    );
    echo $OUTPUT->footer();
    exit;
}
if ($action === 'replace' && $scene) {
    $form = new \mod_aisoftskills\form\replace_image_form($baseurl);
    if ($form->is_cancelled()) {
        redirect($baseurl);
    } else if ($data = $form->get_data()) {
        $ok = manager::replace_scene_image($context, $scene, (int)$data->image);
        redirect(
            $baseurl,
            get_string($ok ? 'imagereplaced' : 'noimagefound', 'mod_aisoftskills'),
            null,
            $ok ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_WARNING
        );
    }
    $draftid = file_get_submitted_draft_itemid('image');
    file_prepare_draft_area(
        $draftid,
        $context->id,
        'mod_aisoftskills',
        'sceneimage',
        $scene->id,
        manager::image_filemanager_options(1)
    );
    $form->set_data(['id' => $cm->id, 'sceneid' => $scene->id, 'image' => $draftid]);
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('replaceimage', 'mod_aisoftskills') . ': ' . format_string($scene->title), 3);
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

$addform = new \mod_aisoftskills\form\add_scenes_form($baseurl);
$addform->set_data(['id' => $cm->id]);
if ($data = $addform->get_data()) {
    $count = manager::create_scenes_from_draft($instance, $context, (int)$data->images);
    if (!$count && trim((string)$data->title) !== '') {
        manager::add_scene($instance, ['title' => (string)$data->title]);
        $count = 1;
    }
    redirect(
        $baseurl,
        get_string('scenescreated', 'mod_aisoftskills', $count),
        null,
        $count ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_WARNING
    );
}

$scenes = manager::get_scenes($instance->id);
$options = manager::get_options(array_keys($scenes));
$provider = factory::get();
$canai = $provider->can_generate() && has_capability('mod/aisoftskills:useai', $context);
$cards = [];
$i = 0;
$total = count($scenes);
$sesskey = sesskey();
$missing = [];
$noresponses = 0;
foreach ($scenes as $s) {
    $i++;
    [$url] = manager::get_scene_image($context, (int)$s->id);
    $responsesok = setuppath::responses_ok($options[$s->id]);
    if (!$url) {
        $missing[] = (int)$s->id;
    }
    $noresponses += $responsesok ? 0 : 1;
    $cards[] = [
        'id' => (int)$s->id,
        'number' => $i,
        'title' => format_string($s->title, true, ['context' => $context]),
        'skill' => format_string((string)$s->skill, true, ['context' => $context]),
        'image' => $url,
        'noimage' => !$url,
        'responsesok' => $responsesok,
        'canai' => $canai,
        'imagecredits' => \mod_aisoftskills\local\ai\lmslabs::IMAGE_CREDITS,
        'editurl' => (new moodle_url('/mod/aisoftskills/editor.php', ['id' => $cm->id, 'sceneid' => $s->id]))->out(false),
        'upurl' => $i > 1 ? (new moodle_url($baseurl, ['action' => 'up', 'sceneid' => $s->id, 'sesskey' => $sesskey]))
            ->out(false) : null,
        'downurl' => $i < $total ? (new moodle_url($baseurl, ['action' => 'down', 'sceneid' => $s->id,
            'sesskey' => $sesskey]))->out(false) : null,
        'replaceurl' => (new moodle_url($baseurl, ['action' => 'replace', 'sceneid' => $s->id]))->out(false),
        'deleteurl' => (new moodle_url($baseurl, ['action' => 'delete', 'sceneid' => $s->id]))->out(false),
    ];
}

$str = fn($k, $a = null) => get_string($k, 'mod_aisoftskills', $a);
if ($step === setuppath::PICTURES) {
    $blocked = $missing ? $str('setup_needpictures', count($missing)) : '';
    $back = setuppath::CREATE;
} else {
    $blocked = $noresponses ? $str('setup_needresponses', $noresponses)
        : ($missing ? $str('setup_needpictures', count($missing)) : '');
    $back = setuppath::PICTURES;
}
if (!$total) {
    $blocked = $str('setup_needscene');
}
$next = $step + 1;

$PAGE->requires->js_call_amd('mod_aisoftskills/scenes', 'init', ['#ss-scenes']);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_aisoftskills/scenes', [
    'bar' => setuppath::bar($step),
    'pictures' => $step === setuppath::PICTURES,
    'check' => $step === setuppath::CHECK,
    'cards' => $cards,
    'hasscenes' => $total > 0,
    'industry' => catalogue::industry_name($instance->industry, (string)$instance->customindustry),
    'count' => $total,
    'addform' => $step === setuppath::CHECK ? $addform->render() : '',
    'cmid' => (int)$cm->id,
    'canai' => $canai,
    'aioff' => !$canai,
    'missingcount' => count($missing),
    'missingids' => implode(',', $missing),
    'missingcredits' => count($missing) * \mod_aisoftskills\local\ai\lmslabs::IMAGE_CREDITS,
    'imagecredits' => \mod_aisoftskills\local\ai\lmslabs::IMAGE_CREDITS,
    'requests' => $step === setuppath::PICTURES && has_capability('mod/aisoftskills:useai', $context) ? array_map(
        fn($r) => requests::export($r, $context),
        requests::open((int)$instance->id, requests::IMAGE)
    ) : [],
    'nav' => [
        'backurl' => setuppath::url((int)$cm->id, $back)->out(false),
        'backlabel' => $str('setup_back'),
        'nexturl' => setuppath::url((int)$cm->id, $next)->out(false),
        'nextlabel' => $str('setup_next', $str('setupstep_' . setuppath::STEPS[$next - 1])),
        'blocked' => $blocked,
    ],
]);
echo $OUTPUT->footer();
