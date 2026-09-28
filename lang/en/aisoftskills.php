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

/**
 * English strings for mod_aisoftskills.
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addscenes'] = 'Add scenes';
$string['addscenes_help'] = 'Upload pictures (one scene per picture, a ZIP works too), or give a title to add a scene and upload its picture later.';
$string['aibadimage'] = 'The AI service did not return a usable picture.';
$string['aierror_body_too_large'] = 'The picture request is too large. Shorten the scene\'s picture description. (LMS Labs reference: {$a->requestid})';
$string['aierror_deadline_exceeded'] = 'The picture took too long and was stopped. Check your LMS Labs usage before trying again. (LMS Labs reference: {$a->requestid})';
$string['aierror_failed'] = 'LMS Labs could not create the picture. You have not been charged. (LMS Labs reference: {$a->requestid})';
$string['aierror_idempotency_conflict'] = 'LMS Labs rejected a duplicate request. Try again. (LMS Labs reference: {$a->requestid})';
$string['aierror_image_failed'] = 'The picture could not be created. You have not been charged. (LMS Labs reference: {$a->requestid})';
$string['aierror_image_unavailable'] = 'LMS Labs pictures are not available right now. You have not been charged. (LMS Labs reference: {$a->requestid})';
$string['aierror_insufficient_credits'] = 'Not enough LMS Labs credits: a picture needs {$a->credits} and {$a->balance} are left. Top up at lms-labs.com. (LMS Labs reference: {$a->requestid})';
$string['aierror_invalid_credentials'] = 'LMS Labs did not accept this site\'s Site ID and API key. Check them in Central Config. (LMS Labs reference: {$a->requestid})';
$string['aierror_invalid_idempotency_key'] = 'LMS Labs could not read the request. You have not been charged. (LMS Labs reference: {$a->requestid})';
$string['aierror_invalid_input'] = 'LMS Labs could not use this picture description. Check it and try again. You have not been charged. (LMS Labs reference: {$a->requestid})';
$string['aierror_invalid_json'] = 'LMS Labs could not read the request. You have not been charged. (LMS Labs reference: {$a->requestid})';
$string['aierror_network'] = 'LMS Labs could not be reached, or did not answer in time. The picture may still have been created and charged: check your LMS Labs usage before trying again. (LMS Labs reference: {$a->requestid})';
$string['aierror_no_entitlement'] = 'This site does not have AI Soft Skills enabled with LMS Labs. (LMS Labs reference: {$a->requestid})';
$string['aierror_not_live'] = 'The LMS Labs picture service is not available yet. (LMS Labs reference: {$a->requestid})';
$string['aierror_pending'] = 'LMS Labs is still working on an earlier request for this picture. Wait a minute before trying again. (LMS Labs reference: {$a->requestid})';
$string['aierror_prompt_too_long'] = 'The picture description is too long. Shorten the scene\'s picture description and try again. (LMS Labs reference: {$a->requestid})';
$string['aierror_provider_failed'] = 'The picture service could not create this picture. You have not been charged. (LMS Labs reference: {$a->requestid})';
$string['aierror_provider_rate_limited'] = 'The picture service is busy. Wait a moment and try again. You have not been charged. (LMS Labs reference: {$a->requestid})';
$string['aierror_provider_unavailable'] = 'The picture service is not available right now. You have not been charged. (LMS Labs reference: {$a->requestid})';
$string['aierror_rate_limited'] = 'Too many pictures requested at once. Wait a moment and try again. You have not been charged. (LMS Labs reference: {$a->requestid})';
$string['aierror_result_not_retained'] = 'This request was already completed. Try again to create a new picture. (LMS Labs reference: {$a->requestid})';
$string['aierror_settlement_unconfirmed'] = 'LMS Labs could not confirm the charge for this picture. Check your LMS Labs usage before trying again. (LMS Labs reference: {$a->requestid})';
$string['aierror_unexpected_fields'] = 'LMS Labs could not read the request. You have not been charged. (LMS Labs reference: {$a->requestid})';
$string['aierror_unusable_image'] = 'LMS Labs did not return a usable picture. You have not been charged. (LMS Labs reference: {$a->requestid})';
$string['aiimages'] = 'AI scene pictures';
$string['aiimages_desc'] = 'Let teachers create a scene picture with AI from its picture prompt. Each successful picture costs 5 LMS Labs credits; failed requests are not charged. Teachers need the "Use AI to create scene pictures" capability.';
$string['ainotavailable'] = 'AI pictures are switched off, or this site\'s LMS Labs Site ID and API key are not set.';
$string['airate'] = 'AI requests per teacher per hour';
$string['airate_desc'] = 'Most AI picture requests one teacher can make per hour.';
$string['airatelimit'] = 'You have reached the hourly limit for AI requests. Try again later.';
$string['aisoftskills:addinstance'] = 'Add a new AI Soft Skills activity';
$string['aisoftskills:attempt'] = 'Play the scenes';
$string['aisoftskills:manage'] = 'Build and edit scenes';
$string['aisoftskills:useai'] = 'Use AI to create scene pictures';
$string['aisoftskills:view'] = 'View AI Soft Skills';
$string['aisoftskills:viewreports'] = 'View reports';
$string['allowretry'] = 'Try again after a poorer choice';
$string['allowretry_desc'] = 'Learners see what went wrong, then choose again. Only the first choice is marked.';
$string['alreadyanswered'] = 'You have already found the better response in this scene.';
$string['alreadytried'] = 'You have already tried that response.';
$string['announce_best'] = '{$a->name} rises to {$a->value}.';
$string['announce_poor'] = '{$a->name} falls to {$a->value}.';
$string['attempt'] = 'Attempt';
$string['attemptfinished'] = 'This attempt has already finished.';
$string['attemptsdeleted'] = '{$a} attempts deleted.';
$string['attemptsleft'] = 'Attempts left: {$a}';
$string['attemptsused'] = 'Attempts: {$a->used} of {$a->max}';
$string['backtohome'] = 'Back to the activity page';
$string['backtoscenes'] = 'Back to scenes';
$string['balance_credits'] = 'LMS Labs credits: {$a} (for information; the balance can change)';
$string['balance_unlimited'] = 'LMS Labs credits: unlimited';
$string['bestfirstchoices'] = 'Better first choice in {$a->best} of {$a->total} scenes.';
$string['bestresponse'] = 'Better response';
$string['bestresponse_help'] = 'The response that shows the skill well. Choosing it first counts towards the grade.';
$string['bestscore'] = 'Best score';
$string['betterresponse'] = 'A better response';
$string['build_ai_off'] = 'The scenes are drafted with an AI assistant of your choice; this plugin does not send them to an AI service. Add a picture to each scene afterwards.';
$string['build_manual_1'] = 'Copy the prompt below.';
$string['build_manual_2'] = 'Paste it into ChatGPT, Claude, Gemini or Copilot.';
$string['build_manual_3'] = 'Paste the reply here and preview it.';
$string['build_manual_title'] = 'Use any AI assistant';
$string['build_paste'] = 'Paste the AI\'s reply';
$string['build_preview'] = 'Preview';
$string['builder_change'] = 'Change choices';
$string['builder_existing'] = 'This activity already has {$a} scenes. New scenes are added after them.';
$string['builder_save'] = 'Save and build the scenes';
$string['builder_skip'] = 'Skip to building';
$string['builder_steps'] = 'Scene builder steps';
$string['buildlesson'] = 'Build scenes';
$string['careerladder'] = 'Career levels';
$string['col_answers'] = 'Answers';
$string['col_attempt'] = 'Attempt';
$string['col_attempts'] = 'Attempts';
$string['col_bestfirst'] = 'Better first choice';
$string['col_bestscore'] = 'Best score';
$string['col_duration'] = 'Time taken';
$string['col_grade'] = 'Grade';
$string['col_lastfinish'] = 'Last finished';
$string['col_scene'] = 'Scene';
$string['col_score'] = 'Score';
$string['col_skill'] = 'Skill';
$string['col_started'] = 'Started';
$string['col_state'] = 'State';
$string['col_tries'] = 'Average tries';
$string['completionallscenes'] = 'Play every scene';
$string['completionallscenes_desc'] = 'Finish an attempt with every scene played';
$string['completiondetail:allscenes'] = 'Play every scene';
$string['confirmdeleteattempts'] = 'Delete the selected attempts? Grades and completion are recalculated.';
$string['confirmdeletescene'] = 'Delete the scene "{$a}", its picture, its responses and learners\' choices in it?';
$string['contentlang'] = 'Language of the scenes';
$string['contentlang_help'] = 'The language the scenes, responses and consequences are written in. Buttons and messages follow each learner\'s Moodle language.';
$string['copied'] = 'Copied';
$string['copyimageprompt'] = 'Copy picture prompt';
$string['copyprompt'] = 'Copy prompt';
$string['creating'] = 'Creating scenes…';
$string['credentials_central'] = 'In use: the Site ID and API key from LMS Labs Central Config. The standalone fields below are ignored while Central Config has both.';
$string['credentials_centralincomplete'] = 'Not configured: LMS Labs Central Config is missing its Site ID or API key, and the standalone fields below are not both filled in. Complete Central Config (recommended) or enter both below. LMS Labs is not contacted until then.';
$string['credentials_local'] = 'In use: the standalone Site ID and API key below (LMS Labs Central Config is not installed or does not have both).';
$string['credentials_none'] = 'Not configured: enter this site\'s LMS Labs Site ID and API key in LMS Labs Central Config (recommended), or both in the standalone fields below. LMS Labs is not contacted until then.';
$string['customindustry'] = 'Your industry';
$string['customindustry_placeholder'] = 'For example: veterinary clinic';
$string['deleteselected'] = 'Delete selected';
$string['deltabest'] = 'The better response must raise the indicator.';
$string['deltaother'] = 'The other response can\'t raise the indicator.';
$string['deltarange'] = 'Use a number from -{$a} to {$a}.';
$string['editscene'] = 'Edit scene';
$string['errorsceneneeds'] = 'Upload at least one picture or give the scene a title.';
$string['eventattemptfinished'] = 'Attempt finished';
$string['generateimage'] = 'Create the picture with AI ({$a} credits)';
$string['generating'] = 'Creating… this can take a minute';
$string['gradeaverage'] = 'Average of attempts';
$string['gradefirst'] = 'First attempt';
$string['gradehighest'] = 'Highest attempt';
$string['gradelast'] = 'Last attempt';
$string['grademethod'] = 'Grading method';
$string['grademethod_help'] = 'How the grade is calculated when a learner makes more than one attempt. Each attempt\'s score is the share of scenes where the first choice was the better response.';
$string['headline_best'] = 'Great choice!';
$string['headline_bestretry'] = 'That works better.';
$string['headline_poor'] = 'That didn\'t go well.';
$string['herotitle'] = 'You are the {$a}';
$string['imagecreated'] = 'Picture created. {$a} LMS Labs credits used.';
$string['imagecreatedbalance'] = 'Picture created. {$a->charged} LMS Labs credits used; {$a->balance} left.';
$string['imagehelp'] = 'Picture';
$string['imageprompt_full'] = 'Create one {$a->style} for a workplace soft-skills lesson.

Scene: {$a->description}

Industry: {$a->industry}. The workplace, uniforms, equipment and people should look typical of this industry.

Rules:
- Landscape, 16:10 (for example 1600 × 1000 pixels).
- Show the people involved clearly; faces, gestures and body language make the moment obvious.
- The moment is shown just before anyone responds, so the picture suits both possible responses.
- No text, letters, numbers, captions, signs, logos or speech bubbles.';
$string['imageprompt_help'] = 'Copy this prompt into any AI that makes pictures (ChatGPT, Gemini, Copilot), then upload the picture.';
$string['imagereplaced'] = 'Picture saved.';
$string['imagestyle'] = 'Picture style';
$string['imagestyle_illustration'] = 'bright, friendly flat illustration';
$string['imagestyle_illustration_name'] = 'Illustration';
$string['imagestyle_photo'] = 'realistic photograph';
$string['imagestyle_photo_name'] = 'Photo';
$string['imagetip_moment'] = 'One moment per picture, just before anyone responds, so it suits both responses.';
$string['imagetip_notext'] = 'No text, signs or speech bubbles: the responses come from the activity.';
$string['imagetip_people'] = 'Show the people involved clearly; their faces and body language should tell the story.';
$string['imagetip_size'] = 'Landscape, about 1600 × 1000 pixels (16:10).';
$string['imagetip_workplace'] = 'Set it in a real {$a} workplace, with the right uniforms and equipment.';
$string['imagetips_title'] = 'Good pictures for soft-skills scenes';
$string['industry'] = 'Industry';
$string['industry_agedcare'] = 'Aged and disability care';
$string['industry_agriculture'] = 'Agriculture';
$string['industry_callcentre'] = 'Contact centre';
$string['industry_construction'] = 'Construction';
$string['industry_custom'] = 'Another industry';
$string['industry_education'] = 'Education';
$string['industry_finance'] = 'Banking and finance';
$string['industry_government'] = 'Government and public service';
$string['industry_healthcare'] = 'Healthcare';
$string['industry_help'] = 'Where the scenes take place. The people, places and problems in every scene come from this industry.';
$string['industry_hospitality'] = 'Hospitality';
$string['industry_logistics'] = 'Transport and logistics';
$string['industry_manufacturing'] = 'Manufacturing';
$string['industry_mining'] = 'Mining and resources';
$string['industry_office'] = 'Office and administration';
$string['industry_retail'] = 'Retail';
$string['industry_technology'] = 'Technology';
$string['industry_trades'] = 'Trades and services';
$string['invalidoption'] = 'That response doesn\'t belong to this scene.';
$string['kpi_customersatisfaction'] = 'Customer satisfaction';
$string['kpi_engagement'] = 'Engagement';
$string['kpi_morale'] = 'Morale';
$string['kpi_motivation'] = 'Motivation';
$string['kpi_productivity'] = 'Productivity';
$string['kpi_quality'] = 'Quality';
$string['kpi_safety'] = 'Safety';
$string['kpi_teamwork'] = 'Teamwork';
$string['kpi_trust'] = 'Trust';
$string['kpi_wellbeing'] = 'Wellbeing';
$string['lang_ar'] = 'Arabic';
$string['lang_de'] = 'German';
$string['lang_en'] = 'English';
$string['lang_es'] = 'Spanish';
$string['lang_fil'] = 'Filipino';
$string['lang_fr'] = 'French';
$string['lang_hi'] = 'Hindi';
$string['lang_id'] = 'Indonesian';
$string['lang_it'] = 'Italian';
$string['lang_ja'] = 'Japanese';
$string['lang_ko'] = 'Korean';
$string['lang_ms'] = 'Malay';
$string['lang_nl'] = 'Dutch';
$string['lang_pl'] = 'Polish';
$string['lang_pt'] = 'Portuguese';
$string['lang_ru'] = 'Russian';
$string['lang_th'] = 'Thai';
$string['lang_tr'] = 'Turkish';
$string['lang_vi'] = 'Vietnamese';
$string['lang_zh'] = 'Chinese (Simplified)';
$string['lessonempty'] = 'The draft has no usable scenes. Each scene needs a title and two responses, one of them marked as the better one.';
$string['lessoninvalid'] = 'That doesn\'t look like a set of scenes. Paste the whole reply, including the {"scenes": …} part.';
$string['lessonprompt'] = 'You are an expert workplace trainer. Create picture-based soft-skills scenes.

Industry: {$a->industry}
Career level of the learner: {$a->level}. {$a->levelguide}
Language: write every title, context, question, response, consequence and reason in {$a->language}. Use natural, everyday {$a->language} as people speak it at work.

Soft skills to practise:
{$a->skills}

Create {$a->scenes} scenes, shared out between the skills. A scene is one realistic moment in this industry that a single picture can show, for example "A shift is behind on its target an hour before closing". The learner plays the {$a->level} and must choose between exactly two responses:
- one clearly better response that shows the skill well (best: true);
- one poorer but realistic response that people really do use under pressure (best: false), for example shouting "Hurry up!" instead of asking "Is there anything I can get you to help you reach your goals faster?".
Do not make the poorer response silly or obviously rude every time; it should be tempting.

For each response give:
- text: exactly what the person says or does
- best: true or false (exactly one true per scene)
- kpi: the workplace indicator the choice moves most, one of: {$a->kpis}
- kpidelta: how much it moves, a whole number from 5 to {$a->maxdelta} for the better response and from -{$a->maxdelta} to -5 for the poorer one
- consequence: two or three sentences on what happens next because of this choice
- reason: one sentence on why the response works or does not

For every scene give:
- skill: the soft skill practised
- title: a short title for the moment
- context: one or two sentences describing what is happening
- speaker: who the learner is in this moment, for example "You, the shift supervisor"
- question: what the learner must decide, for example "What do you say to the team?"
- imageprompt: an English description of one {$a->style} of this moment in a {$a->industry} workplace, showing the people involved, their body language and the setting. No text, letters, captions, signs or speech bubbles.

Reply with JSON only, in exactly this shape:
{"scenes":[{"skill":"","title":"","context":"","speaker":"","question":"","imageprompt":"","options":[{"text":"","best":true,"kpi":"","kpidelta":20,"consequence":"","reason":""},{"text":"","best":false,"kpi":"","kpidelta":-20,"consequence":"","reason":""}]}]}';
$string['level'] = 'Career level';
$string['level_help'] = 'Who the learner plays in the scenes. Each level has its own kind of situations: a worker deals with colleagues and customers, a leader with strategy, culture and big changes.';
$string['level_leader'] = 'Leader';
$string['level_manager'] = 'Manager';
$string['level_supervisor'] = 'Supervisor';
$string['level_worker'] = 'Worker';
$string['leveldesc_leader'] = 'Setting direction, culture and change.';
$string['leveldesc_manager'] = 'Managing people, results and resources.';
$string['leveldesc_supervisor'] = 'Running a shift or a small team day to day.';
$string['leveldesc_worker'] = 'Doing the job well with colleagues and customers.';
$string['levelguide_leader'] = 'The learner is a senior leader. Scenes involve vision, culture, major change, crises, stakeholders, trust across the organisation and developing other leaders.';
$string['levelguide_manager'] = 'The learner manages a team or department. Scenes involve performance conversations, targets, delegation, hiring, budgets, conflict between staff and difficult feedback.';
$string['levelguide_supervisor'] = 'The learner supervises a shift or small team. Scenes involve giving instructions, rosters, motivating people on the day, first-line conflict, safety and passing problems up.';
$string['levelguide_worker'] = 'The learner is a front-line worker. Scenes involve colleagues, customers, a supervisor, safety, deadlines and everyday problems.';
$string['levelline_beginning'] = 'These moments are tricky. Read why each response works, then try again.';
$string['levelline_developing'] = 'You are building the habits of a good {$a}. Try again to lift your score.';
$string['levelline_excellent'] = 'You handled these moments like a natural {$a}.';
$string['levelline_strong'] = 'Most of your first choices were what a strong {$a} would do.';
$string['lmslabsapikey'] = 'Standalone LMS Labs API key';
$string['lmslabsapikey_desc'] = 'Used only with the standalone site ID above. Stays on the server and is sent only to lms-labs.com.';
$string['lmslabscredentialsmissing'] = 'Configure this site\'s LMS Labs Site ID and API key in Central Config, or provide both in this plugin\'s settings.';
$string['lmslabssiteid'] = 'Standalone LMS Labs site ID';
$string['lmslabssiteid_desc'] = 'Leave empty when LMS Labs Central Config (local_aiconfig) is installed: its Site ID and API key are used automatically. Used only when Central Config does not have both, and only together with the standalone API key.';
$string['managescenes'] = 'Scenes';
$string['maxattempts'] = 'Attempts allowed';
$string['modulename'] = 'AI Soft Skills';
$string['modulename_help'] = 'AI Soft Skills lets learners practise workplace soft skills through picture scenes. Each scene shows a real moment in the chosen industry, such as a leader encouraging a team that is behind on a target, and offers two possible responses. The better response brings confetti and a rising workplace indicator such as morale or productivity; the poorer one shows what goes wrong. Only the first choice in each scene is marked.

Teachers pick the industry, the career level (worker, supervisor, manager or leader), the skills and the language of the scenes, then draft the scenes with any AI assistant and add a picture to each one.';
$string['modulename_link'] = 'mod/aisoftskills/view';
$string['modulenameplural'] = 'AI Soft Skills activities';
$string['moveleft'] = 'Move earlier';
$string['moveright'] = 'Move later';
$string['newscene'] = 'New scene';
$string['next'] = 'Next';
$string['nextscene'] = 'Next scene';
$string['noattempts'] = 'No attempts yet.';
$string['noimagefound'] = 'No picture was found in the upload.';
$string['noimageyet'] = 'No picture yet';
$string['nolearners'] = 'No learners to show.';
$string['nomoreattempts'] = 'No attempts left.';
$string['noscenes'] = 'This activity has no scenes ready yet.';
$string['noscenesyet'] = 'No scenes yet. Build the scenes, or add scenes below.';
$string['notready_manager'] = 'Add scenes that each have a picture and two responses, one of them marked as the better one.';
$string['notready_student'] = 'Your teacher is still preparing this activity.';
$string['notyourattempt'] = 'This attempt belongs to someone else.';
$string['playsettings'] = 'Playing';
$string['pluginadministration'] = 'AI Soft Skills administration';
$string['pluginname'] = 'AI Soft Skills';
$string['previewstudent'] = 'Preview as learner';
$string['previous'] = 'Back';
$string['privacy:metadata:ailog'] = 'Teachers\' AI picture requests, kept for rate limits.';
$string['privacy:metadata:ailog:action'] = 'What was requested.';
$string['privacy:metadata:ailog:status'] = 'Whether the request worked.';
$string['privacy:metadata:ailog:timecreated'] = 'When the request was made.';
$string['privacy:metadata:ailog:userid'] = 'The teacher.';
$string['privacy:metadata:attempt'] = 'Each time a learner plays the scenes.';
$string['privacy:metadata:attempt:attempt'] = 'The attempt number.';
$string['privacy:metadata:attempt:kpis'] = 'The workplace indicator values reached.';
$string['privacy:metadata:attempt:sceneorder'] = 'The order the scenes and responses were shown in.';
$string['privacy:metadata:attempt:score'] = 'The share of scenes where the first choice was the better response.';
$string['privacy:metadata:attempt:state'] = 'Whether the attempt is in progress or finished.';
$string['privacy:metadata:attempt:timefinish'] = 'When the attempt finished.';
$string['privacy:metadata:attempt:timestart'] = 'When the attempt started.';
$string['privacy:metadata:attempt:userid'] = 'The learner.';
$string['privacy:metadata:choice'] = 'The learner\'s choice in each scene.';
$string['privacy:metadata:choice:attemptid'] = 'The attempt.';
$string['privacy:metadata:choice:best'] = 'Whether the first choice was the better response.';
$string['privacy:metadata:choice:optionid'] = 'The first response chosen.';
$string['privacy:metadata:choice:resolved'] = 'Whether the better response was found.';
$string['privacy:metadata:choice:sceneid'] = 'The scene.';
$string['privacy:metadata:choice:timecreated'] = 'When the first choice was made.';
$string['privacy:metadata:choice:tries'] = 'How many responses were tried.';
$string['privacy:metadata:core_grades'] = 'Grades are stored in the gradebook.';
$string['privacy:metadata:lmslabs'] = 'When a teacher creates a scene picture with AI, the scene\'s English picture description and the site\'s LMS Labs credentials are sent to LMS Labs, which creates the picture with OpenAI. No learner data is sent.';
$string['privacy:metadata:lmslabs:prompt'] = 'The teacher-written picture description of the scene.';
$string['privacy:metadata:lmslabs:siteid'] = 'The site\'s LMS Labs Site ID.';
$string['progress'] = 'Progress';
$string['prompt_anyskills'] = '- Any soft skills that matter most at this level in this industry';
$string['q_custom'] = 'Your own skill';
$string['q_custom_add'] = 'Add another';
$string['q_custom_help'] = 'Anything your learners need, for example "Handling an angry patient\'s family".';
$string['q_custom_placeholder'] = 'Describe a skill';
$string['q_industry'] = 'Which industry do your learners work in?';
$string['q_industry_help'] = 'Every scene is set in this industry.';
$string['q_language'] = 'Which language should the scenes be written in?';
$string['q_language_help'] = 'Every scene, response and consequence is written in this language.';
$string['q_level'] = 'Which career level are they practising?';
$string['q_level_help'] = 'The learner plays this role in every scene.';
$string['q_skills'] = 'Which soft skills should the scenes practise?';
$string['q_skills_help'] = 'Pick one or more. The scenes are shared out between them.';
$string['q_skills_none'] = 'Choose at least one skill, or describe your own.';
$string['rating_beginning'] = 'Keep practising';
$string['rating_developing'] = 'Good start';
$string['rating_excellent'] = 'Outstanding!';
$string['rating_strong'] = 'Well done!';
$string['regradeall'] = 'Recalculate grades';
$string['regraded'] = 'Grades recalculated.';
$string['replaceimage'] = 'Save picture';
$string['report_attempts'] = 'Attempts';
$string['report_learners'] = 'Learners';
$string['report_learners_help'] = 'Each learner\'s finished attempts, best score and grade.';
$string['report_scenes'] = 'Scenes';
$string['report_scenes_help'] = 'How often learners chose the better response first in each scene. Scenes where fewer than half did are highlighted.';
$string['reports'] = 'Reports';
$string['resetattempts'] = 'Delete all attempts';
$string['responseconsequence'] = 'Consequence';
$string['responseconsequence_help'] = 'What happens next because of this choice. Shown in the popup after the learner chooses.';
$string['responsedelta'] = 'Change';
$string['responsedelta_help'] = 'How far the indicator moves: positive for the better response, zero or negative for the other (at most 50 either way). Every attempt starts each indicator at 50.';
$string['responsekpi'] = 'Indicator it moves';
$string['responsekpi_help'] = 'The workplace indicator shown on the gauge after this choice.';
$string['responsereason'] = 'Why';
$string['responses'] = 'Your two possible responses';
$string['responsetext'] = 'What the person says or does';
$string['responsex'] = 'Response {$a}';
$string['resume'] = 'Carry on';
$string['review_better'] = 'Better response';
$string['review_create'] = 'Create these scenes';
$string['review_help'] = 'Untick any scene you don\'t want. You can edit every scene afterwards and add its picture in the scene list.';
$string['review_title'] = 'Draft: {$a} scenes';
$string['scene'] = 'Scene';
$string['scenecontext'] = 'What is happening';
$string['scenecontext_help'] = 'One or two sentences shown above the picture, in the language of the scenes.';
$string['scenecount'] = 'Number of scenes';
$string['scenecounter'] = 'Scene {$a->number} of {$a->total}';
$string['scenedeleted'] = 'Scene deleted.';
$string['sceneimage'] = 'Picture';
$string['sceneimageprompt'] = 'Picture description (for AI)';
$string['sceneimageprompt_help'] = 'An English description of the picture, used in the picture prompt. It is never shown to learners.';
$string['sceneneedsimage'] = 'Needs a picture. ';
$string['sceneneedsresponses'] = 'Needs two responses, one marked as the better one.';
$string['scenequestion'] = 'Question to the learner';
$string['sceneready'] = 'Ready to play';
$string['scenesaved'] = 'Scene saved.';
$string['scenescreated'] = '{$a} scenes added.';
$string['sceneskill'] = 'Soft skill';
$string['scenesleft'] = 'Play every scene before finishing.';
$string['scenespeaker'] = 'Who the learner is';
$string['scenesword'] = 'Scenes';
$string['scenetitle'] = 'Scene title';
$string['scenetitle_help'] = 'Used when you add a scene without a picture. Scenes made from pictures take the file name as their title.';
$string['scenex'] = 'Scene {$a}';
$string['score'] = 'Score';
$string['seeresults'] = 'See my results';
$string['settings_ai'] = 'AI pictures (LMS Labs)';
$string['settings_ai_desc'] = 'AI Soft Skills uses LMS Labs only to create scene pictures, at 5 LMS Labs credits per successful picture; failed requests are not charged and are never retried automatically. Teachers draft the scenes themselves with the copy-and-paste prompt. The picture description (English, teacher-written) is sent to LMS Labs; no learner data is sent.';
$string['settings_defaults'] = 'Defaults for new activities';
$string['shuffleoptions'] = 'Shuffle the order of the two responses';
$string['skill_accountability'] = 'Accountability';
$string['skill_activelistening'] = 'Active listening';
$string['skill_adaptability'] = 'Adaptability';
$string['skill_change'] = 'Leading change';
$string['skill_clearinstructions'] = 'Giving clear instructions';
$string['skill_coaching'] = 'Coaching';
$string['skill_conflict'] = 'Resolving conflict';
$string['skill_customerservice'] = 'Customer service';
$string['skill_decisionmaking'] = 'Decision making';
$string['skill_delegation'] = 'Delegating';
$string['skill_difficultconversations'] = 'Difficult conversations';
$string['skill_empathy'] = 'Empathy';
$string['skill_feedbackgiving'] = 'Giving feedback';
$string['skill_feedbackreceiving'] = 'Receiving feedback';
$string['skill_inclusion'] = 'Inclusion and respect';
$string['skill_integrity'] = 'Integrity';
$string['skill_motivation'] = 'Motivating others';
$string['skill_negotiation'] = 'Negotiation';
$string['skill_problemsolving'] = 'Problem solving';
$string['skill_safety'] = 'Speaking up about safety';
$string['skill_stress'] = 'Staying calm under pressure';
$string['skill_teamwork'] = 'Teamwork';
$string['skill_timemanagement'] = 'Time management';
$string['skillgroup_communication'] = 'Communication';
$string['skillgroup_people'] = 'Working with people';
$string['skillgroup_self'] = 'Managing yourself';
$string['skillgroup_thinking'] = 'Thinking and deciding';
$string['skills_any'] = 'Any soft skills';
$string['sounds'] = 'Sound effects';
$string['sounds_desc'] = 'Play short sound effects (made in the browser, no audio files). Learners can mute them.';
$string['start'] = 'Start';
$string['state_finished'] = 'Finished';
$string['state_inprogress'] = 'In progress';
$string['step_build'] = 'Build';
$string['step_language'] = 'Language';
$string['step_level'] = 'Level';
$string['step_skills'] = 'Skills';
$string['step_workplace'] = 'Workplace';
$string['teachertools'] = 'Teacher tools';
$string['tryagain'] = 'Try the other response';
$string['tryagainactivity'] = 'Play again';
$string['twooptionsrequired'] = 'A scene needs exactly two responses, with one marked as the better response.';
$string['uploadimage'] = 'Upload a picture';
$string['uploadimages'] = 'Pictures';
$string['uploadimages_help'] = 'PNG, JPEG, GIF or WebP, or a ZIP of them (up to 100 pictures).';
$string['whatdoyousay'] = 'What do you say?';
$string['why'] = 'Why:';
$string['workplace'] = 'Workplace';
$string['workplaceindicators'] = 'Workplace indicators';
$string['yourattempts'] = 'Your attempts';
$string['zipinvalid'] = 'That ZIP file could not be read.';
$string['ziptoolarge'] = 'That ZIP is too large. Upload at most {$a} pictures and 200 MB unpacked.';
