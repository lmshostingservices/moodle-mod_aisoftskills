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

namespace mod_aisoftskills\local\ai;

use moodle_exception;

/**
 * LMS Labs account connection.
 *
 * AI Soft Skills uses two dedicated LMS Labs routes (owner-approved tariffs, charged by LMS Labs only):
 * POST /api/moodle/ai-softskills/scenes/draft (3 credits per delivered scene draft) and
 * POST /api/moodle/ai-softskills/images (5 credits per delivered picture). Credentials go in headers only. Each
 * intentional request gets a new Idempotency-Key that is stored with its exact body before sending (see requests), and
 * nothing is retried automatically. The balance check (GET /api/credits) is read-only and advisory.
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lmslabs implements provider {
    /** @var string Fixed LMS Labs host. */
    public const BASE_URL = 'https://lms-labs.com';

    /** @var string Dedicated AI Soft Skills scene draft route. */
    public const TEXT_ROUTE = '/api/moodle/ai-softskills/scenes/draft';

    /** @var int Credits LMS Labs charges per delivered scene draft (owner-approved tariff). */
    public const TEXT_CREDITS = 3;

    /**
     * @var string AI Soft Skills charge for scenes made outside LMS Labs AI (an AI assistant of the teacher's choice):
     * the same tariff as an LMS Labs scene draft, per scene. Requested from LMS Labs on 8 Oct 2026; until it is live,
     * LMS Labs answers 404 and no scene is created.
     */
    public const IMPORT_ROUTE = '/api/moodle/ai-softskills/scenes/import';

    /** @var string Dedicated AI Soft Skills picture route. */
    public const IMAGE_ROUTE = '/api/moodle/ai-softskills/images';

    /** @var int Credits LMS Labs charges per successful picture (owner-approved tariff). */
    public const IMAGE_CREDITS = 5;

    /** @var int Longest picture prompt the route accepts, in Unicode code points. */
    public const MAX_PROMPT = 2000;

    /** @var string[] Picture styles the route accepts. */
    public const STYLES = ['illustration', 'photo'];

    /** @var callable|null Replacement GET transport used by unit tests: fn($url, $headers) => [status, body]. */
    public static $transport = null;

    /**
     * @var callable|null Replacement POST transport used by unit tests:
     *      fn($url, $headers, $body) => [status, lower-case response headers, body].
     */
    public static $posttransport = null;

    /**
     * Whether a complete LMS Labs credential pair is available (Central Config first, then this plugin's settings).
     *
     * @return bool
     */
    public function is_connected(): bool {
        return \mod_aisoftskills\local\credentials::find() !== null;
    }

    /**
     * Whether pictures can be created: AI pictures switched on for the site and a complete LMS Labs credential pair.
     *
     * @return bool
     */
    public function can_generate(): bool {
        return (bool)get_config('mod_aisoftskills', 'aiimages') && $this->is_connected();
    }

    /**
     * Whether scene drafts can be requested: AI scene drafts switched on for the site and a complete credential pair.
     *
     * @return bool
     */
    public function can_draft(): bool {
        return (bool)get_config('mod_aisoftskills', 'aidrafts') && $this->is_connected();
    }

    /**
     * Sends one POST to an AI Soft Skills route with header-only credentials and the given Idempotency-Key.
     *
     * The caller persists the key and body before calling, and reuses both for any later check of the same request.
     * Nothing is retried here.
     *
     * @param string $route one of TEXT_ROUTE, IMPORT_ROUTE or IMAGE_ROUTE
     * @param string $body exact JSON body
     * @param string $key Idempotency-Key
     * @param string $accept Accept header value
     * @return array [status (0 when LMS Labs could not be reached or did not answer), lower-case headers, body]
     */
    public static function post(string $route, string $body, string $key, string $accept): array {
        global $CFG;
        $credentials = \mod_aisoftskills\local\credentials::find();
        if ($credentials === null) {
            throw new moodle_exception('ainotavailable', 'mod_aisoftskills');
        }
        if (!in_array($route, [self::TEXT_ROUTE, self::IMPORT_ROUTE, self::IMAGE_ROUTE], true)) {
            throw new \coding_exception('Unknown LMS Labs route');
        }
        $url = self::BASE_URL . $route;
        $headers = [
            'Content-Type: application/json',
            'Accept: ' . $accept,
            'X-Site-ID: ' . $credentials['siteid'],
            'X-API-Key: ' . $credentials['apikey'],
            'Idempotency-Key: ' . $key,
        ];
        if (self::$posttransport) {
            [$status, $responseheaders, $response] = (self::$posttransport)($url, $headers, $body);
        } else {
            require_once($CFG->libdir . '/filelib.php');
            $curl = new \curl(['ignoresecurity' => false]);
            $curl->setHeader($headers);
            $response = $curl->post($url, $body, [
                'CURLOPT_CONNECTTIMEOUT' => 10,
                // Both routes finish within 85 to 90 seconds.
                'CURLOPT_TIMEOUT' => 100,
                'CURLOPT_FOLLOWLOCATION' => false,
            ]);
            if ($curl->get_errno()) {
                return [0, [], ''];
            }
            $status = (int)($curl->get_info()['http_code'] ?? 0);
            $responseheaders = [];
            foreach ((array)$curl->getResponse() as $name => $value) {
                $responseheaders[strtolower((string)$name)] = is_array($value) ? end($value) : (string)$value;
            }
        }
        return [(int)$status, array_change_key_case((array)$responseheaders, CASE_LOWER), (string)$response];
    }

    /**
     * Reads the site's balance (GET /api/credits, key in the X-API-Key header).
     *
     * @return array|null
     */
    public function balance(): ?array {
        global $CFG;
        $credentials = \mod_aisoftskills\local\credentials::find();
        if ($credentials === null) {
            return null;
        }
        $url = self::BASE_URL . '/api/credits?siteId=' . rawurlencode($credentials['siteid']);
        $headers = ['X-API-Key: ' . $credentials['apikey'], 'Accept: application/json'];
        if (self::$transport) {
            $transport = self::$transport;
            [$status, $body] = $transport($url, $headers);
        } else {
            require_once($CFG->libdir . '/filelib.php');
            $curl = new \curl();
            $curl->setHeader($headers);
            $body = $curl->get($url, [], ['CURLOPT_CONNECTTIMEOUT' => 5, 'CURLOPT_TIMEOUT' => 10]);
            if ($curl->get_errno()) {
                return null;
            }
            $status = (int)($curl->get_info()['http_code'] ?? 0);
        }
        if ($status !== 200) {
            return null;
        }
        $data = json_decode((string)$body, true);
        if (!is_array($data)) {
            return null;
        }
        if (!empty($data['isUnlimited'])) {
            return ['unlimited' => true, 'credits' => -1];
        }
        if (isset($data['creditsRaw']) && is_numeric($data['creditsRaw'])) {
            return ['unlimited' => false, 'credits' => (int)$data['creditsRaw']];
        }
        return null;
    }
}
