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
 * Essay
 *
 * @package    mod
 * @subpackage lesson
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir.'/weblib.php');
require_once($CFG->dirroot.'/mod/languagelesson/locallib.php');

class languagelesson_page_type_audio extends languagelesson_page {

    protected $type = languagelesson_page::TYPE_QUESTION;
    protected $typeidstring = 'audio';
    protected $typeid = LL_AUDIO;
    protected $string = null;

    public function get_typeid() {
        return $this->typeid;
    }
    public function get_typestring() {
        if ($this->string===null) {
            $this->string = get_string($this->typeidstring, 'languagelesson');
        }
        return $this->string;
    }
    public function get_idstring() {
        return $this->typeidstring;
    }
    public function display($renderer, $attempt) {
        global $PAGE, $CFG, $USER;

        $mform = new languagelesson_display_answer_form_audio($CFG->wwwroot.'/mod/languagelesson/continue.php',
                                            array('contents'=>$this->get_contents(), 'lessonid'=>$this->lesson->id));

        $data = new stdClass;
        $data->id = $PAGE->cm->id;
        $data->pageid = $this->properties->id;
        $pageid = $data->pageid;
        $mform->set_data($data);

        return $mform->display();
    }

    public function create_answers($properties) {
        global $DB;
        // Now add the answers.
        $newanswer = new stdClass;
        $newanswer->lessonid = $this->lesson->id;
        $newanswer->pageid = $this->properties->id;
        $newanswer->timecreated = $this->properties->timecreated;

        if (isset($properties->jumpto[0])) {
            $newanswer->jumpto = $properties->jumpto[0];
        }
        if (isset($properties->score[0])) {
            $newanswer->score = $properties->score[0];
        }
        $newanswer->id = $DB->insert_record("languagelesson_answers", $newanswer);
        $answers = array($newanswer->id => new languagelesson_page_answer($newanswer));
        $this->answers = $answers;
        return $answers;
    }

    public function check_answer() {
        global $PAGE, $CFG, $USER;
        $result = parent::check_answer();
        $result->typeid = $this->typeid;
        $result->score = 0;

        $lessonid = $PAGE->cm->id;

        $mform = new languagelesson_display_answer_form_audio($CFG->wwwroot.'/mod/languagelesson/continue.php',
                                                              array('contents'=>$this->get_contents()));
        $data = $mform->get_data();
        require_sesskey();

        $result->audiodata = $data->audiodata;

        if (!$data) {
            redirect(new moodle_url('/mod/languagelesson/view.php', array('id'=>$PAGE->cm->id, 'pageid'=>$this->properties->id)));
        }

        $answers = $this->get_answers();
        foreach ($answers as $answer) {
            $result->answerid = $answer->id;
            $result->newpageid = $answer->jumpto;
        }

        return $result;
    }
    public function update($properties, $context = null, $maxbytes = null) {
        global $DB, $PAGE;
        $answers  = $this->get_answers();
        $properties->id = $this->properties->id;
        $properties->lessonid = $this->lesson->id;
        $properties = file_postupdate_standard_editor($properties, 'contents',
                    array('noclean'=>true, 'maxfiles'=>EDITOR_UNLIMITED_FILES, 'maxbytes'=>$PAGE->course->maxbytes),
                    context_module::instance($PAGE->cm->id), 'mod_languagelesson', 'page_contents', $properties->id);
        $DB->update_record("languagelesson_pages", $properties);

        if (!array_key_exists(0, $this->answers)) {
            $this->answers[0] = new stdClass;
            $this->answers[0]->lessonid = $this->lesson->id;
            $this->answers[0]->pageid = $this->id;
            $this->answers[0]->timecreated = $this->timecreated;
        }
        if (isset($properties->jumpto[0])) {
            $this->answers[0]->jumpto = $properties->jumpto[0];
        }
        if (isset($properties->score[0])) {
            $this->answers[0]->score = $properties->score[0];
        }
        if (!isset($this->answers[0]->id)) {
            $this->answers[0]->id =  $DB->insert_record("languagelesson_answers", $this->answers[0]);
        } else {
            $DB->update_record("languagelesson_answers", $this->answers[0]->properties());
        }

        // Update maxscore field of page record.
        $maxscore = languagelesson_calc_page_maxscore($this->id);
        $DB->set_field("languagelesson_pages", 'maxscore', $maxscore, array('id'=>$this->id));

        return true;
    }
    public function stats(array &$pagestats, $tries) {
        if (count($tries) > $this->lesson->maxattempts) {
            // If there are more tries than the max that is allowed, grab the last "legal" attempt.
            $temp = $tries[$this->lesson->maxattempts - 1];
        } else {
            // Else, user attempted the question less than the max, so grab the last one.
            $temp = end($tries);
        }

        return true;
    }
    public function report_answers($answerpage, $answerdata, $useranswer, $pagestats, &$i, &$n) {
        $answers = $this->get_answers();

        foreach ($answers as $answer) {
            if ($useranswer != null) {
                if (isset($pagestats[$this->properties->id])) {
                    $percent = $pagestats[$this->properties->id]->totalscore / $pagestats[$this->properties->id]->total * 100;
                    $percent = round($percent, 2);
                    $percent = get_string("averagescore", "languagelesson").": ". $percent ."%";
                } else {
                    // Dont think this should ever be reached....
                    $percent = get_string("nooneansweredthisquestion", "languagelesson");
                }
                // Do we want to repurpose this code for audio?
                /*
                if ($essayinfo->graded) {
                    if ($this->lesson->custom) {
                        $answerdata->score = get_string("pointsearned", "languagelesson").": ".$essayinfo->score;
                    } else if ($essayinfo->score) {
                        $answerdata->score = get_string("receivedcredit", "languagelesson");
                    } else {
                        $answerdata->score = get_string("didnotreceivecredit", "languagelesson");
                    }
                } else {
                    $answerdata->score = get_string("havenotgradedyet", "languagelesson");
                }
                */
            }

            if (isset($pagestats[$this->properties->id])) {
                $avescore = $pagestats[$this->properties->id]->totalscore / $pagestats[$this->properties->id]->total;
                $avescore = round($avescore, 2);
                $avescore = get_string("averagescore", "languagelesson").": ". $avescore;
            } else {
                // Dont think this should ever be reached....
                $avescore = get_string("nooneansweredthisquestion", "languagelesson");
            }
            $answerpage->answerdata = $answerdata;
        }
        return $answerpage;
    }
    public function is_unanswered($nretakes) {
        global $DB, $USER;
        if (!$DB->count_records("languagelesson_attempts",
                                array('pageid'=>$this->properties->id, 'userid'=>$USER->id, 'retry'=>$nretakes))) {
            return true;
        }
        return false;
    }
    public function requires_manual_grading() {
        return true;
    }
    public function get_earnedscore($answers, $attempt) {
        return $attempt->score;
    }
}

class languagelesson_add_page_form_audio extends languagelesson_add_page_form_base {

    public $qtype = 'audio';
    public $qtypestring = 'audio';

    public function custom_definition() {
        $this->add_jumpto(0);
        $this->add_score(0, null, 1);
    }
}

class languagelesson_display_answer_form_audio extends moodleform {

    public function definition() {
        global $CFG, $USER, $OUTPUT, $PAGE, $DB;
        $mform = $this->_form;
        $contents = $this->_customdata['contents'];
        $cmid = $PAGE->cm->id;
        $lessonid = $PAGE->cm->instance;
        $pageid = optional_param('pageid', null, PARAM_INT);

        $context = CONTEXT_MODULE::instance($cmid);

        $hasattempt = false;
        $retry = true;
        $attrs = '';
        $fileid = '';
        $hasfeedback = false;

        $maxattempts = $DB->get_field('languagelesson', 'maxattempts', array ('id'=>$lessonid));

        $thisattempt = languagelesson_get_most_recent_attempt_on($pageid, $USER->id);

        if ($feedbackrecords = $DB->get_records('languagelesson_feedback', array('pageid' => $pageid, 'userid'=>$USER->id))) {
            $hasfeedback = true;
            foreach ($feedbackrecords as $feedback) {
                if ($feedback->text != null) {
                    $feedbackteacher = new stdClass();
                    $feedbackteacher = $DB->get_record('user', array('id'=>$feedback->teacherid));
                    $teacherpic = $OUTPUT->user_picture($feedbackteacher);
                    $fullname = fullname($feedbackteacher);

                    $textfeedback = nl2br($feedback->text);
                    $textfeedback = stripslashes($textfeedback);

                    $feedbackdate = userdate($feedback->timeseen);
                }
            }
        }

        if (!empty($thisattempt->fileid)) {
            $hasattempt = true;
            $filerecord = $DB->get_record('files', array('id'=>$thisattempt->fileid));

            $fileurl = languagelesson_get_audio_submission($filerecord);
            $recordingconfig = languagelesson_build_config_for_FBplayer($lessonid, $fileurl, $thisattempt->id);

        }

        $mform->addElement('header', 'pageheader');

        $mform->addElement('html', $OUTPUT->container($contents, 'contents'));

        if ($hasfeedback) {
            $mform->addElement('html', '<center><table border=1><tr><td>'. $teacherpic . $fullname . '</td></tr>
                               <tr><td>'.$feedbackdate.'</td></tr><tr><td><hr>'.$textfeedback.'</td></tr></table></center>');
        }

        $options = new stdClass;
        $options->para = false;
        $options->noclean = true;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'pageid');
        $mform->setType('pageid', PARAM_INT);

        $mform->addElement('hidden', 'attemptid');
        $mform->setType('attemptid', PARAM_INT);

        $mform->addElement('hidden', 'audiodata');
        $mform->setType('audiodata', PARAM_RAW);

        $mform->addElement('hidden', 'resubmit');
        $mform->setType('resubmit', PARAM_INT);

        $mform->addElement('html', '<link rel="stylesheet" type="text/css"
                           href="'.$CFG->wwwroot.'/mod/languagelesson/recorders/history/history.css" />');
        $mform->addElement('html', '<script type="text/javascript"
                           src="'.$CFG->wwwroot.'/mod/languagelesson/recorders/history/history.js"/></script />');
        $mform->addElement('html', '<script type="text/javascript"
                           src="http://ajax.googleapis.com/ajax/libs/jquery/1.6.1/jquery.min.js"/></script />');
        $mform->addElement('html', '<script type="text/javascript"
                           src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.13/jquery-ui.min.js"/></script />');
        $mform->addElement('html', '<script type="text/javascript"
                           src="'.$CFG->wwwroot.'/mod/languagelesson/recorders/swfobject.js"></script>');

        $displayfeedbackplayer = "<script type='text/javascript'>
            // For version detection, set to min. required Flash Player version, or 0 (or 0.0.0), for no version detection.
            var swfVersionStr = '10.2.0';
            // To use express install, set to playerProductInstall.swf, otherwise the empty string.
            var xiSwfUrlStr = 'playerProductInstall.swf';
            var flashvars = {};
            var params = {};
            params.quality = 'high';
            params.bgcolor = '#ffffff';
            params.allowscriptaccess = 'sameDomain';
            params.allowfullscreen = 'true';
            var attributes = {};
            attributes.id = 'LanguageLessonFeedbackPlayer';
            attributes.name = 'LanguageLessonFeedbackPlayer';
            attributes.align = 'middle';
            swfobject.embedSWF(
                '".$CFG->wwwroot."/mod/languagelesson/recorders/LanguageLessonFeedbackPlayer.swf', 'flashContent',
                '800', '140',
                swfVersionStr, xiSwfUrlStr,
                flashvars, params, attributes);
            // JavaScript enabled so display the flashContent div in case it is not replaced with a swf object.
            swfobject.createCSS('#flashContent', 'display:block;text-align:left;');
        </script>";

        $displayrecorder = "<script type='text/javascript'>
            // For version detection, set to min. required Flash Player version, or 0 (or 0.0.0), for no version detection.
            var swfVersionStr = '10.2.0';
            // To use express install, set to playerProductInstall.swf, otherwise the empty string.
            var xiSwfUrlStr = 'playerProductInstall.swf';
            var flashvars = {};
            var params = {};
            params.quality = 'high';
            params.bgcolor = '#ffffff';
            params.allowscriptaccess = 'sameDomain';
            params.allowfullscreen = 'true';
            var attributes = {};
            attributes.id = 'LanguageLessonRecorder';
            attributes.name = 'LanguageLessonRecorder';
            attributes.align = 'middle';
            swfobject.embedSWF(
                '".$CFG->wwwroot."/mod/languagelesson/recorders/LanguageLessonRecorder.swf', 'flashContent',
                '800', '140',
                swfVersionStr, xiSwfUrlStr,
                flashvars, params, attributes);
            // JavaScript enabled so display the flashContent div in case it is not replaced with a swf object.
            swfobject.createCSS('#flashContent', 'display:block;text-align:left;');
        </script>";

        // Pass PHP variables to JS.
        $config = send_audio_config($lessonid, $USER);

        $js1 = "<script type='text/javascript'>
                function micAccessStatusCallback(x){
                        // Callback that indicates if the user has allowed or denied access to the microphone.
                        // We can add code here to tell if the user can record.
                }
                function languageLessionAppletLoaded(x){
                        //This is called when the Flash applet is ready

                        var playerElement = document.getElementById('LanguageLessonFeedbackPlayer');

                       if (playerElement != null)
                        {

                        var config = '";

        $js2 = "'
                        // Pass JSON data to applet to play.
                        document.getElementById('LanguageLessonFeedbackPlayer').loadLessonDescription(config);
                        }
                }

                function languageLessonUpdated(newLessonConfig, newUploadData){
                        //Note: we pass newLessonConfig as a string, without parsing the json, as JS does not need to inspect it
                        //The php page will parse it when it arrives there

                        var updateInfo = {
                                'uploaddata': JSON.parse(newUploadData)
                                };

                    document.forms['mform1'].elements['audiodata'].value = newUploadData;
                    document.forms['mform1'].submit();
                }

                function error(x){
                        console.log('error: ' + x);
                }

                function info(x){
                        console.log('info: ' + x);
                }

        </script>";

        if ($hasattempt) {
            $jsfunctions = $js1 . $recordingconfig . $js2;
        } else {
            $jsfunctions = $js1 . $js2;
        }

        $mform->addElement('html', $jsfunctions);

        if ($hasattempt) {

            $playerurl='recorders/LanguageLessonFeedbackPlayer.swf?gateway='.$CFG->wwwroot.'/mod/languagelesson/pagetypes/load.php';
            $flashvars='&id='.$PAGE->cm->id.'&sesskey='.$USER->sesskey;

            $mform->addElement('html', '<div id="flashContent">');
            $mform->addElement('html', '<p>To view this page ensure that Adobe Flash Player
                               version 10.2.0 or greater is installed.</p>');

            $getflash = "<script type=\'text/javascript\'>
                    var pageHost = ((document.location.protocol == \'https:\') ? \'https://\' : \'http://\');
                    document.write(\'<a href='http://www.adobe.com/go/getflashplayer'><img src='\'
                                    + pageHost + \'www.adobe.com/images/shared/download_buttons/get_flash_player.gif'
                                    alt='Get Adobe Flash player' /></a>\' );
                </script> ";

            $mform->addElement('html', $getflash);
            $mform->addElement('html', '</div>');

            $mform->addElement('html', $displayfeedbackplayer);
            $mform->addElement('html', '<noscript>');
            $mform->addElement('html', '<center><object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000"
                               width="800" height="140" id="LanguageLessonFeedbackPlayer">');
            $mform->addElement('html', '<param name="movie" value="'.$playerurl.$flashvars.'" />');
            $mform->addElement('html', '<param name="quality" value="high" />');
            $mform->addElement('html', '<param name="bgcolor" value="#ffffff" />');
            $mform->addElement('html', '<param name="allowScriptAccess" value="sameDomain" />');
            $mform->addElement('html', '<param name="allowFullScreen" value="true" />');
            $mform->addElement('html', '<!--[if !IE]>-->');
            $mform->addElement('html', '<object type="application/x-shockwave-flash" data="'
                               .$playerurl.$flashvars.'" width="800" height="140">');
            $mform->addElement('html', '<param name="quality" value="high" />');
            $mform->addElement('html', '<param name="bgcolor" value="#ffffff" />');
            $mform->addElement('html', '<param name="allowScriptAccess" value="sameDomain" />');
            $mform->addElement('html', '<param name="allowFullScreen" value="true" />');
            $mform->addElement('html', '<!--<![endif]-->');
            $mform->addElement('html', '<!--[if gte IE 6]>-->');
            $mform->addElement('html', '<p> ');
            $mform->addElement('html', 'Either scripts and active content are not permitted to run or Adobe Flash Player version');
            $mform->addElement('html', '10.2.0 or greater is not installed.');
            $mform->addElement('html', '</p>');
            $mform->addElement('html', '<!--<![endif]-->');
            $mform->addElement('html', '<a href="http://www.adobe.com/go/getflashplayer">
                    <img src="http://www.adobe.com/images/shared/download_buttons/get_flash_player.gif"
                    alt="Get Adobe Flash Player" /></a>');
            $mform->addElement('html', '<!--[if !IE]>-->');
            $mform->addElement('html', '</object>');
            $mform->addElement('html', '<!--<![endif]-->');
            $mform->addElement('html', '</object></center>');
            $mform->addElement('html', '</noscript>');
        }

        if ($retry) {

            $recorderurl='recorders/LanguageLessonRecorder.swf?gateway='.$CFG->wwwroot.'/mod/languagelesson/pagetypes/load.php';
            $flashvars='&id='.$PAGE->cm->id.'&sesskey='.$USER->sesskey;

            $mform->addElement('html', '<div id="flashContent">');
            $mform->addElement('html', '<p>To view this page ensure that Adobe Flash Player
                               version 10.2.0 or greater is installed.</p>');

            $getflash = "<script type=\'text/javascript\'>
                    var pageHost = ((document.location.protocol == \'https:\') ? \'https://\' : \'http://\');
                    document.write(\'<a href='http://www.adobe.com/go/getflashplayer'><img src='\'
                                    + pageHost + \'www.adobe.com/images/shared/download_buttons/get_flash_player.gif'
                                    alt='Get Adobe Flash player' /></a>\' );
                </script> ";

            $mform->addElement('html', $getflash);
            $mform->addElement('html', '</div>');

            $mform->addElement('html', $displayrecorder);
            $mform->addElement('html', '<noscript>');
            $mform->addElement('html', '<center><object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" width="800"
                               height="140" id="LanguageLessonRecorder">');
            $mform->addElement('html', '<param name="movie" value="'.$recorderurl.$flashvars.'" />');
            $mform->addElement('html', '<param name="quality" value="high" />');
            $mform->addElement('html', '<param name="bgcolor" value="#ffffff" />');
            $mform->addElement('html', '<param name="allowScriptAccess" value="sameDomain" />');
            $mform->addElement('html', '<param name="allowFullScreen" value="true" />');
            $mform->addElement('html', '<!--[if !IE]>-->');
            $mform->addElement('html', '<object type="application/x-shockwave-flash" data="'.$recorderurl.$flashvars.'"
                               width="800" height="140">');
            $mform->addElement('html', '<param name="quality" value="high" />');
            $mform->addElement('html', '<param name="bgcolor" value="#ffffff" />');
            $mform->addElement('html', '<param name="allowScriptAccess" value="sameDomain" />');
            $mform->addElement('html', '<param name="allowFullScreen" value="true" />');
            $mform->addElement('html', '<!--<![endif]-->');
            $mform->addElement('html', '<!--[if gte IE 6]>-->');
            $mform->addElement('html', '<p> ');
            $mform->addElement('html', 'Either scripts and active content are not permitted to run or Adobe Flash Player version');
            $mform->addElement('html', '10.2.0 or greater is not installed.');
            $mform->addElement('html', '</p>');
            $mform->addElement('html', '<!--<![endif]-->');
            $mform->addElement('html', '<a href="http://www.adobe.com/go/getflashplayer">
                            <img src="http://www.adobe.com/images/shared/download_buttons/get_flash_player.gif"
                            alt="Get Adobe Flash Player" /></a>');
            $mform->addElement('html', '<!--[if !IE]>-->');
            $mform->addElement('html', '</object>');
            $mform->addElement('html', '<!--<![endif]-->');
            $mform->addElement('html', '</object></center>');
            $mform->addElement('html', '</noscript>');

        }

        if ($hasattempt) {
            $nextpage = $DB->get_field('languagelesson_pages', 'nextpageid', array('id'=>$pageid));
            $url = new moodle_url('/mod/languagelesson/view.php', array('id'=>$cmid, 'pageid'=>$nextpage));
            $mform->addElement('html', '<br><br><script type="text/javascript">');
            $mform->addElement('html', 'function gotonext(url)');
            $mform->addElement('html', '{ window.location.assign(url)}');
            $mform->addElement('html', '</script>');
            $mform->addElement('html', '<center><button type="button" onclick="gotonext(\''.$url.'\')">Continue</button></center>');
        }
    }

    protected function download_submissions() {
        global $CFG;

        $submissions = $this->get_submissions('', '');

        $filesforzipping = array();
        $filesnewname = array();
        $desttemp = "";

        // Create prefix of new filename.
        $filenewname = clean_filename($this->pageid->name. "_");
        $course     = $this->course;
        $pageid = $this->pageid;
        $cm         = $this->cm;
        $context    = context_module::instance($cm->id);
        $groupmode = groupmode($course, $cm);
        $groupid = 0;    // All users.
        if ($groupmode) {
            $groupid = get_current_group($course->id, $full = false);
        }
        $count = 0;

        foreach ($submissions as $submission) {
            $auserid = $submission->userid; // Get userid.
            if ( (groups_is_member( $groupid, $auserid)or !$groupmode or !$groupid)) {
                $count++;

                $anassignid = $submission->pageid; // Get name of this assignment for use in the file names.

                $auser = get_complete_user_data("id", $auserid); // Get user.

                $filearea = $this->file_area_name($auserid);

                // Get temp directory name.
                $desttemp = $CFG->dataroot . "/" . substr($filearea, 0, strrpos($filearea, "/")). "/temp/";

                if (!file_exists($desttemp)) { // Create temp dir if it doesn't already exist.
                    mkdir($desttemp);
                }

                if ($basedir = $this->file_area($auserid)) {
                    if ($files = get_directory_list($basedir)) {
                        foreach ($files as $key => $file) {
                            require_once($CFG->libdir.'/filelib.php');

                            // Get files new name.
                            $filesforzip = $desttemp . $auser->username . "_" . $filenewname . "_" . $file;

                            // Get files old name.
                            $fileold = $CFG->dataroot . "/" . $filearea . "/" . $file;

                            if (!copy($fileold, $filesforzip)) {
                                error ("failed to copy file<br>" . $filesforzip . "<br>" .$fileold);
                            }

                            // Save file name to array for zipping.
                            $filesforzipping[] = $filesforzip;
                        }
                    }
                }
            }
        }     // End of foreach.

        // Zip files.
        $filename = "assignment.zip"; // Name of new zip file.
        if ($count) {
            zip_files($filesforzipping, $desttemp.$filename);
        }
        // Skip if no files zipped.
        // Delete old temp files.
        foreach ($filesforzipping as $filefor) {
            unlink($filefor);
        }

        // Send file to user.
        if (file_exists($desttemp.$filename)) {
            header ("Cache-Control: must-revalidate, post-check=0, pre-check=0");
            header ("Content-Type: application/octet-stream");
            header ("Content-Length: " . filesize($desttemp.$filename));
            header ("Content-Disposition: attachment; filename=$filename");
            readfile($desttemp.$filename);
        }
    }
    protected function print_user_files($userid=0, $return=false, $mode='') {
        global $CFG, $USER;

        if (!$userid) {
            if (!isloggedin()) {
                return '';
            }
            $userid = $USER->id;
        }

        $filearea = $this->file_area_name($userid);
        $output = '';

        $submission = $this->get_submission($userid);

        $candelete = $this->can_delete_files($submission);
        $strdelete   = get_string('delete');

        if ($basedir = $this->file_area($userid)) {
            if ($files = get_directory_list($basedir)) {
                require_once($CFG->libdir . '/filelib.php');

                foreach ($files as $key => $file) {
                    $icon = mimeinfo('icon', $file);
                    $ffurl = get_file_url("$filearea/$file");

                    $output .= '<img src="'.$CFG->pixpath.'/f/'.$icon.'" class="icon" alt="'.$icon.'" />';
                    // Dummy link for media filters.
                    $filtered = format_text('<a href="'.$ffurl.'" style="display:none;"> </a> ', FORMAT_HTML);
                    $filtered = preg_replace('~<a.+?</a>~', '', $filtered);
                    // Add a real link after the dummy one, so that we get a proper download link no matter what.
                    $output .= $filtered . '<a href="'.$ffurl.'" >'.$file.'</a>';
                    if ($candelete) {
                        $delurl = "$CFG->wwwroot/mod/languagelesson/delete.php?
                           id={$this->cm->id}&amp;file=$file&amp;userid={$submission->userid}" . ($mode ? '&mode=submissions' : '');

                        $output .= '<a href="' . $delurl . '">&nbsp;'
                                . '<img title="' . $strdelete . '" src="' . $CFG->pixpath . '/t/delete.gif"
                                class="iconsmall" alt="" /></a> ';
                    }
                    $output .='<br/>';

                }
            }
        }

        $output = '<div class="recorder_files">'.$output.'</div>';

        if ($return) {
            return $output;
        }
        echo $output;
    }


    protected function can_delete_files($submission) {
        global $USER;

        if (has_capability('mod/languagelesson:grade', $this->context)) {
            return true;
        }

        if (has_capability('mod/languagelesson:submit', $this->context)
          and $this->isopen()                                      // Assignment not closed yet.
          and $USER->id == $submission->userid                      // His/her own submission.
          and $submission->grade ==-1) {  // Not yet graded.
            return true;
        } else {
            return false;
        }
    }


    protected function delete() {
        global $CFG;

        $file     = required_param('file', PARAM_FILE);
        $userid   = required_param('userid', PARAM_INT);
        $confirm  = optional_param('confirm', 0, PARAM_BOOL);
        $mode     = optional_param('mode', '', PARAM_ALPHA);

        require_login($this->course->id, false, $this->cm);

        if (empty($mode)) {
            $urlreturn = 'view.php';
            $optionsreturn = array('id'=>$this->cm->id);
            $returnurl = 'view.php?id='.$this->cm->id;
        } else {
            $urlreturn = 'submissions.php';
            $optionsreturn = array('id'=>$this->cm->id, 'mode'=>$mode, 'userid'=>$userid);
            $returnurl = "submissions.php?id={$this->cm->id}&amp;mode=$mode&amp;userid=$userid";
        }

        if (!$submission = $this->get_submission($userid) // Incorrect submission.
          or !$this->can_delete_files($submission)) {     // Can not delete.
            $this->view_header(get_string('delete'));
            notify(get_string('cannotdeletefiles', 'assignment'));
            print_continue($returnurl);
            $this->view_footer();
            die;
        }
        $dir = $this->file_area_name($userid);

        if (!data_submitted('nomatch') or !$confirm or !confirm_sesskey()) {
            $optionsyes = array ('id'=>$this->cm->id, 'file'=>$file, 'userid'=>$userid, 'confirm'=>1, 'sesskey'=>sesskey(),
                                 'mode'=>$mode,  'sesskey'=>sesskey());
            if (empty($mode)) {
                $this->view_header(get_string('delete'));
            } else {
                print_header(get_string('delete'));
            }
            print_heading(get_string('delete'));
            notice_yesno(get_string('confirmdeletefile', 'assignment', $file), 'delete.php', $urlreturn, $optionsyes,
                         $optionsreturn, 'post', 'get');
            if (empty($mode)) {
                $this->view_footer();
            } else {
                print_footer('none');
            }
            die;
        }

        $filepath = $CFG->dataroot.'/'.$dir.'/'.$file;
        if (file_exists($filepath)) {
            if (@unlink($filepath)) {
                $updated = new object();
                $updated->id = $submission->id;
                $updated->timemodified = time();
                if (update_record('languagelesson_submissions', $updated)) {
                    add_to_log($this->course->id, 'languagelesson', 'upload', // TODO: add delete action to log.
                            'view.php?a='.$this->lesson->id, $this->pageid->id, $this->cm->id);
                    $submission = $this->get_submission($userid);
                    $this->update_grade($submission);
                }
                redirect($returnurl);
            }
        }

        // Print delete error.
        if (empty($mode)) {
            $this->view_header(get_string('delete'));
        } else {
            print_header(get_string('delete'));
        }
        notify(get_string('deletefilefailed', 'languagelesson'));
        print_continue($returnurl);
        if (empty($mode)) {
            $this->view_footer();
        } else {
            print_footer('none');
        }
        die;
    }
}
