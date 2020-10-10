<?php
define('NO_OUTPUT_BUFFERING', true);
require (__DIR__ . '/../../../config.php');
$enrolled = 0;
global $DB;
function check_enrol($courseid, $userid, $roleid, $enrolmethod = 'manual') {
    file_put_contents(dirname(__FILE__) . "/check_enrol.txt", print_r($courseid . ' ' . $userid, true));
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

    file_put_contents(dirname(__FILE__) . "/params.txt", print_r($username . '' . $productid, true));

    $courseid_arr = $DB->get_record_sql("SELECT courseid FROM {tool_leeloo_courses_sync} Where productid = '$productid'");
    $courseid = $courseid_arr->courseid;

    $userid_arr = $DB->get_record_sql("SELECT id FROM {user} Where username = '$username'");
    $userid = $userid_arr->id;

    if ($courseid && $userid) {
        file_put_contents(dirname(__FILE__) . "/if.txt", print_r($courseid . ' ' . $userid, true));

        if (check_enrol($courseid, $userid, 5, 'manual')) {
            $enrolled = 1;
        }
    }
}
file_put_contents(dirname(__FILE__) . "/enrolled.txt", print_r($enrolled, true));
echo $enrolled;