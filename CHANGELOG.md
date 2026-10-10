# Changelog

All notable changes to mod_aisoftskills are recorded here.

## [v1.4.13] - 2026-10-12

### Added

- **Full screen.** A full screen button in the player's top bar fills the screen with the scenes, the feedback and the results (Esc or the button leaves). The picture grows with the screen height while the cards and responses stay in view; nothing scrolls sideways, and a long scene scrolls inside. Browsers without full screen for part of a page (such as iPhone Safari) get a full-window view instead. An error message leaves full screen first, so it is never hidden.

### Fixed

- On short screens the scene's speaker button sat beside a narrowed picture; it now always sits on the picture's top-right corner.
- On the results, the sound button (and now the full screen button) stay on the right of the bar.

## [v1.4.12] - 2026-10-12

### Changed

- **The AI-assistant prompt gives character limits, not word counts.** AI assistants count words loosely, so scenes came back just over Moodle's limits ("456 / 450 characters"). The prompt now asks for at most 400 characters for what is happening, 140 for the question, 260 for each response and consequence, 190 for each reason and 50 for who the learner is (each a little under Moodle's limit), and to count and shorten before replying. It also says never to name a real person, brand or business.

## [v1.4.11] - 2026-10-12

### Fixed

- **The responses are read in a voice that matches the learner's character.** The learner's label ("You - Shift supervisor") rarely says he or she, so its automatic voice was the first voice in the list (a woman's), even in an all-male scene. A label with no voice kind now takes it from the scene text and, failing that, from the picture description ("the shift supervisor, a man in his forties"), the learner by their role. This also applies to other people, such as "Dr Reeves". The AI-assistant prompt now asks for the learner's character in the picture description too. Where a voice changes, those clips are listed to make again in the Voiceover step.

## [v1.4.10] - 2026-10-11

### Changed

- **No clip is made while its price is unknown.** When the LMS Labs voice list does not publish a price per clip (`tariff.tts`), Moodle now waits as it does for a different price, instead of going ahead: a clip sent without a ceiling to an older LMS Labs could cost more than the teacher was shown (LMS Labs' recommendation of 11 Oct 2026). LMS Labs publishes `tariff.tts: 2` in development.

### Fixed

- **Pictures follow the chosen style.** A scene's picture description written while the activity had the other style (or by an AI assistant that wrote "a realistic photograph of ...") could override the activity's choice, so an illustration activity got photos. The description's style words are now swapped for the chosen style, and the prompt states the style as a firm rule ("must look drawn, never like a photograph", or the reverse for photos). Pictures already made are kept; make a new picture for any that do not match.

## [v1.4.9] - 2026-10-11

### Changed

- **Voiceover costs 2 credits per clip** (owner-approved 11 Oct 2026; was 5). Every price shown and every ceiling sent (`maxCredits`) uses 2.
- **No clip is made at a price the teacher was not shown.** Moodle reads the price per clip that LMS Labs publishes in its voice catalogue (`tariff.tts`). While it is not 2, the Voiceover step says so and no new clip is asked for; clips already made keep playing. When LMS Labs publishes 2, voiceover works again without a new Moodle version. The catalogue is read again after this upgrade so the price is known at once (its cache key changed; no cache is purged).

### Fixed

- **Labels written role first are different people.** "RN - Fatima", "RN - Thomas" and "Junior RN - Eli" were all read as one person called "RN", so they shared one voice. A label is now read as name and role in either order, and a role before the name ("RN Priya", "Nurse Priya") is split off; titles stay with the name ("Dr Reeves"). The picture prompt reads labels the same way.
- While the voiceover or pictures are being made, Back, Next and the step links now look disabled (they were already blocked).
- The response speaker icon is centred in its circle; there is more room between the gauge needle and the number.

### Added

- **Headings are read out.** The voiceover reads each card after its heading, as learners see them: "The situation", "Good to know", and in the feedback "What happened" and "Why this works" (or "Why this falls short"). Each heading is one shared clip for the whole activity, so it is made once, not once per scene. Because what is happening is now read card by card, scenes whose voiceover was made before this version need their scene clips made again (the Voiceover step lists them).
- **The card being read rises off the page** with a soft shadow, instead of an outline around both cards.
- **Listen to the feedback first** (activity setting): the feedback is read out on its own after each choice, and Next scene and Try again stay greyed out until it has played to the end.
- **Labels need no typing.** Every label is shown and saved as "Name - Role", and anyone the scenario names who has no label is added when the label editor opens, so the teacher only drags each label into place (they can still edit or remove one).
- **Clearer review slides.** Each scene shows "Your response" with a big green tick or red cross, and, when it was not the better one, "Correct response" beside it with a green tick, each with why it works or falls short. Opening a slide plays a chime for a better first response and a low tone otherwise (when sounds are on).
- **Responses are written as spoken words.** The AI-assistant prompt asks for the exact words the learner says to the person, by name ("Fatima, I'll take over your handover now..."), never a description of what to do. Existing scenes keep their wording until edited or written again.

## [v1.4.8] - 2026-10-11

### Changed

- Each response's speaker icon now sits under its letter (A, B, C), on the left of the card.

### Added

- **Teachers are told why a scene is silent.** Someone who can manage the activity and tries it sees a short note, never shown to learners, when the voiceover can't play: it is turned off in the plugin settings, LMS Labs hasn't sent its list of voices to the site, or some of the scene's clips are not made yet (for example "Add voiceover" was not run, or the scene or a voice changed).

## [v1.4.7] - 2026-10-11

### Fixed

- **Name labels now find everyone the scenario names.** Before, a person was only suggested when a lower-case job came right before the name ("nurse Priya"), so "RN Fatima" and "Dr Reeves" at the start of a sentence were missed and a scene with three people got one or two labels. Every named person is now found: titles (Dr, Mrs, Prof), full names (Priya Sharma), abbreviated roles ("RN Fatima" becomes "Fatima - RN") and names at the start of a sentence. Places and organisations (Royal Perth Hospital) and common words are not taken for people.
- A person's voice (woman or man) is no longer read from a sentence about someone else ("Dr Reeves waits. Fatima says she is fine." says nothing about Dr Reeves).
- **People without a label can be added with one click.** The label editor lists everyone the scenario names who has no label yet (for scenes whose labels were saved before this fix), and adds them in one click.

## [v1.4.6] - 2026-10-11

### Fixed

- The upgrade no longer purges a cache. On some sites (seen on Moodle 4.4) the voice catalogue cache could not be purged during the web upgrade, which stopped the upgrade with an error. The catalogue is now read again because its cache key changed. A site whose upgrade stopped can simply run the upgrade again with this version.

## [v1.4.5] - 2026-10-11

Follows the LMS Labs review of 9 Oct 2026 of the free-remake integration.

### Fixed

- A missing charge (no `X-Credits-Charged` header, or no `creditsCharged` in a text or import answer) is no longer recorded as a charge. It is kept as not known, and the teacher sees "LMS Labs did not say what it charged: at most N credits, as you confirmed". The confirmed ceiling stays with the stored request (`maxCredits`).
- Dialogue lines now have stable ids, kept through edits (unchanged and edited lines keep theirs, new lines get new ones). A clip's place (`clipRef`) uses the line's id, so deleting or adding a line no longer moves other lines onto a neighbour's free-remake allowance.

## [v1.4.4] - 2026-10-11

Follows the LMS Labs response of 9 Oct 2026 on free voiceover remakes (approved by Jamie: 10 free remakes per clip in a rolling 30 days).

### Added

- **Free voiceover remakes after an edit.** Once the LMS Labs catalogue says `clipRefSupported`, every clip is sent with `clipRef` (a stable SHA-256 of the clip's place: JSON array of component, site, activity, scene, part, line and clip number; no text or voice) and `maxCredits` (the price the teacher confirmed: 0 or 5).
- Before anything is made, each missing clip is priced with the free `speech/quote` route. The confirmation shows the split, for example "Create 9 voiceover clips? 2 are new: 10 credits. 7 are made again after an edit: free."
- A free clip whose price has gone up is refused by LMS Labs (409 `PRICE_CHANGED`) before anything is made or charged; Moodle says so and asks the teacher to confirm the new price.
- Until LMS Labs supports the new fields, clips are sent exactly as before.

## [v1.4.3] - 2026-10-11

### Added

- **Choose any of the 8 voices for each name label**, grouped female and male. "Automatic" shows the voice it gives (for example "Automatic female voice: Leda"). The narrator's voice is shown but can't be chosen. The learner's label can have a voice too, and nobody else then gets it.
- The label editor warns when two people in the scene would sound alike.
- **The scenario in the label editor**, with the names in it marked, so the teacher can check who is who.
- While the voiceover (or the pictures) is being made, Back and Next are locked, leaving the page asks first, and a **Stop** button stops after the clip being made.
- After editing a scene that had voiceover, Moodle says how many changed sentences need a new clip. Unchanged sentences keep theirs.

### Fixed

- Suggested labels no longer take words such as "quietly mentions" for a role ("Priya - Quietly mentions" is now "Priya"), and titles stay with their name ("Mrs Tanaka - Resident", a woman).

### Changed

- **Pictures match the names in the scene**: the picture request lists every labelled person with their role and voice gender, and asks for each to look like their name and role suggest (for example, Priya looks South Asian). The AI-assistant prompt asks for the same in each picture description, plus where each person stands.

## [v1.4.2] - 2026-10-11

Follows the LMS Labs handover "AI Soft Skills: complete three-response drafts" (9 Oct 2026, option A). No tariff change: 5 credits per scene.

### Changed

- **"Use your own text" makes complete scenes**: `scenes/draft` now also returns who the learner is, the question and three responses (better, poorer, very poor). Moodle saves them with the scene (wire `kpiDelta` becomes `kpidelta`; the very poor drop is kept at least double). Script-only drafts, including old receipts replayed as they were, still arrive without responses for the teacher to write, as before; nothing is asked for again.
- Developer comments no longer mention old prices or "per delivered" charging.

## [v1.4.1] - 2026-10-11

Follows the LMS Labs release response of 9 Oct 2026 (speech enabled for AI Soft Skills, 5 credits per scene and per clip in development), and Jamie's requests of 9 Oct. No tariff change.

### Added

- **A third, very poor response (C)** to make scenes harder. It makes things much worse: its indicator drop is at least double the poorer response's ("double minus points"), with its own red feedback ("That made things much worse.") and results slide. Scenes can have two or three responses; the editor has an optional Response C and a "Very poor response" choice.
- The ChatGPT/AI-assistant prompt now asks for three responses per scene (better, poorer, very poor) in a random order.
- With "Shuffle the order of the responses" on, the better response is spread evenly over A, B and C across the scenes, and the very poor one moves too.
- **Traffic-light indicators**: the indicator bars are red, amber or green. The bands are activity settings (default: amber from 40, green from 70).

### Changed

- **The scene fits on one screen**: the indicators sit in the top bar; the picture and its context cards are on the left; the role line, question and responses are on the right. The picture shrinks on short screens so nothing falls below the fold. Phones keep one column, with the role line first.

### Fixed

- A voiceover answer of 503 `VOICEOVER_NOT_ENABLED` (or the older `TARIFF_NOT_APPROVED`) or `PROVIDER_NOT_CONFIGURED`, and 502 `UNUSABLE_AUDIO`, now end as "not created, not charged" with their own message, instead of "uncertain".
- Charging wording: LMS Labs charges a clip when it makes it, not when it reaches the site. A clip it made is charged even if the page is closed before it arrives.
- A request that can no longer return audio (410) no longer says it was charged or that it is safe to make again: keep the reference and contact LMS Labs support first.
- The voice catalogue kept from earlier versions is forgotten on upgrade.

## [v1.4.0] - 2026-10-10

Builds on 1.3.1. New tariffs approved by the owner on 8 Oct 2026: **5 credits per scene** (LMS Labs AI or the teacher's own AI assistant; was 3) and **5 credits per voiceover clip**. Unchanged: 50 to unlock, 5 per picture. LMS Labs charges these; Moodle shows them. Voiceover stays off until LMS Labs publishes the speech routes.

### Added

- **Voiceover** (Google Chirp 3 HD, 8 voice types): a narrator voice chosen in the plugin settings reads the scenario and the question; the people in the picture speak with voices matching their name labels (never the narrator's); the two responses are read in the learner's voice, and the feedback (what happened, why) by the narrator. Each activity chooses what is read. New set-up step 7 "Voiceover" creates the missing clips one after another.
- **"Listen before answering"**: the responses open once the scene's voiceover has played to the end.
- **Name labels on pictures** ("Leo - Bartender"): suggested from the scene text, dragged into place by the teacher, shown over the picture.
- **Practice and test modes**, either or both, with a pass mark, "Take the test again", and the completion rule "Pass the test".
- **Results as slides**: score and indicators first, then one slide per scene with the better response and why.

### Changed

- The scene setting is shown as short cards (The situation, What you do, Good to know) under a role line ("As the bar shift supervisor, how would you handle this situation?") instead of a caption on the picture.
- Feedback is shown as cards, one sentence per paragraph.
- The better response is first in half of a test's scenes and second in the other half.
- Text limits so every scene fits on the page, with character counters in the editor; the AI assistant prompt asks for shorter text.

## [v1.3.1] - 2026-10-09

Builds on 1.3.0, which it replaces. It follows the LMS Labs handover of 9 Oct 2026 for the scene import route. No tariff or database changes.

### Changed

- **A 404 from LMS Labs always means "not switched on at LMS Labs yet"**, whatever error code it carries. 1.3.0 showed the vague "could not confirm these scenes" message when the import route was not yet deployed.
- **An unexpected server error (5xx) is never taken as "not charged".** The request is kept for "Check again" with the same key. Only LMS Labs' documented provider failures count as a definite no.
- **"Already used with different content" (409) no longer says "create it again".** It now says the earlier request may still have been charged and to contact LMS Labs support first.
- **Every scene sent for charging has a title.** An empty title becomes "Scene N", because LMS Labs rejects empty titles.

## [v1.3.0] - 2026-10-08

Builds on 1.2.1, which it replaces. The owner approved both changes on 8 Oct 2026. The tariffs are unchanged: 50 credits to unlock, 3 per scene, 5 per picture.

### Changed

- **The whole plugin needs this site to be unlocked with LMS Labs**, either with 50 credits or free when LMS Labs has a record of a Moodle Marketplace purchase. Until then:
  - teachers can't open any set-up step;
  - learners see "This activity is not available yet";
  - the web services refuse to start anything.

  Requests already made can still be checked and dismissed. Administrators get a link to the activation panel in the plugin settings.
- **An unlocked site stays usable during an LMS Labs outage.** If "Check access" gets no definite answer, an unlocked site keeps working. Only a definite "locked" answer locks it again.
- **Scenes written with the teacher's own AI assistant now cost 3 credits each**, the same as scenes written by LMS Labs AI:
  - The teacher confirms the total before anything is sent.
  - Moodle sends LMS Labs only the number of scenes and their titles, to `POST /api/moodle/ai-softskills/scenes/import` with an Idempotency-Key.
  - The scenes are created only after LMS Labs confirms the charge. A refusal creates nothing.
  - An unconfirmed charge is checked again with the same key, so it can never be charged twice.
- **This route is new and has been requested from LMS Labs.** Until it is live, the teacher is told that this way of creating scenes isn't available yet.
- **The free "Add scenes" upload on the Check the scenes step has been removed.** Every scene is now created in step 5, where both ways are charged. Teachers can still upload their own picture for any scene in step 6.

## [v1.2.1] - 2026-10-08

Builds on 1.2.0, which it replaces. No tariff, route, unlock contract or database changes.

### Changed

- **Activation is now part of the plugin settings page**, like the other LMS Labs plugins. The separate "AI Soft Skills activation" admin page is gone. The settings page shows:
  - the last known access;
  - where the Site ID and API key come from;
  - the balance;
  - "Check access" and "Unlock…".
- Opening the settings page never calls LMS Labs and never spends credits. The live price is fetched only after "Unlock…", on the confirmation step, and nothing is bought until the administrator confirms that price.
- After an action, the administrator returns to the settings page with the result.
- When LMS Labs has not confirmed the release or price, the message now says why.

### Fixed

- The separate activation page in 1.1.0 to 1.2.0 failed with an error when opened, so sites could not check access or unlock. The panel in the settings page replaces it and has its own tests.

## [v1.2.0] - 2026-10-02

Builds on 1.1.1, which it replaces. No tariff, route or database changes.

### Changed

- **One set-up path in eight steps.** Teachers move through the steps with Back and Next only:
  1. Workplace.
  2. Level.
  3. Skills.
  4. Language.
  5. Create the scenes.
  6. Pictures.
  7. Check the scenes.
  8. Finish.

  A step bar shows where they are ("Step 6 of 8"); it is not a set of links. Next stays closed until the step is done, and says why:
  - at least one scene is needed after step 5;
  - every scene needs a picture after step 6;
  - every scene needs its two responses after step 7.
- **One teacher entry point.** The separate "Build scenes" and "Scenes" pages are gone from the activity menu and the teacher bar. "Set up the lesson" opens the path at the first step that isn't done yet.
- **Pictures are a step of their own, before checking the scenes.** "Create missing pictures" is the main button. Each scene also has "Create picture" and "Upload my own picture".
- **Check the scenes** lists each scene's responses and offers "Add the two responses" where they are missing. The editor shows the step bar, and saving it returns to step 7. "Add scenes" is folded away.
- **Finish** shows how many scenes are ready and what is still missing, with "Preview as a learner" and "Done: back to the course".

### Fixed

- **Delivered pictures are no longer thrown away over their format.** Earlier versions kept a delivered picture only if LMS Labs sent raw PNG bytes labelled image/png, while the LMS Labs image specification allows PNG or WebP. Anything else (WebP, JPEG, or a picture wrapped as base64 in JSON) was marked "lost", even though it may have been charged. Moodle now:
  - accepts PNG, WebP and JPEG, checked by their bytes and by Moodle's image check;
  - accepts the same formats as base64 in a JSON answer;
  - asks for `Accept: image/png, image/webp, image/jpeg, application/json`.

  If an answer still can't be used, its content type and size (never the bytes) are kept with the request so that LMS Labs support can trace it.

## [v1.1.1] - 2026-10-02

Builds on 1.1.0, which it replaces. No server, tariff or database changes.

### Changed

- **Simpler "Build scenes" page.** Teachers first pick one of two cards, and only that path is shown:
  - **Use your own text:** paste or type a workplace situation. LMS Labs AI turns it into a scene (3 credits, confirmed first, charged only on delivery). Guidance covers length (30 to 300 words), what works well, what to include and removing real names. Leaving the box empty creates a scene from the builder choices.
  - **Use an AI assistant:** copy the prompt into ChatGPT, Claude, Gemini or Copilot for more control, paste the reply and preview it. No LMS Labs credits.
- The separate "Who the learners are" and "The workplace" fields are gone; they are taken from the builder choices.
- The prompt text is folded under "Show the prompt"; "Copy prompt" sits in step 1.
- When LMS Labs AI scene writing is not available, only the AI assistant path is shown, with a note saying why.

## [v1.1.0] - 2026-09-30

Release candidate built on the live 1.0.3. It has not been tested against the live LMS Labs service, and no paid provider call was made to test it.

### Added

- **Scene drafts become scenes.** A scene delivered by the approved LMS Labs scene route (3 credits) is saved straight away as a scene:
  - The title and setting.
  - The lead-in conversation, shown to learners above the question and editable as "Name: line" rows.
  - A teachers-only teaching note.
  - A picture description.
  The teacher adds the two responses; the route does not supply them yet.
- **Pictures come from LMS Labs AI only.**
  - "Create picture (5 credits)" is on every scene without a picture.
  - "Create missing pictures" makes all of them one after another.
  - "New AI picture" replaces an existing one.
  - The copy-this-prompt-into-another-AI picture option has been removed. Teachers can still upload their own pictures.
- **Every paid request is confirmed first**, with the credits named: each scene draft, each picture, and the batch of missing pictures.
- **Stored requests** (`aisoftskills_aireq`) for scene drafts and pictures:
  - The Idempotency-Key and the exact body are saved before sending, and are bound to the LMS Labs site they were sent for.
  - "Check again" resends the same key and body, so LMS Labs can never charge twice for it.
  - Requests that are pending, unconfirmed, conflicting, expired or lost stay listed until dismissed.
  - Pictures follow their own contract: nothing is retained upstream, so an undelivered picture is shown as lost with its reference.
- **End-of-attempt debrief.** "What to take away" lists each scene where the first choice was the poorer one, with the stronger response and why it works.
- **Advanced path for complex scenarios.** The copy-and-paste prompt for ChatGPT or another assistant is kept, and now asks for demanding scenes: competing interests, pressure, missing information, emotions under the surface, tempting realistic mistakes and knock-on consequences.
- **Activation tests:** mocked tests for the 1.0.3 activation page, covering the following (1.0.3 had none):
  - Not checked, locked, unlocked and unable to verify.
  - Credentials present but not unlocked.
  - Review without buying.
  - Unlock sends the exact live price and SHA; the release is refused when it isn't sellable.
  - Already unlocked, with historic credits never shown as a new charge.
  - Restoring a Marketplace purchase at zero credits.
  - Insufficient credits.
  - Stale price or release.
  - An uncertain result blocks another unlock until a free check gives a definite answer.

### Changed

- The builder now leads with "Create a scene with LMS Labs AI", written in plain words, followed by the advanced path. The confusing "script text draft" panel from 1.0.2/1.0.3 is gone.
- The activation page's LMS Labs host is fixed to lms-labs.com; it can no longer be pointed elsewhere from config.php.

### Upgrade

- Script drafts and picture jobs from 1.0.2/1.0.3 move into the stored requests, keeping their keys:
  - Completed drafts become scenes.
  - Unresolved drafts and pictures stay checkable with the same key and body.
  - A picture that was being saved when interrupted is marked lost.
- The old `aisoftskills_draft` and `aisoftskills_imagejob` tables are then removed.

### Fixed

- Code checker: constant visibility and comment style in the 1.0.3 activation code.
- CI: template heading levels.

Version 2026092900. Scene drafts (3 credits) and pictures (5 credits) are unchanged; there is no Moodle-side debit.

## [v1.0.3] - 2026-09-28

### Added

- Administrator-only activation page with free access verification, live release/credit-price review and explicit POST confirmation before one-time site unlock.
- Durable pending marker and verification-first recovery for uncertain unlock responses; show unlimited/low balances, Marketplace restorations and server conflict messages without claiming a new debit for prior purchases.
- Activation uses the existing complete LMS Labs credential pair. Scene drafting (3 credits), image generation (5 credits), request keys, pending/410 handling and teacher review are unchanged.

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
