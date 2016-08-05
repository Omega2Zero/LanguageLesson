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
 * Settings used by the lesson module, were moved from mod_edit
 *
 * @package    mod
 * @subpackage lesson
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or late
 **/

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot.'/mod/languagelesson/locallib.php');

    /* Slideshow settings */
    $settings->add(new admin_setting_configtext('languagelesson_slideshowwidth', get_string('slideshowwidth', 'languagelesson'),
            get_string('configslideshowwidth', 'languagelesson'), 640, PARAM_INT));

    $settings->add(new admin_setting_configtext('languagelesson_slideshowheight', get_string('slideshowheight', 'languagelesson'),
            get_string('configslideshowheight', 'languagelesson'), 480, PARAM_INT));

    $settings->add(new admin_setting_configtext('languagelesson_slideshowbgcolor', get_string('slideshowbgcolor', 'languagelesson'),
            get_string('configslideshowbgcolor', 'languagelesson'), '#FFFFFF', PARAM_TEXT));

    /* Media file popup settings */
    $settings->add(new admin_setting_configtext('languagelesson_mediawidth', get_string('mediawidth', 'languagelesson'),
            get_string('configmediawidth', 'languagelesson'), 640, PARAM_INT));

    $settings->add(new admin_setting_configtext('languagelesson_mediaheight', get_string('mediaheight', 'languagelesson'),
            get_string('configmediaheight', 'languagelesson'), 480, PARAM_INT));

    $settings->add(new admin_setting_configcheckbox('languagelesson_mediaclose', get_string('mediaclose', 'languagelesson'),
            get_string('configmediaclose', 'languagelesson'), false, PARAM_TEXT));

    /* Misc lesson settings */
    $settings->add(new admin_setting_configtext('languagelesson_maxhighscores', get_string('maxhighscores', 'languagelesson'),
            get_string('configmaxhighscores', 'lesson'), 10, PARAM_INT));

    /* Default lesson settings */
    $numbers = array();
    for ($i=20; $i>1; $i--) {
        $numbers[$i] = $i;
    }
    $settings->add(new admin_setting_configselect('languagelesson_maxanswers', get_string('maximumnumberofanswersbranches', 'lesson'),
            get_string('configmaxanswers', 'languagelesson'), 4, $numbers));

    $defaultnextpages = array();
    $defaultnextpages[0] = get_string("normal", "languagelesson");
    $defaultnextpages[LL_UNSEENPAGE] = get_string("showanunseenpage", "languagelesson");
    $defaultnextpages[LL_UNANSWEREDPAGE] = get_string("showanunansweredpage", "languagelesson");
    $settings->add(new admin_setting_configselect('languagelesson_defaultnextpage', get_string('actionaftercorrectanswer', 'lesson'),
            get_string('configactionaftercorrectanswer', 'languagelesson'), 0, $defaultnextpages));
}