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

namespace mod_aisoftskills\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use mod_aisoftskills\local\catalogue;
use mod_aisoftskills\local\manager;

/**
 * Edits one scene and its two responses.
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scene_form extends \moodleform {
    /**
     * Definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $c = 'mod_aisoftskills';
        $dir = !empty($this->_customdata['rtl']) ? ['dir' => 'rtl'] : ['dir' => 'auto'];
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'sceneid');
        $mform->setType('sceneid', PARAM_INT);

        $mform->addElement('header', 'scenehdr', get_string('scene', $c));
        $mform->addElement('text', 'title', get_string('scenetitle', $c), ['size' => 60] + $dir);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');
        $mform->addRule('title', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addElement('text', 'skill', get_string('sceneskill', $c), ['size' => 40] + $dir);
        $mform->setType('skill', PARAM_TEXT);
        $mform->addElement('textarea', 'context', get_string('scenecontext', $c), ['rows' => 3, 'cols' => 60,
            'data-ss-limit' => manager::LIMITS['context']] + $dir);
        $mform->setType('context', PARAM_TEXT);
        $mform->addHelpButton('context', 'scenecontext', $c);
        $mform->addElement('textarea', 'scripttext', get_string('scenescript', $c), ['rows' => 5, 'cols' => 60] + $dir);
        $mform->setType('scripttext', PARAM_TEXT);
        $mform->addHelpButton('scripttext', 'scenescript', $c);
        $mform->addElement('text', 'speaker', get_string('scenespeaker', $c), ['size' => 40,
            'data-ss-limit' => manager::LIMITS['speaker']] + $dir);
        $mform->setType('speaker', PARAM_TEXT);
        $mform->addElement('text', 'question', get_string('scenequestion', $c), ['size' => 60,
            'data-ss-limit' => manager::LIMITS['question']] + $dir);
        $mform->setType('question', PARAM_TEXT);
        $mform->addElement('textarea', 'imageprompt', get_string('sceneimageprompt', $c), ['rows' => 3, 'cols' => 60]);
        $mform->setType('imageprompt', PARAM_TEXT);
        $mform->addHelpButton('imageprompt', 'sceneimageprompt', $c);
        $mform->addElement('textarea', 'teachingnote', get_string('sceneteachingnote', $c), ['rows' => 3, 'cols' => 60] + $dir);
        $mform->setType('teachingnote', PARAM_TEXT);
        $mform->addHelpButton('teachingnote', 'sceneteachingnote', $c);

        $kpis = catalogue::kpi_options();
        for ($i = 0; $i < manager::MAX_OPTIONS; $i++) {
            $letter = chr(65 + $i);
            $optional = $i >= manager::OPTIONS;
            $mform->addElement('header', "optionhdr{$i}", get_string($optional ? 'responsex_optional' : 'responsex', $c, $letter));
            $mform->setExpanded("optionhdr{$i}");
            if ($optional) {
                $mform->addElement('static', "optionhelp{$i}", '', get_string('responsec_help', $c));
            }
            $mform->addElement('textarea', "text{$i}", get_string('responsetext', $c), ['rows' => 2, 'cols' => 60,
                'data-ss-limit' => manager::LIMITS['text']] + $dir);
            $mform->setType("text{$i}", PARAM_TEXT);
            if (!$optional) {
                $mform->addRule("text{$i}", null, 'required', null, 'client');
            }
            $mform->addElement('select', "kpi{$i}", get_string('responsekpi', $c), $kpis);
            $mform->addHelpButton("kpi{$i}", 'responsekpi', $c);
            $mform->addElement('text', "kpidelta{$i}", get_string('responsedelta', $c), ['size' => 4]);
            $mform->setType("kpidelta{$i}", PARAM_INT);
            $mform->addHelpButton("kpidelta{$i}", 'responsedelta', $c);
            $mform->addElement(
                'textarea',
                "consequence{$i}",
                get_string('responseconsequence', $c),
                ['rows' => 3, 'cols' => 60, 'data-ss-limit' => manager::LIMITS['consequence']] + $dir
            );
            $mform->setType("consequence{$i}", PARAM_TEXT);
            $mform->addHelpButton("consequence{$i}", 'responseconsequence', $c);
            $mform->addElement('textarea', "reason{$i}", get_string('responsereason', $c), ['rows' => 2, 'cols' => 60,
                'data-ss-limit' => manager::LIMITS['reason']] + $dir);
            $mform->setType("reason{$i}", PARAM_TEXT);
        }
        $mform->addElement('header', 'besthdr', get_string('bestresponse', $c));
        $mform->setExpanded('besthdr');
        $group = [];
        for ($i = 0; $i < manager::MAX_OPTIONS; $i++) {
            $group[] = $mform->createElement('radio', 'best', '', get_string('responsex', $c, chr(65 + $i)), $i);
        }
        $mform->addGroup($group, 'bestgroup', get_string('bestresponse', $c), ' ', false);
        $mform->addHelpButton('bestgroup', 'bestresponse', $c);
        $mform->setDefault('best', 0);
        $group = [$mform->createElement('radio', 'worst', '', get_string('worstresponse_none', $c), -1)];
        for ($i = 0; $i < manager::MAX_OPTIONS; $i++) {
            $group[] = $mform->createElement('radio', 'worst', '', get_string('responsex', $c, chr(65 + $i)), $i);
        }
        $mform->addGroup($group, 'worstgroup', get_string('worstresponse', $c), ' ', false);
        $mform->addHelpButton('worstgroup', 'worstresponse', $c);
        $mform->setDefault('worst', -1);
        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * The best response needs a positive change; the other a negative or no change.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $count = trim((string)($data['text' . manager::OPTIONS] ?? '')) !== '' ? manager::MAX_OPTIONS : manager::OPTIONS;
        $best = (int)($data['best'] ?? -1);
        if ($best < 0 || $best >= $count) {
            $errors['bestgroup'] = get_string('required');
        }
        $worst = $count === manager::MAX_OPTIONS ? (int)($data['worst'] ?? -1) : -1;
        if ($count === manager::MAX_OPTIONS && ($worst < 0 || $worst >= $count || $worst === $best)) {
            $errors['worstgroup'] = get_string('worstrequired', 'mod_aisoftskills');
        }
        // Short enough to sit neatly on the page.
        $limit = function (string $name, string $field) use ($data, &$errors) {
            $length = \core_text::strlen(trim((string)($data[$name] ?? '')));
            if ($length > manager::LIMITS[$field]) {
                $errors[$name] = get_string(
                    'toolong_field',
                    'mod_aisoftskills',
                    (object)['max' => manager::LIMITS[$field], 'length' => $length]
                );
            }
        };
        foreach (['context', 'question', 'speaker'] as $field) {
            $limit($field, $field);
        }
        for ($i = 0; $i < $count; $i++) {
            foreach (['text', 'consequence', 'reason'] as $field) {
                $limit($field . $i, $field);
            }
            if (trim((string)($data["text{$i}"] ?? '')) === '') {
                $errors["text{$i}"] = get_string('required');
            }
            $delta = (int)($data["kpidelta{$i}"] ?? 0);
            if (abs($delta) > catalogue::MAX_DELTA) {
                $errors["kpidelta{$i}"] = get_string('deltarange', 'mod_aisoftskills', catalogue::MAX_DELTA);
            } else if ($i === $best && $delta <= 0) {
                $errors["kpidelta{$i}"] = get_string('deltabest', 'mod_aisoftskills');
            } else if ($i !== $best && $delta > 0) {
                $errors["kpidelta{$i}"] = get_string('deltaother', 'mod_aisoftskills');
            }
        }
        // The very poor response does double the harm of the poorer one.
        if ($worst >= 0 && $worst !== $best && empty($errors["kpidelta{$worst}"])) {
            foreach (range(0, $count - 1) as $i) {
                $floor = max(-catalogue::MAX_DELTA, 2 * min(-5, (int)($data["kpidelta{$i}"] ?? 0)));
                if ($i !== $best && $i !== $worst && (int)($data["kpidelta{$worst}"] ?? 0) > $floor) {
                    $errors["kpidelta{$worst}"] = get_string(
                        'deltaworst',
                        'mod_aisoftskills',
                        $floor
                    );
                }
            }
        }
        return $errors;
    }
}
