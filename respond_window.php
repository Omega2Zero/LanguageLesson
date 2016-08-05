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

require_once('../../config.php');
require_once($CFG->dirroot.'/mod/languagelesson/locallib.php');
require_once($CFG->dirroot.'/mod/languagelesson/lib.php');

global $DB, $CFG, $PAGE, $OUTPUT;

// Pull the course module context id.
$cmid = required_param('cmid', PARAM_INT);
$context = context_module::instance($cmid);

// Before building page take any previously submitted feedback.
if (!empty($_POST['audiodata'])) {
    $feedbackrecords = array();

    $feedbackrecords = languagelesson_upload_feedback($_POST['audiodata'], $_POST['attemptid'], $cmid);

    $configdata = array();
    $configdata = json_decode($_POST['config'], true, 5);

    $fbinfo = $configdata['feedback'];

    foreach ($fbinfo as $key => $filedata) {
        $filename = $filedata['audiofile'];

        $location = $filedata['location'];
        if ($thisfbrecord = $DB->get_record_select('languagelesson_feedback', "filename = '$filename'")) {
            $thisfbrecord->location = $location;
            $DB->update_record('languagelesson_feedback', $thisfbrecord);
        }

    }
}

// And retrieve the course module object.
list($cm, $course, $lesson) = languagelesson_get_basics($cmid);

require_login($course, true, $cm);
$PAGE->set_url('/mod/languagelesson/grader.php', array('id'=> $cm->id));
$PAGE->set_pagelayout('popup');
$PAGE->navbar->ignore_active();
// Pull the attempt data for the attempt clicked on.
$attemptid = required_param('attemptid', PARAM_INT);
$pageid  = $DB->get_field('languagelesson_attempts', 'pageid', array('id'=>$attemptid));   // Lesson Page ID for this attempt.
$lesson = new languagelesson($DB->get_record('languagelesson', array('id' => $cm->instance), '*', MUST_EXIST));

$lessonoutput = $PAGE->get_renderer('mod_languagelesson');
echo $lessonoutput->popup_header($lesson, $cm);

// Build $attempt object for use in page.
$attempt = new stdClass();
$attempt = $DB->get_record('languagelesson_attempts', array('id'=>$attemptid));

$student = $DB->get_record('user', array('id'=>$attempt->userid));
$stuname = $student->firstname . ' ' . $student->lastname;

$page = $DB->get_record('languagelesson_pages',    array('id'=>$attempt->pageid));

echo '<form id="submissionform" action="respond_window.php" method="post">';

// Adding a switch to handle the two kinds of attempts.
switch ($attemptid) {
    case ($DB->record_exists('languagelesson_attempts', array('id'=>$attemptid))):
        // Set the attempt type here for use later
        $attempttype = $DB->get_field('languagelesson_pages', 'qtype', array('id'=>$attempt->pageid));

        //$OUTPUT->box_start('center');

        $action = 'reportdetail';

        // Lifted base code from report.php to replicate the portions of that I want.
        if ($action === 'reportdetail') {

            $coursecontext = context_course::instance($course->id);

            $formattextdefoptions = new stdClass;
            $formattextdefoptions->para = false;  // I'll use it widely in this page.
            $formattextdefoptions->overflowdiv = true;

            $userid = $attempt->userid;
            $try    = optional_param('try', null, PARAM_INT);

            $pagestats = array();

            $page = new languagelesson_page_type_manager();
            $page->id = $pageid;

            $manager = languagelesson_page_type_manager::get($lesson);
            $qtypes = $manager->get_page_type_strings();
            $page = $manager->load_page($pageid, $lesson);

            $answerpages = array();
            $answerpage = "";

            if ($pageid != 0) { // EOL.

                $answerpage = new stdClass;
                $data ='';

                $answerdata = new stdClass;
                // Set some defaults for the answer data.
                $answerdata->score = null;
                $answerdata->response = null;
                $answerdata->responseformat = FORMAT_PLAIN;

                $answerpage->title = format_string($page->title);

                $options = new stdClass;
                $options->noclean = true;
                $options->overflowdiv = true;
                $answerpage->contents = format_text($page->contents, $page->contentsformat, $options);

                $answerpage->qtype = $qtypes[$page->qtype];
                $answerpage->context = $context;

                $useranswers = $DB->get_records("languagelesson_attempts",
                                array("lessonid"=>$lesson->id, "userid"=>$userid, "pageid"=>$page->id, "iscurrent"=>1), "timeseen");

                // Get the user's answer for this page.
                // Need to find the right one.
                $i = 0;
                foreach ($useranswers as $userattempt) {
                    $useranswer = $userattempt;
                    $i++;
                    if ($lesson->maxattempts == $i) {
                        break; // Reached maxattempts, break out.
                    }
                }
                $i = 0;
                $n = 0;
                $answerpages[] = $page->report_answers(clone($answerpage), clone($answerdata), $useranswer, $pagestats, $i, $n);

            }

            // Actually start printing something.
            $table = new html_table();
            $table->wrap = array();
            $table->width = "90%";
            if (!empty($userid)) {
                $table->head = array();
                $table->align = array('right', 'left');
                $table->attributes['class'] = 'compacttable generaltable';
                $maxscore = $DB->get_field('languagelesson_pages', 'maxscore', array('id'=>$attempt->pageid));
                $table->data[] = array(get_string('name').':', $OUTPUT->user_picture($student,
                                        array('courseid'=>$course->id)). $stuname, get_string('rawgrade', 'languagelesson').':'.
                                       $attempt->score.'/'.$maxscore);
                $table->data[] = array(get_string("completed", "languagelesson").":", userdate($attempt->timeseen),
                                       get_string('override_grade', "languagelesson") .
                                       ':<input id="grade_input" name="grade" type="text" size="5" value="' .
                                       ((isset($thisscore)) ? $thisscore :    $maxscore) .'" onblur="update_grade(this, ' . $maxscore .')"/>
                                       <input type="hidden" name="maxgrade" value="' . $maxscore . '" />');
            }
            echo html_writer::table($table);

            // Don't want this class for later tables.
            $table->attributes['class'] = '';
        }

        $table->align = array('left', 'left');
        $table->size = array('90%', null);
        $table->attributes['class'] = 'compacttable generaltable';
        foreach ($answerpages as $apage) {
            if (!empty($answerpages)) {
                unset($table->data);
                $fontstart = "";
                $fontend = "";
                $fontstart2 = "";
                $fontend2 = "";

                $table->head = array($fontstart2.$apage->qtype." : ".format_string($apage->title).$fontend2);

                /*
                 * We special case the LL_CLOZE type because we need to combine answers with the original question
                 * HTML in order to really show the answer. Also with LL_CLOZE, $page->answerdata does not actually
                 * have our answers in the correct format, so we just look at $useranswer->useranswer.
                 */
                if ($attempttype == LL_CLOZE) {
                    // Get our cloze_correct_incorrect_view html.
                    $html = $page->get_cloze_correct_incorrect_view($useranswer->useranswer, $apage->contents);
                    $table->data[] = array($fontstart.'Question with Answers:'.$fontend);
                    $table->data[] = array($html);
                } else if ($attempttype !== LL_AUDIO) {
                    $table->data[] = array($fontstart.get_string('question', 'languagelesson').':<br />'.$fontend);
                    $table->data[] = array($fontstart2.$apage->contents.$fontend2);
                    $table->data[] = array($fontstart.get_string("answer", "languagelesson").":<br />".$fontend);
                    if (!empty($apage->answerdata)) {
                        foreach ($apage->answerdata->answers as $answer) {
                            $modified = array();
                            list($response, $stats) = $answer;
                            $modified[] = $fontstart2.$response.$fontend2;
                            $table->data[] = $modified;
                            unset($response);
                            unset($stats);
                        }
                    }
                    $table->data[] = array($fontstart.get_string("response", "languagelesson").": <br />".$fontend.
                                        $fontstart2.format_text($apage->answerdata->response, $apage->answerdata->responseformat,
                                                                $formattextdefoptions).$fontend2);
                } else if ($attempttype == LL_AUDIO) {
                    $table->data[] = array($fontstart.get_string('question', 'languagelesson').':<br />'.$fontend);
                    $table->data[] = array($fontstart2.$apage->contents.$fontend2);
                    //$table->data[] = array($fontstart.get_string("answer", "languagelesson").":<br />".$fontend);
                }
                echo html_writer::table($table);
            }
        }

        // Eend of copy from report.php.

}

    // Make note of whether we need to flag this attempt as viewed or not.
    $needsflag = optional_param('needsflag', 1, PARAM_INT);

    // If we do, then flag this attempt as viewed and deflag the resubmit and
    // refresh the opener page.
if ($needsflag) {

    $uma = new stdClass;
    $uma->id = $attempt->id;
    $uma->viewed = 1;
    $uma->resubmit = 0;
    if (!$DB->update_record('languagelesson_attempts', $uma)) {
        print_error('Could not flag this attempt as viewed!');
    }
    echo '<script type="text/javascript">window.opener.location.reload();</script>';
}

// Iif 'submitting' is set, then the form was just submitted here, so save
// thesaved textual response and grade into the attempt data.

$submitting = optional_param('submitting', null, PARAM_RAW);
if ($submitting) {

    // Handle the text response.
    $textresponse = trim(optional_param('text_response', '', PARAM_RAW));
    $textresponse = clean_param($textresponse, PARAM_CLEANHTML);
    $textresponse = addslashes($textresponse);

    if ($textresponse) {
        // Pull the id of the old text feedback record, if there is one.
        $select = "lessonid = $lesson->id AND pageid = $attempt->pageid AND
            userid = $attempt->userid AND attemptid = $attempt->id AND teacherid = $USER->id AND text IS NOT null";
        $oldfeedbackid = $DB->get_field_select('languagelesson_feedback', 'id', $select );

        // Build the text feedback object.
        $feedback = new stdClass();
        $feedback->lessonid = $lesson->id;
        $feedback->pageid = $attempt->pageid;
        $feedback->userid = $attempt->userid;
        $feedback->attemptid = $attempt->id;
        $feedback->teacherid = $USER->id;
        $feedback->text = $textresponse;
        $feedback->timeseen = time();

        // And insert it into the DB.
        if ($oldfeedbackid) {
            $feedback->id = $oldfeedbackid;
            if (! $update = $DB->update_record('languagelesson_feedback', $feedback)) {
                print_print_error('Could not update text feedback record.');
            }
        } else {
            if (! $feedbackid = $DB->insert_record('languagelesson_feedback', $feedback)) {
                print_error("Could not insert text feedback record!");
            }
        }

    }
    // Handle the text response.

    // Handle the assigned score

    $grade = optional_param('grade', 1, PARAM_NUMBER);
    if ($grade != -1) {
        $attempt->score = $grade;
        if (! $update = $DB->update_record('languagelesson_attempts', $attempt)) {
            print_error('Could not save the score for this attempt!');
        }
    }

    $usergrade = languagelesson_calculate_user_lesson_grade($lesson->id, $attempt->userid);

    $grades->userid = $attempt->userid;
    $grades->rawgrade = $usergrade;

    languagelesson_update_grades($lesson, $attempt->userid);

    //languagelesson_grade_item_update($lesson, $grades);
    //$DB->update_record('grade_grades', $gradeinstance);

    // Handle the assigned score.





    // Nothing more to do here: refresh the opener screen (the grader
    // window), and refresh this window to reflect the newly-submitted
    // textual feedback.
    echo "<script
    type=\"text/javascript\">window.opener.location.reload();"
         ."window.location.href='$CFG->wwwroot/mod/languagelesson/respond_window.php"
         ."?cmid=$cmid&attemptid=$attemptid';</script>";
}
// End submission code.
    echo '<table><tr><td valign=top>';
    echo '<b>Text Feedback:</b>';
    echo '</td><td>';
    $select = "lessonid = ".$lesson->id." AND pageid = ".$attempt->pageid." AND
                userid = ". $attempt->userid ." AND attemptid = ". $attempt->id ." AND text IS NOT null";
    $feedbacktext = $DB->get_field_select('languagelesson_feedback', 'text', $select);
    echo '<textarea id="text_response" name="text_response" rows="3" cols="120" style="width:500px;">'.$feedbacktext.'</textarea><br>';
    echo '</td></tr></table>';
if ($attempttype == LL_AUDIO) {

    echo '<center>Use this submit button only if you are entering text feedback only. &nbsp;';
    echo '<button type="submit" />Submit Text Feedback</button></center>';

    // get the student's submission.
    $submittedfilerecord = $DB->get_record('files', array('id'=>$attempt->fileid));
    $fileurl = languagelesson_get_audio_submission($submittedfilerecord);

    $recordingconfig = languagelesson_build_config_for_FBplayer($lesson->id, $fileurl, $attemptid);

    echo $OUTPUT->box_start('center');

    echo '<table id="recorderContainer">';

        echo '<tr>';

        echo '<td id="feedback_recorder_container">';

        echo '<link rel="stylesheet" type="text/css" href="'.$CFG->wwwroot.'/mod/languagelesson/recorders/history/history.css" />';
        echo '<script type="text/javascript" src="'.$CFG->wwwroot.'/mod/languagelesson/recorders/history/history.js"></script>';
        echo '<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.6.1/jquery.min.js"></script>';
        echo '<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.13/jquery-ui.min.js"></script>';
        echo '<script type="text/javascript" src="'.$CFG->wwwroot.'/mod/languagelesson/recorders/swfobject.js"></script>';

           $js1 = "<script type='text/javascript'>
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
                attributes.id = 'LanguageLessonFeedbackRecorder';
                attributes.name = 'LanguageLessonFeedbackRecorder';
                attributes.align = 'middle';
                swfobject.embedSWF(
                '". $CFG->wwwroot."/mod/languagelesson/recorders/LanguageLessonFeedbackRecorder.swf', 'flashContent',
                '800', '140',
                swfVersionStr, xiSwfUrlStr,
                flashvars, params, attributes);
                // JavaScript enabled so display the flashContent div in case it is not replaced with a swf object.
                swfobject.createCSS('#flashContent', 'display:block;text-align:left;');
            </script>
            <script type='text/javascript'>

                    function micAccessStatusCallback(x){
                        //Callback that indicates if the user has allowed or denied access to the microphone
                        //We can add code here to tell if the user can record
                    }

                    function languageLessionAppletLoaded(x){
                        //This is called when the Flash applet is ready

                            var config = '";

        $js2 = "'
                              //Pass lessonConfig to the applet
                              document.getElementById('LanguageLessonFeedbackRecorder').loadLessonDescription(config);
                    }

                    function languageLessonUpdated(newLessonConfig, newUploadData){
                        //Note: we pass newLessonConfig as a string, without parsing the json, as JS does not need to inspect it
                        //The php page will parse it when it arrives there
                        var updateInfo = {
                            'uploaddata': JSON.parse(newUploadData)
                            };
                    //send the audiodata to the form to be submitted along with everything else
                         document.forms['submissionform'].elements['config'].value = newLessonConfig;
                         document.forms['submissionform'].elements['audiodata'].value = newUploadData;
                         document.forms['submissionform'].submit();
                    }

                    function error(x){
                        console.log('error: ' + x);
                    }

                    function info(x){
                        console.log('info: ' + x);
                    }
                    </script> ";
    echo $js1 . $recordingconfig. $js2;

        $recorderurl='recorders/LanguageLessonFeedbackRecorder.swf?gateway='.$CFG->wwwroot.'/mod/languagelesson/pagetypes/load.php';
        $flashvars='&id='.$PAGE->cm->id.'&sesskey='.$USER->sesskey;

    echo '<div id="flashContent">';
        echo '   <p>';
        echo '       To view this page ensure that Adobe Flash Player version ';
        echo '       10.2.0 or greater is installed.';
        echo '    </p>';
        $getflash =  "<script type=\'text/javascript\'>
                var pageHost = ((document.location.protocol == \'https:\') ? \'https://\' : \'http://\');
                document.write(\'<a href='http://www.adobe.com/go/getflashplayer'>
                <img src='\'+ pageHost + \'www.adobe.com/images/shared/download_buttons/get_flash_player.gif'
                alt='Get Adobe Flash player' /></a>\' );
            </script> ";
    echo $getflash;

        echo '</div>';

        echo '<noscript>';
        echo '    <object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" width="800" height="140" id="LanguageLessonFeedbackRecorder">';
        echo '        <param name="movie" value="'.$recorderurl.$flashvars.'" />';
        echo '        <param name="quality" value="high" />';
        echo '        <param name="bgcolor" value="#ffffff" />';
        echo '        <param name="allowScriptAccess" value="sameDomain" />';
        echo '        <param name="allowFullScreen" value="true" />';
        echo '        <!--[if !IE]>-->';
        echo '        <object type="application/x-shockwave-flash" data="'.$recorderurl.$flashvars.'" width="800" height="140">';
        echo '            <param name="quality" value="high" />';
        echo '           <param name="bgcolor" value="#ffffff" />';
        echo '            <param name="allowScriptAccess" value="sameDomain" />';
        echo '            <param name="allowFullScreen" value="true" />';
        echo '        <!--<![endif]-->';
        echo '        <!--[if gte IE 6]>-->';
        echo '            <p> ';
        echo '                Either scripts and active content are not permitted to run or Adobe Flash Player version';
        echo '                10.2.0 or greater is not installed.';
        echo '            </p>';
        echo '        <!--<![endif]-->';
        echo '            <a href="http://www.adobe.com/go/getflashplayer">';
        echo '                <img src="http://www.adobe.com/images/shared/download_buttons/get_flash_player.gif" alt="Get Adobe Flash Player" />';
        echo '            </a>';
        echo '        <!--[if !IE]>-->';
        echo '        </object>';
        echo '        <!--<![endif]-->';
        echo '    </object>';
        echo '</noscript>    ';

    echo '</tr>';

    echo '</table>';

    echo $OUTPUT->box_end('center');

} else {
    echo '<div align="center" width="500"><input type="submit"></div>';
}


    echo '<input type="hidden" name="audiodata" value="" />';
    echo '<input type="hidden" name="config" value="" />';


    // And close the submission form.
    echo '<input type="hidden" name="cmid" value="' . $cmid .'" /> ';
    echo '<input type="hidden" name="attemptid" value="' . $attemptid .'" /> ';
    echo '<input type="hidden" name="submitting" value="true" />';

echo '</form>';

/*
 * The "cancel" and "next" buttons */
echo ' <form id="theform" action="respond_window.php" method="get">';
    echo '<input type="hidden" name="cmid" value="' . $cmid .'" />';
    echo '<input type="hidden" id="attemptidinput" name="attemptid" />';

    echo '<script type="text/javascript">';
        echo 'var theInp; ';

        echo 'function setAttemptId(val) {';
            echo 'theInp = document.getElementById(\'attemptidinput\');';
            echo 'theInp.value = val;';
    echo '    }';
    echo '</script> ';

function find_page_by_id($pageid, $pages) {
    $i = 0;
    while ($i < count($pages) && $pages[$i]->id != $pageid) {
        $i++;
    } if ($i >= count($pages)) {
        return null;
    } else {
        return $i;
    }
}

function strip_autograde_pages($sortedpages) {
    $outsortedpages = array();
    for ($i=0; $i<count($sortedpages); $i++) {
        $curpageqtype = $sortedpages[$i]->qtype;
        if ($curpageqtype == LL_AUDIO || $curpageqtype == LL_ESSAY) {
            $outsortedpages[] = $sortedpages[$i];
        }
    } return $outsortedpages;
}

// Note that we need to get ALL pages first, so that
// languagelesson_sort_pages works, then we can safely remove
// the autograded pages from the page list.
$pages = $DB->get_records("languagelesson_pages", array("lessonid"=>$attempt->lessonid));
$sortedpages = languagelesson_sort_pages($pages);
//$sortedpages = strip_autograde_pages($sortedpages);
$thispageindex = find_page_by_id($attempt->pageid, $sortedpages);

$userid = $attempt->userid;

if (($thispageindex + 1) >= count($sortedpages)) {
    $nextpageid = null; $nextattempt = null;
} else {
    $nextpageid = $sortedpages[$thispageindex + 1]->id;
    $nextattempt = languagelesson_get_most_recent_attempt_on($nextpageid, $userid);
}

if (($thispageindex - 1) < 0) {
    $prevpageid = null; $prevattempt = null;
} else {
    $prevpageid = $sortedpages[$thispageindex - 1]->id;
    $prevattempt = languagelesson_get_most_recent_attempt_on($prevpageid, $userid);
}

// For making the navigation buttons at the bottom of the screen.
$students = languagelesson_get_students($lesson->course);
$studentids = array_keys($students);
$thisstudentindex = array_search($userid, $studentids);

if (($thisstudentindex + 1) >= count($studentids)) {
    $nextstuid = null;
    $nextstuattempt = null;
} else {
    $offset = 1;
    $nextstuid = $studentids[$thisstudentindex + $offset];
    while ($thisstudentindex + $offset < count($studentids) && !$nextstuattempt =
           languagelesson_get_most_recent_attempt_on($attempt->pageid, $nextstuid))
        $offset++;
        $nextstuid = $studentids[$thisstudentindex + $offset];
}
echo '<br><br>';
echo $OUTPUT->box_start();
echo '<center><table id="nav_table">';
        echo '<tr>';
            echo '<td class="thiscell" colspan="3">';
                echo '<center><input class="nav_button"id="nav_nextstu_button" type="submit" value="' . get_string('nextstudent', 'languagelesson');
                echo '"';
                echo (($nextstuattempt) ? "onclick='setAttemptId(\"$nextstuattempt->id\")';" : 'disabled="disabled"');
                echo ' /></center>';
            echo '</td>';
        echo '</tr><tr>';
            echo '<td class="thiscell">';
                echo '<input class="nav_button" id="nav_prev_button" type="submit" value="'.get_string('previousquestion', 'languagelesson');
                echo '"';
                echo (($prevattempt) ? "onclick='setAttemptId(\"$prevattempt->id\");'" : 'disabled="disabled"');
                echo ' />';
            echo '<td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>';
            echo '</td><td class="thiscell">';
                echo '<input class="nav_button" id="nav_next_button" type="submit" value="'.get_string('nextquestion', 'languagelesson');
                echo '"';
                echo (($nextattempt) ? "onclick='setAttemptId(\"$nextattempt->id\");'" : 'disabled="disabled"');
                echo '/>';
            echo '</td>';
        echo '</tr><tr>';
            echo '<td class="thiscell" colspan="3">';
                echo '<center><input class="nav_button" id="nav_cancel_button" type="submit" onclick="window.close();"';
                echo 'value="'.get_string('cancel', 'languagelesson');
                echo '" /></center>';
            echo '</td>';
        echo '</tr>';
    echo '</table></center>';
echo $OUTPUT->box_end();

    echo '<div style="text-align:center">';
        echo '<a
        href="https://docs.google.com/a/carleton.edu/spreadsheet/viewform?formkey=dGw5bjNrN2tjS3MwbC05NnVnNV9HZFE6MQ"
            target="_blank" style="font-size:0.75em;
            margin-top:25px;">Report a problem</a> </div>';

echo '</form>';