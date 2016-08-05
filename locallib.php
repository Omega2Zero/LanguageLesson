<?php
// This file is part of Moodle - http:// Moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// It under the terms of the GNU General Public License as published by
// The Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// Along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Local library file for Language Lesson.  These are non-standard functions that are used
 * only by Language Lesson.
 *
 * @package    mod
 * @subpackage languagelesson
 * @copyright 1999 onwards Martin Dougiamas  {@link http:// Moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or late
 **/

/** Make sure this isn't being directly accessed */
defined('MOODLE_INTERNAL') || die();

/** Include the files that are required by this module */
require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/languagelesson/lib.php');
require_once($CFG->libdir . '/filelib.php');

/** This page */
define('LL_THISPAGE', 0);
/** Next page -> any page not seen before */
define("LL_UNSEENPAGE", 1);
/** Next page -> any page not answered correctly */
define("LL_UNANSWEREDPAGE", 2);
/** Jump to Next Page */
define("LL_NEXTPAGE", -1);
/** End of Lesson */
define("LL_EOL", -9);
/** Jump to an unseen page within a branch and end of branch or end of lesson */
define("LL_UNSEENBRANCHPAGE", -50);
/** Jump to Previous Page */
define("LL_PREVIOUSPAGE", -40);
/** Jump to a random page within a branch and end of branch or end of lesson */
define("LL_RANDOMPAGE", -60);
/** Jump to a random Branch */
define("LL_RANDOMBRANCH", -70);
/** Cluster Jump */
define("LL_CLUSTERJUMP", -80);
/** Undefined */
define("LL_UNDEFINED", -99);

/** LL_MAX_EVENT_LENGTH = 432000 ; 5 days maximum */
define("LL_MAX_EVENT_LENGTH", "432000");


// MODES.

/**
 * Practice Type
 */
if (!defined("LL_TYPE_PRACTICE")) {
    define("LL_TYPE_PRACTICE", 0);
}
/**
 * Assignment Type
 */
if (!defined("LL_TYPE_ASSIGNMENT")) {
    define("LL_TYPE_ASSIGNMENT", 1);
}
/**
 * Test Type
 */
if (!defined("LL_TYPE_TEST")) {
    define("LL_TYPE_TEST", 2);
}

// PENALTY TYPES.

/**
 * Use mean type
 */
if (!defined("LL_PENALTY_MEAN")) {
    define("LL_PENALTY_MEAN", 0);
}
/**
 * Use set penalty multiplier type
 */
if (!defined("LL_PENALTY_SET")) {
    define("LL_PENALTY_SET", 1);
}

// DEFINE PAGE TYPES.

// Lesson question types defined.

// Description question type.
define("LL_DESCRIPTION", "1");

/** Multichoice question type */
define("LL_MULTICHOICE",   "2");

/** True/False question type */
define("LL_TRUEFALSE",     "3");

 /** Short answer question type */
define("LL_SHORTANSWER",   "4");

 /** Cloze question type */
define("LL_CLOZE",   "5");

/** Matching question type */
define("LL_MATCHING",      "6");

/** Essay question type */
define("LL_ESSAY", "8");

/** Audio question type */
define("LL_AUDIO", "9");

 /** Branch Table page */
define("LL_BRANCHTABLE",   "20");

 /** End of Branch page */
define("LL_ENDOFBRANCH",   "21");


 /** Start of Cluster page */
define("LL_CLUSTER",   "30");

 /** End of Cluster page */
define("LL_ENDOFCLUSTER",   "31");


/**
 * Lesson question type array.
 * Contains all question types used
 *
 * Decided to fetch these using get_string, as opposed to from the database, for two reasons:
 * 1) Speed -- this file is included in every single page used in the language lesson module,
 * 				and adding an additional 10 database queries to it is not desirable.
 * 2) Changeability -- leaving the database out of it makes it easier to rename a question type,
 * 						if necessary; however, please be aware that the database entry for the
 * 						question type should also be updated to reflect a name change
 */
$LL_QUESTION_TYPE = array (
                            LL_MULTICHOICE => get_string("multichoicename", "languagelesson"),
                            LL_TRUEFALSE     => get_string("truefalsename", "languagelesson"),
                            LL_SHORTANSWER   => get_string("shortanswername", "languagelesson"),
                            LL_CLOZE		=> get_string('clozename', 'languagelesson'),
                            LL_MATCHING      => get_string("matchingname", "languagelesson"),
                            LL_ESSAY           => get_string("essayname", "languagelesson"),
                            LL_AUDIO  => get_string("audioname", "languagelesson"),
                            LL_BRANCHTABLE => get_string("branchtablename", "languagelesson"),
                            LL_ENDOFBRANCH => get_string("endofbranchname", "languagelesson"),
                            LL_DESCRIPTION => get_string("descriptionname", "languagelesson")
                              );

// Any other lesson functions go here.  Each of them must have a name that
// Starts with languagelesson_.

/**
 * Checks to see if a LL_CLUSTERJUMP or
 * a LL_UNSEENBRANCHPAGE is used in a lesson.
 *
 * This function is only executed when a teacher is
 * checking the navigation for a lesson.
 *
 * @param stdClass $lesson Id of the lesson that is to be checked.
 * @return boolean True or false.
 **/
function languagelesson_display_teacher_warning($lesson) {
    global $DB;

    // Get all of the lesson answers.
    $params = array ("lessonid" => $lesson->id);
    if (!$lessonanswers = $DB->get_records_select("languagelesson_answers", "lessonid = :lessonid", $params)) {
        // No answers, then not using cluster or unseen.
        return false;
    }
    // Just check for the first one that fulfills the requirements.
    foreach ($lessonanswers as $lessonanswer) {
        if ($lessonanswer->jumpto == LL_CLUSTERJUMP || $lessonanswer->jumpto == LL_UNSEENBRANCHPAGE) {
            return true;
        }
    }

    // If no answers use either of the two jumps.
    return false;
}

/**
 * Interprets the LL_UNSEENBRANCHPAGE jump.
 *
 * will return the pageid of a random unseen page that is within a branch
 *
 * @param languagelesson $lesson
 * @param int $userid Id of the user.
 * @param int $pageid Id of the page from which we are jumping.
 * @return int Id of the next page.
 **/
function languagelesson_unseen_question_jump($lesson, $user, $pageid) {
    global $DB;

    // Get the number of retakes.
    if (!$retakes = $DB->count_records("languagelesson_grades", array("lessonid"=>$lesson->id, "userid"=>$user))) {
        $retakes = 0;
    }

    // Get all the languagelesson_attempts aka what the user has seen.
    if ($viewedpages = $DB->get_records("languagelesson_attempts", array("lessonid"=>$lesson->id, "userid"=>$user, "retry"=>$retakes), "timeseen DESC")) {
        foreach ($viewedpages as $viewed) {
            $seenpages[] = $viewed->pageid;
        }
    } else {
        $seenpages = array();
    }

    // Get the lesson pages.
    $lessonpages = $lesson->load_all_pages();

    if ($pageid == LL_UNSEENBRANCHPAGE) {  // This only happens when a student leaves in the middle of an unseen question within a branch series.
        $pageid = $seenpages[0];  // Just change the pageid to the last page viewed inside the branch table.
    }

    // Go up the pages till branch table.
    while ($pageid != 0) { // This condition should never be satisfied... only happens if there are no branch tables above this page.
        if ($lessonpages[$pageid]->qtype == LL_BRANCHTABLE) {
            break;
        }
        $pageid = $lessonpages[$pageid]->prevpageid;
    }

    $pagesinbranch = $lesson->get_sub_pages_of($pageid, array(LL_BRANCHTABLE, LL_ENDOFBRANCH));

    // This foreach loop stores all the pages that are within the branch table but are not in the $seenpages array.
    $unseen = array();
    foreach ($pagesinbranch as $page) {
        if (!in_array($page->id, $seenpages)) {
            $unseen[] = $page->id;
        }
    }

    if (count($unseen) == 0) {
        if (isset($pagesinbranch)) {
            $temp = end($pagesinbranch);
            $nextpage = $temp->nextpageid; // They have seen all the pages in the branch, so go to EOB/next branch table/EOL.
        } else {
            // There are no pages inside the branch, so return the next page.
            $nextpage = $lessonpages[$pageid]->nextpageid;
        }
        if ($nextpage == 0) {
            return LL_EOL;
        } else {
            return $nextpage;
        }
    } else {
        return $unseen[rand(0, count($unseen)-1)];  // Returns a random page id for the next page.
    }
}

/**
 * Handles the unseen branch table jump.
 *
 * @param languagelesson $lesson
 * @param int $userid User id.
 * @return int Will return the page id of a branch table or end of lesson
 **/
function languagelesson_unseen_branch_jump($lesson, $userid) {
    global $DB;

    if (!$retakes = $DB->count_records("languagelesson_grades", array("lessonid"=>$lesson->id, "userid"=>$userid))) {
        $retakes = 0;
    }

    $params = array ("lessonid" => $lesson->id, "userid" => $userid, "retry" => $retakes);
    if (!$seenbranches = $DB->get_records_select("languagelesson_branch", "lessonid = :lessonid AND userid = :userid AND retry = :retry", $params,
                "timeseen DESC")) {
        print_error('cannotfindrecords', 'languagelesson');
    }

    // Get the lesson pages.
    $lessonpages = $lesson->load_all_pages();

    // This loads all the viewed branch tables into $seen until it finds the branch table with the flag
    // Which is the branch table that starts the unseenbranch function.
    $seen = array();
    foreach ($seenbranches as $seenbranch) {
        if (!$seenbranch->flag) {
            $seen[$seenbranch->pageid] = $seenbranch->pageid;
        } else {
            $start = $seenbranch->pageid;
            break;
        }
    }
    // This function searches through the lesson pages to find all the branch tables.
    // That follow the flagged branch table.
    $pageid = $lessonpages[$start]->nextpageid; // Move down from the flagged branch table.
    $branchtables = array();
    while ($pageid != 0) {  // Grab all of the branch table till eol.
        if ($lessonpages[$pageid]->qtype == LL_BRANCHTABLE) {
            $branchtables[] = $lessonpages[$pageid]->id;
        }
        $pageid = $lessonpages[$pageid]->nextpageid;
    }
    $unseen = array();
    foreach ($branchtables as $branchtable) {
        // Load all of the unseen branch tables into unseen.
        if (!array_key_exists($branchtable, $seen)) {
            $unseen[] = $branchtable;
        }
    }
    if (count($unseen) > 0) {
        return $unseen[rand(0, count($unseen)-1)];  // Returns a random page id for the next page.
    } else {
        return LL_EOL;  // Has viewed all of the branch tables.
    }
}

/**
 * Handles the random jump between a branch table and end of branch or end of lesson (LL_RANDOMPAGE).
 *
 * @param languagelesson $lesson
 * @param int $pageid The id of the page that we are jumping from (?)
 * @return int The pageid of a random page that is within a branch table
 **/
function languagelesson_random_question_jump($lesson, $pageid) {
    global $DB;

    // Get the lesson pages.
    $params = array ("lessonid" => $lesson->id);
    if (!$lessonpages = $DB->get_records_select("languagelesson_pages", "lessonid = :lessonid", $params)) {
        print_error('cannotfindpages', 'languagelesson');
    }

    // Go up the pages till branch table.
    while ($pageid != 0) { // This condition should never be satisfied... only happens if there are no branch tables above this page.

        if ($lessonpages[$pageid]->qtype == LL_BRANCHTABLE) {
            break;
        }
        $pageid = $lessonpages[$pageid]->prevpageid;
    }

    // Get the pages within the branch.
    $pagesinbranch = $lesson->get_sub_pages_of($pageid, array(LL_BRANCHTABLE, LL_ENDOFBRANCH));

    if (count($pagesinbranch) == 0) {
        // There are no pages inside the branch, so return the next page.
        return $lessonpages[$pageid]->nextpageid;
    } else {
        return $pagesinbranch[rand(0, count($pagesinbranch)-1)]->id;  // Returns a random page id for the next page.
    }
}

/**
 * Calculates a user's grade for a lesson.
 *
 * @param object $lesson The lesson that the user is taking.
 * @param int $ntries The attempt number.
 * @param int $userid Id of the user (optional, default current user).
 * @return object { nquestions => number of questions answered
                    attempts => number of question attempts
                    total => max points possible
                    earned => points earned by student
                    grade => calculated percentage grade
                    nmanual => number of manually graded questions
                    manualpoints => point value for manually graded questions }
 */
function languagelesson_grade($lesson, $ntries, $userid = 0) {
    global $USER, $DB;

    if (empty($userid)) {
        $userid = $USER->id;
    }

    // Zero out everything.
    $ncorrect     = 0;
    $nviewed      = 0;
    $score        = 0;
    $nmanual      = 0;
    $manualpoints = 0;
    $thegrade     = 0;
    $nquestions   = 0;
    $total        = 0;
    $earned       = 0;

    $params = array ("lessonid" => $lesson->id, "userid" => $userid, "retry" => $ntries);
    if ($useranswers = $DB->get_records_select("languagelesson_attempts",  "lessonid = :lessonid AND
            userid = :userid AND retry = :retry", $params, "timeseen")) {
        // Group each try with its page.
        $attemptset = array();
        foreach ($useranswers as $useranswer) {
            $attemptset[$useranswer->pageid][] = $useranswer;
        }

        // Drop all attempts that go beyond max attempts for the lesson.
        if ($lesson->maxattempts != 0) { 
            foreach ($attemptset as $key => $set) {
                $attemptset[$key] = array_slice($set, 0, $lesson->maxattempts);
            }
        }
        
        // Get only the pages and their answers that the user answered.
        list($usql, $parameters) = $DB->get_in_or_equal(array_keys($attemptset));
        array_unshift($parameters, $lesson->id);
        $pages = $DB->get_records_select("languagelesson_pages", "lessonid = ? AND id $usql", $parameters);
        $answers = $DB->get_records_select("languagelesson_answers", "lessonid = ? AND pageid $usql", $parameters);

        // Number of pages answered.
        $nquestions = count($pages);

        foreach ($attemptset as $attempts) {
            $page = languagelesson_page::load($pages[end($attempts)->pageid], $lesson);
            if ($lesson->custom) {
                $attempt = end($attempts);
                // If essay question, handle it, otherwise add to score.
                if ($page->requires_manual_grading()) {
                    $useranswerobj = unserialize($attempt->useranswer);
                    if (isset($useranswerobj->score)) {
                        $earned += $useranswerobj->score;
                    }
                    $nmanual++;
                } else if (!empty($attempt->answerid)) {
                    $earned += $page->earned_score($answers, $attempt);
                }
            } else {
                foreach ($attempts as $attempt) {
                    $earned += $attempt->correct;
                }
                $attempt = end($attempts); // Doesn't matter which one.
                // If essay question, increase numbers
                if ($page->requires_manual_grading()) {
                    $nmanual++;
                    $manualpoints++;
                }
            }
            // Number of times answered.
            $nviewed += count($attempts);
        }

        if ($lesson->custom) {
            $bestscores = array();
            // Find the highest possible score per page to get our total.
            foreach ($answers as $answer) {
                if (!isset($bestscores[$answer->pageid])) {
                    $bestscores[$answer->pageid] = $answer->score;
                } else if ($bestscores[$answer->pageid] < $answer->score) {
                    $bestscores[$answer->pageid] = $answer->score;
                }
            }
            $total = array_sum($bestscores);
        } else {
            // Check to make sure the student has answered the minimum questions.
            if ($lesson->minquestions and $nquestions < $lesson->minquestions) {
                // Nope, increase number viewed by the amount of unanswered questions.
                $total =  $nviewed + ($lesson->minquestions - $nquestions);
            } else {
                $total = $nviewed;
            }
        }
    }

    if ($total) { // Not zero.
        $thegrade = round(100 * $earned / $total, 5);
    }

    // Build the grade information object.
    $gradeinfo               = new stdClass;
    $gradeinfo->nquestions   = $nquestions;
    $gradeinfo->attempts     = $nviewed;
    $gradeinfo->total        = $total;
    $gradeinfo->earned       = $earned;
    $gradeinfo->grade        = $thegrade;
    $gradeinfo->nmanual      = $nmanual;
    $gradeinfo->manualpoints = $manualpoints;

    return $gradeinfo;
}

/**
 * Determines if a user can view the left menu.  The determining factor
 * is whether a user has a grade greater than or equal to the lesson setting
 * of displayleftif
 *
 * @param object $lesson Lesson object of the current lesson
 * @return boolean 0 if the user cannot see, or $lesson->displayleft to keep displayleft unchanged
 **/
function languagelesson_displayleftif($lesson) {
    global $CFG, $USER, $DB;

    if (!empty($lesson->displayleftif)) {
        // Get the current user's max grade for this lesson.
        $params = array ("userid" => $USER->id, "lessonid" => $lesson->id);
        if ($maxgrade = $DB->get_record_sql('SELECT userid, MAX(grade) AS maxgrade FROM {languagelesson_grades} WHERE userid = :userid AND lessonid = :lessonid GROUP BY userid', $params)) {
            if ($maxgrade->maxgrade < $lesson->displayleftif) {
                return 0;  // Turn off the displayleft
            }
        } else {
            return 0; // No grades.
        }
    }

    // If we get to here, keep the original state of displayleft lesson setting.
    return $lesson->displayleft;
}

/**
 *
 * @param $cm
 * @param $lesson
 * @param $page
 * @return unknown_type
 */
function languagelesson_add_fake_blocks($page, $cm, $lesson, $timer = null) {
    $bc = languagelesson_menu_block_contents($cm->id, $lesson);
    if (!empty($bc)) {
        $regions = $page->blocks->get_regions();
        $firstregion = reset($regions);
        $page->blocks->add_fake_block($bc, $firstregion);
    }

    $bc = languagelesson_mediafile_block_contents($cm->id, $lesson);
    if (!empty($bc)) {
        $page->blocks->add_fake_block($bc, $page->blocks->get_default_region());
    }

    if (!empty($timer)) {
        $bc = languagelesson_clock_block_contents($cm->id, $lesson, $timer, $page);
        if (!empty($bc)) {
            $page->blocks->add_fake_block($bc, $page->blocks->get_default_region());
        }
    }
}

/**
 * If there is a media file associated with this
 * lesson, return a block_contents that displays it.
 *
 * @param int $cmid Course Module ID for this lesson
 * @param object $lesson Full lesson record object
 * @return block_contents
 **/
function languagelesson_mediafile_block_contents($cmid, $lesson) {
    global $OUTPUT;
    if (empty($lesson->mediafile)) {
        return null;
    }

    $options = array();
    $options['menubar'] = 0;
    $options['location'] = 0;
    $options['left'] = 5;
    $options['top'] = 5;
    $options['scrollbars'] = 1;
    $options['resizable'] = 1;
    $options['width'] = $lesson->mediawidth;
    $options['height'] = $lesson->mediaheight;

    $link = new moodle_url('/mod/languagelesson/mediafile.php?id='.$cmid);
    $action = new popup_action('click', $link, 'lessonmediafile', $options);
    $content = $OUTPUT->action_link($link, get_string('mediafilepopup', 'languagelesson'), $action,
                                    array('title'=>get_string('mediafilepopup', 'languagelesson')));

    $bc = new block_contents();
    $bc->title = get_string('linkedmedia', 'languagelesson');
    $bc->attributes['class'] = 'mediafile';
    $bc->content = $content;

    return $bc;
}

/**
 * If a timed lesson and not a teacher, then
 * return a block_contents containing the clock.
 *
 * @param int $cmid Course Module ID for this lesson
 * @param object $lesson Full lesson record object
 * @param object $timer Full timer record object
 * @return block_contents
 **/
function languagelesson_clock_block_contents($cmid, $lesson, $timer, $page) {
    // Display for timed lessons and for students only.
    $context = context_module::instance($cmid);
    if (!$lesson->timed || has_capability('mod/languagelesson:manage', $context)) {
        return null;
    }

    $content = '<div class="jshidewhenenabled">';
    $content .=  $lesson->time_remaining($timer->starttime);
    $content .= '</div>';

    $clocksettings = array('starttime'=>$timer->starttime, 'servertime'=>time(),'testlength'=>($lesson->maxtime * 60));
    $page->requires->data_for_js('clocksettings', $clocksettings);
    $page->requires->js('/mod/languagelesson/timer.js');
    $page->requires->js_function_call('show_clock');

    $bc = new block_contents();
    $bc->title = get_string('timeremaining', 'languagelesson');
    $bc->attributes['class'] = 'clock block';
    $bc->content = $content;

    return $bc;
}

function languagelesson_menu_block_contents($cmid, $lesson) {

/*
 * This has been extensively customized from the original for use in
 * languagelessons.  In lesson functionality, only branch tables are
 * printed here.  In language lessons, the following rules are followed:
 *
 * - Cluster, End of Cluster, and End of Branch demarcation structural pages
 *	 are not printed at all.
 * - If a question page is not contained in a branch table, it is printed.
 * - If a branch table is encountered, the following happens:
 *	 :: the branch table page itself is printed
 *	 :: the titles of each of the branches in the table are printed as links
 *		to the first page in each branch
 *	 :: all question pages that are in the branch the user is currently working
 *	 	on are printed
 *	 :: all question pages in other branches are not printed
 *
 * The logic is thoroughly commented below.
 *
 */

    global $CFG, $USER, $DB;

    if ($lesson->displayleft) {
        $pageid = $DB->get_field('languagelesson_pages', 'id', array('lessonid'=>$lesson->id, 'prevpageid'=>0));
        $pages  = $DB->get_records('languagelesson_pages', array('lessonid' => $lesson->id));
        $currentpageid = optional_param('pageid', $pageid, PARAM_INT);
        
        
      // Initialize all the variables used in context-sensitive printing of the
      // Left menu contents
      /*
       * @param branchtable_id :: the pageID of the most recent branch table seen
       * @param branch_heads :: list of pageIDs of pages that start each branch in the
       *						current branch table
       * @param branch_pages :: a temp array of all pageIDs belonging to the branch
       *						currently being checked; used to determine the contents
       *						of currentbranch_pages
       * @param currentbranch_pages :: list of all pageIDs belonging to the branch that
       *							   the user is currently in
       * @param branches_seen :: count variable used (with branches_expected) to determine
       *						 when the end of the current branch table has been reached;
       *						 incremented when a LL_ENDOFBRANCH page is seen
       * @param branches_expected :: count variable used (with branches_seen) to determine
       *							 when the end of the current branch table has been reached
       * @param inbranchtable :: bool flag used to mark whether the page currently being
       *						 checked belongs to a branch table or not
       * @param print :: bool flag marking whether page currently being checked should be
       *				 printed in the left menu block
       * @param indent :: multiplier variable used to mark with how many degrees of indentation
       *				  page currently being checked should be printed in the left menu
       * @param indent_pixels :: int constant setting the number of pixels the indent
       *						 multiplier is multiplied by to yield final indentation value
       */
        $branchtable_id = null;
        $branch_heads = array();
        $branch_pages = array();
        $currentbranch_pages = array();
        $branches_seen = 0;
        $branches_expected = 0;
        $inbranchtable = false;
        $print = true;
        $indent = 0;
        $indent_pixels = 20;
        
      // Initialize the default (base) texts used for printing selected or not selected
      // Page links in the left menu.
        $selected = '<li class="selected"><span %s>%s</span> %s %s</li>';
        $notselected = "<li class=\"notselected\"><a href=\"$CFG->wwwroot/mod/"
        				  . "languagelesson/view.php?id=$cmid&amp;pageid=%d\""
        				  . "class=\"%s\" %s >%s</a>%s %s</li>\n";
      // Initialize the base style declaration used in setting indent values.
        $indent_style = 'style="margin-left:%dpx;"';
        

        if ($pageid and $pages) {
			$content = '<a href="#maincontent" class="skip">'.get_string('skip', 'languagelesson')."</a>\n<div
				class=\"menuwrapper\">\n<ul>\n";
            while ($pageid != 0) {
                $page = $pages[$pageid];

                switch ($page->qtype) {
                	case LL_CLUSTER:
                	case LL_ENDOFCLUSTER:
                		break;
                	case LL_BRANCHTABLE:
                		$branchtable_id = $page->id;
                		$branch_heads = languagelesson_get_branch_heads($page->id);
                		$branches_seen = 0; // Reset count of branches seen.
                		$branches_expected = count($branch_heads);
                		$inbranchtable = true;
                		if ($page->id == $currentpageid) {
                			$content .= sprintf($selected, sprintf($indent_style, 0*$indent_pixels), format_string($page->title,true),
									'', '');
                		} else {
							$content .= sprintf($notselected, $page->id, '', sprintf($indent_style, 0*$indent_pixels),
									format_string($page->title, true), '', '');
                		}
                		break;
                	case LL_ENDOFBRANCH:
                		$branches_seen++;
                		if ($branches_seen == $branches_expected) {
                			$inbranchtable = false;
                		}
                		break;
                	default:
                		
                	// PRINT BOOL CHECKING.
                		
                	  // If we aren't in a branch table, flag it as to-be-printed with no
                	  // indent, and move on.
                		if (! $inbranchtable) {
                			$print = true;
                			$indent = 0;
                		} 
                	  // Otherwise, do special checking to see if it should be printed and
                	  // manage behind-the-scenes variables.
                		else {
                		  // If it's the first page in a branch (a branch header).
                			if (in_array($page->id, $branch_heads)) {
                			  // Get its title...
                			  	$branchheader_title = languagelesson_get_branch_header_title($branchtable_id, $page->id);
                				
                			  // Get the list of pageIDs belonging to this branch...
                				$branch_pages = languagelesson_get_current_branch_pages($lesson->id, $page->id);
                				
                			  // If the currently selected page is among the pageIDs belonging
                			  // to this branch, save that list as the list of branch pages in
                			  // the current branch...
                			  	if (in_array($currentpageid, $branch_pages)) {
                			  		$currentbranch_pages = $branch_pages;
                			  	}
                				
                			  // And print the branch header.
                				if (in_array($page->id, $currentbranch_pages)) {
                				  // If the branch header being checked is in the current branch,
                				  // Print the header as selected.
									$content .= sprintf($selected, sprintf($indent_style, 1*$indent_pixels),
											format_string($branchheader_title,true), '', '');
                				} else {
                				  // Otherwise, just print the header as not selected.
									$content .= sprintf($notselected, $page->id, '', sprintf($indent_style, 1*$indent_pixels),
											format_string($branchheader_title,true), '', '');
                				}
                			}
                			
                		  // Now that we may have updated the list of current branch pageids,
                		  // Check this page against it: if it's in the current branch, flag.
                		  // It as to-be-printed and set the indent, otherwise, hide it.
                			if (in_array($page->id, $currentbranch_pages)) {
                				$print = true;
                				$indent = 2;
                			} else {
                				$print = false;
                			}
                		}
                		
                		
                	// PRINTING.
                		
                		if ($print) {
                                    // Reset the optional second image string.
                                    $img2 = '';
                                    $fbsrc = get_string('iconsrcfeedback', 'languagelesson');
                                    if ($state = languagelesson_get_autograde_state($lesson->id, $page->id, $USER->id)) {
                                            if ($lesson->contextcolors) {
                                                    if ($state == 'correct') {
                                                            $class = 'leftmenu_autograde_correct';
                                                            $img = "<img src=\"{$CFG->wwwroot}".get_string('iconsrccorrect', 'languagelesson')."\"
                                                                    width=\"10\" height=\"10\" alt=\"correct\" />";
                                                    } else if ($state == 'correctfeedback') {
                                                        $class = 'leftmenu_autograde_correct';
                                                        $img = "<img src=\"{$CFG->wwwroot}".get_string('iconsrccorrect', 'languagelesson')."\"
                                                                    width=\"10\" height=\"10\" alt=\"correct\" />";
                                                        $img2 = "<img src=\"{$CFG->wwwroot}$fbsrc\"
                                                                            width=\"15\" height=\"15\" alt=\"feedback\" />";
                                                    } else if ($state == 'incorrect') {
                                                            $class = 'leftmenu_autograde_incorrect';
                                                            $img = "<img src=\"{$CFG->wwwroot}".get_string('iconsrcwrong', 'languagelesson')."\"
                                                                    width=\"10\" height=\"10\" alt=\"incorrect\" />";
                                                    } else if ($state == 'incorrectfeedback') {
                                                        $class = 'leftmenu_autograde_incorrect';
                                                        $img = "<img src=\"{$CFG->wwwroot}".get_string('iconsrcwrong', 'languagelesson')."\"
                                                                    width=\"10\" height=\"10\" alt=\"incorrect\" />";
                                                        $img2 = "<img src=\"{$CFG->wwwroot}$fbsrc\"
                                                                            width=\"15\" height=\"15\" alt=\"feedback\" />";
                                                    } else if ($state == 'essay') {
                                                        // It's an essay question
                                                        $class = 'leftmenu_manualgrade';
                                                        $img = "<img src=\"{$CFG->wwwroot}".get_string('iconsrcessay', 'languagelesson'). "\"
                                                                width=\"10\" height=\"10\" alt=\"essay\" />";
                                                    } else if ($state == 'essayfeedback') {
                                                        $class = 'leftmenu_essay';
                                                        $img = "<img src=\"{$CFG->wwwroot}".get_string('iconsrcessay', 'languagelesson')."\"
                                                                    width=\"10\" height=\"10\" alt=\"essay\" />";
                                                        $img2 = "<img src=\"{$CFG->wwwroot}$fbsrc\"
                                                                            width=\"15\" height=\"15\" alt=\"feedback\" />";
                                                    } else if ($state == 'audio') {    
                                                        $class = 'leftmenu_audio';
                                                        $img = "<img src=\"{$CFG->wwwroot}".get_string('iconsrcaudio', 'languagelesson')."\"
                                                                width=\"10\" height=\"10\" alt=\"audio\" />";
                                                    } else if ($state == 'audiofeedback') {
                                                        $class = 'leftmenu_audio';
                                                        $img = "<img src=\"{$CFG->wwwroot}".get_string('iconsrcaudio', 'languagelesson')."\"
                                                                    width=\"10\" height=\"10\" alt=\"audio\" />";
                                                        $img2 = "<img src=\"{$CFG->wwwroot}$fbsrc\"
                                                                            width=\"15\" height=\"15\" alt=\"feedback\" />";
                                                    }
                                            } else {
                                                    $class = 'leftmenu_attempted';
                                                    $img = '';
                                            }
                                    } else {
                                            // Page has not been attempted, so don't mod the style and don't include an image.
                                            $class = 'leftmenu_noattempt';
                                            $img = '';
                                    }
                                    // Print the link based on if it is the current page or not.
                                    if ($page->id == $currentpageid) { 
                                            $content .= sprintf($selected, sprintf($indent_style, $indent*$indent_pixels),
                                                    format_string($page->title,true), $img, ((!empty($img2)) ? $img2 : ''));
                                    } else {
                                            $content .= sprintf($notselected, $page->id, $class, sprintf($indent_style, $indent*$indent_pixels),
                                                    format_string($page->title,true), $img, ((!empty($img2)) ? $img2 : ''));
                                    }
                                    }
                                    break;
						
                } // End switch($page->qtype).
                
                $pageid = $page->nextpageid;
            } // Eend while($pageid != 0).
            $content .= "</ul>\n</div>\n";
        }
    }
    $bc = new block_contents();
    $bc->title = get_string('lessonmenu', 'languagelesson');
    $bc->attributes['class'] = 'menu block';
    $bc->content = $content;

    return $bc;
}

/**
 * @NEEDSDOC@
 **/
function languagelesson_get_branch_heads($branchtable_id) {
    global $DB;
	
	$branches = $DB->get_records('languagelesson_answers', array('pageid'=>$branchtable_id));
	
	$branch_heads = array();
	
	foreach ($branches as $branch) {
		$branch_heads[] = $branch->jumpto;
	}
	
	return $branch_heads;
	
}





/**
 * @NEEDSDOC@
 **/
function languagelesson_get_current_branch_pages($lessonid, $branchhead_pageid) {
    global $DB;
	
	$pageid = $DB->get_field('languagelesson_pages', 'id', array('lessonid'=>$lessonid, 'prevpageid'=>0));
    $pages  = $DB->get_records_select('languagelesson_pages', "lessonid = $lessonid");
    
    $current_branch_pages = array();
    $isinbranch = false;
    
    while ($pageid != 0) {
    	$page = $pages[$pageid];	
    	if ($page->id == $branchhead_pageid) {
    		$isinbranch = true;
    	}
    	if ($page->qtype == LL_ENDOFBRANCH) {
    		$isinbranch = false;
    	}
    	if ($isinbranch) {
    		$current_branch_pages[] = $page->id;
    	}

    	$pageid = $page->nextpageid;
    }
    
    return $current_branch_pages;	
}
                				




/**
 * @NEEDSDOC@
 **/
function languagelesson_get_branch_header_title($branchtable_id, $pageid) {
    global $DB;
	
	$branches = $DB->get_records('languagelesson_answers', array('pageid'=>$branchtable_id));
	
	$title = '';
	foreach ($branches as $branch) {
		if ((int)$branch->jumpto == $pageid) {
			$title = $branch->answer;
		}
	}
	
	return $title;
	
}

/**
 * @NEEDSDOC@
 **/
function languagelesson_get_autograde_state($lessonid, $pageid, $userid, $retry=null) {
	/* function to return a string representation of the auto-grade state of a page; returns false if page has not been attempted */
	global $CFG, $DB;
	
	if ($retry===null) {
		$retry = $DB->get_field('languagelesson_attempts', 'retry', array('iscurrent'=>1, 'userid'=>$userid, 'pageid'=>$pageid));
	}
	$result = $DB->get_record_select('languagelesson_attempts', "lessonid=$lessonid and pageid=$pageid and userid=$userid and iscurrent=1");
        
	if ($result) {
            if (!$DB->count_records('languagelesson_feedback', array('attemptid'=>$result->id))) {
		$feedback = FALSE;
	    } else {
                $feedback = TRUE;
            }
            if ($result->type == LL_AUDIO) {
                    if ($feedback == TRUE) {
                            return 'audiofeedback';
                    } else {
                            return 'audio';
                    }
            } else if ($result->type == LL_ESSAY) {
                if ($feedback == TRUE) {
                            return 'essayfeedback';
                    } else {
                        return 'essay';
                    }
            } else if ($result->correct) {
                if ($feedback == TRUE) {
                    return 'correctfeedback';
                } else {
                    return 'correct';
                }
            } else {
                if ($feedback == TRUE) {
                    return 'incorrectfeedback';
                } else {
                    return 'incorrect';
                }
            }
	}
        unset($feedback);
	
	return false;	
}


/**
 * Adds header buttons to the page for the lesson
 *
 * @param object $cm
 * @param object $context
 * @param bool $extraeditbuttons
 * @param int $lessonpageid
 */
function languagelesson_add_header_buttons($cm, $context, $extraeditbuttons=false, $lessonpageid=null) {
    global $CFG, $PAGE, $OUTPUT;
    if (has_capability('mod/languagelesson:edit', $context) && $extraeditbuttons) {
        if ($lessonpageid === null) {
            print_error('invalidpageid', 'languagelesson');
        }
        if (!empty($lessonpageid) && $lessonpageid != LL_EOL) {
            $url = new moodle_url('/mod/languagelesson/editpage.php', array('id'=>$cm->id, 'pageid'=>$lessonpageid, 'edit'=>1));
            $PAGE->set_button($OUTPUT->single_button($url, get_string('editpagecontent', 'languagelesson')));
        }
    }
}

/**
 * This is a function used to detect media types and generate html code.
 *
 * @global object $CFG
 * @global object $PAGE
 * @param object $lesson
 * @param object $context
 * @return string $code the html code of media
 */
function languagelesson_get_media_html($lesson, $context) {
    global $CFG, $PAGE, $OUTPUT;
    require_once("$CFG->libdir/resourcelib.php");

    // Get the media file link.
    if (strpos($lesson->mediafile, '://') !== false) {
        $url = new moodle_url($lesson->mediafile);
    } else {
        // The timemodified is used to prevent caching problems, instead of '/' we should better read from files table and use sortorder.
        $url = moodle_url::make_pluginfile_url($context->id, 'mod_languagelesson', 'mediafile', $lesson->timemodified, '/', ltrim($lesson->mediafile, '/'));
    }
    $title = $lesson->mediafile;

    $clicktoopen = html_writer::link($url, get_string('download'));

    $mimetype = resourcelib_guess_url_mimetype($url);

    $extension = resourcelib_get_extension($url->out(false));

    $mediarenderer = $PAGE->get_renderer('core', 'media');
    $embedoptions = array(
        core_media::OPTION_TRUSTED => true,
        core_media::OPTION_BLOCK => true
    );

    // Find the correct type and print it out.
    if (in_array($mimetype, array('image/gif','image/jpeg','image/png'))) {  // It's an image.
        $code = resourcelib_embed_image($url, $title);

    } else if ($mediarenderer->can_embed_url($url, $embedoptions)) {
        // Media (audio/video) file.
        $code = $mediarenderer->embed_url($url, $title, 0, 0, $embedoptions);

    } else {
        // Anything else - just try object tag enlarged as much as possible.
        $code = resourcelib_embed_general($url, $title, $clicktoopen, $mimetype);
    }

    return $code;
}


/**
 * Abstract class that page type's MUST inherit from.
 *
 * This is the abstract class that ALL add page type forms must extend.
 * You will notice that all but two of the methods this class contains are final.
 * Essentially the only thing that extending classes can do is extend custom_definition.
 * OR if it has a special requirement on creation it can extend construction_override
 *
 * @abstract
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class languagelesson_add_page_form_base extends moodleform {

    /**
     * This is the classic define that is used to identify this pagetype.
     * Will be one of LESSON_*
     * @var int
     */
    public $qtype;

    /**
     * The simple string that describes the page type e.g. truefalse, multichoice
     * @var string
     */
    public $qtypestring;

    /**
     * An array of options used in the htmleditor
     * @var array
     */
    protected $editoroptions = array();

    /**
     * True if this is a standard page of false if it does something special.
     * Questions are standard pages, branch tables are not
     * @var bool
     */
    protected $standard = true;

    /**
     * Each page type can and should override this to add any custom elements to
     * the basic form that they want
     */
    public function custom_definition() {}

    /**
     * Used to determine if this is a standard page or a special page
     * @return bool
     */
    public final function is_standard() {
        return (bool)$this->standard;
    }

    /**
     * Add the required basic elements to the form.
     *
     * This method adds the basic elements to the form including title and contents
     * and then calls custom_definition();
     */
    public final function definition() {
        $mform = $this->_form;
        $editoroptions = $this->_customdata['editoroptions'];

        $mform->addElement('header', 'qtypeheading', get_string('addaquestionpage', 'languagelesson',
                                                                get_string($this->qtypestring, 'languagelesson')));

        if ($this->standard === true) {
            $mform->addElement('hidden', 'qtype');
            $mform->setType('qtype', PARAM_INT);

            $mform->addElement('text', 'title', get_string('pagetitle', 'languagelesson'), array('size'=>70));
            $mform->setType('title', PARAM_TEXT);
            $mform->addRule('title', get_string('required'), 'required', null, 'client');

            $this->editoroptions = array('noclean'=>true, 'maxfiles'=>EDITOR_UNLIMITED_FILES, 'maxbytes'=>$this->_customdata['maxbytes']);
            $mform->addElement('editor', 'contents_editor', get_string('pagecontents', 'languagelesson'), null, $this->editoroptions);
            $mform->setType('contents_editor', PARAM_RAW);
            $mform->addRule('contents_editor', get_string('required'), 'required', null, 'client');
        }

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'pageid');
        $mform->setType('pageid', PARAM_INT);



        $this->custom_definition();

        if ($this->_customdata['edit'] === true) {
            $mform->addElement('hidden', 'edit', 1);
            $this->add_action_buttons(get_string('cancel'), get_string('savepage', 'languagelesson'));
        } else if ($this->qtype === 'questiontype') {
            $this->add_action_buttons(get_string('cancel'), get_string('addaquestionpage', 'languagelesson'));
        } else {
            $this->add_action_buttons(get_string('cancel'), get_string('savepage', 'languagelesson'));
        }
    }

    /**
     * Convenience function: Adds a jumpto select element
     *
     * @param string $name
     * @param string|null $label
     * @param int $selected The page to select by default
     */
    protected final function add_jumpto($name, $label=null, $selected=LL_NEXTPAGE) {
        $title = get_string("jump", "languagelesson");
        if ($label === null) {
            $label = $title;
        }
        if (is_int($name)) {
            $name = "jumpto[$name]";
        }
        $this->_form->addElement('select', $name, $label, $this->_customdata['jumpto']);
        $this->_form->setDefault($name, $selected);
        $this->_form->addHelpButton($name, 'jumps', 'languagelesson');
    }

    /**
     * Convenience function: Adds a score input element
     *
     * @param string $name
     * @param string|null $label
     * @param mixed $value The default value
     */
    protected final function add_score($name, $label=null, $value=null) {
        if ($label === null) {
            $label = get_string("score", "languagelesson");
        }
        if (is_int($name)) {
            $name = "score[$name]";
        }
        $this->_form->addElement('text', $name, $label, array('size'=>5));
        if ($value !== null) {
            $this->_form->setDefault($name, $value);
        }
    }

    /**
     * Convenience function: Adds an answer editor
     *
     * @param int $count The count of the element to add
     * @param string $label, NULL means default
     * @param bool $required
     * @return void
     */
    protected final function add_answer($count, $label = NULL, $required = false) {
        if ($label === NULL) {
            $label = get_string('answer', 'languagelesson');
        }
        $this->_form->addElement('editor', 'answer_editor['.$count.']', $label, array('rows'=>'4', 'columns'=>'80'), array('noclean'=>true));
        $this->_form->setDefault('answer_editor['.$count.']', array('text'=>'', 'format'=>FORMAT_MOODLE));
        if ($required) {
            $this->_form->addRule('answer_editor['.$count.']', get_string('required'), 'required', null, 'client');
        }
    }
    /**
     * Convenience function: Adds an response editor
     *
     * @param int $count The count of the element to add
     * @param string $label, NULL means default
     * @param bool $required
     * @return void
     */
    protected final function add_response($count, $label = NULL, $required = false) {
        if ($label === NULL) {
            $label = get_string('response', 'languagelesson');
        }
        $this->_form->addElement('editor', 'response_editor['.$count.']', $label, array('rows'=>'4', 'columns'=>'80'), array('noclean'=>true));
        $this->_form->setDefault('response_editor['.$count.']', array('text'=>'', 'format'=>FORMAT_MOODLE));
        if ($required) {
            $this->_form->addRule('response_editor['.$count.']', get_string('required'), 'required', null, 'client');
        }
    }

    /**
     * A function that gets called upon init of this object by the calling script.
     *
     * This can be used to process an immediate action if required. Currently it
     * is only used in special cases by non-standard page types.
     *
     * @return bool
     */
    public function construction_override($pageid, languagelesson $lesson) {
        return true;
    }
}



/**
 * Class representation of a lesson
 *
 * This class is used the interact with, and manage a lesson once instantiated.
 * If you need to fetch a lesson object you can do so by calling
 *
 * <code>
 * lesson::load($lessonid);
 * // Or
 * $lessonrecord = $DB->get_record('languagelesson', $lessonid);
 * $lesson = new languagelesson($lessonrecord);
 * </code>
 *
 * The class itself extends languagelesson_base as all classes within the lesson module should
 *
 * These properties are from the database
 * @property int $id The id of this lesson
 * @property int $course The ID of the course this lesson belongs to
 * @property string $name The name of this lesson
 * @property int $practice Flag to toggle this as a practice lesson
 * @property int $modattempts Toggle to allow the user to go back and review answers
 * @property int $usepassword Toggle the use of a password for entry
 * @property string $password The password to require users to enter
 * @property int $dependency ID of another lesson this lesson is dependent on
 * @property string $conditions Conditions of the lesson dependency
 * @property int $grade The maximum grade a user can achieve (%)
 * @property int $custom Toggle custom scoring on or off
 * @property int $ongoing Toggle display of an ongoing score
 * @property int $usemaxgrade How retakes are handled (max=1, mean=0)
 * @property int $maxanswers The max number of answers or branches
 * @property int $maxattempts The maximum number of attempts a user can record
 * @property int $review Toggle use or wrong answer review button
 * @property int $nextpagedefault Override the default next page
 * @property int $feedback Toggles display of default feedback
 * @property int $minquestions Sets a minimum value of pages seen when calculating grades
 * @property int $maxpages Maximum number of pages this lesson can contain
 * @property int $retake Flag to allow users to retake a lesson
 * @property int $activitylink Relate this lesson to another lesson
 * @property string $mediafile File to pop up to or webpage to display
 * @property int $mediaheight Sets the height of the media file popup
 * @property int $mediawidth Sets the width of the media file popup
 * @property int $mediaclose Toggle display of a media close button
 * @property string $bgcolor Background colour of slideshow
 * @property int $displayleft Display a left menu
 * @property int $displayleftif Sets the condition on which the left menu is displayed
 * @property int $progressbar Flag to toggle display of a lesson progress bar
 * @property int $highscores Flag to toggle collection of high scores
 * @property int $maxhighscores Number of high scores to limit to
 * @property int $available Timestamp of when this lesson becomes available
 * @property int $deadline Timestamp of when this lesson is no longer available
 * @property int $timemodified Timestamp when lesson was last modified
 *
 * These properties are calculated
 * @property int $firstpageid Id of the first page of this lesson (prevpageid=0)
 * @property int $lastpageid Id of the last page of this lesson (nextpageid=0)
 *
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class languagelesson extends languagelesson_base {

    /**
     * The id of the first page (where prevpageid = 0) gets set and retrieved by
     * {@see get_firstpageid()} by directly calling <code>$lesson->firstpageid;</code>
     * @var int
     */
    protected $firstpageid = null;
    /**
     * The id of the last page (where nextpageid = 0) gets set and retrieved by
     * {@see get_lastpageid()} by directly calling <code>$lesson->lastpageid;</code>
     * @var int
     */
    protected $lastpageid = null;
    /**
     * An array used to cache the pages associated with this lesson after the first
     * time they have been loaded.
     * A note to developers: If you are going to be working with MORE than one or
     * two pages from a lesson you should probably call {@see $lesson->load_all_pages()}
     * in order to save excess database queries.
     * @var array An array of languagelesson_page objects
     */
    protected $pages = array();
    /**
     * Flag that gets set to true once all of the pages associated with the lesson
     * have been loaded.
     * @var bool
     */
    protected $loadedallpages = false;

    /**
     * Simply generates a lesson object given an array/object of properties
     * Overrides {@see languagelesson_base->create()}
     * @static
     * @param object|array $properties
     * @return lesson
     */
    public static function create($properties) {
        return new languagelesson($properties);
    }

    /**
     * Generates a lesson object from the database given its id
     * @static
     * @param int $lessonid
     * @return lesson
     */
    public static function load($lessonid) {
        global $DB;

        if (!$lesson = $DB->get_record('languagelesson', array('id' => $lessonid))) {
            print_error('invalidcoursemodule');
        }
        return new languagelesson($lesson);
    }

    /**
     * Deletes this lesson from the database
     */
    public function delete() {
        global $CFG, $DB;
        require_once($CFG->libdir.'/gradelib.php');
        require_once($CFG->dirroot.'/calendar/lib.php');

        $DB->delete_records("languagelesson", array("id"=>$this->properties->id));;
        $DB->delete_records("languagelesson_pages", array("lessonid"=>$this->properties->id));
        $DB->delete_records("languagelesson_answers", array("lessonid"=>$this->properties->id));
        $DB->delete_records("languagelesson_attempts", array("lessonid"=>$this->properties->id));
        $DB->delete_records("languagelesson_grades", array("lessonid"=>$this->properties->id));
        $DB->delete_records("languagelesson_timer", array("lessonid"=>$this->properties->id));
        $DB->delete_records("languagelesson_branch", array("lessonid"=>$this->properties->id));
        $DB->delete_records("languagelesson_high_scores", array("lessonid"=>$this->properties->id));
        if ($events = $DB->get_records('event', array("modulename"=>'lesson', "instance"=>$this->properties->id))) {
            foreach ($events as $event) {
                $event = calendar_event::load($event);
                $event->delete();
            }
        }

        grade_update('mod/languagelesson', $this->properties->course, 'mod', 'languagelesson', $this->properties->id, 0, NULL, array('deleted'=>1));
        return true;
    }

    /**
     * Fetches messages from the session that may have been set in previous page
     * actions.
     *
     * <code>
     * // Do not call this method directly instead use.
     * $lesson->messages;
     * </code>
     *
     * @return array
     */
    protected function get_messages() {
        global $SESSION;

        $messages = array();
        if (!empty($SESSION->languagelesson_messages) && is_array($SESSION->languagelesson_messages) && array_key_exists($this->properties->id, $SESSION->languagelesson_messages)) {
            $messages = $SESSION->languagelesson_messages[$this->properties->id];
            unset($SESSION->languagelesson_messages[$this->properties->id]);
        }

        return $messages;
    }

    /**
     * Get all of the attempts for the current user.
     *
     * @param int $retries
     * @param bool $correct Optional: only fetch correct attempts
     * @param int $pageid Optional: only fetch attempts at the given page
     * @param int $userid Optional: defaults to the current user if not set
     * @return array|false
     */
    public function get_attempts($retries, $correct=false, $pageid=null, $userid=null) {
        global $USER, $DB;
        $params = array("lessonid"=>$this->properties->id, "userid"=>$userid, "retry"=>$retries);
        if ($correct) {
            $params['correct'] = 1;
        }
        if ($pageid !== null) {
            $params['pageid'] = $pageid;
        }
        if ($userid === null) {
            $params['userid'] = $USER->id;
        }
        return $DB->get_records('languagelesson_attempts', $params, 'timeseen ASC');
    }

    /**
     * Returns the first page for the lesson or false if there isn't one.
     *
     * This method should be called via the magic method __get();
     * <code>
     * $firstpage = $lesson->firstpage;
     * </code>
     *
     * @return languagelesson_page|bool Returns the languagelesson_page specialised object or false
     */
    protected function get_firstpage() {
        $pages = $this->load_all_pages();
        if (count($pages) > 0) {
            foreach ($pages as $page) {
                if ((int)$page->prevpageid === 0) {
                    return $page;
                }
            }
        }
        return false;
    }

    /**
     * Returns the last page for the lesson or false if there isn't one.
     *
     * This method should be called via the magic method __get();
     * <code>
     * $lastpage = $lesson->lastpage;
     * </code>
     *
     * @return languagelesson_page|bool Returns the languagelesson_page specialised object or false
     */
    protected function get_lastpage() {
        $pages = $this->load_all_pages();
        if (count($pages) > 0) {
            foreach ($pages as $page) {
                if ((int)$page->nextpageid === 0) {
                    return $page;
                }
            }
        }
        return false;
    }

    /**
     * Returns the id of the first page of this lesson. (prevpageid = 0)
     * @return int
     */
    public function get_firstpageid() {
        global $DB;
        if ($this->firstpageid == null) {
            if (!$this->loadedallpages) {
                $firstpageid = $DB->get_field('languagelesson_pages', 'id', array('lessonid'=>$this->properties->id, 'prevpageid'=>0));
                if (!$firstpageid) {
                    print_error('cannotfindfirstpage', 'languagelesson');
                }
                $this->firstpageid = $firstpageid;
            } else {
                $firstpage = $this->get_firstpage();
                $this->firstpageid = $firstpage->id;
            }
        }
        return $this->firstpageid;
    }

    /**
     * Returns the id of the last page of this lesson. (nextpageid = 0)
     * @return int
     */
    public function get_lastpageid() {
        global $DB;
        if ($this->lastpageid == null) {
            if (!$this->loadedallpages) {
                $lastpageid = $DB->get_field('languagelesson_pages', 'id', array('lessonid'=>$this->properties->id, 'nextpageid'=>0));
                if (!$lastpageid) {
                    print_error('cannotfindlastpage', 'languagelesson');
                }
                $this->lastpageid = $lastpageid;
            } else {
                $lastpageid = $this->get_lastpage();
                $this->lastpageid = $lastpageid->id;
            }
        }

        return $this->lastpageid;
    }

     /**
     * Gets the next page id to display after the one that is provided.
     * @param int $nextpageid
     * @return bool
     */
    public function get_next_page($nextpageid) {
        global $USER, $DB;
        $allpages = $this->load_all_pages();
        if ($this->properties->nextpagedefault) {
            // In Flash Card mode...first get number of retakes.
            $nretakes = $DB->count_records("languagelesson_grades", array("lessonid" => $this->properties->id, "userid" => $USER->id));
            shuffle($allpages);
            $found = false;
            if ($this->properties->nextpagedefault == LL_UNSEENPAGE) {
                foreach ($allpages as $nextpage) {
                    if (!$DB->count_records("languagelesson_attempts",
                                            array("pageid" => $nextpage->id, "userid" => $USER->id, "retry" => $nretakes))) {
                        $found = true;
                        break;
                    }
                }
            } else if ($this->properties->nextpagedefault == LL_UNANSWEREDPAGE) {
                foreach ($allpages as $nextpage) {
                    if (!$DB->count_records("languagelesson_attempts",
                                array('pageid' => $nextpage->id, 'userid' => $USER->id, 'correct' => 1, 'retry' => $nretakes))) {
                        $found = true;
                        break;
                    }
                }
            }
            if ($found) {
                if ($this->properties->maxpages) {
                    // Check number of pages viewed (in the lesson).
                    if ($DB->count_records("languagelesson_attempts", array("lessonid" => $this->properties->id, "userid" => $USER->id, "retry" => $nretakes)) >= $this->properties->maxpages) {
                        return LL_EOL;
                    }
                }
                return $nextpage->id;
            }
        }
        // In a normal lesson mode.
        foreach ($allpages as $nextpage) {
            if ((int)$nextpage->id === (int)$nextpageid) {
                return $nextpage->id;
            }
        }
        return LL_EOL;
    }

    /**
     * Sets a message against the session for this lesson that will displayed next
     * time the lesson processes messages
     *
     * @param string $message
     * @param string $class
     * @param string $align
     * @return bool
     */
    public function add_message($message, $class="notifyproblem", $align='center') {
        global $SESSION;

        if (empty($SESSION->languagelesson_messages) || !is_array($SESSION->languagelesson_messages)) {
            $SESSION->languagelesson_messages = array();
            $SESSION->languagelesson_messages[$this->properties->id] = array();
        } else if (!array_key_exists($this->properties->id, $SESSION->languagelesson_messages)) {
            $SESSION->languagelesson_messages[$this->properties->id] = array();
        }

        $SESSION->languagelesson_messages[$this->properties->id][] = array($message, $class, $align);

        return true;
    }

    /**
     * Check if the lesson is accessible at the present time
     * @return bool True if the lesson is accessible, false otherwise
     */
    public function is_accessible() {
        $available = $this->properties->available;
        $deadline = $this->properties->deadline;
        return (($available == 0 || time() >= $available) && ($deadline == 0 || time() < $deadline));
    }

    /**
     * Starts the lesson time for the current user
     * @return bool Returns true
     */
    public function start_timer() {
        global $USER, $DB;
        $USER->startlesson[$this->properties->id] = true;
        $startlesson = new stdClass;
        $startlesson->lessonid = $this->properties->id;
        $startlesson->userid = $USER->id;
        $startlesson->starttime = time();
        $startlesson->lessontime = time();
        $DB->insert_record('languagelesson_timer', $startlesson);
        if ($this->properties->timed) {
            $this->add_message(get_string('maxtimewarning', 'languagelesson', $this->properties->maxtime), 'center');
        }
        return true;
    }

    /**
     * Updates the timer to the current time and returns the new timer object
     * @param bool $restart If set to true the timer is restarted
     * @param bool $continue If set to true AND $restart=true then the timer
     *                        will continue from a previous attempt
     * @return stdClass The new timer
     */
    public function update_timer($restart=false, $continue=false) {
        global $USER, $DB;
        // Clock code.
        // Get time information for this user.
        if (!$timer = $DB->get_records('languagelesson_timer',
                            array ("lessonid" => $this->properties->id, "userid" => $USER->id), 'starttime DESC', '*', 0, 1)) {
            print_error('cannotfindtimer', 'languagelesson');
        } else {
            $timer = current($timer); // This will get the latest start time record.
        }

        if ($restart) {
            if ($continue) {
                // Continue a previous test, need to update the clock  (think this option is disabled atm).
                $timer->starttime = time() - ($timer->lessontime - $timer->starttime);
            } else {
                // Starting over, so reset the clock.
                $timer->starttime = time();
            }
        }

        $timer->lessontime = time();
        $DB->update_record('languagelesson_timer', $timer);
        return $timer;
    }

    /**
     * Updates the timer to the current time then stops it by unsetting the user var
     * @return bool Returns true
     */
    public function stop_timer() {
        global $USER, $DB;
        unset($USER->startlesson[$this->properties->id]);
        return $this->update_timer(false, false);
    }

    /**
     * Checks to see if the lesson has pages
     */
    public function has_pages() {
        global $DB;
        $pagecount = $DB->count_records('languagelesson_pages', array('lessonid'=>$this->properties->id));
        return ($pagecount>0);
    }

    /**
     * Returns the link for the related activity
     * @return array|false
     */
    public function link_for_activitylink() {
        global $DB;
        $module = $DB->get_record('course_modules', array('id' => $this->properties->activitylink));
        if ($module) {
            $modname = $DB->get_field('modules', 'name', array('id' => $module->module));
            if ($modname) {
                $instancename = $DB->get_field($modname, 'name', array('id' => $module->instance));
                if ($instancename) {
                    return html_writer::link(new moodle_url('/mod/'.$modname.'/view.php', array('id'=>$this->properties->activitylink)),
                        get_string('activitylinkname', 'languagelesson', $instancename),
                        array('class'=>'centerpadded lessonbutton standardbutton'));
                }
            }
        }
        return '';
    }

    /**
     * Loads the requested page.
     *
     * This function will return the requested page id as either a specialised
     * languagelesson_page object OR as a generic languagelesson_page.
     * If the page has been loaded previously it will be returned from the pages
     * array, otherwise it will be loaded from the database first
     *
     * @param int $pageid
     * @return languagelesson_page A languagelesson_page object or an object that extends it
     */
    public function load_page($pageid) {
        if (!array_key_exists($pageid, $this->pages)) {
            $manager = languagelesson_page_type_manager::get($this);
            $this->pages[$pageid] = $manager->load_page($pageid, $this);
        }
        return $this->pages[$pageid];
    }

    /**
     * Loads ALL of the pages for this lesson
     *
     * @return array An array containing all pages from this lesson
     */
    public function load_all_pages() {
        if (!$this->loadedallpages) {
            $manager = languagelesson_page_type_manager::get($this);
            $this->pages = $manager->load_all_pages($this);
            $this->loadedallpages = true;
        }
        return $this->pages;
    }

    /**
     * Determines if a jumpto value is correct or not.
     *
     * returns true if jumpto page is (logically) after the pageid page or
     * if the jumpto value is a special value.  Returns false in all other cases.
     *
     * @param int $pageid Id of the page from which you are jumping from.
     * @param int $jumpto The jumpto number.
     * @return boolean True or false after a series of tests.
     **/
    public function jumpto_is_correct($pageid, $jumpto) {
        global $DB;

        // First test the special values
        if (!$jumpto) {
            // Same page
            return false;
        } else if ($jumpto == LL_NEXTPAGE) {
            return true;
        } else if ($jumpto == LL_UNSEENBRANCHPAGE) {
            return true;
        } else if ($jumpto == LL_RANDOMPAGE) {
            return true;
        } else if ($jumpto == LL_CLUSTERJUMP) {
            return true;
        } else if ($jumpto == LL_EOL) {
            return true;
        }

        $pages = $this->load_all_pages();
        $apageid = $pages[$pageid]->nextpageid;
        while ($apageid != 0) {
            if ($jumpto == $apageid) {
                return true;
            }
            $apageid = $pages[$apageid]->nextpageid;
        }
        return false;
    }

    /**
     * Returns the time a user has remaining on this lesson
     * @param int $starttime Starttime timestamp
     * @return string
     */
    public function time_remaining($starttime) {
        $timeleft = $starttime + $this->maxtime * 60 - time();
        $hours = floor($timeleft/3600);
        $timeleft = $timeleft - ($hours * 3600);
        $minutes = floor($timeleft/60);
        $secs = $timeleft - ($minutes * 60);

        if ($minutes < 10) {
            $minutes = "0$minutes";
        }
        if ($secs < 10) {
            $secs = "0$secs";
        }
        $output   = array();
        $output[] = $hours;
        $output[] = $minutes;
        $output[] = $secs;
        $output = implode(':', $output);
        return $output;
    }

    /**
     * Interprets LL_CLUSTERJUMP jumpto value.
     *
     * This will select a page randomly
     * and the page selected will be inbetween a cluster page and end of clutter or end of lesson
     * and the page selected will be a page that has not been viewed already
     * and if any pages are within a branch table or end of branch then only 1 page within
     * the branch table or end of branch will be randomly selected (sub clustering).
     *
     * @param int $pageid Id of the current page from which we are jumping from.
     * @param int $userid Id of the user.
     * @return int The id of the next page.
     **/
    public function cluster_jump($pageid, $userid=null) {
        global $DB, $USER;

        if ($userid===null) {
            $userid = $USER->id;
        }
        // Get the number of retakes.
        if (!$retakes = $DB->count_records("languagelesson_grades", array("lessonid"=>$this->properties->id, "userid"=>$userid))) {
            $retakes = 0;
        }
        // Get all the languagelesson_attempts aka what the user has seen.
        $seenpages = array();
        if ($attempts = $this->get_attempts($retakes)) {
            foreach ($attempts as $attempt) {
                $seenpages[$attempt->pageid] = $attempt->pageid;
            }

        }

        // Get the lesson pages.
        $lessonpages = $this->load_all_pages();
        // Find the start of the cluster.
        while ($pageid != 0) { // This condition should not be satisfied... should be a cluster page.
            if ($lessonpages[$pageid]->qtype == LL_CLUSTER) {
                break;
            }
            $pageid = $lessonpages[$pageid]->prevpageid;
        }

        $clusterpages = array();
        $clusterpages = $this->get_sub_pages_of($pageid, array(LL_ENDOFCLUSTER));
        $unseen = array();
        foreach ($clusterpages as $key=>$cluster) {
            if ($cluster->type !== languagelesson_page::TYPE_QUESTION) {
                unset($clusterpages[$key]);
            } else if ($cluster->is_unseen($seenpages)) {
                $unseen[] = $cluster;
            }
        }

        if (count($unseen) > 0) {
            // It does not contain elements, then use exitjump, otherwise find out next page/branch.
            $nextpage = $unseen[rand(0, count($unseen)-1)];
            if ($nextpage->qtype == LL_BRANCHTABLE) {
                // If branch table, then pick a random page inside of it.
                $branchpages = $this->get_sub_pages_of($nextpage->id, array(LL_BRANCHTABLE, LL_ENDOFBRANCH));
                return $branchpages[rand(0, count($branchpages)-1)]->id;
            } else { // Otherwise, return the page's id.
                return $nextpage->id;
            }
        } else {
            // Seen all there is to see, leave the cluster.
            if (end($clusterpages)->nextpageid == 0) {
                return LL_EOL;
            } else {
                $clusterendid = $pageid;
                while ($clusterendid != 0) { // This condition should not be satisfied... should be a cluster page.
                    if ($lessonpages[$clusterendid]->qtype == LL_CLUSTER) {
                        break;
                    }
                    $clusterendid = $lessonpages[$clusterendid]->prevpageid;
                }
                $exitjump = $DB->get_field("languagelesson_answers", "jumpto",
                                           array("pageid" => $clusterendid, "lessonid" => $this->properties->id));
                if ($exitjump == LL_NEXTPAGE) {
                    $exitjump = $lessonpages[$pageid]->nextpageid;
                }
                if ($exitjump == 0) {
                    return LL_EOL;
                } else if (in_array($exitjump, array(LL_EOL, LL_PREVIOUSPAGE))) {
                    return $exitjump;
                } else {
                    if (!array_key_exists($exitjump, $lessonpages)) {
                        $found = false;
                        foreach ($lessonpages as $page) {
                            if ($page->id === $clusterendid) {
                                $found = true;
                            } else if ($page->qtype == LL_ENDOFCLUSTER) {
                                $exitjump = $DB->get_field("languagelesson_answers", "jumpto",
                                                           array("pageid" => $page->id, "lessonid" => $this->properties->id));
                                break;
                            }
                        }
                    }
                    if (!array_key_exists($exitjump, $lessonpages)) {
                        return LL_EOL;
                    }
                    return $exitjump;
                }
            }
        }
    }

    /**
     * Finds all pages that appear to be a subtype of the provided pageid until
     * an end point specified within $ends is encountered or no more pages exist
     *
     * @param int $pageid
     * @param array $ends An array of LESSON_PAGE_* types that signify an end of
     *               the subtype
     * @return array An array of specialised languagelesson_page objects
     */
    public function get_sub_pages_of($pageid, array $ends) {
        $lessonpages = $this->load_all_pages();
        $pageid = $lessonpages[$pageid]->nextpageid;  // Move to the first page after the branch table.
        $pages = array();

        while (true) {
            if ($pageid == 0 || in_array($lessonpages[$pageid]->qtype, $ends)) {
                break;
            }
            $pages[] = $lessonpages[$pageid];
            $pageid = $lessonpages[$pageid]->nextpageid;
        }

        return $pages;
    }

    /**
     * Checks to see if the specified page[id] is a subpage of a type specified in
     * the $types array, until either there are no more pages of we find a type
     * corresponding to that of a type specified in $ends
     *
     * @param int $pageid The id of the page to check
     * @param array $types An array of types that would signify this page was a subpage
     * @param array $ends An array of types that mean this is not a subpage
     * @return bool
     */
    public function is_sub_page_of_type($pageid, array $types, array $ends) {
        $pages = $this->load_all_pages();
        $pageid = $pages[$pageid]->prevpageid; // Move up one.

        array_unshift($ends, 0);
        // Go up the pages till branch table.
        while (true) {
            if ($pageid==0 || in_array($pages[$pageid]->qtype, $ends)) {
                return false;
            } else if (in_array($pages[$pageid]->qtype, $types)) {
                return true;
            }
            $pageid = $pages[$pageid]->prevpageid;
        }
    }
}


/**
 * Abstract class to provide a core functions to the all lesson classes
 *
 * This class should be abstracted by ALL classes with the lesson module to ensure
 * that all classes within this module can be interacted with in the same way.
 *
 * This class provides the user with a basic properties array that can be fetched
 * or set via magic methods, or alternatively by defining methods get_blah() or
 * set_blah() within the extending object.
 *
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class languagelesson_base {

    /**
     * An object containing properties
     * @var stdClass
     */
    protected $properties;

    /**
     * The constructor
     * @param stdClass $properties
     */
    public function __construct($properties) {
        $this->properties = (object)$properties;
    }

    /**
     * Magic property method
     *
     * Attempts to call a set_$key method if one exists otherwise falls back
     * to simply set the property
     *
     * @param string $key
     * @param mixed $value
     */
    public function __set($key, $value) {
        if (method_exists($this, 'set_'.$key)) {
            $this->{'set_'.$key}($value);
        }
        $this->properties->{$key} = $value;
    }

    /**
     * Magic get method
     *
     * Attempts to call a get_$key method to return the property and ralls over
     * to return the raw property
     *
     * @param str $key
     * @return mixed
     */
    public function __get($key) {
        if (method_exists($this, 'get_'.$key)) {
            return $this->{'get_'.$key}();
        }
        return $this->properties->{$key};
    }

    /**
     * Stupid PHP needs an isset magic method if you use the get magic method and
     * still want empty calls to work.... blah ~!
     *
     * @param string $key
     * @return bool
     */
    public function __isset($key) {
        if (method_exists($this, 'get_'.$key)) {
            $val = $this->{'get_'.$key}();
            return !empty($val);
        }
        return !empty($this->properties->{$key});
    }

    //NOTE: E_STRICT does not allow to change function signature!

    /**
     * If implemented should create a new instance, save it in the DB and return it
     */
    //public static function create() {}
    /**
     * If implemented should load an instance from the DB and return it
     */
    //public static function load() {}
    /**
     * Fetches all of the properties of the object
     * @return stdClass
     */
    public function properties() {
        return $this->properties;
    }
}


/**
 * Abstract class representation of a page associated with a lesson.
 *
 * This class should MUST be extended by all specialised page types defined in
 * mod/lesson/pagetypes/.
 * There are a handful of abstract methods that need to be defined as well as
 * severl methods that can optionally be defined in order to make the page type
 * operate in the desired way
 *
 * Database properties
 * @property int $id The id of this lesson page
 * @property int $lessonid The id of the lesson this page belongs to
 * @property int $prevpageid The id of the page before this one
 * @property int $nextpageid The id of the next page in the page sequence
 * @property int $qtype Identifies the page type of this page
 * @property int $qoption Used to record page type specific options
 * @property int $layout Used to record page specific layout selections
 * @property int $display Used to record page specific display selections
 * @property int $timecreated Timestamp for when the page was created
 * @property int $timemodified Timestamp for when the page was last modified
 * @property string $title The title of this page
 * @property string $contents The rich content shown to describe the page
 * @property int $contentsformat The format of the contents field
 *
 * Calculated properties
 * @property-read array $answers An array of answers for this page
 * @property-read bool $displayinmenublock Toggles display in the left menu block
 * @property-read array $jumps An array containing all the jumps this page uses
 * @property-read languagelesson $lesson The lesson this page belongs to
 * @property-read int $type The type of the page [question | structure]
 * @property-read typeid The unique identifier for the page type
 * @property-read typestring The string that describes this page type
 *
 * @abstract
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class languagelesson_page extends languagelesson_base {

    /**
     * A reference to the lesson this page belongs to
     * @var lesson
     */
    protected $lesson = null;
    /**
     * Contains the answers to this languagelesson_page once loaded
     * @var null|array
     */
    protected $answers = null;
    /**
     * This sets the type of the page, can be one of the constants defined below
     * @var int
     */
    protected $type = 0;

    /**
     * Constants used to identify the type of the page
     */
    const TYPE_QUESTION = 0;
    const TYPE_STRUCTURE = 1;

    /**
     * This method should return the integer used to identify the page type within
     * the database and throughout code. This maps back to the defines used in 1.x
     * @abstract
     * @return int
     */
    abstract protected function get_typeid();
    /**
     * This method should return the string that describes the pagetype
     * @abstract
     * @return string
     */
    abstract protected function get_typestring();

    /**
     * This method gets called to display the page to the user taking the lesson
     * @abstract
     * @param object $renderer
     * @param object $attempt
     * @return string
     */
    abstract public function display($renderer, $attempt);

    /**
     * Creates a new languagelesson_page within the database and returns the correct pagetype
     * object to use to interact with the new lesson
     *
     * @final
     * @static
     * @param object $properties
     * @param languagelesson $lesson
     * @return languagelesson_page Specialised object that extends languagelesson_page
     */
    private static function replace_cloze_content($properties){
    	if (isset($properties ->contents_editor)){ 
    		$contents = $properties -> contents_editor;
    		$text = $contents['text'];
	    	$pattern = '|<\s*a\s([^>]*id="a([0-9]{1,2})"[^>]*)>(.*)<\/a>|U';
	    	$offset = 0;
	    	preg_match($pattern, $text, $matches,PREG_OFFSET_CAPTURE, $offset);
	    	
			while($matches){
				// Operation here.
				// This is the tag of the anchor says the number of answer field it relates to. 
				// Eg "<a id = a2>" matches the answer field number 2;.
				// Because first 2 answers(0,1) are the right/wring responses so answer number actually starts at 2.
				$answer_num = $matches[2][0]-1;

				if (isset($properties->ans_option[$answer_num])){
					// If answer is chosen to use multiple choice (drop down menu), then mark it in class attribute;.
					$attr = 'class="multiplechoice" id="a'.$matches[2][0].'"';
				}else{
					$attr = 'class="shortanswer" id="a'.$matches[2][0].'"';
				}
				
				// Record the content of the .
				$oldanchor = $matches[0][0];
				if (isset($properties->answer_editor[$answer_num]['text'])){
					$answer_content = $properties->answer_editor[$answer_num]['text'];
					$anchor = '<a '.$attr.' title="'.$answer_content.'"></a>';
				}else{
					$anchor = '<a '.$attr.'></a>';
				}
				
				$text = str_replace($oldanchor, $anchor, $text);

	    		$match = reset($matches);
	    		// Next offset should be the pos right after current match.
	    		// Match[1] is the pos for the  beginning of the current match, mathc[0] is the current matched string.
	    		$offset = $match[1]+ strlen($match[0]);
	    		preg_match($pattern, $text, $matches,PREG_OFFSET_CAPTURE, $offset);
			}
			return $text;
    	}
    }
    
    
    final public static function create($properties, languagelesson $lesson, $context, $maxbytes) {
        global $DB;

        $newpage = new stdClass;
        $newpage->title = $properties->title;
 
        // If it is cloze type, replace the content to appropraite format first.
        if ($properties->qtype == LL_CLOZE){
        	$properties->contents_editor['text'] = self::replace_cloze_content($properties);
        }
        $newpage->contents = $properties->contents_editor['text'];
        $newpage->contentsformat = $properties->contents_editor['format'];
        $newpage->lessonid = $lesson->id;
        $newpage->timecreated = time();
        $newpage->qtype = $properties->qtype;
        $newpage->qoption = (isset($properties->qoption))?1:0;
        $newpage->layout = (isset($properties->layout))?1:0;
        $newpage->display = (isset($properties->display))?1:0;
        $newpage->prevpageid = 0; // This is a first page.
        $newpage->nextpageid = 0; // This is the only page.
        //$newpage->branchid = 0; // Set branchid as 0 until we know otherwise.

        if ($properties->pageid) {
            $prevpage = $DB->get_record("languagelesson_pages", array("id" => $properties->pageid));
            if (!$prevpage) {
                print_error('cannotfindpages', 'languagelesson');
            }
            $newpage->prevpageid = $prevpage->id;
            $newpage->nextpageid = $prevpage->nextpageid;
            $lastOrderVal = $prevpage->ordering;
            $newpage->ordering = $lastOrderVal + 1;
        } else {
            $nextpage = $DB->get_record('languagelesson_pages', array('lessonid'=>$lesson->id, 'prevpageid'=>0), 'id');
            if ($nextpage) {
                // This is the first page, there are existing pages put this at the start.
                $newpage->nextpageid = $nextpage->id;
                $newpage->ordering = 0;
            }
        }

        $newpage->id = $DB->insert_record("languagelesson_pages", $newpage);
        
        // languagelesson_reorder_pages($newpage->id);.

        $editor = new stdClass;
        $editor->id = $newpage->id;
        $editor->contents_editor = $properties->contents_editor;
        $editor = file_postupdate_standard_editor($editor, 'contents',
                                                  array('noclean'=>true, 'maxfiles'=>EDITOR_UNLIMITED_FILES, 'maxbytes'=>$maxbytes),
                                                  $context, 'mod_languagelesson', 'page_contents', $editor->id);
        $DB->update_record("languagelesson_pages", $editor);

        // Insert record in languagelesson_branches for tracking branches and ordering.
        if ($newpage->qtype == LL_BRANCHTABLE) {
            $data = new stdClass;
            $data->lessonid = $newpage->lessonid;
            // Figure out if there are other branches in this lesson already.
            $prevbranch = $DB->get_records("languagelesson_branches", array('lessonid'=>$newpage->lessonid), 'ordering', 'ordering');
            if (!$prevbranch) {
                //this is the only branch table in this lesson, so set ordering to 0
                $data->ordering = 1;
            } else {
                // Get count branches for this lesson and increment by one to set ordering for this new branch.
                $branchcount = count($prevbranch);
                $data->ordering += $branchcount;
            }
            
            $data->firstpage = $newpage->nextpageid;
            $data->title = $newpage->title;
            $data->timecreated = time();

            $branchrecord = $DB->insert_record("languagelesson_branches", $data);
        }

        if ($newpage->prevpageid > 0) {
            $DB->set_field("languagelesson_pages", "nextpageid", $newpage->id, array("id" => $newpage->prevpageid));
        
            // Set the branchid.
            // The new page only gets a branch id if it's not the first page (first page can't be in a branch).
            if ($prevpage) {
                // If the previous page is a branch table, this goes in the first branch.
                if ($prevpage->qtype == LL_BRANCHTABLE) {
                    $branchid = $DB->get_field('languagelesson_branches', 'id', array('parentid'=>$prevpage->id, 'ordering'=> 1));
                    // If the preceding page is an end of branch...
                } else if ($prevpage->qtype == LL_ENDOFBRANCH) {
                    if ($newpage->nextpageid) {
                        $branchid = $DB->get_field('languagelesson_pages', 'branchid', array('id'=>$newpage->nextpageid));
                    // If there is no following page, it gets the same branchid as the parent BT.
                    } else {
                        $parentBT = $DB->get_field('languagelesson_branches', 'parentid', array('id'=>$prevpage->branchid));
                        $branchid = $DB->get_field('languagelesson_pages', 'branchid', array('id'=>$parentBT));
                    }
                // Otherwise, it's in the same branch as the preceding page.
                } else {
                    $branchid = $prevpage->branchid;
                }
                $newpage->branchid = $branchid;
            }
        }
        if ($newpage->nextpageid > 0) {
            $DB->set_field("languagelesson_pages", "prevpageid", $newpage->id, array("id" => $newpage->nextpageid));
        }

        $page = languagelesson_page::load($newpage, $lesson);
        $page->create_answers($properties);

        // Add up max score and insert into languagelesson_pages table.
        $maxscore = languagelesson_calc_page_maxscore($newpage->id);
        $DB->set_field('languagelesson_pages', 'maxscore', $maxscore, array('id'=>$newpage->id));

        // Recalculate and set lesson grade after creating this page.
        $lesson->grade = languagelesson_calculate_lessongrade($lesson->id);
        $DB->set_field('languagelesson', 'grade', $lesson->grade, array('id'=>$lesson->id));
        // And push to gradebook.
        $DB->set_field('grade_items', 'grademax', $lesson->grade, array('iteminstance'=>$lesson->id));
        
        // Add to pagecount if it is a page that should be answered by students, for completion tracking by submissions
        switch ($newpage->qtype) {
            case LL_DESCRIPTION:
            case LL_BRANCHTABLE:
            case LL_ENDOFBRANCH:
                break;
            default:
                $lesson->pagecount += 1;
                $DB->set_field('languagelesson', 'pagecount', $lesson->pagecount, array('id'=> $lesson->id));
        }       
        
        languagelesson_reorder_pages($lesson->id);
        
        $lesson->add_message(get_string('insertedpage', 'languagelesson').': '.format_string($newpage->title, true), 'notifysuccess');

        return $page;
    }

    /**
     * This method loads a page object from the database and returns it as a
     * specialised object that extends languagelesson_page
     *
     * @final
     * @static
     * @param int $id
     * @param languagelesson $lesson
     * @return languagelesson_page Specialised languagelesson_page object
     */
    final public static function load($id, languagelesson $lesson) {
        global $DB;

        if (is_object($id) && !empty($id->qtype)) {
            $page = $id;
        } else {
            $page = $DB->get_record("languagelesson_pages", array("id" => $id));
            if (!$page) {
                print_error('cannotfindpages', 'languagelesson');
            }
        }
        $manager = languagelesson_page_type_manager::get($lesson);

        $class = 'languagelesson_page_type_'.$manager->get_page_type_idstring($page->qtype);
        if (!class_exists($class)) {
            $class = 'languagelesson_page';
        }

        return new $class($page, $lesson);
    }

    /**
     * Deletes a languagelesson_page from the database as well as any associated records.
     * @final
     * @return bool
     */
    final public function delete() {
        global $DB;
        // First delete all the associated records...
        $DB->delete_records("languagelesson_attempts", array("pageid" => $this->properties->id));
        // Now delete the answers...
        $DB->delete_records("languagelesson_answers", array("pageid" => $this->properties->id));
        // And the page itself.
        $DB->delete_records("languagelesson_pages", array("id" => $this->properties->id));

        // Repair the hole in the linkage.
        if (!$this->properties->prevpageid && !$this->properties->nextpageid) {
            // This is the only page, no repair needed.
        } else if (!$this->properties->prevpageid) {
            // This is the first page...
            $page = $this->lesson->load_page($this->properties->nextpageid);
            $page->move(null, 0);
        } else if (!$this->properties->nextpageid) {
            // This is the last page...
            $page = $this->lesson->load_page($this->properties->prevpageid);
            $page->move(0);
        } else {
            // Page is in the middle...
            $prevpage = $this->lesson->load_page($this->properties->prevpageid);
            $nextpage = $this->lesson->load_page($this->properties->nextpageid);

            $prevpage->move($nextpage->id);
            $nextpage->move(null, $prevpage->id);
        }
        
        // Remove from $lesson->pagecount if it was an answer-able page
        switch ($this->properties->qtype) {
            case LL_DESCRIPTION:
            case LL_BRANCHTABLE:
            case LL_ENDOFBRANCH:
                break;
            default:
                $pagecount = $DB->get_field('languagelesson', 'pagecount', array('id'=>$this->lesson->id));
                $pagecount -= 1;
                $DB->set_field('languagelesson', 'pagecount', $pagecount, array('id'=>$this->lesson->id));
        }
        
        
        return true;
    }

    /**
     * Moves a page by updating its nextpageid and prevpageid values within
     * the database
     *
     * @final
     * @param int $nextpageid
     * @param int $prevpageid
     */
    final public function move($nextpageid=null, $prevpageid=null) {
        global $DB;
        if ($nextpageid === null) {
            $nextpageid = $this->properties->nextpageid;
        }
        if ($prevpageid === null) {
            $prevpageid = $this->properties->prevpageid;
        }
        $obj = new stdClass;
        $obj->id = $this->properties->id;
        $obj->prevpageid = $prevpageid;
        $obj->nextpageid = $nextpageid;
        $DB->update_record('languagelesson_pages', $obj);
    }

	/** 
	* Gets selected pages from edit.php returns array of page obj
	*
	* @param array $pageids
	* @return array
	*/
	
	final public function get_selected_pages($pageids) {
		global $DB;
		$pages = array();
		foreach ($pageids as $pageid) {
			$page = $DB->get_record('languagelesson_pages', array('pageid' => $pageid));
			$pages[$page->ordering] = $page;
		}
		
		return $pages;
	}
	
	
	/**
	* Moves a group of pages by updating prevpageid of first in the series, 
	* nextpageid of last in the series and then calls reorder_pages
	* 
	* @param array $pages ($key = ordering, $value=pageid)
	* @param int $newprevpage
	* @param int $newnextpage
	* @param int $lessonid
	* @param array $pages
	*/
	
	final public function moveall($newprevpage, $newnextpage, $lessonid, $pages) {
		// Make sure the pages are in sort order by key value.
		ksort($pages);
		// Replace the prevpageid of the first page with that given.
		$firstpage = array_shift($pages);
		$DB->set_field('languagelesson_pages', 'prevpageid', $newprevpage, array('pageid' => $firstpage));
		 
		// Replace the nextpageid of the last page with that given.
		$lastpage = array_pop($pages);
		$DB->set_field('languagelesson_pages', 'nextpageid', $newnextpage, array('pageid' => $lastpage));
		
		// Run reorder_pages function (the one that works).
		languagelesson_reorder_pages($lessonid);
	}
	
    /**
     * Returns the answers that are associated with this page in the database
     *
     * @final
     * @return array
     */
    final public function get_answers() {
        global $DB;
        if ($this->answers === null) {
            $this->answers = array();
            $answers = $DB->get_records('languagelesson_answers', array('pageid'=>$this->properties->id, 'lessonid'=>$this->lesson->id), 'id');
            if (!$answers) {
                // It is possible that a lesson upgraded from Moodle 1.9 still.
                // Contains questions without any answers [MDL-25632].
                return array();
            }
            foreach ($answers as $answer) {
                $this->answers[count($this->answers)] = new languagelesson_page_answer($answer);
            }
        }
        return $this->answers;
    }

    /**
     * Returns the lesson this page is associated with
     * @final
     * @return lesson
     */
    final protected function get_lesson() {
        return $this->lesson;
    }

    /**
     * Returns the type of page this is. Not to be confused with page type
     * @final
     * @return int
     */
    final protected function get_type() {
        return $this->type;
    }

    /**
     * Records an attempt at this page
     *
     * @final
     * @global moodle_database $DB
     * @param stdClass $context
     * @return stdClass Returns the result of the attempt
     */
    final public function record_attempt($context) {
        global $DB, $USER, $OUTPUT;
        /**
         * This should be overridden by each page type to actually check the response
         * against what ever custom criteria they have defined
         */
        $result = $this->check_answer();
        $result->attemptsremaining  = 0;
        $result->maxattemptsreached = false;
        $lesson = $this->lesson;
        $modid = $DB->get_field('modules', 'id', array('name'=>'languagelesson'));
        $cmid = $DB->get_field('course_modules', 'id', array('module'=>$modid, 'instance'=>$this->lesson->id));

        if ($result->noanswer) {
            $result->newpageid = $this->properties->id; // Display same page again.
            $result->feedback  = get_string('noanswer', 'languagelesson');
        } else {
                switch ($result->typeid) {
                    
                case LL_ESSAY :
                    $isessayquestion = true;
                
                    $attempt = new stdClass;
                    $attempt->lessonid = $this->lesson->id;
                    $attempt->pageid = $this->properties->id;
                    $attempt->userid = $USER->id;
                    $attempt->type = $result->typeid;
                    $attempt->answerid = $result->answerid;
                
                    $useranswer = $result->userresponse;
                    $useranswer = clean_param($useranswer, PARAM_RAW);
                    $attempt->useranswer = $useranswer;    
                    // If the student had previously submitted an attempt on this question, and it has since been graded,
		            // Mark this new submission as a resubmit.
                    if ($prevAttempt = languagelesson_get_most_recent_attempt_on($attempt->pageid, $USER->id)) {
                        $attempt->retry = $prevAttempt->retry;
                        if (! $oldManAttempt = $DB->get_record('languagelesson_attempts', array('id'=>$prevAttempt->id))) {
                                error('Failed to fetch matching manual_attempt record for old attempt on this question!');
                        }
                        if ($oldManAttempt->graded && !$lesson->autograde) {
                            $attempt->resubmit = 1;
                            $attempt->viewed = 0;
                            $attempt->graded = 0;
                        }
                    } else {
                        $attempt->retry = 0;
                    }
                    /*if (!$answer = $DB->get_record("languagelesson_answers", array("pageid"=>$page->id))) {
                        print_error("Continue: No answer found");
                    }*/
                    $correctanswer = false;
		            //$newpageid = $this->nextpageid;

                    // AUTOMATIC GRADING.
                    // If this lesson is to be auto-graded...
                    if ($lesson->autograde === 1) {
                        $correctanswer = true;
                        // Flag it as graded
                        $attempt->graded = 1;
                        $attempt->viewed = 1;
                        // Set the grade to the maximum point value for this question.
                        $maxscore = $DB->get_field('languagelesson_pages', 'maxscore', array('id'=>$attempt->pageid));
                        $score = $maxscore;
                    } else {
        		    // If it's not, mark these submissions as ungraded.
                        $score = 0;
                    }
                    
                    $attempt->iscurrent = 1;
                    $attempt->score = $score;
                    $attempt->timeseen = time();
                    
                    // Check for maxattempts, 0 means unlimited attempts are allowed.
                    $nattempts = $attempt->retry;
                    if ($this->lesson->maxattempts != 0) { // Don't bother with message if unlimited.
                        if ($nattempts >= $this->lesson->maxattempts || $this->lesson->maxattempts == 1){
                            $result->maxattemptsreached = true;
                            $result->newpageid = LL_NEXTPAGE;
                            $result->attemptsremaining = 0;
                        } else {
                            $result->attemptsremaining = $this->lesson->maxattempts - $nattempts;
                        }
                    }
                    
                    // Insert/update some records.
                    if (!has_capability('mod/languagelesson:manage', $context)) {
                        // Pull the retry value for this attempt, and handle deflagging former current attempt 
			
                        if ($oldAttempt = languagelesson_get_most_recent_attempt_on($attempt->pageid, $USER->id)) {
                            $nretakes = $oldAttempt->retry + 1;

                            // Update the old attempt to no longer be marked as the current one.
                            $attempt->id = $oldAttempt->id;
                            
                            // Set the retake value.
                            $attempt->retry = $nretakes;
                        
                            // Flag this as the current attempt.
                            $attempt->correct = $correctanswer;
                            
                            if (! $DB->update_record('languagelesson_attempts', $attempt)) {
                                    error('Failed to update previous attempt!');
                            }
                            
                        } else {
                            $nretakes = 1;
                           
                            // Set the retake value.
                            $attempt->retry = $nretakes;
                            
                            // Flag this as the current attempt.
                            $attempt->correct = $correctanswer;
                            
                            // Every try is recorded as a new one (by increasing retry value), so just insert this one.
                            if (!$newattemptid = $DB->insert_record("languagelesson_attempts", $attempt)) {
                                    error("Continue: attempt not inserted");
                            }
                        }
                }    
                    break;
                
                default :
                    
                    // Record student's attempt.
                    $attempt = new stdClass;
                    $attempt->lessonid = $this->lesson->id;
                    $attempt->pageid = $this->properties->id;
                    $attempt->userid = $USER->id;
                    
                    if ($result->answerid != null) {
                        $attempt->answerid = $result->answerid;
                    } else {
                        $attempt->answerid = 0;
                    }
                    
                    $attempt->type = $result->typeid;
                    $attempt->correct = $result->correctanswer;
                    $attempt->iscurrent = 1;
 
                    if ($result->score == null) {
                        $attempt->score = 0;
                    } else if ($result->correctanswer) {
                        $maxscore = $DB->get_field('languagelesson_pages', 'maxscore', array('id'=>$attempt->pageid));
                        $attempt->score = $maxscore;
                    }  else {
                       $attempt->score = $result->score;
                    }
                    
                    if ($result->userresponse !== null) {
                        $attempt->useranswer = $result->userresponse;
                    }
    
                    $attempt->timeseen = time();
                    
                    if ($previousattempt = $DB->get_record('languagelesson_attempts',
                                        array('lessonid'=> $attempt->lessonid, 'pageid'=>$attempt->pageid,
                                       'userid'=>$attempt->userid, 'iscurrent'=>1))) {
                        $attempt->id = $previousattempt->id;
                        $attempt->retry = $previousattempt->retry + 1;
                        if ($oldFile = $DB->get_record('files', array('id'=>$previousattempt->fileid))) {
                            // Delete the previous audio file.
                            languagelesson_delete_submitted_file($oldFile);
                        }
                        if ($previousattempt->graded = 1) {
                            // Set it as resubmit.
                            $attempt->resubmit = 1;
                            // Remove old feedback files if they exist
                            if ($oldfeedback = $DB->get_records('languagelesson_feedback', array('attemptid'=>$attempt->id), null, 'id, fileid')) {
                                if ($oldfeedback->fileid != NULL) {
                                    foreach ($oldfeedback as $oldrecord) {
                                       $oldfilerecord = $DB->get_record('files', array('id'=>$oldrecord->fileid));
                                       languagelesson_delete_submitted_file($oldfilerecord);
                                       $DB->delete_records('languagelesson_feedback', array('fileid'=>$oldrecord->fileid));
                                    }
                                }
                            }
                        }
                        if (($this->lesson->maxattempts == 0) || ($this->lesson->maxattempts >= $attempt->retry)) {
                            $DB->update_record("languagelesson_attempts", $attempt, true);
                        }
                    } else {
                        $attempt->retry = 1;
                        $DB->insert_record('languagelesson_attempts', $attempt, true);
                    }
                    $recordedattemptid = $DB->get_field('languagelesson_attempts', 'id',
                                                        array('lessonid'=>$attempt->lessonid, 'userid'=>$attempt->userid,
                                                              'pageid'=>$attempt->pageid));

                } // End switch.
                
                // And update the languagelesson's grade.
		// NOTE that this happens no matter the question type.

                if ($lesson->type != LL_TYPE_PRACTICE) {
                    // Get the lesson's graded information.

                    if ($gradeinfo = $DB->get_record('languagelesson_grades', array('lessonid'=>$lesson->id, 'userid'=>$USER->id))){
                        $gradeinfo->grade = languagelesson_calculate_user_lesson_grade($lesson->id, $USER->id);
                    } else {
                        $gradeinfo = new stdClass;
                        $gradeinfo->grade = languagelesson_calculate_user_lesson_grade($lesson->id, $USER->id);
                    }
                    
                    // Save the grade.
                    languagelesson_save_grade($lesson->id, $USER->id, $gradeinfo->grade);
                    
                    // Finally, update the records in the gradebook.
                    languagelesson_grade_item_update($lesson);
                    
                    $gradeitem = $DB->get_record('grade_items', array('iteminstance'=>$lesson->id, 'itemmodule'=>'languagelesson'));
                    $DB->set_field('grade_grades', 'finalgrade', $gradeinfo->grade, array('userid'=>$USER->id, 'itemid'=>$gradeitem->id));
                    
                    languagelesson_update_grades($lesson, $USER->id);
                    
                }
                        
                // "number of attempts remaining" message if $this->lesson->maxattempts > 1
                // Displaying of message(s) is at the end of page for more ergonomic display.
                
                // IT'S NOT HITTING THIS CONTROL GROUP BELOW FOR SOME REASON.
                if ((!$result->correctanswer && ($result->newpageid == 0)) || $result->typeid == LL_AUDIO) {
                    // Wrong answer and student is stuck on this page - check how many attempts.
                    // The student has had at this page/question.
                    $nattempts = $attempt->retry;
                    // $nattempts = $DB->count_records("languagelesson_attempts", array("pageid"=>$this->properties->id,
                    // "userid"=>$USER->id, "retry" => $attempt->retry));
                    // Retreive the number of attempts left counter for displaying at bottom of feedback page.
                    if ($this->lesson->maxattempts != 0) { // Don't bother with message if unlimited.
                        if ($nattempts >= $this->lesson->maxattempts || $this->lesson->maxattempts == 1){
                            $result->maxattemptsreached = true;
                            $result->newpageid = LL_NEXTPAGE;
                            $result->attemptsremaining = 0;
                        } else {
                            $result->attemptsremaining = $this->lesson->maxattempts - $nattempts;
                        }
                    }
                }
            
            // TODO: merge this code with the jump code below.  Convert jumpto page into a proper page id.
            if ($result->newpageid == 0) {
                $result->newpageid = $this->properties->id;
            } else if ($result->newpageid == LL_NEXTPAGE) {
                $result->newpageid = $this->lesson->get_next_page($this->properties->nextpageid);
            }

            // Determine default feedback if necessary.
            if (empty($result->response)) {
                if (!$this->lesson->feedback && !$result->noanswer && !($this->lesson->review & !$result->correctanswer && !$result->isessayquestion)) {
                    // These conditions have been met:
                    //  1. The lesson manager has not supplied feedback to the student
                    //  2. Not displaying default feedback
                    //  3. The user did provide an answer
                    //  4. We are not reviewing with an incorrect answer (and not reviewing an essay question).

                    $result->nodefaultresponse = true;  // This will cause a redirect below.
                } else if ($result->isessayquestion) {
                    $result->response = get_string('defaultessayresponse', 'languagelesson');
                } else if ($result->correctanswer) {
                    if ($this->lesson->defaultfeedback == true) {
                        $result->response = $this->lesson->defaultcorrect;
                    } else {
                        $result->response = get_string('thatsthecorrectanswer', 'languagelesson');
                    }
                } else {
                    if ($this->lesson->defaultfeedback == true) {
                        $result->response = $this->lesson->defaultwrong;
                    } else {
                        $result->response = get_string('thatsthewronganswer', 'languagelesson');
                    }
                }
            }
        
            if ($result->typeid == LL_AUDIO) {
                if ($result->audiodata) {
                $uploadData = $result->audiodata;
                $mp3data = json_decode($uploadData, true, 5);
                $recordedattempt = $DB->get_record('languagelesson_attempts', array('id'=>$recordedattemptid));
 
                foreach ($mp3data['mp3Data'] as $newfilename => $newfilebits) {
                    // Send the file to the pool and return the file id.
                    $recordedattempt->fileid = upload_audio_file($USER->id, $cmid, $recordedattemptid, $newfilename, $newfilebits);
                    $DB->update_record('languagelesson_attempts', $recordedattempt);
                }
                }
            } else if ($result->response) {
                if ($this->lesson->review && !$result->correctanswer && !$result->isessayquestion) {
                    $nretakes = $DB->count_records("languagelesson_grades", array("lessonid"=>$this->lesson->id, "userid"=>$USER->id));
                    $qattempts = $DB->count_records("languagelesson_attempts",
                                                array("userid"=>$USER->id, "retry"=>$nretakes, "pageid"=>$this->properties->id));
                    if ($qattempts == 1) {
                        $result->feedback = $OUTPUT->box(get_string("firstwrong", "languagelesson"), 'feedback');
                    } else {
                        $result->feedback = $OUTPUT->BOX(get_string("secondpluswrong", "languagelesson"), 'feedback');
                    }
                }
                else
                {
                	$class = 'response';
                    if ($result->correctanswer) {
                        $class .= ' correct'; // CSS over-ride this if they exist (!important).
                    } else if (!$result->isessayquestion) {
                        $class .= ' incorrect'; // CSS over-ride this if they exist (!important).
                    }
                    $options = new stdClass;
                    $options->noclean = true;
                    $options->para = true;
                    $options->overflowdiv = true;
                    
                    if ($result->typeid == LL_CLOZE)
                    {
                    	// Lets do our own thing for CLOZE - get question_html.
                    	$my_page = $DB->get_record('languagelesson_pages', array('id'=>$attempt->pageid));
                    	$question_html = $my_page->contents;
                    	
                    	// Get user answers from the attempt.
                    	$attempt_answers = $DB->get_record('languagelesson_attempts', array('id'=>$recordedattemptid));
                	$user_answers_str = $attempt_answers->useranswer;
                		
                	// Get the lesson page so we can use functions from cloze.php.
                    	$manager = languagelesson_page_type_manager::get($lesson);
			$page = $manager->load_page($attempt->pageid, $lesson);
	    				
	    		// Get our cloze_correct_incorrect_view html.
	    		$html = $page->get_cloze_correct_incorrect_view($user_answers_str, $question_html);
	    		$result->feedback = $html;
                    }
                    else
                    {
                    	$result->feedback = $OUTPUT->box(format_text($this->get_contents(), $this->properties->contentsformat, $options), 'generalbox boxaligncenter');
                    	$result->feedback .= '<div class="correctanswer generalbox"><em>'.get_string("youranswer", "languagelesson").'</em> : '.$result->studentanswer; // Already in clean html
                    	$result->feedback .= $OUTPUT->box($result->response, $class); // Already conerted to HTML
                    	$result->feedback .= '</div>';
                    }
                }
            }
        }
        
        // Update completion state
        $course = get_course($lesson->course);
        $cm = get_coursemodule_from_id('languagelesson', $cmid, 0, false, MUST_EXIST);
        $completion=new completion_info($course);
        if ($completion->is_enabled($cm) && $lesson->completionsubmit) {
            $completion->update_state($cm, COMPLETION_COMPLETE, $USER->id);
        }
        
        return $result;
    }
    


    /**
     * Returns the string for a jump name
     *
     * @final
     * @param int $jumpto Jump code or page ID
     * @return string
     **/
    final protected function get_jump_name($jumpto) {
        global $DB;
        static $jumpnames = array();

        if (!array_key_exists($jumpto, $jumpnames)) {
            if ($jumpto == LL_THISPAGE) {
                $jumptitle = get_string('thispage', 'languagelesson');
            } else if ($jumpto == LL_NEXTPAGE) {
                $jumptitle = get_string('nextpage', 'languagelesson');
            } else if ($jumpto == LL_EOL) {
                $jumptitle = get_string('endoflesson', 'languagelesson');
            } else if ($jumpto == LL_UNSEENBRANCHPAGE) {
                $jumptitle = get_string('unseenpageinbranch', 'languagelesson');
            } else if ($jumpto == LL_PREVIOUSPAGE) {
                $jumptitle = get_string('previouspage', 'languagelesson');
            } else if ($jumpto == LL_RANDOMPAGE) {
                $jumptitle = get_string('randompageinbranch', 'languagelesson');
            } else if ($jumpto == LL_RANDOMBRANCH) {
                $jumptitle = get_string('randombranch', 'languagelesson');
            } else if ($jumpto == LL_CLUSTERJUMP) {
                $jumptitle = get_string('clusterjump', 'languagelesson');
            } else {
                if (!$jumptitle = $DB->get_field('languagelesson_pages', 'title', array('id' => $jumpto))) {
                    $jumptitle = '<strong>'.get_string('notdefined', 'languagelesson').'</strong>';
                }
            }
            $jumpnames[$jumpto] = format_string($jumptitle,true);
        }

        return $jumpnames[$jumpto];
    }

    /**
     * Constructor method
     * @param object $properties
     * @param languagelesson $lesson
     */
    public function __construct($properties, languagelesson $lesson) {
        parent::__construct($properties);
        $this->lesson = $lesson;
    }

    /**
     * Returns the score for the attempt
     * This may be overridden by page types that require manual grading
     * @param array $answers
     * @param object $attempt
     * @return int
     */
    public function earned_score($answers, $attempt) {
        return $answers[$attempt->answerid]->score;
    }

    /**
     * This is a callback method that can be override and gets called when ever a page
     * is viewed
     *
     * @param bool $canmanage True if the user has the manage cap
     * @return mixed
     */
    public function callback_on_view($canmanage) {
        return true;
    }

    /**
     * Updates a lesson page and its answers within the database
     *
     * @param object $properties
     * @return bool
     */
    public function update($properties, $context = null, $maxbytes = null) {
        global $DB, $PAGE;
        $answers  = $this->get_answers();
        $properties->id = $this->properties->id;
        $properties->lessonid = $this->lesson->id;
        if (empty($properties->qoption)) {
            $properties->qoption = '0';
        }
        if (empty($context)) {
            $context = $PAGE->context;
        }
        if (empty($answers) && $properties->qtype == LL_MULTICHOICE) {
            $properties->qtype = LL_DESCRIPTION;
        }
        if ($maxbytes === null) {
            $maxbytes = get_user_max_upload_file_size($context);
        }
                
        // If cloze type then replace the content in processable format.
        if ($properties->qtype == LL_CLOZE){
        	$properties->contents_editor['text'] = self::replace_cloze_content($properties);
        }
        $properties = file_postupdate_standard_editor($properties, 'contents',
                                    array('noclean'=>true, 'maxfiles'=>EDITOR_UNLIMITED_FILES, 'maxbytes'=>$maxbytes),
                                    $context, 'mod_languagelesson', 'page_contents', $properties->id);
        $DB->update_record("languagelesson_pages", $properties);
        
        $maxanswers = $this->lesson->maxanswers;
        if (($anscount = count($this->answers)) >= $maxanswers) {
            $maxanswers = $anscount+2;
        }

        for ($i = 0; $i < $maxanswers; $i++) {
            if (!array_key_exists($i, $this->answers)) {
                $this->answers[$i] = new stdClass;
                $this->answers[$i]->lessonid = $this->lesson->id;
                $this->answers[$i]->pageid = $this->id;
                $this->answers[$i]->timecreated = $this->timecreated;
            }
            if (isset($properties->ans_option[$i])){
            	$this->answers[$i]->flags = $properties->ans_option[$i];
            }else{
            	$this->answers[$i]->flags = 0;
            }
            if (!empty($properties->answer_editor[$i]) && is_array($properties->answer_editor[$i])) {
                $this->answers[$i]->answer = $properties->answer_editor[$i]['text'];
                $this->answers[$i]->answerformat = $properties->answer_editor[$i]['format'];
            }
            if (!empty($properties->response_editor[$i]) && is_array($properties->response_editor[$i])) {
                $this->answers[$i]->response = $properties->response_editor[$i]['text'];
                $this->answers[$i]->responseformat = $properties->response_editor[$i]['format'];
            }
            if (isset($properties->ans_option[$i])){
            	$this->answers[$i]->flags = $properties->ans_option[$i];
            }

            // We don't need to check for isset here because properties called it's own isset method.
            // For cloze type we dont ignore empty answers so we set default here.
            if ($this->answers[$i]->answer != '' || $properties->qtype == LL_CLOZE) {
                if (isset($properties->jumpto[$i])) {
                    $this->answers[$i]->jumpto = $properties->jumpto[$i];
                }
                $this->answers[$i]->answer = strip_tags($this->answers[$i]->answer);
                if (isset($properties->score[$i])) {
                    $this->answers[$i]->score = $properties->score[$i];
                }
                if (!isset($this->answers[$i]->id)) {
                    $this->answers[$i]->id =  $DB->insert_record("languagelesson_answers", $this->answers[$i]);
                } else if ($this->answers[$i]->answer == NULL && $this->answers[$i]->score == 0) {
                    $DB->delete_records('languagelesson_answers', array('id'=>$this->answers[$i]->id));
                    unset($this->answers[$i]);
                } else {
                    $DB->update_record("languagelesson_answers", $this->answers[$i]->properties());
                }
            }

        }
        // Update maxscore field of page record.
        $maxscore = languagelesson_calc_page_maxscore($this->id);
        $DB->set_field("languagelesson_pages", 'maxscore', $maxscore, array('id'=>$this->id));
        
        // Recalculate and set lesson grade after creating this page.
        $lessongrade = languagelesson_calculate_lessongrade($properties->lessonid);
        
        $DB->set_field('languagelesson', 'grade', $lessongrade, array('id'=>$properties->lessonid));
        // And push to gradebook.
        $DB->set_field('grade_items', 'grademax', $lessongrade, array('iteminstance'=>$properties->lessonid));
        
        languagelesson_reorder_pages($properties->lessonid);

        return true;
    }

    /**
     * Can be set to true if the page requires a static link to create a new instance
     * instead of simply being included in the dropdown
     * @param int $previd
     * @return bool
     */
    public function add_page_link($previd) {
        return false;
    }

    /**
     * Returns true if a page has been viewed before
     *
     * @param array|int $param Either an array of pages that have been seen or the
     *                   number of retakes a user has had
     * @return bool
     */
    public function is_unseen($param) {
        global $USER, $DB;
        if (is_array($param)) {
            $seenpages = $param;
            return (!array_key_exists($this->properties->id, $seenpages));
        } else {
            $nretakes = $param;
            if (!$DB->count_records("languagelesson_attempts", array("pageid"=>$this->properties->id,
                                                                     "userid"=>$USER->id, "retry"=>$nretakes))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Checks to see if a page has been answered previously
     * @param int $nretakes
     * @return bool
     */
    public function is_unanswered($nretakes) {
        global $DB, $USER;
        if (!$DB->count_records("languagelesson_attempts", array('pageid'=>$this->properties->id, 'userid'=>$USER->id,
                                                                 'correct'=>1, 'retry'=>$nretakes))) {
            return true;
        }
        return false;
    }

    /**
     * Creates answers within the database for this languagelesson_page. Usually only ever
     * called when creating a new page instance
     * @param object $properties
     * @return array
     */
    public function create_answers($properties) {
        global $DB;
        // Now add the answers
        $newanswer = new stdClass;
        $newanswer->lessonid = $this->lesson->id;
        $newanswer->pageid = $this->properties->id;
        $newanswer->timecreated = $this->properties->timecreated;

        $answers = array();

        $maxanswers = $this->lesson->maxanswers;
        
        if (($anscount = count($this->answers)) > $maxanswers) {
            $maxanswers = $anscount+2;
        }

        for ($i = 0; $i < $maxanswers; $i++) {
            $answer = clone($newanswer);

            // Check if answer option is enabled, especiallly for cloze type.
            if (isset($properties->ans_option[$i])){
            	$answer->flags = $properties->ans_option[$i];
            }
            // If it is in multiple choices format then record the right answer.
            if (!empty($properties->answer_editor[$i]) && is_array($properties->answer_editor[$i])) { 
            $answer->answer = $properties->answer_editor[$i]['text'];
            $answer->answerformat = $properties->answer_editor[$i]['format'];
            }
            if (!empty($properties->response_editor[$i]) && is_array($properties->response_editor[$i])) {
                    $answer->response = $properties->response_editor[$i]['text'];
                    $answer->responseformat = $properties->response_editor[$i]['format'];
            }
	    // For cloze type we dont ignore empty answers so we set default here.

            if (isset($answer->answer) && ($answer->answer != '' || $properties->qtype == LL_CLOZE)) {
                if (isset($properties->jumpto[$i])) {
                    $answer->jumpto = $properties->jumpto[$i];
                }
                $answer->answer = strip_tags($answer->answer);
                // Set score to 0 for branch tables.
                if ($properties->qtype == LL_BRANCHTABLE) {
                    $answer->score = "0.00";
                } else {
                    $answer->score = $properties->score[$i];
                }
                $answer->id = $DB->insert_record("languagelesson_answers", $answer);
                $answers[$answer->id] = new languagelesson_page_answer($answer);
            } else {
                break;
            }
        }
        $this->answers = $answers;
        return $answers;
    }

    /**
     * This method MUST be overridden by all question page types, or page types that
     * wish to score a page.
     *
     * The structure of result should always be the same so it is a good idea when
     * overriding this method on a page type to call
     * <code>
     * $result = parent::check_answer();
     * </code>
     * before modifying it as required.
     *
     * @return stdClass
     */
    public function check_answer() {
        $result = new stdClass;
        $result->answerid        = 0;
        $result->noanswer        = false;
        $result->correctanswer   = false;
        $result->isessayquestion = false;   // Use this to turn off review button on essay questions
        $result->response        = '';
        $result->newpageid       = 0;       // Stay on the page
        $result->studentanswer   = '';      // Use this to store student's answer(s) in order to display it on feedback page
        $result->userresponse    = null;
        $result->feedback        = '';
        $result->nodefaultresponse  = false; // Flag for redirecting when default feedback is turned off
        return $result;
    }

    /**
     * True if the page uses a custom option
     *
     * Should be override and set to true if the page uses a custom option.
     *
     * @return bool
     */
    public function has_option() {
        return false;
    }

    /**
     * Returns the maximum number of answers for this page given the maximum number
     * of answers permitted by the lesson.
     *
     * @param int $default
     * @return int
     */
    public function max_answers($default) {
        return $default;
    }

    /**
     * Returns the properties of this lesson page as an object
     * @return stdClass;
     */
    public function properties() {
        $properties = clone($this->properties);
        if ($this->answers === null) {
            $this->get_answers();
        }
        if (count($this->answers)>0) {
            $count = 0;
            foreach ($this->answers as $answer) {
                $properties->{'answer_editor['.$count.']'} = array('text'=>$answer->answer, 'format'=>$answer->answerformat);
                $properties->{'response_editor['.$count.']'} = array('text'=>$answer->response, 'format'=>$answer->responseformat);
                $properties->{'jumpto['.$count.']'} = $answer->jumpto;
                $properties->{'score['.$count.']'} = $answer->score;
                $count++;
            }
        }
        return $properties;
    }

    /**
     * Returns an array of options to display when choosing the jumpto for a page/answer
     * @static
     * @param int $pageid
     * @param languagelesson $lesson
     * @return array
     */
    public static function get_jumptooptions($pageid, languagelesson $lesson) {
        global $DB;
        $jump = array();
        $jump[0] = get_string("thispage", "languagelesson");
        $jump[LL_NEXTPAGE] = get_string("nextpage", "languagelesson");
        $jump[LL_PREVIOUSPAGE] = get_string("previouspage", "languagelesson");
        $jump[LL_EOL] = get_string("endoflesson", "languagelesson");

        if ($pageid == 0) {
            return $jump;
        }

        $pages = $lesson->load_all_pages();
        if ($pages[$pageid]->qtype == LL_BRANCHTABLE || $lesson->is_sub_page_of_type($pageid,
                                                                    array(LL_BRANCHTABLE), array(LL_ENDOFBRANCH, LL_CLUSTER))) {
            $jump[LL_UNSEENBRANCHPAGE] = get_string("unseenpageinbranch", "languagelesson");
            $jump[LL_RANDOMPAGE] = get_string("randompageinbranch", "languagelesson");
        }
        if ($pages[$pageid]->qtype == LL_CLUSTER || $lesson->is_sub_page_of_type($pageid, array(LL_CLUSTER), array(LL_ENDOFCLUSTER))) {
            $jump[LL_CLUSTERJUMP] = get_string("clusterjump", "languagelesson");
        }
        if (!optional_param('firstpage', 0, PARAM_INT)) {
            $apageid = $DB->get_field("languagelesson_pages", "id", array("lessonid" => $lesson->id, "prevpageid" => 0));
            while (true) {
                if ($apageid) {
                    $title = $DB->get_field("languagelesson_pages", "title", array("id" => $apageid));
                    $jump[$apageid] = strip_tags(format_string($title,true));
                    $apageid = $DB->get_field("languagelesson_pages", "nextpageid", array("id" => $apageid));
                } else {
                    // Last page reached
                    break;
                }
            }
        }
        return $jump;
    }
    /**
     * Returns the contents field for the page properly formatted and with plugin
     * file url's converted
     * @return string
     */
    public function get_contents() {
        global $PAGE;
        if (!empty($this->properties->contents)) {
            if (!isset($this->properties->contentsformat)) {
                $this->properties->contentsformat = FORMAT_HTML;
            }
            $context = context_module::instance($PAGE->cm->id);
            $contents = file_rewrite_pluginfile_urls($this->properties->contents, 'pluginfile.php', $context->id,
                                                    'mod_languagelesson', 'page_contents', $this->properties->id);
            // Must do this BEFORE format_text()!!!!!!
            return format_text($contents, $this->properties->contentsformat,
                               array('context'=>$context, 'noclean'=>true));
            // Page edit is marked with XSS, we want all content here.
        } else {
            return '';
        }
    }

    /**
     * Set to true if this page should display in the menu block
     * @return bool
     */
    protected function get_displayinmenublock() {
        return false;
    }

    /**
     * Get the string that describes the options of this page type
     * @return string
     */
    public function option_description_string() {
        return '';
    }

    /**
     * Updates a table with the answers for this page
     * @param html_table $table
     * @return html_table
     */
    public function display_answers(html_table $table) {
        $answers = $this->get_answers();
        $i = 1;
        foreach ($answers as $answer) {
            $cells = array();
            $cells[] = "<span class=\"label\">".get_string("jump", "languagelesson")." $i<span>: ";
            $cells[] = $this->get_jump_name($answer->jumpto);
            $table->data[] = new html_table_row($cells);
            if ($i === 1){
                $table->data[count($table->data)-1]->cells[0]->style = 'width:20%;';
            }
            $i++;
        }
        return $table;
    }

    /**
     * Determines if this page should be grayed out on the management/report screens
     * @return int 0 or 1
     */
    protected function get_grayout() {
        return 0;
    }

    /**
     * Adds stats for this page to the &pagestats object. This should be defined
     * for all page types that grade
     * @param array $pagestats
     * @param int $tries
     * @return bool
     */
    public function stats(array &$pagestats, $tries) {
        return true;
    }

    /**
     * Formats the answers of this page for a report
     *
     * @param object $answerpage
     * @param object $answerdata
     * @param object $useranswer
     * @param array $pagestats
     * @param int $i Count of first level answers
     * @param int $n Count of second level answers
     * @return object The answer page for this
     */
    public function report_answers($answerpage, $answerdata, $useranswer, $pagestats, &$i, &$n) {
        $answers = $this->get_answers();
        $formattextdefoptions = new stdClass;
        $formattextdefoptions->para = false;  //I'll use it widely in this page
        foreach ($answers as $answer) {
            $data = get_string('jumpsto', 'languagelesson', $this->get_jump_name($answer->jumpto));
            $answerdata->answers[] = array($data, "");
            $answerpage->answerdata = $answerdata;
        }
        return $answerpage;
    }

    /**
     * Gets an array of the jumps used by the answers of this page
     *
     * @return array
     */
    public function get_jumps() {
        global $DB;
        $jumps = array();
        $params = array ("lessonid" => $this->lesson->id, "pageid" => $this->properties->id);
        if ($answers = $this->get_answers()) {
            foreach ($answers as $answer) {
                $jumps[] = $this->get_jump_name($answer->jumpto);
            }
        } else {
            $jumps[] = $this->get_jump_name($this->properties->nextpageid);
        }
        return $jumps;
    }
    /**
     * Informs whether this page type require manual grading or not
     * @return bool
     */
    public function requires_manual_grading() {
        return false;
    }

    /**
     * A callback method that allows a page to override the next page a user will
     * see during when this page is being completed.
     * @return false|int
     */
    public function override_next_page() {
        return false;
    }

    /**
     * This method is used to determine if this page is a valid page
     *
     * @param array $validpages
     * @param array $pageviews
     * @return int The next page id to check
     */
    public function valid_page_and_view(&$validpages, &$pageviews) {
        $validpages[$this->properties->id] = 1;
        return $this->properties->nextpageid;
    }
}



/**
 * Class used to represent an answer to a page
 *
 * @property int $id The ID of this answer in the database
 * @property int $lessonid The ID of the lesson this answer belongs to
 * @property int $pageid The ID of the page this answer belongs to
 * @property int $jumpto Identifies where the user goes upon completing a page with this answer
 * @property int $grade The grade this answer is worth
 * @property int $score The score this answer will give
 * @property int $flags Used to store options for the answer
 * @property int $timecreated A timestamp of when the answer was created
 * @property int $timemodified A timestamp of when the answer was modified
 * @property string $answer The answer itself
 * @property string $response The response the user sees if selecting this answer
 *
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class languagelesson_page_answer extends languagelesson_base {

    /**
     * Loads an page answer from the DB
     *
     * @param int $id
     * @return languagelesson_page_answer
     */
    public static function load($id) {
        global $DB;
        $answer = $DB->get_record("languagelesson_answers", array("id" => $id));
        return new languagelesson_page_answer($answer);
    }

    /**
     * Given an object of properties and a page created answer(s) and saves them
     * in the database.
     *
     * @param stdClass $properties
     * @param languagelesson_page $page
     * @return array
     */
    public static function create($properties, languagelesson_page $page) {
        return $page->create_answers($properties);
    }

}

/**
 * A management class for page types
 *
 * This class is responsible for managing the different pages. A manager object can
 * be retrieved by calling the following line of code:
 * <code>
 * $manager  = languagelesson_page_type_manager::get($lesson);
 * </code>
 * The first time the page type manager is retrieved the it includes all of the
 * different page types located in mod/lesson/pagetypes.
 *
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class languagelesson_page_type_manager {

    /**
     * An array of different page type classes
     * @var array
     */
    protected $types = array();

    /**
     * Retrieves the lesson page type manager object
     *
     * If the object hasn't yet been created it is created here.
     *
     * @staticvar languagelesson_page_type_manager $pagetypemanager
     * @param languagelesson $lesson
     * @return languagelesson_page_type_manager
     */
    public static function get(languagelesson $lesson) {
        static $pagetypemanager;
        if (!($pagetypemanager instanceof languagelesson_page_type_manager)) {
            $pagetypemanager = new languagelesson_page_type_manager();
            $pagetypemanager->load_languagelesson_types($lesson);
        }
        return $pagetypemanager;
    }

    /**
     * Finds and loads all lesson page types in mod/lesson/pagetypes
     *
     * @param languagelesson $lesson
     */
    public function load_languagelesson_types(languagelesson $lesson) {
        global $CFG;
        $basedir = $CFG->dirroot.'/mod/languagelesson/pagetypes/';
        $dir = dir($basedir);
        while (false !== ($entry = $dir->read())) {
            if (strpos($entry, '.')===0 || !preg_match('#^[a-zA-Z]+\.php#i', $entry)) {
                continue;
            }
            require_once($basedir.$entry);
            $class = 'languagelesson_page_type_'.strtok($entry,'.');
            if (class_exists($class)) {
                $pagetype = new $class(new stdClass, $lesson);
                $this->types[$pagetype->typeid] = $pagetype;
            }
        }

    }

    /**
     * Returns an array of strings to describe the loaded page types
     *
     * @param int $type Can be used to return JUST the string for the requested type
     * @return array
     */
    public function get_page_type_strings($type=null, $special=true) {
        $types = array();
        foreach ($this->types as $pagetype) {
            if (($type===null || $pagetype->type===$type) && ($special===true || $pagetype->is_standard())) {
                $types[$pagetype->typeid] = $pagetype->typestring;
            }
        }
        return $types;
    }

    /**
     * Returns the basic string used to identify a page type provided with an id
     *
     * This string can be used to instantiate or identify the page type class.
     * If the page type id is unknown then 'unknown' is returned
     *
     * @param int $id
     * @return string
     */
    public function get_page_type_idstring($id) {
        foreach ($this->types as $pagetype) {
            if ((int)$pagetype->typeid === (int)$id) {
                return $pagetype->idstring;
            }
        }
        return 'unknown';
    }

    /**
     * Loads a page for the provided lesson given it's id
     *
     * This function loads a page from the lesson when given both the lesson it belongs
     * to as well as the page's id.
     * If the page doesn't exist an error is thrown
     *
     * @param int $pageid The id of the page to load
     * @param languagelesson $lesson The lesson the page belongs to
     * @return languagelesson_page A class that extends languagelesson_page
     */
    public function load_page($pageid, languagelesson $lesson) {
        global $DB;
        if (!($page =$DB->get_record('languagelesson_pages', array('id'=>$pageid, 'lessonid'=>$lesson->id)))) {
            print_error('cannotfindpages', 'languagelesson');
        }
        $pagetype = get_class($this->types[$page->qtype]);
        $page = new $pagetype($page, $lesson);
        return $page;
    }

    /**
     * This function loads ALL pages that belong to the lesson.
     *
     * @param languagelesson $lesson
     * @return array An array of languagelesson_page_type_*
     */
    public function load_all_pages(languagelesson $lesson) {
        global $DB;
        if (!($pages =$DB->get_records('languagelesson_pages', array('lessonid'=>$lesson->id)))) {
            print_error('cannotfindpages', 'languagelesson');
        }
        foreach ($pages as $key=>$page) {
            $pagetype = get_class($this->types[$page->qtype]);
            $pages[$key] = new $pagetype($page, $lesson);
        }

        $orderedpages = array();
        $lastpageid = 0;

        while (true) {
            foreach ($pages as $page) {
                if ((int)$page->prevpageid === (int)$lastpageid) {
                    $orderedpages[$page->id] = $page;
                    unset($pages[$page->id]);
                    $lastpageid = $page->id;
                    if ((int)$page->nextpageid===0) {
                        break 2;
                    } else {
                        break 1;
                    }
                }
            }
        }

        return $orderedpages;
    }

    /**
     * Fetches an mform that can be used to create/edit an page
     *
     * @param int $type The id for the page type
     * @param array $arguments Any arguments to pass to the mform
     * @return languagelesson_add_page_form_base
     */
    public function get_page_form($type, $arguments) {
        $class = 'languagelesson_add_page_form_'.$this->get_page_type_idstring($type);
        if (!class_exists($class) || get_parent_class($class)!=='languagelesson_add_page_form_base') {
            debugging('Lesson page type unknown class requested '.$class, DEBUG_DEVELOPER);
            $class = 'languagelesson_add_page_form_selection';
        } else if ($class === 'languagelesson_add_page_form_unknown') {
            $class = 'languagelesson_add_page_form_selection';
        }
        return new $class(null, $arguments);
    }

    /**
     * Returns an array of links to use as add page links
     * @param int $previd The id of the previous page
     * @return array
     */
    public function get_add_page_type_links($previd) {
        global $OUTPUT;

        $links = array();

        foreach ($this->types as $key=>$type) {
            if ($link = $type->add_page_link($previd)) {
                $links[$key] = $link;
            }
        }

        return $links;
    }
}

// ----- Constants and functions copied from LL locallib.php --------------------

// DEFINE JUMP VALUES.
//  TODO: instead of using define statements, create an array with all the jump values.

/**
 * Jump to Next Page
 */
if (!defined("LL_NEXTPAGE")) {
    define("LL_NEXTPAGE", -1);
}
/**
 * Stay on same page
 */
if (!defined("LL_THISPAGE")) {
	define("LL_THISPAGE", 0);
}
/**
 * End of Lesson
 */
if (!defined("LL_EOL")) {
    define("LL_EOL", -9);
}
/**
 * Jump to an unseen page within a branch and end of branch or end of lesson
 */
if (!defined("LL_UNSEENBRANCHPAGE")) {
    define("LL_UNSEENBRANCHPAGE", -50);
}
/**
 * Jump to Previous Page
 */
if (!defined("LL_PREVIOUSPAGE")) {
    define("LL_PREVIOUSPAGE", -40);
}
/**
 * Jump to a random page within a branch and end of branch or end of lesson
 */
if (!defined("LL_RANDOMPAGE")) {
    define("LL_RANDOMPAGE", -60);
}
/**
 * Jump to a random Branch
 */
if (!defined("LL_RANDOMBRANCH")) {
    define("LL_RANDOMBRANCH", -70);
}
/**
 * Cluster Jump
 */
if (!defined("LL_CLUSTERJUMP")) {
    define("LL_CLUSTERJUMP", -80);
}
/**
 * Undefined
 */    
if (!defined("LL_UNDEFINED")) {
    define("LL_UNDEFINED", -99);
}


/**
 * Returns course module, course and module instance given 
 * either the course module ID or a lesson module ID.
 *
 * @param int $cmid Course Module ID
 * @param int $lessonid Lesson module instance ID
 * @return array array($cm, $course, $lesson)
 **/
function languagelesson_get_basics($cmid = 0, $lessonid = 0) {
	global $DB;
	
    if ($cmid) {
        if (!$cm = get_coursemodule_from_id('languagelesson', $cmid)) {
            print_error('Course Module ID was incorrect');
        }
        if (!$course = $DB->get_record('course', array('id'=>$cm->course))) {
            print_error('Course is misconfigured');
        }
        if (!$lesson = $DB->get_record('languagelesson', array('id'=>$cm->instance))) {
            print_error('Course module is incorrect');
        }
    } else if ($lessonid) {
        if (!$lesson = $DB->get_record('languagelesson', array('id'=>$lessonid))) {
            print_error('Course module is incorrect');
        }
        if (!$course = $DB->get_record('course', array('id'=>$lesson->course))) {
            print_error('Course is misconfigured');
        }
        if (!$cm = get_coursemodule_from_instance('languagelesson', $lesson->id, $course->id)) {
            print_error('Course Module ID was incorrect');
        }
    } else {
        print_error('No course module ID or lesson ID were passed');
    }
    
    return array($cm, $course, $lesson);
}

function languagelesson_print_header($cm, $course, $lesson, $currenttab = '') {
    global $CFG, $USER, $PAGE, $OUTPUT;

    $strlesson = get_string('modulename', 'languagelesson');
    $strname   = format_string($lesson->name, true, $course->id);

    $context = context_module::instance($cm->id);

    if (has_capability('mod/languagelesson:edit', $context)) {
        $button = update_module_button($cm->id, $course->id, $strlesson);
    } else {
        $button = '';
    }

// Header setup.
    $navigation = $PAGE->navbar->add('', $cm);
    
// Print header, heading, tabs and messages.
        if ($currenttab != 'grader') {
            $PAGE->set_heading($course->fullname);
        }
        $PAGE->set_title("$course->shortname: $strname");
        
	echo $OUTPUT->header();

    if (has_capability('mod/languagelesson:grade', $context)) {
        echo $OUTPUT->heading($strname, 2, "lesson", $course->id);

        if (!empty($currenttab)) {
            include($CFG->dirroot.'/mod/languagelesson/tabs.php');
        }
    } else {
        $OUTPUT->heading($strname);
    }

    languagelesson_print_messages();

    return true;
}
/**
 * Print all set messages.
 *
 * See {@link languagelesson_set_message()} for setting messages.
 *
 * Uses {@link notify()} to print the messages.
 *
 * @uses $SESSION
 * @return boolean
 **/
function languagelesson_print_messages() {
    global $SESSION;
    
    if (empty($SESSION->lesson_messages)) {
        // No messages to print.
        return true;
    }
    
    foreach ($SESSION->lesson_messages as $message) {
        notify($message[0], $message[1], $message[2]);
    }
    
    // Reset.
    unset($SESSION->lesson_messages);
    
    return true;
}

/**
 * Fetches an array of users assigned to the 'student' role in the input course
 * @param int $courseid The Course to fetch students from
 */
function languagelesson_get_students($courseid)
{
	global $DB;
	
	// Pull the context value for input courseid.
	$context = context_course::instance($courseid);
        $allusers = get_enrolled_users($context, 'mod/languagelesson:submit', 0, 'u.id, u.firstname, u.lastname, u.email, u.picture');
       
        $getteachers = get_enrolled_users($context, 'mod/languagelesson:manage', 0, 'u.id');
        $getgraders = get_enrolled_users($context, 'mod/languagelesson:grade', 0, 'u.id');
        
        
        $teachers = array();
        
        foreach ($getteachers as $thisteacher) {
            $teachers[] = $thisteacher->id;
        }
        foreach ($getgraders as $thisgrader) {
            $teachers[] = $thisgrader->id;
        }
        sort($teachers);
        
        // Remove all of the teachers those with manage or grade capabilities from the list.
        foreach ($allusers as $oneuser) {
            foreach ($teachers as $teacherid) {
                if ($oneuser->id == $teacherid) {
                    unset($allusers[$oneuser->id]);
                }
            }
        } 
	return $allusers;
}

/*
 * Function to retrieve attempts by a user for questions in a languagelesson
 *
 * This allows for retry values to not all be the same (thus helping partial-attempt correcting
 * functionality)
 *
 * @param int $lesson The lessonid to fetch attempts for
 * @param int $user The userid to fetch attempts for
 * @param bool $mostrecent Should we only be fetching the most recent attempt on each question?
 * @return array $attempts An array of attempt record objects, one for each page that the user has
 * 							submitted an attempt for (returns only the most recent attempt for
 * 							each page)
 */
function languagelesson_get_attempts($lesson, $user, $mostrecent) {
	global $CFG, $DB;
	
	$select = "select a.*, p.ordering as ordering, p.qtype as qtype";

	$from = "from {languagelesson_pages} p
				inner join
				{languagelesson_attempts} a
				on p.id = a.pageid";
	
	$where = "where p.lessonid=?
				and a.userid=?"
				. (($mostrecent) ? ' and a.iscurrent=?' : '');

	$orderby = "order by p.ordering" . ((!$mostrecent) ? ', a.retry' : '');
	
        $array = array($lesson, $user, 1);
        
	$query =	"$select
				$from
				$where
				$orderby";
				
	
	$attempts = $DB->get_records_sql($query, $array);
	
	return $attempts;
}

/**
 * Shorthand function; retrieves the most recent attempt by a user for all questions in a lesson
 *
 * @param int $lessonid The languagelesson ID to fetch attempts for
 * @param int $userid The user ID to fetch attempts by
 * @return array $attempts Array of attempt record objects, one for each page in the languagelesson
 */
function languagelesson_get_most_recent_attempts($lessonid, $userid) {
	return languagelesson_get_attempts($lessonid, $userid, true);
}


/**
 * Checks the input HTML question text against the list of answers given to make sure that the question is valid
 *
 * Valid is defined as:
 *  - Each question location is defined by a named anchor
 *  - The name of each anchor is a number
 *  - The number of each anchor matches with the number of exactly one answer
 *  - There are exactly as many question anchors and questions
 *
 * @param object $form The data entered into the question page form
 * @return bool True if valid, errors out if invalid
 */
function languagelesson_validate_cloze_text($form) {
    $valid = true;

    // The plain, non-escaped html of the question text to validate.
    $html = stripslashes($form->contents);

    // The keyed array of answers as entered into the page form.
    if (isset($form->answer)) { $answertexts = $form->answer; }
    else { error('No answers defined for this page! Use your browser\'s back button to define answers and retry.'); }

    // Any dropdown boxes that were checked.
    if (isset($form->dropdown)) { $dropdowns = $form->dropdown; }
    else { $dropdowns = array(); }

    $doc = new DOMDocument();
    $doc->loadHTML($html);
    $links = $doc->getElementsByTagName('a');

    // Pull the subset of the links that are anchors (have 'name' attribute).
    $anchors = array();
    foreach ($links as $link) {
        $attrs = $link->attributes;
        if ($attrs->getNamedItem('name')) {
            $anchors[] = $link;
        }
    }

    // Pull the list of anchor names given (if any clones are found, error out).
    $namesseen = array();
    foreach ($anchors as $anchor) {
        $name = $anchor->attributes->getNamedItem('name')->nodeValue;
        if (in_array($name, $namesseen)) {
            $message = 'Found two questions with the same number!';
            $valid = false;
            break;
        } else if (!is_numeric($name)) {
            $message = 'Found non-numeric question label: '.$name;
            $valid = false;
            break;
        }
        $namesseen[] = $name;
    }

    // Remove any empty items from $answertexts.
    if ($valid) {
        $realanswers = array();
        foreach ($answertexts as $num => $answer) {
            if (!empty($answer)) { $realanswers[$num] = $answer; }
        }
        $answertexts = $realanswers;
    }

    // Make sure the number of anchors and the number of answers match.
    if ($valid) {
        $numqs = count($namesseen);
        $numas = count($answertexts);
        if ($numqs != $numas) {
            $message = "Cloze parsing: the number of questions placed in the text did not match the number of answers provided: $numqs
                questions found, $numas answers found. You may have forgotten to place a question, or to have named one.";
            $valid = false;
        }
    }

    // Compare anchor names given to answer numbers given.
    $matches = array();
    if ($valid) {
        foreach ($namesseen as $name) {
            $name = intval($name);
            $namecorrected = $name - 1; // The question names are indexed from 1, but answers are indexed from 0.
            if (!isset($answertexts[$namecorrected])) {
                $message = 'Found question label with no corresponding answer: '.$name;
                $valid = false;
                break;
            }
            $matches[] = $name;
        }
    }

    // Check that the number of matches corresponds with the number of answers.
    if ($valid && count($matches) != count($answertexts)) {
        $message = 'More answers were provided than matched question labels given!';
        $valid = false;
    }

    // Now go through and make sure that any drop-downs have a correct answer marked.
    foreach ($dropdowns as $num => $val) {
        if ($val) {
            if (! preg_match('/=/', $answertexts[$num])) {
                $message = 'No correct answer was found for drop-down question '.($num+1);
                $valid = false;
            }
        }
    }

    // If an error was thrown, print it out here.
    if (!$valid) {
        $text = "Cloze parsing: $message <br /><br />Please use your browser's back button to return to the question editing page.";
        error($text);
    }

    return true;
}



/**
 * Breaks apart a CLOZE question text into text chunks between questions
 *
 * Assumes that cloze question locations are marked with anchor tags <a name="..."></a>
 *
 * NOTE that this runs using regular expressions. This is emphatically NOT the way to deal with HTML code most of the time, but the
 * situation here (finding the string indices of these tags, the fact that the tags are very specific) and the fact that using anchor
 * tags introduces a little bit of unreliability in and of itself (it would be, for example, easy to create unbalanced tags in a
 * question and throw off the processing by editing the HTML) make it such that regexes can be considered a viable solution, and in my
 * opinion possibly a better (certainly an easier and faster) solution than using DOM or XML parsers.
 *
 * @param string $text The question text to be parsed
 * @return array $chunks The chunks of the text around the cloze questions
 */
function languagelesson_parse_cloze($text) {
    
    // Pattern to ungreedily find paired anchor open/close tags.
    $pattern = '/<\s*a[^>]*name="[^"]*"[^>]*>.*<\/a>/U';

    // Pull all matches of the pattern and store them in $elements.
    preg_match_all($pattern, $text, $elements);
    // This stores them in a 2-d array, so pull the first (only) result (the matches with the first pattern).
    $elements = reset($elements);

    // Initialize the array to hold the question text chunks.
    $chunks = array();
    // Set the current index (from which we look ahead) to 0.
    $offset = 0;
    // Init the array to hold question.
    foreach ($elements as $question) {
        // Pull the index at which the current question starts, looking from the end of the previous question.
        $start = strpos($text, $question, $offset);
        // Pull the chunk of text between the end of the previous question and the start of this one.
        $nextchunk = substr($text, $offset, $start-$offset);
        $chunks[] = $nextchunk;
        // Pull the name value for this question (indicating the question number).
        $qnum = languagelesson_extract_qnum($question);
        // And store that in chunks (offset by 1, so that it's indexed from 0).
        $chunks[] = intval($qnum)-1;
        // Move the current index past the end of this question.
        $offset = $start + strlen($question);
    }
    // If there is a last chunk of text (after the final question), save it, otherwise, save an empty string.
    if ($lastchunk = substr($text, $offset)) {
        $chunks[] = $lastchunk;
    } else {
        $chunks[] = '';
    }

    return $chunks;
}


function languagelesson_key_cloze_answers($answers) {
    // Save the answers in an array, keyed to their order of appearance.
    $keyedAnswers = array();
    foreach ($answers as $answer) {
        // Only look at the actual answers, not custom feedback (saved to its own answer record).
        if ($answer->answer) {
            $atext = $answer->answer;
            list($num, $text) = explode('|', $atext);
            $answer->answer = $text;
            $keyedAnswers[$num] = $answer;
        }
    }
    return $keyedAnswers;
}

function languagelesson_print_feedback_table($manattempt, $gradingmode=false) {
	global $CFG, $USER, $DB, $lesson;
	if ($attemptype = 'closed') {
	
	    $where = "attemptid = $attempt->id";

	    $feedbacks = $DB->get_records_select('languagelesson_feedback', $where);

	    $select = "attemptid = $attempt->id and not isnull(fname)";

	    $hasFeedbackFiles = $DB->count_records_select('languagelesson_feedback', $select);
	} else {
	$where = "manattemptid = $manattempt->id";
	if ($gradingmode) { $where .= ' and not isnull(text)'; }
	
	$feedbacks = $DB->get_records_select('languagelesson_feedback', $where);
	
	$select = "manattemptid = $manattempt->id and not isnull(fname)";

	$hasFeedbackFiles = $DB->count_records_select('languagelesson_feedback', $select);
	}
	
// If this was called from view.php ($gradingmode=false), then only print anything if there
// Is feedback to show; if this was called from respond_window.php ($gradingmode=true), at least
// The WYSIWYG text editor needs to be printed, regardless if other feedback exists.
	if ($feedbacks || $gradingmode) {
		echo '<div id="feedback_area">';

		if (!$gradingmode) { print_heading(get_string('submissionfeedback', 'languagelesson'), '', 4); }
		
		echo 	"<script type=\"text/javascript\">
				
				var curselected = null;
				var curselected_oldid = null;
				var curpic = null;
				var curpic_oldid = null;
				var element = null;
				var pic = null;
				
				function displayThisTeach(elname, picname) {
				  // Pull the element corresponding to the input name and the currently-visible element
					element = document.getElementById(elname);
					curselected = document.getElementById('curselected');

					pic = document.getElementById(picname);
					curpic = document.getElementById('curselectedpic');
					
				  // Only toggle elements if clicked on non-selected picture
					if (element.style.display == \"none\") {
						element.style.display = \"table-row\";
						curselected.style.display = \"none\";

						pic.className = 'activePic';
						curpic.className = 'inactivePic';
						
					  // Reset the formerly-visible element's id
						curselected.id = curselected_oldid;
					  // And update the relevant values for the newly-selected element
						curselected_oldid = element.id;
						curselected = element;
						curselected.id = 'curselected';

						curpic.id = curpic_oldid;
						curpic_oldid = pic.id;
						curpic = pic;
						pic.id = 'curselectedpic';
					}
				}
			</script>";
		
	// Establish feedback storage variables here (they're referred to later, even if there is no feedback saved).
		$feedbackdata = array();    // 2-d arr storing information of all saved feedback.
		$thistext = '';             // Stores the text to be put into the WYSIWYG editor as starting value.
		$basename = 'fb_block_';    // Establish the base of the ID attribute for each teacher feedback div.
		$picname  = 'teacher_pic_'; // Establish the base of the ID attribute for each teacher picture tab.
		$teachernames = array();    // Array mapping <teacherID> => <teacherName> (for clearer information for student).
		

	// Print the start of the feedback table.
		echo '<table class="feedbackTable">';

	// Print out the start of the teacher/feedback-selection tab row.
		echo '<tr id="teacherTabRowContainer">';
		echo '<td id="teacherTabRowContainerCell">';
		echo '<div class="teacherTabRow ' . (($gradingmode) ? 'left' : 'center') . '">';
		echo '<ul class="teacherPics">';

		// If this is the grading window, the current teacher's picture will be displayed (along with the WYSIWYG editor) no matter
		// What, so force that here.
		if ($gradingmode) {
			echo 	"<li id='wysiwyg_pic' onclick = \"displayThisTeach('wysiwyg', 'wysiwyg_pic');\">";
			print_user_picture($USER, $lesson->course, $USER->picture, 0, false, false);
			echo 	'</li>';
		}
		
	// Regardless of mode, this content should only be called if there are other feedback.
	// Records to display.
		if ($feedbacks) {
			

		// Fill in the feedbackdata array with all the info for each teacher's submitted feedback.
		// Feedback data looks like:
		//       teacherID => { 'text'   =>   <textual feedback>,
		///						 'files'  =>   <feedback file paths>,
		// 					 'time'   =>   <time of most recent feedback submission> }.
			foreach ($feedbacks as $feedback) {
			// If this is the teacher's view (respond_window) and the feedback being examined.
			// Is the viewing teacher's text feedback, save it and skip the below code.
				if ($gradingmode && $feedback->teacherid == $USER->id && $feedback->text) {
					$thistext = $feedback->text;
					continue;
				}
				
			// If the current feedback is from a teacher we haven't seen yet, initialize the feedback.
			// Data structure for that teacher.
				if (!array_key_exists($feedback->teacherid, $feedbackdata)) {
					$feedbackdata[$feedback->teacherid] = array();
					$feedbackdata[$feedback->teacherid]['text'] = '';
					$feedbackdata[$feedback->teacherid]['files'] = array();
					$feedbackdata[$feedback->teacherid]['time'] = 0;
				}
				
				// Set text or file feedback for this item's submitting teacher appropriately.
				if ($feedback->text) { $feedbackdata[$feedback->teacherid]['text'] = $feedback->text; }
				else if ($feedback->fname) {
					$dir = languagelesson_get_file_area($manattempt, $feedback);
					$feedbackdata[$feedback->teacherid]['files'][] = "$dir/$feedback->fname";
				}

				// And update the time of submission for the most recent feedback by this teacher.
				if ($feedback->timeseen > $feedbackdata[$feedback->teacherid]['time']) {
					$feedbackdata[$feedback->teacherid]['time'] = $feedback->timeseen;
				}
				
			}
			
			
		// Print out the rest of the teacher pictures in tabbed form to enable switching between different feedback sets;.
        // Also implode Feedback files list here.
			foreach ($feedbackdata as $teachID => $fbdata) {
				// Implode each teacher's feedback file paths set into a comma-separated list.
				$feedbackdata[$teachID]['files'] = implode(',', $fbdata['files']);

				// Print this teacher's tab.
				if ($teachID != $USER->id || !$gradingmode) {
					echo "<li id=\"{$picname}{$teachID}\" class=\"inactivePic\"
						onclick='displayThisTeach(\"{$basename}{$teachID}\", \"{$picname}{$teachID}\");'>";
					$thisteach = get_record('user', 'id', $teachID);
					print_user_picture($thisteach, $lesson->course, $thisteach->picture, 0, false, false);
					// Store the teacher's full name for printing later to distinguish feedbacks.
					$teachernames[$teachID] = fullname($thisteach);
					echo '</li>';
				}
				
			// If this is the respond_window, include submission times in the text feedback.
				if ($gradingmode && $teachID != $USER->id) {
					$a->fullname = fullname($thisteach);
					$a->text = $feedbackdata[$teachID]['text'];
					$feedbackdata[$teachID]['text'] = get_string('feedbacktextframe', 'languagelesson', $a);
				}
			}
			
			
		}

	// Close out the teacher/feedback-tab row.
		echo '</ul>';
		// Print this to cancel out the float=left of the above ul
		echo '<div style="clear:both"></div>';
		echo '</td></tr>';


	// If this is the respond_window (the teacher is grading), then print out their required WYSIWYG editor.
		if ($gradingmode) {
			echo 		"<tr id=\"wysiwyg\" class=\"contentRow\">
						 <td class=\"feedbackCell\">
						<script type=\"text/javascript\">
						  // Initialize the curselected data to point to the WYSIWYG editor
							var wysiwyg = document.getElementById('wysiwyg');
							curselected_oldid = 'wysiwyg';
							curselected = wysiwyg;
							curselected.id = 'curselected';

							var wyspic = document.getElementById('wysiwyg_pic');
							curpic_oldid = 'wysiwyg_pic';
							curpic = wyspic;
							curpic.id = 'curselectedpic';
							curpic.className = 'activePic';
						</script>";
			
			// Check if we can use the WYSIWYG.
			$usehtmleditor = can_use_html_editor();
			// Print out the area for text feedback.
			print_textarea($usehtmleditor,0,0,300,50, 'text_response', $thistext);
			// If we can use WYSIWYG, switch it on.
			if ($usehtmleditor) { use_html_editor('text_response'); }
			
			echo		'</td></tr>';
		}
		
		$teacherIDs = array_keys($feedbackdata);
		// There may be no submitted feedback yet, so set the div ids $firstFeedback and $firstPic accordingly.
		if (! empty($teacherIDs)) {	$firstFeedback = $basename . $teacherIDs[0]; $firstPic = $picname . $teacherIDs[0]; }
		else { $firstFeedback = $basename; $firstPic = $picname; }
		$flag = false;
		foreach ($feedbackdata as $teachID => $fbarr) {
			echo "<tr id='{$basename}{$teachID}' class='contentRow' style='display:none'><td>";

			// Open the single feedback table.
			echo '<table class="singleFeedback">';

			// And open the teacher info/text feedback row.
			echo '<tr class="textRow">';

			// Print out the teacher's submission info.
			echo '<td class="feedbackCell teacherInfoCell">';
			echo '<div class="teacherName">'.$teachernames[$teachID].'</div>';
			echo '<div class="submissionTime">'.userdate($fbarr['time']).'</div>';
			echo '</td>';

			// If there is text feedback, print it here.
			if (!empty($fbarr['text'])) {
				echo '<td class="feedbackCell textFeedbackCell">';
				echo '<div class="subheader">'.get_string('comments','languagelesson').'</div>';
				echo '<div class="textFeedback">'.$fbarr['text'].'</div>';
				echo '</td>';
			}
			
			// Close out the info row.
			echo '</tr>';
			
			// Now, if the student is viewing and there are feedback files to display, print them out here.
			if (!$gradingmode && $fbarr['files']) {

				// Open up the row and cell to contain the revlet.
				echo '<tr class="filesRow">';
				echo '<td class="feedbackCell filesContainer" colspan="2">';

				echo '<div class="subheader">'.get_string('audioresponse','languagelesson').'</div>';

				// Print out the instructions for hearing the feedback dependent on the question type (if it's an audio, it's complex.
				// Feedback; if it's ESSAY or VIDEO, it's simple.
				echo '<div class="revletInstructions">'
					.get_string( (($manattempt->type == LL_AUDIO) ? 'feedbackplayerinstructions' : 'feedbackplayerinstructionssimple'),
							'languagelesson')
					.'</div>';

				if (!$flag) {
					$qmodpluginID = true;
					$modpluginID = 'plugina';
				}

			  // Show the FB player revlet stack.
				include($CFG->dirroot . '/mod/languagelesson/runrev/feedback/player/revA.php');

				echo "\t\tMoodleSession=\"". $_COOKIE['MoodleSession'] . "\"\n" ;  
				echo "\t\tMoodleSessionTest=\"" . $_COOKIE['MoodleSessionTest'] . "\"\n";
				echo "\t\tMOODLEID=\"" . $_COOKIE['MOODLEID_'] . "\"\n"; 
				echo "\t\tsesskey=\"" . sesskey() . "\"\n"; 
				echo "\t\tid=\"" . $manattempt->lessonid . "\"\n";
				echo "\t\tuserid=\"" . $USER->id . "\"\n";
				
				// If this is an audio type question, we're using complex feedback, so get the path to the student's submitted file and
				// Load it in as the basic file to display, then load in the (multiple) feedback files to display as speech bubbles.
				if ($manattempt->type == LL_AUDIO) {
					$dir = languagelesson_get_file_area($manattempt);
					$src = "$dir/$manattempt->fname";
					echo "\t\tstudentfile=\"$src\"\n"; // Path to the student file to be downloaded.
					echo "\t\tfeedbackfnames=\"".$fbarr['files']."\"\n";
				}
				// If it's not, though, we're using simple feedback, so use the (ONE!) feedback file whose path is stored in
				// $fbarr['files'] and load it in as the main file, then load an empty list for the speech bubble files.
				else {
					echo "\t\tstudentfile=\"".$fbarr['files']."\"\n";
					echo "\t\tfeedbackfnames=\"\"\n";
				}
				
			// Only include the revB file once (it's only necessary once); after that, just close.
			// The embedding tags.
				if (!$flag) {
					include($CFG->dirroot . '/mod/languagelesson/runrev/revB.php');
					$flag = true;
					// Make sure that the extra revlets in the page are still not in <divs id="plugin" ..., so that if revWeb is not.
					// Installed, the audio/video recorder gets hidden properly.
					$modpluginID = "irrelevant";
				} else {
					echo "></embed></object></div>";
				}

				// Close the containing cell and row.
				echo '</td></tr>';
			}

			// Close the feedback table.
			echo '</table>';

			// Close this teacher's feedback div.
			echo '</td></tr>';
		}
		
		if (!$gradingmode) {
			echo '<script type="text/javascript">
					var firstFeedback = document.getElementById("'.$firstFeedback.'");
					firstFeedback.style.display = "table-row";
					curselected_oldid = "'.$firstFeedback.'";
					curselected = firstFeedback;
					curselected.id = "curselected";

					var firstPic = document.getElementById("'.$firstPic.'");
					firstPic.className = "activePic";
					curpic_oldid = "'.$firstPic.'";
					curpic = firstPic;
					curpic.id = "curselectedpic";
					</script>';
		}

		echo '</table>';

		// Close the "feedbackarea" div.
		echo '</div>';

	}

	// If there are no feedbacks, just display a FeedbackPlayer revlet with the student file in it.
	if (! $gradingmode && ! $hasFeedbackFiles && $manattempt->type == LL_AUDIO) {
		echo '<div>'.get_string('yousubmitted', 'languagelesson').'</div>';
		echo '<div class="submissionTime">'.userdate($manattempt->timeseen).'</div>';

		// Lets just embed an mp3 player.
		$stufilepath = languagelesson_get_student_file_path($manattempt);
		$studentfile = $CFG->wwwroot . "/file.php" . $stufilepath;
		
		echo '<embed type="application/x-shockwave-flash" 
			  src="http://www.google.com/reader/ui/3523697345-audio-player.swf" 
			  flashvars="audioUrl='.$studentfile.'" 
			  width="400" 
			  height="27" 
			  quality="best">
			  </embed>';
	}

}


        
/**
 * Reorders pages in a given lesson starting from $startpageid and records new sort
 * order in the 'ordering' field of the page record in languagelesson_pages
 * @param int $lessonid
 */
function languagelesson_reorder_pages($lessonid) {
    global $DB;
    $startpage = $DB->get_record("languagelesson_pages", array('lessonid'=> $lessonid, 'prevpageid'=>0));
    $order = 0;
    $DB->set_field("languagelesson_pages", 'ordering', $order, array('id'=>$startpage->id));
    $order++;
    $nextpageid = $DB->get_field("languagelesson_pages", 'id', array('id'=>$startpage->nextpageid));

    for (; $nextpageid != 0; $order++) {
        $DB->set_field("languagelesson_pages", 'ordering', $order, array('id'=>$nextpageid));
        $nextpageid = (int)$DB->get_field("languagelesson_pages", 'nextpageid', array('id'=>$nextpageid));
    }
}

 /**
  * Fixes prevpageid and nextpageid based on ordering field
  * @param int $lessonid
  */
 function languagelesson_sort_by_ordering($lessonid) {
    global $DB;
    
    $pages = $DB->get_records('languagelesson_pages', array('lessonid'=>$lessonid));

    // Make sure they are in order by field ordering.
    $order = array();
    foreach ($pages as $key => $obj) {
            $order[$key] = $obj->ordering;
    }
    array_multisort($order, SORT_ASC, $pages);
    
    $pagecount = count($pages);
    $keys = array_keys($pages);
    
    $i = array_shift($keys);
    $last = array_pop($keys);
    
    $prev = $pages[$i];
    $prev->prevpageid = 0;
    $prev->nextpageid = $pages[$i += 1];
    $DB->update_record('languagelesson_pages', $prev);
    
    while ($i < $pagecount) {
        $thispage = $pages[$i];
        $thispage->prevpageid = $prev->id;
        if (!$pages[$i += 1]) {
            $thispage->nextpageid = 0;
            $DB->update_record('languagelesson_pages', $thispage);
            break;
        } else {
            $thispage->nextpageid = $i;
        }
        $DB->update_record($thispage);
        $prev = $thispage;
    }
    
 }

  
 
/**
 * @NEEDSDOC@
 **/ 
function languagelesson_sort_pages($pages) {
/* function to sort pages by lesson progression order; returns array of sorted pages w/o keys */
	// Store pages in an array, keyed to their id values.
	$pages_byid = array();
	foreach ($pages as $page) {
		$pages_byid[$page->id] = $page;
	}
	
	// Create array to hold pages in sorted order and populate the first item.
	$sorted_pages = array();
	foreach ($pages as $page) {
		// Find first page in lesson (the page with prevpageid value of 0)...
		if ($page->prevpageid == 0) {
			$sorted_pages[] = $page; //...and store it as the first item in $sorted_pages
			break;
		}
	}
	
	// Sort the rest of the pages; inchworm, storing value of the latest page added to $sorted_pages as $curpage,
    // and continue pulling pages from the $pages_byid array by the nextpageid value of $curpage until $curpage->nextpageid == 0,
    // at which point we've reached the end of the lesson.
	$curpage = $sorted_pages[0];
	while ($curpage->nextpageid != 0) {
		$curpage = $pages_byid[$curpage->nextpageid];
		$sorted_pages[] = $curpage;
	}

	return $sorted_pages;
}


/**
 * @NEEDSDOC@
 */
function languagelesson_get_most_recent_attempt_on($page, $user) {
	global $CFG, $DB;
	$query = "select *
	          from {languagelesson_attempts}
			  where userid=?
				and pageid=?
				and iscurrent=?";
	
	$result = $DB->get_record_sql($query, array($user, $page, '1'));
	
	if ($result) { return $result; }
	else { return null; }
	
}

/**
 * Calculates the maxscore for the page from answer scores
 * @param int $pageid
 * @return int
 */
function languagelesson_calc_page_maxscore($pageid) {
    global $DB;
    if (!$pageid) {
        error('The pageid is empty!');
        break;
    } else {
        $page = $DB->get_record('languagelesson_pages', array('id'=>$pageid));
        if ($page->qtype == LL_SHORTANSWER) {
            // Find the answer with the highest score and use that for $maxscore.
            $scores = $DB->get_fieldset_select('languagelesson_answers', 'score', 'pageid = ?', array($pageid));
            rsort($scores, SORT_NUMERIC);
            $maxscore = $scores[0];
        } else if ($page->qtype == LL_MULTICHOICE && $page->qoption == 0) {
            // Find the answer with the highest score and use that for $maxscore.
            if ($scores = $DB->get_fieldset_select('languagelesson_answers', 'score', 'pageid = ?', array($pageid))) {
                rsort($scores, SORT_NUMERIC);
                $maxscore = $scores[0];
            } else {
                // If there are no answer records, change it to a Description type question and set maxscore to 0.
                $page->qtype == 1;
                $DB->update_record('languagelesson_pages', $page);
                $maxscore = 0;
            }
        } else if ($page->qtype == LL_BRANCHTABLE) {
            $maxscore = (int)0;
        } else if ($page->qtype == LL_DESCRIPTION) {
            $maxscore = (int)0;
        } else if ($page->qtype == LL_ENDOFBRANCH) {
            $maxscore = (int)0;
        } else {
            $scores = array();
            $scores = $DB->get_fieldset_select("languagelesson_answers", 'score', 'pageid = ?', array($pageid));
            $maxscore = 0;
            foreach ($scores as $value) {
                $maxscore += $value;
            }
            
        }
        return $maxscore;
    }
}

/** Calculates the max grade for the lesson by adding up
 * the maxscore for each page in the lesson.
 * @param int $lessonid
 * @return int
 */
function languagelesson_calculate_lessongrade($lessonid) {
    global $DB;
    $pagescores = array();
    $pagescores = $DB->get_records_menu('languagelesson_pages', array('lessonid' => $lessonid), null, 'id,maxscore');
    $lessongrade = 0;
    foreach ($pagescores as $pagescore) {
        $lessongrade += $pagescore;
    }

    return $lessongrade;
}

/** Calculates current lesson score for a given student
 * @param lessonid
 * @param userid
 */
function languagelesson_calculate_user_lesson_grade($lessonid, $userid) {
    global $DB;
    $attemptscores = $DB->get_records('languagelesson_attempts', array('lessonid'=>$lessonid, 'userid'=>$userid), null, 'id,score');
    $lessonscore = 0;
    foreach ($attemptscores as $score) {
        $lessonscore += $score->score;
    }
    $DB->set_field('languagelesson_grades', 'grade', $lessonscore, array('lessonid'=>$lessonid, 'userid'=>$userid));
 
    return $lessonscore;
}

/**
 * Stores the SQL record of a student's grade on a lesson
 *
 * @param int $lessonid The ID of the lesson graded
 * @param int $userid The ID of the student graded
 * @param real $gradeval The grade the student received
 */
function languagelesson_save_grade($lessonid, $userid, $gradeval) {
    global $DB;
	// build the grade object
	$grade = new stdClass;
	$grade->lessonid = $lessonid;
	$grade->userid = $userid;
	$grade->grade = $gradeval;

	// And update the old grade record, if there is one; if not, insert the record.
	if ($oldgrade = $DB->get_record("languagelesson_grades", array("lessonid"=>$lessonid, "userid"=>$userid))) {
		// If the old grade was for a completed lesson attempt, update the completion time.
		if ($oldgrade->completed) { $grade->completed = time(); }
		$grade->id = $oldgrade->id;
		if (! $DB->update_record("languagelesson_grades", $grade)) {
			error("Navigation: grade not updated");
		}
	} else {
		if (! $DB->insert_record("languagelesson_grades", $grade)) {
			error("Navigation: grade not inserted");
		}
	}
}

//Functions for handling audio files.



/** gets the pertinent information for a submitted recording
 * in order to call send config info to flash applets
 * $lessonid (int) the lesson id for this attempt
 * $user (obj) the user record for the student in question
 * $feedback (array) array of related feedback files
 */

    function send_audio_config($lessonid, $user, $fileurl = NULL, $feedback = NULL) {
        global $CFG;
        
        $moodleData = array();
        $moodleData['userid'] = $user->id;
        
        $config = array();
        $config['lessonid'] = $lessonid;
        if ($fileurl) {
            $config['lessonaudio'] = $fileurl;
        } else {
            $config['lessonaudio'] = '';
        }

        $config['feedback'] = array();
        $config['moodleData'] = $moodleData;
        
        $config = json_encode($config);
        
        return $config;
    }
    
    function get_audio_file_location($data) { 
        global $DB;
        
        if (! $data) {
            $serverpath = '';
        } else {
            $contenthash = $DB->get_field('files', 'contenthash', array('id'=>$data->fileid));
            $serverpath = path_from_hash($contenthash);
        }
        
        return $serverpath;
    }
   
   /** parse the JSON data from Feedback Recorder
    * to prepare for upload
    * $jsondata (str) the string of JSON from the recorder
    * 
    */
   function languagelesson_upload_feedback($jsondata, $attemptid, $cmid) {
        global $USER, $DB;
        
        $filearray = json_decode($jsondata, true, 5);
        $mp3Data = $filearray['mp3Data'];
        $removed = $filearray['removed'];
        
        $attempt = $DB->get_record('languagelesson_attempts', array('id'=>$attemptid));

        // Create an array to store all of the new fb records created.
        $feedbackrecords = array();
        
        foreach ($removed as $filepath) {
            // Do something.
            $urlarray = explode('/', $filepath);
            $filename = array_pop($urlarray);
            $feedbackrecord = $DB->get_record_sql('SELECT * FROM {languagelesson_feedback}
                                        WHERE '.$DB->sql_compare_text('filename', 50) . '= ' . $DB->sql_compare_text('?', 50),
                                                     array($filename));

            $filerecord = $DB->get_record('files', array('id'=>$feedbackrecord->fileid));

            languagelesson_delete_submitted_file($filerecord);
            $DB->delete_records('languagelesson_feedback', array('id'=>$feedbackrecord->id));
        }
        
        foreach ($mp3Data as $filename => $content) {                      
            // Upload each feedback file and get the fileid.
            $fileid = upload_audio_file($attempt->userid, $cmid, $attempt->id, $filename, $content);  
            // Insert a new record in languagelesson_feedback for each fileid.
            $newfeedback = array();
            $newfeedback['lessonid'] = $attempt->lessonid;
            $newfeedback['pageid'] = $attempt->pageid;
            $newfeedback['userid'] = $attempt->userid;
            $newfeedback['attemptid'] = $attempt->id;
            $newfeedback['teacherid'] = $USER->id;
            $newfeedback['timeseen'] = time();
            $newfeedback['fileid'] = $fileid;
            $newfeedback['filename'] = $filename;
            $feedbackrecords[$filename] = $DB->insert_record('languagelesson_feedback', $newfeedback);

        }
        return $feedbackrecords;
        
   }
   
    function upload_audio_file($userid, $cmid, $itemid, $filename, $content) {
        global $CFG, $DB;
        
        $context = CONTEXT_MODULE::instance($cmid); 

        $fs = get_file_storage();
        $fileinfo = array( 
            'contextid' => $context->id,
            'component' => 'mod_languagelesson',
            'filearea' => 'submission',
            'userid' => $userid,
            'itemid' => $itemid,
            'filepath' => '/',
            'filename' => $filename,
            'mimetype' => 'audio/mp3');
        
        $filerecord = $fs->create_file_from_string($fileinfo, base64_decode(urldecode($content)));
        
        return $filerecord->get_id();
    }
    
    /**Deletes previously recorded file to keep file storage reasonable.
     * $filerecord (obj) the id of the file that needs to be deleted
     */
    
    function languagelesson_delete_submitted_file($filerecord) {
        $fs = get_file_storage();
        $file = $fs->get_file_instance($filerecord);
        
        if ($file->delete()) {
            return true;
        } else {
            print_error('Cannot delete previously submitted file');
        }

    }
    
    /** Gets a file previously uploaded as a submmission to return
     * to the Feedback Player and Recorder
     * $filerecord (obj) the filerecord object of the file
     */
    
    function languagelesson_get_audio_submission($filerecord) {
        global $CFG;
        // Get file.
        $fs = get_file_storage();
        $file = $fs->get_file_instance($filerecord);
        
        $filecontext = $file->get_contextid();
        $itemid = $file->get_itemid();
        $filename = $file->get_filename();
        
        $fileurl = $CFG->wwwroot .'/pluginfile.php/'.$filecontext . '/mod_languagelesson/submission/'. $itemid .'/'.$filename;
        
        $return = array();
        $return['filename'] = $filename;
        $return['fileurl'] = $fileurl;
        
        return $fileurl;
    
    }
    
    /** Builds a config file for the Feedback Player
     * 
     * $audio_array array a one element array where key is
     * the filename and element is the file data
     */
    
    function languagelesson_build_config_for_FBplayer($lessonid, $fileurl, $attemptid = NULL) {
        global $DB;
        
        // Get the feedback, if it exists.
        if ($fbfiles = $DB->get_records('languagelesson_feedback', array('attemptid'=>$attemptid))) {
            $feedbackconfig = '\"feedback\":[';
            $count = 0;
            foreach ($fbfiles as $record) {
                // Count only the records that refer to feedback files, rather than text feedback.
                if (!empty($record->fileid)) {
                    $count += 1;
                }
            }
            
            $i = 0;
            foreach ($fbfiles as $fbrecord) {
                if (!empty($fbrecord->fileid)) {
                    $filerecord = $DB->get_record('files', array('id'=> $fbrecord->fileid));
                    if ($i == $count - 1) {
                        // Do not include final comma because this is the last file.
                        $fbfileurl = languagelesson_get_audio_submission($filerecord);
                        $feedbackconfig .= '{\"audiofile\":\"' . $fbfileurl . '\",';
                        $feedbackconfig .= '\"location\":\"' . $fbrecord->location . '\"}';
                    } else {
                        // Make sure to end with a delimiting comma if there will be more.
                        $fbfileurl = languagelesson_get_audio_submission($filerecord);
                        $feedbackconfig .= '{\"audiofile\":\"' . $fbfileurl . '\",';
                        $feedbackconfig .= '\"location\":\"' . $fbrecord->location . '\"},';
                    }
                    $i++;
                }
                
            }
            $feedbackconfig .= '],';
        } else {
            $feedbackconfig = '\"feedback\":[],';
        }
        
        
        $config = '{\"lessonid\":\"'.$lessonid.'\",';
        $config .= '\"lessonaudio\":\"'.$fileurl.'\",';
        $config .= $feedbackconfig;
        $config .= '\"serverpath\":\"\"}';

        return $config;
        
    }
    
    /* 
 * function to check if an answer has been given by the userid for each
 * question in the lessonid
 *
 * This is done by comparing the number of question pages stored for the input
 * lesson with the number of record attempts stored for the input lesson from
 * the input user on the relevant run-through.
 *
 * @param lessonid => ID value for the lesson being examined
 * @param userid => ID value for the user being examined
 */
function languagelesson_is_lesson_complete($lessonid, $userid) {
	global $CFG, $DB, $LL_QUESTION_TYPE;

  // Pull the list of all question types as a string of format [type],[type],[type],.
	$qtypeslist = implode(',', array_keys($LL_QUESTION_TYPE));

	
// Find the number of question pages 
	
  // This alias must be the same in both queries, so establish it here.
	$tmp_name = "page";
  // A sub-query used to ignore pages that have no answers stored for them
  // (instruction pages).
	$do_answers_exist = "select *
						 from {$CFG->prefix}languagelesson_answers ans
						 where ans.pageid = $tmp_name.id";
  // Query to pull only pages of stored languagelesson question types, belonging
  // to the current lesson, and having answer records stored.
	$get_only_question_pages = "select *
								from {$CFG->prefix}languagelesson_pages $tmp_name
								where qtype in ($qtypeslist)
									  and $tmp_name.lessonid=$lessonid
									  and exists ($do_answers_exist)";
	$qpages = $DB->get_records_sql($get_only_question_pages);
	$numqpages = count($qpages);
	
	
// Find the number of questions attempted.
	
	// See how many questions have been attempted.
	$numattempts = languagelesson_count_most_recent_attempts($lessonid, $userid);

	// If the number of question pages matches the number of attempted questions, it's complete.
	if ($numqpages == $numattempts) { return true; }
	else { return false; }
}
    

/**
 * Count the number of questions for which attempts have been submitted
 * for input user on input lesson
 *
 * @param int $lesson The ID of the LanguageLesson to check attempts on
 * @param int $user The ID of the user whose attempts to check
 * @return int $count The number of questions with saved attempts
 **/
function languagelesson_count_most_recent_attempts($lesson, $user) {
	global $CFG, $DB;
	
	$querytext = 	"select count(*)
					from {$CFG->prefix}languagelesson_pages p,
						 {$CFG->prefix}languagelesson_attempts a
					where a.pageid = p.id
					  and a.lessonid = $lesson
					  and a.userid = $user
					  and a.iscurrent = 1";
	$result = $DB->count_records_sql($querytext);
	
	return $result;
}

/**
 * Obtains the automatic completion state for this languagelesson based on any conditions
 * in languagelesson settings.
 *
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not, $type if conditions not set.
 */
function languagelesson_get_completion_state($course,$cm,$userid,$type) {
    global $CFG,$DB;

    // Get languagelesson details
    if(!($lesson=$DB->get_record('languagelesson',array('id'=>$cm->instance)))) {
        throw new Exception("Can't find Language Lesson {$cm->instance}");
    }

    // If completion option is enabled, evaluate it and return true/false 
    if($lesson->completionsubmit) {
        $usersubmissions = count($DB->get_records('languagelesson_attempts', array('userid'=>$userid, 'lessonid'=>$lesson->id)));
        $pagecount = $DB->get_field('languagelesson', 'pagecount', array('id'=>$lesson->id));
        if ($usersubmissions < $pagecount) {
            return false;
        } else {
            return true;
        }
    } else {
        // Completion option is not enabled so just return $type
        return $type;
    }
}