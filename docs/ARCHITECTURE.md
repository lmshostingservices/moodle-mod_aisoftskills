# mod_aisoftskills architecture

## Data

| Table | Holds |
|---|---|
| `aisoftskills` | Activity settings: industry, custom industry, level, content language, builder choices (JSON), picture style, retry/shuffle/sounds, grading, attempts, completion. |
| `aisoftskills_scene` | One moment on one picture: skill, title, context, speaker, question, English picture description. |
| `aisoftskills_option` | Exactly two per scene: text, `best`, indicator key, change (-50 to 50), consequence, reason. |
| `aisoftskills_attempt` | A learner's play-through: scene and response order (JSON), score, indicator values (JSON), times. |
| `aisoftskills_choice` | One per scene per attempt: the **first** response chosen (marked), whether it was the better one, tries, resolved. |
| `aisoftskills_ailog` | Teachers' AI picture requests (rate limit). |

Pictures are stored in the `sceneimage` file area, with the scene id as the item id.

## Code

- `local\catalogue`: industries, levels, skills, indicators and languages.
- `local\manager`: scenes, responses, pictures and grades. `save_scene()` enforces two responses with one better, and `clean_option()` enforces the direction of each change.
- `local\learning`: start/resume, choose, finish, summary, review and data deletion. The player data never contains `best`, changes, consequences or reasons; those come back only after a choice.
- `local\lesson`: the AI prompt, image prompt, draft parsing/cleaning and import.
- `local\credentials`: resolves the LMS Labs Site ID and API key. LMS Labs Central Config (`local_aiconfig`) comes first, then the plugin's standalone pair. A pair is used only when complete, the two are never mixed, and values are never copied.
- `local\ai\lmslabs`: `generate_image()` makes one POST to `/api/moodle/ai-softskills/images`. It sends the X-Site-ID, X-API-Key and new Idempotency-Key headers, with a body of exactly `{prompt, style}`. It accepts only an `image/png` 200 with a PNG signature and maps every documented error code to a message carrying the request id. It never retries. `balance()` is a read-only GET with the key in a header. Both use `local\credentials`.
- External functions: `start_attempt`, `choose_option`, `finish_attempt` (learners, own attempts only); `import_lesson` and `generate_image` (manage capability).
- AMD: `player` (scenes, consequence popup, gauge, confetti, results), `builder`, `scenes`, `report`, `copy`, `sound`, `ui`.

## Marking

- `choose()` records the first response. A later try (when retries are on) only sets `resolved`.
- Indicators start at 50 and move on every choice, clamped to 0–100.
- `finish_attempt()` requires every scene to be resolved. It sets the score to the share of first choices that were the better response, then updates grades, completion and the event.
