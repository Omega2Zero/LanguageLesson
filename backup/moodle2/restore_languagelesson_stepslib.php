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
 * @package moodlecore
 * @subpackage backup-moodle2
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the restore steps that will be used by the restore_languagelesson_activity_task
 */

/**
 * Structure step to restore one languagelesson activity
 */
class restore_languagelesson_activity_structure_step extends restore_activity_structure_step {
    // Store the answers as they're received but only process them at the
    // End of the languagelesson.
    protected $answers = array();

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('languagelesson', '/activity/languagelesson');
        $paths[] = new restore_path_element('languagelesson_page', '/activity/languagelesson/pages/page');
        $paths[] = new restore_path_element('languagelesson_answer', '/activity/languagelesson/pages/page/answers/answer');
        $paths[] = new restore_path_element('languagelesson_branchinfo',
                        '/activity/languagelesson/pages/page/branchinfos/branchinfo');
        if ($userinfo) {
            $paths[] = new restore_path_element('languagelesson_attempt',
                        '/activity/languagelesson/pages/page/answers/answer/attempts/attempt');
            $paths[] = new restore_path_element('languagelesson_grade', '/activity/languagelesson/grades/grade');
            $paths[] = new restore_path_element('languagelesson_branch', '/activity/languagelesson/pages/page/branches/branch');
            $paths[] = new restore_path_element('languagelesson_highscore', '/activity/languagelesson/highscores/highscore');
            $paths[] = new restore_path_element('languagelesson_timer', '/activity/languagelesson/timers/timer');
            $paths[] = new restore_path_element('languagelesson_feedback', '/activity/languagelesson/feedbacks/feedback');
            $paths[] = new restore_path_element('languagelesson_seenbranch', '/activity/languagelesson/seenbranches/seenbranch');
            $paths[] = new restore_path_element('languagelesson_manattempt', '/activity/languagelesson/manattempts/manattempt');
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    protected function process_languagelesson($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->available = $this->apply_date_offset($data->available);
        $data->deadline = $this->apply_date_offset($data->deadline);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // Languagelesson->highscores can come both in data->highscores and
        // data->showhighscores, handle both.
        if (isset($data->showhighscores)) {
            $data->highscores = $data->showhighscores;
            unset($data->showhighscores);
        }

        // Insert the languagelesson record.
        $newitemid = $DB->insert_record('languagelesson', $data);
        // Immediately after inserting "activity" record, call this.
        $this->apply_activity_instance($newitemid);
    }


    protected function process_languagelesson_page($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->lessonid = $this->get_new_parentid('languagelesson');

        // We'll remap all the prevpageid and nextpageid at the end, once all pages have been created.
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->timecreated = $this->apply_date_offset($data->timecreated);

        $newitemid = $DB->insert_record('languagelesson_pages', $data);
        $this->set_mapping('languagelesson_page', $oldid, $newitemid, true); // Has related fileareas.
    }

    protected function process_languagelesson_answer($data) {
        global $DB;

        $data = (object)$data;
        $data->lessonid = $this->get_new_parentid('languagelesson');
        $data->pageid = $this->get_new_parentid('languagelesson_page');
        $data->answer = $data->answer_text;
        $data->grade = $data->answer_grade;
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->timecreated = $this->apply_date_offset($data->timecreated);

        // Set a dummy mapping to get the old ID so that it can be used by get_old_parentid when
        // processing attempts. It will be corrected in after_execute.
        $this->set_mapping('languagelesson_answer', $data->id, 0);

        // Answers need to be processed in order, so we store them in an
        // instance variable and insert them in the after_execute stage.
        $this->answers[$data->id] = $data;
    }

    protected function process_languagelesson_attempt($data) {
        global $DB;

        $data = (object)$data;
        $data->lessonid = $this->get_new_parentid('languagelesson');
        $data->pageid = $this->get_new_parentid('languagelesson_page');

        // We use the old answerid here as the answer isn't created until after_execute.
        $data->answerid = $this->get_old_parentid('languagelesson_answer');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timeseen = $this->apply_date_offset($data->timeseen);

        $newitemid = $DB->insert_record('languagelesson_attempts', $data);
    }

    protected function process_languagelesson_grade($data) {
        global $DB;
        $data = (object)$data;
        $data->lessonid = $this->get_new_parentid('languagelesson');
        $data->grade = $data->grade_info;
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->completed = $this->apply_date_offset($data->completed);

        $newitemid = $DB->insert_record('languagelesson_grades', $data);
    }

    protected function process_languagelesson_manattempt($data) {
        global $DB;

        $data = (object)$data;
        $data->lessonid = $this->get_new_parentid('languagelesson');
        $data->pageid = $this->get_new_parentid('languagelesson_page');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timeseen = $this->apply_date_offset($data->timeseen);

        $newitemid = $DB->insert_record('languagelesson_manattempts', $data);
    }

    protected function process_languagelesson_feedback($data) {
        global $DB;
        $data = (object)$data;
        $data->lessonid = $this->get_new_parentid('languagelesson');
        $data->pageid = $this->get_new_parentid('languagelesson_page');
        $data->manattemptid = $this->get_new_parentid('languagelesson_manattempts');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->teacherid = $this->get_mappingid('teacher', $data->teacherid);
        $data->timeseen = $this->apply_date_offset($data->timeseen);

        $newitemid = $DB->insert_record('languagelesson_feedback', $data);
    }

    protected function process_languagelesson_branch($data) {
        global $DB;

        $data = (object)$data;
        $data->lessonid = $this->get_new_parentid('languagelesson');
        $data->pageid = $this->get_new_parentid('languagelesson_page');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timeseen = $this->apply_date_offset($data->timeseen);

        $newitemid = $DB->insert_record('languagelesson_branch', $data);
    }

    protected function process_languagelesson_highscore($data) {
        global $DB;

        $data = (object)$data;
        $data->lessonid = $this->get_new_parentid('languagelesson');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->gradeid = $this->get_mappingid('languagelesson_grade', $data->gradeid);

        $newitemid = $DB->insert_record('languagelesson_high_scores', $data);
    }

    protected function process_languagelesson_timer($data) {
        global $DB;

        $data = (object)$data;
        $data->lessonid = $this->get_new_parentid('languagelesson');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->starttime = $this->apply_date_offset($data->starttime);
        $data->lessontime = $this->apply_date_offset($data->lessontime);

        $newitemid = $DB->insert_record('languagelesson_timer', $data);
    }

    protected function process_languagelesson_branchinfo($data) {
        global $DB;

        $data = (object)$data;
        $data->lessonid = $this->get_new_parentid('languagelesson');
        $data->parentid = $this->get_new_parentid('languagelesson_page');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->firstpage = $this->get_mappingid('languagelesson_page', $data->firstpage);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified= $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('languagelesson_branches', $data);
    }

    protected function process_languagelesson_seenbranch($data) {
        global $DB;

        $data = (object)$data;
        $data->lessonid = $this->get_new_parentid('languagelesson');
        $data->pageid = $this->get_new_parentid('languagelesson_page');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timeseen = $this->apply_date_offset($data->timeseen);

        $newitemid = $DB->insert_record('languagelesson_seenbranches', $data);
    }



    protected function after_execute() {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/mod/languagelesson/locallib.php');

        // Answers must be sorted by id to ensure that they're shown correctly.
        ksort($this->answers);
        foreach ($this->answers as $answer) {
            $newitemid = $DB->insert_record('languagelesson_answers', $answer);
            $this->set_mapping('languagelesson_answer', $answer->id, $newitemid);

            // Update the languagelesson attempts to use the newly created answerid.
            $DB->set_field('languagelesson_attempts', 'answerid', $newitemid, array(
                    'lessonid' => $answer->lessonid,
                    'pageid' => $answer->pageid,
                    'answerid' => $answer->id));
        }

        // Add lesson mediafile, no need to match by itemname (just internally handled context).
        $this->add_related_files('mod_languagelesson', 'mediafile', null);
        // Add lesson page files, by languagelesson_page itemname.
        $this->add_related_files('mod_languagelesson', 'page_contents', 'languagelesson_page');

        // Remap all the restored prevpageid and nextpageid now that we have all the pages and their mappings.
        $rs = $DB->get_recordset('languagelesson_pages', array('lessonid' => $this->task->get_activityid()),
                                 '', 'id, prevpageid, nextpageid');
        foreach ($rs as $page) {
            $page->prevpageid = (empty($page->prevpageid)) ? 0 : $this->get_mappingid('languagelesson_page', $page->prevpageid);
            $page->nextpageid = (empty($page->nextpageid)) ? 0 : $this->get_mappingid('languagelesson_page', $page->nextpageid);
            if ($maxscore = languagelesson_calc_page_maxscore($page->id)) {
                $page->maxscore = languagelesson_calc_page_maxscore($page->id);
            }
            $DB->update_record('languagelesson_pages', $page);
        }
        $rs->close();

        // Remap all the restored 'jumpto' fields now that we have all the pages and their mappings.
        $rs = $DB->get_recordset('languagelesson_answers', array('lessonid' => $this->task->get_activityid()),
                                 '', 'id, jumpto');
        foreach ($rs as $answer) {
            if ($answer->jumpto > 0) {
                $answer->jumpto = $this->get_mappingid('languagelesson_page', $answer->jumpto);
                $DB->update_record('languagelesson_answers', $answer);
            }
        }
        $rs->close();

        // Re-map the dependency and activitylink information.
        // If a depency or activitylink has no mapping in the backup data then it could either be a duplication of a
        // lesson, or a backup/restore of a single lesson. We have no way to determine which and whether this is the
        // same site and/or course. Therefore we try and retrieve a mapping, but fallback to the original value if one
        // was not found. We then test to see whether the value found is valid for the course being restored into.
        $lesson = $DB->get_record('languagelesson',
                        array('id' => $this->task->get_activityid()), 'id, course, dependency, activitylink');
        $updaterequired = false;
        if (!empty($lesson->dependency)) {
            $updaterequired = true;
            $lesson->dependency = $this->get_mappingid('languagelesson', $lesson->dependency, $lesson->dependency);
            if (!$DB->record_exists('languagelesson', array('id' => $lesson->dependency, 'course' => $lesson->course))) {
                $lesson->dependency = 0;
            }
        }

        if (!empty($lesson->activitylink)) {
            $updaterequired = true;
            $lesson->activitylink = $this->get_mappingid('course_module', $lesson->activitylink, $lesson->activitylink);
            if (!$DB->record_exists('course_modules', array('id' => $lesson->activitylink, 'course' => $lesson->course))) {
                $lesson->activitylink = 0;
            }
        }

        // Calculate the lesson maxgrade by adding up maxcore for all pages.
        $lesson->grade = languagelesson_calculate_lessongrade($lesson->id);
        $updaterequired = true;

        // Update max grade of lesson in grade item entry in gradebook.
        // And push to gradebook.

        if ($updaterequired) {
            $DB->update_record('languagelesson', $lesson);
        }

    }
}
