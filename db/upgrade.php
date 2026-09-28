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
 * Upgrade steps for mod_aisoftskills.
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Runs the upgrade steps between the installed version and this one.
 *
 * Version 1.0.0 is the first release, so there are no steps yet; new ones go below with upgrade_mod_savepoint().
 *
 * @param int $oldversion the version being upgraded from
 * @return bool
 */
function xmldb_aisoftskills_upgrade($oldversion) {
    global $DB;
    if ($oldversion < 2026092802) {
        $dbman = $DB->get_manager();
        $image = new xmldb_table('aisoftskills_imagejob');
        $image->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $image->add_field('aisoftskillsid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $image->add_field('sceneid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $image->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $image->add_field('intentkey', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $image->add_field('requestkey', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $image->add_field('siteid', XMLDB_TYPE_CHAR, '512', null, XMLDB_NOTNULL, null, null);
        $image->add_field('requestbody', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $image->add_field('requestid', XMLDB_TYPE_CHAR, '64', null, null, null, null);
        $image->add_field('errorcode', XMLDB_TYPE_CHAR, '64', null, null, null, null);
        $image->add_field('state', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, null);
        $image->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $image->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $image->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $image->add_key('aisoftskillsid', XMLDB_KEY_FOREIGN, ['aisoftskillsid'], 'aisoftskills', ['id']);
        $image->add_key('sceneid', XMLDB_KEY_FOREIGN, ['sceneid'], 'aisoftskills_scene', ['id']);
        $image->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $image->add_index('userintent', XMLDB_INDEX_UNIQUE, ['userid', 'intentkey']);
        $image->add_index('requestkey', XMLDB_INDEX_UNIQUE, ['requestkey']);
        $image->add_index('scenerecent', XMLDB_INDEX_NOTUNIQUE, ['sceneid', 'userid', 'id']);
        if (!$dbman->table_exists($image)) {
            $dbman->create_table($image);
        }
        $table = new xmldb_table('aisoftskills_draft');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('aisoftskillsid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('requestkey', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('intentkey', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('siteid', XMLDB_TYPE_CHAR, '512', null, XMLDB_NOTNULL, null, null);
        $table->add_field('requestbody', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('bodyhash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('responsebody', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('editedbody', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('requestid', XMLDB_TYPE_CHAR, '64', null, null, null, null);
        $table->add_field('errorcode', XMLDB_TYPE_CHAR, '64', null, null, null, null);
        $table->add_field('state', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('aisoftskillsid', XMLDB_KEY_FOREIGN, ['aisoftskillsid'], 'aisoftskills', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('useractivity', XMLDB_INDEX_NOTUNIQUE, ['aisoftskillsid', 'userid', 'timecreated']);
        $table->add_index('requestkey', XMLDB_INDEX_UNIQUE, ['requestkey']);
        $table->add_index('userintent', XMLDB_INDEX_UNIQUE, ['userid', 'intentkey']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_mod_savepoint(true, 2026092802, 'aisoftskills');
    }
    return true;
}
