<?php

// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * A token refreshing helper for EnglishCentral
 *
 * @package    mod_englishcentral
 * @copyright  Justin Hunt (justin@poodll.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');

use \mod_englishcentral\cloudpoodllauth;

require_login(0, false);
$systemcontext = context_system::instance();

if (has_capability('moodle/site:config', $systemcontext)) {
    if ($apiuser = get_config(cloudpoodllauth::M_COMPONENT, 'poodllapiuser')) {
        if ($apisecret = get_config(cloudpoodllauth::M_COMPONENT, 'poodllapisecret')) {
            cloudpoodllauth::fetch_token($apiuser, $apisecret, true); // force = TRUE.
        }
    }
}
redirect($CFG->wwwroot.'/admin/settings.php?section=modsettingenglishcentral');