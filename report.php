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
 * Displays the lesson statistics.
 *
 * @package    mod
 * @subpackage lesson
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or late
 **/

require_once('../../config.php');
require_once($CFG->dirroot.'/mod/languagelesson/locallib.php');

$id     = required_param('id', PARAM_INT);    // Course Module ID.
$pageid = optional_param('pageid', null, PARAM_INT);    // Lesson Page ID.
$action = optional_param('action', 'reportoverview', PARAM_ALPHA);  // Action to take.
$nothingtodisplay = false;

$cm = get_coursemodule_from_id('languagelesson', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$lesson = new languagelesson($DB->get_record('languagelesson', array('id' => $cm->instance), '*', MUST_EXIST));

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/languagelesson:manage', $context);

$ufields = user_picture::fields('u'); // These fields are enough.
$params = array("lessonid" => $lesson->id);
// TODO: Improve this. Fetching all students always is crazy!
if (!empty($cm->groupingid)) {
    $params["groupid"] = $cm->groupingid;
    $sql = "SELECT DISTINCT $ufields
                FROM {languagelesson_attempts} a
                    INNER JOIN {user} u ON u.id = a.userid
                    INNER JOIN {groups_members} gm ON gm.userid = u.id
                    INNER JOIN {groupings_groups} gg ON gm.groupid = :groupid
                WHERE a.lessonid = :lessonid
                ORDER BY u.lastname";
} else {
    $sql = "SELECT DISTINCT $ufields
            FROM {user} u,
                 {languagelesson_attempts} a
            WHERE a.lessonid = :lessonid and
                  u.id = a.userid
            ORDER BY u.lastname";
}

if (! $students = $DB->get_records_sql($sql, $params)) {
    $nothingtodisplay = true;
}

$url = new moodle_url('/mod/languagelesson/report.php', array('id'=>$id));
if ($action !== 'reportoverview') {
    $url->param('action', $action);
}
if ($pageid !== null) {
    $url->param('pageid', $pageid);
}
$PAGE->set_url($url);
if ($action == 'reportoverview') {
    $PAGE->navbar->add(get_string('reports', 'languagelesson'));
    $PAGE->navbar->add(get_string('overview', 'languagelesson'));
}

$lessonoutput = $PAGE->get_renderer('mod_languagelesson');

if (! $attempts = $DB->get_records('languagelesson_attempts', array('lessonid' => $lesson->id), 'timeseen')) {
    $nothingtodisplay = true;
}

if (! $grades = $DB->get_records('languagelesson_grades', array('lessonid' => $lesson->id), 'completed')) {
    $grades = array();
}

if (! $times = $DB->get_records('languagelesson_timer', array('lessonid' => $lesson->id), 'starttime')) {
    $times = array();
}

if ($nothingtodisplay) {
    echo $lessonoutput->header($lesson, $cm, $action);
    echo $OUTPUT->notification(get_string('nolessonattempts', 'languagelesson'));
    echo $OUTPUT->footer();
    exit();
}

if ($action === 'delete') {
    // Process any form data before fetching attempts, grades and times.
    if (has_capability('mod/languagelesson:edit', $context) and $form = data_submitted() and confirm_sesskey()) {
        // Cycle through array of userids with nested arrays of tries.
        if (!empty($form->attempts)) {
            foreach ($form->attempts as $userid => $tries) {
                // Modifier IS VERY IMPORTANT!  What does it do?
                //      Well, it is for when you delete multiple attempts for the same user.
                //      If you delete try 1 and 3 for a user, then after deleting try 1, try 3 then
                //      becomes try 2 (because try 1 is gone and all tries after try 1 get decremented).
                //      So, the modifier makes sure that the submitted try refers to the current try in the
                //      database - hope this all makes sense :).
                $modifier = 0;

                foreach ($tries as $try => $junk) {
                    $try -= $modifier;

                    // Clean up the timer table by removing using the order - this is silly,
                    // It should be linked to specific attempt (skodak).
                    $params = array ("userid" => $userid, "lessonid" => $lesson->id);
                    $timers = $DB->get_records_sql("SELECT id FROM {languagelesson_timer}
                                                     WHERE userid = :userid AND lessonid = :lessonid
                                                  ORDER BY starttime", $params, $try, 1);
                    if ($timers) {
                        $timer = reset($timers);
                        $DB->delete_records('languagelesson_timer', array('id' => $timer->id));
                    }

                    // Remove the grade from the grades and high_scores tables - this is silly,
                    // It should be linked to specific attempt (skodak).
                    $grades = $DB->get_records_sql("SELECT id FROM {languagelesson_grades}
                                                     WHERE userid = :userid AND lessonid = :lessonid
                                                  ORDER BY completed", $params, $try, 1);

                    if ($grades) {
                        $grade = reset($grades);
                        $DB->delete_records('languagelesson_grades', array('id' => $grade->id));
                        $DB->delete_records('languagelesson_high_scores',
                                            array('gradeid' => $grade->id, 'lessonid' => $lesson->id, 'userid' => $userid));
                    }

                    // Remove attempts and update the retry number.
                    $DB->delete_records('languagelesson_attempts', array('userid' => $userid, 'lessonid' => $lesson->id, 'retry' => $try));
                    $DB->execute("UPDATE {languagelesson_attempts} SET retry = retry - 1 WHERE userid = ? AND lessonid = ? AND retry > ?",
                                                                            array($userid, $lesson->id, $try));

                    // Remove seen branches and update the retry number.
                    $DB->delete_records('languagelesson_branch', array('userid' => $userid, 'lessonid' => $lesson->id, 'retry' => $try));
                    $DB->execute("UPDATE {languagelesson_branch} SET retry = retry - 1 WHERE userid = ? AND lessonid = ? AND retry > ?",
                                                                            array($userid, $lesson->id, $try));

                    // Update central gradebook.
                    languagelesson_update_grades($lesson, $userid);

                    $modifier++;
                }
            }
        }
    }
    redirect(new moodle_url($PAGE->url, array('action'=>'reportoverview')));

} else if ($action === 'reportoverview') {
    /**************************************************************************
    this action is for default view and overview view
    **************************************************************************/
    echo $lessonoutput->header($lesson, $cm, $action);

    $coursecontext = context_course::instance($course->id);
    if (has_capability('gradereport/grader:view', $coursecontext) && has_capability('moodle/grade:viewall', $coursecontext)) {
        $seeallgradeslink = new moodle_url('/grade/report/grader/index.php', array('id'=>$course->id));
        $seeallgradeslink = html_writer::link($seeallgradeslink, get_string('seeallcoursegrades', 'grades'));
        echo $OUTPUT->box($seeallgradeslink, 'allcoursegrades');
    }

    $studentdata = array();

    // Bbuild an array for output.
    foreach ($attempts as $attempt) {
        // If the user is not in the array or if the retry number is not in the sub array, add the data for that try.
        if (!array_key_exists($attempt->userid, $studentdata) || !array_key_exists($attempt->retry, $studentdata[$attempt->userid])) {
            // Restore/setup defaults.
            $n = 0;
            $timestart = 0;
            $timeend = 0;
            $usergrade = null;

            // Search for the grade record for this try. if not there, the nulls defined above will be used.
            foreach ($grades as $grade) {
                // Check to see if the grade matches the correct user.
                if ($grade->userid == $attempt->userid) {
                    // See if n is = to the retry.
                    if ($n == $attempt->retry) {
                        // Get grade info.
                        $usergrade = round($grade->grade, 2); // Round it here so we only have to do it once.
                        break;
                    }
                    $n++; // If not equal, then increment n.
                }
            }
            $n = 0;
            // Search for the time record for this try. if not there, the nulls defined above will be used.
            foreach ($times as $time) {
                // Check to see if the grade matches the correct user.
                if ($time->userid == $attempt->userid) {
                    // See if n is = to the retry.
                    if ($n == $attempt->retry) {
                        // Get grade info.
                        $timeend = $time->lessontime;
                        $timestart = $time->starttime;
                        break;
                    }
                    $n++; // If not equal, then increment n.
                }
            }

            // Build up the array.
            // This array represents each student and all of their tries at the lesson.
            $studentdata[$attempt->userid][$attempt->retry] = array( "timestart" => $timestart,
                                                                    "timeend" => $timeend,
                                                                    "grade" => $usergrade,
                                                                    "try" => $attempt->retry,
                                                                    "userid" => $attempt->userid);
        }
    }
    // Set all the stats variables.
    $numofattempts = 0;
    $avescore      = 0;
    $avetime       = 0;
    $highscore     = null;
    $lowscore      = null;
    $hightime      = null;
    $lowtime       = null;

    $table = new html_table();

    // Set up the table object.
    $table->head = array(get_string('name'), get_string('attempts', 'languagelesson'), get_string('highscore', 'languagelesson'));
    $table->align = array('center', 'left', 'left');
    $table->wrap = array('nowrap', 'nowrap', 'nowrap');
    $table->attributes['class'] = 'standardtable generaltable';
    $table->size = array(null, '70%', null);

    // Print out the $studentdata array
    // Going through each student that has attempted the lesson, so, each student should have something to be displayed.
    foreach ($students as $student) {
        // Check to see if the student has attempts to print out.
        if (array_key_exists($student->id, $studentdata)) {
            // Set/reset some variables.
            $attempts = array();
            // Gather the data for each user attempt.
            $bestgrade = 0;
            $bestgradefound = false;
            // $tries holds all the tries/retries a student has done.
            $tries = $studentdata[$student->id];
            $studentname = "{$student->lastname},&nbsp;$student->firstname";
            foreach ($tries as $try) {
                // Start to build up the checkbox and link.
                if (has_capability('mod/languagelesson:edit', $context)) {
                    $temp = '<input type="checkbox" id="attempts" name="attempts['.$try['userid'].']['.$try['try'].']" /> ';
                } else {
                    $temp = '';
                }

                $temp .= "<a href=\"report.php?id=$cm->id&amp;action=reportdetail&amp;userid=".$try['userid'].'&amp;try='.$try['try'].'">';
                if ($try["grade"] !== null) { // If null then not done yet
                    // This is what the link does when the user has completed the try.
                    $timetotake = $try["timeend"] - $try["timestart"];

                    $temp .= $try["grade"]."%";
                    $bestgradefound = true;
                    if ($try["grade"] > $bestgrade) {
                        $bestgrade = $try["grade"];
                    }
                    $temp .= "&nbsp;".userdate($try["timestart"]);
                    $temp .= ",&nbsp;(".format_time($timetotake).")</a>";
                } else {
                    // This is what the link does/looks like when the user has not completed the try.
                    $temp .= get_string("notcompleted", "languagelesson");
                    $temp .= "&nbsp;".userdate($try["timestart"])."</a>";
                    $timetotake = null;
                }
                // Build up the attempts array.
                $attempts[] = $temp;

                // Run these lines for the stats only if the user finnished the lesson.
                if ($try["grade"] !== null) {
                    $numofattempts++;
                    $avescore += $try["grade"];
                    $avetime += $timetotake;
                    if ($try["grade"] > $highscore || $highscore == null) {
                        $highscore = $try["grade"];
                    }
                    if ($try["grade"] < $lowscore || $lowscore == null) {
                        $lowscore = $try["grade"];
                    }
                    if ($timetotake > $hightime || $hightime == null) {
                        $hightime = $timetotake;
                    }
                    if ($timetotake < $lowtime || $lowtime == null) {
                        $lowtime = $timetotake;
                    }
                }
            }
            // Get line breaks in after each attempt.
            $attempts = implode("<br />\n", $attempts);
            // Add it to the table data[] object.
            $table->data[] = array($studentname, $attempts, $bestgrade."%");
        }
    }
    // Print it all out !
    if (has_capability('mod/languagelesson:edit', $context)) {
        echo  "<form id=\"theform\" method=\"post\" action=\"report.php\">\n
               <input type=\"hidden\" name=\"sesskey\" value=\"".sesskey()."\" />\n
               <input type=\"hidden\" name=\"id\" value=\"$cm->id\" />\n";
    }
    echo html_writer::table($table);
    if (has_capability('mod/languagelesson:edit', $context)) {
        $checklinks  = '<a href="javascript: checkall();">'.get_string('selectall').'</a> / ';
        $checklinks .= '<a href="javascript: checknone();">'.get_string('deselectall').'</a>';
        $checklinks .= html_writer::select(array('delete' => get_string('deleteselected')), 'action', 0,
                                           array(''=>'choosedots'), array('id'=>'actionid'));
        $PAGE->requires->js_init_call('M.util.init_select_autosubmit', array('theform', 'actionid', ''));
        echo $OUTPUT->box($checklinks, 'center');
        echo '</form>';
    }

    // Some stat calculations.
    if ($numofattempts == 0) {
        $avescore = get_string("notcompleted", "languagelesson");
    } else {
        $avescore = format_float($avescore/$numofattempts, 2);
    }
    if ($avetime == null) {
        $avetime = get_string("notcompleted", "languagelesson");
    } else {
        $avetime = format_float($avetime/$numofattempts, 0);
        $avetime = format_time($avetime);
    }
    if ($hightime == null) {
        $hightime = get_string("notcompleted", "languagelesson");
    } else {
        $hightime = format_time($hightime);
    }
    if ($lowtime == null) {
        $lowtime = get_string("notcompleted", "languagelesson");
    } else {
        $lowtime = format_time($lowtime);
    }
    if ($highscore == null) {
        $highscore = get_string("notcompleted", "languagelesson");
    }
    if ($lowscore == null) {
        $lowscore = get_string("notcompleted", "languagelesson");
    }

    // Output the stats.
    echo $OUTPUT->heading(get_string('lessonstats', 'languagelesson'));
    $stattable = new html_table();
    $stattable->head = array(get_string('averagescore', 'languagelesson'), get_string('averagetime', 'languagelesson'),
                            get_string('highscore', 'languagelesson'), get_string('lowscore', 'languagelesson'),
                            get_string('hightime', 'languagelesson'), get_string('lowtime', 'languagelesson'));
    $stattable->align = array('center', 'center', 'center', 'center', 'center', 'center');
    $stattable->wrap = array('nowrap', 'nowrap', 'nowrap', 'nowrap', 'nowrap', 'nowrap');
    $stattable->attributes['class'] = 'standardtable generaltable';
    $stattable->data[] = array($avescore.'%', $avetime, $highscore.'%', $lowscore.'%', $hightime, $lowtime);

    echo html_writer::table($stattable);
} else if ($action === 'reportdetail') {
    /**************************************************************************
    this action is for a student detailed view and for the general detailed view

    General flow of this section of the code
    1.  Generate a object which holds values for the statistics for each question/answer
    2.  Cycle through all the pages to create a object.  Foreach page, see if the student actually answered
        the page.  Then process the page appropriatly.  Display all info about the question,
        Highlight correct answers, show how the user answered the question, and display statistics
        about each page
    3.  Print out info about the try (if needed)
    4.  Print out the object which contains all the try info

    **************************************************************************/
    echo $lessonoutput->header($lesson, $cm, $action);

    $coursecontext = context_course::instance($course->id);
    if (has_capability('gradereport/grader:view', $coursecontext) && has_capability('moodle/grade:viewall', $coursecontext)) {
        $seeallgradeslink = new moodle_url('/grade/report/grader/index.php', array('id'=>$course->id));
        $seeallgradeslink = html_writer::link($seeallgradeslink, get_string('seeallcoursegrades', 'grades'));
        echo $OUTPUT->box($seeallgradeslink, 'allcoursegrades');
    }

    $formattextdefoptions = new stdClass;
    $formattextdefoptions->para = false;  // I'll use it widely in this page.
    $formattextdefoptions->overflowdiv = true;

    $userid = optional_param('userid', null, PARAM_INT); // If empty, then will display the general detailed view.
    $try    = optional_param('try', null, PARAM_INT);

    $lessonpages = $lesson->load_all_pages();
    foreach ($lessonpages as $lessonpage) {
        if ($lessonpage->prevpageid == 0) {
            $pageid = $lessonpage->id;
        }
    }

    // Now gather the stats into an object.
    $firstpageid = $pageid;
    $pagestats = array();
    while ($pageid != 0) { // EOL.
        $page = $lessonpages[$pageid];
        $params = array ("lessonid" => $lesson->id, "pageid" => $page->id);
        if ($allanswers = $DB->get_records_select("languagelesson_attempts", "lessonid = :lessonid AND pageid = :pageid", $params, "timeseen")) {
            // Get them ready for processing.
            $orderedanswers = array();
            foreach ($allanswers as $singleanswer) {
                // Ordering them like this, will help to find the single attempt record that we want to keep.
                $orderedanswers[$singleanswer->userid][$singleanswer->retry][] = $singleanswer;
            }
            // This is foreach user and for each try for that user, keep one attempt record.
            foreach ($orderedanswers as $orderedanswer) {
                foreach ($orderedanswer as $tries) {
                    $page->stats($pagestats, $tries);
                }
            }
        } else {
            // No one answered yet...
        }
        // unset($orderedanswers);  initialized above now.
        $pageid = $page->nextpageid;
    }

    $manager = languagelesson_page_type_manager::get($lesson);
    $qtypes = $manager->get_page_type_strings();

    $answerpages = array();
    $answerpage = "";
    $pageid = $firstpageid;
    // CYcle through all the pages
    //  foreach page, add to the $answerpages[] array all the data that is needed
    //  from the question, the users attempt, and the statistics.
    // Grayout pages that the user did not answer and Branch, end of branch, cluster
    // And end of cluster pages.
    while ($pageid != 0) { // EOL.
        $page = $lessonpages[$pageid];
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

        $answerpage->qtype = $qtypes[$page->qtype].$page->option_description_string();
        $answerpage->grayout = $page->grayout;
        $answerpage->context = $context;

        if (empty($userid)) {
            // There is no userid, so set these vars and display stats.
            $answerpage->grayout = 0;
            $useranswer = null;
        } else if ($useranswers = $DB->get_records("languagelesson_attempts",
                            array("lessonid"=>$lesson->id, "userid"=>$userid, "retry"=>$try, "pageid"=>$page->id), "timeseen")) {
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
        } else {
            // User did not answer this page, gray it out and set some nulls.
            $answerpage->grayout = 1;
            $useranswer = null;
        }
        $i = 0;
        $n = 0;
        $answerpages[] = $page->report_answers(clone($answerpage), clone($answerdata), $useranswer, $pagestats, $i, $n);
        $pageid = $page->nextpageid;
    }

    // Actually start printing something.
    $table = new html_table();
    $table->wrap = array();
    $table->width = "60%";
    if (!empty($userid)) {
        // If looking at a students try, print out some basic stats at the top.

        echo $OUTPUT->heading(get_string('attempt', 'languagelesson', $try+1));

        $table->head = array();
        $table->align = array('right', 'left');
        $table->attributes['class'] = 'compacttable generaltable';

        $params = array("lessonid"=>$lesson->id, "userid"=>$userid);
        if (!$grades = $DB->get_records_select("languagelesson_grades", "lessonid = :lessonid and userid = :userid", $params, "completed", "*", $try, 1)) {
            $grade = -1;
            $completed = -1;
        } else {
            $grade = current($grades);
            $completed = $grade->completed;
            $grade = round($grade->grade, 2);
        }
        if (!$times = $DB->get_records_select("languagelesson_timer", "lessonid = :lessonid and userid = :userid", $params, "starttime", "*", $try, 1)) {
            $timetotake = -1;
        } else {
            $timetotake = current($times);
            $timetotake = $timetotake->lessontime - $timetotake->starttime;
        }

        if ($timetotake == -1 || $completed == -1 || $grade == -1) {
            $table->align = array("center");

            $table->data[] = array(get_string("notcompleted", "languagelesson"));
        } else {
            $user = $students[$userid];

            $gradeinfo = languagelesson_grade($lesson, $try, $user->id);

            $table->data[] = array(get_string('name').':', $OUTPUT->user_picture($user, array('courseid'=>$course->id)).fullname($user, true));
            $table->data[] = array(get_string("timetaken", "languagelesson").":", format_time($timetotake));
            $table->data[] = array(get_string("completed", "languagelesson").":", userdate($completed));
            $table->data[] = array(get_string('rawgrade', 'languagelesson').':', $gradeinfo->earned.'/'.$gradeinfo->total);
            $table->data[] = array(get_string("grade", "languagelesson").":", $grade."%");
        }
        echo html_writer::table($table);

        // Don't want this class for later tables.
        $table->attributes['class'] = '';
    }

    $table->align = array('left', 'left');
    $table->size = array('70%', null);
    $table->attributes['class'] = 'compacttable generaltable';

    foreach ($answerpages as $page) {
        unset($table->data);
        if ($page->grayout) { // Set the color of text.
            $fontstart = "<span class=\"dimmed\">";
            $fontend = "</font>";
            $fontstart2 = $fontstart;
            $fontend2 = $fontend;
        } else {
            $fontstart = "";
            $fontend = "";
            $fontstart2 = "";
            $fontend2 = "";
        }

        $table->head = array($fontstart2.$page->qtype.": ".format_string($page->title).$fontend2,
                             $fontstart2.get_string("classstats", "languagelesson").$fontend2);
        $table->data[] = array($fontstart.get_string("question", "languagelesson").": <br />".$fontend.
                               $fontstart2.$page->contents.$fontend2, " ");
        $table->data[] = array($fontstart.get_string("answer", "languagelesson").":".$fontend, ' ');
        // Apply the font to each answer.
        if (!empty($page->answerdata)) {
            foreach ($page->answerdata->answers as $answer) {
                $modified = array();
                foreach ($answer as $single) {
                    // Need to apply a font to each one.
                    $modified[] = $fontstart2.$single.$fontend2;
                }
                $table->data[] = $modified;
            }
            if (isset($page->answerdata->response)) {
                $table->data[] = array($fontstart.get_string("response", "languagelesson").": <br />".$fontend.
                    $fontstart2.format_text($page->answerdata->response, $page->answerdata->responseformat, $formattextdefoptions).$fontend2, " ");
            }
            $table->data[] = array($page->answerdata->score, " ");
        } else {
            $table->data[] = array(get_string('didnotanswerquestion', 'languagelesson'), " ");
        }
        echo html_writer::table($table);
    }
} else {
    print_error('unknowaction');
}

// Finish the page.
echo $OUTPUT->footer();
