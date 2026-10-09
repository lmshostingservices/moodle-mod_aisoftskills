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
 * Restore structure for mod_aisoftskills.
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restore structure step.
 */
class restore_aisoftskills_activity_structure_step extends restore_activity_structure_step {
    /** @var bool A backup from before 1.4.0 of an activity without a second try: its attempts were tests. */
    protected $wastest = false;

    /**
     * Defines the paths.
     *
     * @return array
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');
        $base = '/activity/aisoftskills';
        $paths = [
            new restore_path_element('aisoftskills', $base),
            new restore_path_element('aisoftskills_scene', $base . '/scenes/scene'),
            new restore_path_element('aisoftskills_option', $base . '/scenes/scene/options/option'),
        ];
        if ($userinfo) {
            $paths[] = new restore_path_element('aisoftskills_attempt', $base . '/attempts/attempt');
            $paths[] = new restore_path_element('aisoftskills_choice', $base . '/attempts/attempt/choices/choice');
        }
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restores the instance.
     *
     * @param array $data
     */
    protected function process_aisoftskills($data) {
        global $DB;
        $data = (object)$data;
        $data->course = $this->get_courseid();
        $data->timemodified = time();
        if (!isset($data->practicemode) && isset($data->allowretry)) {
            // A backup from before 1.4.0: "try again" was practice, no second try was a test without a pass mark.
            $this->wastest = empty($data->allowretry);
            $data->practicemode = $this->wastest ? 0 : 1;
            $data->testmode = $this->wastest ? 1 : 0;
            $data->passmark = 0;
        }
        $newid = $DB->insert_record('aisoftskills', $data);
        $this->apply_activity_instance($newid);
    }

    /**
     * Restores a scene.
     *
     * @param array $data
     */
    protected function process_aisoftskills_scene($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->aisoftskillsid = $this->get_new_parentid('aisoftskills');
        $newid = $DB->insert_record('aisoftskills_scene', $data);
        $this->set_mapping('aisoftskills_scene', $oldid, $newid, true);
    }

    /**
     * Restores a response option.
     *
     * @param array $data
     */
    protected function process_aisoftskills_option($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->sceneid = $this->get_new_parentid('aisoftskills_scene');
        $newid = $DB->insert_record('aisoftskills_option', $data);
        $this->set_mapping('aisoftskills_option', $oldid, $newid);
    }

    /**
     * Restores an attempt, remapping its scene order to the new scene and option ids.
     *
     * @param array $data
     */
    protected function process_aisoftskills_attempt($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->aisoftskillsid = $this->get_new_parentid('aisoftskills');
        if (!isset($data->playmode)) {
            $data->playmode = $this->wastest ? 'test' : 'practice';
        }
        $data->userid = $this->get_mappingid('user', $data->userid);
        if (!$data->userid) {
            return;
        }
        $order = json_decode((string)$data->sceneorder, true);
        $neworder = [];
        foreach (is_array($order) ? $order : [] as $item) {
            $sceneid = (int)$this->get_mappingid('aisoftskills_scene', $item['scene'] ?? 0);
            if (!$sceneid) {
                continue;
            }
            $optionids = array_map(fn($o) => (int)$this->get_mappingid('aisoftskills_option', $o), (array)($item['options'] ?? []));
            $neworder[] = ['scene' => $sceneid, 'options' => array_values(array_filter($optionids))];
        }
        $data->sceneorder = json_encode($neworder);
        $newid = $DB->insert_record('aisoftskills_attempt', $data);
        $this->set_mapping('aisoftskills_attempt', $oldid, $newid);
    }

    /**
     * Restores a choice.
     *
     * @param array $data
     */
    protected function process_aisoftskills_choice($data) {
        global $DB;
        $data = (object)$data;
        $data->attemptid = $this->get_new_parentid('aisoftskills_attempt');
        $data->sceneid = (int)$this->get_mappingid('aisoftskills_scene', $data->sceneid);
        $data->optionid = (int)$this->get_mappingid('aisoftskills_option', $data->optionid);
        if ($data->attemptid && $data->sceneid && $data->optionid) {
            $DB->insert_record('aisoftskills_choice', $data);
        }
    }

    /**
     * Restores files after the structure.
     */
    protected function after_execute() {
        $this->add_related_files('mod_aisoftskills', 'intro', null);
        $this->add_related_files('mod_aisoftskills', 'sceneimage', 'aisoftskills_scene');
        $this->add_related_files('mod_aisoftskills', 'voiceover', 'aisoftskills_scene');
    }
}
