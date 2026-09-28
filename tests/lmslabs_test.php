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

use mod_aisoftskills\local\ai\lmslabs;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the LMS Labs connection (balance only; no network).
 *
 * @package    mod_aisoftskills
 * @category   test
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_aisoftskills\local\ai\lmslabs
 */
#[CoversClass(lmslabs::class)]
final class lmslabs_test extends \advanced_testcase {
    /**
     * Resets the transport.
     */
    protected function tearDown(): void {
        lmslabs::$transport = null;
        lmslabs::$posttransport = null;
        parent::tearDown();
    }

    /**
     * Pictures need the site switch and a complete credential pair.
     * @covers \mod_aisoftskills\local\ai\lmslabs
     */
    public function test_generation_switch(): void {
        $this->resetAfterTest();
        set_config('aiimages', 1, 'mod_aisoftskills');
        $p = new lmslabs();
        $this->assertFalse($p->can_generate(), 'No credentials.');
        set_config('lmslabssiteid', 'site', 'mod_aisoftskills');
        set_config('lmslabsapikey', 'key', 'mod_aisoftskills');
        $this->assertTrue($p->is_connected());
        $this->assertTrue($p->can_generate());
        set_config('aiimages', 0, 'mod_aisoftskills');
        $this->assertFalse($p->can_generate());
        lmslabs::$posttransport = function () {
            $this->fail('No request may be made while AI pictures are off.');
        };
        $this->expectException(\moodle_exception::class);
        $p->generate_image('x');
    }

    /**
     * A picture request: header-only credentials, a fresh idempotency key, exactly prompt and style, PNG back.
     * @covers \mod_aisoftskills\local\ai\lmslabs
     */
    public function test_generate_image_success(): void {
        $this->resetAfterTest();
        set_config('aiimages', 1, 'mod_aisoftskills');
        set_config('lmslabssiteid', 'My Site', 'mod_aisoftskills');
        set_config('lmslabsapikey', 'secret', 'mod_aisoftskills');
        $png = file_get_contents(__DIR__ . '/fixtures/scene.png');
        $seen = [];
        lmslabs::$posttransport = function ($url, $headers, $body) use (&$seen, $png) {
            $seen[] = [$url, $headers, $body];
            return [200, ['Content-Type' => 'image/png', 'X-Request-Id' => 'req-1', 'X-Credits-Charged' => '5',
                'X-Credits-Balance' => '95', 'X-Image-Model' => 'gpt-image-2'], $png];
        };
        $p = new lmslabs();
        $result = $p->generate_image('A calm team huddle', 'photo');
        $this->assertSame($png, $result['bytes']);
        $this->assertSame(5, $result['charged']);
        $this->assertSame(95, $result['balance']);
        $this->assertSame('req-1', $result['requestid']);
        [$url, $headers, $body] = $seen[0];
        $this->assertSame('https://lms-labs.com/api/moodle/ai-softskills/images', $url);
        $this->assertContains('X-Site-ID: My Site', $headers);
        $this->assertContains('X-API-Key: secret', $headers);
        $this->assertSame(['prompt' => 'A calm team huddle', 'style' => 'photo'], json_decode($body, true));
        $this->assertStringNotContainsString('secret', $body . $url);
        $keys = preg_grep('/^Idempotency-Key: [0-9a-f-]{36}$/', $headers);
        $this->assertCount(1, $keys);
        // A second intentional request uses a new key.
        $p->generate_image('A calm team huddle', 'photo');
        $this->assertNotSame(array_values($keys), array_values(preg_grep('/^Idempotency-Key: /', $seen[1][1])));
        $this->assertCount(2, $seen, 'Exactly one request per call; nothing is retried.');
    }

    /**
     * Errors map to clear messages with the LMS Labs reference, are never retried, and a 200 that is not a PNG fails.
     * @covers \mod_aisoftskills\local\ai\lmslabs
     */
    public function test_generate_image_errors(): void {
        $this->resetAfterTest();
        set_config('aiimages', 1, 'mod_aisoftskills');
        set_config('lmslabssiteid', 'site', 'mod_aisoftskills');
        set_config('lmslabsapikey', 'key', 'mod_aisoftskills');
        $cases = [
            [402, ['error' => 'INSUFFICIENT_CREDITS', 'requestId' => 'r2', 'balance' => 4], 'aierror_insufficient_credits'],
            [403, ['error' => 'NO_ENTITLEMENT', 'requestId' => 'r3'], 'aierror_no_entitlement'],
            [503, ['error' => 'SETTLEMENT_UNCONFIRMED', 'requestId' => 'r4'], 'aierror_settlement_unconfirmed'],
            [404, ['error' => 'Not found'], 'aierror_not_live'],
            [500, ['error' => 'SOMETHING_NEW'], 'aierror_failed'],
            [200, 'not a picture', 'aierror_unusable_image'],
        ];
        $p = new lmslabs();
        foreach ($cases as [$status, $body, $expected]) {
            $calls = 0;
            lmslabs::$posttransport = function () use ($status, $body, &$calls) {
                $calls++;
                return [$status, ['content-type' => $status === 200 ? 'image/png' : 'application/json'],
                    is_array($body) ? json_encode($body) : $body];
            };
            try {
                $p->generate_image('x');
                $this->fail('Accepted HTTP ' . $status);
            } catch (\moodle_exception $e) {
                $this->assertSame($expected, $e->errorcode);
                $this->assertSame(1, $calls);
            }
        }
        lmslabs::$posttransport = fn() => [402, [], json_encode(['error' => 'INSUFFICIENT_CREDITS', 'requestId' => 'r9',
            'balance' => 4])];
        try {
            $p->generate_image('x');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString('4 are left', $e->getMessage());
            $this->assertStringContainsString('r9', $e->getMessage());
        }
        $this->expectException(\moodle_exception::class);
        $p->generate_image(str_repeat('é', lmslabs::MAX_PROMPT + 1));
    }

    /**
     * The balance is read with the key in the X-API-Key header, never the URL.
     * @covers \mod_aisoftskills\local\ai\lmslabs
     */
    public function test_balance(): void {
        $this->resetAfterTest();
        $p = new lmslabs();
        $this->assertNull($p->balance(), 'Not connected.');
        set_config('lmslabssiteid', 'My Site', 'mod_aisoftskills');
        set_config('lmslabsapikey', 'secret', 'mod_aisoftskills');
        $seen = [];
        lmslabs::$transport = function ($url, $headers) use (&$seen) {
            $seen = [$url, $headers];
            return [200, json_encode(['siteId' => 'My Site', 'credits' => 120, 'creditsRaw' => 120, 'isUnlimited' => false])];
        };
        $this->assertSame(['unlimited' => false, 'credits' => 120], $p->balance());
        $this->assertSame('https://lms-labs.com/api/credits?siteId=My%20Site', $seen[0]);
        $this->assertStringNotContainsString('secret', $seen[0]);
        $this->assertContains('X-API-Key: secret', $seen[1]);
        lmslabs::$transport = fn() => [200, json_encode(['credits' => 'unlimited', 'creditsRaw' => -1, 'isUnlimited' => true])];
        $this->assertSame(['unlimited' => true, 'credits' => -1], $p->balance());
        lmslabs::$transport = fn() => [401, json_encode(['error' => 'Invalid credentials'])];
        $this->assertNull($p->balance());
    }
}
