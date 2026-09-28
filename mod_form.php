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
 * Activity settings form.
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

use mod_aisoftskills\local\catalogue;
use mod_aisoftskills\local\manager;

/**
 * Activity settings form for mod_aisoftskills.
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_aisoftskills_mod_form extends moodleform_mod {
    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;
        $c = 'mod_aisoftskills';
        $config = get_config($c);

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $this->standard_intro_elements();

        // Workplace.
        $mform->addElement('header', 'workplacehdr', get_string('workplace', $c));
        $mform->setExpanded('workplacehdr');
        $mform->addElement('select', 'industry', get_string('industry', $c), catalogue::industry_options());
        $mform->setDefault('industry', $config->defaultindustry ?? 'office');
        $mform->addHelpButton('industry', 'industry', $c);
        $mform->addElement('text', 'customindustry', get_string('customindustry', $c), ['size' => 40]);
        $mform->setType('customindustry', PARAM_TEXT);
        $mform->hideIf('customindustry', 'industry', 'neq', 'custom');
        $mform->addElement('select', 'level', get_string('level', $c), catalogue::level_options());
        $mform->setDefault('level', 'supervisor');
        $mform->addHelpButton('level', 'level', $c);
        $mform->addElement('select', 'contentlang', get_string('contentlang', $c), catalogue::language_options());
        $mform->setDefault('contentlang', $config->defaultcontentlang ?? 'en');
        $mform->addHelpButton('contentlang', 'contentlang', $c);
        $mform->addElement('select', 'imagestyle', get_string('imagestyle', $c), [
            'illustration' => get_string('imagestyle_illustration_name', $c),
            'photo' => get_string('imagestyle_photo_name', $c),
        ]);

        // Play.
        $mform->addElement('header', 'playhdr', get_string('playsettings', $c));
        $mform->addElement('advcheckbox', 'allowretry', get_string('allowretry', $c), get_string('allowretry_desc', $c));
        $mform->setDefault('allowretry', 1);
        $mform->addElement('advcheckbox', 'shuffleoptions', get_string('shuffleoptions', $c));
        $mform->setDefault('shuffleoptions', 1);
        $mform->addElement('advcheckbox', 'sounds', get_string('sounds', $c), get_string('sounds_desc', $c));
        $mform->setDefault('sounds', $config->defaultsounds ?? 1);
        $attemptoptions = [0 => get_string('unlimited')] + array_combine(range(1, 10), range(1, 10));
        $mform->addElement('select', 'maxattempts', get_string('maxattempts', $c), $attemptoptions);

        // Grade.
        $this->standard_grading_coursemodule_elements();
        $mform->setDefault('grade', 100);
        $mform->addElement('select', 'grademethod', get_string('grademethod', $c), [
            manager::GRADE_HIGHEST => get_string('gradehighest', $c),
            manager::GRADE_AVERAGE => get_string('gradeaverage', $c),
            manager::GRADE_FIRST => get_string('gradefirst', $c),
            manager::GRADE_LAST => get_string('gradelast', $c),
        ]);
        $mform->addHelpButton('grademethod', 'grademethod', $c);
        $mform->hideIf('grademethod', 'grade[modgrade_type]', 'eq', 'none');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Returns the form element name with the completion suffix (Moodle 4.3+).
     *
     * @param string $name
     * @return string
     */
    protected function suffixed(string $name): string {
        return method_exists($this, 'get_suffix') ? $name . $this->get_suffix() : $name;
    }

    /**
     * Adds custom completion rules.
     *
     * @return array element names
     */
    public function add_completion_rules() {
        $mform = $this->_form;
        $name = $this->suffixed('completionallscenes');
        $mform->addElement(
            'advcheckbox',
            $name,
            get_string('completionallscenes', 'mod_aisoftskills'),
            get_string('completionallscenes_desc', 'mod_aisoftskills')
        );
        $mform->addHelpButton($name, 'completionallscenes', 'mod_aisoftskills');
        return [$name];
    }

    /**
     * Whether a custom completion rule is enabled.
     *
     * @param array $data
     * @return bool
     */
    public function completion_rule_enabled($data) {
        return !empty($data[$this->suffixed('completionallscenes')]);
    }

    /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array errors
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (($data['industry'] ?? '') === 'custom' && trim((string)($data['customindustry'] ?? '')) === '') {
            $errors['customindustry'] = get_string('required');
        }
        return $errors;
    }
}
