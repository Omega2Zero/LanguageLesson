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
 * jjg7:8/9/2004
 *
 * @package    mod
 * @subpackage lesson
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or late
 **/

defined('MOODLE_INTERNAL') || die();

function removedoublecr($filename) {
    // This function will adjust a file in roughly Aiken style by replacing extra newlines with <br/> tags
    // so that instructors can have newlines wherever they like as long as the overall format is in Aiken.

    $filearray = file($filename);
    // Check for Macintosh OS line returns (ie file on one line), and fix.
    if (preg_match("/\r/", $filearray[0]) AND !preg_match("/\n/", $filearray[0])) {
        $outfile = explode("\r", $filearray[0]);
    } else {
        $outfile = $filearray;
    }

    $outarray = array();

    foreach ($outfile as $line) {
        // Remove leading and trailing whitespace.
        trim($line);
        // Check it's length, if 0 do not output... if it is > 0 output.
        if ($line[0] == "\n" OR strlen($line)==0 ) {
            if (count($outarray) ) {
                // Get the last item in the outarray.
                $curpos = (count($outarray) - 1);
                $outarray[$curpos] = trim($outarray[$curpos])."<br/>\n";
            }
        } else {
            $length=strlen($line);
            if ($length==0) {
                // Don't do anything.
            } else {
                if ($line[$length-1] == "\n") {
                    $outarray[] = $line;
                } else {
                    $outarray[] = $line."\n";
                }
            }
        }
    }
    // Output modified file to original.
    if ( is_writable($filename) ) {

        if (! $handle =fopen ($filename , 'w' )) {
            echo "Cannot open file ($filename)";
            exit;
        }
        foreach ($outarray as $outline) {
            fwrite($handle, $outline);
        }
        fclose($handle);
    } else {
        // File not writeable.
    }
}

// Jjg7:8/9/2004.
function importmodifiedaikenstyle($filename) {
    // This function converts from Brusca style to Aiken.
    $lines = file($filename);
    $answerfound = 0;
    $responses = 0;
    $outlines = array();
    foreach ($lines as $line) {
        // Strip leading and trailing whitespace.
        $line = trim($line);
        // Add a space at the end, quick hack to make sure words from different lines don't run together.
        $line = $line. ' ';

        // Ignore lines less than 2 characters.
        if (strlen($line) < 2) {
            continue;
        }

        // See if we have the answer line.
        if ($line[0] =='*') {
            if ($line[0] == '*') {
                $answerfound = 1;
                $line[0]="\t";
                $line = ltrim($line);
                $answer = $line[0];
            }
        }

        $leadin = substr($line, 0, 2);
        if (strpos(".A)B)C)D)E)F)G)H)I)J)a)b)c)d)e)f)g)h)i)j)A.B.C.D.E.F.G.H.I.J.a.b.c.d.e.f.g.h.i.j.", $leadin)>0) {

            // Re-add newline to indicate end of previous question/response.
            if (count($outlines)) {
                $curpos = (count($outlines) - 1);
                $outlines[$curpos] = $outlines[$curpos]."\n";
            }

            $responses = 1;
            // Make character uppercase.
            $line[0]=strtoupper($line[0]);

            // Make entry followed by '.'.
            $line[1]='.';
        } else if ( ($responses AND $answerfound) OR (count($outlines)<=1) ) {
            // We have found responses and an answer and the current line is not an answer.
            switch ($line[0]) {
                case 1:
                case 2:
                case 3:
                case 4:
                case 5:
                case 6:
                case 7:
                case 8:
                case 9:

                    // Re-add newline to indicate end of previous question/response.
                    if (count($outlines)) {
                        $curpos = (count($outlines) - 1);
                        $outlines[$curpos] = $outlines[$curpos]."\n";
                    }

                    // This next ugly block is to strip out the numbers at the beginning.
                    $np = 0;
                    // This probably could be done cleaner... it escapes me at the moment.
                    while ($line[$np] == '0' OR $line[$np] == '1' OR $line[$np] == '2'
                            OR $line[$np] == '3' OR $line[$np] == '4'  OR $line[$np] == '5'
                            OR $line[$np] == '6'  OR $line[$np] == '7' OR $line[$np] == '8'
                            OR $line[$np] == '9' ) {
                        $np++;
                    }
                    // Grab everything after '###.'.
                    $line = substr($line, $np+1, strlen($line));

                    if ($responses AND $answerfound) {
                        $responses = 0;
                        $answerfound = 0;
                        $answer = strtoupper($answer);
                        $outlines[] = "ANSWER: $answer\n\n";
                    }
                    break;
            }
        }
        if (substr($line, 0, 14) == 'ANSWER CHOICES') {
            // Don't output this line.
        } else {
            $outlines[]=$line;
        }
    } // Close for each line.

    // Re-add newline to indicate end of previous question/response.
    if (count($outlines)) {
        $curpos = (count($outlines) - 1);
        $outlines[$curpos] = $outlines[$curpos]."\n";
    }

    // Output the last answer.
    $answer = strtoupper($answer);
    $outlines[] = "ANSWER: $answer\n\n";

    // Output modified file to original.
    if ( is_writable($filename) ) {
        if (! $handle =fopen ($filename , 'w' )) {
            echo "Cannot open file ($filename)";
            exit;
        }
        foreach ($outlines as $outline) {
            fwrite($handle, $outline);
        }
        fclose($handle);
        return true;
    } else {
        return false;
    }
}

