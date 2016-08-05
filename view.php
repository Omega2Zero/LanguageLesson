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
 * This page prints a particular instance of lesson
 *
 * @package    mod
 * @subpackage lesson
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or late
 **/

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot.'/mod/languagelesson/locallib.php');
require_once($CFG->dirroot.'/mod/languagelesson/view_form.php');
require_once($CFG->libdir . '/completionlib.php');

$id      = required_param('id', PARAM_INT);             // Course Module ID.
$pageid  = optional_param('pageid', null, PARAM_INT);   // Lesson Page ID.
$edit    = optional_param('edit', -1, PARAM_BOOL);
$userpassword = optional_param('userpassword', '', PARAM_RAW);
$backtocourse = optional_param('backtocourse', false, PARAM_RAW);

$cm = get_coursemodule_from_id('languagelesson', $id, 0, false, MUST_EXIST);;
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$lesson = new languagelesson($DB->get_record('languagelesson', array('id' => $cm->instance), '*', MUST_EXIST));

require_login($course, false, $cm);

if ($backtocourse) {
    redirect(new moodle_url('/course/view.php', array('id'=>$course->id)));
}

// Mark as viewed.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$url = new moodle_url('/mod/languagelesson/view.php', array('id'=>$id));
if ($pageid !== null) {
    $url->param('pageid', $pageid);
}
$PAGE->set_url($url);

$context = context_module::instance($cm->id);
$canmanage = has_capability('mod/languagelesson:manage', $context);

$lessonoutput = $PAGE->get_renderer('mod_languagelesson');

$reviewmode = false;
$userhasgrade = $DB->count_records("languagelesson_grades", array("lessonid"=>$lesson->id, "userid"=>$USER->id));
if ($userhasgrade && !$lesson->retake) {
    $reviewmode = true;
}

if ($lesson->maxattempts != 0 ) {
    if ($nretakes = $DB->get_field('languagelesson_attempts', 'retry', array('lessonid'=>$lesson->id, 'userid'=>$USER->id, 'pageid'=>$pageid))) {
        if ($nretakes >= $lesson->maxattempts) {
            $reviewmode = true;
            $lesson->add_message(get_string('maximumnumberofattemptsreached', 'languagelesson'));  // Maxattempts have been reached.
        }
    }
}


// Check these for students only TODO: Find a better method for doing this!
//     Check lesson availability.
//     Check for password.
//     Check dependencies.
//     Check for high scores.
if (!$canmanage) {
    if (!$lesson->is_accessible()) {  // Deadline restrictions.
        echo $lessonoutput->header($lesson, $cm);
        if ($lesson->deadline != 0 && time() > $lesson->deadline) {
            echo $lessonoutput->languagelesson_inaccessible(get_string('lessonclosed', 'languagelesson', userdate($lesson->deadline)));
        } else {
            echo $lessonoutput->languagelesson_inaccessible(get_string('lessonopen', 'languagelesson', userdate($lesson->available)));
        }
        echo $lessonoutput->footer();
        exit();
    } else if ($lesson->usepassword && empty($USER->lessonloggedin[$lesson->id])) { // Password protected lesson code.
        $correctpass = false;
        if (!empty($userpassword) && (($lesson->password == md5(trim($userpassword))) || ($lesson->password == trim($userpassword)))) {
            // With or without md5 for backward compatibility (MDL-11090).
            $USER->lessonloggedin[$lesson->id] = true;
            if ($lesson->highscores) {
                // Logged in - redirect so we go through all of these checks before starting the lesson.
                redirect("$CFG->wwwroot/mod/languagelesson/view.php?id=$cm->id");
            }
        } else {
            echo $lessonoutput->header($lesson, $cm);
            echo $lessonoutput->login_prompt($lesson, $userpassword !== '');
            echo $lessonoutput->footer();
            exit();
        }
    } else if ($lesson->dependency) { // Check for dependencies.
        if ($dependentlesson = $DB->get_record('languagelesson', array('id' => $lesson->dependency))) {
            // Lesson exists, so we can proceed.
            $conditions = unserialize($lesson->conditions);
            // Assume false for all.
            $errors = array();

            // Check for the timespent condition.
            if ($conditions->timespent) {
                $timespent = false;
                if ($attempttimes = $DB->get_records('languagelesson_timer', array("userid"=>$USER->id, "lessonid"=>$dependentlesson->id))) {
                    // Go through all the times and test to see if any of them satisfy the condition.
                    foreach ($attempttimes as $attempttime) {
                        $duration = $attempttime->lessontime - $attempttime->starttime;
                        if ($conditions->timespent < $duration/60) {
                            $timespent = true;
                        }
                    }
                }
                if (!$timespent) {
                    $errors[] = get_string('timespenterror', 'languagelesson', $conditions->timespent);
                }
            }

            // Check for the gradebetterthan condition.
            if ($conditions->gradebetterthan) {
                $gradebetterthan = false;
                if ($studentgrades = $DB->get_records('languagelesson_grades', array("userid"=>$USER->id, "lessonid"=>$dependentlesson->id))) {
                    // Go through all the grades and test to see if any of them satisfy the condition.
                    foreach ($studentgrades as $studentgrade) {
                        if ($studentgrade->grade >= $conditions->gradebetterthan) {
                            $gradebetterthan = true;
                        }
                    }
                }
                if (!$gradebetterthan) {
                    $errors[] = get_string('gradebetterthanerror', 'languagelesson', $conditions->gradebetterthan);
                }
            }

            // Check for the completed condition.
            if ($conditions->completed) {
                if (!$DB->count_records('languagelesson_grades', array('userid'=>$USER->id, 'lessonid'=>$dependentlesson->id))) {
                    $errors[] = get_string('completederror', 'languagelesson');
                }
            }

            if (!empty($errors)) {  // Print out the errors if any.
                echo $lessonoutput->header($lesson, $cm);
                echo $lessonoutput->dependancy_errors($dependentlesson, $errors);
                echo $lessonoutput->footer();
                exit();
            }
        }
    } else if ($lesson->highscores && !$lesson->practice && !optional_param('viewed', 0, PARAM_INT) && empty($pageid)) {
        // Display high scores before starting lesson.
        redirect(new moodle_url('/mod/languagelesson/highscores.php', array("id"=>$cm->id)));
    }
}

    // This is called if a student leaves during a lesson.
if ($pageid == LL_UNSEENBRANCHPAGE) {
    $pageid = languagelesson_unseen_question_jump($lesson, $USER->id, $pageid);
}

// Display individual pages and their sets of answers
// if pageid is EOL then the end of the lesson has been reached
// for flow, changed to simple echo for flow styles, michaelp, moved lesson name and page title down.
$attemptflag = false;
if (empty($pageid)) {
    // Make sure there are pages to view.
    if (!$DB->get_field('languagelesson_pages', 'id', array('lessonid' => $lesson->id, 'prevpageid' => 0))) {
        if (!$canmanage) {
            $lesson->add_message(get_string('lessonnotready2', 'languagelesson')); // A nice message to the student.
        } else {
            if (!$DB->count_records('languagelesson_pages', array('lessonid'=>$lesson->id))) {
                redirect("$CFG->wwwroot/mod/languagelesson/edit.php?id=$cm->id"); // No pages - redirect to add pages.
            } else {
                $lesson->add_message(get_string('lessonpagelinkingbroken', 'languagelesson'));  // Ok, bad mojo.
            }
        }
    }
    
    $start_event = \mod_languagelesson\event\lesson_started::create(array(
        'objectid' => $lesson->id,
        'context' => context_module::instance($cm->id)
    ));
    $start_event->trigger();

    // Iif no pageid given see if the lesson has been started.
    $retries = $DB->count_records('languagelesson_grades', array("lessonid" => $lesson->id, "userid" => $USER->id));
    if ($retries > 0) {
        $attemptflag = true;
    }


    // If there are any questions have been answered correctly in this attempt.
    $corrrectattempts = $lesson->get_attempts($retries, true);
    if (!empty($corrrectattempts)) {
        $attempt = end($corrrectattempts);
        $jumpto = $DB->get_field('languagelesson_answers', 'jumpto', array('id' => $attempt->answerid));
        // Convert the jumpto to a proper page id.
        if ($jumpto == 0) { // Unlikely value!
            $lastpageseen = $attempt->pageid;
        } else if ($jumpto == LL_NEXTPAGE) {
            if (!$lastpageseen = $DB->get_field('languagelesson_pages', 'nextpageid', array('id' => $attempt->pageid))) {
                // No nextpage go to end of lesson.
                $lastpageseen = LL_EOL;
            }
        } else {
            $lastpageseen = $jumpto;
        }
    }

    if ($branchtables = $DB->get_records('languagelesson_branch',
                                         array("lessonid"=>$lesson->id, "userid"=>$USER->id, "retry"=>$retries), 'timeseen DESC')) {
        // In here, user has viewed a branch table.
        $lastbranchtable = current($branchtables);
        if (count($corrrectattempts)>0) {
            foreach ($corrrectattempts as $attempt) {
                if ($lastbranchtable->timeseen > $attempt->timeseen) {
                    // Branch table was viewed later than the last attempt.
                    $lastpageseen = $lastbranchtable->pageid;
                }
                break;
            }
        } else {
            // Hasnt answered any questions but has viewed a branch table.
            $lastpageseen = $lastbranchtable->pageid;
        }
    }

    if ($attemptflag) {
        if (!$lesson->retake) {
            echo $lessonoutput->header($lesson, $cm, 'view');
            $courselink = new single_button(new moodle_url('/course/view.php',
                                            array('id'=>$PAGE->course->id)), get_string('returntocourse', 'languagelesson'), 'get');
            echo $lessonoutput->message(get_string("noretake", "languagelesson"), $courselink);
            echo $lessonoutput->footer();
            exit();
        }
    }

    // Start at the first page.
    if (!$pageid = $DB->get_field('languagelesson_pages', 'id', array('lessonid' => $lesson->id, 'prevpageid' => 0))) {
            print_error('cannotfindfirstpage', 'languagelesson');
    }
    // This is the code for starting a timed test.
    if (!isset($USER->startlesson[$lesson->id]) && !$canmanage) {
        $lesson->start_timer();
    }
}

$currenttab = 'view';
$extraeditbuttons = false;
$lessonpageid = null;
$timer = null;

if ($pageid != LL_EOL) {
    // This is the code updates the lessontime for a timed test.
    $startlastseen = optional_param('startlastseen', '', PARAM_ALPHA);

    $page = $lesson->load_page($pageid);

    // Check if the page is of a special type and if so take any nessecary action.
    $newpageid = $page->callback_on_view($canmanage);
    if (is_numeric($newpageid)) {
        $page = $lesson->load_page($newpageid);
    }
    
    $view_event = \mod_languagelesson\event\course_module_viewed::create(array(
        'objectid' => $page->id,
        'context' => context_module::instance($PAGE->cm->id)
    ));
    $view_event->add_record_snapshot('course', $PAGE->course);
    $view_event->trigger();

    // This is where several messages (usually warnings) are displayed
    // all of this is displayed above the actual page.

    // Check to see if the user can see the left menu.
    if (!$canmanage) {
        $lesson->displayleft = languagelesson_displayleftif($lesson);

        $continue = ($startlastseen !== '');
        $restart  = ($continue && $startlastseen == 'yes');
        $timer = $lesson->update_timer($continue, $restart);

        if ($lesson->timed) {
            $timeleft = ($timer->starttime + $lesson->maxtime * 60) - time();
            if ($timeleft <= 0) {
                // Out of time.
                $lesson->add_message(get_string('eolstudentoutoftime', 'languagelesson'));
                redirect(new moodle_url('/mod/languagelesson/view.php', array('id'=>$cm->id, 'pageid'=>LL_EOL, 'outoftime'=>'normal')));
                die; // Shouldn't be reached, but make sure.
            } else if ($timeleft < 60) {
                // One minute warning.
                $lesson->add_message(get_string('studentoneminwarning', 'languagelesson'));
            }
        }

        if ($page->qtype == LL_BRANCHTABLE && $lesson->minquestions) {
            // Tell student how many questions they have seen, how many are required and their grade.
            $ntries = $DB->count_records("languagelesson_grades", array("lessonid"=>$lesson->id, "userid"=>$USER->id));
            $gradeinfo = languagelesson_grade($lesson, $ntries);
            if ($gradeinfo->attempts) {
                if ($gradeinfo->nquestions < $lesson->minquestions) {
                    $a = new stdClass;
                    $a->nquestions   = $gradeinfo->nquestions;
                    $a->minquestions = $lesson->minquestions;
                    $lesson->add_message(get_string('numberofpagesviewednotice', 'languagelesson', $a));
                }

                $a = new stdClass;
                $a->grade = number_format($gradeinfo->grade * $lesson->grade / 100, 1);
                $a->total = $lesson->grade;
                if (!$reviewmode && !$lesson->retake) {
                    $lesson->add_message(get_string("numberofcorrectanswers", "languagelesson", $gradeinfo->earned), 'notify');
                    $lesson->add_message(get_string('yourcurrentgradeisoutof', 'languagelesson', $a), 'notify');
                }
            }
        }
    } else {
        $timer = null;
        if ($lesson->timed) {
            $lesson->add_message(get_string('teachertimerwarning', 'languagelesson'));
        }
        if (languagelesson_display_teacher_warning($lesson)) {
            // This is the warning msg for teachers to inform them that cluster
            // and unseen does not work while logged in as a teacher.
            $warningvars->cluster = get_string('clusterjump', 'languagelesson');
            $warningvars->unseen = get_string('unseenpageinbranch', 'languagelesson');
            $lesson->add_message(get_string('teacherjumpwarning', 'languagelesson', $warningvars));
        }
    }

    $PAGE->set_url('/mod/languagelesson/view.php', array('id' => $cm->id, 'pageid' => $page->id));
    $PAGE->set_subpage($page->id);
    $currenttab = 'view';
    $extraeditbuttons = true;
    $lessonpageid = $page->id;

    if (($edit != -1) && $PAGE->user_allowed_editing()) {
        $USER->editing = $edit;
    }

    if (is_array($page->answers) && count($page->answers)>0) {
        // This is for modattempts option.  Find the users previous answer to this page,
        //   and then display it below in answer processing.
        $attempt = $DB->get_record('languagelesson_attempts', array('lessonid'=>$lesson->id, 'userid'=>$USER->id, 'pageid'=>$pageid));

        $USER->modattempts[$lesson->id] = $attempt;
        $lessoncontent = $lessonoutput->display_page($lesson, $page, $attempt);
    } else {
        $data = new stdClass;
        $data->id = $PAGE->cm->id;
        $data->pageid = $page->id;
        $data->newpageid = $lesson->get_next_page($page->nextpageid);

        $customdata = array(
            'title'     => $page->title,
            'contents'  => $page->get_contents()
        );
        // $mform = new languagelesson_page($CFG->wwwroot.'/mod/languagelesson/continue.php', $customdata);
        $mform = new languagelesson_page_without_answers($CFG->wwwroot.'/mod/languagelesson/continue.php', $customdata);
        $mform->set_data($data);
        ob_start();
        $mform->display();
        $lessoncontent = ob_get_contents();
        ob_end_clean();
    }

    languagelesson_add_fake_blocks($PAGE, $cm, $lesson, $timer);
    echo $lessonoutput->header($lesson, $cm, $currenttab, $extraeditbuttons, $lessonpageid);
    if ($attemptflag) {
        // We are using level 3 header because attempt heading is a sub-heading of lesson title (MDL-30911).
        // echo $OUTPUT->heading(get_string('attempt', 'languagelesson', $retries), 3);
    }
    // This calculates and prints the ongoing score.
    if ($lesson->ongoing && !empty($pageid) && !$reviewmode) {
        echo $lessonoutput->ongoing_score($lesson);
    }
    if ($lesson->displayleft) {
        echo '<a name="maincontent" id="maincontent" title="' . get_string('anchortitle', 'languagelesson') . '"></a>';
    }
    echo $lessoncontent;
    // echo $lessonoutput->slideshow_end();
    echo $lessonoutput->progress_bar($lesson);
    echo $lessonoutput->footer();

} else {

    $lessoncontent = '';
    // End of lesson reached work out grade.
    // Used to check to see if the student ran out of time.
    $outoftime = optional_param('outoftime', '', PARAM_ALPHA);

    // Update the clock / get time information for this user.
    $view_event = \mod_languagelesson\event\lesson_ended::create(array(
        'objectid' => $lesson->id,
        'context' => context_module::instance($PAGE->cm->id)
    ));
    $view_event->trigger();

    // We are using level 3 header because the page title is a sub-heading of lesson title (MDL-30911).
    $lessoncontent .= $OUTPUT->heading(get_string("congratulations", "languagelesson"), 3);
    $lessoncontent .= $OUTPUT->box_start('generalbox boxaligncenter');
    $ntries = $DB->count_records("languagelesson_grades", array("lessonid"=>$lesson->id, "userid"=>$USER->id));
    if (isset($USER->modattempts[$lesson->id])) {
        $ntries--;  // Need to look at the old attempts :).
    }
    if (!$canmanage) {
        $lesson->stop_timer();
        $gradeinfo = languagelesson_grade($lesson, $ntries);
        print_object($gradeinfo);
        if ($gradeinfo->attempts) {
            if (!$lesson->custom) {
                $lessoncontent .= $lessonoutput->paragraph(get_string("numberofpagesviewed", "languagelesson", $gradeinfo->nquestions), 'center');
                if ($lesson->minquestions) {
                    if ($gradeinfo->nquestions < $lesson->minquestions) {
                        // Print a warning and set nviewed to minquestions.
                        $lessoncontent .= $lessonoutput->paragraph(get_string("youshouldview", "languagelesson", $lesson->minquestions), 'center');
                    }
                }
                $lessoncontent .= $lessonoutput->paragraph(get_string("numberofcorrectanswers", "languagelesson", $gradeinfo->earned), 'center');
            }
            $a = new stdClass;
            $a->score = $gradeinfo->earned;
            $a->grade = $gradeinfo->total;
            if ($gradeinfo->nmanual) {
                $a->tempmaxgrade = $gradeinfo->total - $gradeinfo->manualpoints;
                $a->essayquestions = $gradeinfo->nmanual;
                $lessoncontent .= $OUTPUT->box(get_string("displayscorewithessays", "languagelesson", $a), 'center');
            } else {
                $lessoncontent .= $OUTPUT->box(get_string("displayscorewithoutessays", "languagelesson", $a), 'center');
            }
            $a = new stdClass;
            $a->grade = number_format($gradeinfo->grade * $lesson->grade / 100, 1);
            $a->total = $lesson->grade;
            $lessoncontent .= $lessonoutput->paragraph(get_string("yourcurrentgradeisoutof", "languagelesson", $a), 'center');

            $grade = new stdClass();
            $grade->lessonid = $lesson->id;
            $grade->userid = $USER->id;
            $grade->grade = $gradeinfo->grade;
            $grade->completed = time();
            if (!$lesson->practice) {
                if (isset($USER->modattempts[$lesson->id])) { // If reviewing, make sure update old grade record.
                    if (!$grades = $DB->get_records("languagelesson_grades",
                                        array("lessonid" => $lesson->id, "userid" => $USER->id), "completed DESC", '*', 0, 1)) {
                        print_error('cannotfindgrade', 'languagelesson');
                    }
                    $oldgrade = array_shift($grades);
                    $grade->id = $oldgrade->id;
                    $DB->update_record("languagelesson_grades", $grade);
                } else {
                    $newgradeid = $DB->insert_record("languagelesson_grades", $grade);
                }
            } else {
                $DB->delete_records("languagelesson_attempts", array("lessonid" => $lesson->id, "userid" => $USER->id, "retry" => $ntries));
            }
        } else {
            if ($lesson->timed) {
                if ($outoftime == 'normal') {
                    $grade = new stdClass();;
                    $grade->lessonid = $lesson->id;
                    $grade->userid = $USER->id;
                    $grade->grade = 0;
                    $grade->completed = time();
                    if (!$lesson->practice) {
                        $newgradeid = $DB->insert_record("languagelesson_grades", $grade);
                    }
                    $lessoncontent .= get_string("eolstudentoutoftimenoanswers", "languagelesson");
                }
            } else {
                $lessoncontent .= get_string("welldone", "languagelesson");
            }
        }

        // Update central gradebook.
        languagelesson_update_grades($lesson, $USER->id);

    } else {
        // Display for teacher.
        $lessoncontent .= $lessonoutput->paragraph(get_string("displayofgrade", "languagelesson"), 'center');
    }
    $lessoncontent .= $OUTPUT->box_end(); // End of Lesson button to Continue.

    // After all the grade processing, check to see if "Show Grades" is off for the course
    // if yes, redirect to the course page.
    if (!$course->showgrades) {
        redirect(new moodle_url('/course/view.php', array('id'=>$course->id)));
    }

    // High scores code.
    if ($lesson->highscores && !$canmanage && !$lesson->practice) {
        $lessoncontent .= $OUTPUT->box_start('center');
        if ($grades = $DB->get_records("languagelesson_grades", array("lessonid" => $lesson->id), "completed")) {
            $madeit = false;
            if ($highscores = $DB->get_records("languagelesson_high_scores", array("lessonid" => $lesson->id))) {
                // Get all the high scores into an array.
                $topscores = array();
                $uniquescores = array();
                foreach ($highscores as $highscore) {
                    $grade = $grades[$highscore->gradeid]->grade;
                    $topscores[] = $grade;
                    $uniquescores[$grade] = 1;
                }
                // Sort to find the lowest score.
                sort($topscores);
                $lowscore = $topscores[0];

                if ($gradeinfo->grade >= $lowscore || count($uniquescores) <= $lesson->maxhighscores) {
                    $madeit = true;
                }
            }
            if (!$highscores or $madeit) {
                $lessoncontent .= $lessonoutput->paragraph(get_string("youmadehighscore", "languagelesson", $lesson->maxhighscores), 'center');
                $aurl = new moodle_url('/mod/languagelesson/highscores.php', array('id'=>$PAGE->cm->id, 'sesskey'=>sesskey()));
                $lessoncontent .= $OUTPUT->single_button($aurl, get_string('clicktopost', 'languagelesson'));
            } else {
                $lessoncontent .= get_string("nothighscore", "languagelesson", $lesson->maxhighscores)."<br />";
            }
        }
        $url = new moodle_url('/mod/languagelesson/highscores.php', array('id'=>$PAGE->cm->id, 'link'=>'1'));
        $lessoncontent .= html_writer::link($url, get_string('viewhighscores', 'languagelesson'),
                                            array('class'=>'centerpadded lessonbutton standardbutton'));
        $lessoncontent .= $OUTPUT->box_end();
    }

    if ($lesson->modattempts && !$canmanage) {
        // Make sure if the student is reviewing, that he/she sees the same pages/page path that he/she saw the first time
        // look at the attempt records to find the first QUESTION page that the user answered, then use that page id
        // to pass to view again.  This is slick cause it wont call the empty($pageid) code
        // $ntries is decremented above.
        if (!$attempts = $lesson->get_attempts($ntries)) {
            $attempts = array();
            $url = new moodle_url('/mod/languagelesson/view.php', array('id'=>$PAGE->cm->id));
        } else {
            $firstattempt = current($attempts);
            $pageid = $firstattempt->pageid;
            // IF the student wishes to review, need to know the last question page that the student answered.  This will help to make
            // sure that the student can leave the lesson via pushing the continue button.
            $lastattempt = end($attempts);
            $USER->modattempts[$lesson->id] = $lastattempt->pageid;

            $url = new moodle_url('/mod/languagelesson/view.php', array('id'=>$PAGE->cm->id, 'pageid'=>$pageid));
        }
        $lessoncontent .= html_writer::link($url, get_string('reviewlesson', 'languagelesson'),
                                            array('class' => 'centerpadded lessonbutton standardbutton'));
    } else if ($lesson->modattempts && $canmanage) {
        $lessoncontent .= $lessonoutput->paragraph(get_string("modattemptsnoteacher", "languagelesson"), 'centerpadded');
    }

    if ($lesson->activitylink) {
        $lessoncontent .= $lesson->link_for_activitylink();
    }

    $url = new moodle_url('/course/view.php', array('id'=>$course->id));
    $lessoncontent .= html_writer::link($url, get_string('returnto', 'languagelesson',
                            format_string($course->fullname, true)), array('class'=>'centerpadded lessonbutton standardbutton'));

    $url = new moodle_url('/grade/index.php', array('id'=>$course->id));
    $lessoncontent .= html_writer::link($url, get_string('viewgrades', 'languagelesson'),
                                        array('class'=>'centerpadded lessonbutton standardbutton'));

    languagelesson_add_fake_blocks($PAGE, $cm, $lesson, $timer);
    echo $lessonoutput->header($lesson, $cm, $currenttab, $extraeditbuttons, $lessonpageid);
    echo $lessoncontent;
    echo $lessonoutput->footer();
}