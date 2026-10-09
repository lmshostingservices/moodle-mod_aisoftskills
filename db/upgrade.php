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
 * @param int $oldversion the version being upgraded from
 * @return bool
 */
function xmldb_aisoftskills_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026092900) {
        // Scene lead-in dialogue and teaching note (from AI scene drafts).
        $table = new xmldb_table('aisoftskills_scene');
        foreach (['script', 'teachingnote'] as $name) {
            $field = new xmldb_field($name, XMLDB_TYPE_TEXT, null, null, null, null, null);
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Paid LMS Labs requests, persisted before they are sent.
        $table = new xmldb_table('aisoftskills_aireq');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('aisoftskillsid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('operation', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, null);
            $table->add_field('targetid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('idemkey', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL, null, null);
            $table->add_field('sitehash', XMLDB_TYPE_CHAR, '40', null, null, null, null);
            $table->add_field('body', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, 'pending');
            $table->add_field('errorcode', XMLDB_TYPE_CHAR, '64', null, null, null, null);
            $table->add_field('requestid', XMLDB_TYPE_CHAR, '64', null, null, null, null);
            $table->add_field('result', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('charged', XMLDB_TYPE_INTEGER, '6', null, null, null, null);
            $table->add_field('balance', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('tries', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('aisoftskillsid', XMLDB_KEY_FOREIGN, ['aisoftskillsid'], 'aisoftskills', ['id']);
            $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_index('idemkey', XMLDB_INDEX_UNIQUE, ['idemkey']);
            $table->add_index('opstatus', XMLDB_INDEX_NOTUNIQUE, ['aisoftskillsid', 'operation', 'status']);
            $dbman->create_table($table);
        }
        $field = new xmldb_field('sitehash', XMLDB_TYPE_CHAR, '40', null, null, null, null, 'idemkey');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Sites on 1.0.2 or 1.0.3 kept separate tables for script drafts and picture jobs. Move them to the stored
        // requests: an unresolved request keeps its exact key and body (so "Check again" can never charge twice),
        // and a completed script draft becomes a scene the teacher can finish.
        mod_aisoftskills_upgrade_move_103_requests($dbman);

        upgrade_mod_savepoint(true, 2026092900, 'aisoftskills');
    }

    if ($oldversion < 2026101000) {
        // Name labels on scene pictures ("Leo - Bartender"), placed by the teacher; and "listen before answering".
        $table = new xmldb_table('aisoftskills_scene');
        $field = new xmldb_field('labels', XMLDB_TYPE_TEXT, null, null, null, null, null, 'teachingnote');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $table = new xmldb_table('aisoftskills');
        $field = new xmldb_field(
            'voiceparts',
            XMLDB_TYPE_CHAR,
            '255',
            null,
            XMLDB_NOTNULL,
            null,
            'scenario,question,responses,consequence,why',
            'sounds'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('mustlisten', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'voiceparts');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('voicemap', XMLDB_TYPE_TEXT, null, null, null, null, null, 'mustlisten');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Practice and test modes replace "try again after a poorer choice": an activity that allowed a second try
        // becomes practice only, one that did not becomes test only (without a pass mark, as before). Earlier attempts
        // keep counting towards the grade.
        $fields = [
            new xmldb_field('practicemode', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'allowretry'),
            new xmldb_field('testmode', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'practicemode'),
            new xmldb_field('passmark', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '70', 'testmode'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        $field = new xmldb_field(
            'completionpasstest',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'completionallscenes'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $attempts = new xmldb_table('aisoftskills_attempt');
        $field = new xmldb_field('playmode', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, 'practice', 'state');
        if (!$dbman->field_exists($attempts, $field)) {
            $dbman->add_field($attempts, $field);
        }
        // Safe to run again: only activities not converted yet, and their attempts.
        $DB->execute("UPDATE {aisoftskills_attempt} SET playmode = 'test'
                       WHERE aisoftskillsid IN (SELECT id FROM {aisoftskills} WHERE allowretry = 0 AND testmode = 0)");
        $DB->execute('UPDATE {aisoftskills} SET practicemode = 0, testmode = 1, passmark = 0
                       WHERE allowretry = 0 AND testmode = 0');
        upgrade_mod_savepoint(true, 2026101000, 'aisoftskills');
    }

    if ($oldversion < 2026101001) {
        // The voice catalogue now carries the LMS Labs tariff: forget any copy kept before.
        unset_config('voicecatalog', 'mod_aisoftskills');
        $table = new xmldb_table('aisoftskills');
        $fields = [
            new xmldb_field('kpiamber', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '40', 'passmark'),
            new xmldb_field('kpigreen', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '70', 'kpiamber'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        $table = new xmldb_table('aisoftskills_choice');
        $field = new xmldb_field('tried', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'resolved');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $table = new xmldb_table('aisoftskills_option');
        $field = new xmldb_field('worst', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'best');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2026101001, 'aisoftskills');
    }

    if ($oldversion < 2026101003) {
        // Labels suggested by earlier versions: "Priya - Quietly mentions" becomes "Priya", "Mrs - Resident" gets the name.
        $rs = $DB->get_recordset_select('aisoftskills_scene', 'labels IS NOT NULL', null, 'id', 'id, context, labels');
        foreach ($rs as $scene) {
            \mod_aisoftskills\local\labels::repair($scene);
        }
        $rs->close();
        upgrade_mod_savepoint(true, 2026101003, 'aisoftskills');
    }

    if ($oldversion < 2026101004) {
        // Free voiceover remakes: the catalogue is read again (its cache key changed) so its tariff is known.
        // Caches are never touched here: during an upgrade a cache definition may not be registered yet.
        unset_config('voiceremakes', 'mod_aisoftskills');
        upgrade_mod_savepoint(true, 2026101004, 'aisoftskills');
    }

    return true;
}

/**
 * Moves 1.0.2/1.0.3 script drafts and picture jobs into aisoftskills_aireq, then drops their tables.
 *
 * @param database_manager $dbman
 */
function mod_aisoftskills_upgrade_move_103_requests(database_manager $dbman): void {
    global $DB;
    $now = time();
    $drafts = new xmldb_table('aisoftskills_draft');
    if ($dbman->table_exists($drafts)) {
        foreach ($DB->get_recordset('aisoftskills_draft', null, 'id') as $row) {
            $instance = $DB->get_record('aisoftskills', ['id' => $row->aisoftskillsid]);
            if (!$instance || $DB->record_exists('aisoftskills_aireq', ['idemkey' => $row->requestkey])) {
                continue;
            }
            $req = (object)[
                'aisoftskillsid' => $instance->id, 'userid' => $row->userid, 'operation' => 'scene', 'targetid' => 0,
                'idemkey' => $row->requestkey, 'sitehash' => sha1((string)$row->siteid),
                'body' => (string)$row->requestbody, 'status' => 'dismissed',
                'errorcode' => $row->errorcode, 'requestid' => $row->requestid, 'tries' => 1,
                'timecreated' => $row->timecreated, 'timemodified' => $now,
            ];
            if ($row->state === 'pending' && $row->requestbody !== '' && $now - (int)$row->timecreated < DAYSECS) {
                // Outcome unknown: keep the key and body for "Check again".
                $req->status = 'uncertain';
            } else if ($row->state === 'complete') {
                $draft = \mod_aisoftskills\local\ai\requests::clean_scene_draft(json_decode((string)$row->responsebody, true));
                if ($draft !== null) {
                    $req->targetid = \mod_aisoftskills\local\manager::add_scene(
                        $instance,
                        \mod_aisoftskills\local\ai\requests::scene_fields($draft)
                    );
                    $req->status = 'completed';
                    $req->result = json_encode($draft, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }
            if ($req->body === '') {
                $req->body = '{}';
            }
            $DB->insert_record('aisoftskills_aireq', $req);
        }
        $dbman->drop_table($drafts);
    }
    $jobs = new xmldb_table('aisoftskills_imagejob');
    if ($dbman->table_exists($jobs)) {
        foreach ($DB->get_recordset_select('aisoftskills_imagejob', "state IN ('pending', 'saving')", null, 'id') as $row) {
            if ($DB->record_exists('aisoftskills_aireq', ['idemkey' => $row->requestkey]) || (string)$row->requestbody === '') {
                continue;
            }
            $DB->insert_record('aisoftskills_aireq', (object)[
                'aisoftskillsid' => $row->aisoftskillsid, 'userid' => $row->userid, 'operation' => 'image',
                'targetid' => $row->sceneid, 'idemkey' => $row->requestkey, 'sitehash' => sha1((string)$row->siteid),
                'body' => (string)$row->requestbody,
                // A picture that was being saved may have been charged and cannot be fetched again.
                'status' => $row->state === 'pending' ? 'uncertain' : 'lost',
                'errorcode' => $row->errorcode, 'requestid' => $row->requestid, 'tries' => 1,
                'timecreated' => $row->timecreated, 'timemodified' => $now,
            ]);
        }
        $dbman->drop_table($jobs);
    }
}
