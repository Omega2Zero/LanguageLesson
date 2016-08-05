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
 * This file keeps track of upgrades to
 * the lesson module
 *
 * Sometimes, changes between versions involve
 * alterations to database structures and other
 * major things that may break installations.
 *
 * The upgrade function in this file will attempt
 * to perform all the necessary actions to upgrade
 * your older installation to the current version.
 *
 * If there's something it cannot do itself, it
 * will tell you what you need to do.
 *
 * The commands in here will all be database-neutral,
 * using the methods of database_manager class
 *
 * Please do not forget to use upgrade_set_timeout()
 * before any action that may take longer time to finish.
 *
 * @package    mod
 * @subpackage lesson
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 o
 */

defined('MOODLE_INTERNAL') || die();

/**
 *
 * @global stdClass $CFG
 * @global moodle_database $DB
 * @global core_renderer $OUTPUT
 * @param int $oldversion
 * @return bool
 */
function xmldb_languagelesson_upgrade($oldversion) {
    global $CFG, $DB, $OUTPUT;

    $dbman = $DB->get_manager();

    if ($oldversion < 201208100 ) {

        // Drop unnecessary table.
        $table = new xmldb_table('languagelesson_default');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        // Adjust grade field to handle decimals.
        $table = new xmldb_table('languagelesson_grades');
        $field = new xmldb_field('grade', XMLDB_TYPE_NUMBER, '10,2', XMLDB_UNSIGNED, null, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
        }

        // Adjust feedback fields to track oral feedback better.
        $table = new xmldb_table('languagelesson_feedback');
        $field = new xmldb_field('fileid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null);

        // Add field to track id of feedback files.
        if (!$dbman->field_exists($field)) {
            $dbman->add_field($table, $field);
        }

        // Add field to store feedback filenames to make them easier to retrieve.
        $field = new xmldb_field('filename', XMLDB_TYPE_TEXT, 'medium', null, null, null, null);
        if (!$dbman->field_exists($field)) {
            $dbman->add_field($table, $field);
        }

        // Drop the old fname field from version 1.
        $field = new xmldb_field('fname', XMLDB_TYPE_TEXT);
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Rename manattemptid to attemptid because we aren't using a separate table for manattempts anymore.
        $field = new xmldb_field('manattemptid');
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'attemptid');
        }

        // All edits made, store the new version and set it as the current version.
        upgrade_mod_savepoint(true, 201208100, 'languagelesson');
    }

    if ($oldversion < 2012101500) {
        $table = new xmldb_table('languagelesson_feedback');

        $field = new xmldb_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
        }

        $field = new xmldb_field('lessonid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
        }

        $field = new xmldb_field('pageid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
        }

        $field = new xmldb_field('userid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
        }

        $field = new xmldb_field('attemptid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
        }

        $field = new xmldb_field('teacherid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
        }

        $field = new xmldb_field('fileid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
        }

        $field = new xmldb_field('filename', XMLDB_TYPE_TEXT, 'medium', XMLDB_UNSIGNED, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
        }

        $field = new xmldb_field('text', XMLDB_TYPE_TEXT, 'long', XMLDB_UNSIGNED, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
        }

        $field = new xmldb_field('timeseen', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
        }

        $field = new xmldb_field('location', XMLDB_TYPE_INTEGER, '10,9', XMLDB_UNSIGNED, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
        }

        $table = new xmldb_table('languagelesson_grades');

        $field = new xmldb_field('grade', XMLDB_TYPE_INTEGER, '10,2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
        }

        $table = new xmldb_table('languagelesson');

        $field = new xmldb_field('slideshow', XMLDB_TYPE_INTEGER);
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        $field = new xmldb_field('width', XMLDB_TYPE_INTEGER);
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        $field = new xmldb_field('height', XMLDB_TYPE_INTEGER);
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // All edits made, store the new version and set it as the current version.
        upgrade_mod_savepoint(true, 2012101500, 'languagelesson');

        return true;

    }
    
    if ($oldversion < 2016022200) {
        
        // Define field to be added to languagelesson.
        $table = new xmldb_table('languagelesson');
        $field = new xmldb_field('completionsubmit', XMLDB_TYPE_INTEGER, '2', null,
                                 XMLDB_NOTNULL, null, '0', 'timemodified');

        // Conditionally launch add field.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Languagelesson savepoint reached.
        upgrade_mod_savepoint(true, 2016022200, 'languagelesson');
    }

        if ($oldversion < 2016022201) {
        
        // Define field to be added to languagelesson.
        $table = new xmldb_table('languagelesson');
        $field = new xmldb_field('pagecount', XMLDB_TYPE_INTEGER, '3', null,
                                 XMLDB_NOTNULL, null, '0', 'timemodified');

        // Conditionally launch add field.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Languagelesson savepoint reached.
        upgrade_mod_savepoint(true, 2016022201, 'languagelesson');
    }
    // Moodle v2.2.0 release upgrade line.
    // Put any upgrade step following this.

    return true;
}


