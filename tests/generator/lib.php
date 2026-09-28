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
 * Data generator for mod_aisoftskills.
 *
 * @package    mod_aisoftskills
 * @category   test
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_aisoftskills_generator extends testing_module_generator {
    /**
     * Creates an instance with sensible defaults.
     *
     * @param array|stdClass $record
     * @param array|null $options
     * @return stdClass
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object)(array)$record;
        $defaults = [
            'industry' => 'retail', 'customindustry' => '', 'level' => 'supervisor', 'contentlang' => 'en',
            'skills' => json_encode(['keys' => ['motivation'], 'custom' => [], 'scenes' => 4]),
            'imagestyle' => 'illustration', 'allowretry' => 1, 'shuffleoptions' => 0, 'sounds' => 1, 'grade' => 100,
            'grademethod' => 1, 'maxattempts' => 0, 'completionallscenes' => 0,
        ];
        foreach ($defaults as $key => $value) {
            if (!isset($record->$key)) {
                $record->$key = $value;
            }
        }
        return parent::create_instance($record, (array)$options);
    }

    /**
     * Creates a scene with a picture and two responses (the first is the better one unless $bestindex says otherwise).
     *
     * @param stdClass $instance
     * @param string $title
     * @param string $better text of the better response
     * @param string $poorer text of the other response
     * @param array $extra scene fields (skill, context, speaker, question) and option fields (kpi, betterdelta, poorerdelta)
     * @param string|null $imagepath picture (defaults to the test fixture; '' for no picture)
     * @return stdClass scene
     */
    public function create_scene(
        stdClass $instance,
        string $title,
        string $better = 'Is there anything I can get you to help you reach your goals faster?',
        string $poorer = 'Hurry up!',
        array $extra = [],
        ?string $imagepath = null
    ): stdClass {
        global $CFG, $DB;
        $cm = get_coursemodule_from_instance('aisoftskills', $instance->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        $sceneid = \mod_aisoftskills\local\manager::add_scene($instance, [
            'title' => $title,
            'skill' => $extra['skill'] ?? 'Motivating others',
            'context' => $extra['context'] ?? 'Context of ' . $title,
            'speaker' => $extra['speaker'] ?? 'You, the supervisor',
            'question' => $extra['question'] ?? 'What do you say?',
        ]);
        $imagepath = $imagepath ?? $CFG->dirroot . '/mod/aisoftskills/tests/fixtures/scene.png';
        if ($imagepath !== '') {
            get_file_storage()->create_file_from_pathname([
                'contextid' => $context->id, 'component' => 'mod_aisoftskills', 'filearea' => 'sceneimage',
                'itemid' => $sceneid, 'filepath' => '/', 'filename' => basename($imagepath),
            ], $imagepath);
        }
        $kpi = $extra['kpi'] ?? 'motivation';
        $scene = $DB->get_record('aisoftskills_scene', ['id' => $sceneid], '*', MUST_EXIST);
        \mod_aisoftskills\local\manager::save_scene($scene, [], [
            ['text' => $better, 'best' => 1, 'kpi' => $kpi, 'kpidelta' => $extra['betterdelta'] ?? 20,
                'consequence' => 'The team relaxes and asks for what it needs.', 'reason' => 'Offering help removes blockers.'],
            ['text' => $poorer, 'best' => 0, 'kpi' => $kpi, 'kpidelta' => $extra['poorerdelta'] ?? -20,
                'consequence' => 'People rush and make mistakes.', 'reason' => 'Pressure without support lowers motivation.'],
        ]);
        return $DB->get_record('aisoftskills_scene', ['id' => $sceneid], '*', MUST_EXIST);
    }

    /**
     * Creates a scene from Behat table data.
     *
     * @param array $data activityid (instance id), title, better, poorer, kpi
     * @return stdClass scene
     */
    public function create_behat_scene(array $data): stdClass {
        global $DB;
        $instance = $DB->get_record('aisoftskills', ['id' => $data['activityid']], '*', MUST_EXIST);
        return $this->create_scene(
            $instance,
            $data['title'],
            $data['better'] ?? 'Is there anything I can get you to help you reach your goals faster?',
            $data['poorer'] ?? 'Hurry up!',
            array_filter(['kpi' => $data['kpi'] ?? null, 'skill' => $data['skill'] ?? null])
        );
    }
}
