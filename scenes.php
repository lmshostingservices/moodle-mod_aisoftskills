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
use mod_aisoftskills\local\labels;
use mod_aisoftskills\local\setuppath;
use mod_aisoftskills\local\voiceover;

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$sceneid = optional_param('sceneid', 0, PARAM_INT);
$steps = ['pictures' => setuppath::PICTURES, 'voices' => setuppath::VOICES];
$step = $steps[optional_param('step', 'check', PARAM_ALPHA)] ?? setuppath::CHECK;

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

// The whole plugin needs this site to be unlocked with LMS Labs (50 credits or a recognised Marketplace purchase).
if (!\mod_aisoftskills\local\unlock::active()) {
    echo $OUTPUT->header();
    echo \mod_aisoftskills\local\unlock::locked_notice();
    echo $OUTPUT->footer();
    exit;
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

// Scenes are created only in step 5, where every way of creating one is charged.
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
// Voiceover: what is still to make, scene by scene (only worked out on the voiceover step).
$voicestep = $step === setuppath::VOICES;
$canvoice = $voicestep && voiceover::enabled() && has_capability('mod/aisoftskills:useai', $context);
$voiceconfig = $canvoice ? voiceover::config($instance) : null;
// The name labels editor shows the voice each person really gets (free: the catalogue is cached).
$labelvoices = $step === setuppath::PICTURES && voiceover::enabled() ? voiceover::config($instance) : null;
$labelvoices = $labelvoices && $labelvoices['locale'] !== '' ? $labelvoices : null;
$clips = [];
foreach ($scenes as $s) {
    $i++;
    [$url] = manager::get_scene_image($context, (int)$s->id);
    $responsesok = setuppath::responses_ok($options[$s->id]);
    if (!$url) {
        $missing[] = (int)$s->id;
    }
    $noresponses += $responsesok ? 0 : 1;
    $saved = labels::get($s);
    $toclip = [];
    if ($voiceconfig && $voiceconfig['locale'] !== '') {
        foreach (voiceover::segments($s, $voiceconfig, $options[$s->id], voiceover::parts($instance)) as $segment) {
            if (voiceover::url($context, (int)$s->id, $segment) === '') {
                $toclip[] = $segment['index'];
                $clips[] = $s->id . ':' . $segment['index'];
            }
        }
    }
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
        'labelsjson' => json_encode($saved ?: labels::suggest($s), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'labelssuggested' => $saved ? 0 : 1,
        // The scenario, so the teacher can read who is who while placing the labels.
        'scenariojson' => json_encode([
            // Shown with textContent, never as HTML: plain text, not escaped twice.
            'context' => format_string((string)$s->context, true, ['context' => $context, 'escape' => false]),
            'lines' => array_map(fn($l) => [
                'speaker' => format_string((string)$l['speaker'], true, ['context' => $context, 'escape' => false]),
                'line' => format_string((string)$l['line'], true, ['context' => $context, 'escape' => false]),
            ], manager::dialogue($s->script)),
            'speaker' => format_string((string)$s->speaker, true, ['context' => $context, 'escape' => false]),
            // Everyone the scenario names, so people without a label can be added with one click.
            'people' => array_values(array_map(fn($l) => [
                'text' => format_string($l['text'], true, ['context' => $context, 'escape' => false]),
                'gender' => $l['gender'],
            ], array_filter(labels::suggest($s), fn($l) => !$l['you']))),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'labelvoicesjson' => json_encode($labelvoices ? voiceover::label_voices($s, $labelvoices) : []),
        'labelcount' => count($saved),
        'labelsline' => $saved ? (count($saved) === 1 ? get_string('labels_one', 'mod_aisoftskills')
            : get_string('labels_count', 'mod_aisoftskills', count($saved))) : get_string('labels_none', 'mod_aisoftskills'),
        'labels' => array_map(fn($l) => ['text' => format_string($l['text'], true, ['context' => $context]),
            'x' => $l['x'], 'y' => $l['y'], 'you' => $l['you']], $saved),
        'toolong' => (bool)manager::too_long($s, $options[$s->id]),
        'voiceready' => $voiceconfig && !$toclip,
        'voicemissing' => $toclip ? get_string('voice_scene_missing', 'mod_aisoftskills', count($toclip)) : '',
    ];
}

$str = fn($k, $a = null) => get_string($k, 'mod_aisoftskills', $a);
if ($step === setuppath::PICTURES) {
    $blocked = $missing ? $str('setup_needpictures', count($missing)) : '';
    $back = setuppath::CREATE;
} else if ($step === setuppath::VOICES) {
    // Voiceover is optional: it never blocks the next step.
    $blocked = '';
    $back = setuppath::PICTURES;
} else {
    $blocked = $noresponses ? $str('setup_needresponses', $noresponses)
        : ($missing ? $str('setup_needpictures', count($missing)) : '');
    $back = setuppath::VOICES;
}
if (!$total) {
    $blocked = $str('setup_needscene');
}
$next = $step + 1;

$PAGE->requires->js_call_amd('mod_aisoftskills/scenes', 'init', ['#ss-scenes']);
if ($step === setuppath::PICTURES) {
    $narrator = $labelvoices ? preg_replace('/^.*-Chirp3-HD-/', '', $labelvoices['narrator'])
        : ((string)get_config('mod_aisoftskills', 'narratorvoice') ?: voiceover::DEFAULT_NARRATOR);
    // Only the voices LMS Labs offers for this activity's language (all 8 while the catalogue is not known).
    $offered = $labelvoices ? array_keys($labelvoices['types']) : voiceover::VOICETYPES;
    $genders = array_map(fn($list) => array_values(array_intersect($list, $offered)), voiceover::GENDERS);
    $PAGE->requires->js_call_amd('mod_aisoftskills/labels', 'init', ['#ss-scenes', $narrator, $genders]);
}

// Who sounds like what, for the voiceover step.
$voices = [];
if ($voiceconfig && $voiceconfig['locale'] !== '') {
    $typename = function (string $voice): string {
        $type = strtolower(preg_replace('/^.*-Chirp3-HD-/', '', $voice));
        return get_string_manager()->string_exists('voicetype_' . $type, 'mod_aisoftskills')
            ? get_string('voicetype_' . $type, 'mod_aisoftskills') : $voice;
    };
    $voices[] = ['text' => $str('voice_narrator', $typename($voiceconfig['narrator']))];
    $voices[] = ['text' => $str('voice_learner', (object)['f' => $typename($voiceconfig['learner']['f']),
        'm' => $typename($voiceconfig['learner']['m'])])];
    foreach ($voiceconfig['people'] as $person => $voice) {
        $voices[] = ['text' => $str('voice_person', (object)['name' => core_text::strtotitle($person),
            'voice' => $typename($voice)])];
    }
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_aisoftskills/scenes', [
    'bar' => setuppath::bar($step),
    'pictures' => $step === setuppath::PICTURES,
    'voicestep' => $voicestep,
    'voiceon' => $canvoice && $voiceconfig && $voiceconfig['locale'] !== '',
    'voicenolocale' => $canvoice && $voiceconfig && $voiceconfig['locale'] === '',
    'voiceoff' => $voicestep && !$canvoice,
    'voices' => $voices,
    'voicepartsline' => $str('voice_parts', implode('; ', array_map(
        fn($p) => core_text::strtolower(core_text::substr($str('voicepart_' . $p), 0, 1)) .
            core_text::substr($str('voicepart_' . $p), 1),
        voiceover::parts($instance)
    ))),
    'settingsurl' => (new moodle_url('/course/modedit.php', ['update' => $cm->id]))->out(false) . '#id_playhdr',
    'clipcount' => count($clips),
    'clipids' => implode(',', $clips),
    'clipcredits' => count($clips) * \mod_aisoftskills\local\ai\lmslabs::VOICE_CREDITS,
    'voicecredits' => \mod_aisoftskills\local\ai\lmslabs::VOICE_CREDITS,
    // LMS Labs still publishes another price per clip: nothing new is made until it publishes the approved one.
    'pricehold' => voiceover::price_hold() !== null ? get_string('voice_pricehold', 'mod_aisoftskills', [
        'published' => voiceover::price_hold(), 'approved' => \mod_aisoftskills\local\ai\lmslabs::VOICE_CREDITS]) : '',
    // Free remakes (when LMS Labs supports them): each clip is priced before the teacher confirms.
    'remakes' => voiceover::remakes() ? 1 : 0,
    'remakesline' => voiceover::remakes() ? get_string('voice_remakes', 'mod_aisoftskills', (object)voiceover::remakes()) : '',
    'check' => $step === setuppath::CHECK,
    'cards' => $cards,
    'hasscenes' => $total > 0,
    'industry' => catalogue::industry_name($instance->industry, (string)$instance->customindustry),
    'count' => $total,
    'cmid' => (int)$cm->id,
    'canai' => $canai,
    'aioff' => !$canai,
    'missingcount' => count($missing),
    'missingids' => implode(',', $missing),
    'missingcredits' => count($missing) * \mod_aisoftskills\local\ai\lmslabs::IMAGE_CREDITS,
    'imagecredits' => \mod_aisoftskills\local\ai\lmslabs::IMAGE_CREDITS,
    'requests' => $step !== setuppath::CHECK && has_capability('mod/aisoftskills:useai', $context) ? array_map(
        fn($r) => requests::export($r, $context),
        requests::open((int)$instance->id, $voicestep ? requests::VOICE : requests::IMAGE)
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
