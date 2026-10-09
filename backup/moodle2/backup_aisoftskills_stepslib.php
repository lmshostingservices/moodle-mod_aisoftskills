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
 * Backup structure for mod_aisoftskills.
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Backup structure step.
 */
class backup_aisoftskills_activity_structure_step extends backup_activity_structure_step {
    /**
     * Defines the structure.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $root = new backup_nested_element('aisoftskills', ['id'], [
            'name', 'intro', 'introformat', 'industry', 'customindustry', 'level', 'contentlang', 'skills', 'imagestyle',
            'allowretry', 'practicemode', 'testmode', 'passmark', 'shuffleoptions', 'sounds', 'voiceparts', 'mustlisten',
            'voicemap', 'grade',
            'grademethod', 'maxattempts', 'completionallscenes', 'completionpasstest',
            'timecreated', 'timemodified',
        ]);
        $scenes = new backup_nested_element('scenes');
        $scene = new backup_nested_element('scene', ['id'], ['sortorder', 'skill', 'title', 'context', 'speaker',
            'question', 'imageprompt', 'script', 'teachingnote', 'labels', 'timecreated', 'timemodified']);
        $options = new backup_nested_element('options');
        $option = new backup_nested_element('option', ['id'], ['sortorder', 'text', 'best', 'kpi', 'kpidelta',
            'consequence', 'reason']);
        $attempts = new backup_nested_element('attempts');
        $attempt = new backup_nested_element('attempt', ['id'], ['userid', 'attempt', 'state', 'playmode', 'sceneorder', 'score',
            'kpis', 'timestart', 'timefinish', 'timemodified']);
        $choices = new backup_nested_element('choices');
        $choice = new backup_nested_element('choice', ['id'], ['sceneid', 'optionid', 'best', 'tries', 'resolved',
            'timecreated', 'timemodified']);

        $root->add_child($scenes);
        $scenes->add_child($scene);
        $scene->add_child($options);
        $options->add_child($option);
        $root->add_child($attempts);
        $attempts->add_child($attempt);
        $attempt->add_child($choices);
        $choices->add_child($choice);

        $root->set_source_table('aisoftskills', ['id' => backup::VAR_ACTIVITYID]);
        $scene->set_source_table('aisoftskills_scene', ['aisoftskillsid' => backup::VAR_PARENTID], 'sortorder ASC');
        $option->set_source_table('aisoftskills_option', ['sceneid' => backup::VAR_PARENTID], 'sortorder ASC');
        if ($userinfo) {
            $attempt->set_source_table('aisoftskills_attempt', ['aisoftskillsid' => backup::VAR_PARENTID], 'id ASC');
            $choice->set_source_table('aisoftskills_choice', ['attemptid' => backup::VAR_PARENTID], 'id ASC');
        }
        $attempt->annotate_ids('user', 'userid');

        $root->annotate_files('mod_aisoftskills', 'intro', null);
        $scene->annotate_files('mod_aisoftskills', 'sceneimage', 'id');
        $scene->annotate_files('mod_aisoftskills', 'voiceover', 'id');

        return $this->prepare_activity_structure($root);
    }
}
