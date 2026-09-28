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

namespace mod_aisoftskills;

use mod_aisoftskills\local\learning;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Backup and restore tests (duplicate, and course backup with user data).
 *
 * @package    mod_aisoftskills
 * @category   test
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
#[CoversNothing]
final class backup_test extends \advanced_testcase {
    /**
     * Backs up a course with users and restores it as a new course.
     *
     * @param int $courseid
     * @return int new course id
     */
    protected function backup_restore(int $courseid): int {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $courseid,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value(true);
        $bc->execute_plan();
        $backupid = $bc->get_backupid();
        $file = $bc->get_results()['backup_destination'];
        $file->extract_to_pathname(
            get_file_packer('application/vnd.moodle.backup'),
            make_backup_temp_directory($backupid)
        );
        $bc->destroy();
        $newcourseid = \restore_dbops::create_new_course('Restored', 'R1', $this->getDataGenerator()->create_category()->id);
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $rc->get_plan()->get_setting('users')->set_value(true);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();
        return $newcourseid;
    }

    /**
     * Content, pictures, attempts and choices survive, with every id remapped.
     * @coversNothing
     */
    public function test_course_backup_with_users(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $student = $gen->create_and_enrol($course, 'student');
        $ss = $gen->get_plugin_generator('mod_aisoftskills');
        $instance = $ss->create_instance(['course' => $course->id, 'industry' => 'hospitality', 'level' => 'manager',
            'shuffleoptions' => 1]);
        $scene = $ss->create_scene($instance, 'Short-staffed Friday', 'Ask what would help', 'Tell them to cope');
        $cm = get_coursemodule_from_instance('aisoftskills', $instance->id);
        $context = \context_module::instance($cm->id);
        $instance = $DB->get_record('aisoftskills', ['id' => $instance->id]);
        $data = learning::start_attempt($instance, $context, (int)$student->id);
        $attempt = $DB->get_record('aisoftskills_attempt', ['id' => $data['attemptid']]);
        $best = (int)$DB->get_field('aisoftskills_option', 'id', ['sceneid' => $scene->id, 'best' => 1]);
        learning::choose($instance, $context, $attempt, (int)$scene->id, $best);
        learning::finish_attempt($instance, $cm, $course, $context, $attempt);

        $newcourseid = $this->backup_restore((int)$course->id);
        $newinstance = $DB->get_record('aisoftskills', ['course' => $newcourseid], '*', MUST_EXIST);
        $this->assertSame('hospitality', $newinstance->industry);
        $this->assertSame('manager', $newinstance->level);
        $newscene = $DB->get_record('aisoftskills_scene', ['aisoftskillsid' => $newinstance->id], '*', MUST_EXIST);
        $this->assertSame('Short-staffed Friday', $newscene->title);
        $newbest = (int)$DB->get_field('aisoftskills_option', 'id', ['sceneid' => $newscene->id, 'best' => 1]);
        $this->assertNotSame($best, $newbest);
        $newcm = get_coursemodule_from_instance('aisoftskills', $newinstance->id);
        $newcontext = \context_module::instance($newcm->id);
        $this->assertNotNull(\mod_aisoftskills\local\manager::get_scene_file($newcontext, (int)$newscene->id));
        $newattempt = $DB->get_record('aisoftskills_attempt', ['aisoftskillsid' => $newinstance->id], '*', MUST_EXIST);
        $this->assertEquals(100, (float)$newattempt->score);
        $order = json_decode($newattempt->sceneorder, true);
        $this->assertSame((int)$newscene->id, $order[0]['scene']);
        $this->assertContains($newbest, $order[0]['options']);
        $choice = $DB->get_record('aisoftskills_choice', ['attemptid' => $newattempt->id], '*', MUST_EXIST);
        $this->assertSame($newbest, (int)$choice->optionid);
        $this->assertSame((int)$newscene->id, (int)$choice->sceneid);
        // The restored activity is playable.
        $again = learning::start_attempt($newinstance, $newcontext, (int)$student->id);
        $this->assertCount(1, $again['scenes']);
        $this->assertSame(2, $again['attempt']);
    }

    /**
     * Duplicating an activity copies the content but not learner data.
     * @coversNothing
     */
    public function test_duplicate(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $ss = $gen->get_plugin_generator('mod_aisoftskills');
        $instance = $ss->create_instance(['course' => $course->id, 'name' => 'Orig']);
        $ss->create_scene($instance, 'Behind on target');
        $cm = get_fast_modinfo($course)->get_cm(get_coursemodule_from_instance('aisoftskills', $instance->id)->id);
        if (method_exists(\core_courseformat\local\cmactions::class, 'duplicate')) {
            // Moodle 5.2+.
            $newcm = \core_courseformat\formatactions::cm($course->id)->duplicate($cm->id);
        } else {
            $newcm = duplicate_module($course, $cm);
        }
        $this->assertSame(2, $DB->count_records('aisoftskills'));
        $this->assertSame(4, $DB->count_records('aisoftskills_option'));
        $this->assertNotNull(\mod_aisoftskills\local\manager::get_scene_file(
            \context_module::instance($newcm->id),
            (int)$DB->get_field('aisoftskills_scene', 'id', ['aisoftskillsid' => $newcm->instance])
        ));
    }
}
