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
 * The AI Soft Skills scene player.
 *
 * Each scene shows one workplace moment and two responses. The learner picks one; Moodle marks it (the browser never
 * knows which response is better) and a consequence popup shows a workplace indicator gauge moving up or down.
 *
 * @module     mod_aisoftskills/player
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import * as Sound from 'mod_aisoftskills/sound';
import {loadStrings, fmt, render, confetti, REDUCED} from 'mod_aisoftskills/ui';

const KEYS = ['headline_best', 'headline_bestretry', 'headline_poor', 'scenecounter', 'rating_excellent', 'rating_strong',
    'rating_developing', 'rating_beginning', 'levelline_excellent', 'levelline_strong', 'levelline_developing',
    'levelline_beginning', 'bestfirstchoices', 'attemptsleft', 'announce_best', 'announce_poor', 'nextscene', 'seeresults'];

let S = {};

/**
 * Sets a gauge (semicircle) to a value 0-100.
 *
 * @param {HTMLElement} gauge
 * @param {number} value
 */
const setGauge = (gauge, value) => {
    const v = Math.max(0, Math.min(100, value));
    gauge.querySelector('.ss-gauge-fill').style.strokeDashoffset = String(100 - v);
    gauge.querySelector('.ss-gauge-needle').style.transform = `rotate(${-90 + v * 1.8}deg)`;
    gauge.querySelector('[data-region="gaugevalue"]').textContent = String(Math.round(v));
};

/**
 * Animates a gauge from one value to another.
 *
 * @param {HTMLElement} gauge
 * @param {number} from
 * @param {number} to
 * @returns {Promise}
 */
const animateGauge = (gauge, from, to) => new Promise((resolve) => {
    setGauge(gauge, from);
    if (REDUCED) {
        setGauge(gauge, to);
        resolve();
        return;
    }
    const start = performance.now();
    const duration = 1100;
    const step = (now) => {
        const t = Math.min(1, (now - start - 250) / duration);
        const eased = t <= 0 ? 0 : 1 - Math.pow(1 - t, 3);
        setGauge(gauge, from + (to - from) * eased);
        if (t < 1) {
            requestAnimationFrame(step);
        } else {
            resolve();
        }
    };
    requestAnimationFrame(step);
});

class Player {
    /**
     * Constructor.
     *
     * @param {HTMLElement} root
     */
    constructor(root) {
        this.root = root;
        this.config = JSON.parse(root.dataset.config || '{}');
        this.home = root.querySelector('[data-region="home"]');
        this.host = root.querySelector('[data-region="player"]');
        this.live = root.querySelector('[data-region="live"]');
        this.busy = false;
        Sound.setAllowed(!!this.config.sounds);
    }

    /**
     * Announces text to assistive technologies.
     *
     * @param {string} text
     */
    say(text) {
        this.live.textContent = '';
        window.setTimeout(() => {
            this.live.textContent = text;
        }, 50);
    }

    /**
     * Starts or resumes an attempt.
     */
    async start() {
        try {
            this.data = await Ajax.call([{methodname: 'mod_aisoftskills_start_attempt',
                args: {cmid: this.config.cmid}}])[0];
        } catch (err) {
            Notification.exception(err);
            return;
        }
        const total = this.data.scenes.length;
        const shell = await render('player_shell', {
            level: this.config.level, levelname: this.config.levelname, total,
            dots: this.data.scenes.map((s) => ({number: s.number})), kpis: this.data.kpis,
        });
        this.host.replaceChildren(shell);
        this.shell = shell;
        this.stage = shell.querySelector('[data-region="stage"]');
        this.nextBtn = shell.querySelector('[data-action="next"]');
        this.nextBtn.addEventListener('click', () => this.advance());
        shell.querySelector('[data-action="home"]').addEventListener('click', () => window.location.reload());
        const soundBtn = shell.querySelector('[data-action="sound"]');
        soundBtn.hidden = !this.config.sounds;
        soundBtn.setAttribute('aria-pressed', Sound.isMuted() ? 'false' : 'true');
        soundBtn.classList.toggle('is-muted', Sound.isMuted());
        soundBtn.addEventListener('click', () => {
            const muted = Sound.toggleMute();
            soundBtn.setAttribute('aria-pressed', muted ? 'false' : 'true');
            soundBtn.classList.toggle('is-muted', muted);
        });
        this.home.hidden = true;
        this.host.hidden = false;
        this.data.scenes.forEach((s, i) => this.markDot(i));
        const next = this.data.scenes.findIndex((s) => !s.resolved);
        if (next < 0) {
            await this.finish();
        } else {
            await this.showScene(next);
        }
    }

    /**
     * Updates a progress dot.
     *
     * @param {number} index
     */
    markDot(index) {
        const dot = this.shell.querySelectorAll('.ss-dot')[index];
        const scene = this.data.scenes[index];
        dot.classList.toggle('is-done', !!scene.resolved);
        dot.classList.toggle('is-current', index === this.index);
    }

    /**
     * Shows a scene.
     *
     * @param {number} index
     */
    async showScene(index) {
        this.index = index;
        const scene = this.data.scenes[index];
        const node = await render('player_scene', Object.assign({}, scene, {total: this.data.scenes.length}));
        this.stage.replaceChildren(node);
        this.data.scenes.forEach((s, i) => this.markDot(i));
        this.shell.querySelector('[data-region="count"]').textContent =
            fmt(S.scenecounter, {number: scene.number, total: this.data.scenes.length});
        this.nextBtn.disabled = !scene.resolved;
        node.querySelectorAll('.ss-option').forEach((btn) => {
            if (Number(btn.dataset.optionid) === scene.tried) {
                btn.classList.add('is-tried');
                btn.disabled = true;
            }
            btn.addEventListener('click', () => this.choose(scene, btn));
        });
        if (!REDUCED) {
            node.animate([{opacity: 0, transform: 'translateX(24px)'}, {opacity: 1, transform: 'none'}],
                {duration: 380, easing: 'cubic-bezier(.22,1,.36,1)'});
        }
        Sound.play('slide');
        node.querySelector('.ss-scene-title').setAttribute('tabindex', '-1');
        node.querySelector('.ss-scene-title').focus({preventScroll: false});
    }

    /**
     * Sends the learner's choice and shows its consequence.
     *
     * @param {object} scene
     * @param {HTMLButtonElement} btn
     */
    async choose(scene, btn) {
        if (this.busy || btn.disabled) {
            return;
        }
        this.busy = true;
        const options = this.stage.querySelectorAll('.ss-option');
        options.forEach((b) => {
            b.disabled = true;
        });
        btn.classList.add('is-chosen');
        Sound.play('select');
        let res;
        try {
            res = await Ajax.call([{methodname: 'mod_aisoftskills_choose_option', args: {
                attemptid: this.data.attemptid, sceneid: scene.sceneid, optionid: Number(btn.dataset.optionid)}}])[0];
        } catch (err) {
            this.busy = false;
            options.forEach((b) => {
                b.disabled = b.classList.contains('is-tried');
            });
            btn.classList.remove('is-chosen');
            Notification.exception(err);
            return;
        }
        btn.classList.add(res.best ? 'is-best' : 'is-poor');
        scene.resolved = res.resolved;
        scene.answered = 1;
        if (!res.best && res.canretry) {
            scene.tried = Number(btn.dataset.optionid);
        }
        this.updateKpi(res.kpi, res.kpiname, res.after);
        this.markDot(this.index);
        await this.showConsequence(scene, btn, res);
        this.busy = false;
    }

    /**
     * Updates the indicator strip.
     *
     * @param {string} kpi
     * @param {string} name
     * @param {number} value
     */
    updateKpi(kpi, name, value) {
        const strip = this.shell.querySelector('[data-region="kpis"]');
        let chip = strip.querySelector(`[data-kpi="${kpi}"]`);
        if (!chip) {
            chip = document.createElement('div');
            chip.className = 'ss-kpichip';
            chip.dataset.kpi = kpi;
            chip.setAttribute('role', 'meter');
            chip.setAttribute('aria-valuemin', '0');
            chip.setAttribute('aria-valuemax', '100');
            chip.setAttribute('aria-label', name);
            const label = document.createElement('span');
            label.className = 'ss-kpichip-name';
            label.textContent = name;
            const bar = document.createElement('span');
            bar.className = 'ss-kpichip-bar';
            bar.appendChild(document.createElement('span'));
            const val = document.createElement('span');
            val.className = 'ss-kpichip-value';
            chip.append(label, bar, val);
            strip.appendChild(chip);
        }
        chip.setAttribute('aria-valuenow', String(value));
        chip.querySelector('.ss-kpichip-bar span').style.width = `${value}%`;
        chip.querySelector('.ss-kpichip-value').textContent = String(value);
        if (!REDUCED) {
            chip.animate([{transform: 'scale(1.08)'}, {transform: 'scale(1)'}], {duration: 500});
        }
    }

    /**
     * Shows the consequence popup with the indicator gauge.
     *
     * @param {object} scene
     * @param {HTMLButtonElement} btn
     * @param {object} res choose_option result
     */
    async showConsequence(scene, btn, res) {
        const last = res.allresolved && res.resolved;
        let headline = S.headline_poor;
        if (res.best) {
            headline = res.first ? S.headline_best : S.headline_bestretry;
        }
        const delta = res.after - res.before;
        const popup = await render('consequence', {
            best: !!res.best, headline, kpiname: res.kpiname, before: res.before, after: res.after,
            deltatext: delta >= 0 ? `+${delta}` : `−${Math.abs(delta)}`, up: delta >= 0,
            consequence: res.consequence, reason: res.reason, better: res.better, betterreason: res.betterreason,
            canretry: !!res.canretry, last,
        });
        this.shell.appendChild(popup);
        const gauge = popup.querySelector('[data-region="gauge"]');
        setGauge(gauge, res.before);
        const primary = popup.querySelector('[data-action="retry"], [data-action="continue"]');
        primary.focus();
        this.say(`${headline} ${fmt(res.best ? S.announce_best : S.announce_poor, {name: res.kpiname, value: res.after})}`);
        if (res.best) {
            Sound.play('correct');
            window.setTimeout(() => Sound.play('mastered'), 350);
            confetti(popup, res.first ? 140 : 60);
        } else {
            Sound.play('wrong');
        }
        animateGauge(gauge, res.before, res.after);
        const close = () => {
            popup.remove();
            document.removeEventListener('keydown', onkey);
        };
        const onkey = (e) => {
            if (e.key === 'Escape') {
                primary.click();
            } else if (e.key === 'Tab') {
                // Keep focus inside the popup.
                e.preventDefault();
                primary.focus();
            }
        };
        document.addEventListener('keydown', onkey);
        const retry = popup.querySelector('[data-action="retry"]');
        if (retry) {
            retry.addEventListener('click', () => {
                close();
                btn.classList.remove('is-chosen', 'is-poor');
                btn.classList.add('is-tried');
                this.stage.querySelectorAll('.ss-option').forEach((b) => {
                    b.disabled = b.classList.contains('is-tried');
                });
                const other = this.stage.querySelector('.ss-option:not(.is-tried)');
                if (other) {
                    other.focus();
                }
            });
        }
        const cont = popup.querySelector('[data-action="continue"]');
        if (cont) {
            cont.addEventListener('click', () => {
                close();
                this.nextBtn.disabled = false;
                this.advance();
            });
        }
    }

    /**
     * Moves to the next unresolved scene, or finishes.
     */
    async advance() {
        const next = this.data.scenes.findIndex((s, i) => i > this.index && !s.resolved);
        const any = next >= 0 ? next : this.data.scenes.findIndex((s) => !s.resolved);
        if (any >= 0) {
            await this.showScene(any);
        } else {
            await this.finish();
        }
    }

    /**
     * Finishes the attempt and shows the results.
     */
    async finish() {
        let res;
        try {
            res = await Ajax.call([{methodname: 'mod_aisoftskills_finish_attempt',
                args: {attemptid: this.data.attemptid}}])[0];
        } catch (err) {
            Notification.exception(err);
            return;
        }
        const tone = {excellent: 'great', strong: 'good', developing: 'ok', beginning: 'bad'}[res.rating] || 'ok';
        const node = await render('summary', {
            score: res.score, tone,
            headline: S['rating_' + res.rating],
            message: fmt(S.bestfirstchoices, {best: res.best, total: res.total}),
            levelline: fmt(S['levelline_' + res.rating], this.config.levelname),
            kpis: res.kpis.map((k) => {
                const change = k.value - k.start;
                return Object.assign({}, k, {up: change >= 0, change: change >= 0 ? `+${change}` : `−${Math.abs(change)}`});
            }),
            takeaways: res.takeaways,
            hastakeaways: res.takeaways.length > 0,
            canretake: !!res.canretake,
            attemptsleft: res.attemptsleft >= 0 ? fmt(S.attemptsleft, res.attemptsleft) : '',
        });
        this.stage.replaceChildren(node);
        this.shell.querySelector('.ss-footer').hidden = true;
        this.shell.querySelector('[data-region="kpis"]').hidden = true;
        node.focus();
        Sound.play('finish');
        if (res.score >= 70) {
            confetti(this.shell, 200);
        }
        const retake = node.querySelector('[data-action="retake"]');
        if (retake) {
            retake.addEventListener('click', () => this.start());
        }
        node.querySelector('[data-action="home"]').addEventListener('click', () => window.location.reload());
        this.say(`${S['rating_' + res.rating]}. ${res.score}%`);
    }
}

/**
 * Sets up the player.
 *
 * @param {string} selector
 */
export const init = async(selector) => {
    const root = document.querySelector(selector);
    if (!root) {
        return;
    }
    S = await loadStrings(KEYS);
    const player = new Player(root);
    const start = root.querySelector('[data-action="start"]');
    if (start) {
        start.addEventListener('click', () => player.start());
    }
    root.dataset.ssReady = '1';
};
