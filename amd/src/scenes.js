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
 * Scene manager: copy picture prompts and create pictures with AI.
 *
 * @module     mod_aisoftskills/scenes
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {add as addToast} from 'core/toast';
import {init as initCopy} from 'mod_aisoftskills/copy';
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
    initCopy('.ss-copy');
    const S = await loadStrings(['generating', 'imagecreated', 'imagecreatedbalance',
        'imageintent_confirm', 'imageintent_replayed']);
    root.querySelectorAll('[data-action="genimage"]').forEach((btn) => {
        btn.addEventListener('click', async() => {
            if (btn.dataset.pending !== '1' && !window.confirm(S.imageintent_confirm)) {
                return;
            }
            const label = btn.querySelector('span');
            const original = label.textContent;
            btn.disabled = true;
            label.textContent = S.generating;
            try {
                // The server persists the key/body before sending; pending checks reuse the same operation.
                const res = await Ajax.call([{methodname: 'mod_aisoftskills_generate_image',
                    args: {sceneid: parseInt(btn.dataset.scene, 10), intent: btn.dataset.intent}}], true, true, false, 180000)[0];
                const message = res.replayed ? S.imageintent_replayed : res.balance >= 0 ?
                    fmt(S.imagecreatedbalance, {charged: res.charged, balance: res.balance})
                    : fmt(S.imagecreated, res.charged);
                await addToast(message, {type: 'success'});
                window.setTimeout(() => window.location.reload(), 2500);
            } catch (err) {
                btn.disabled = false;
                label.textContent = original;
                Notification.exception(err);
                // Refresh the durable state: pending remains resumable, 410 explicitly needs a new intent.
                window.setTimeout(() => window.location.reload(), 3000);
            }
        });
    });
};
