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
 * Name labels editor: drag each label ("Leo - Bartender") onto the person in the picture, choose the person's voice (one
 * of the 8 voices, or an automatic female or male voice, which shows the voice it gives), and mark the learner.
 * Saving is free: nothing is sent to LMS Labs.
 *
 * @module     mod_aisoftskills/labels
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Modal from 'core/modal';
import Notification from 'core/notification';
import {loadStrings, fmt} from 'mod_aisoftskills/ui';

const MAX = 6;
let S = {};
let NARRATOR = '';
let GENDERS = {f: [], m: []};

/**
 * The select value of a label: a voice type ("v:Leda"), an automatic voice of a kind ("f", "m") or anything ("").
 *
 * @param {object} label
 * @returns {string}
 */
const voiceValue = (label) => (label.voice ? 'v:' + label.voice : label.gender);

/**
 * Makes an element.
 *
 * @param {string} tag
 * @param {object} attrs
 * @param {Array} children
 * @returns {HTMLElement}
 */
const el = (tag, attrs = {}, children = []) => {
    const node = document.createElement(tag);
    Object.entries(attrs).forEach(([k, v]) => {
        if (k === 'text') {
            node.textContent = v;
        } else if (k === 'class') {
            node.className = v;
        } else {
            node.setAttribute(k, v);
        }
    });
    children.forEach((c) => node.append(c));
    return node;
};

/**
 * Opens the editor for one scene.
 *
 * @param {HTMLButtonElement} btn
 */
const open = async(btn) => {
    let labels = JSON.parse(btn.dataset.labels || '[]').map((l) => Object.assign({gender: '', voice: '', you: false}, l));
    // The voice each saved label really gets, so "Automatic" can say which one it is.
    const resolved = JSON.parse(btn.dataset.voices || '[]');
    labels.forEach((l, i) => {
        l.resolved = resolved[i] || '';
        l.start = voiceValue(l);
    });
    const scenario = JSON.parse(btn.dataset.scenario || '{}');
    const person = (text) => String(text).split(/\s+[-–—]\s+|,/)[0].trim();
    const people = scenario.people || [];
    // Everyone the scenario names gets a label: people missing from labels saved earlier are added here, spread
    // along the top of the picture, so the teacher only drags each one onto the right person.
    const added = [];
    if (btn.dataset.suggested !== '1') {
        const have = labels.map((l) => person(l.text).toLowerCase());
        people.filter((p) => !have.includes(person(p.text).toLowerCase())).forEach((p) => {
            if (labels.length >= MAX) {
                return;
            }
            const used = labels.map((l) => l.x);
            const x = [20, 80, 50, 35, 65, 10, 90].find((c) => used.every((u) => Math.abs(u - c) > 8)) || 50;
            labels.push({text: p.text, x, y: 30, gender: p.gender || '', voice: '', you: false, resolved: '', start: ''});
            added.push(p.text);
        });
    }
    const body = el('div', {class: 'ss-labeleditor'});
    body.append(el('p', {class: 'ss-mini', text: S.labels_help}));
    if (btn.dataset.suggested === '1' && labels.length) {
        body.append(el('p', {class: 'ss-note', text: S.labels_suggested}));
    }
    if (added.length) {
        body.append(el('p', {class: 'ss-note', text: fmt(S.labels_added, added.join(', '))}));
    }
    // The scenario, with the names in it marked, so the teacher can check who is who.
    const names = [...new Set(labels.map((l) => person(l.text)).concat(people.map((p) => person(p.text))))]
        .filter((n) => n && n.toLowerCase() !== 'you');
    const marked = (text) => {
        const node = el('span');
        const pattern = names.length ? new RegExp('(' + names.map((n) => n.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'))
            .join('|') + ')', 'gi') : null;
        const lower = names.map((n) => n.toLowerCase());
        const parts = pattern ? String(text).split(pattern) : [String(text)];
        // A name is marked only as a whole word ("Leo", not the start of "Leonard").
        const letter = /\p{L}/u;
        parts.forEach((part, k) => {
            const whole = !letter.test((parts[k - 1] || ' ').slice(-1)) && !letter.test((parts[k + 1] || ' ').charAt(0));
            node.append(lower.includes(part.toLowerCase()) && whole ? el('mark', {text: part})
                : document.createTextNode(part));
        });
        return node;
    };
    if (scenario.context || (scenario.lines || []).length) {
        const box = el('details', {class: 'ss-labelscenario', open: 'open'}, [el('summary', {text: S.labels_scenario})]);
        if (scenario.speaker) {
            box.append(el('p', {class: 'ss-mini', text: scenario.speaker}));
        }
        if (scenario.context) {
            box.append(el('p', {}, [marked(scenario.context)]));
        }
        (scenario.lines || []).forEach((l) => box.append(el('p', {class: 'ss-labelscenario-line'},
            [el('strong', {}, [marked(l.speaker)]), document.createTextNode(' '), marked(l.line)])));
        body.append(box);
    }
    const stage = el('div', {class: 'ss-labeleditor-stage'}, [el('img', {src: btn.dataset.image, alt: '', draggable: 'false'})]);
    const list = el('div', {class: 'ss-labeleditor-list'});
    const add = el('button', {type: 'button', class: 'ss-btn ss-btn-ghost ss-btn-sm', text: S.label_add});
    body.append(stage, list, add);

    const draw = () => {
        stage.querySelectorAll('.ss-namelabel').forEach((n) => n.remove());
        list.replaceChildren();
        labels.forEach((label, i) => {
            // The label on the picture: drag it, or move it with the arrow keys.
            const pin = el('span', {class: 'ss-namelabel is-draggable' + (label.you ? ' is-you' : ''), tabindex: '0',
                role: 'button', 'aria-label': label.text || S.label_text, dir: 'auto', text: label.text || '…'});
            pin.style.left = `${label.x}%`;
            pin.style.top = `${label.y}%`;
            pin.addEventListener('pointerdown', (e) => {
                e.preventDefault();
                pin.setPointerCapture(e.pointerId);
                const move = (ev) => {
                    const box = stage.getBoundingClientRect();
                    label.x = Math.max(2, Math.min(98, ((ev.clientX - box.left) / box.width) * 100));
                    label.y = Math.max(2, Math.min(98, ((ev.clientY - box.top) / box.height) * 100));
                    pin.style.left = `${label.x}%`;
                    pin.style.top = `${label.y}%`;
                };
                const up = () => {
                    pin.removeEventListener('pointermove', move);
                    pin.removeEventListener('pointerup', up);
                };
                pin.addEventListener('pointermove', move);
                pin.addEventListener('pointerup', up);
            });
            pin.addEventListener('keydown', (e) => {
                const step = e.shiftKey ? 5 : 1;
                const moves = {ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step]};
                if (moves[e.key]) {
                    e.preventDefault();
                    label.x = Math.max(2, Math.min(98, label.x + moves[e.key][0]));
                    label.y = Math.max(2, Math.min(98, label.y + moves[e.key][1]));
                    pin.style.left = `${label.x}%`;
                    pin.style.top = `${label.y}%`;
                }
            });
            stage.append(pin);

            // Its row: text, voice, learner, remove.
            const text = el('input', {type: 'text', class: 'form-control form-control-sm', maxlength: '60',
                value: label.text, 'aria-label': S.label_text, dir: 'auto'});
            text.addEventListener('input', () => {
                label.text = text.value;
                pin.textContent = text.value || '…';
                pin.setAttribute('aria-label', text.value || S.label_text);
            });
            // The automatic choices say which voice they give while the choice is unchanged since the last save.
            const auto = (value, text) => {
                const same = label.resolved && label.start === value;
                return el('option', {value, text: same ? fmt(S.label_auto_is, {kind: text, voice: label.resolved}) : text});
            };
            const group = (kind, title) => el('optgroup', {label: title}, GENDERS[kind].map((type) => {
                const option = el('option', {value: 'v:' + type, text: type === NARRATOR
                    ? fmt(S.label_voice_narrator, type) : type});
                if (type === NARRATOR) {
                    option.disabled = true;
                }
                return option;
            }));
            const voice = el('select', {class: 'custom-select form-select form-select-sm', 'aria-label': S.label_voice}, [
                auto('', S.label_unknownvoice), auto('f', S.label_female), auto('m', S.label_male),
                group('f', S.label_voices_female), group('m', S.label_voices_male)]);
            voice.value = voiceValue(label);
            voice.addEventListener('change', () => {
                const value = voice.value;
                label.voice = value.startsWith('v:') ? value.slice(2) : '';
                label.gender = label.voice ? (GENDERS.f.includes(label.voice) ? 'f' : 'm') : value;
                warnSame();
            });
            const you = el('input', {type: 'radio', name: 'ss-label-you', id: `ss-label-you-${i}`});
            you.checked = !!label.you;
            you.addEventListener('change', () => {
                labels.forEach((l) => {
                    l.you = l === label;
                });
                draw();
            });
            const remove = el('button', {type: 'button', class: 'ss-iconbtn', title: S.label_remove,
                'aria-label': S.label_remove}, []);
            remove.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>';
            remove.addEventListener('click', () => {
                labels.splice(i, 1);
                draw();
            });
            list.append(el('div', {class: 'ss-labelrow', 'data-row': String(i)}, [text, voice,
                el('label', {class: 'ss-labelrow-you', for: `ss-label-you-${i}`}, [you, el('span', {text: S.label_youlearner})]),
                remove]));
        });
        add.disabled = labels.length >= MAX;
        warnSame();
    };

    // Two people in this scene with the same voice would sound alike: say so (saving is still allowed).
    const note = el('p', {class: 'ss-note ss-labelvoice-note', role: 'status', hidden: 'hidden'});
    list.after(note);
    const warnSame = () => {
        const heard = labels.map((l) => l.voice || (l.resolved && l.start === voiceValue(l) ? l.resolved : ''));
        const twice = heard.filter((v, i) => v && heard.indexOf(v) !== i);
        note.hidden = twice.length === 0;
        note.textContent = twice.length ? fmt(S.label_voice_same, twice[0]) : '';
    };
    add.addEventListener('click', () => {
        labels.push({text: '', x: 50, y: 50, gender: '', voice: '', you: false, resolved: '', start: ''});
        draw();
        list.querySelector('.ss-labelrow:last-child input[type="text"]').focus();
    });
    draw();

    const save = el('button', {type: 'button', class: 'btn btn-primary', text: S.labels_save});
    const modal = await Modal.create({title: fmt(S.labels_dialog, btn.dataset.title), body, footer: save, large: true,
        removeOnClose: true, show: true});
    save.addEventListener('click', async() => {
        save.disabled = true;
        try {
            await Ajax.call([{methodname: 'mod_aisoftskills_save_labels', args: {sceneid: Number(btn.dataset.scene),
                labels: labels.filter((l) => l.text.trim() !== '').map((l) => ({text: l.text.trim(),
                    x: Math.round(l.x * 10) / 10, y: Math.round(l.y * 10) / 10, gender: l.gender, voice: l.voice || '',
                    you: !!l.you}))}}])[0];
            modal.destroy();
            window.location.reload();
        } catch (err) {
            save.disabled = false;
            Notification.exception(err);
        }
    });
};

/**
 * Initialises the "Name labels" buttons of the pictures step.
 *
 * @param {string} selector
 * @param {string} narrator the narrator's voice type, which people can't have
 * @param {object} genders voice types by kind: {f: [...], m: [...]}
 */
export const init = async(selector, narrator = '', genders = null) => {
    const root = document.querySelector(selector);
    if (!root) {
        return;
    }
    NARRATOR = narrator;
    GENDERS = genders || GENDERS;
    S = await loadStrings(['labels_help', 'labels_suggested', 'label_add', 'label_text', 'label_unknownvoice',
        'label_female', 'label_male', 'label_youlearner', 'label_remove', 'labels_save', 'labels_dialog', 'label_auto_is',
        'label_voice', 'label_voice_narrator', 'label_voices_female', 'label_voices_male', 'label_voice_same', 'labels_scenario',
        'labels_added']);
    root.querySelectorAll('[data-action="labels"]').forEach((btn) => btn.addEventListener('click', () => open(btn)));
};
