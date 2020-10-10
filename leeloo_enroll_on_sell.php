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
 * Enrol user on purchase.
 *
 * @package tool_leeloo_courses_sync
 * @copyright  2020 Leeloo LXP (https://leeloolxp.com)
 * @author     Leeloo LXP <info@leeloolxp.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_OUTPUT_BUFFERING', true);
require (__DIR__ . '/../../../config.php');
$enrolled = 0;
global $DB;

/**
 * Check if user is enrolled
 *
 * @param int $courseid The courseid
 * @param int $userid The userid
 * @param int $roleid The roleid
 * @param int $enrolmethod The enrolmethod
 * @return bool Return true
 */
function check_enrol($courseid, $userid, $roleid, $enrolmethod = 'manual') {
    global $DB;
    $user = $DB->get_record('user', array('id' => $userid, 'deleted' => 0), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
    $context = context_course::instance($course->id);
    if (!is_enrolled($context, $user)) {
        $enrol = enrol_get_plugin($enrolmethod);
        if ($enrol === null) {
            return false;
        }
        $instances = enrol_get_instances($course->id, true);
        $manualinstance = null;
        foreach ($instances as $instance) {
            if ($instance->enrol == $enrolmethod) {
                $manualinstance = $instance;
                break;
            }
        }
        if ($manualinstance == null) {
            $instanceid = $enrol->add_default_instance($course);
            if ($instanceid === null) {
                $instanceid = $enrol->add_instance($course);
            }
            $manualinstance = $DB->get_record('enrol', array('id' => $instanceid));
        }
        $enrol->enrol_user($manualinstance, $userid, $roleid);
    }
    return true;
}

if (isset($_REQUEST['product_id']) && isset($_REQUEST['username'])) {
    $productid = $_REQUEST['product_id'];
    $username = $_REQUEST['username'];

    $courseidarr = $DB->get_record_sql("SELECT courseid FROM {tool_leeloo_courses_sync} Where productid = '$productid'");
    $courseid = $courseidarr->courseid;

    $useridarr = $DB->get_record_sql("SELECT id FROM {user} Where username = '$username'");
    $userid = $useridarr->id;

    if ($courseid && $userid) {
        if (check_enrol($courseid, $userid, 5, 'manual')) {
            $enrolled = 1;
        }
    }
}

echo $enrolled;