<?php


///////////////////////////////////////////////////////////////////////////
//                                                                       //
// This file is part of Moodle - http://moodle.org/                      //
// Moodle - Modular Object-Oriented Dynamic Learning Environment         //
//                                                                       //
// Moodle is free software: you can redistribute it and/or modify        //
// it under the terms of the GNU General Public License as published by  //
// the Free Software Foundation, either version 3 of the License, or     //
// (at your option) any later version.                                   //
//                                                                       //
// Moodle is distributed in the hope that it will be useful,             //
// but WITHOUT ANY WARRANTY; without even the implied warranty of        //
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the         //
// GNU General Public License for more details.                          //
//                                                                       //
// You should have received a copy of the GNU General Public License     //
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.       //
//                                                                       //
///////////////////////////////////////////////////////////////////////////

/**
 * Account lookup torm for EnglishCentral Activity
 *
 * @package    mod_englishcentral
 * @author     Justin Hunt <poodllsupport@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 1999 onwards Justin Hunt  http://poodll.com
 */

namespace mod_englishcentral;

require_once($CFG->dirroot.'/lib/formslib.php');

/**
 * Account lookup form.
 *
 * @abstract
 * @copyright  2019 Justin Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lookupform extends \moodleform {

    /**
     * Add the required basic elements to the form.
     *
     */
    public final function definition() {
        $mform = $this->_form;
        $users = $this->_customdata['users'];

        // set up list of users (userid => fullname)
        foreach ($users as $userid => $user) {
            $users[$userid] = fullname($user);
        }

        $name = 'id';
        $value = optional_param($name, 0, PARAM_INT);
        $mform->addElement('hidden', $name, $value);
        $mform->setType($name, PARAM_INT);

        $name = 'userid';
        $label = get_string('fullnameuser');
        $mform->addElement('autocomplete', $name, $label, $users);
        $mform->addRule($name, null, 'required', null, 'client');
        $mform->setType($name, PARAM_INT);

        //add the action buttons
        $this->add_action_buttons(false, get_string('search'));
    }

    public final function definition_after_data() {
        parent::definition_after_data();
    }

    /**
     * A function that gets called upon init of this object by the calling script.
     *
     * This can be used to process an immediate action if required. Currently it
     * is only used in special cases by non-standard item types.
     *
     * @return bool
     */
    public function construction_override($itemid,  $pchat) {
        return true;
    }
}