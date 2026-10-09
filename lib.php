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
 * Moodle callbacks for mod_aisoftskills.
 *
 * Moodle requires activity callbacks to be named after the module (aisoftskills_*). Everything else lives in
 * autoloaded classes under classes/.
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_aisoftskills\local\manager;

/**
 * Declares the features this module supports.
 *
 * @param string $feature FEATURE_xx constant
 * @return mixed
 */
function aisoftskills_supports($feature) {
    if (defined('FEATURE_MOD_OTHERPURPOSE') && $feature === FEATURE_MOD_OTHERPURPOSE) {
        return MOD_PURPOSE_INTERACTIVECONTENT;
    }
    switch ($feature) {
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_OTHER;
        case FEATURE_GROUPS:
        case FEATURE_GROUPINGS:
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_COMPLETION_HAS_RULES:
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_ASSESSMENT;
        default:
            return null;
    }
}

/**
 * Adds a new instance.
 *
 * @param stdClass $data
 * @param mod_aisoftskills_mod_form|null $mform
 * @return int new instance id
 */
function aisoftskills_add_instance($data, $mform = null) {
    global $DB;
    $data = manager::prepare_instance_data($data);
    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    $data->id = $DB->insert_record('aisoftskills', $data);
    aisoftskills_grade_item_update($data);
    if (!empty($data->completionexpected)) {
        \core_completion\api::update_completion_date_event(
            $data->coursemodule,
            'aisoftskills',
            $data->id,
            $data->completionexpected
        );
    }
    return $data->id;
}

/**
 * Updates an instance.
 *
 * @param stdClass $data
 * @param mod_aisoftskills_mod_form|null $mform
 * @return bool
 */
function aisoftskills_update_instance($data, $mform = null) {
    global $DB;
    $data = manager::prepare_instance_data($data);
    $data->id = $data->instance;
    $data->timemodified = time();
    $before = $DB->get_record('aisoftskills', ['id' => $data->id], '*', MUST_EXIST);
    $DB->update_record('aisoftskills', $data);
    $instance = $DB->get_record('aisoftskills', ['id' => $data->id], '*', MUST_EXIST);
    if (\mod_aisoftskills\local\learning::graded_mode($before) !== \mod_aisoftskills\local\learning::graded_mode($instance)) {
        // Practice and test grades never mix: start the gradebook again from the attempts that now count.
        aisoftskills_grade_item_update($instance, 'reset');
    }
    aisoftskills_grade_item_update($instance);
    aisoftskills_update_grades($instance, 0, false);
    \core_completion\api::update_completion_date_event(
        $data->coursemodule,
        'aisoftskills',
        $data->id,
        $data->completionexpected ?? null
    );
    return true;
}

/**
 * Deletes an instance and all its data. Files are removed with the module context.
 *
 * @param int $id
 * @return bool
 */
function aisoftskills_delete_instance($id) {
    global $DB;
    if (!$instance = $DB->get_record('aisoftskills', ['id' => $id])) {
        return false;
    }
    \mod_aisoftskills\local\learning::delete_all_user_data((int)$instance->id);
    $sceneids = $DB->get_fieldset_select('aisoftskills_scene', 'id', 'aisoftskillsid = :id', ['id' => $id]);
    if ($sceneids) {
        [$insql, $params] = $DB->get_in_or_equal($sceneids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('aisoftskills_option', "sceneid $insql", $params);
    }
    $DB->delete_records('aisoftskills_scene', ['aisoftskillsid' => $id]);
    $DB->delete_records('aisoftskills_ailog', ['aisoftskillsid' => $id]);
    $DB->delete_records('aisoftskills_aireq', ['aisoftskillsid' => $id]);
    aisoftskills_grade_item_delete($instance);
    $DB->delete_records('aisoftskills', ['id' => $id]);
    return true;
}

/**
 * Adds completion rule data to the course module info cache.
 *
 * @param stdClass $coursemodule
 * @return cached_cm_info|false
 */
function aisoftskills_get_coursemodule_info($coursemodule) {
    global $DB;
    $fields = 'id, name, intro, introformat, completionallscenes, completionpasstest';
    if (!$instance = $DB->get_record('aisoftskills', ['id' => $coursemodule->instance], $fields)) {
        return false;
    }
    $result = new cached_cm_info();
    $result->name = $instance->name;
    if ($coursemodule->showdescription) {
        $result->content = format_module_intro('aisoftskills', $instance, $coursemodule->id, false);
    }
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $result->customdata['customcompletionrules']['completionallscenes'] = $instance->completionallscenes;
        $result->customdata['customcompletionrules']['completionpasstest'] = $instance->completionpasstest;
    }
    return $result;
}

/**
 * Creates or updates the grade item.
 *
 * @param stdClass $instance
 * @param mixed $grades optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int GRADE_UPDATE_OK etc.
 */
function aisoftskills_grade_item_update($instance, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    $params = ['itemname' => $instance->name];
    if (isset($instance->cmidnumber)) {
        $params['idnumber'] = $instance->cmidnumber;
    }
    if ($instance->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax'] = $instance->grade;
        $params['grademin'] = 0;
    } else if ($instance->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid'] = -$instance->grade;
    } else {
        $params['gradetype'] = GRADE_TYPE_NONE;
    }
    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }
    return grade_update(
        'mod/aisoftskills',
        $instance->course,
        'mod',
        'aisoftskills',
        $instance->id,
        0,
        $grades,
        $params
    );
}

/**
 * Deletes the grade item.
 *
 * @param stdClass $instance
 * @return int
 */
function aisoftskills_grade_item_delete($instance) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    return grade_update(
        'mod/aisoftskills',
        $instance->course,
        'mod',
        'aisoftskills',
        $instance->id,
        0,
        null,
        ['deleted' => 1]
    );
}

/**
 * Pushes grades to the gradebook.
 *
 * @param stdClass $instance
 * @param int $userid
 * @param bool $nullifnone
 */
function aisoftskills_update_grades($instance, $userid = 0, $nullifnone = true) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    if ($instance->grade == 0) {
        aisoftskills_grade_item_update($instance);
        return;
    }
    if ($grades = manager::get_user_grades($instance, (int)$userid)) {
        aisoftskills_grade_item_update($instance, $grades);
    } else if ($userid && $nullifnone) {
        $grade = new stdClass();
        $grade->userid = $userid;
        $grade->rawgrade = null;
        aisoftskills_grade_item_update($instance, $grade);
    } else {
        aisoftskills_grade_item_update($instance);
    }
}

/**
 * Serves plugin files: scene pictures.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool false if file not found
 */
function aisoftskills_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }
    require_course_login($course, true, $cm);
    if (!in_array($filearea, manager::FILEAREAS, true)) {
        return false;
    }
    require_capability('mod/aisoftskills:view', $context);
    $itemid = (int)array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_aisoftskills', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, DAYSECS, 0, $forcedownload, $options);
}

/**
 * Adds plugin pages to the activity's secondary navigation.
 *
 * @param settings_navigation $settingsnav
 * @param navigation_node $node
 */
function aisoftskills_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $node) {
    $cm = $settingsnav->get_page()->cm;
    if (!$cm) {
        return;
    }
    $context = context_module::instance($cm->id);
    if (has_capability('mod/aisoftskills:manage', $context)) {
        // One entry for teachers: it opens the set-up path at the step it is up to.
        $node->add(
            get_string('setup_title', 'mod_aisoftskills'),
            new moodle_url('/mod/aisoftskills/builder.php', ['id' => $cm->id, 'step' => 'resume']),
            navigation_node::TYPE_SETTING,
            null,
            'aisoftskills_builder',
            new pix_icon('i/settings', '')
        );
    }
    if (has_capability('mod/aisoftskills:viewreports', $context)) {
        $node->add(
            get_string('reports', 'mod_aisoftskills'),
            new moodle_url('/mod/aisoftskills/report.php', ['id' => $cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            'aisoftskills_reports',
            new pix_icon('i/report', '')
        );
    }
}

/**
 * Adds reset elements to the course reset form.
 *
 * @param MoodleQuickForm $mform
 */
function aisoftskills_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'aisoftskillsheader', get_string('modulenameplural', 'mod_aisoftskills'));
    $mform->addElement('advcheckbox', 'reset_aisoftskills_attempts', get_string('resetattempts', 'mod_aisoftskills'));
}

/**
 * Reset form defaults.
 *
 * @param stdClass $course
 * @return array
 */
function aisoftskills_reset_course_form_defaults($course) {
    return ['reset_aisoftskills_attempts' => 1];
}

/**
 * Removes user data when a course is reset.
 *
 * @param stdClass $data
 * @return array status
 */
function aisoftskills_reset_userdata($data) {
    global $DB;
    $status = [];
    if (!empty($data->reset_aisoftskills_attempts)) {
        $instances = $DB->get_records('aisoftskills', ['course' => $data->courseid]);
        foreach ($instances as $instance) {
            \mod_aisoftskills\local\learning::delete_all_user_data((int)$instance->id);
            if (empty($data->reset_gradebook_grades)) {
                aisoftskills_grade_item_update($instance, 'reset');
            }
        }
        $status[] = [
            'component' => get_string('modulenameplural', 'mod_aisoftskills'),
            'item' => get_string('resetattempts', 'mod_aisoftskills'),
            'error' => false,
        ];
    }
    return $status;
}
