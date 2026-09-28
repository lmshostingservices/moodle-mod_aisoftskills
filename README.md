# AI Soft Skills (mod_aisoftskills)

A Moodle activity for practising workplace soft skills. Each scene is one picture of a realistic moment in the chosen industry, with two possible responses. For example, a supervisor whose team is behind on its target can ask "Is there anything I can get you to help you achieve your goals faster?" or shout "Hurry up!".

- **Better response:** confetti, a sound and a popup showing the consequence. A workplace indicator gauge (morale, motivation, productivity, trust, wellbeing, engagement, teamwork, customer satisfaction, quality or safety) animates upwards.
- **Poorer response:** the gauge drops and the popup explains why. The learner can then try the other response. Only the first choice in each scene is marked.
- **Next scene:** moves to a new picture with a new pair of responses. At the end the learner sees a score, a rating and the final value of every indicator.

## Requirements

- Moodle 4.4 to 5.3 (`$plugin->requires = 2024042200`, `supported = [404, 503]`).
- PHP 8.1 or later, as your Moodle version requires.

## Install

1. Upload `mod_aisoftskills_v1.0.0.zip` in *Site administration > Plugins > Install plugins*, or unzip it into `mod/aisoftskills` (`public/mod/aisoftskills` on Moodle 5.1 and later).
2. Complete the upgrade.

## Teachers

1. **Add the activity.** Pick the industry (16 industries, or your own), the career level (worker, supervisor, manager or leader), the language of the scenes (20 languages, including right-to-left Arabic), the picture style, retries, shuffling, sounds, attempts and grading.
2. **Build the scenes.** The builder asks for the workplace, the level, the soft skills and the language, then writes a prompt for any AI assistant (ChatGPT, Claude, Gemini, Copilot). Paste the reply back in, preview it and create the scenes. A scene is kept only if it has exactly two responses and exactly one of them is marked as better. A better response always raises its indicator; the poorer one never does.
3. **Add a picture to each scene.** Each scene has a ready-made picture prompt set in the chosen industry. Paste it into any image AI, then upload the picture (images or a ZIP). A scene is played only once it has a picture and valid responses.
4. **Edit any scene** with its title, skill, context, who the learner is, the question, and both responses: their text, indicator, change, consequence and "why".
5. **Reports:** learners (attempts, best score, grade), scenes (how often the better response was chosen first, and average tries) and attempts (with downloads and deletion).

**Grading:** each attempt scores the percentage of scenes where the first choice was the better response. The gradebook uses the highest, average, first or last attempt.

**Completion:** "Play every scene" (finish an attempt).

## Languages

Scene content is written in the language chosen for the activity and shown with the right text direction. The interface uses each learner's Moodle language (English strings are included).

## AI and LMS Labs

- **AI scene pictures:** "Create the picture with AI (5 credits)" on each scene in the scene list.
  - It calls the dedicated LMS Labs route `POST https://lms-labs.com/api/moodle/ai-softskills/images` with the scene's picture prompt (English, 2,000 characters or fewer) and the activity's picture style.
  - LMS Labs returns a 1600×1000 PNG, which Moodle stores as the scene picture.
  - Each successful picture costs 5 LMS Labs credits. Failed requests are not charged.
  - Each click is one request with its own idempotency key. Nothing is ever retried automatically.
  - Errors say what happened and give the LMS Labs reference: not enough credits (with the balance), not entitled, service not available, charge not confirmed.
  - Site administrators can switch it off ("AI scene pictures"). Only teachers with `mod/aisoftskills:useai` see the button.
- **Balance check:** read-only (`GET https://lms-labs.com/api/credits?siteId=…`, key in the `X-API-Key` header), for information only.
- **Credentials:** LMS Labs Central Config (`local_aiconfig`) comes first. When it is installed and has both a Site ID and an API key, the plugin uses them automatically and nothing needs entering here.
  - The plugin's own "standalone" Site ID and API key are used only when Central Config does not have both.
  - A pair is used only when complete, and central and standalone values are never mixed.
  - The settings page shows which pair is in use.
- The API key stays on the server. It is never sent to the browser, put in URLs or logged.
- **Scene drafting:** copy and paste with any AI assistant, for now. A paid LMS Labs drafting route is proposed but not yet approved.
- No speech or audio features are included.

## Privacy

The plugin stores attempts, choices and teachers' AI request log lines, and uses the gradebook. All of these are covered by the Privacy API (export and deletion). When a teacher creates a picture with AI, only the scene's picture description and the site's LMS Labs credentials go to LMS Labs, which uses OpenAI for the picture. No learner data leaves Moodle.

## Licence

GNU GPL v3 or later. © 2026 LMS Hosting Services.
