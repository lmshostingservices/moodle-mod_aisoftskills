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

namespace mod_aisoftskills\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use mod_aisoftskills\local\learning;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Privacy provider tests.
 *
 * @package    mod_aisoftskills
 * @category   test
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_aisoftskills\privacy\provider
 */
#[CoversClass(provider::class)]
final class provider_test extends provider_testcase {
    /** @var \context_module */
    protected $context;
    /** @var \stdClass */
    protected $u1;
    /** @var \stdClass */
    protected $u2;

    /**
     * Two learners, each with an attempt and a choice.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $ss = $gen->get_plugin_generator('mod_aisoftskills');
        $instance = $ss->create_instance(['course' => $course->id]);
        $scene = $ss->create_scene($instance, 'Behind on target', 'Offer help', 'Hurry up!');
        $cm = get_coursemodule_from_instance('aisoftskills', $instance->id);
        $this->context = \context_module::instance($cm->id);
        $instance = $DB->get_record('aisoftskills', ['id' => $instance->id]);
        $option = (int)$DB->get_field('aisoftskills_option', 'id', ['sceneid' => $scene->id, 'best' => 0]);
        foreach (['u1', 'u2'] as $name) {
            $user = $gen->create_and_enrol($course, 'student');
            $this->$name = $user;
            $data = learning::start_attempt($instance, $this->context, (int)$user->id);
            $attempt = $DB->get_record('aisoftskills_attempt', ['id' => $data['attemptid']]);
            learning::choose($instance, $this->context, $attempt, (int)$scene->id, $option);
        }
    }

    /**
     * Metadata lists every learner table and the grades link.
     */
    public function test_metadata(): void {
        $items = provider::get_metadata(new collection('mod_aisoftskills'))->get_collection();
        $names = array_map(fn($i) => $i->get_name(), $items);
        foreach (['aisoftskills_attempt', 'aisoftskills_choice', 'aisoftskills_ailog', 'core_grades'] as $name) {
            $this->assertContains($name, $names);
        }
    }

    /**
     * Contexts, users and export.
     */
    public function test_export(): void {
        $contexts = provider::get_contexts_for_userid((int)$this->u1->id);
        $this->assertEquals([$this->context->id], $contexts->get_contextids());
        $userlist = new userlist($this->context, 'mod_aisoftskills');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$this->u1->id, $this->u2->id], $userlist->get_userids());
        $this->export_context_data_for_user((int)$this->u1->id, $this->context, 'mod_aisoftskills');
        $data = writer::with_context($this->context)->get_data([]);
        $this->assertCount(1, $data->attempts);
        $this->assertSame('Behind on target', $data->attempts[0]->choices[0]->scene);
        $this->assertStringContainsString('Hurry up!', $data->attempts[0]->choices[0]->firstchoice);
        $this->assertSame(['motivation' => 30], $data->attempts[0]->indicators);
    }

    /**
     * Deleting one user, a list of users and the whole context keeps the scenes.
     */
    public function test_delete(): void {
        global $DB;
        provider::delete_data_for_user(new approved_contextlist($this->u1, 'mod_aisoftskills', [$this->context->id]));
        $this->assertSame(0, $DB->count_records('aisoftskills_attempt', ['userid' => $this->u1->id]));
        $this->assertSame(1, $DB->count_records('aisoftskills_attempt', ['userid' => $this->u2->id]));
        $this->assertSame(1, $DB->count_records('aisoftskills_choice'));
        provider::delete_data_for_users(new approved_userlist($this->context, 'mod_aisoftskills', [$this->u2->id]));
        $this->assertSame(0, $DB->count_records('aisoftskills_choice'));
        $this->assertSame(0, $DB->count_records('aisoftskills_attempt'));
        $this->assertSame(1, $DB->count_records('aisoftskills_scene'));
        provider::delete_data_for_all_users_in_context($this->context);
        $this->assertSame(2, $DB->count_records('aisoftskills_option'));
    }
}
