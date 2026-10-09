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
 * Scene manager: create scene pictures with LMS Labs AI, one at a time or all missing pictures at once, and create the
 * missing voiceover clips one after another.
 *
 * @module     mod_aisoftskills/scenes
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import * as Requests from 'mod_aisoftskills/requests';
import {loadStrings, fmt} from 'mod_aisoftskills/ui';

/**
 * Initialises the page.
 *
 * @param {string} selector
 */
export const init = async(selector) => {
    const root = document.querySelector(selector);
    if (!root) {
        return;
    }
    const S = await loadStrings(['generating', 'genone_confirm', 'genone_confirm_replace', 'genall_confirm',
        'genall_progress', 'confirm_title', 'confirm_create', 'voice_confirm', 'voice_progress']);
    const region = root.querySelector('[data-region="aireqs"]');
    // A delivered picture is already saved: show it by reloading the page.
    const done = (request) => {
        Requests.toast(request);
        window.setTimeout(() => window.location.reload(), 2500);
    };
    Requests.init(region, done);

    // Every paid request is confirmed first; the credits are named in the question.
    const confirm = (question) => new Promise((resolve) => {
        Notification.saveCancel(S.confirm_title, question, S.confirm_create, () => resolve(true), () => resolve(false));
    });
    // One intentional, stored request (a new key); it is never resent automatically.
    const create = (sceneid) => Ajax.call([{methodname: 'mod_aisoftskills_generate_image',
        args: {sceneid}}], true, true, false, 180000)[0];

    root.querySelectorAll('[data-action="genimage"]').forEach((btn) => {
        btn.addEventListener('click', async() => {
            if (!await confirm(btn.dataset.replace === '1' ? S.genone_confirm_replace : S.genone_confirm)) {
                return;
            }
            const label = btn.querySelector('span');
            const original = label.textContent;
            btn.disabled = true;
            label.textContent = S.generating;
            try {
                const request = await create(parseInt(btn.dataset.scene, 10));
                await Requests.show(region, request, done);
                if (request.status !== 'completed') {
                    btn.disabled = false;
                    label.textContent = original;
                    region.scrollIntoView({behavior: 'smooth', block: 'start'});
                }
            } catch (err) {
                btn.disabled = false;
                label.textContent = original;
                Notification.exception(err);
            }
        });
    });

    // Voiceover: every missing clip, one after another; the run stops at the first clip that is not delivered.
    const voice = root.querySelector('[data-action="genvoice"]');
    if (voice) {
        voice.addEventListener('click', async() => {
            const clips = voice.dataset.clips.split(',').filter((v) => v !== '').map((v) => v.split(':').map(Number));
            const each = parseInt(voice.dataset.credits, 10);
            const credits = clips.length * each;
            if (!clips.length || !await confirm(fmt(S.voice_confirm, {count: clips.length, credits, each}))) {
                return;
            }
            const label = voice.querySelector('span');
            const original = label.textContent;
            voice.disabled = true;
            let made = 0;
            try {
                for (const [sceneid, index] of clips) {
                    label.textContent = fmt(S.voice_progress, {done: made + 1, count: clips.length});
                    let request;
                    try {
                        request = await Ajax.call([{methodname: 'mod_aisoftskills_create_voice',
                            args: {sceneid, index}}], true, true, false, 120000)[0];
                    } catch (err) {
                        if (err && err.errorcode === 'voice_alreadymade') {
                            // Made meanwhile (another tab): nothing bought, go on.
                            made++;
                            continue;
                        }
                        throw err;
                    }
                    if (request.status !== 'completed') {
                        await Requests.show(region, request, null);
                        region.scrollIntoView({behavior: 'smooth', block: 'start'});
                        break;
                    }
                    made++;
                }
            } catch (err) {
                Notification.exception(err);
            }
            if (made > 0) {
                window.location.reload();
                return;
            }
            voice.disabled = false;
            label.textContent = original;
        });
    }

    const all = root.querySelector('[data-action="genall"]');
    if (all) {
        all.addEventListener('click', async() => {
            const ids = all.dataset.scenes.split(',').map((v) => parseInt(v, 10)).filter((v) => v > 0);
            const credits = ids.length * parseInt(all.dataset.credits, 10);
            if (!ids.length || !await confirm(fmt(S.genall_confirm, {count: ids.length, credits}))) {
                return;
            }
            const label = all.querySelector('span');
            const original = label.textContent;
            all.disabled = true;
            let made = 0;
            try {
                // One after another; the run stops at the first picture that is not delivered.
                for (const id of ids) {
                    label.textContent = fmt(S.genall_progress, {done: made + 1, count: ids.length});
                    const request = await create(id);
                    if (request.status !== 'completed') {
                        await Requests.show(region, request, null);
                        region.scrollIntoView({behavior: 'smooth', block: 'start'});
                        break;
                    }
                    made++;
                }
            } catch (err) {
                Notification.exception(err);
            }
            if (made > 0) {
                window.location.reload();
                return;
            }
            all.disabled = false;
            label.textContent = original;
        });
    }
};
