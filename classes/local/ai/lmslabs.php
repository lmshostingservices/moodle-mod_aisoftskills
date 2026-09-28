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
 * AI Soft Skills uses LMS Labs for scene pictures: POST /api/moodle/ai-softskills/images, the dedicated route of the
 * AI Soft Skills image contract (owner-approved tariff: 5 credits per successful picture). Credentials go in headers
 * only, every intentional request carries a new Idempotency-Key, and a failed request is never retried automatically.
 * The balance check (GET /api/credits) is read-only and advisory. No other LMS Labs route or product billing is used.
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lmslabs implements provider {
    /** @var string Fixed LMS Labs host. */
    public const BASE_URL = 'https://lms-labs.com';

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
     * Creates one scene picture (one paid request; never retried automatically).
     *
     * @param string $prompt English picture description (at most MAX_PROMPT code points)
     * @param string $style illustration or photo
     * @return array bytes (PNG), charged, balance, requestid, model
     * @throws moodle_exception on any failure, with the LMS Labs request id when there is one
     */
    public function generate_image(string $prompt, string $style = 'illustration'): array {
        global $CFG;
        $credentials = \mod_aisoftskills\local\credentials::find();
        if ($credentials === null || !get_config('mod_aisoftskills', 'aiimages')) {
            throw new moodle_exception('ainotavailable', 'mod_aisoftskills');
        }
        $prompt = trim($prompt);
        if ($prompt === '' || \core_text::strlen($prompt) > self::MAX_PROMPT) {
            throw new moodle_exception('aierror_prompt_too_long', 'mod_aisoftskills');
        }
        $style = in_array($style, self::STYLES, true) ? $style : 'illustration';
        $url = self::BASE_URL . self::IMAGE_ROUTE;
        $headers = [
            'Content-Type: application/json',
            'Accept: image/png, application/json',
            'X-Site-ID: ' . $credentials['siteid'],
            'X-API-Key: ' . $credentials['apikey'],
            // A new key for every intentional request: a replay can never be charged twice.
            'Idempotency-Key: ' . \core\uuid::generate(),
        ];
        $body = json_encode(['prompt' => $prompt, 'style' => $style], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (self::$posttransport) {
            [$status, $responseheaders, $response] = (self::$posttransport)($url, $headers, $body);
        } else {
            require_once($CFG->libdir . '/filelib.php');
            $curl = new \curl(['ignoresecurity' => false]);
            $curl->setHeader($headers);
            $response = $curl->post($url, $body, [
                'CURLOPT_CONNECTTIMEOUT' => 10,
                // The route answers within 85 seconds.
                'CURLOPT_TIMEOUT' => 95,
                'CURLOPT_FOLLOWLOCATION' => false,
            ]);
            if ($curl->get_errno()) {
                throw new moodle_exception('aierror_network', 'mod_aisoftskills');
            }
            $info = $curl->get_info();
            $status = (int)($info['http_code'] ?? 0);
            $responseheaders = [];
            foreach ((array)$curl->getResponse() as $name => $value) {
                $responseheaders[strtolower((string)$name)] = is_array($value) ? end($value) : (string)$value;
            }
        }
        $responseheaders = array_change_key_case((array)$responseheaders, CASE_LOWER);
        $requestid = clean_param((string)($responseheaders['x-request-id'] ?? ''), PARAM_ALPHANUMEXT);
        $type = strtolower(trim(explode(';', (string)($responseheaders['content-type'] ?? ''))[0]));
        if ((int)$status === 200 && $type === 'image/png' && strncmp((string)$response, "\x89PNG\r\n\x1a\n", 8) === 0) {
            return [
                'bytes' => (string)$response,
                'charged' => (int)($responseheaders['x-credits-charged'] ?? self::IMAGE_CREDITS),
                'balance' => isset($responseheaders['x-credits-balance']) ? (int)$responseheaders['x-credits-balance'] : null,
                'requestid' => $requestid,
                'model' => clean_param((string)($responseheaders['x-image-model'] ?? ''), PARAM_TEXT),
            ];
        }
        $data = json_decode((string)$response, true);
        $code = is_array($data) ? strtolower(clean_param((string)($data['error'] ?? ''), PARAM_ALPHANUMEXT)) : '';
        if ($requestid === '' && is_array($data)) {
            $requestid = clean_param((string)($data['requestId'] ?? ''), PARAM_ALPHANUMEXT);
        }
        if ((int)$status === 200) {
            $code = 'unusable_image';
        } else if ($code === '' || !get_string_manager()->string_exists('aierror_' . $code, 'mod_aisoftskills')) {
            $code = (int)$status === 404 ? 'not_live' : 'failed';
        }
        $a = (object)['requestid' => $requestid !== '' ? $requestid : '-', 'balance' => (int)($data['balance'] ?? 0),
            'credits' => self::IMAGE_CREDITS];
        throw new moodle_exception('aierror_' . $code, 'mod_aisoftskills', '', $a);
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
