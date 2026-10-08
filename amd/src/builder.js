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
 * Lesson builder: the four-step wizard, then reviewing a pasted AI draft and creating the scenes.
 *
 * Without JavaScript every step of the wizard is shown on one page and the form still works.
 *
 * @module     mod_aisoftskills/builder
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {init as initCopy} from 'mod_aisoftskills/copy';
import * as Requests from 'mod_aisoftskills/requests';
import {render, loadStrings, fmt} from 'mod_aisoftskills/ui';

const KPIS = ['morale', 'motivation', 'productivity', 'trust', 'wellbeing', 'engagement', 'teamwork',
    'customersatisfaction', 'quality', 'safety'];
const MAX_DELTA = 50;

/**
 * The wizard (set-up steps 1 to 4).
 *
 * @param {string} selector
 * @param {number} start the step to open (1 to 4), e.g. 4 when coming back from step 5
 */
export const initWizard = async(selector, start = 1) => {
    const form = document.querySelector(selector);
    if (!form) {
        return;
    }
    const steps = Array.from(form.querySelectorAll('[data-step]'));
    const dots = Array.from(form.querySelectorAll('[data-stepdot]'));
    const prev = form.querySelector('[data-action="prev"]');
    const next = form.querySelector('[data-action="nextstep"]');
    const save = form.querySelector('[data-action="save"]');
    const skillStep = 2;
    const progress = document.querySelector('[data-region="setupprogress"]');
    const S = await loadStrings(['setup_progress']);
    let current = 0;
    form.classList.add('is-enhanced');

    const syncIndustry = () => {
        const chosen = form.querySelector('input[name="industry"]:checked');
        form.querySelector('[data-region="customindustry"]').hidden = !chosen || chosen.value !== 'custom';
    };
    const skillsChosen = () => form.querySelectorAll('input[name="skills[]"]:checked').length > 0 ||
        Array.from(form.querySelectorAll('input[name="custom[]"]')).some((i) => i.value.trim() !== '');
    const show = (i) => {
        current = Math.max(0, Math.min(steps.length - 1, i));
        steps.forEach((s, k) => {
            s.hidden = k !== current;
        });
        dots.forEach((d, k) => {
            d.classList.toggle('is-active', k === current);
            d.classList.toggle('is-done', k < current);
            if (k === current) {
                d.setAttribute('aria-current', 'step');
            } else {
                d.removeAttribute('aria-current');
            }
        });
        if (progress) {
            progress.textContent = fmt(S.setup_progress, {current: current + 1, total: 8});
        }
        prev.hidden = current === 0;
        next.hidden = current === steps.length - 1;
        save.hidden = current !== steps.length - 1;
        const first = steps[current].querySelector('input:checked, input, select');
        if (first) {
            first.focus({preventScroll: true});
        }
        form.scrollIntoView({behavior: 'smooth', block: 'start'});
    };
    const valid = () => {
        const error = form.querySelector('[data-region="skillerror"]');
        if (current === skillStep && !skillsChosen()) {
            error.hidden = false;
            return false;
        }
        error.hidden = true;
        return true;
    };

    form.addEventListener('change', (e) => {
        if (e.target.name === 'industry') {
            syncIndustry();
        }
    });
    prev.addEventListener('click', () => show(current - 1));
    next.addEventListener('click', () => {
        if (valid()) {
            show(current + 1);
        }
    });
    form.addEventListener('submit', (e) => {
        if (!skillsChosen()) {
            e.preventDefault();
            show(skillStep);
            valid();
        }
    });
    form.querySelector('[data-action="addcustom"]').addEventListener('click', () => {
        const hidden = form.querySelector('input[name="custom[]"][hidden]');
        if (hidden) {
            hidden.hidden = false;
            hidden.focus();
        }
    });
    syncIndustry();
    show(start - 1);
};

/**
 * Cleans one response the same way the server does, for the preview.
 *
 * @param {object} o
 * @returns {object}
 */
const cleanOption = (o) => {
    const best = !!o.best;
    const kpi = KPIS.includes(o.kpi) ? o.kpi : 'morale';
    let delta = Math.round(Number(o.kpidelta));
    if (!Number.isFinite(delta)) {
        delta = best ? 20 : -20;
    }
    delta = Math.max(-MAX_DELTA, Math.min(MAX_DELTA, delta));
    if (best && delta <= 0) {
        delta = Math.abs(delta) || 20;
    } else if (!best && delta > 0) {
        delta = -delta;
    }
    return {text: String(o.text || '').trim(), best, kpi, kpidelta: delta,
        consequence: String(o.consequence || ''), reason: String(o.reason || '')};
};

/**
 * Reads pasted AI output (tolerates code fences and text around the JSON).
 *
 * @param {string} raw
 * @returns {object|null}
 */
const parse = (raw) => {
    const text = raw.trim().replace(/^```[a-z]*\s*/i, '').replace(/```\s*$/, '');
    const start = text.indexOf('{');
    const end = text.lastIndexOf('}');
    if (start < 0 || end <= start) {
        return null;
    }
    try {
        const data = JSON.parse(text.slice(start, end + 1));
        if (!data || !Array.isArray(data.scenes)) {
            return null;
        }
        const scenes = data.scenes.filter((s) => s && s.title && Array.isArray(s.options)).map((s) => ({
            title: String(s.title),
            skill: String(s.skill || ''),
            context: String(s.context || ''),
            speaker: String(s.speaker || ''),
            question: String(s.question || ''),
            imageprompt: String(s.imageprompt || ''),
            options: s.options.slice(0, 2).filter((o) => o && o.text).map(cleanOption),
        })).filter((s) => s.options.length === 2 && s.options.filter((o) => o.best).length === 1);
        return scenes.length ? {scenes} : null;
    } catch (e) {
        return null;
    }
};

/**
 * Step 5: review a pasted draft and create the scenes.
 *
 * @param {string} selector
 */
export const initBuild = async(selector) => {
    const root = document.querySelector(selector);
    if (!root) {
        return;
    }
    const S = await loadStrings(['lessoninvalid', 'creating', 'lessonempty', 'aidraft_drafting', 'aidraft_confirm',
        'confirm_title', 'confirm_create',
        ...KPIS.map((k) => 'kpi_' + k)]);
    initCopy('.ss-copy');
    const cmid = parseInt(root.dataset.cmid, 10);
    const review = root.querySelector('[data-region="review"]');

    const showDraft = async(draft) => {
        const scenes = draft.scenes.map((s, index) => ({...s, index,
            options: s.options.map((o, i) => ({...o, letter: String.fromCharCode(65 + i), kpiname: S['kpi_' + o.kpi],
                delta: (o.kpidelta > 0 ? '+' : '') + o.kpidelta}))}));
        const node = await render('builder_review', {count: scenes.length, scenes});
        review.replaceChildren(node);
        review.hidden = false;
        review.scrollIntoView({behavior: 'smooth', block: 'start'});
        node.querySelector('[data-action="create"]').addEventListener('click', async(e) => {
            const btn = e.currentTarget;
            const keep = Array.from(node.querySelectorAll('[data-scene]')).filter((c) => c.checked)
                .map((c) => draft.scenes[parseInt(c.dataset.scene, 10)]);
            if (!keep.length) {
                Notification.alert('', S.lessonempty);
                return;
            }
            btn.disabled = true;
            btn.textContent = S.creating;
            try {
                await Ajax.call([{methodname: 'mod_aisoftskills_import_lesson', args: {cmid,
                    // Escape "<" so the JSON passes PARAM_TEXT; the server decodes it and strips any markup itself.
                    draft: JSON.stringify({scenes: keep}).replace(/</g, '\\u003c')}}])[0];
                window.location.href = root.dataset.nexturl;
            } catch (err) {
                btn.disabled = false;
                Notification.exception(err);
            }
        });
    };

    // Two ways to create scenes: show only the one the teacher picks.
    root.querySelectorAll('input[name="ss-path"]').forEach((radio) => radio.addEventListener('change', () => {
        root.querySelectorAll('[data-path]').forEach((path) => {
            path.hidden = path.dataset.path !== radio.value;
        });
        root.querySelector('[data-path="' + radio.value + '"]').scrollIntoView({behavior: 'smooth', block: 'nearest'});
    }));

    const panel = root.querySelector('[data-region="aidraft"]');
    if (panel) {
        const region = panel.querySelector('[data-region="aireqs"]');
        // A delivered scene is saved already: reload so it counts and Next opens.
        const delivered = (request) => {
            Requests.toast(request);
            window.setTimeout(() => window.location.reload(), 2500);
        };
        Requests.init(region, delivered);
        const button = panel.querySelector('[data-action="aidraft"]');
        const label = button.querySelector('span');
        const original = label.textContent;
        button.addEventListener('click', async() => {
            const value = (name) => panel.querySelector('[data-field="' + name + '"]').value;
            // No text of their own: LMS Labs AI writes a scene from the choices made earlier.
            const brief = value('brief').trim() || panel.querySelector('[data-field="brief"]').dataset.default;
            // A paid request: confirmed first, with the credits named.
            const go = await new Promise((resolve) => Notification.saveCancel(S.confirm_title, S.aidraft_confirm,
                S.confirm_create, () => resolve(true), () => resolve(false)));
            if (!go) {
                return;
            }
            button.disabled = true;
            label.textContent = S.aidraft_drafting;
            try {
                // One intentional, stored request per click (a new key); it is never resent automatically.
                const request = await Ajax.call([{methodname: 'mod_aisoftskills_draft_scene', args: {cmid,
                    brief, audience: value('audience'), context: value('context')}}],
                    true, true, false, 180000)[0];
                await Requests.show(region, request, delivered);
            } catch (err) {
                Notification.exception(err);
            } finally {
                button.disabled = false;
                label.textContent = original;
            }
        });
    }

    root.querySelector('[data-action="preview"]').addEventListener('click', () => {
        const data = parse(root.querySelector('[data-region="json"]').value);
        if (!data) {
            Notification.alert('', S.lessoninvalid);
            return;
        }
        showDraft(data);
    });
};
