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
        $mform->addElement('textarea', 'context', get_string('scenecontext', $c), ['rows' => 3, 'cols' => 60] + $dir);
        $mform->setType('context', PARAM_TEXT);
        $mform->addHelpButton('context', 'scenecontext', $c);
        $mform->addElement('text', 'speaker', get_string('scenespeaker', $c), ['size' => 40] + $dir);
        $mform->setType('speaker', PARAM_TEXT);
        $mform->addElement('text', 'question', get_string('scenequestion', $c), ['size' => 60] + $dir);
        $mform->setType('question', PARAM_TEXT);
        $mform->addElement('textarea', 'imageprompt', get_string('sceneimageprompt', $c), ['rows' => 3, 'cols' => 60]);
        $mform->setType('imageprompt', PARAM_TEXT);
        $mform->addHelpButton('imageprompt', 'sceneimageprompt', $c);

        $kpis = catalogue::kpi_options();
        for ($i = 0; $i < manager::OPTIONS; $i++) {
            $letter = chr(65 + $i);
            $mform->addElement('header', "optionhdr{$i}", get_string('responsex', $c, $letter));
            $mform->setExpanded("optionhdr{$i}");
            $mform->addElement('textarea', "text{$i}", get_string('responsetext', $c), ['rows' => 2, 'cols' => 60] + $dir);
            $mform->setType("text{$i}", PARAM_TEXT);
            $mform->addRule("text{$i}", null, 'required', null, 'client');
            $mform->addElement('select', "kpi{$i}", get_string('responsekpi', $c), $kpis);
            $mform->addHelpButton("kpi{$i}", 'responsekpi', $c);
            $mform->addElement('text', "kpidelta{$i}", get_string('responsedelta', $c), ['size' => 4]);
            $mform->setType("kpidelta{$i}", PARAM_INT);
            $mform->addHelpButton("kpidelta{$i}", 'responsedelta', $c);
            $mform->addElement(
                'textarea',
                "consequence{$i}",
                get_string('responseconsequence', $c),
                ['rows' => 3, 'cols' => 60] + $dir
            );
            $mform->setType("consequence{$i}", PARAM_TEXT);
            $mform->addHelpButton("consequence{$i}", 'responseconsequence', $c);
            $mform->addElement('textarea', "reason{$i}", get_string('responsereason', $c), ['rows' => 2, 'cols' => 60] + $dir);
            $mform->setType("reason{$i}", PARAM_TEXT);
        }
        $mform->addElement('header', 'besthdr', get_string('bestresponse', $c));
        $mform->setExpanded('besthdr');
        $group = [];
        for ($i = 0; $i < manager::OPTIONS; $i++) {
            $group[] = $mform->createElement('radio', 'best', '', get_string('responsex', $c, chr(65 + $i)), $i);
        }
        $mform->addGroup($group, 'bestgroup', get_string('bestresponse', $c), ' ', false);
        $mform->addHelpButton('bestgroup', 'bestresponse', $c);
        $mform->setDefault('best', 0);
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
        $best = (int)($data['best'] ?? -1);
        if ($best < 0 || $best >= manager::OPTIONS) {
            $errors['bestgroup'] = get_string('required');
        }
        for ($i = 0; $i < manager::OPTIONS; $i++) {
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
        return $errors;
    }
}
