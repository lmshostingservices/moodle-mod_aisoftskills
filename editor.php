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
 * Scene editor: the moment, the question and the two responses with their consequences.
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use mod_aisoftskills\local\catalogue;
use mod_aisoftskills\local\manager;

$id = required_param('id', PARAM_INT);
$sceneid = required_param('sceneid', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'aisoftskills');
$instance = $DB->get_record('aisoftskills', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/aisoftskills:manage', $context);
$scene = $DB->get_record('aisoftskills_scene', ['id' => $sceneid, 'aisoftskillsid' => $instance->id], '*', MUST_EXIST);

$url = new moodle_url('/mod/aisoftskills/editor.php', ['id' => $cm->id, 'sceneid' => $scene->id]);
$backurl = new moodle_url('/mod/aisoftskills/scenes.php', ['id' => $cm->id]);
$PAGE->set_url($url);
$PAGE->set_title(format_string($instance->name) . ': ' . format_string($scene->title));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->activityheader->disable();
if ($node = $PAGE->settingsnav->find('aisoftskills_scenes', navigation_node::TYPE_SETTING)) {
    $node->make_active();
}

$form = new \mod_aisoftskills\form\scene_form($url, ['rtl' => catalogue::is_rtl((string)$instance->contentlang)]);
if ($form->is_cancelled()) {
    redirect($backurl);
} else if ($data = $form->get_data()) {
    $options = [];
    for ($i = 0; $i < manager::OPTIONS; $i++) {
        $options[] = [
            'text' => $data->{"text{$i}"},
            'best' => (int)$data->best === $i,
            'kpi' => $data->{"kpi{$i}"},
            'kpidelta' => $data->{"kpidelta{$i}"},
            'consequence' => $data->{"consequence{$i}"},
            'reason' => $data->{"reason{$i}"},
        ];
    }
    manager::save_scene($scene, [
        'title' => $data->title,
        'skill' => $data->skill,
        'context' => $data->context,
        'speaker' => $data->speaker,
        'question' => $data->question,
        'imageprompt' => $data->imageprompt,
    ], $options);
    redirect($backurl, get_string('scenesaved', 'mod_aisoftskills'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$defaults = ['id' => $cm->id, 'sceneid' => $scene->id, 'title' => $scene->title, 'skill' => $scene->skill,
    'context' => $scene->context, 'speaker' => $scene->speaker, 'question' => $scene->question,
    'imageprompt' => $scene->imageprompt, 'best' => 0];
$existing = manager::get_options([$scene->id])[$scene->id];
foreach (array_values($existing) as $i => $option) {
    if ($i >= manager::OPTIONS) {
        break;
    }
    $defaults += ["text{$i}" => $option->text, "kpi{$i}" => $option->kpi, "kpidelta{$i}" => (int)$option->kpidelta,
        "consequence{$i}" => $option->consequence, "reason{$i}" => $option->reason];
    if ((int)$option->best === 1) {
        $defaults['best'] = $i;
    }
}
for ($i = count($existing); $i < manager::OPTIONS; $i++) {
    $defaults += ["kpidelta{$i}" => $i === 0 ? 20 : -20];
}
$form->set_data($defaults);

[$image] = manager::get_scene_image($context, (int)$scene->id);
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_aisoftskills/editor', [
    'title' => format_string($scene->title, true, ['context' => $context]),
    'image' => $image,
    'backurl' => $backurl->out(false),
    'replaceurl' => (new moodle_url($backurl, ['action' => 'replace', 'sceneid' => $scene->id]))->out(false),
    'form' => $form->render(),
]);
echo $OUTPUT->footer();
