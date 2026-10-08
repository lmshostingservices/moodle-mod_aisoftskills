# AI Soft Skills (mod_aisoftskills)

A Moodle activity for practising workplace soft skills. Each scene is one picture of a realistic moment in the chosen industry, with two possible responses. For example, a supervisor whose team is behind on its target can ask "Is there anything I can get you to help you achieve your goals faster?" or shout "Hurry up!".

- **Better response:** confetti, a sound and a popup showing the consequence. A workplace indicator gauge (morale, motivation, productivity, trust, wellbeing, engagement, teamwork, customer satisfaction, quality or safety) animates upwards.
- **Poorer response:** the gauge drops and the popup explains why. The learner can then try the other response. Only the first choice in each scene is marked.
- **Next scene:** moves to a new picture with a new pair of responses. At the end the learner sees a score, a rating and the final value of every indicator.

## Requirements

- Moodle 4.4 to 5.3 (`$plugin->requires = 2024042200`, `supported = [404, 503]`).
- PHP 8.1 or later, as your Moodle version requires.

## Install

1. Upload `mod_aisoftskills_v1.0.1.zip` in *Site administration > Plugins > Install plugins*, or unzip it into `mod/aisoftskills` (`public/mod/aisoftskills` on Moodle 5.1 and later).
2. Complete the upgrade.

## Teachers

1. **Add the activity.** Pick the industry (16 industries, or your own), the career level (worker, supervisor, manager or leader), the language of the scenes (20 languages, including right-to-left Arabic), the picture style, retries, shuffling, sounds, attempts and grading.
2. **Activate the plugin** in the AI Soft Skills settings: "Check access", then "Unlock…" (50 LMS Labs credits, or free when LMS Labs has a record of a Moodle Marketplace purchase). Nothing can be set up or played until the site is unlocked.
3. **Set up the lesson** ("Set up the lesson" in the activity menu). Eight steps, moved through with Back and Next only. Next opens when the step is done:
   1. Workplace.
   2. Level.
   3. Skills.
   4. Language.
   5. **Create the scenes**, in one of two ways:
      - "Use your own text": paste or type a workplace situation (30 to 300 words), and LMS Labs AI turns it into a scene: title, setting, lead-in conversation and a teaching note. 3 credits, confirmed first.
      - "Use an AI assistant": for more control and complex scenarios, copy the prompt into ChatGPT or another assistant, paste the reply back in, preview it and create complete scenes. 3 credits per scene, the same as LMS Labs AI, confirmed first.
   6. **Pictures:**
      - "Create missing pictures" asks LMS Labs AI for a picture of every scene that has none (5 credits per delivered picture, confirmed first).
      - Each scene can also get its own AI picture or an uploaded one.
   7. **Check the scenes:** every scene needs two responses, exactly one marked as the better one. "Add the two responses" opens the scene editor.
   8. **Finish:** see what is ready, preview as a learner, and go back to the course.

   "Set up the lesson" always reopens at the first step that is not done yet. A better response always raises its indicator; the poorer one never does.
4. **Scenes are played** only once they have a picture and valid responses.
5. **Edit any scene** with its title, skill, context, who the learner is, the question, and both responses: their text, indicator, change, consequence and "why".
6. **Reports:** learners (attempts, best score, grade), scenes (how often the better response was chosen first, and average tries) and attempts (with downloads and deletion).

**Grading:** each attempt scores the percentage of scenes where the first choice was the better response. The gradebook uses the highest, average, first or last attempt.

**Completion:** "Play every scene" (finish an attempt).

## Languages

Scene content is written in the language chosen for the activity and shown with the right text direction. The interface uses each learner's Moodle language (English strings are included).

## AI and LMS Labs

- **AI scene drafts:** "Draft a scene with LMS Labs AI (3 credits)" in the lesson builder.
  - It calls the dedicated LMS Labs route `POST https://lms-labs.com/api/moodle/ai-softskills/scenes/draft` with the teacher's own text (at most 2,000 characters; if left empty, a brief built from the builder choices), plus the level and workplace from the builder choices.
  - The delivered title, setting, lead-in conversation and teaching note become a new scene at once. The teacher writes the two responses and adds a picture.
  - Each delivered draft costs 3 LMS Labs credits, charged by LMS Labs only. Failed drafts are not charged.
  - Site administrators can switch it off ("AI scene drafts").
- **Stored requests:** every draft or picture request is saved in Moodle, with its own Idempotency-Key and exact body, before it is sent. "Check again" asks LMS Labs about the same request and can never be charged twice. Requests that are still in progress, unconfirmed, conflicting, expired or lost stay listed until dismissed. Only requests LMS Labs reports as in progress are checked again automatically (after `Retry-After`, a limited number of times); nothing else is resent. A delivered draft can be replayed for 24 hours; a picture cannot (LMS Labs keeps no picture), so an undelivered picture is shown as lost, with the reference, and never requested again automatically.
- **AI scene pictures:** "Create the picture with AI (5 credits)" on each scene in the scene list.
  - It calls the dedicated LMS Labs route `POST https://lms-labs.com/api/moodle/ai-softskills/images` with the scene's English picture description (2,000 characters or fewer) and the activity's picture style.
  - LMS Labs returns a 1600×1000 PNG, which Moodle stores as the scene picture.
  - Each successful picture costs 5 LMS Labs credits. Failed requests are not charged.
  - Each click is one stored request with its own idempotency key. Nothing is ever retried automatically.
  - Errors say what happened and give the LMS Labs reference: not enough credits (with the balance), not entitled, service not available, charge not confirmed.
  - Site administrators can switch it off ("AI scene pictures"). Only teachers with `mod/aisoftskills:useai` see the button.
- **Balance check:** read-only (`GET https://lms-labs.com/api/credits?siteId=…`, key in the `X-API-Key` header), for information only.
- **Credentials:** LMS Labs Central Config (`local_aiconfig`) comes first. When it is installed and has both a Site ID and an API key, the plugin uses them automatically and nothing needs entering here.
  - The plugin's own "standalone" Site ID and API key are used only when Central Config does not have both.
  - A pair is used only when complete, and central and standalone values are never mixed.
  - The settings page shows which pair is in use.
- The API key stays on the server. It is never sent to the browser, put in URLs or logged.
- **Scene drafting without LMS Labs:** the copy-and-paste prompt for any AI assistant is still available.
- No speech or audio features are included.

## Activation

Administrators open *Site administration > Plugins > Activity modules > AI Soft Skills activation*. The page shows:

- where the LMS Labs credentials come from (Central Config or this plugin), with a link to that setting;
- access to this plugin: Not checked, Locked, Unlocked or Unable to verify;
- the LMS Labs balance, including unlimited;
- the live one-time price.

Having a Site ID and API key is not the same as being unlocked. "Check access" is free. "Unlock" first shows the live price and release and asks for confirmation; only then does it send the purchase, and only with that exact price and release SHA-256. After an uncertain result, another unlock is blocked until a free check gives a definite answer. Nothing is bought when the page loads. Every action is a POST with sesskey and needs `moodle/site:config`.

## Privacy

The plugin stores attempts, choices, teachers' AI request log lines and stored LMS Labs requests, and uses the gradebook. All of these are covered by the Privacy API (export and deletion). When a teacher drafts a scene or creates a picture with LMS Labs AI, only teacher-written text (the brief, audience and workplace, or the picture description) and the site's LMS Labs credentials go to LMS Labs, which creates the text and pictures with its own AI providers. No learner data leaves Moodle.

## Licence

GNU GPL v3 or later. © 2026 LMS Hosting Services.
