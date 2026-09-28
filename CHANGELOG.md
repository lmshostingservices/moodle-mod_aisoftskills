# Changelog

All notable changes to mod_aisoftskills are recorded here.

## [v1.0.2] - 2026-09-28

### Added

- Separate teacher-only scene/script text drafting with the approved LMS Labs scene-draft endpoint. A validated successful draft costs 3 credits; image generation remains a separate 5-credit operation. The dialogue/script is not silently imported into the branching two-choice lesson format.
- Persist each text request's key and body on Moodle before contacting LMS Labs. Show explicit pending, retry, expiration and errors; manually resuming uses the same key and body. Save the original completed draft and a teacher-editable copy in Moodle.
- Persist image-generation intent, key, site and exact prompt/style before each paid call. Checking an uncertain image request keeps its key; a completed 410 never promises image-byte replay. New teacher intent explicitly confirms the separate 5-credit charge.

### Fixed

- Moodle 4.4/4.5 Code Checker coverage warnings via method-level PHPUnit annotations.
- Mustache lint failures caused by meter roles on generic elements; label numeric graphics in templates and the player AMD module instead.

## [v1.0.1] - 2026-09-28

Release-pipeline test build.

### Changed

- Version 2026092801, release 1.0.1. No functional changes from 1.0.0 (version 2026092800).

## [v1.0.0] - 2026-09-28

First release.

### Added

- Activity module for practising workplace soft skills. Each scene shows one picture with two possible responses.
- **Better choice:** confetti, sounds and a consequence popup with an animated semicircle gauge for the indicator it moves.
- **Poorer choice:** the popup shows what went wrong, and the learner can optionally try the other response. Only the first choice is marked.
- Career levels: worker, supervisor, manager and leader. The teacher picks one level per activity; it shapes the prompt and appears as a ladder on the activity page.
- 16 industries plus a custom one, 23 soft skills in four groups, 10 workplace indicators, and 20 content languages including right-to-left Arabic.
- Scene builder wizard: workplace, level, skills, language and number of scenes. It writes a prompt for any AI assistant, then previews and imports the reply. Imported drafts are validated: two responses per scene, one better; the better response raises its indicator and the poorer one never does.
- Scene list with picture upload (images or ZIP), a picture prompt per scene set in the industry, reordering and deletion. A scene editor form covers the scene and both responses.
- Reports on learners, scenes and attempts, with group filtering, downloads, attempt deletion and regrading.
- Gradebook (highest, average, first or last attempt), a custom completion rule (play every scene), events, course reset, backup and restore (including user data), and duplication.
- Privacy API provider for attempts, choices, the AI request log and grades.
- LMS Labs credentials come from LMS Labs Central Config (`local_aiconfig`: `siteid` and `apikey`) when it has both. The plugin's standalone Site ID and API key are a fallback, used only as a complete pair and never mixed with central values. Values are read when needed, never copied, so Central Config changes apply at once. The settings page shows which pair is in use.
- AI scene pictures through the dedicated LMS Labs route `/api/moodle/ai-softskills/images`: 5 credits per successful picture (owner-approved), 1600×1000 PNG, header-only credentials, a new idempotency key per request, no automatic retries, clear error messages with the LMS Labs reference, a site switch and the `useai` capability. The read-only balance check is advisory.
- Mustache templates and ES6 AMD modules with built files.

### Fixed before release

- Starting or resuming an attempt failed with "Error reading from database" on MySQL and MariaDB. Two queries used a `sceneid, *` field list, which PostgreSQL accepts but MySQL and MariaDB reject. They now name their fields.

### Verified before packaging

- PHPUnit: 32 tests and 232 assertions pass on Moodle 4.4.12+ and 4.5.14+ (PHP 8.3), and on 5.0, 5.1, 5.2 and 5.3beta (PHP 8.4). Core privacy provider tests also pass on all six. The plugin tests also pass on MariaDB 10.11 with Moodle 4.5.
- Behat: 9 scenarios pass on Moodle 5.3beta (no JavaScript needed).
- moodle-cs `moodle-extra` standard: no errors or warnings. Grunt ESLint and stylelint pass.
- Browser runs (Chromium) on Moodle 4.5:
  - the full learner flow (poorer choice, retry, better choice with confetti and gauge, results) on desktop and phone sizes, and in Arabic (right to left);
  - the teacher flow: wizard, prompt, paste and preview, create, scene list, editor, reports.
- axe WCAG 2.1 AA scans of every page and popup visited report no violations.
- Installed from the ZIP through *Install plugins* on a Moodle 4.4 site after a clean uninstall. The deployed files match the ZIP and `check_database_schema` is clean.
