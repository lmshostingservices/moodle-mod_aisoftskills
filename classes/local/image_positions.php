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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.

namespace mod_aisoftskills\local;

/**
 * Optional positions shown only in the teacher's unsaved labels editor.
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class image_positions {
    /**
     * Descriptions in stable label order, only when advertised by LMS Labs.
     *
     * @param \stdClass $scene scene
     * @return array anchor descriptions
     */
    public static function anchors(\stdClass $scene): array {
        $labels = labels::get($scene) ?: labels::suggest($scene);
        return array_map(fn($label) => (string)$label['text'], $labels);
    }

    /**
     * Validates the optional header without coercing numeric strings.
     *
     * @param string $header compact JSON
     * @param int $count requested anchors
     * @return array|null points or no usable header
     */
    public static function decode(string $header, int $count): ?array {
        if ($count < 1 || $count > 6 || strlen($header) > 2048) {
            return null;
        }
        $points = json_decode($header, true);
        if (!is_array($points) || array_keys($points) !== range(0, $count - 1)) {
            return null;
        }
        foreach ($points as $point) {
            if ($point === null) {
                continue;
            }
            if (!is_array($point) || count($point) !== 2) {
                return null;
            }
            foreach (['x', 'y'] as $axis) {
                $value = $point[$axis] ?? null;
                if ((!is_int($value) && !is_float($value)) || !is_finite((float)$value) || $value < 0 || $value > 100) {
                    return null;
                }
            }
        }
        return $points;
    }

    /**
     * Improves unsaved suggestions, never moves any saved teacher labels.
     *
     * @param \stdClass $scene scene
     * @return array effective labels for the editor and learner, with saved teacher edits taking priority
     */
    public static function suggestions(\stdClass $scene): array {
        global $DB;
        $saved = labels::get($scene);
        if ($saved) {
            return $saved;
        }
        $labels = labels::suggest($scene);
        $rows = $DB->get_records('aisoftskills_aireq', ['targetid' => $scene->id, 'operation' => 'image',
            'aisoftskillsid' => $scene->aisoftskillsid, 'status' => 'completed'], 'id DESC', '*', 0, 1);
        $row = $rows ? reset($rows) : null;
        $result = $row ? json_decode((string)$row->result, true) : null;
        $cm = get_coursemodule_from_instance('aisoftskills', $scene->aisoftskillsid, 0, false, MUST_EXIST);
        $file = manager::get_scene_file(\context_module::instance($cm->id), (int)$scene->id);
        if (!$file || ($result['imagehash'] ?? '') !== $file->get_contenthash()) {
            return $labels;
        }
        $anchors = $result['anchors'] ?? [];
        $points = self::decode((string)($result['positions'] ?? ''), count($anchors));
        if ($points === null) {
            return $labels;
        }
        foreach ($labels as &$label) {
            $index = array_search($label['text'], $anchors, true);
            if ($index !== false && $points[$index] !== null) {
                $label['x'] = $points[$index]['x'];
                $label['y'] = $points[$index]['y'];
            }
        }
        return $labels;
    }
}
