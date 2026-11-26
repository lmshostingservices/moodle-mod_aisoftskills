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
use mod_aisoftskills\local\byok\engine;
use mod_aisoftskills\local\byok\js;

/**
 * Bring your own AI: AI Soft Skills work that runs on the site's own AI instead of LMS Labs credits.
 *
 * AI Grader Central Config (local_aiconfig) decides, for each kind of work, whether it runs on LMS Labs credits, the
 * Moodle AI subsystem or the site's own key. Scene writing (and scenes from the teacher's own AI assistant) is text,
 * scene pictures are images (the people's name label positions are found by vision when the site's own AI reads
 * pictures) and voiceover is voice; each is decided separately.
 *
 * Work on the site's own AI runs the LMS Labs service's own logic ({@see engine}) inside this Moodle site and answers
 * every request exactly as the service would, at no charge. So the plugin's stored requests, refusals, "Check again"
 * and saved media work unchanged: {@see lmslabs::post()} asks here first.
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class byok {
    /** @var string The component Central Config knows this plugin by. */
    const COMPONENT = 'mod_aisoftskills';
    /** @var int Seconds a scene draft may take. */
    const TEXT_TIMEOUT = 120;
    /** @var string[] Chirp 3 HD voices with a female voice. */
    const FEMALE = ['Achernar', 'Aoede', 'Autonoe', 'Callirrhoe', 'Despina', 'Erinome', 'Gacrux', 'Kore', 'Laomedeia',
        'Leda', 'Pulcherrima', 'Sulafat', 'Vindemiatrix', 'Zephyr'];
    /** @var string[] Chirp 3 HD voices with a male voice. */
    const MALE = ['Achird', 'Algenib', 'Algieba', 'Alnilam', 'Charon', 'Enceladus', 'Fenrir', 'Iapetus', 'Orus', 'Puck',
        'Rasalgethi', 'Sadachbia', 'Sadaltager', 'Schedar', 'Umbriel', 'Zubenelgenubi'];
    /** @var string[] The closest OpenAI voice for each Chirp 3 HD voice. */
    const OPENAI_VOICES = ['Achernar' => 'shimmer', 'Aoede' => 'nova', 'Autonoe' => 'coral', 'Callirrhoe' => 'shimmer',
        'Despina' => 'coral', 'Erinome' => 'sage', 'Gacrux' => 'sage', 'Kore' => 'shimmer', 'Laomedeia' => 'nova',
        'Leda' => 'coral', 'Pulcherrima' => 'nova', 'Sulafat' => 'sage', 'Vindemiatrix' => 'sage', 'Zephyr' => 'sage',
        'Achird' => 'echo', 'Algenib' => 'onyx', 'Algieba' => 'ash', 'Alnilam' => 'onyx', 'Charon' => 'echo',
        'Enceladus' => 'ash', 'Fenrir' => 'ash', 'Iapetus' => 'echo', 'Orus' => 'ballad', 'Puck' => 'onyx',
        'Rasalgethi' => 'ballad', 'Sadachbia' => 'verse', 'Sadaltager' => 'echo', 'Schedar' => 'onyx',
        'Umbriel' => 'ash', 'Zubenelgenubi' => 'verse'];
    /** @var string[] Google's Chirp 3 HD locales for the plugin's content languages. */
    const LOCALES = ['ar-XA', 'cmn-CN', 'de-DE', 'en-AU', 'en-GB', 'en-IN', 'en-US', 'es-ES', 'es-US', 'fr-CA', 'fr-FR',
        'hi-IN', 'id-ID', 'it-IT', 'ja-JP', 'ko-KR', 'nl-BE', 'nl-NL', 'pl-PL', 'pt-BR', 'ru-RU', 'th-TH', 'tr-TR', 'vi-VN',
        'yue-HK'];
    /** @var string[] Locales the service also lists under another name (zh-CN for cmn-CN, zh-HK for yue-HK). */
    const ALIASES = ['zh-CN' => 'cmn-CN', 'zh-HK' => 'yue-HK'];

    /** @var string|null What the site's own AI said when it last failed, shown to the teacher. */
    public static $lasterror = null;

    /** @var bool Whether the last request was answered here, on the site's own AI. */
    public static $answered = false;

    /**
     * Whether a kind of work runs on the site's own AI now.
     *
     * @param string $kind text, image, voice or vision
     * @return bool
     */
    public static function uses(string $kind): bool {
        return class_exists('\\local_aiconfig\\byok\\ai') && \local_aiconfig\byok\ai::uses_own_ai(self::COMPONENT, $kind);
    }

    /**
     * Whether any work runs on the site's own AI now (the site then holds a Bring your own AI licence).
     *
     * @return bool
     */
    public static function any(): bool {
        return self::uses('text') || self::uses('image') || self::uses('voice');
    }

    /**
     * The service's answer to a request, made here on the site's own AI; null when the request goes to LMS Labs.
     *
     * @param string $route the route
     * @param string|null $body the JSON body (POST), or null for GET
     * @return array|null [status, headers (lower-case names), body], as {@see lmslabs::post()} answers
     */
    public static function answer(string $route, ?string $body): ?array {
        $routes = [
            lmslabs::TEXT_ROUTE => 'text', lmslabs::IMPORT_ROUTE => 'text',
            lmslabs::IMAGE_ROUTE => 'image', lmslabs::IMAGE_ROUTE . '/capabilities' => 'image',
            lmslabs::VOICE_ROUTE => 'voice', lmslabs::VOICE_CATALOG_ROUTE => 'voice', lmslabs::VOICE_QUOTE_ROUTE => 'voice',
        ];
        self::$lasterror = null;
        self::$answered = isset($routes[$route]) && self::uses($routes[$route]);
        if (!self::$answered) {
            return null;
        }
        self::hooks();
        $json = (string)$body;
        $id = \core\uuid::generate();
        $json200 = fn(array $data) => [200, ['content-type' => 'application/json', 'x-request-id' => $id],
            json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)];
        // Text and voice errors carry a code and message object; picture errors carry the code alone.
        $error = function (int $status, string $code, bool $structured = true, ?array $details = null) use ($id): array {
            $error = $structured ? ['code' => $code, 'message' => $code] + ($details === null ? [] : ['details' => $details])
                : $code;
            return [$status, ['content-type' => 'application/json', 'x-request-id' => $id],
                js::stringify((object)['requestId' => $id, 'error' => $structured ? (object)$error : $error])];
        };
        switch ($route) {
            case lmslabs::TEXT_ROUTE:
                [$status, $out, $details] = engine::scene($json);
                if ($status !== 200) {
                    return $error($status, $out, true, $details);
                }
                return [200, ['content-type' => 'application/json', 'x-request-id' => $id],
                    js::stringify((object)['requestId' => $id, 'draft' => js::parse($out), 'model' => engine::MODEL,
                        'creditsCharged' => 0, 'creditsBalance' => null])];
            case lmslabs::IMPORT_ROUTE:
                [$status, $out] = engine::import($json);
                return $status === 200 ? $json200(['requestId' => $id, 'creditsCharged' => 0, 'creditsBalance' => null])
                    : $error($status, $out);
            case lmslabs::IMAGE_ROUTE . '/capabilities':
                return $json200(['tariff' => ['image' => engine::IMAGE_TARIFF], 'styles' => engine::STYLES,
                    'maxPromptChars' => 2000, 'anchorPositions' => true]);
            case lmslabs::IMAGE_ROUTE:
                [$status, $out, $headers] = engine::image($json);
                return $status === 200 ? [200, $headers + ['content-type' => 'image/png', 'x-credits-charged' => '0',
                    'x-request-id' => $id], $out] : $error($status, $out, false);
            case lmslabs::VOICE_CATALOG_ROUTE:
                return $json200(['locales' => self::catalogue(), 'tariff' => ['tts' => lmslabs::VOICE_CREDITS],
                    'requestId' => $id]);
            case lmslabs::VOICE_QUOTE_ROUTE:
                return $json200(['requestId' => $id, 'credits' => 0, 'freeRemakesRemaining' => 0, 'firstClip' => false]);
            default:
                [$status, $out, $headers] = engine::speech($json, self::catalogue());
                return $status === 200 ? [200, $headers + ['content-type' => 'audio/mpeg', 'x-credits-charged' => '0',
                    'x-request-id' => $id], $out] : $error($status, $out);
        }
    }

    /**
     * The voice catalogue on the site's own voice: every Chirp 3 HD voice for each Chirp 3 HD locale of the plugin's
     * content languages, in the service's format. On an own OpenAI key each plays as the closest OpenAI voice; on other
     * voice services, as the service's own voice.
     *
     * @return array
     */
    public static function catalogue(): array {
        $names = array_merge(self::FEMALE, self::MALE);
        sort($names);
        $rows = [];
        foreach (self::LOCALES as $code) {
            $rows[$code] = ['locale' => $code, 'ttsLanguageCode' => $code,
                'voices' => array_map(fn($n) => ['name' => "{$code}-Chirp3-HD-{$n}",
                    'gender' => in_array($n, self::FEMALE, true) ? 'FEMALE' : 'MALE'], $names),
                'defaultFemale' => "{$code}-Chirp3-HD-Kore", 'defaultMale' => "{$code}-Chirp3-HD-Charon",
                'rates' => ['normal' => 1, 'slow' => 0.8]];
        }
        foreach (self::ALIASES as $alias => $code) {
            $rows[$alias] = ['locale' => $alias] + $rows[$code];
        }
        ksort($rows, SORT_STRING);
        return array_values($rows);
    }

    /**
     * Connect the ported service to the site's own AI.
     */
    protected static function hooks(): void {
        $ai = '\\local_aiconfig\\byok\\ai';
        engine::$text = fn(string $system, string $user, int $maxtokens, float $temperature, array $schema) => self::call(
            fn() => $ai::text(self::COMPONENT, $system, $user, ['maxtokens' => $maxtokens, 'temperature' => $temperature,
                'schema' => $schema, 'timeout' => self::TEXT_TIMEOUT])
        );
        engine::$image = fn(string $prompt) => self::call(
            fn() => $ai::image(self::COMPONENT, $prompt, ['size' => '1536x1024', 'quality' => 'medium'])
        );
        // Without vision on the site's own AI the service's own fallback applies: Moodle keeps its placement guesses.
        engine::$vision = !self::uses('vision') ? null : fn(string $system, string $user, string $png, array $schema) =>
            $ai::text(self::COMPONENT, $system, $user, ['images' => [['mime' => 'image/png', 'data' => $png]],
                'schema' => $schema, 'maxtokens' => 500, 'timeout' => 30]);
        engine::$speech = fn(string $text, string $locale, string $voice, float $rate) => self::call(
            fn() => $ai::speech(self::COMPONENT, $text, ['language' => self::ALIASES[$locale] ?? $locale,
                'voice' => self::voice($locale, $voice), 'format' => 'mp3', 'rate' => $rate])
        );
    }

    /**
     * The voice to ask the site's voice service for.
     *
     * @param string $locale
     * @param string $voice a catalogue voice name
     * @return string
     */
    public static function voice(string $locale, string $voice): string {
        $short = substr($voice, strrpos($voice, '-') + 1);
        $vendor = \local_aiconfig\byok\router::vendor(\local_aiconfig\byok\router::VOICE);
        if ($vendor === 'googletts') {
            return (self::ALIASES[$locale] ?? $locale) . '-Chirp3-HD-' . $short;
        }
        return $vendor === 'openai' ? (self::OPENAI_VOICES[$short] ?? 'shimmer') : '';
    }

    /**
     * Run a call to the site's own AI, keeping what it said when it fails.
     *
     * @param callable $call
     * @return string
     */
    protected static function call(callable $call): string {
        try {
            return (string)$call();
        } catch (\Throwable $e) {
            self::$lasterror = $e->getMessage();
            throw $e;
        }
    }
}
