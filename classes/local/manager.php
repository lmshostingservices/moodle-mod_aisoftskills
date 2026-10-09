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

namespace mod_aisoftskills\local;

use moodle_exception;
use moodle_url;
use stdClass;

/**
 * Activity content: scenes (one picture each) and their two responses, pictures and grades.
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /** @var int Fewest responses per scene: the better response and a poorer one. */
    public const OPTIONS = 2;

    /** @var int Most responses per scene: the better one, a poorer one and a very poor one (C). */
    public const MAX_OPTIONS = 3;

    /** @var string[] Accepted picture types. */
    public const IMAGE_TYPES = ['.png', '.jpg', '.jpeg', '.gif', '.webp'];

    /**
     * @var int[] Longest text, in characters, that still sits neatly on the scene page and in the feedback popup.
     *     Longer text (from earlier versions or pasted from an AI assistant) is kept, but flagged in the check step and
     *     must be shortened when the scene is next saved in the editor.
     */
    public const LIMITS = ['context' => 450, 'question' => 160, 'text' => 300, 'consequence' => 300, 'reason' => 220,
        'speaker' => 60];

    /** @var string[] File areas. */
    public const FILEAREAS = ['sceneimage', 'voiceover'];

    /** @var int Most pictures accepted in one upload. */
    public const MAX_UPLOAD_IMAGES = 100;

    /** @var int Largest ZIP accepted (unpacked), in bytes. */
    public const MAX_ZIP_BYTES = 209715200;

    /** @var int Grading method: highest attempt. */
    public const GRADE_HIGHEST = 1;

    /** @var int Grading method: average of attempts. */
    public const GRADE_AVERAGE = 2;

    /** @var int Grading method: first attempt. */
    public const GRADE_FIRST = 3;

    /** @var int Grading method: last attempt. */
    public const GRADE_LAST = 4;

    /**
     * Normalises activity settings before saving.
     *
     * @param stdClass $data
     * @return stdClass
     */
    public static function prepare_instance_data(stdClass $data): stdClass {
        $flags = ['allowretry', 'practicemode', 'testmode', 'shuffleoptions', 'sounds', 'mustlisten', 'completionallscenes',
            'completionpasstest'];
        foreach ($flags as $flag) {
            if (property_exists($data, $flag)) {
                $data->$flag = empty($data->$flag) ? 0 : 1;
            }
        }
        if (isset($data->industry) && $data->industry !== 'custom' && !in_array($data->industry, catalogue::INDUSTRIES, true)) {
            $data->industry = 'office';
        }
        if (property_exists($data, 'customindustry')) {
            $data->customindustry = self::clean_line($data->customindustry ?? '');
        }
        if (isset($data->level) && !in_array($data->level, catalogue::LEVELS, true)) {
            $data->level = 'supervisor';
        }
        if (isset($data->contentlang) && !catalogue::is_language($data->contentlang)) {
            $data->contentlang = 'en';
        }
        if (isset($data->imagestyle) && !in_array($data->imagestyle, ['illustration', 'photo'], true)) {
            $data->imagestyle = 'illustration';
        }
        if (isset($data->grademethod) && !in_array((int)$data->grademethod, [1, 2, 3, 4], true)) {
            $data->grademethod = self::GRADE_HIGHEST;
        }
        if (
            property_exists($data, 'practicemode') && property_exists($data, 'testmode')
                && !$data->practicemode && !$data->testmode
        ) {
            // At least one way to play.
            $data->practicemode = 1;
        }
        if (property_exists($data, 'testmode') || property_exists($data, 'practicemode')) {
            // Kept in step for anything that still reads the old setting.
            $data->allowretry = empty($data->testmode) || !empty($data->practicemode) ? 1 : 0;
        }
        if (isset($data->voiceparts) && is_array($data->voiceparts)) {
            // From the form: one checkbox per part.
            $data->voiceparts = implode(',', array_keys(array_filter($data->voiceparts)));
        }
        if (property_exists($data, 'voiceparts')) {
            $data->voiceparts = implode(',', array_intersect(voiceover::PARTS, explode(',', (string)$data->voiceparts)));
        }
        if (property_exists($data, 'passmark')) {
            $data->passmark = max(0, min(100, (int)$data->passmark));
        }
        if (property_exists($data, 'maxattempts')) {
            $data->maxattempts = max(0, min(100, (int)$data->maxattempts));
        }
        if (!isset($data->grade)) {
            $data->grade = 100;
        }
        return $data;
    }

    /**
     * User grades computed from finished attempts.
     *
     * @param stdClass $instance
     * @param int $userid 0 for all users
     * @return array userid => stdClass(userid, rawgrade, dategraded, datesubmitted)
     */
    public static function get_user_grades(stdClass $instance, int $userid = 0): array {
        global $DB;
        // Only the graded mode counts: the test when the activity has one, otherwise practice.
        $params = ['aid' => $instance->id, 'state' => 'finished', 'mode' => learning::graded_mode($instance)];
        $where = 'aisoftskillsid = :aid AND state = :state AND playmode = :mode';
        if ($userid) {
            $where .= ' AND userid = :userid';
            $params['userid'] = $userid;
        }
        $grades = [];
        $current = null;
        $list = [];
        $flush = function () use (&$grades, &$current, &$list, $instance) {
            if ($current === null || !$list) {
                return;
            }
            $percent = self::calculate_percent($list, (int)$instance->grademethod);
            $last = end($list);
            $grade = new stdClass();
            $grade->userid = $current;
            $grade->rawgrade = $instance->grade > 0 ? round($percent * $instance->grade / 100, 5) : null;
            $grade->dategraded = $last->timefinish;
            $grade->datesubmitted = $last->timefinish;
            $grades[$current] = $grade;
        };
        $rs = $DB->get_recordset_select(
            'aisoftskills_attempt',
            $where,
            $params,
            'userid, attempt',
            'id, userid, attempt, score AS grade, timefinish'
        );
        try {
            foreach ($rs as $attempt) {
                if ((int)$attempt->userid !== $current) {
                    $flush();
                    $current = (int)$attempt->userid;
                    $list = [];
                }
                $list[] = $attempt;
            }
            $flush();
        } finally {
            $rs->close();
        }
        return $grades;
    }

    /**
     * Scenes of an activity, in order.
     *
     * @param int $aid
     * @return stdClass[]
     */
    public static function get_scenes(int $aid): array {
        global $DB;
        return $DB->get_records('aisoftskills_scene', ['aisoftskillsid' => $aid], 'sortorder ASC, id ASC');
    }

    /**
     * Responses of the given scenes.
     *
     * @param int[] $sceneids
     * @return array sceneid => stdClass[] (in order)
     */
    public static function get_options(array $sceneids): array {
        global $DB;
        $out = array_fill_keys($sceneids, []);
        if (!$sceneids) {
            return $out;
        }
        [$insql, $params] = $DB->get_in_or_equal($sceneids, SQL_PARAMS_NAMED);
        foreach ($DB->get_records_select('aisoftskills_option', "sceneid $insql", $params, 'sceneid, sortorder, id') as $option) {
            $out[$option->sceneid][] = $option;
        }
        return $out;
    }

    /**
     * Whether a scene is complete: a picture and two responses, exactly one of them the better response.
     *
     * @param \context $context
     * @param stdClass $scene
     * @param stdClass[] $options
     * @return bool
     */
    public static function scene_ready(\context $context, stdClass $scene, array $options): bool {
        return self::options_ok($options) && self::get_scene_file($context, (int)$scene->id) !== null;
    }

    /**
     * Whether a scene's responses are complete: two or three with text, exactly one the better response, and with
     * three, exactly one the very poor response.
     *
     * @param array $options response records or arrays (text, best, worst)
     * @return bool
     */
    public static function options_ok(array $options): bool {
        $list = array_values(array_filter(
            array_map(fn($o) => (array)$o, $options),
            fn($o) => trim((string)($o['text'] ?? '')) !== ''
        ));
        $count = count($list);
        if ($count !== count($options) || $count < self::OPTIONS || $count > self::MAX_OPTIONS) {
            return false;
        }
        $best = array_filter($list, fn($o) => !empty($o['best']));
        $worst = array_filter($list, fn($o) => !empty($o['worst']));
        if (count($best) !== 1 || array_intersect_key($best, $worst)) {
            return false;
        }
        return count($worst) === ($count === self::MAX_OPTIONS ? 1 : 0);
    }

    /**
     * Marks the very poor response among three and makes its indicator drop at least double the poorer one's.
     *
     * With three responses and none marked, the poorer response with the larger drop is taken as the very poor one.
     *
     * @param stdClass[] $clean responses from {@see self::clean_option()}
     * @return stdClass[]
     */
    public static function mark_worst(array $clean): array {
        $clean = array_values($clean);
        if (count($clean) !== self::MAX_OPTIONS) {
            foreach ($clean as $option) {
                $option->worst = 0;
            }
            return $clean;
        }
        $poor = array_keys(array_filter($clean, fn($o) => !$o->best));
        $marked = array_values(array_filter($poor, fn($i) => $clean[$i]->worst));
        if (count($marked) !== 1) {
            usort($poor, fn($a, $b) => $clean[$a]->kpidelta <=> $clean[$b]->kpidelta);
            $marked = [$poor[0] ?? -1];
        }
        foreach ($clean as $i => $option) {
            $option->worst = $i === $marked[0] ? 1 : 0;
        }
        foreach ($poor as $i) {
            if (!$clean[$i]->worst) {
                $w = $clean[$marked[0]];
                // A very poor response does double the harm: double minus points on its indicator.
                $w->kpidelta = max(-catalogue::MAX_DELTA, min($w->kpidelta, 2 * min(-5, $clean[$i]->kpidelta)));
            }
        }
        return $clean;
    }

    /**
     * Scenes that are ready to play, with their responses.
     *
     * @param stdClass $instance
     * @param \context $context
     * @return array list of [scene, options]
     */
    public static function ready_scenes(stdClass $instance, \context $context): array {
        $scenes = self::get_scenes((int)$instance->id);
        $options = self::get_options(array_keys($scenes));
        $out = [];
        foreach ($scenes as $scene) {
            if (self::scene_ready($context, $scene, $options[$scene->id])) {
                $out[] = [$scene, $options[$scene->id]];
            }
        }
        return $out;
    }

    /**
     * The fields of a scene that are longer than {@see self::LIMITS}.
     *
     * @param stdClass $scene
     * @param stdClass[] $options
     * @return string[] field names (context, question, speaker, text, consequence or reason), each once
     */
    public static function too_long(stdClass $scene, array $options): array {
        $out = [];
        foreach (['context', 'question', 'speaker'] as $field) {
            if (\core_text::strlen(trim((string)$scene->$field)) > self::LIMITS[$field]) {
                $out[] = $field;
            }
        }
        foreach ($options as $option) {
            foreach (['text', 'consequence', 'reason'] as $field) {
                if (\core_text::strlen(trim((string)$option->$field)) > self::LIMITS[$field] && !in_array($field, $out, true)) {
                    $out[] = $field;
                }
            }
        }
        return $out;
    }

    /**
     * Adds a scene at the end.
     *
     * @param stdClass $instance
     * @param array $data title, skill, context, speaker, question, imageprompt, script (JSON), teachingnote
     * @return int scene id
     */
    public static function add_scene(stdClass $instance, array $data): int {
        global $DB;
        $sortorder = (int)$DB->get_field_sql(
            'SELECT MAX(sortorder) FROM {aisoftskills_scene} WHERE aisoftskillsid = :aid',
            ['aid' => $instance->id]
        );
        $now = time();
        $title = self::clean_line($data['title'] ?? '');
        return (int)$DB->insert_record('aisoftskills_scene', (object)[
            'aisoftskillsid' => $instance->id,
            'sortorder' => $sortorder + 1,
            'skill' => self::clean_line($data['skill'] ?? ''),
            'title' => $title !== '' ? $title : get_string('newscene', 'mod_aisoftskills'),
            'context' => self::clean_text($data['context'] ?? '', 2000),
            'speaker' => self::clean_line($data['speaker'] ?? ''),
            'question' => self::clean_line($data['question'] ?? ''),
            'imageprompt' => self::clean_text($data['imageprompt'] ?? '', 2000),
            'script' => self::clean_script($data['script'] ?? ''),
            'teachingnote' => self::clean_text($data['teachingnote'] ?? '', 2000),
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Cleans one response.
     *
     * @param array $item text, best, kpi, kpidelta, consequence, reason
     * @return stdClass
     */
    public static function clean_option(array $item): stdClass {
        $best = !empty($item['best']) ? 1 : 0;
        $worst = !$best && !empty($item['worst']) ? 1 : 0;
        $kpi = (string)($item['kpi'] ?? '');
        if (!catalogue::is_kpi($kpi)) {
            $kpi = 'morale';
        }
        $delta = (int)round((float)($item['kpidelta'] ?? ($best ? 20 : -20)));
        $delta = max(-catalogue::MAX_DELTA, min(catalogue::MAX_DELTA, $delta));
        // The better response always helps and the other never does, whatever a draft says.
        if ($best && $delta <= 0) {
            $delta = abs($delta) ?: 20;
        } else if (!$best && $delta > 0) {
            $delta = -$delta;
        }
        return (object)[
            'text' => self::clean_text($item['text'] ?? '', 1000),
            'best' => $best,
            'worst' => $worst,
            'kpi' => $kpi,
            'kpidelta' => $delta,
            'consequence' => self::clean_text($item['consequence'] ?? '', 2000),
            'reason' => self::clean_text($item['reason'] ?? '', 2000),
        ];
    }

    /**
     * Saves a scene and its two or three responses.
     *
     * @param stdClass $scene
     * @param array $data title, skill, context, speaker, question, imageprompt, script (JSON), teachingnote
     * @param array $options two or three response arrays; exactly one marked best, and with three one marked worst
     */
    public static function save_scene(stdClass $scene, array $data, array $options): void {
        global $DB;
        $clean = self::mark_worst(array_map(fn($o) => self::clean_option((array)$o), array_values($options)));
        if (!self::options_ok($clean)) {
            throw new moodle_exception('twooptionsrequired', 'mod_aisoftskills');
        }
        $transaction = $DB->start_delegated_transaction();
        $update = (object)['id' => $scene->id, 'timemodified' => time()];
        foreach (['title' => 255, 'skill' => 255, 'speaker' => 255, 'question' => 255] as $field => $len) {
            if (array_key_exists($field, $data)) {
                $update->$field = self::clean_line($data[$field], $len);
            }
        }
        foreach (['context', 'imageprompt', 'teachingnote'] as $field) {
            if (array_key_exists($field, $data)) {
                $update->$field = self::clean_text($data[$field], 2000);
            }
        }
        if (array_key_exists('script', $data)) {
            $update->script = self::clean_script($data['script']);
        }
        if (isset($update->title) && $update->title === '') {
            $update->title = get_string('newscene', 'mod_aisoftskills');
        }
        $DB->update_record('aisoftskills_scene', $update);
        $existing = array_values($DB->get_records('aisoftskills_option', ['sceneid' => $scene->id], 'sortorder, id'));
        foreach ($clean as $i => $option) {
            $option->sceneid = $scene->id;
            $option->sortorder = $i + 1;
            if (isset($existing[$i])) {
                $option->id = $existing[$i]->id;
                $DB->update_record('aisoftskills_option', $option);
            } else {
                $DB->insert_record('aisoftskills_option', $option);
            }
        }
        foreach (array_slice($existing, count($clean)) as $extra) {
            $DB->delete_records('aisoftskills_option', ['id' => $extra->id]);
        }
        $transaction->allow_commit();
    }

    /**
     * Deletes a scene with its responses, picture and learner choices.
     *
     * @param \context $context
     * @param stdClass $scene
     */
    public static function delete_scene(\context $context, stdClass $scene): void {
        global $DB;
        $DB->delete_records('aisoftskills_choice', ['sceneid' => $scene->id]);
        $DB->delete_records('aisoftskills_option', ['sceneid' => $scene->id]);
        $DB->delete_records('aisoftskills_scene', ['id' => $scene->id]);
        get_file_storage()->delete_area_files($context->id, 'mod_aisoftskills', 'sceneimage', $scene->id);
        get_file_storage()->delete_area_files($context->id, 'mod_aisoftskills', 'voiceover', $scene->id);
        self::normalise_sortorder((int)$scene->aisoftskillsid);
    }

    /**
     * Marks the activity viewed and triggers the event.
     *
     * @param stdClass $instance
     * @param stdClass $course
     * @param \cm_info|stdClass $cm
     * @param \context_module $context
     */
    public static function view(stdClass $instance, stdClass $course, $cm, \context_module $context): void {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        $event = \mod_aisoftskills\event\course_module_viewed::create([
            'objectid' => $instance->id,
            'context' => $context,
        ]);
        $event->add_record_snapshot('course', $course);
        $event->add_record_snapshot('aisoftskills', $instance);
        $event->trigger();
        $completion = new \completion_info($course);
        $completion->set_module_viewed($cm);
    }

    /**
     * Applies the grading method to a list of attempts (ordered by attempt number).
     *
     * @param array $attempts
     * @param int $method
     * @return float percentage
     */
    public static function calculate_percent(array $attempts, int $method): float {
        $attempts = array_values($attempts);
        if (!$attempts) {
            return 0.0;
        }
        switch ($method) {
            case self::GRADE_AVERAGE:
                $sum = 0;
                foreach ($attempts as $a) {
                    $sum += (float)$a->grade;
                }
                return $sum / count($attempts);
            case self::GRADE_FIRST:
                return (float)$attempts[0]->grade;
            case self::GRADE_LAST:
                return (float)$attempts[count($attempts) - 1]->grade;
            default:
                $max = 0.0;
                foreach ($attempts as $a) {
                    $max = max($max, (float)$a->grade);
                }
                return $max;
        }
    }

    /**
     * The stored picture of a scene.
     *
     * @param \context $context
     * @param int $sceneid
     * @return \stored_file|null
     */
    public static function get_scene_file(\context $context, int $sceneid): ?\stored_file {
        $files = get_file_storage()->get_area_files(
            $context->id,
            'mod_aisoftskills',
            'sceneimage',
            $sceneid,
            'sortorder, id',
            false
        );
        return $files ? reset($files) : null;
    }

    /**
     * Picture information for a scene.
     *
     * @param \context $context
     * @param int $sceneid
     * @return array [url|null, width, height]
     */
    public static function get_scene_image(\context $context, int $sceneid): array {
        $file = self::get_scene_file($context, $sceneid);
        if (!$file) {
            return [null, 16, 10];
        }
        $url = moodle_url::make_pluginfile_url(
            $context->id,
            'mod_aisoftskills',
            'sceneimage',
            $sceneid,
            $file->get_filepath(),
            $file->get_filename()
        );
        $url->param('rev', $file->get_timemodified());
        $info = $file->get_imageinfo();
        $width = !empty($info['width']) ? (int)$info['width'] : 1600;
        $height = !empty($info['height']) ? (int)$info['height'] : 1000;
        return [$url->out(false), $width, $height];
    }

    /**
     * Checks that bytes really are a picture of an accepted type.
     *
     * @param string $head the first bytes of the file (at least 16)
     * @return string|null the extension (png, jpg, gif, webp) or null
     */
    public static function image_signature(string $head): ?string {
        if (strncmp($head, "\x89PNG\r\n\x1a\n", 8) === 0) {
            return 'png';
        }
        if (strncmp($head, "\xFF\xD8\xFF", 3) === 0) {
            return 'jpg';
        }
        if (strncmp($head, 'GIF87a', 6) === 0 || strncmp($head, 'GIF89a', 6) === 0) {
            return 'gif';
        }
        if (strncmp($head, 'RIFF', 4) === 0 && substr($head, 8, 4) === 'WEBP') {
            return 'webp';
        }
        return null;
    }

    /**
     * Whether an image file on disk is a real, readable picture.
     *
     * @param string $path
     * @return bool
     */
    protected static function valid_image_path(string $path): bool {
        if (is_link($path) || !is_file($path) || !is_readable($path)) {
            return false;
        }
        $handle = fopen($path, 'rb');
        if (!$handle) {
            return false;
        }
        $head = (string)fread($handle, 16);
        fclose($handle);
        if (self::image_signature($head) === null) {
            return false;
        }
        $info = getimagesize($path);
        return !empty($info[0]) && !empty($info[1]);
    }

    /**
     * Whether a stored file is a real picture.
     *
     * @param \stored_file $file
     * @return bool
     */
    protected static function valid_image_file(\stored_file $file): bool {
        $handle = $file->get_content_file_handle();
        if (!$handle) {
            return false;
        }
        $head = (string)fread($handle, 16);
        fclose($handle);
        return self::image_signature($head) !== null && $file->is_valid_image();
    }

    /**
     * Makes a readable title from a file name.
     *
     * @param string $filename
     * @return string
     */
    public static function title_from_filename(string $filename): string {
        $title = preg_replace('/\.[^.]+$/', '', $filename);
        $title = trim(preg_replace('/[_\-]+/', ' ', $title));
        return \core_text::substr($title !== '' ? $title : $filename, 0, 255);
    }

    /**
     * Collects the pictures in a draft area, including pictures inside ZIP files.
     *
     * @param int $draftitemid
     * @return array list of [name, file|path]
     */
    protected static function draft_images(int $draftitemid): array {
        global $USER;
        $fs = get_file_storage();
        $usercontext = \context_user::instance($USER->id);
        $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'filename', false);
        $sources = [];
        foreach ($files as $file) {
            $filename = $file->get_filename();
            if (preg_match('/\.zip$/i', $filename)) {
                $packer = get_file_packer('application/zip');
                $list = $file->list_files($packer);
                if (!is_array($list)) {
                    throw new moodle_exception('zipinvalid', 'mod_aisoftskills');
                }
                $unpacked = 0;
                $images = 0;
                foreach ($list as $entry) {
                    $unpacked += (int)$entry->size;
                    if (!$entry->is_directory && preg_match('/\.(png|jpe?g|gif|webp)$/i', $entry->pathname)) {
                        $images++;
                    }
                }
                if ($unpacked > self::MAX_ZIP_BYTES || $images > self::MAX_UPLOAD_IMAGES) {
                    throw new moodle_exception('ziptoolarge', 'mod_aisoftskills', '', self::MAX_UPLOAD_IMAGES);
                }
                $tmpdir = make_request_directory();
                $file->extract_to_pathname($packer, $tmpdir);
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($tmpdir, \FilesystemIterator::SKIP_DOTS)
                );
                foreach ($iterator as $path) {
                    $name = $path->getFilename();
                    if (
                        !$path->isLink() && $path->isFile() && strpos($name, '.') !== 0 &&
                        strpos($path->getPathname(), '__MACOSX') === false &&
                        preg_match('/\.(png|jpe?g|gif|webp)$/i', $name) && self::valid_image_path($path->getPathname())
                    ) {
                        $sources[] = ['name' => $name, 'path' => $path->getPathname()];
                    }
                }
            } else if (self::valid_image_file($file)) {
                $sources[] = ['name' => $filename, 'file' => $file];
            }
        }
        if (count($sources) > self::MAX_UPLOAD_IMAGES) {
            throw new moodle_exception('ziptoolarge', 'mod_aisoftskills', '', self::MAX_UPLOAD_IMAGES);
        }
        usort($sources, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));
        return $sources;
    }

    /**
     * File record for a scene picture.
     *
     * @param \context $context
     * @param int $sceneid
     * @param string $filename
     * @return array
     */
    protected static function image_record(\context $context, int $sceneid, string $filename): array {
        return [
            'contextid' => $context->id,
            'component' => 'mod_aisoftskills',
            'filearea' => 'sceneimage',
            'itemid' => $sceneid,
            'filepath' => '/',
            'filename' => clean_param($filename, PARAM_FILE) ?: 'scene.png',
        ];
    }

    /**
     * Replaces a scene picture with the first picture in a draft area.
     *
     * @param \context $context
     * @param stdClass $scene
     * @param int $draftitemid
     * @return bool whether a picture was saved
     */
    public static function replace_scene_image(\context $context, stdClass $scene, int $draftitemid): bool {
        global $DB;
        $sources = self::draft_images($draftitemid);
        if (!$sources) {
            return false;
        }
        $source = reset($sources);
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_aisoftskills', 'sceneimage', $scene->id);
        $record = self::image_record($context, (int)$scene->id, $source['name']);
        if (isset($source['file'])) {
            $fs->create_file_from_storedfile($record, $source['file']);
        } else {
            $fs->create_file_from_pathname($record, $source['path']);
        }
        $DB->set_field('aisoftskills_scene', 'timemodified', time(), ['id' => $scene->id]);
        return true;
    }

    /**
     * Saves picture bytes (from an AI provider) as the scene picture, after checking they are a real picture.
     *
     * @param \context $context
     * @param stdClass $scene
     * @param string $bytes
     */
    public static function save_scene_image_bytes(\context $context, stdClass $scene, string $bytes): void {
        global $DB;
        $type = self::image_signature(substr($bytes, 0, 16));
        if ($type === null || strlen($bytes) > 20 * 1048576) {
            throw new moodle_exception('aibadimage', 'mod_aisoftskills');
        }
        $dir = make_request_directory();
        $path = $dir . '/scene.' . $type;
        file_put_contents($path, $bytes);
        if (!self::valid_image_path($path)) {
            throw new moodle_exception('aibadimage', 'mod_aisoftskills');
        }
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_aisoftskills', 'sceneimage', $scene->id);
        $fs->create_file_from_pathname(self::image_record($context, (int)$scene->id, 'scene-' . time() . '.' . $type), $path);
        $DB->set_field('aisoftskills_scene', 'timemodified', time(), ['id' => $scene->id]);
    }

    /**
     * File manager options for scene pictures.
     *
     * @param int $maxfiles
     * @return array
     */
    public static function image_filemanager_options(int $maxfiles = -1): array {
        global $CFG;
        require_once($CFG->dirroot . '/repository/lib.php');
        return [
            'subdirs' => 0,
            'maxfiles' => $maxfiles,
            'accepted_types' => $maxfiles === 1 ? self::IMAGE_TYPES : array_merge(self::IMAGE_TYPES, ['.zip']),
            'return_types' => FILE_INTERNAL,
        ];
    }

    /**
     * Re-numbers scene sort order 1..n.
     *
     * @param int $aid
     */
    public static function normalise_sortorder(int $aid): void {
        global $DB;
        $i = 0;
        foreach (self::get_scenes($aid) as $scene) {
            $i++;
            if ((int)$scene->sortorder !== $i) {
                $DB->set_field('aisoftskills_scene', 'sortorder', $i, ['id' => $scene->id]);
            }
        }
    }

    /**
     * Moves a scene earlier or later.
     *
     * @param stdClass $scene
     * @param int $direction -1 earlier, 1 later
     */
    public static function move_scene(stdClass $scene, int $direction): void {
        global $DB;
        self::normalise_sortorder((int)$scene->aisoftskillsid);
        $scenes = array_values(self::get_scenes((int)$scene->aisoftskillsid));
        foreach ($scenes as $index => $s) {
            if ((int)$s->id === (int)$scene->id) {
                $target = $index + $direction;
                if (isset($scenes[$target])) {
                    $DB->set_field('aisoftskills_scene', 'sortorder', $scenes[$target]->sortorder, ['id' => $s->id]);
                    $DB->set_field('aisoftskills_scene', 'sortorder', $s->sortorder, ['id' => $scenes[$target]->id]);
                }
                return;
            }
        }
    }

    /**
     * Cleans one line of text.
     *
     * @param mixed $value
     * @param int $length
     * @return string
     */
    public static function clean_line($value, int $length = 255): string {
        $value = trim(preg_replace('/\s+/u', ' ', clean_param((string)$value, PARAM_TEXT)));
        return \core_text::substr($value, 0, $length);
    }

    /**
     * Cleans multi-line text.
     *
     * @param mixed $value
     * @param int $length
     * @return string
     */
    public static function clean_text($value, int $length = 4000): string {
        $lines = preg_split('/\R/u', clean_param((string)$value, PARAM_TEXT));
        $lines = array_map(fn($l) => trim(preg_replace('/[ \t]+/u', ' ', $l)), $lines);
        return \core_text::substr(trim(implode("\n", $lines)), 0, $length);
    }

    /** @var int Most dialogue lines in a scene's lead-in. */
    public const MAX_DIALOGUE = 20;

    /**
     * Cleans a scene's lead-in dialogue (JSON with characters and speaker/line pairs).
     *
     * @param mixed $value JSON string or array
     * @return string JSON, or '' when there is no dialogue
     */
    public static function clean_script($value): string {
        $data = is_array($value) ? $value : json_decode((string)$value, true);
        $dialogue = [];
        foreach (array_slice((array)($data['dialogue'] ?? []), 0, self::MAX_DIALOGUE) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $line = self::clean_text($entry['line'] ?? '', 1000);
            if ($line !== '') {
                $dialogue[] = ['speaker' => self::clean_line($entry['speaker'] ?? '', 100), 'line' => $line];
            }
        }
        if (!$dialogue) {
            return '';
        }
        $characters = [];
        foreach (array_merge((array)($data['characters'] ?? []), array_column($dialogue, 'speaker')) as $name) {
            $name = self::clean_line($name, 100);
            if ($name !== '' && !in_array($name, $characters, true)) {
                $characters[] = $name;
            }
        }
        return json_encode(
            ['characters' => $characters, 'dialogue' => $dialogue],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * A scene's dialogue lines.
     *
     * @param string|null $script
     * @return array list of ['speaker' => string, 'line' => string]
     */
    public static function dialogue(?string $script): array {
        $data = json_decode(self::clean_script((string)$script), true);
        return $data['dialogue'] ?? [];
    }

    /**
     * A scene's dialogue as editable text, one "Speaker: line" per line.
     *
     * @param string|null $script
     * @return string
     */
    public static function script_to_text(?string $script): string {
        return implode("\n", array_map(
            fn($d) => $d['speaker'] !== '' ? $d['speaker'] . ': ' . $d['line'] : $d['line'],
            self::dialogue($script)
        ));
    }

    /**
     * Reads "Speaker: line" text back into dialogue JSON.
     *
     * @param string $text
     * @return string JSON, or '' when empty
     */
    public static function text_to_script(string $text): string {
        $dialogue = [];
        foreach (preg_split('/\R/u', $text) as $row) {
            $row = trim($row);
            if ($row === '') {
                continue;
            }
            if (preg_match('/^([^:]{1,100}):\s*(.+)$/u', $row, $m)) {
                $dialogue[] = ['speaker' => trim($m[1]), 'line' => trim($m[2])];
            } else {
                $dialogue[] = ['speaker' => '', 'line' => $row];
            }
        }
        return self::clean_script(['dialogue' => $dialogue]);
    }
}
