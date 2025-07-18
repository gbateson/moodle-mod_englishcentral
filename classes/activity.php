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
 * Internal library of functions for module English Central
 *
 * All the englishcentral specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package    mod_englishcentral
 * @copyright  2018 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_englishcentral;

defined('MOODLE_INTERNAL') || die();

/**
 * Authentication class to access EnglishCentral API
 * originally used OAuth, modified to use JWT
 *
 *
 * @package    mod_englishcentral
 * @copyright  2018 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity {

    /**
     * @var string The type of the plugin.
     */
    public $plugintype;

    /**
     * @var string The name of the plugin.
     */
    public $pluginname;

    /**
     * @var object The plugin instance.
     */
    public $plugin;

    /**
     * @var object The course module.
     */
    public $cm;

    /**
     * @var object The course instance.
     */
    public $course;

    /**
     * @var object The context instance.
     */
    public $context;

    /**
     * @var int The timestamp.
     */
    public $time;

    /**
     * @var bool Whether the activity is available.
     */
    public $available;

    /**
     * @var bool Whether the activity is viewable.
     */
    public $viewable;

    /**
     * @var array The configuration settings.
     */
    public $config;

    /**
     * @var object The English Central instance.
     */
    public $ecinstance;


    /**
     * construct English Central activity instance
     */
    function __construct($instance=null, $cm=null, $course=null, $context=null) {
        global $COURSE;

        $this->plugintype = 'mod';
        $this->pluginname = 'englishcentral';
        $this->plugin = $this->plugintype.'_'.$this->pluginname;

        if ($instance) {
            $this->ecinstance = $instance;
        }

        if ($cm) {
            $this->cm = $cm;
        }

        if ($course) {
            $this->course = $course;
        } else {
            $this->course = $COURSE;
        }

        if ($context) {
            $this->context = $context;
        } else if ($cm) {
            $this->context = \context_module::instance($cm->id);;
        } else if ($course) {
            $this->context = \context_course::instance($course->id);
        } else {
            $this->context = \context_system::instance();
        }

        $this->time = time();

        if (has_capability('mod/englishcentral:manage', $this->context)) {
            $this->available = true;
        } else if ($this->activityopen && $this->activityopen > $this->time) {
            $this->available = false;
        } else if ($this->activityclose && $this->activityclose < $this->time) {
            $this->available = false;
        } else {
            $this->available = true;
        }

        if (has_capability('mod/englishcentral:manage', $this->context)) {
            $this->viewable = true;
        } else if ($this->videoopen && $this->videoopen > $this->time) {
            $this->viewable = false;
        } else if ($this->videoclose && $this->videoclose < $this->time) {
            $this->viewable = false;
        } else {
            $this->viewable = true;
        }

        $this->config = get_config($this->plugin);
    }

    /**
     * Magic method to get properties.
     *
     * @param string $name The name of the property.
     * @return mixed The value of the property or null if not found.
     */
    public function __get($name) {
        if (property_exists($this, $name)) {
            return $this->$name;
        } else if (property_exists($this->ecinstance, $name)) {
            return $this->ecinstance->$name;
        } else {
            return null;
        }
    }

    /**
     * Creates a new EnglishCentral activity
     *
     * @param stdclass $instance a row from the reader table
     * @param stdclass $cm a row from the course_modules table
     * @param stdclass $course a row from the course table
     * @return reader the new reader object
     */
    public static function create($instance=null, $cm=null, $course=null, $context=null) {
        return new activity($instance, $cm, $course, $context);
    }

    ////////////////////////////////////////////////////////////////////////////////
    // availability API
    ////////////////////////////////////////////////////////////////////////////////

    public function not_available() {
        return ($this->available ? false : true);
    }

    public function not_viewable() {
        return ($this->viewable ? false : true);
    }

    /*
     * Detect if watch goal is set.
     *
     * @return boolean TRUE if watch goal is > 0; otherwise FALSE.
     */
    public function watchgoal_set() {
        return ($this->watchgoal ? true : false);
    }

    /*
     * Detect if learn goal is set.
     *
     * @return boolean TRUE if learn goal is > 0; otherwise FALSE.
     */
    public function learngoal_set() {
        return ($this->learngoal ? true : false);
    }

    /*
     * Detect if speak goal is set.
     *
     * @return boolean TRUE if speak goal is > 0; otherwise FALSE.
     */
    public function speakgoal_set() {
        return ($this->speakgoal ? true : false);
    }

    /*
     * Detect if chat goal is set.
     *
     * @return boolean TRUE if chat goal is > 0; otherwise FALSE.
     */
    public function chatgoal_set() {
        return ($this->chatgoal ? true : false);
    }

    /*
     * Detect if chat mode is enabled for this Moodle site.
     *
     * @return boolean TRUE if chat mode is enabled; otherwise FALSE.
     */
    public function chatmode_enabled() {
        return ($this->config->chatmode ? true : false);
    }

    ////////////////////////////////////////////////////////////////////////////////
    // URLs API
    ////////////////////////////////////////////////////////////////////////////////

    public function get_report_url($escaped=null, $params=[]) {
        return $this->url('reports.php', $escaped, $params);
    }

    public function get_developertools_url($escaped=null, $params=[]) {
        return $this->url('developer.php', $escaped, $params);
    }

    public function get_view_url($escaped=null, $params=[]) {
        return $this->url('view.php', $escaped, $params);
    }

    public function get_viewajax_url($escaped=null, $params=[]) {
        return $this->url('view.ajax.php', $escaped, $params);
    }

    public function get_videoinfo_url($escaped=null) {
        $lang = substr(current_language(), 0, 2);
        switch ($lang) {

            case 'en': // English
                return 'https://www.englishcentral.com/videodetails';

            case 'ar': // Arabic
            case 'es': // Spanish
            case 'he': // Hebrew
            case 'ja': // Japanese
            case 'pt': // Portuguese
            case 'ru': // Russian
            case 'th': // Thai
            case 'tr': // Turkish
            case 'vi': // Vietnamese
                return "https://$lang.englishcentral.com/videodetails";

            case 'zh': // Chinese
                return 'https://www.englishcentralchina.com/videodetails';

            default:
                return 'https://www.englishcentral.com/videodetails';
            // 'https://www.englishcentral.com/videodetails?setLanguage='.$lang;
        }
    }

    public function url($filepath, $escaped=null, $params=[]) {
        if (isset($this->cm)) {
            $params['id'] = $this->cm->id;
        }
        $url = '/'.$this->plugintype.'/'.$this->pluginname.'/'.$filepath;
        $url = new \moodle_url($url, $params);
        if (is_bool($escaped)) {
            $url = $url->out($escaped);
        }
        return $url;
    }

    ////////////////////////////////////////////////////////////////////////////////
    // strings API
    ////////////////////////////////////////////////////////////////////////////////

    public function get_string($name, $a=null) {
        return get_string($name, $this->plugin, $a);
    }

    ////////////////////////////////////////////////////////////////////////////////
    // database API
    ////////////////////////////////////////////////////////////////////////////////

    public function get_videoids() {
        global $DB;
        return $DB->get_records_menu('englishcentral_videos', ['ecid' => $this->id], 'sortorder', 'id,videoid');
    }

    public function get_accountid() {
        global $DB, $USER;
        return $DB->get_field('englishcentral_accountids', 'accountid', ['userid' => $USER->id]);
    }

    public function get_accountids($groupid=0) {
        global $DB;
        $groupid = 0;
        if ($userids = $this->get_userids($groupid)) {
            list($select, $params) = $DB->get_in_or_equal($userids);
            return $DB->get_records_select_menu('englishcentral_accountids', "userid $select", $params, 'userid, accountid');
        }
        return false;
    }

    public function get_userids($groupid=0) {
        global $DB;
        $mode = $this->get_groupmode();
        if ($mode == NOGROUPS || $mode == VISIBLEGROUPS || has_capability('moodle/site:accessallgroups', $this->context)) {
            $users = get_enrolled_users($this->context, 'mod/englishcentral:view', $groupid, 'u.id', 'id');
            if (empty($users)) {
                return false;
            }
            return array_keys($users);
        } else {
            if ($groupid) {
                $select = 'groupid = ?';
                $params = [$groupid];
            } else {
                $groups = groups_get_user_groups($course->id);
                if (empty($groups)) {
                    return false;
                }
                list($select, $params) = $DB->get_in_or_equal($groups['0']);
            }
            $users = $DB->get_records_select_menu('group_members', 'groupid '.$select, $params, 'id, userid');
            if (empty($users)) {
                return false;
            }
            return array_unique($users);
        }
    }

    /*
     * get groupmode (0=NOGROUPS, 1=VISIBLEGROUPS, 2=SEPARATEGROUPS)
     *
     * @return integer, the groupmode of this activity or course
     **/
    public function get_groupmode() {
        if ($this->cm) {
            return groups_get_activity_groupmode($this->cm);
        }
        if ($this->course) {
            return groups_get_course_groupmode($this->course);
        }
        return NOGROUPS;
    }

    public function get_progress() {
        global $DB, $USER;
        $progress = (object)[
        'watch' => 0,
        'learn' => 0,
        'speak' => 0,
        'chat' => 0,
        ];
        $table = 'englishcentral_attempts';
        $params = ['ecid' => $this->id,
                    'userid' => $USER->id];
        if ($attempts = $DB->get_records($table, $params)) {
            foreach ($attempts as $attempt) {
                $progress->watch += $attempt->watchcomplete;
                $progress->learn += $attempt->learncount;
                $progress->speak += $attempt->speakcount;
                $progress->chat += $attempt->chatcount;
            }
        }
        return $progress;
    }

    public function update_progress($dialog) {
        global $DB, $USER;

        // extract/create $attempt
        $table = 'englishcentral_attempts';
        $params = ['ecid' => $this->id,
                    'userid' => $USER->id,
                    'videoid' => $dialog->dialogID];
        if ($attempt = $DB->get_record($table, $params)) {
            // $USER has attempted this video before
        } else {
            $attempt = (object)$params;
            $attempt->timecreated = $this->time;
        }

        $progress = $this->extract_progress($dialog, $attempt);

        foreach ($progress as $name => $value) {
            $attempt->$name = $value;
        }

        if (empty($attempt->id)) {
            $attempt->id = $DB->insert_record($table, $attempt);
        } else {
            $DB->update_record($table, $attempt);
        }

        // trigger progress update event
        $event = \mod_englishcentral\event\progress_updated::create([
        'context' => $this->context,
        'objectid' => $attempt->id,
        'other' => ['ecid' => $this->id],
        ]);
        $event->add_record_snapshot($table, $attempt);
        $event->trigger();

        englishcentral_update_grades($this->ecinstance, $USER->id);
        // Update completion state.
        $completion = new \completion_info($this->course);
        if ($completion->is_enabled($this->cm) && ($this->completiongoals)) {
            $completion->update_state($this->cm, COMPLETION_COMPLETE);
        }
    }

    /**
     * Format data about dialog activities returned from EC ReportCard api
     * e.g. /rest/report/dialog/{dialogID}/progress
     *
     * @param array $dialog JSON data returned from EC REST call
     * @param object $attempt record from "englishcentral_attempts"
     * @return array of $progress data
     */
    public function extract_progress($dialog, $attempt) {

        // initialize totals for goals
        $progress = [
        'dialogID' => $dialog->dialogID,

        'watchcomplete' => 0,
        'watchtotal'    => 0,
        'watchcount'    => 0,
        'watchlineids'  => [], // dialogLineID's of lines watched,

        'learncomplete' => 0,
        'learntotal'    => 0,
        'learncount'    => 0,
        'learnwordids'  => [], // wordHeadID's of words learned,

        'speakcomplete' => 0,
        'speaktotal'    => 0,
        'speakcount'    => 0,
        'speaklineids'  => [], // dialogLineID's of lines spoken,

        'chatcomplete' => 0,
        'chattotal'    => 0,
        'chatcount'    => 0,
        'chatquestionids'  => [], // chatQuestionID's of chat questions discussed,

        'totalpoints'   => 0,

        // this info is no longer available
        'activetime'    => 0,
        'totaltime'     => 0,
        'sessionScore'  => 0,
        'sessionGrade'  => '', // A-F
        ];

        if (isset($dialog->hash)) {
            $progress['hash'] = $dialog->hash;
        }
        if (isset($dialog->totalPoints)) {
            $progress['totalpoints']  = $dialog->totalPoints;
        }

        // populate the $progress array with values earned hitherto
        $names = ['watchlineids', 'learnwordids', 'speaklineids', 'chatquestionids'];
        foreach ($names as $thename) {
            if (isset($attempt->$thename) && $attempt->$thename) {
                $progress[$thename] = explode(',', $attempt->$thename);
                $progress[$thename] = array_fill_keys($progress[$thename], 1);
            }
        }

        //Dialog activities should not be empty, but oddly occasionally it is, so we try to fallback gradefully without killing it for students
        if (!empty($dialog->activities)) {
            foreach ($dialog->activities as $activity) {

                // activityType     : watchActivity / speakActivity
                // activityID       : 208814
                // activityTypeID   : (see below)
                // activityPoints   : 10
                // activityProgress : 1
                // completed        : 1
                // grade            : A (speakActivity only ?)

                // extract DB fields
                switch ($activity->activityTypeID) {

                    case \mod_englishcentral\auth::ACTIVITYTYPE_WATCH: // =9
                    case \mod_englishcentral\auth::ACTIVITYTYPE_WATCHCOMPREHENSIONCHOICE: // =40
                        $progress['watchcomplete'] = (empty($activity->completed) ? 0 : 1);
                        foreach ($activity->watchedDialogLines as $line) {
                            $progress['watchlineids'][$line->dialogLineID] = 1;
                        }
                        break;

                    case \mod_englishcentral\auth::ACTIVITYTYPE_LEARN: // =10
                        $progress['learncomplete'] = (empty($activity->completed) ? 0 : 1);
                        foreach ($activity->learnedDialogLines as $line) {
                            foreach ($line->learnedWords as $word) {
                                if ($word->completed) {
                                    $progress['learnwordids'][$word->wordHeadID] = 1;
                                }
                            }
                        }
                        break;

                    case \mod_englishcentral\auth::ACTIVITYTYPE_SPEAK: // =11
                        $progress['speakcomplete'] = (empty($activity->completed) ? 0 : 1);
                        foreach ($activity->spokenDialogLines as $line) {
                            $progress['speaklineids'][$line->dialogLineID] = 1;
                        }
                        break;

                    case \mod_englishcentral\auth::ACTIVITYTYPE_CHAT: // =55
                        $progress['chatcomplete'] = (empty($activity->completed) ? 0 : 1);
                        foreach ($activity->submittedQuestionIds as $questionid) {
                            $progress['chatquestionids'][$questionid] = 1;
                        }
                        break;
                }
            }
        }

        $progress['watchcount'] += count($progress['watchlineids']);
        $progress['learncount'] += count($progress['learnwordids']);
        $progress['speakcount'] += count($progress['speaklineids']);
        $progress['chatcount'] += count($progress['chatquestionids']);

        $progress['watchlineids'] = implode(',', array_keys($progress['watchlineids']));
        $progress['learnwordids'] = implode(',', array_keys($progress['learnwordids']));
        $progress['speaklineids'] = implode(',', array_keys($progress['speaklineids']));
        $progress['chatquestionids'] = implode(',', array_keys($progress['chatquestionids']));

        return $progress;
    }

    public function get_attempts_fields($addvideoid=true) {
        $fields = 'watchcount,watchcomplete,'.
              'learncount,learncomplete,'.
              'speakcount,speakcomplete,'.
              'chatcount,chatcomplete';
        if ($addvideoid) {
            $fields = "videoid,$fields";
        }
        return $fields;
    }

    public function get_attempts($videoid=0) {
        global $DB, $USER;
        $params = ['ecid' => $this->id,
                    'userid' => $USER->id];
        if ($videoid) {
            $params['videoid'] = $videoid;
        }
        $fields = $this->get_attempts_fields();
        if ($attempts = $DB->get_records('englishcentral_attempts', $params, 'id', $fields)) {
            return $attempts;
        } else {
            return [];
        }
    }
}
