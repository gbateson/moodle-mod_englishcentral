<?php

namespace mod_englishcentral;

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
 * Setup Form for englishcentral Activity
 *
 * @package    mod_englishcentral
 * @author     Justin Hunt <poodllsupport@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 1999 onwards Justin Hunt  http://poodll.com
 */

//why do we need to include this?
require_once($CFG->libdir . '/formslib.php');

use \mod_englishcentral\constants;
use \mod_englishcentral\utils;

/**
 * Abstract class that item type's inherit from.
 *
 * This is the abstract class that add item type forms must extend.
 *
 * @abstract
 * @copyright  2021 Justin Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setupform extends \moodleform {

    /**
     * This is used to identify this itemtype.
     * @var string
     */
    public $type;

    /**
     * The simple string that describes the item type e.g. audioitem, textitem
     * @var string
     */
    public $typestring;

	
    /**
     * An array of options used in the htmleditor
     * @var array
     */
    protected $editoroptions = array(); // ['noclean' => true]

	/**
     * An array of options used in the filemanager
     * @var array
     */
    protected $filemanageroptions = array();

    /**
     * An array of options used in the filemanager
     * @var array
     */
    protected $moduleinstance = null;


    /**
     * Add the required basic elements to the form.
     *
     * This method adds the basic elements to the form including title and contents
     * and then calls custom_definition();
     */
    public final function definition() {
        $mform = $this->_form;

        // Unpack the customdata.
        $context = $this->_customdata['context'];
        $instance = $this->_customdata['instance'];
        $cm = $this->_customdata['cm'];
        $course = $this->_customdata['course'];

        utils::add_mform_elements($mform, $instance, $cm, $course, $context, true);

        $this->add_action_buttons(
            get_string('cancel'),
            get_string('savechangesanddisplay')
        );
    }

    protected final function add_media_upload($name, $count=-1, $label=null, $required=false) {
		if ($count > -1) {
			$name = $name.$count;
		}
		$this->_form->addElement('filemanager', $name, $label, null, $this->filemanageroptions);
	}

	protected final function add_media_prompt_upload($label = null, $required = false) {
		return $this->add_media_upload(constants::AUDIOPROMPT,-1, $label, $required);
	}

    /**
     * Helper function to add a response editor
     *
     * @param int $count The count of the element to add
     * @param string $label, (optional, default=null)
     * @param bool $required whether or no the element is required
     * @return void, but will add an editor element to $mform
     */
    protected final function add_editorarearesponse($count, $label = null, $required = false) {
        $name = constants::TEXTANSWER.$count.'_editor';
        if ($label === null) {
            $label = get_string('response', constants::M_COMPONENT);
        }
        $options = array('rows' => '4', 'columns' => '80');
        $this->_form->addElement('editor', $name, $label, $options, $this->editoroptions);
        $this->_form->setDefault($name, array('text'=>'', 'format' => FORMAT_MOODLE));
        if ($required) {
            $this->_form->addRule($name, get_string('required'), 'required', null, 'client');
        }
    }


}