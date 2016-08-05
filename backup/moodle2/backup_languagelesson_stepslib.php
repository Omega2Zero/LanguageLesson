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
 * This file contains the backup structure for the lanugauge lesson module
 *
 * This is the "graphical" structure of the lesson module:
 *
 *                 
 *                                                                                              languagelesson 
 *                                                                              (CL, pk->id)                 
 *                                                                                             |
 *                                                                                             |
 *                                                                                             |
 *                                                                                             |
 *                                                                                                                      languagelesson_pages--------------languagelesson_timer------------ languagelesson_grades
 *                                                                                                                (pk->id,fk->lessonid)                    (UL, pk->id,fk->lessonid)           (UL, pk->id,fk->lessonid)
 *                                                                                                                              |                                                                                                                                                |
 *                                                                                                                              |                                                                                                                                                |
 *                                                                                                                              |                                                                                                                                                |
 * ll_branches---LLseenbraches-----languagelesson_branch---------- languagelesson_answers----------------languagelesson_manattempts      languagelesson_highscore
 * (fk->pageid)  (UL,fk->pageid)   (UL, pk->id, fk->pageid)                  (CL,pk->id,fk->pageid)                                  (UL,pk->id, ck->pageid)                   (UL, pk->id, ck->gradeid)
 *                                                                                                                              |                                                                        |
 *                                                                                                                              |                                                                        |
 *                                                                                                                              |                                                                        |
 *                                                                                                                languagelesson_attempts                                languagelesson_feedback
 *                                                                                                                   (UL,pk->id,fk->answerid)                        (UL, pk->id, ck->manattemptid)
 *                                                                                                                   
 * Meaning: pk->primary key field of the table
 *          fk->foreign key to link with parent
 *          nt->nested field (recursive data)
 *          CL->course level info
 *          UL->user level info
 *          files->table may have files)
 *
 * @package    mod
 * @subpackage lesson
 * @copyright  2010 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Structure step class that informs a backup task how to backup the lesson module.
 *
 * @copyright  2010 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_languagelesson_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        // The languagelesson table.
        // This table contains all of the goodness for the lesson module, quite
        // alot goes into it but nothing relational other than course when will
        // need to be corrected upon restore.
        $lesson = new backup_nested_element('languagelesson', array('id'), array(
            'course', 'name', 'practice', 'modattempts', 'usepassword', 'password', 'grade',
            'dependency', 'conditions', 'lesson_grade', 'custom', 'ongoing', 'usemaxgrade', 'showongoingscore', 'showoldanswer',
            'maxanswers', 'maxattempts', 'review', 'nextpagedefault', 'feedback',
            'minquestions', 'maxpages', 'penalty', 'penaltytype', 'penaltyvalue', 'autograde', 'shuffleanswers', 'defaultfeedback',
            'defaultcorrect', 'defaultwrong', 'timed', 'maxtime', 'retake', 'activitylink',
            'mediafile', 'mediaheight', 'mediawidth', 'mediaclose', 'bgcolor', 'displayleft', 'displayleftif', 'contextcolors', 'progressbar',
            'showhighscores', 'available', 'deadline', 'timemodified', 'completionsubmit', 'pagecount'
        ));
        // Tell the lesson element about the showhighscores elements mapping to the highscores
        // database field.
        $lesson->set_source_alias('highscores', 'showhighscores');
        $lesson->set_source_alias('grade', 'lesson_grade');
        // The languagelesson_pages table
        // Grouped within a `pages` element, important to note that page is relational
        // to the lesson, and also to the previous/next page in the series.
        // Upon restore prevpageid and nextpageid will need to be corrected.
        $pages = new backup_nested_element('pages');
        $page = new backup_nested_element('page', array('id'), array('branchid',
            'prevpageid', 'nextpageid', 'ordering', 'qtype', 'qoption', 'layout', 'maxscore',
            'display', 'timecreated', 'timemodified', 'title', 'contents',
            'contentsformat'
        ));

        // The languagelesson_answers table
        // Grouped within an answers `element`, the languagelesson_answers table relates
        // to the page and lesson with `pageid` and `lessonid` that will both need
        // to be corrected during restore.
        $answers = new backup_nested_element('answers');
        $answer = new backup_nested_element('answer', array('id'), array(
            'jumpto', 'answer_grade', 'score', 'flags', 'timecreated', 'timemodified', 'answer_text',
            'answerformat', 'response', 'responseformat'
        ));
        // Tell the answer element about the answer_text elements mapping to the answer
        // database field.
        $answer->set_source_alias('answer', 'answer_text');
        $answer->set_source_alias('grade', 'answer_grade');

        // The languagelesson_attempts table
        // Grouped by an `attempts` element this is relational to the page, lesson,
        // and user.
        $attempts = new backup_nested_element('attempts');
        $attempt = new backup_nested_element('attempt', array('id'), array(
            'userid', 'viewed', 'retry', 'iscurrent', 'correct', 'useranswer', 'score', 'timeseen'
        ));

        // Languagelesson_manattempts table.
        $manattempts = new backup_nested_element('manattempts');
        $manattempt = new backup_nested_element('manattempt', array('id'),
                        array('userid', 'viewed', 'graded', 'type', 'essay', 'fname', 'resubmit', 'timeseen'));

        // Languagelesson_feedback table.
        $feedbacks = new backup_nested_element('feedbacks');
        $feedback = new backup_nested_element('feedback', array('id'), array('teacherid', 'fname', 'text', 'timeseen'));

        // The languagelesson_branch table
        // Grouped by a `branch` element this is relational to the page, lesson,
        // and user.
        $branches = new backup_nested_element('branches');
        $branch = new backup_nested_element('branch', array('id'), array(
            'userid', 'retry', 'flag', 'timeseen'
        ));

        // The languagelesson_grades table
        // Grouped by a grades element this is relational to the lesson and user.
        $grades = new backup_nested_element('grades');
        $grade = new backup_nested_element('grade', array('id'), array(
            'userid', 'grade_info', 'late', 'completed'
        ));

        $grade->set_source_alias('grade', 'grade_info');

        // The languagelesson_high_scores table
        // Grouped by a highscores element this is relational to the lesson, user,
        // and possibly a grade.
        $highscores = new backup_nested_element('highscores');
        $highscore = new backup_nested_element('highscore', array('id'), array(
            'gradeid', 'userid', 'nickname'
        ));

        // The languagelesson_timer table
        // Grouped by a `timers` element this is relational to the lesson and user.
        $timers = new backup_nested_element('timers');
        $timer = new backup_nested_element('timer', array('id'), array(
            'userid', 'starttime', 'lessontime'
        ));

        $seenbranches = new backup_nested_element('seenbranches');
        $seenbranch = new backup_nested_element('seenbranch', array('id'), array(
            'userid', 'flag', 'timeseen'
        ));

        $branchinfos = new backup_nested_element('branchinfos');
        $branchinfo = new backup_nested_element('branchinfo', array('id'),
                        array('ordering', 'firstpage', 'title', 'timecreated', 'timemodified'));

        // Now that we have all of the elements created we've got to put them
        // together correctly.
        $lesson->add_child($pages);
        $pages->add_child($page);
        $page->add_child($answers);
        $answers->add_child($answer);
        $answer->add_child($attempts);
        $attempts->add_child($attempt);
        $page->add_child($branchinfos);
        $branchinfos->add_child($branchinfo);
        $page->add_child($seenbranches);
        $seenbranches->add_child($seenbranch);
        $page->add_child($branches);
        $branches->add_child($branch);
        $page->add_child($manattempts);
        $manattempts->add_child($manattempt);
        $manattempt->add_child($feedbacks);
        $feedbacks->add_child($feedback);
        $lesson->add_child($grades);
        $grades->add_child($grade);
        $lesson->add_child($highscores);
        $highscores->add_child($highscore);
        $lesson->add_child($timers);
        $timers->add_child($timer);

        // Set the source table for the elements that aren't reliant on the user
        // at this point (lesson, languagelesson_pages, languagelesson_answers).
        $lesson->set_source_table('languagelesson', array('id' => backup::VAR_ACTIVITYID));
        // We use SQL here as it must be ordered by prevpageid so that restore gets the pages in the right order.
        $page->set_source_sql("
                SELECT *
                  FROM {languagelesson_pages}
                 WHERE lessonid = ? ORDER BY prevpageid",
                array(backup::VAR_PARENTID));

        // We use SQL here as answers must be ordered by id so that the restore gets them in the right order.
        $answer->set_source_sql('
                SELECT *
                FROM {languagelesson_answers}
                WHERE pageid = :pageid
                ORDER BY id',
                array('pageid' => backup::VAR_PARENTID));
        $branchinfo->set_source_table('languagelesson_branches', array('parentid'=>backup::VAR_PARENTID));
        // Check if we are also backing up user information.
        if ($this->get_setting_value('userinfo')) {
            // Set the source table for elements that are reliant on the user.
            // languagelesson_attempts, languagelesson_branch, languagelesson_grades,
            // languagelesson_high_scores, languagelesson_timer.
            $attempt->set_source_table('languagelesson_attempts', array('answerid' => backup::VAR_PARENTID));
            $manattempt->set_source_table('languagelesson_manattempts', array('pageid'=>backup::VAR_PARENTID));
            $feedback->set_source_table('languagelesson_feedback', array('manattemptid'=>backup::VAR_PARENTID));
            $branch->set_source_table('languagelesson_branch', array('pageid' => backup::VAR_PARENTID));
            $seenbranch->set_source_table('languagelesson_seenbranches', array('pageid' => backup::VAR_PARENTID));
            $grade->set_source_table('languagelesson_grades', array('lessonid'=>backup::VAR_PARENTID));
            $highscore->set_source_table('languagelesson_high_scores', array('lessonid' => backup::VAR_PARENTID));
            $timer->set_source_table('languagelesson_timer', array('lessonid' => backup::VAR_PARENTID));
        }

        // Annotate the user (teacher) id's where required.
        $attempt->annotate_ids('user', 'userid');
        $seenbranch->annotate_ids('user', 'userid');
        $manattempt->annotate_ids('user', 'userid');
        $feedback->annotate_ids('teacher', 'teacherid');
        $grade->annotate_ids('user', 'userid');
        $highscore->annotate_ids('user', 'userid');
        $timer->annotate_ids('user', 'userid');

        // Annotate the file areas in user by the lesson module.
        $lesson->annotate_files('mod_languagelesson', 'mediafile', null);
        $page->annotate_files('mod_languagelesson', 'page_contents', 'id');

        // Prepare and return the structure we have just created for the lesson module.
        return $this->prepare_activity_structure($lesson);
    }
}
