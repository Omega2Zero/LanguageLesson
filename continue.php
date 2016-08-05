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
 * Action for processing page answers by users
 *
 * @package    mod
 * @subpackage lesson
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

/** Require the specific libraries */
require_once("../../config.php");
require_once($CFG->dirroot.'/mod/languagelesson/locallib.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('languagelesson', $id, 0, false, MUST_EXIST);;
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$lesson = new languagelesson($DB->get_record('languagelesson', array('id' => $cm->instance), '*', MUST_EXIST));

require_login($course, false, $cm);
require_sesskey();

$context = context_module::instance($cm->id);
$canmanage = has_capability('mod/languagelesson:manage', $context);
$lessonoutput = $PAGE->get_renderer('mod_languagelesson');


$url = new moodle_url('/mod/languagelesson/continue.php', array('id'=>$cm->id));
$PAGE->set_url($url);
$PAGE->navbar->add(get_string('continue', 'languagelesson'));

// This is the code updates the lesson time for a timed test
// get time information for this user.
if (!$canmanage) {
    $lesson->displayleft = languagelesson_displayleftif($lesson);
    $timer = $lesson->update_timer();
    if ($lesson->timed) {
        $timeleft = ($timer->starttime + $lesson->maxtime * 60) - time();
        if ($timeleft <= 0) {
            // Out of time.
            $lesson->add_message(get_string('eolstudentoutoftime', 'languagelesson'));
            redirect(new moodle_url('/mod/languagelesson/view.php', array('id'=>$cm->id, 'pageid'=>LL_EOL, 'outoftime'=>'normal')));
        } else if ($timeleft < 60) {
            // One minute warning.
            $lesson->add_message(get_string("studentoneminwarning", "languagelesson"));
        }
    }
} else {
    $timer = new stdClass;
}

// Record answer (if necessary) and show response (if none say if answer is correct or not).
$page = $lesson->load_page(required_param('pageid', PARAM_INT));

$userhasgrade = $DB->count_records("languagelesson_grades", array("lessonid"=>$lesson->id, "userid"=>$USER->id));
$reviewmode = false;
if ($userhasgrade && !$lesson->retake) {
    $reviewmode = true;
}

// Check the page has answers [MDL-25632].
if ($page->answers !== null) {
    if (count($page->answers) > 0) {
        $result = $page->record_attempt($context);
    } else {
        // The page has no answers so we will just progress to the next page in the
        // sequence (as set by newpageid).
        $result = new stdClass;
        $result->newpageid       = optional_param('newpageid', $page->nextpageid, PARAM_INT);
        $result->nodefaultresponse  = true;
        $result->typeid = "";
    }
}


if (isset($USER->modattempts[$lesson->id])) {
    if ($result->typeid == LL_AUDIO) {
            redirect(new moodle_url('/mod/languagelesson/view.php', array('id'=>$cm->id, 'pageid'=>$page->id)));
    } else {
        $nretakes = $DB->count_records("languagelesson_grades", array("lessonid"=>$lesson->id, "userid"=>$USER->id));
        $nretakes--; // Make sure we are looking at the right try.
        $attempts = $DB->get_records("languagelesson_attempts",
                array("lessonid"=>$lesson->id, "userid"=>$USER->id, "retry"=>$nretakes), "timeseen", "id, pageid");
        $found = false;
        $temppageid = 0;
        foreach ($attempts as $attempt) {
            if ($found && $temppageid != $attempt->pageid) {
                // Now try to find the next page, make sure next few attempts do no belong to current page.
                $result->newpageid = $attempt->pageid;
                break;
            }
            if ($attempt->pageid == $page->id) {
                $found = true; // If found current page.
                $temppageid = $attempt->pageid;
            }
        }
    }
} else if ($result->newpageid != LL_CLUSTERJUMP && $page->id != 0 && $result->newpageid > 0) {
    // Going to check to see if the page that the user is going to view next, is a cluster page.
    // If so, dont display, go into the cluster.  The $result->newpageid > 0 is used to filter out all of the negative code jumps.
    if ($result->typeid == LL_AUDIO) {
        redirect(new moodle_url('/mod/languagelesson/view.php', array('id'=>$cm->id, 'pageid'=>$page->id)));
    } else {
        $newpage = $lesson->load_page($result->newpageid);
        if ($newpageid = $newpage->override_next_page($result->newpageid)) {
            $result->newpageid = $newpageid;
        }
    }

} else if ($result->newpageid == LL_UNSEENBRANCHPAGE) {
    if ($canmanage) {
        if ($page->nextpageid == 0) {
            $result->newpageid = LL_EOL;
        } else {
            $result->newpageid = $page->nextpageid;
        }
    } else {
        $result->newpageid = languagelesson_unseen_question_jump($lesson, $USER->id, $page->id);
    }
} else if ($result->newpageid == LL_PREVIOUSPAGE) {
    $result->newpageid = $page->prevpageid;
} else if ($result->newpageid == LL_RANDOMPAGE) {
    $result->newpageid = languagelesson_random_question_jump($lesson, $page->id);
} else if ($result->newpageid == LL_CLUSTERJUMP) {
    if ($canmanage) {
        if ($page->nextpageid == 0) {  // If teacher, go to next page.
            $result->newpageid = LL_EOL;
        } else {
            $result->newpageid = $page->nextpageid;
        }
    } else {
        $result->newpageid = $lesson->cluster_jump($page->id);
    }
}

if ($result->nodefaultresponse) {
    // Don't display feedback.
    redirect(new moodle_url('/mod/languagelesson/view.php', array('id'=>$cm->id, 'pageid'=>$page->nextpageid)));
}

// Set Messages.

if ($canmanage) {
    // This is the warning msg for teachers to inform them that cluster and unseen does not work while logged in as a teacher.
    if (languagelesson_display_teacher_warning($lesson)) {
        $warningvars = new stdClass();
        $warningvars->cluster = get_string("clusterjump", "languagelesson");
        $warningvars->unseen = get_string("unseenpageinbranch", "languagelesson");
        $lesson->add_message(get_string("teacherjumpwarning", "languagelesson", $warningvars));
    }
    // Inform teacher that s/he will not see the timer.
    if ($lesson->timed) {
        $lesson->add_message(get_string("teachertimerwarning", "languagelesson"));
    }
}
// Report attempts remaining.
if ($result->attemptsremaining != 0 && !$lesson->review && !$reviewmode) {
    $lesson->add_message(get_string('attemptsremaining', 'languagelesson', $result->attemptsremaining));
}
// Report if max attempts reached.
if ($result->maxattemptsreached != 0 && !$lesson->review && !$reviewmode) {
    $lesson->add_message('('.get_string("maximumnumberofattemptsreached", "languagelesson").')');
}

$PAGE->set_url('/mod/languagelesson/view.php', array('id' => $cm->id, 'pageid' => $page->id));
$PAGE->set_subpage($page->id);

// Print the header, heading and tabs.
languagelesson_add_fake_blocks($PAGE, $cm, $lesson, $timer);
echo $lessonoutput->header($lesson, $cm, 'view', true, $page->id);

if ($lesson->displayleft) {
    echo '<a name="maincontent" id="maincontent" title="'.get_string('anchortitle', 'languagelesson').'"></a>';
}
// This calculates and prints the ongoing score message.
if ($lesson->ongoing && !$reviewmode) {
    echo $lessonoutput->ongoing_score($lesson);
}
if ($result->feedback) {
    echo $result->feedback;
}

// Review button back.
if (!$result->correctanswer && !$result->noanswer && !$result->isessayquestion && !$reviewmode && $lesson->review) {
    $url = $CFG->wwwroot.'/mod/languagelesson/view.php';
    $content = html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'id', 'value'=>$cm->id));
    $content .= html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'pageid', 'value'=>$page->id));
    $content .= html_writer::empty_tag('input',
                array('type'=>'submit', 'name'=>'submit', 'value'=>get_string('reviewquestionback', 'languagelesson')));
    echo html_writer::tag('form', "<div class=\"singlebutton\">$content</div>", array('method'=>'post', 'action'=>$url));
}

$url = new moodle_url('/mod/languagelesson/view.php', array('id'=>$cm->id, 'pageid'=>$result->newpageid));
if ($lesson->review && !$result->correctanswer && !$result->noanswer && !$result->isessayquestion) {
    // Review button continue.
    echo $OUTPUT->single_button($url, get_string('reviewquestioncontinue', 'languagelesson'));
} else {
    // Normal continue button.
    if ($result->isessayquestion) {
        echo $OUTPUT->single_button($url, get_string('continue', 'languagelesson'));
    } else {
        echo $OUTPUT->single_button($url, get_string('tryagain', 'languagelesson'));
        echo $OUTPUT->single_button(new moodle_url('/mod/languagelesson/view.php/', array('id'=>$cm->id, 'pageid'=>$page->nextpageid)), get_string('continue','languagelesson'));
    }
}

echo $lessonoutput->footer();