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
 * Standard library of functions and constants for lesson
 *
 * @package    mod
 * @subpackage languagelesson
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

defined('MOODLE_INTERNAL') || die();

/* Do not include any libraries here! */

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @global object
 * @global object
 * @param object $lesson Lesson post data from the form
 * @return int
 **/
function languagelesson_add_instance($data, $mform) {
    global $DB;

    $cmid = $data->coursemodule;

    languagelesson_process_pre_save($data);

    unset($data->mediafile);
    $lessonid = $DB->insert_record("languagelesson", $data);
    $data->id = $lessonid;

    $context = context_module::instance($cmid);
    $lesson = $DB->get_record('languagelesson', array('id'=>$lessonid), '*', MUST_EXIST);

    if ($filename = $mform->get_new_filename('mediafilepicker')) {
        if ($file = $mform->save_stored_file('mediafilepicker', $context->id, 'mod_languagelesson', 'mediafile', 0, '/', $filename)) {
            $DB->set_field('languagelesson', 'mediafile', '/'.$file->get_filename(), array('id'=>$lesson->id));
        }
    }

    languagelesson_process_post_save($data);

    languagelesson_grade_item_update($lesson);

    return $lesson->id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @global object
 * @param object $lesson Lesson post data from the form
 * @return boolean
 **/
function languagelesson_update_instance($data, $mform) {
    global $DB;

    $data->id = $data->instance;
    $cmid = $data->coursemodule;

    languagelesson_process_pre_save($data);

    unset($data->mediafile);
    $DB->update_record("languagelesson", $data);

    $context = context_module::instance($cmid);
    if ($filename = $mform->get_new_filename('mediafilepicker')) {
        if ($file = $mform->save_stored_file('mediafilepicker', $context->id, 'mod_languagelesson', 'mediafile', 0, '/', $filename, true)) {
            $DB->set_field('languagelesson', 'mediafile', '/'.$file->get_filename(), array('id'=>$data->id));
        } else {
            $DB->set_field('languagelesson', 'mediafile', '', array('id'=>$data->id));
        }
    } else {
        $DB->set_field('languagelesson', 'mediafile', '', array('id'=>$data->id));
    }
    
    $completionsubmit = $mform->completionsubmit;

    languagelesson_process_post_save($data);

    $data->grade = languagelesson_calculate_lessongrade($data->id);

    // Update grade item definition.
    languagelesson_grade_item_update($data);

    // Update grades - TODO: do it only when grading style changes.
    languagelesson_update_grades($data, 0, false);

    return true;
}


/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @global object
 * @param int $id
 * @return bool
 */
function languagelesson_delete_instance($id) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/mod/languagelesson/locallib.php');

    $lesson = $DB->get_record("languagelesson", array("id"=>$id), '*', MUST_EXIST);
    $lesson = new languagelesson($lesson);
    return $lesson->delete();
}

/**
 * Given a course object, this function will clean up anything that
 * would be leftover after all the instances were deleted
 *
 * @global object
 * @param object $course an object representing the course that is being deleted
 * @param boolean $feedback to specify if the process must output a summary of its work
 * @return boolean
 */
function languagelesson_delete_course($course, $feedback=true) {
    return true;
}

/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @global object
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $lesson
 * @return object
 */
function languagelesson_user_outline($course, $user, $mod, $lesson) {
    global $CFG;

    require_once("$CFG->libdir/gradelib.php");
    $grades = grade_get_grades($course->id, 'mod', 'languagelesson', $lesson->id, $user->id);

    $return = new stdClass();
    if (empty($grades->items[0]->grades)) {
        $return->info = get_string("no")." ".get_string("attempts", "languagelesson");
    } else {
        $grade = reset($grades->items[0]->grades);
        $return->info = get_string("grade") . ': ' . $grade->str_long_grade;

        // Datesubmitted == time created. dategraded == time modified or time overridden
        // if grade was last modified by the user themselves use date graded. Otherwise use date submitted.
        // TODO: move this copied & pasted code somewhere in the grades API. See MDL-26704.
        if ($grade->usermodified == $user->id || empty($grade->datesubmitted)) {
            $return->time = $grade->dategraded;
        } else {
            $return->time = $grade->datesubmitted;
        }
    }
    return $return;
}

/**
 * Print a detailed representation of what a  user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @global object
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $lesson
 * @return bool
 */
function languagelesson_user_complete($course, $user, $mod, $lesson) {
    global $DB, $OUTPUT, $CFG;

    require_once("$CFG->libdir/gradelib.php");

    $grades = grade_get_grades($course->id, 'mod', 'languagelesson', $lesson->id, $user->id);
    if (!empty($grades->items[0]->grades)) {
        $grade = reset($grades->items[0]->grades);
        echo $OUTPUT->container(get_string('grade').': '.$grade->str_long_grade);
        if ($grade->str_feedback) {
            echo $OUTPUT->container(get_string('feedback').': '.$grade->str_feedback);
        }
    }

    $params = array ("lessonid" => $lesson->id, "userid" => $user->id);
    if ($attempts = $DB->get_records_select("languagelesson_attempts", "lessonid = :lessonid AND userid = :userid", $params,
                "retry, timeseen")) {
        echo $OUTPUT->box_start();
        $table = new html_table();
        $table->head = array (get_string("attempt", "languagelesson"),  get_string("numberofpagesviewed", "languagelesson"),
            get_string("numberofcorrectanswers", "languagelesson"), get_string("time"));
        $table->width = "100%";
        $table->align = array ("center", "center", "center", "center");
        $table->size = array ("*", "*", "*", "*");
        $table->cellpadding = 2;
        $table->cellspacing = 0;

        $retry = 0;
        $npages = 0;
        $ncorrect = 0;

        foreach ($attempts as $attempt) {
            if ($attempt->retry == $retry) {
                $npages++;
                if ($attempt->correct) {
                    $ncorrect++;
                }
                $timeseen = $attempt->timeseen;
            } else {
                $table->data[] = array($retry + 1, $npages, $ncorrect, userdate($timeseen));
                $retry++;
                $npages = 1;
                if ($attempt->correct) {
                    $ncorrect = 1;
                } else {
                    $ncorrect = 0;
                }
            }
        }
        if ($npages) {
                $table->data[] = array($retry + 1, $npages, $ncorrect, userdate($timeseen));
        }
        echo html_writer::table($table);
        echo $OUTPUT->box_end();
    }

    return true;
}

/**
 * Prints lesson summaries on MyMoodle Page
 *
 * Prints lesson name, due date and attempt information on
 * lessons that have a deadline that has not already passed
 * and it is available for taking.
 *
 * @global object
 * @global stdClass
 * @global object
 * @uses CONTEXT_MODULE
 * @param array $courses An array of course objects to get lesson instances from
 * @param array $htmlarray Store overview output array( course ID => 'lesson' => HTML output )
 * @return void
 */
function languagelesson_print_overview($courses, &$htmlarray) {
    global $USER, $CFG, $DB, $OUTPUT;

    if (!$lessons = get_all_instances_in_courses('languagelesson', $courses)) {
        return;
    }

    // Get Necessary Strings.
    $strlesson       = get_string('modulename', 'languagelesson');
    $strnotattempted = get_string('nolessonattempts', 'languagelesson');
    $strattempted    = get_string('lessonattempted', 'languagelesson');

    $now = time();
    foreach ($lessons as $lesson) {
        if ($lesson->deadline != 0                                         // The lesson has a deadline.
            and $lesson->deadline >= $now                                  // And it is before the deadline has been met.
            and ($lesson->available == 0 or $lesson->available <= $now)) { // And the lesson is available.

            // Lesson name.
            if (!$lesson->visible) {
                $class = ' class="dimmed"';
            } else {
                $class = '';
            }
            $str = $OUTPUT->box("$strlesson: <a$class href=\"$CFG->wwwroot/mod/languagelesson/view.php?id=$lesson->coursemodule\">".
                             format_string($lesson->name).'</a>', 'name');

            // Deadline.
            $str .= $OUTPUT->box(get_string('lessoncloseson', 'languagelesson', userdate($lesson->deadline)), 'info');

            // Attempt information.
            if (has_capability('mod/languagelesson:manage', context_module::instance($lesson->coursemodule))) {
                // Number of user attempts.
                $attempts = $DB->count_records('languagelesson_attempts', array('lessonid'=>$lesson->id));
                $str     .= $OUTPUT->box(get_string('xattempts', 'languagelesson', $attempts), 'info');
            } else {
                // Determine if the user has attempted the lesson or not.
                if ($DB->count_records('languagelesson_attempts', array('lessonid'=>$lesson->id, 'userid'=>$USER->id))) {
                    $str .= $OUTPUT->box($strattempted, 'info');
                } else {
                    $str .= $OUTPUT->box($strnotattempted, 'info');
                }
            }
            $str = $OUTPUT->box($str, 'lesson overview');

            if (empty($htmlarray[$lesson->course]['lesson'])) {
                $htmlarray[$lesson->course]['lesson'] = $str;
            } else {
                $htmlarray[$lesson->course]['lesson'] .= $str;
            }
        }
    }
}

/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 * @global stdClass
 * @return bool true
 */
function languagelesson_cron () {
    global $CFG, $DB;
    // Check that all Language Lessons have a pagecount greater than zero, if not count pages
    $lessons = $DB->get_records('languagelesson', array('pagecount'=> '0'), null, 'id');
    foreach ($lessons as $lesson) {
        languagelesson_update_lesson_pagecount($lesson);
    }
    return true;
}

/**
 * Return grade for given user or all users.
 *
 * @global stdClass
 * @global object
 * @param int $lessonid id of lesson
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 */
function languagelesson_get_user_grades($lesson, $userid=0) {
    global $CFG, $DB;

    $params = array("lessonid" => $lesson->id, "lessonid2" => $lesson->id);

    if (isset($userid)) {
        $params["userid"] = $userid;
        $params["userid2"] = $userid;
        $user = "AND u.id = :userid";
        $fuser = "AND uu.id = :userid2";
    } else {
        $user="";
        $fuser="";
    }

    if ($lesson->retake) {
        if ($lesson->usemaxgrade) {
            $sql = "SELECT u.id, u.id AS userid, MAX(g.grade) AS rawgrade
                      FROM {user} u, {languagelesson_grades} g
                     WHERE u.id = g.userid AND g.lessonid = :lessonid
                           $user
                  GROUP BY u.id";
        } else {
            $sql = "SELECT u.id, u.id AS userid, AVG(g.grade) AS rawgrade
                      FROM {user} u, {languagelesson_grades} g
                     WHERE u.id = g.userid AND g.lessonid = :lessonid
                           $user
                  GROUP BY u.id";
        }
        unset($params['lessonid2']);
        unset($params['userid2']);
    } else {
        // Use only first attempts (with lowest id in languagelesson_grades table).
        $firstonly = "SELECT uu.id AS userid, MIN(gg.id) AS firstcompleted
                        FROM {user} uu, {languagelesson_grades} gg
                       WHERE uu.id = gg.userid AND gg.lessonid = :lessonid2
                             $fuser
                       GROUP BY uu.id";

        $sql = "SELECT u.id, u.id AS userid, g.grade AS rawgrade
                  FROM {user} u, {languagelesson_grades} g, ($firstonly) f
                 WHERE u.id = g.userid AND g.lessonid = :lessonid
                       AND g.id = f.firstcompleted AND g.userid=f.userid
                       $user";
    }

    return $DB->get_records_sql($sql, $params);
}

/**
 * Update grades in central gradebook
 *
 * @category grade
 * @param object $lesson
 * @param int $userid specific user only, 0 means all
 * @param bool $nullifnone
 */
function languagelesson_update_grades($lesson, $userid=0, $nullifnone=true) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    if ($lesson->grade == 0) {
        languagelesson_grade_item_update($lesson);

    } else if ($grades = languagelesson_get_user_grades($lesson, $userid)) {
        languagelesson_grade_item_update($lesson, $grades);

    } else if ($userid and $nullifnone) {
        $grade = new stdClass();
        $grade->userid   = $userid;
        $grade->rawgrade = null;
        languagelesson_grade_item_update($lesson, $grade);

    } else {
        languagelesson_grade_item_update($lesson);
    }
}

/**
 * Update all grades in gradebook.
 *
 * @global object
 */
function languagelesson_upgrade_grades() {
    global $DB;

    $sql = "SELECT COUNT('x')
              FROM {languagelesson} l, {course_modules} cm, {modules} m
             WHERE m.name='languagelesson' AND m.id=cm.module AND cm.instance=l.id";
    $count = $DB->count_records_sql($sql);

    $sql = "SELECT l.*, cm.idnumber AS cmidnumber, l.course AS courseid
              FROM {languagelesson} l, {course_modules} cm, {modules} m
             WHERE m.name='languagelesson' AND m.id=cm.module AND cm.instance=l.id";
    $rs = $DB->get_recordset_sql($sql);
    if ($rs->valid()) {
        $pbar = new progress_bar('lessonupgradegrades', 500, true);
        $i=0;
        foreach ($rs as $lesson) {
            $i++;
            upgrade_set_timeout(60*5); // Set up timeout, may also abort execution.
            languagelesson_update_grades($lesson, 0, false);
            $pbar->update($i, $count, "Updating Lesson grades ($i/$count).");
        }
    }
    $rs->close();
}

/**
 * Create grade item for given lesson
 *
 * @category grade
 * @uses GRADE_TYPE_VALUE
 * @uses GRADE_TYPE_NONE
 * @param object $lesson object with extra cmidnumber
 * @param array|object $grades optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function languagelesson_grade_item_update($lesson, $grades=null) {
    global $CFG;
    if (!function_exists('grade_update')) { // Workaround for buggy PHP versions.
        require_once($CFG->libdir.'/gradelib.php');
    }

    if (array_key_exists('cmidnumber', $lesson)) { // It may not be always present.
        $params = array('itemname'=>$lesson->name, 'idnumber'=>$lesson->cmidnumber);
    } else {
        $params = array('itemname'=>$lesson->name);
    }

    if ($lesson->type == 0) {
        $params['gradetype'] = GRADE_TYPE_NONE;
    } else {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax'] = $lesson->grade;
        $params['gradmin'] = 0;
    }
    if ($grades  === 'reset') {
        $params['reset'] = true;
        $grades = null;
    } else if (!empty($grades)) {
        // Need to calculate raw grade (Note: $grades has many forms).
        if (is_object($grades)) {
            $grades = array($grades->userid => $grades);
        } else if (array_key_exists('userid', $grades)) {
            $grades = array($grades['userid'] => $grades);
        }
        foreach ($grades as $key => $grade) {
            if (!is_array($grade)) {
                $grades[$key] = $grade = (array) $grade;
            }
            // Check raw grade isnt null otherwise we erroneously insert a grade of 0.
            if ($grade['rawgrade'] == null) {
                // Setting rawgrade to null just in case user is deleting a grade.
                $grades[$key]['rawgrade'] = null;
                $grades[$key]['finalgrade'] = null;
            }
        }
    }

    return grade_update('mod/languagelesson', $lesson->course, 'mod', 'languagelesson', $lesson->id, 0, $grades, $params);
}

/**
 * Delete grade item for given lesson
 *
 * @category grade
 * @param object $lesson object
 * @return object lesson
 */
function languagelesson_grade_item_delete($lesson) {
    global $CFG;

}

/**
 * @return array
 */
function languagelesson_get_view_actions() {
    return array('view', 'view all');
}

/**
 * @return array
 */
function languagelesson_get_post_actions() {
    return array('end', 'start');
}

/**
 * Runs any processes that must run before
 * a lesson insert/update
 *
 * @global object
 * @param object $lesson Lesson form data
 * @return void
 **/
function languagelesson_process_pre_save(&$lesson) {
    global $DB;

    $lesson->timemodified = time();

    if (empty($lesson->timed)) {
        $lesson->timed = 0;
    }
    if (empty($lesson->timespent) or !is_numeric($lesson->timespent) or $lesson->timespent < 0) {
        $lesson->timespent = 0;
    }
    if (!isset($lesson->completed)) {
        $lesson->completed = 0;
    }
    if (empty($lesson->gradebetterthan) or !is_numeric($lesson->gradebetterthan) or $lesson->gradebetterthan < 0) {
        $lesson->gradebetterthan = 0;
    } else if ($lesson->gradebetterthan > 100) {
        $lesson->gradebetterthan = 100;
    }

    if (empty($lesson->width)) {
        $lesson->width = 640;
    }
    if (empty($lesson->height)) {
        $lesson->height = 480;
    }
    if (empty($lesson->bgcolor)) {
        $lesson->bgcolor = '#FFFFFF';
    }

    // Conditions for dependency.
    $conditions = new stdClass;
    $conditions->timespent = $lesson->timespent;
    $conditions->completed = $lesson->completed;
    $conditions->gradebetterthan = $lesson->gradebetterthan;
    $lesson->conditions = serialize($conditions);
    unset($lesson->timespent);
    unset($lesson->completed);
    unset($lesson->gradebetterthan);

    if (empty($lesson->password)) {
        unset($lesson->password);
    }
}

/**
 * Runs any processes that must be run
 * after a lesson insert/update
 *
 * @global object
 * @param object $lesson Lesson form data
 * @return void
 **/
function languagelesson_process_post_save(&$lesson) {
    global $DB, $CFG;
    require_once($CFG->dirroot.'/calendar/lib.php');
    require_once($CFG->dirroot . '/mod/languagelesson/locallib.php');

    if ($events = $DB->get_records('event', array('modulename'=>'languagelesson', 'instance'=>$lesson->id))) {
        foreach ($events as $event) {
            $event = calendar_event::load($event->id);
            $event->delete();
        }
    }

    $event = new stdClass;
    $event->description = $lesson->name;
    $event->courseid    = $lesson->course;
    $event->groupid     = 0;
    $event->userid      = 0;
    $event->modulename  = 'languagelesson';
    $event->instance    = $lesson->id;
    $event->eventtype   = 'open';
    $event->timestart   = $lesson->available;

    $event->visible     = instance_is_visible('languagelesson', $lesson);

    $event->timeduration = ($lesson->deadline - $lesson->available);

    if ($lesson->deadline and $lesson->available and $event->timeduration <= LL_MAX_EVENT_LENGTH) {
        // Single event for the whole lesson.
        $event->name = $lesson->name;
        calendar_event::create(clone($event));
    } else {
        // Separate start and end events.
        $event->timeduration  = 0;
        if ($lesson->available) {
            $event->name = $lesson->name.' ('.get_string('lessonopens', 'languagelesson').')';
            calendar_event::create(clone($event));
        }

        if ($lesson->deadline) {
            $event->name      = $lesson->name.' ('.get_string('lessoncloses', 'languagelesson').')';
            $event->timestart = $lesson->deadline;
            $event->eventtype = 'close';
            calendar_event::create(clone($event));
        }
    }
    
     // Count pages that require answers to ensure $lesson->pagecount is set, used for completion tracking on submit
    languagelesson_update_lesson_pagecount($lesson);
}


/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the lesson.
 *
 * @param $mform form passed by reference
 */
function languagelesson_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'lessonheader', get_string('modulenameplural', 'languagelesson'));
    $mform->addElement('advcheckbox', 'reset_lesson', get_string('deleteallattempts', 'lesson'));
}

/**
 * Course reset form defaults.
 * @param object $course
 * @return array
 */
function languagelesson_reset_course_form_defaults($course) {
    return array('reset_lesson'=>1);
}

/**
 * Removes all grades from gradebook
 *
 * @global stdClass
 * @global object
 * @param int $courseid
 * @param string optional type
 */
function languagelesson_reset_gradebook($courseid, $type='') {
    global $CFG, $DB;

    $sql = "SELECT l.*, cm.idnumber as cmidnumber, l.course as courseid
              FROM {languagelesson} l, {course_modules} cm, {modules} m
             WHERE m.name='languagelesson' AND m.id=cm.module AND cm.instance=l.id AND l.course=:course";
    $params = array ("course" => $courseid);
    if ($lessons = $DB->get_records_sql($sql, $params)) {
        foreach ($lessons as $lesson) {
            languagelesson_grade_item_update($lesson, 'reset');
        }
    }
}

/**
 * Actual implementation of the reset course functionality, delete all the
 * lesson attempts for course $data->courseid.
 *
 * @global stdClass
 * @global object
 * @param object $data the data submitted from the reset course.
 * @return array status array
 */
function languagelesson_reset_userdata($data) {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/mod/languagelesson/locallib.php');

    $componentstr = get_string('modulenameplural', 'languagelesson');
    $status = array();

    if (!empty($data->reset_lesson)) {
        $lessonssql = "SELECT l.id
                         FROM {languagelesson} l
                        WHERE l.course=:course";

        $params = array ("course" => $data->courseid);

        // Remove all student audio submissions.
        $allattempts = $DB->get_records_select('languagelesson_attempts', "lessonid IN ($lessonssql)", $params);
        foreach ($allattempts as $thisattempt) {
            $fileid = $thisattempt->fileid;
            if ($fileid != null) {
                $filerecord = $DB->get_record('files', array('id'=>$fileid));
                languagelesson_delete_submitted_file($filerecord);
            }
            unset($fileid);
        }

        // Remove all audio feedback files.
        $allfeedback = $DB->get_records_select('languagelesson_feedback', "lessonid IN ($lessonssql)", $params);
        foreach ($allfeedback as $thisfeedback) {
            if ($fileid = $thisattempt->fileid) {
                $filerecord = $DB->get_record('files', array('id'=>$fileid));
                languagelesson_delete_submitted_file($filerecord);
            }
        }

        $DB->delete_records_select('languagelesson_timer', "lessonid IN ($lessonssql)", $params);
        $DB->delete_records_select('languagelesson_high_scores', "lessonid IN ($lessonssql)", $params);
        $DB->delete_records_select('languagelesson_grades', "lessonid IN ($lessonssql)", $params);
        $DB->delete_records_select('languagelesson_attempts', "lessonid IN ($lessonssql)", $params);

        // Remove all grades from gradebook.
        if (empty($data->reset_gradebook_grades)) {
            languagelesson_reset_gradebook($data->courseid);
        }

        $status[] = array('component'=>$componentstr, 'item'=>get_string('deleteallattempts', 'languagelesson'), 'error'=>false);
    }

    // Updating dates - shift may be negative too.
    if ($data->timeshift) {
        shift_course_mod_dates('languagelesson', array('available', 'deadline'), $data->timeshift, $data->courseid);
        $status[] = array('component'=>$componentstr, 'item'=>get_string('datechanged'), 'error'=>false);
    }

    return $status;
}

/**
 * Returns all other caps used in module
 * @return array
 */
function languagelesson_get_extra_capabilities() {
    return array('moodle/site:accessallgroups');
}

/**
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_GROUPMEMBERSONLY
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know
 */
function languagelesson_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:
            return false;
        case FEATURE_GROUPINGS:
            return false;
        case FEATURE_GROUPMEMBERSONLY:
            return true;
        case FEATURE_MOD_INTRO:
            return false;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        default:
            return null;
    }
}

/**
 * This function extends the settings navigation block for the site.
 *
 * It is safe to rely on PAGE here as we will only ever be within the module
 * context when this is called
 *
 * @param settings_navigation $settings
 * @param navigation_node $lessonnode
 */
function languagelesson_extend_settings_navigation($settings, $lessonnode) {
    global $PAGE, $DB;

    $canedit = has_capability('mod/languagelesson:edit', $PAGE->cm->context);

    $url = new moodle_url('/mod/languagelesson/view.php', array('id'=>$PAGE->cm->id));
    $lessonnode->add(get_string('preview', 'languagelesson'), $url);

    if ($canedit) {
        $url = new moodle_url('/mod/languagelesson/edit.php', array('id'=>$PAGE->cm->id));
        $lessonnode->add(get_string('edit', 'languagelesson'), $url);
    }

    if (has_capability('mod/languagelesson:manage', $PAGE->cm->context)) {
        $reportsnode = $lessonnode->add(get_string('reports', 'languagelesson'));
        $url = new moodle_url('/mod/languagelesson/report.php', array('id'=>$PAGE->cm->id, 'action'=>'reportoverview'));
        $reportsnode->add(get_string('overview', 'languagelesson'), $url);
        $url = new moodle_url('/mod/languagelesson/report.php', array('id'=>$PAGE->cm->id, 'action'=>'reportdetail'));
        $reportsnode->add(get_string('detailedstats', 'languagelesson'), $url);
    }

    if ($canedit) {
        $url = new moodle_url('/mod/languagelesson/essay.php', array('id'=>$PAGE->cm->id));
        $lessonnode->add(get_string('manualgrading', 'languagelesson'), $url);
    }

    if ($PAGE->activityrecord->highscores) {
        $url = new moodle_url('/mod/languagelesson/highscores.php', array('id'=>$PAGE->cm->id));
        $lessonnode->add(get_string('highscores', 'languagelesson'), $url);
    }
}

/**
 * Get list of available import or export formats
 *
 * Copied and modified from lib/questionlib.php
 *
 * @param string $type 'import' if import list, otherwise export list assumed
 * @return array sorted list of import/export formats available
 */
function languagelesson_get_import_export_formats($type) {
    global $CFG;
    $fileformats = get_plugin_list("qformat");
    $fileformatnames['giftplus'] = get_string('pluginname', 'qformat_giftplus');
    return $fileformatnames;
}

/**
 * Serves the lesson attachments. Implements needed access control ;-)
 *
 * @package mod_languagelesson
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - justsend the file
 */
function languagelesson_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $CFG, $DB;

    /*
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }*/

    $fileareas = languagelesson_get_file_areas();

    if (!array_key_exists($filearea, $fileareas)) {
        return false;
    }

    if (!$lesson = $DB->get_record('languagelesson', array('id'=>$cm->instance))) {
        return false;
    }

    require_course_login($course, true, $cm);

    if ($filearea === 'page_contents') {
        $pageid = (int)array_shift($args);
        if (!$page = $DB->get_record('languagelesson_pages', array('id'=>$pageid))) {
            return false;
        }
        $fullpath = "/$context->id/mod_languagelesson/$filearea/$pageid/".implode('/', $args);

    } else if ($filearea === 'mediafile') {
        array_shift($args); // Ignore itemid - caching only.
        $fullpath = "/$context->id/mod_languagelesson/$filearea/0/".implode('/', $args);

    } else if ($filearea === 'submission') {
        $fullpath = "/$context->id/mod_languagelesson/$filearea/".implode('/', $args);

    } else {
        return false;
    }

    $fs = get_file_storage();
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }

    // Finally send the file.
    send_stored_file($file, 0, 0, $forcedownload, $options); // Download MUST be forced - security!
}

/**
 * Returns an array of file areas
 *
 * @package  mod_languagelesson
 * @category files
 * @todo MDL-31048 localize
 * @return array a list of available file areas
 */
function languagelesson_get_file_areas() {
    $areas = array();
    $areas['page_contents'] = 'Page contents'; // TODO: localize!!!!
    $areas['mediafile'] = 'Media file'; // TODO: localize!!!!
    $areas['submission'] = 'Submission';

    return $areas;
}

/**
 * Returns a file_info_stored object for the file being requested here
 *
 * @package  mod_languagelesson
 * @category files
 * @global stdClass $CFG
 * @param file_browse $browser file browser instance
 * @param array $areas file areas
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param int $itemid item ID
 * @param string $filepath file path
 * @param string $filename file name
 * @return file_info_stored
 */
function languagelesson_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    global $CFG;
    if (has_capability('moodle/course:managefiles', $context)) {
        // No peaking here for students!!
        return null;
    }

    $fs = get_file_storage();
    $filepath = is_null($filepath) ? '/' : $filepath;
    $filename = is_null($filename) ? '.' : $filename;
    $urlbase = $CFG->wwwroot.'/pluginfile.php';
    if (!$storedfile = $fs->get_file($context->id, 'mod_languagelesson', $filearea, $itemid, $filepath, $filename)) {
        return null;
    }
    return new file_info_stored($browser, $context, $storedfile, $urlbase, $filearea, $itemid, true, true, false);
}


/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function languagelesson_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $modulepagetype = array(
        'mod-lesson-*'=>get_string('page-mod-lesson-x', 'languagelesson'),
        'mod-lesson-view'=>get_string('page-mod-lesson-view', 'languagelesson'),
        'mod-lesson-edit'=>get_string('page-mod-lesson-edit', 'languagelesson'));
    return $modulepagetype;
}

/**
 * Count all pages in lesson that requires student submission
 * Used for judging when student completes lesson by answering all answer-able pages
 * @param string $lesson
 */
function languagelesson_update_lesson_pagecount($lesson) {
    global $DB;
    // Count pages that require answers to ensure $lesson->pagecount is set, used for completion tracking on submit
    $pagecount = 0;
    $rs = $DB->get_recordset('languagelesson_pages', array('lessonid'=>$lesson->id)); {
        foreach ($rs as $record) {
            switch ($record->qtype) {
                case '1':
                case '20':
                case '21':
                case '30':
                case '31':
                    break;
                case '2':
                    // if it's a MC page with no answers, don't count it
                    if (count($DB->get_records('languagelesson_answers', array('pageid'=>$record->id)))>0) {
                        $pagecount += 1;
                    }
                    break;
                default:
                    $pagecount += 1;
            }
        }
    }
    $rs->close();
    
    // Set pagecount in languagelesson table
    $lesson->pagecount = $pagecount;
    $DB->update_record('languagelesson', $lesson);
    return true;
}