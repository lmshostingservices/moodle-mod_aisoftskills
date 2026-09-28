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
 * Scene manager: pictures, order, and adding or removing scenes.
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use mod_aisoftskills\local\ai\factory;
use mod_aisoftskills\local\catalogue;
use mod_aisoftskills\local\lesson;
use mod_aisoftskills\local\manager;

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$sceneid = optional_param('sceneid', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'aisoftskills');
$instance = $DB->get_record('aisoftskills', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/aisoftskills:manage', $context);

$baseurl = new moodle_url('/mod/aisoftskills/scenes.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($instance->name) . ': ' . get_string('managescenes', 'mod_aisoftskills'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->activityheader->disable();
if ($node = $PAGE->settingsnav->find('aisoftskills_scenes', navigation_node::TYPE_SETTING)) {
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
foreach ($scenes as $s) {
    $i++;
    [$url] = manager::get_scene_image($context, (int)$s->id);
    $list = array_values(array_filter($options[$s->id], fn($o) => trim((string)$o->text) !== ''));
    $hasbest = count(array_filter($list, fn($o) => (int)$o->best === 1)) === 1;
    $responsesok = count($list) === manager::OPTIONS && $hasbest;
    $cards[] = [
        'id' => (int)$s->id,
        'number' => $i,
        'title' => format_string($s->title, true, ['context' => $context]),
        'skill' => format_string((string)$s->skill, true, ['context' => $context]),
        'image' => $url,
        'noimage' => !$url,
        'responses' => count($list),
        'responsesok' => $responsesok,
        'ready' => $url && $responsesok,
        'imageprompt' => lesson::image_prompt($instance, $s),
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

$PAGE->requires->js_call_amd('mod_aisoftskills/scenes', 'init', ['#ss-scenes']);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_aisoftskills/scenes', [
    'cards' => $cards,
    'hasscenes' => $total > 0,
    'industry' => catalogue::industry_name($instance->industry, (string)$instance->customindustry),
    'count' => $total,
    'viewurl' => (new moodle_url('/mod/aisoftskills/view.php', ['id' => $cm->id]))->out(false),
    'builderurl' => (new moodle_url('/mod/aisoftskills/builder.php', ['id' => $cm->id]))->out(false),
    'addform' => $addform->render(),
]);
echo $OUTPUT->footer();
