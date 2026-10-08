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

$string['act_access'] = 'Access';
$string['act_balance'] = 'Balance';
$string['act_balance_unknown'] = 'Unknown (check access to update)';
$string['act_balance_unlimited'] = 'Unlimited';
$string['act_blocked_nocredentials'] = 'Add a complete Site ID and API key first.';
$string['act_blocked_pending'] = 'An earlier unlock request has an uncertain outcome. Check access before trying again.';
$string['act_blocked_pendingstorage'] = 'The pending request could not be saved. Nothing was sent; contact your administrator.';
$string['act_blocked_release'] = 'LMS Labs has not confirmed the release and price.';
$string['act_blocked_unlocked'] = 'This site is already unlocked.';
$string['act_blocked_unverified'] = 'Access could not be verified. Check access first.';
$string['act_check'] = 'Check access';
$string['act_checkedat'] = 'checked {$a}';
$string['act_confirm'] = 'Unlock AI Soft Skills for this site for {$a->price} LMS Labs credits? This spends credits once and cannot be undone. Balance now: {$a->balance}. Release: {$a->release}.';
$string['act_confirmbutton'] = 'Unlock for {$a} credits';
$string['act_credits'] = '{$a} credits';
$string['act_entitlementsource'] = 'entitlement: {$a}';
$string['act_err_network'] = 'no response from LMS Labs';
$string['act_err_nocredentials'] = 'no complete Site ID and API key';
$string['act_intro'] = 'AI Soft Skills needs one-time activation for this site. Checking access is free. Unlocking spends LMS Labs credits only after you confirm the live price.';
$string['act_msg_already'] = 'This site was already unlocked.';
$string['act_msg_ambiguous'] = 'Not unlocked: LMS Labs could not match this site to a single Marketplace purchase ({$a}). Contact LMS Labs support; trying again will not fix this.';
$string['act_msg_balance'] = 'Balance: {$a}.';
$string['act_msg_blocked'] = 'Nothing was sent to LMS Labs. {$a}';
$string['act_msg_changed'] = 'Not unlocked: the price or release changed after confirmation. Review the new price and try again.';
$string['act_msg_check_locked'] = 'Access checked: this site is locked.';
$string['act_msg_check_unknown'] = 'Access could not be verified ({$a}).';
$string['act_msg_check_unlocked'] = 'Access checked: this site is unlocked.';
$string['act_msg_conflict'] = 'Not unlocked: LMS Labs reported a conflict ({$a}). If the message does not say what to do, contact LMS Labs support.';
$string['act_msg_consumed'] = 'LMS Labs recorded {$a} credits for this unlock.';
$string['act_msg_historic'] = 'Credits used when the unlock was first bought: {$a} (not a new charge).';
$string['act_msg_insufficient'] = 'Not unlocked: not enough credits ({$a}).';
$string['act_msg_notreported'] = 'LMS Labs did not report the credits for this request; check the balance below.';
$string['act_msg_refused'] = 'Not unlocked: LMS Labs refused the request ({$a}).';
$string['act_msg_resolved_locked'] = 'The earlier unlock request did not unlock this site. You can review the price and try again.';
$string['act_msg_resolved_unlocked'] = 'The earlier unlock request did complete.';
$string['act_msg_restoredpurchase'] = 'It was activated from your existing {$a} purchase, so this unlock used 0 credits.';
$string['act_msg_servermessage'] = 'LMS Labs: {$a}';
$string['act_msg_stale'] = 'Not unlocked: the credit price at LMS Labs changed ({$a}). Review the new price and confirm again.';
$string['act_msg_uncertain'] = 'The unlock request did not return a clear answer ({$a}). Check access before trying again; unlocking stays disabled until then.';
$string['act_msg_unlocked'] = 'AI Soft Skills is unlocked for this site.';
$string['act_notproof'] = 'Having a Site ID and API key does not mean this site has unlocked AI Soft Skills. Use Check access.';
$string['act_pendingnote'] = 'An unlock request sent {$a} did not return a clear answer.';
$string['act_price'] = 'One-time activation price';
$string['act_price_onreview'] = 'Checked live with LMS Labs when you press Unlock, and shown for you to confirm before anything is spent.';
$string['act_price_unavailable'] = 'Unavailable: {$a}.';
$string['act_reason_mode'] = 'the acquisition mode ({$a}) does not allow unlocking with credits';
$string['act_reason_noprice'] = 'the catalogue entry has no valid credit price';
$string['act_reason_nosha'] = 'the catalogue entry has no valid SHA-256';
$string['act_reason_notavailable'] = 'the release is not available (availability: {$a})';
$string['act_reason_notlisted'] = 'mod_aisoftskills is not in the LMS Labs release catalogue';
$string['act_reason_nozip'] = 'the release package is not available at LMS Labs';
$string['act_reason_unreachable'] = 'the LMS Labs release catalogue could not be read';
$string['act_source'] = 'Credential source';
$string['act_source_central'] = 'Central Config (local_aiconfig)';
$string['act_source_local'] = 'This plugin\'s own Site ID and API key (complete pair)';
$string['act_source_missing'] = 'Not configured';
$string['act_status_locked'] = 'Locked';
$string['act_status_notchecked'] = 'Not checked';
$string['act_status_unknown'] = 'Unable to verify';
$string['act_status_unlocked'] = 'Unlocked';
$string['act_unlock'] = 'Unlock…';
$string['act_unlockedat'] = 'unlocked {$a}';
$string['act_unsaved'] = 'Save any changes below first: these buttons leave this page.';
$string['act_warn_insufficient'] = 'The balance ({$a->balance}) is below the price ({$a->price} credits). LMS Labs will refuse the unlock unless it recognises an existing purchase for this site.';
$string['activation'] = 'AI Soft Skills activation';
$string['addresponses'] = 'Add the two responses';
$string['aibadimage'] = 'The AI service did not return a usable picture.';
$string['aidraft_anyskills'] = 'the soft skills that matter most at their level';
$string['aidraft_audience'] = 'Who the learners are';
$string['aidraft_brief'] = 'Your text';
$string['aidraft_briefdefault'] = 'One short workplace scene for learners at the {$a->level} level to practise {$a->skills}. Two to four colleagues talk in a {$a->industry} workplace; the conversation stops at the moment the learner must decide what to say. Write every line in {$a->language}.';
$string['aidraft_briefrequired'] = 'Describe what the scene should be about.';
$string['aidraft_button'] = 'Create a scene ({$a} credits)';
$string['aidraft_confirm'] = 'Create this scene with LMS Labs AI? LMS Labs charges 3 credits when the scene is delivered.';
$string['aidraft_context'] = 'The workplace';
$string['aidraft_drafting'] = 'Drafting the scene. This can take up to a minute and a half; you can leave this page and come back.';
$string['aidraft_imageprompt'] = '{$a->title}. {$a->setting} People in the picture: {$a->characters}.';
$string['aidraft_nobrackets'] = 'Remove the < and > characters: LMS Labs does not accept them.';
$string['aidraft_toolong'] = '{$a->field} is too long: at most {$a->max} characters.';
$string['aidrafterror_failed'] = 'LMS Labs could not draft this scene. You have not been charged. (LMS Labs reference: {$a->requestid})';
$string['aidrafterror_insufficient_credits'] = 'Not enough LMS Labs credits: a scene draft needs {$a->credits} and {$a->balance} are left. Top up at lms-labs.com. (LMS Labs reference: {$a->requestid})';
$string['aidrafterror_invalid_credentials'] = 'LMS Labs did not accept this site\'s Site ID and API key. Check them in Central Config. (LMS Labs reference: {$a->requestid})';
$string['aidrafterror_no_entitlement'] = 'This site does not have AI Soft Skills enabled with LMS Labs. (LMS Labs reference: {$a->requestid})';
$string['aidrafterror_not_live'] = 'The LMS Labs scene draft service is not available yet. (LMS Labs reference: {$a->requestid})';
$string['aidrafterror_provider_failed'] = 'The AI service could not draft this scene. You have not been charged. Try again later. (LMS Labs reference: {$a->requestid})';
$string['aidrafterror_rate_limited'] = 'Too many drafts requested at once. Wait a moment and try again. You have not been charged. (LMS Labs reference: {$a->requestid})';
$string['aidrafterror_rejected'] = 'LMS Labs did not accept this request. Check the text and try again. You have not been charged. (LMS Labs reference: {$a->requestid})';
$string['aidrafterror_unusable_draft'] = 'LMS Labs delivered a draft this site could not use, and may have charged {$a->charged} credits. Contact LMS Labs support with the reference before drafting again. (LMS Labs reference: {$a->requestid})';
$string['aidrafts'] = 'AI scene drafts';
$string['aidrafts_desc'] = 'Let teachers draft a scene (title, setting, lead-in conversation and teaching note) with LMS Labs AI in the lesson builder. Each delivered draft costs 3 LMS Labs credits; failed drafts are not charged. Teachers need the "Use LMS Labs AI" capability.';
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
$string['aiimporterror_failed'] = 'LMS Labs could not confirm these scenes, so they were not created. You have not been charged. (LMS Labs reference: {$a->requestid})';
$string['aiimporterror_insufficient_credits'] = 'Not enough LMS Labs credits: these scenes need {$a->credits} and {$a->balance} are left. Top up at lms-labs.com. (LMS Labs reference: {$a->requestid})';
$string['aiimporterror_invalid_credentials'] = 'LMS Labs did not accept this site\'s Site ID and API key. Check them in Central Config. (LMS Labs reference: {$a->requestid})';
$string['aiimporterror_no_entitlement'] = 'This site does not have AI Soft Skills enabled with LMS Labs. (LMS Labs reference: {$a->requestid})';
$string['aiimporterror_not_live'] = 'Creating scenes from your own AI assistant is not available from LMS Labs yet. Use "Use your own text" for now. (LMS Labs reference: {$a->requestid})';
$string['aiimporterror_provider_failed'] = 'LMS Labs could not confirm these scenes right now. You have not been charged. Try again later. (LMS Labs reference: {$a->requestid})';
$string['aiimporterror_rate_limited'] = 'Too many requests at once. Wait a moment and try again. You have not been charged. (LMS Labs reference: {$a->requestid})';
$string['aiimporterror_rejected'] = 'LMS Labs did not accept this request. You have not been charged. (LMS Labs reference: {$a->requestid})';
$string['aiimporterror_unusable_draft'] = 'LMS Labs answered in a way this site could not use. Contact LMS Labs support with the reference. (LMS Labs reference: {$a->requestid})';
$string['ainotavailable'] = 'AI pictures are switched off, or this site\'s LMS Labs Site ID and API key are not set.';
$string['airate'] = 'AI requests per teacher per hour';
$string['airate_desc'] = 'Most AI picture requests one teacher can make per hour.';
$string['airatelimit'] = 'You have reached the hourly limit for AI requests. Try again later.';
$string['aireq_busy'] = 'An earlier request for this is still unresolved. Use "Check again" on it, or dismiss it, before asking for a new one.';
$string['aireq_checkagain'] = 'Check again';
$string['aireq_checking'] = 'Checking…';
$string['aireq_dismiss'] = 'Dismiss';
$string['aireq_dismissconfirm'] = 'LMS Labs may still complete this request and charge for it. Dismiss it anyway? You can then ask for a new one, which is a separate request.';
$string['aireq_heading'] = 'LMS Labs requests to finish';
$string['aireq_image_completed'] = 'Picture created. {$a->charged} LMS Labs credits used.';
$string['aireq_image_completedbalance'] = 'Picture created. {$a->charged} LMS Labs credits used; {$a->balance} left.';
$string['aireq_image_conflict'] = 'LMS Labs says this request was already used with a different picture description, so nothing was created. Dismiss it and create the picture again. (LMS Labs reference: {$a->requestid})';
$string['aireq_image_dismissed'] = 'Dismissed.';
$string['aireq_image_expired'] = 'LMS Labs no longer has this request. Dismiss it and create the picture again if you still need one. (LMS Labs reference: {$a->requestid})';
$string['aireq_image_lost'] = 'LMS Labs finished this request, but no picture reached this site, and LMS Labs does not keep pictures. {$a->credits} credits may have been charged. Contact LMS Labs support with the reference before creating another picture; a new picture is a new, separately charged request. (LMS Labs reference: {$a->requestid})';
$string['aireq_image_pending'] = 'LMS Labs is still creating this picture. This page checks again every few seconds. (LMS Labs reference: {$a->requestid})';
$string['aireq_image_uncertain'] = 'LMS Labs did not confirm this picture. It may still have been created and charged. "Check again" asks about the same request and can never create or charge a second picture. (LMS Labs reference: {$a->requestid})';
$string['aireq_import_completed'] = 'Scenes created: {$a->charged} LMS Labs credits used. Next: the pictures.';
$string['aireq_import_completedbalance'] = 'Scenes created: {$a->charged} LMS Labs credits used; {$a->balance} left. Next: the pictures.';
$string['aireq_import_conflict'] = 'LMS Labs says this request was already used with different scenes, so nothing was created. Dismiss it and create the scenes again. (LMS Labs reference: {$a->requestid})';
$string['aireq_import_dismissed'] = 'Dismissed.';
$string['aireq_import_expired'] = 'LMS Labs no longer has this request, so it cannot be confirmed. Contact LMS Labs support with the reference before creating these scenes again. (LMS Labs reference: {$a->requestid})';
$string['aireq_import_lost'] = 'LMS Labs could not confirm these scenes. Contact LMS Labs support with the reference. (LMS Labs reference: {$a->requestid})';
$string['aireq_import_pending'] = 'LMS Labs is still confirming these scenes. This page checks again every few seconds. (LMS Labs reference: {$a->requestid})';
$string['aireq_import_uncertain'] = 'LMS Labs did not confirm these scenes, so they have not been created yet. "Check again" asks about the same request and cannot charge twice. (LMS Labs reference: {$a->requestid})';
$string['aireq_openscene'] = 'Open the scene';
$string['aireq_scene_completed'] = 'Scene created: {$a->charged} LMS Labs credits used. Create another, or press Next for the pictures.';
$string['aireq_scene_completedbalance'] = 'Scene created: {$a->charged} LMS Labs credits used; {$a->balance} left. Create another, or press Next for the pictures.';
$string['aireq_scene_conflict'] = 'LMS Labs says this request was already used with different text, so nothing was drafted. Dismiss it and draft again. (LMS Labs reference: {$a->requestid})';
$string['aireq_scene_dismissed'] = 'Dismissed.';
$string['aireq_scene_expired'] = 'This draft is no longer available at LMS Labs (drafts are kept for 24 hours). Dismiss it and draft again if you still need it. (LMS Labs reference: {$a->requestid})';
$string['aireq_scene_lost'] = 'This draft did not reach this site. Contact LMS Labs support with the reference. (LMS Labs reference: {$a->requestid})';
$string['aireq_scene_pending'] = 'LMS Labs is still drafting this scene. This page checks again every few seconds. (LMS Labs reference: {$a->requestid})';
$string['aireq_scene_uncertain'] = 'LMS Labs did not confirm this draft. It may still be completing. "Check again" asks about the same request: a delivered draft is sent again without a second charge. (LMS Labs reference: {$a->requestid})';
$string['aireq_sitechanged'] = 'This request was made with a different LMS Labs Site ID, so it cannot be checked with the current one (that could be charged again). Dismiss it; ask for a new one if you still need it.';
$string['aisoftskills:addinstance'] = 'Add a new AI Soft Skills activity';
$string['aisoftskills:attempt'] = 'Play the scenes';
$string['aisoftskills:manage'] = 'Build and edit scenes';
$string['aisoftskills:useai'] = 'Use LMS Labs AI (scene drafts and pictures)';
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
$string['build_manual_1'] = 'Copy the prompt.';
$string['build_manual_2'] = 'Paste it into ChatGPT, Claude, Gemini or Copilot. Change it there if you want different scenes.';
$string['build_manual_3'] = 'Paste the AI\'s whole reply below and press Preview.';
$string['build_paste'] = 'The AI\'s reply';
$string['build_preview'] = 'Preview';
$string['build_showprompt'] = 'Show the prompt';
$string['builder_existing'] = 'This activity already has {$a} scenes. New scenes are added after them.';
$string['builder_save'] = 'Next: Create the scenes';
$string['careerladder'] = 'Career levels';
$string['check_intro'] = 'Open each scene and read it through. Every scene needs two responses for learners to choose between, with one marked as the better one. Scenes created from your own text need their two responses added here.';
$string['check_title'] = 'Check each scene';
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
$string['confirm_create'] = 'Yes, create it';
$string['confirm_title'] = 'Use LMS Labs credits?';
$string['confirmdeleteattempts'] = 'Delete the selected attempts? Grades and completion are recalculated.';
$string['confirmdeletescene'] = 'Delete the scene "{$a}", its picture, its responses and learners\' choices in it?';
$string['contentlang'] = 'Language of the scenes';
$string['contentlang_help'] = 'The language the scenes, responses and consequences are written in. Buttons and messages follow each learner\'s Moodle language.';
$string['copied'] = 'Copied';
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
$string['finish_done'] = 'Done: back to the course';
$string['finish_nopicture'] = 'Scenes without a picture: {$a}';
$string['finish_noresponses'] = 'Scenes without their two responses: {$a}';
$string['finish_noscenes'] = 'There are no scenes yet.';
$string['finish_notready'] = '{$a->ready} of {$a->scenes} scenes are ready';
$string['finish_notready_help'] = 'Learners only see the scenes that are ready. Use Back to finish the others.';
$string['finish_preview'] = 'Preview as a learner';
$string['finish_ready'] = 'All {$a} scenes are ready';
$string['finish_ready_help'] = 'Learners can now play the activity. Preview it as a learner first if you like.';
$string['genall_button'] = 'Create missing pictures ({$a->credits} credits)';
$string['genall_confirm'] = 'Create the missing pictures ({$a->count})? LMS Labs charges 5 credits for each picture it delivers, up to {$a->credits} credits. They are made one after another and saved in Moodle.';
$string['genall_progress'] = 'Creating picture {$a->done} of {$a->count}…';
$string['generateimage'] = 'Create picture ({$a} credits)';
$string['generating'] = 'Creating… this can take a minute';
$string['genone_confirm'] = 'Create a picture for this scene? LMS Labs charges 5 credits when the picture is delivered.';
$string['genone_confirm_replace'] = 'Create a new picture for this scene? It replaces the current picture. LMS Labs charges 5 credits when the new picture is delivered.';
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
$string['imageprompt_full'] = 'Create one {$a->style} for a workplace soft-skills lesson.

Scene: {$a->description}

Industry: {$a->industry}. The workplace, uniforms, equipment and people should look typical of this industry.

Rules:
- Landscape, 16:10 (for example 1600 × 1000 pixels).
- Show the people involved clearly; faces, gestures and body language make the moment obvious.
- The moment is shown just before anyone responds, so the picture suits both possible responses.
- No text, letters, numbers, captions, signs, logos or speech bubbles.';
$string['imagereplaced'] = 'Picture saved.';
$string['imagestyle'] = 'Picture style';
$string['imagestyle_illustration'] = 'bright, friendly flat illustration';
$string['imagestyle_illustration_name'] = 'Illustration';
$string['imagestyle_photo'] = 'realistic photograph';
$string['imagestyle_photo_name'] = 'Photo';
$string['import_confirm'] = 'Scenes to create: {$a->count}. LMS Labs charges 3 credits per scene, {$a->credits} credits in all, the same as scenes written by LMS Labs AI. The scenes are created once LMS Labs confirms.';
$string['import_title'] = 'Scenes from your AI assistant ({$a->count}): {$a->titles}';
$string['import_toomany'] = 'At most {$a} scenes can be created at once.';
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
$string['lessonprompt'] = 'You are a senior workplace-learning designer and an expert in soft skills and behavioural coaching. Write demanding, realistic branching scenes for experienced adults.

Industry: {$a->industry}
Career level of the learner: {$a->level}. {$a->levelguide}
Language: write every title, context, question, response, consequence and reason in {$a->language}. Use natural {$a->language} as people really speak it at work in this industry, including its usual job titles and jargon.

Soft skills to practise:
{$a->skills}

Create {$a->scenes} scenes, shared out between the skills. Each scene is one decisive moment that a single picture can show. Make every scene genuinely complex:
- at least two people with different, legitimate interests (for example a tired colleague, a demanding client and your own manager), and a real cost to every choice;
- pressure that is typical for this industry and level: time, safety, targets, money, reputation, hierarchy or cultural differences;
- missing or conflicting information, so the learner has to read the situation, not just recall a rule;
- emotions under the surface (fear, pride, frustration, embarrassment) that the better response notices and handles.

The learner plays the {$a->level} and chooses between exactly two responses:
- the better response (best: true): what a skilled {$a->level} would really say or do. It is specific and shows the skill through observable behaviour, for example naming what they see, asking a real question, agreeing a next step or owning a mistake. It is not perfect or preachy, and it may still carry a cost;
- the poorer response (best: false): a tempting, common mistake that capable people make under this pressure, for example taking over, avoiding the conversation, over-promising or reacting to tone instead of the problem. It must sound reasonable at first reading. Never make it rude, silly or obviously wrong.

For each response give:
- text: exactly what the person says or does, in one to three sentences
- best: true or false (exactly one true per scene)
- kpi: the workplace indicator the choice moves most, one of: {$a->kpis}
- kpidelta: how much it moves, a whole number from 5 to {$a->maxdelta} for the better response and from -{$a->maxdelta} to -5 for the poorer one; bigger numbers for bigger consequences
- consequence: two or three sentences on what realistically happens next, including a knock-on effect on another person, the team or the customer
- reason: one or two sentences naming the specific behaviour that made it work or backfire, linked to the skill

For every scene give:
- skill: the soft skill practised
- title: a short title for the moment
- context: two or three sentences: who is involved, what has just happened, and what is at stake
- speaker: who the learner is in this moment, for example "You, the shift supervisor"
- question: the decision the learner faces, for example "What do you say to Maria right now?"
- imageprompt: an English description of one {$a->style} of this moment in a {$a->industry} workplace, showing the people involved, their body language and expressions, and the setting. No text, letters, captions, signs or speech bubbles. Do not describe real people.

Vary the scenes: different people, places, times of day and kinds of pressure. Do not repeat the same dilemma.

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
$string['notactivated'] = 'AI Soft Skills is not activated on this site yet.';
$string['notactivated_admin'] = 'AI Soft Skills is not activated on this site yet. Open the AI Soft Skills settings, press Check access, and unlock it there (50 LMS Labs credits, or free when LMS Labs has a record of a Moodle Marketplace purchase). Until then, lessons cannot be set up or played.';
$string['notactivated_learner'] = 'This activity is not available yet. Please try again later.';
$string['notactivated_open'] = 'Open the AI Soft Skills settings';
$string['notactivated_teacher'] = 'AI Soft Skills is not activated on this site yet, so lessons cannot be set up. Ask your site administrator to activate it in the AI Soft Skills settings.';
$string['notready_manager'] = 'Add scenes that each have a picture and two responses, one of them marked as the better one.';
$string['notready_student'] = 'Your teacher is still preparing this activity.';
$string['notyourattempt'] = 'This attempt belongs to someone else.';
$string['own_cost'] = 'Each click creates one scene for {$a} LMS Labs credits, charged only when the scene is delivered. Create as many scenes as you need, then press Next. Pictures come in the next step; the two responses learners choose between are added in step 7.';
$string['own_guide_empty'] = 'No text? Leave the box empty and LMS Labs AI writes a scene from your choices above.';
$string['own_guide_include'] = 'Say who is involved, what goes wrong, and the moment the learner has to decide what to say.';
$string['own_guide_length'] = 'Length: 30 to 300 words (at most 2,000 characters). One situation per scene.';
$string['own_guide_privacy'] = 'Remove real names and personal details.';
$string['own_guide_type'] = 'What works well: something that happened at work, a customer complaint, an incident report, or a procedure staff find hard to follow.';
$string['own_label'] = 'Your text';
$string['own_placeholder'] = 'Example: A guest at the front desk complains loudly that their room is not ready. A new receptionist is about to blame housekeeping in front of the guest.';
$string['path_assistant_desc'] = 'Copy our prompt into ChatGPT, Claude, Gemini or Copilot. More control over the content, and best for complex scenarios.';
$string['path_assistant_noimport'] = 'Creating scenes needs this site\'s LMS Labs connection and the "Use LMS Labs AI" permission. Ask your site administrator.';
$string['path_assistant_note'] = 'This plugin does not send the prompt anywhere. Scenes arrive complete with both responses. Each scene you create costs {$a} LMS Labs credits, the same as an LMS Labs AI scene, charged only when LMS Labs confirms. Pictures are created with LMS Labs AI in the next step.';
$string['path_assistant_only'] = 'LMS Labs AI scene writing is not available on this site, so create the scenes with your own AI assistant.';
$string['path_assistant_title'] = 'Use an AI assistant';
$string['path_own_desc'] = 'Paste or type a workplace situation. LMS Labs AI turns it into a scene. Quick and simple.';
$string['path_own_title'] = 'Use your own text';
$string['path_question'] = 'How do you want to create the scenes?';
$string['pictures_alldone'] = 'Every scene has a picture.';
$string['pictures_intro'] = 'Learners only see scenes that have a picture. LMS Labs AI creates each picture for {$a} credits, charged only when the picture is delivered. You can also upload your own picture.';
$string['pictures_notconnected'] = 'Scene pictures are created by LMS Labs AI. Connect LMS Labs (Site ID and API key in Central Config) and switch on "AI scene pictures" to create them here, or upload your own picture for each scene.';
$string['pictures_title'] = 'Create a picture for every scene';
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
$string['privacy:metadata:aireq'] = 'Teachers\' LMS Labs AI requests (scene drafts and pictures), kept so a request is never sent twice with a new key.';
$string['privacy:metadata:aireq:body'] = 'The text sent to LMS Labs: the scene brief, audience and workplace, the picture description, or the number and titles of scenes written with an AI assistant.';
$string['privacy:metadata:aireq:requestid'] = 'The LMS Labs reference.';
$string['privacy:metadata:aireq:status'] = 'Whether the request worked.';
$string['privacy:metadata:aireq:timecreated'] = 'When the request was made.';
$string['privacy:metadata:aireq:userid'] = 'The teacher.';
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
$string['privacy:metadata:lmslabs'] = 'When a teacher drafts a scene or creates a scene picture with LMS Labs AI, or creates scenes written with an AI assistant (charged per scene), the teacher-written text (scene brief, audience and workplace, the English picture description, or the number and titles of the scenes) and the site\'s LMS Labs credentials are sent to LMS Labs, which creates the text and pictures with its own AI providers. No learner data is sent.';
$string['privacy:metadata:lmslabs:brief'] = 'The teacher-written scene brief, audience and workplace, or the titles of scenes written with an AI assistant.';
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
$string['regenerateimage'] = 'New AI picture ({$a} credits)';
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
$string['scenehasimage'] = 'Picture ready';
$string['sceneimage'] = 'Picture';
$string['sceneimageprompt'] = 'Picture description (for AI)';
$string['sceneimageprompt_help'] = 'An English description of the picture. LMS Labs AI uses it to create the scene picture. It is never shown to learners.';
$string['sceneneedsimage'] = 'Needs a picture';
$string['sceneneedsresponses'] = 'Needs two responses, one marked as the better one.';
$string['scenequestion'] = 'Question to the learner';
$string['sceneresponsesok'] = 'Two responses ready';
$string['scenesaved'] = 'Scene saved.';
$string['scenescript'] = 'Lead-in conversation';
$string['scenescript_help'] = 'Shown to learners above the question, one line each. Write each line as "Name: what they say". Leave empty for no conversation.';
$string['sceneskill'] = 'Soft skill';
$string['scenesleft'] = 'Play every scene before finishing.';
$string['scenespeaker'] = 'Who the learner is';
$string['scenesword'] = 'Scenes';
$string['sceneteachingnote'] = 'Teaching note';
$string['sceneteachingnote_help'] = 'For teachers only: what this scene teaches. It is never shown to learners.';
$string['scenetitle'] = 'Scene title';
$string['scenetitle_help'] = 'Used when you add a scene without a picture. Scenes made from pictures take the file name as their title.';
$string['scenex'] = 'Scene {$a}';
$string['score'] = 'Score';
$string['seeresults'] = 'See my results';
$string['settings_ai'] = 'AI pictures (LMS Labs)';
$string['settings_ai_desc'] = 'AI Soft Skills uses LMS Labs for two things, each charged by LMS Labs only when it is delivered: scene drafts (3 credits each) and scene pictures (5 credits each). Every request is stored in Moodle before it is sent and is never retried automatically; "Check again" asks about the same request and cannot be charged twice. Teachers can still draft scenes themselves with the copy-and-paste prompt. Only teacher-written text is sent to LMS Labs; no learner data is sent.';
$string['settings_defaults'] = 'Defaults for new activities';
$string['setup_back'] = 'Back';
$string['setup_needpictures'] = 'Scenes still without a picture: {$a}.';
$string['setup_needresponses'] = 'Scenes still without their two responses: {$a}.';
$string['setup_needscene'] = 'Create at least one scene first.';
$string['setup_next'] = 'Next: {$a}';
$string['setup_progress'] = 'Step {$a->current} of {$a->total}';
$string['setup_title'] = 'Set up the lesson';
$string['setupstep_check'] = 'Check the scenes';
$string['setupstep_create'] = 'Create the scenes';
$string['setupstep_finish'] = 'Finish';
$string['setupstep_language'] = 'Language';
$string['setupstep_level'] = 'Level';
$string['setupstep_pictures'] = 'Pictures';
$string['setupstep_skills'] = 'Skills';
$string['setupstep_workplace'] = 'Workplace';
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
$string['step_language'] = 'Language';
$string['step_level'] = 'Level';
$string['step_skills'] = 'Skills';
$string['step_workplace'] = 'Workplace';
$string['takeaway_better'] = 'A stronger response:';
$string['takeaways'] = 'What to take away';
$string['teachertools'] = 'Teacher tools';
$string['tryagain'] = 'Try the other response';
$string['tryagainactivity'] = 'Play again';
$string['twooptionsrequired'] = 'A scene needs exactly two responses, with one marked as the better response.';
$string['uploadimage'] = 'Upload a picture';
$string['uploadimages_help'] = 'PNG, JPEG, GIF or WebP, or a ZIP of them (up to 100 pictures).';
$string['uploadownimage'] = 'Upload my own picture';
$string['whatdoyousay'] = 'What do you say?';
$string['why'] = 'Why:';
$string['workplace'] = 'Workplace';
$string['workplaceindicators'] = 'Workplace indicators';
$string['yourattempts'] = 'Your attempts';
$string['zipinvalid'] = 'That ZIP file could not be read.';
$string['ziptoolarge'] = 'That ZIP is too large. Upload at most {$a} pictures and 200 MB unpacked.';
