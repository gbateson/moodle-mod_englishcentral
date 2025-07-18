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
 * @copyright  2014 Justin Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_englishcentral;

defined('MOODLE_INTERNAL') || die();

/**
 * Authentication class to access EnglishCentral API
 * originally used OAuth, modified to use JWT
 *
 *
 * @package    englishcentral
 * @copyright  2014 Justin Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class auth {

    // Accepted media types used by EC's API.
    const ACCEPT_V1 = 'application/vnd.englishcentral-v1+json,application/json;q=0.9,*/*;q=0.8';
    const ACCEPT_V2 = 'application/vnd.englishcentral-v2+json,application/json;q=0.9,*/*;q=0.8';
    const ACCEPT_V3 = 'application/vnd.englishcentral-v3+json,application/json;q=0.9,*/*;q=0.8';
    const ACCEPT_V4 = 'application/vnd.englishcentral-v4+json,application/json;q=0.9,*/*;q=0.8';

    // EC constants for EC chatBotId's
    const CHAT_BOT_ID_DEFAULT = 1;
    const CHAT_BOT_ID_GENERIC = 3;
    const CHAT_BOT_ID_DIALOG_MANAGER = 4;
    const CHAT_BOT_ID_DQ = 5;
    const CHAT_BOT_ID_LT_2 = 6;
    const CHAT_BOT_ID_ROLEPLAY = 7;

    // EC constants for EC activityType's
    const ACTIVITYTYPE_WATCH = 9; // watchActivity
    const ACTIVITYTYPE_LEARN = 10; // learnActivity
    const ACTIVITYTYPE_SPEAK = 11; // speakActivity
    const ACTIVITYTYPE_CHAT = 55; // discussionActivity
    const ACTIVITYTYPE_WATCHCOMPREHENSIONCHOICE = 40;

    const SDK_MODE_PRODUCTION   = 0;
    const SDK_MODE_DEVELOPMENT  = 1;

    protected $ec = null; // EC activity
    protected $jwt_token = null; // JWT token
    protected $sdk_token = null; // SDK token
    protected $authorization = null; // HTTP header

    protected $uniqueid = null; // user's unique ID on this Moodle site
    protected $accountid = null; // the EC accountid of the current user

    public $poodllapiuser = null; // Poodll API user

    public $poodllapisecret = null; // Poodll API secret

    public $partnerid = null; // EC partner ID   

    public $consumerkey = null; // EC consumer key

    public $consumersecret = null; // EC consumer secret

    public $encryptedsecret = null; // EC encrypted secret

    public $mimichat = null; // EC chatmode

    public $domain = null; // EnglishCentral API endpoint domain



    /**
     * construct English Central object
     */
    public function __construct($ec) {

        if (empty($ec->config)) {
            $this->config = new \stdClass();
        }
        $this->ec = $ec;


        // Specify names of EC config fields.
        $fields = array('poodllapiuser',
                        'poodllapisecret',
                        'partnerid',
                        'consumerkey',
                        'consumersecret',
                        'encryptedsecret',
                        'mimichat');

        // Fetch the Cloud Poodll token (which contains EC credentials).
        if (empty($ec->config->poodllapiuser) || empty($ec->config->poodllapisecret)) {
            $tokenobject = false;
        } else {
            $tokenobject = cloudpoodllauth::fetch_token($ec->config->poodllapiuser, $ec->config->poodllapisecret);
        }

        foreach ($fields as $field) {
            if (empty($ec->config->$field)) {
                $this->$field = '';
                // If the Cloud Poodll token is available, use it to set this $field value.
                if ($tokenobject) {
                    $tokendata = cloudpoodllauth::fetch_token_customproperty($tokenobject, "mod_englishcentral_$field");
                    if ($tokendata && !empty($tokendata)) {
                        $this->$field = $tokendata;
                    }
                }
            } else {
                // A value for this $field already exists, so use that.
                $this->$field = $ec->config->$field;
            }
        }

        //set mimi chat enabled or disabled
        $this->set_mimichat($ec);

        if ($this->get_sdk_mode() == self::SDK_MODE_DEVELOPMENT) {
            $this->domain = 'qaenglishcentral.com';
        } else {
            $this->domain = 'englishcentral.com';
        }
    }

    /*
     * Detect if mimichat is enabled.
     *
     * @return boolean TRUE if mimichat is enabled; otherwise FALSE.
     */
    public function mimichat_enabled() {
        return ($this->mimichat ? true : false);
    }

    /**
     * Set chatmode to enabled or disabled.
     *
     * @param object $ec an EC activity
     * @return boolean TRUE if the mimichat property was set; otherwise FALSE.
     */
    public function set_mimichat($ec) {

        // Default value is false (i.e. not available/enabled).
        $this->mimichat = false;

        // Check that chatmode is enabled in the EC plugin settings for this Moodle site.
        if (empty($ec->config->chatmode)) {
            return true;
        }

        // Normal Moodle users are verified via Poodll API
        if ($apiuser = $ec->config->poodllapiuser) {
            if ($apisecret = $ec->config->poodllapisecret) {
                $token = cloudpoodllauth::fetch_token($apiuser, $apisecret);
                $field = 'mod_englishcentral_mimichat';
                $value = cloudpoodllauth::fetch_token_customproperty($token, $field);
                $this->mimichat = ($value === 'enabled');
                return true;
            }
        }

        // Developers may be registered directly with EC using a partnerid.
        if ($partnerid = $ec->config->partnerid) {
            if ($consumerkey = $ec->config->consumerkey) {
                if ($consumersecret = $ec->config->consumersecret) {
                    if ($encryptedsecret = $ec->config->encryptedsecret) {
                        // Verify if mimichat is enabled for this partnerid.
                        $value = '1';
                        $this->mimichat = ($value === '1');
                    }
                }
            }
        }

        return false;
    }

    /**
     * Creates a new EnglishCentral auth object
     *
     * @param object $ec an EC activity
     * @return object the new EC auth object
     */
    static public function create($ec) {
        return new auth($ec);
    }

    public function get_uniqueid() {
        global $DB, $USER;
        if ($this->uniqueid === null) {
            $table = 'englishcentral_accountids';
            $params = array('userid' => $USER->id);
            $this->uniqueid = $DB->get_field($table, 'id', $params);
            if (empty($this->uniqueid)) {
                $record = (object)array('userid' => $USER->id,
                                        'accountid' => 0);
                $this->uniqueid = ''.$DB->insert_record($table, $record);
                // we need the quotes, '', to convert the id to a string
            }
            // NOTE: it does not seem to be necessary to create a permanent
            // EC accountid. Everything works without doing so.
            // However, in the future, we may offer Moodle students the
            // chance to assume control of their anonymous EC accountid
            $this->get_accountid();
        }
        return $this->uniqueid;
    }

    public function get_accountid() {
        global $DB, $USER;
        if ($this->accountid === null) {
            $table = 'englishcentral_accountids';
            $params = array('userid' => $USER->id);
            $this->accountid = $DB->get_field($table, 'accountid', $params);
            if (empty($this->accountid)) {
                $this->accountid = $this->create_accountid();
                $DB->set_field($table, 'accountid', $this->accountid, $params);
            } else {
                // next line is not necessary, because we already know accountID
                // $this->accountid = $this->fetch_accountid();
            }
        }
        return $this->accountid;
    }

    public function get_jwt_token() {
        if ($this->jwt_token === null) {
            $payload = array('userID' => $this->get_uniqueid(),
                             'consumerKey' => $this->consumerkey,
                             'exp' => round((microtime(true) + 10000) * 1000));
            $secret = \mod_englishcentral\jwt\JWT::urlsafeB64Decode($this->encryptedsecret);
            $this->jwt_token = \mod_englishcentral\jwt\JWT::encode($payload, $secret);
        }
        return $this->jwt_token;
    }

    public function set_sdk_token($sdk_token) {
        $this->sdk_token = $sdk_token;
    }

    public function get_sdk_token() {
        if ($this->sdk_token === null) {
            $url = $this->get_url('bridge', 'rest/identity/authorize');

            $fields = array('partnerID' => $this->partnerid,
                            'siteLanguage' => $this->get_site_language(),
                            'nativeLanguage' => $this->get_user_language(),
                            'applicationBuildDate' => '2017-08-19T13:33:14.000Z');
            $fields = http_build_query($fields, '', '&', PHP_QUERY_RFC1738);

            $header = array('Accept: ' . self::ACCEPT_V1,
                            'AuthorizeRequest: ' . $this->get_jwt_token(),
                            'Content-Length: ' . strlen($fields),
                            'Content-Type: application/x-www-form-urlencoded');

            $this->sdk_token = $this->doCurl($url, $header, false, true, $fields);
        }
        return $this->sdk_token;
    }

    public function get_sdk_version() {
        return get_config('mod_englishcentral', 'playerversion');
    }

    public function get_sdk_mode() {
        if (get_config('mod_englishcentral', 'developmentmode')) {
            return self::SDK_MODE_DEVELOPMENT;
        } else {
            return self::SDK_MODE_PRODUCTION;
        }
    }

    public function get_header($accept) {
        return array('Accept: ' . $accept,
                     'Authorization: ' . $this->get_authorization(),
                     'Content-Type: application/x-www-form-urlencoded');
    }

    public function get_authorization() {
        if ($this->authorization === null) {
            if ($sdk_token = $this->get_sdk_token()) {
                $consumersecret = \mod_englishcentral\jwt\JWT::urlsafeB64Decode($this->encryptedsecret);
                $payload = \mod_englishcentral\jwt\JWT::decode($sdk_token, $consumersecret, array('HS256'));
                $payload = array('accessToken' => $payload->accessToken,
                                 'consumerKey' => $this->consumerkey);
                $this->authorization = 'JWT '.\mod_englishcentral\jwt\JWT::encode($payload, $consumersecret);
            }
        }
        return $this->authorization;
    }

    public function get_player_settings() {
        return (object)array(
            'chatMode' => $this->mimichat_enabled()
        );
    }

    public function create_accountid() {
        if (has_capability('mod/englishcentral:manage', $this->ec->context)) {
            $isTeacher = 1;
        } else {
            $isTeacher = 0;
        }
        $subdomain = 'bridge';
        $endpoint = 'rest/identity/account';
        $fields = array('partnerID' => $this->partnerid,
                        'partnerAccountID' => $this->get_uniqueid(),
                        'nativeLanguage' => $this->get_user_language(),
                        'siteLanguage' => $this->get_site_language(),
                        'isTeacher' => $isTeacher,
                        'timezone' => \core_date::get_user_timezone(),
                        'fields' => 'accountID');
        $response = $this->doPost($subdomain, $endpoint, $fields, self::ACCEPT_V1);
        return $this->return_value($response, 'accountID', 0);
    }

    public function fetch_accountid() {
        $subdomain = 'bridge';
        $endpoint = 'rest/identity/account';
        $fields = array('partnerID' => $this->partnerid,
                        'partnerAccountID' => $this->get_uniqueid(),
                        'fields' => 'accountID');
        $response = $this->doPost($subdomain, $endpoint, $fields, self::ACCEPT_V1);
        return $this->return_value($response, 'accountID', 0);
    }

    public function fetch_goal_list() {
        $subdomain = 'bridge';
        $endpoint = 'rest/content/goal';
        $fields = array();
        return $this->doGet($subdomain, $endpoint, $fields, self::ACCEPT_V1);
    }

    public function fetch_course_list($goalid,$difficulty) {
        $subdomain = 'bridge';
        $endpoint = 'rest/content/course';
        $fields = array('goalID'=>$goalid,'difficulty'=>$difficulty,'pageSize'=>25,'fields'=>'courseID,name,description,difficulty');
        return $this->doGet($subdomain, $endpoint, $fields, self::ACCEPT_V1);
    }

    public function fetch_dialog_list($videoids) {
        global $DB;

        $subdomain = 'bridge';
        $endpoint = 'rest/content/dialog';
        $fields = array('dialogIDs' => implode(',', $videoids),
                        'siteLanguage' => $this->get_site_language(),
                        'fields' => 'dialogID,title,difficulty,duration,dialogURL,thumbnailURL,videoDetailsURL,demoPictureURL,description,topics');
        $dialoglist = $this->doGet($subdomain, $endpoint, $fields, self::ACCEPT_V1);

        // Cache the dialogs listings for later use in reports, and here eventually
        // Ideally we should do this when add a video. TO DO do that
        list($dialogswhere, $allparams) = $DB->get_in_or_equal($videoids);
        $sql = "SELECT * FROM {" . constants::M_VIDEOSTABLE . "} vt ";
        $sql .= "WHERE videoid " . $dialogswhere;
        $cachedvideoslist = $DB->get_records_sql($sql, $allparams);
        foreach ($cachedvideoslist as $cachedvideo) {
            if ($cachedvideo->detailsjson === null) {
                foreach($dialoglist as $thedialog){
                    if($thedialog->dialogID == $cachedvideo->videoid){
                        $DB->update_record(constants::M_VIDEOSTABLE,
                        ['id' => $cachedvideo->id, 'name' => $thedialog->title,
                                        'detailsjson' => json_encode($thedialog)]);
                    }
                }
            }
        }
        return $dialoglist;
    }

    public function fetch_course_content($courseid) {
        $subdomain = 'bridge';
        $endpoint = 'rest/content/course/' . $courseid;
        $fields = array('siteLanguage' => $this->get_site_language());
        return $this->doGet($subdomain, $endpoint, $fields, self::ACCEPT_V1);
    }

    public function fetch_dialog_content($videoid) {
        $subdomain = 'bridge';
        $endpoint = "rest/content/dialog/$videoid";
        $fields = array('siteLanguage' => $this->get_site_language());
        return $this->doGet($subdomain, $endpoint, $fields, self::ACCEPT_V1);
    }

    public function fetch_dialog_progress($videoid, $sdk_token='') {
        if ($sdk_token) {
            $this->set_sdk_token($sdk_token);
        }
        $subdomain = 'reportcard';
        $endpoint = "rest/report/dialog/$videoid/progress";
        return $this->doGet($subdomain, $endpoint, array(), self::ACCEPT_V2);
    }

    // This method is not used and does not seem to work, but
    // it could be used to get more info about a conversation.
    // https://chat.englishcentral.com/documentation/resource_ConversationREST.html
    // https://chat.englishcentral.com/rest/conversation/list?accountId=xxx&dialogId=xxx&chatBotId=5
    public function fetch_chat_progress($videoid, $sdk_token='') {
        if ($sdk_token) {
            $this->set_sdk_token($sdk_token);
        }
        $subdomain = 'chat';
        $endpoint = 'rest/conversation/list';
        $fields = array(
            'chatBotId' => self::CHAT_BOT_ID_DQ, // =5
            'accountId' => $this->get_accountid(),
            'dialogId' => $videoid,
        );
        return $this->doGet($subdomain, $endpoint, $fields, self::ACCEPT_V1);
    }

    public function doGet($subdomain, $endpoint, $fields, $accept) {
        $url = $this->get_url($subdomain, $endpoint, $fields);
        $header = $this->get_header($accept);
        return $this->doCurl($url, $header, true, false);
    }

    public function doPost($subdomain, $endpoint, $fields, $accept) {
        $url = $this->get_url($subdomain, $endpoint, $fields);
        $header = $this->get_header($accept);
        return $this->doCurl($url, $header, true, true);
    }

    public function doCurl($url, $header, $json_decode=false, $post=null, $fields=null) {
        global $CFG;

        // Use Moodle Curl to ensure we use a proxy if Moodle server is using one.
        require_once($CFG->libdir.'/filelib.php');
        $curl = new \curl();
        $curl->setHeader($header);
        $curl->setopt(array(
            'CURLOPT_AUTOREFERER' => true,
            'CURLOPT_FOLLOWLOCATION' => true,
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_SSL_VERIFYPEER' => false,
        ));

        if ($post) {
            $response = $curl->post($url, $fields);
        } else {
            $response = $curl->get($url,$fields);
        }

        // If its JSON, process that before returning.
        if ($json_decode && $this->is_json($response)) {
            $response = json_decode($response);
        }
        return $response;
    }

    public function is_json($response) {
        if (substr($response, 0, 1)=='{' && substr($response, -1)=='}') {
            return true;
        }
        if (substr($response, 0, 1)=='[' && substr($response, -1)==']') {
            return true;
        }
        return false;
    }

    public function return_value($response, $name, $default) {
        if (empty($response->$name)) {
            return $default;
        } else {
            return $response->$name;
        }
    }

    public function get_user_language($default='en') {
        global $CFG, $USER;
        if (! empty($USER->lang)) {
            return substr($USER->lang, 0, 2);
        }
        if (! empty($CFG->lang)) {
            return substr($CFG->lang, 0, 2);
        }
        return $default;
    }

    public function get_site_language($default='en') {
        $lang = substr(current_language(), 0, 2);
        $langs = array(
            // Only the following languages are available on the EC site.
            // See language menu on: https://www.englishcentral.com/browse/videos.
            'en', // English    English
            'es', // Spanish    Español
            'ja', // Japanese   日本語
            'ko', // Korean     한국어
            'pt', // Portuguese Português
            'ru', // Russian    Русский
            'tr', // Turkish    Türkçe
            'vi', // Vietnamese Tiếng Việt
            'zh', // Chinese    简体中文
            'he', // Hebrew     עִברִית 
            'ar', // Arabic     عربى 
            'fr', // French     Français
            'th', // Thai       ภาษาไทย (added 2024-06-06)
        );
        if (in_array($lang, $langs)) {
            return $lang;
        }
        return $default;
    }

    public function get_fetch_url() {
        return $this->get_url('bridge', 'rest/content/dialog');
    }

    public function get_search_url() {
        return $this->get_url('bridge', 'rest/content/dialog/search/fulltext');
    }

    public function get_url($subdomain, $endpoint, $fields=array()) {
        $url = "https://$subdomain.$this->domain/$endpoint";
        $url = new \moodle_url($url, $fields);
        return $url->out(false); // join with "&" not "&amp;"
    }

    public function missing_poodllapi_config() {
        $missing = array('poodllapiuser' => '/^[0-9a-zA-Z\/\.@+=_-]+$/',
                         'poodllapisecret' => '/^[0-9a-zA-Z\/+=-]+$/');
        foreach ($missing as $name => $pattern) {
            // The patterns don't match what might actually be in the secret, so commented for now. Justin 20212/01/23
            // if (isset($this->ec->config->$name) && preg_match($pattern, $this->ec->config->$name)) {
            if (isset($this->ec->config->$name) && !empty($this->ec->config->$name)) {
                unset($missing[$name]);
            } else {
                $missing[$name] = $this->ec->get_string($name);
            }
        }
        return (empty($missing) ? '' : $missing);
    }

    public function invalid_poodllapi_config() {
        $apiuser = $this->ec->config->poodllapiuser;
        $apisecret = $this->ec->config->poodllapisecret;
        $token = \mod_englishcentral\cloudpoodllauth::fetch_token($apiuser, $apisecret);
        return \mod_englishcentral\cloudpoodllauth::fetch_token_error($token);
    }

    public function missing_config() {
        $missing = array('partnerid' => '/^[0-9]+$/',
                         'consumerkey' => '/^[0-9a-fA-F]{32}$/',
                         'consumersecret' => '/^[0-9a-fA-F]{64}$/',
                         'encryptedsecret' => '/^[0-9a-zA-Z\/+=]+$/');
        foreach ($missing as $name => $pattern) {
            if ($name=='consumersecret' && $this->ec->config->$name == $this->ec->config->encryptedsecret) {
                unset($missing[$name]);
            } else if (isset($this->ec->config->$name) && preg_match($pattern, $this->ec->config->$name)) {
                unset($missing[$name]);
            } else {
                $missing[$name] = $this->ec->get_string($name);
            }
        }
        return (empty($missing) ? '' : $missing);
    }

    public function invalid_config() {
        $sdk_token = $this->get_sdk_token();
        // The token is usually 189 chars long and split into 3 parts delimited by [\.].
        // Parts 1 & 2 contain [0-9a-zA-Z]. The 3rd part can additionally contain [_-].
        if (preg_match('/^[0-9a-zA-Z\._-]{180,200}$/', $sdk_token)) {
            return ''; // token is valid - YAY!
        }
        if ($this->is_json($sdk_token)) {
            // JSON error message from EC server
            return json_decode($sdk_token)->log;
        }
        if (strpos($sdk_token, '<!DOCTYPE html>') === 0) {
            // HTML error message, maybe a wrong URL or invalid data was sent to EC.
            return preg_replace('/^(.*?<body[^>]*>)|(<\/body>.*$)/', '', $sdk_token);
        }
        // Some other problematic token.
        return $sdk_token;
    }
}