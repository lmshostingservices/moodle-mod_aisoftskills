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
 * Site settings for mod_aisoftskills.
 *
 * @package    mod_aisoftskills
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$ADMIN->add('modsettings', new admin_externalpage(
    'mod_aisoftskills_activation',
    get_string('activation', 'mod_aisoftskills'),
    new moodle_url('/mod/aisoftskills/activation.php'),
    'moodle/site:config'
));

use mod_aisoftskills\local\catalogue;

if ($ADMIN->fulltree) {
    $component = 'mod_aisoftskills';

    $unlockstate = \mod_aisoftskills\local\unlock::state();
    $settings->add(new admin_setting_heading(
        "$component/activationheading",
        get_string('activation', $component),
        html_writer::div(
            s(get_string('act_settings_status', $component, get_string('act_status_' .
                $unlockstate['status'], $component))) . ' ' .
            html_writer::link(
                new moodle_url('/mod/aisoftskills/activation.php'),
                get_string('act_settings_link', $component)
            ),
            $unlockstate['status'] === 'unlocked' ? 'alert alert-success' : 'alert alert-warning'
        )
    ));

    $settings->add(new admin_setting_heading(
        "$component/aiheading",
        get_string('settings_ai', $component),
        get_string('settings_ai_desc', $component) . '<p><strong>' .
            get_string(\mod_aisoftskills\local\credentials::status(), $component) . '</strong></p>'
    ));
    $settings->add(new admin_setting_configcheckbox(
        "$component/aidrafts",
        get_string('aidrafts', $component),
        get_string('aidrafts_desc', $component),
        1
    ));
    $settings->add(new admin_setting_configcheckbox(
        "$component/aiimages",
        get_string('aiimages', $component),
        get_string('aiimages_desc', $component),
        1
    ));
    $settings->add(new admin_setting_configtext(
        "$component/lmslabssiteid",
        get_string('lmslabssiteid', $component),
        get_string('lmslabssiteid_desc', $component),
        '',
        PARAM_ALPHANUMEXT
    ));
    $settings->add(new admin_setting_configpasswordunmask(
        "$component/lmslabsapikey",
        get_string('lmslabsapikey', $component),
        get_string('lmslabsapikey_desc', $component),
        ''
    ));
    $settings->add(new admin_setting_configtext(
        "$component/airate",
        get_string('airate', $component),
        get_string('airate_desc', $component),
        30,
        PARAM_INT
    ));

    $settings->add(new admin_setting_heading(
        "$component/defaultsheading",
        get_string('settings_defaults', $component),
        ''
    ));
    $settings->add(new admin_setting_configselect(
        "$component/defaultindustry",
        get_string('industry', $component),
        '',
        'office',
        catalogue::industry_options()
    ));
    $settings->add(new admin_setting_configselect(
        "$component/defaultcontentlang",
        get_string('contentlang', $component),
        '',
        'en',
        catalogue::language_options()
    ));
    $settings->add(new admin_setting_configcheckbox(
        "$component/defaultsounds",
        get_string('sounds', $component),
        '',
        1
    ));
}
