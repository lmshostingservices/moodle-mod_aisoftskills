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
 * External functions for mod_aisoftskills.
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_aisoftskills_start_attempt' => [
        'classname' => \mod_aisoftskills\external\start_attempt::class,
        'description' => 'Starts or resumes the learner\'s attempt and returns its scenes.',
        'type' => 'write',
        'ajax' => true,
    ],
    'mod_aisoftskills_choose_option' => [
        'classname' => \mod_aisoftskills\external\choose_option::class,
        'description' => 'Marks the learner\'s choice of response in a scene.',
        'type' => 'write',
        'ajax' => true,
    ],
    'mod_aisoftskills_finish_attempt' => [
        'classname' => \mod_aisoftskills\external\finish_attempt::class,
        'description' => 'Finishes and grades an attempt.',
        'type' => 'write',
        'ajax' => true,
    ],
    'mod_aisoftskills_import_lesson' => [
        'classname' => \mod_aisoftskills\external\import_lesson::class,
        'description' => 'Creates scenes from a lesson draft written by an AI assistant.',
        'type' => 'write',
        'ajax' => true,
    ],
    'mod_aisoftskills_generate_image' => [
        'classname' => \mod_aisoftskills\external\generate_image::class,
        'description' => 'Creates a scene picture with AI (when available on the site).',
        'type' => 'write',
        'ajax' => true,
    ],
];
