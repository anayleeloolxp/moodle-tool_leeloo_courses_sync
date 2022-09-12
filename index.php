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
 * Index Page.
 *
 * @package tool_leeloo_courses_sync
 * @copyright  2020 Leeloo LXP (https://leeloolxp.com)
 * @author     Leeloo LXP <info@leeloolxp.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_OUTPUT_BUFFERING', true);

require(__DIR__ . '/../../../config.php');
global $CFG;
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/lib/filelib.php');

require_login();
admin_externalpage_setup('toolleeloo_courses_sync');

global $SESSION;

$postcourses = optional_param_array('courses', array(), PARAM_RAW);
$postprices = optional_param_array('price', array(), PARAM_RAW);
$postkeytypes = optional_param_array('keytype', array(), PARAM_RAW);
$postkeyprices = optional_param_array('keyprice', array(), PARAM_RAW);

$vendorkey = get_config('tool_leeloo_courses_sync', 'vendorkey');

$leeloolxplicense = get_config('tool_leeloo_courses_sync')->license;

$url = 'https://leeloolxp.com/api_moodle.php/?action=page_info';
$postdata = [
    'license_key' => $leeloolxplicense,
];
$curl = new curl;

$options = array(
    'CURLOPT_RETURNTRANSFER' => true,
    'CURLOPT_HEADER' => false,
    'CURLOPT_POST' => count($postdata),
);

if (!$output = $curl->post($url, $postdata, $options)) {
    $urltogo = $CFG->wwwroot . '/admin/search.php';
    redirect($urltogo, get_string('nolicense', 'tool_leeloo_courses_sync'), 1);
    return true;
}

$infoleeloolxp = json_decode($output);

if ($infoleeloolxp->status != 'false') {
    $leeloolxpurl = $infoleeloolxp->data->install_url;
} else {
    $urltogo = $CFG->wwwroot . '/admin/search.php';
    redirect($urltogo, get_string('nolicense', 'tool_leeloo_courses_sync'), 1);
    return true;
}

$leelooapibaseurl = 'https://leeloolxp.com/api/moodle_sell_course_plugin/';

/**
 * Encrypt Data
 *
 * @param string $texttoencrypt The texttoencrypt
 * @return string Return encrypted string
 */
function encrption_data($texttoencrypt) {

    $encryptionmethod = "AES-256-CBC";
    $secrethash = "25c6c7ff35b9979b151f2136cd13b0ff";
    return @openssl_encrypt($texttoencrypt, $encryptionmethod, $secrethash);
}

$post = [
    'license_key' => encrption_data($vendorkey),
];

$url = $leelooapibaseurl . 'get_keytypes_by_licensekey.php';
$postdata = [
    'license_key' => encrption_data($vendorkey),
];
$curl = new curl;
$options = array(
    'CURLOPT_RETURNTRANSFER' => true,
    'CURLOPT_HEADER' => false,
    'CURLOPT_POST' => count($postdata),
);

if (!$output = $curl->post($url, $postdata, $options)) {
    $error = get_string('nolicense', 'tool_leeloo_courses_sync');
}
$keysresponse = json_decode($output);

if (!empty($postcourses)) {
    foreach ($postcourses as $postcourseid => $postcourse) {
        if ($postcourse == 0) {
            $leeloodept = $DB->get_record_sql(
                "SELECT productid FROM {tool_leeloo_courses_sync} WHERE courseid = ?",
                [$postcourseid]
            );

            $course = $DB->get_record_sql(
                "SELECT fullname,summary FROM {course} where id = ?",
                [$postcourseid]
            );

            $courseprice = $postprices[$postcourseid];
            $coursesynckeyprice = $postkeyprices[$postcourseid];
            $coursesynckeytype = $postkeytypes[$postcourseid];

            if ($courseprice == '') {
                $courseprice = 0;
            }
            if ($coursesynckeyprice == '') {
                $coursesynckeyprice = 0;
            }

            $post = [
                'license_key' => encrption_data($vendorkey),
                'action' => encrption_data('update'),
                'productid' => encrption_data('0'),
                'status' => encrption_data('0'),
                'coursename' => encrption_data($course->fullname),
                'coursesummary' => encrption_data($course->summary),
                'price' => encrption_data($courseprice),
                'keyprice' => encrption_data($coursesynckeyprice),
                'keytype' => encrption_data($coursesynckeytype),

            ];

            $url = $leelooapibaseurl . 'sync_courses_products.php';
            $curl = new curl;
            $options = array(
                'CURLOPT_RETURNTRANSFER' => true,
                'CURLOPT_HEADER' => false,
                'CURLOPT_POST' => count($post),
            );

            if (!$output = $curl->post($url, $post, $options)) {
                $error = get_string('nolicense', 'tool_leeloo_courses_sync');
            }
            $infoleeloo = json_decode($output);

            if ($infoleeloo->status == 'true') {
                $DB->execute(
                    "UPDATE {tool_leeloo_courses_sync} SET
                        enabled = ?,
                        productprice = ?,
                        keytype = ?,
                        keyprice = ?
                    WHERE courseid = ?",
                    [0, $courseprice, $coursesynckeytype, $coursesynckeyprice, $postcourseid]
                );
            }
        }

        if ($postcourse == 1) {
            $leeloocourse = $DB->get_record_sql(
                "SELECT COUNT(*) countcourse FROM {tool_leeloo_courses_sync} WHERE courseid = ?",
                [$postcourseid]
            );

            if ($leeloocourse->countcourse == 0) {
                $course = $DB->get_record_sql(
                    "SELECT fullname,summary FROM {course} where id = ?",
                    [$postcourseid]
                );

                $courseprice = $postprices[$postcourseid];
                $coursesynckeyprice = $postkeyprices[$postcourseid];
                $coursesynckeytype = $postkeytypes[$postcourseid];

                if ($courseprice == '') {
                    $courseprice = 0;
                }
                if ($coursesynckeyprice == '') {
                    $coursesynckeyprice = 0;
                }

                $post = [
                    'license_key' => encrption_data($vendorkey),
                    'action' => encrption_data('insert'),
                    'courseid' => encrption_data($postcourseid),
                    'coursename' => encrption_data($course->fullname),
                    'coursesummary' => encrption_data($course->summary),
                    'price' => encrption_data($courseprice),
                    'keyprice' => encrption_data($coursesynckeyprice),
                    'keytype' => encrption_data($coursesynckeytype),
                    'synctype' => encrption_data('1'),
                ];

                $url = $leelooapibaseurl . 'sync_courses_products.php';
                $curl = new curl;
                $options = array(
                    'CURLOPT_RETURNTRANSFER' => true,
                    'CURLOPT_HEADER' => false,
                    'CURLOPT_POST' => count($post),
                );

                if (!$output = $curl->post($url, $post, $options)) {
                    $error = get_string('nolicense', 'tool_leeloo_courses_sync');
                }
                $infoleeloo = json_decode($output);

                if ($infoleeloo->status == 'true') {
                    $productid = $infoleeloo->data->id;
                    $productalias = $infoleeloo->data->product_alias;
                    $DB->execute(
                        "INSERT INTO {tool_leeloo_courses_sync}
                            (courseid, productid, enabled, productprice,product_alias,keytype,keyprice)
                        VALUES
                            (?, ?, ?, ?, ?, ?, ?)",
                        [$postcourseid, $productid, 1, $courseprice, $productalias, $coursesynckeytype, $coursesynckeyprice]
                    );
                }
            } else {

                $leeloodept = $DB->get_record_sql(
                    "SELECT productid FROM {tool_leeloo_courses_sync} WHERE courseid = ?",
                    [$postcourseid]
                );

                $productid = $leeloodept->productid;
                $course = $DB->get_record_sql("SELECT fullname,summary FROM {course} where id = ?", [$postcourseid]);

                $courseprice = $postprices[$postcourseid];
                $coursesynckeyprice = $postkeyprices[$postcourseid];
                $coursesynckeytype = $postkeytypes[$postcourseid];

                if ($courseprice == '') {
                    $courseprice = 0;
                }
                if ($coursesynckeyprice == '') {
                    $coursesynckeyprice = 0;
                }

                $post = [
                    'license_key' => encrption_data($vendorkey),
                    'action' => encrption_data('update'),
                    'productid' => encrption_data($productid),
                    'status' => encrption_data('1'),
                    'coursename' => encrption_data($course->fullname),
                    'coursesummary' => encrption_data($course->summary),
                    'price' => encrption_data($courseprice),
                    'keyprice' => encrption_data($coursesynckeyprice),
                    'keytype' => encrption_data($coursesynckeytype),
                ];

                $url = $leelooapibaseurl . 'sync_courses_products.php';
                $curl = new curl;
                $options = array(
                    'CURLOPT_RETURNTRANSFER' => true,
                    'CURLOPT_HEADER' => false,
                    'CURLOPT_POST' => count($post),
                );

                if (!$output = $curl->post($url, $post, $options)) {
                    $error = get_string('nolicense', 'tool_leeloo_courses_sync');
                }
                $infoleeloo = json_decode($output);
                if ($infoleeloo->status == 'true') {
                    $DB->execute(
                        "UPDATE {tool_leeloo_courses_sync} SET
                            enabled = ?,
                            productprice = ?,
                            keytype = ?,
                            keyprice = ?
                        WHERE courseid = ?",
                        [1, $courseprice, $coursesynckeytype, $coursesynckeyprice, $postcourseid]
                    );
                }
            }
        }
    }
}

$courses = $DB->get_records_sql(
    "SELECT
        {course}.id,
        {course}.fullname,
        {tool_leeloo_courses_sync}.enabled,
        {tool_leeloo_courses_sync}.productprice,
        {tool_leeloo_courses_sync}.keytype,
        {tool_leeloo_courses_sync}.keyprice
    FROM {course}
    LEFT JOIN {tool_leeloo_courses_sync}
    ON {course}.id = {tool_leeloo_courses_sync}.courseid
    ORDER BY {course}.id ASC"
);

echo $OUTPUT->header();
echo $OUTPUT->heading_with_help(
    get_string('leeloo_courses_sync', 'tool_leeloo_courses_sync'),
    'leeloo_courses_sync',
    'tool_leeloo_courses_sync'
);

if (!empty($error)) {
    echo $OUTPUT->container($error, 'leeloo_courses_sync_myformerror');
}
$thstyle = '.sellcoursesynctable td,.sellcoursesynctable th {padding: 5px;}';

$instyle = 'border: 1px solid #ced4da;
padding: .375rem .75rem;
height: calc(1.5em + .75rem + 2px);
font-size: .9375rem;
color: #495057;';

$selinput = '.sellcoursesynctable input, .sellcoursesynctable select {' . $instyle . '}';
echo '<style>' . $thstyle . $selinput . '.sellcoursesynctable label{margin-bottom: 0;}</style>';

if (!empty($courses)) {
    echo '<form method="post">
    <table class="sellcoursesynctable" style="width: 100%;">
    <thead>
        <th>&nbsp;</th>
        <th>' . get_string('course', 'tool_leeloo_courses_sync') . '</th>
        <th>' . get_string('price', 'tool_leeloo_courses_sync') . '</th>
        <th>' . get_string('keyallow', 'tool_leeloo_courses_sync') . '</th>
        <th>' . get_string('keyprice', 'tool_leeloo_courses_sync') . '</th>
    </thead>';
    foreach ($courses as $course) {
        $courseid = $course->id;
        $coursefullname = $course->fullname;
        $courseenabled = $course->enabled;
        $courseproductprice = $course->productprice;
        $coursekeyprice = $course->keyprice;
        $coursekeytype = $course->keytype;
        if ($courseenabled == 1) {
            $checkboxchecked = 'checked';
            $pricestyle = '';
        } else {
            $checkboxchecked = '';
            $pricestyle = '';
        }
        echo '<tr>';

        echo "<td><input type='hidden' value='0' name='courses[$courseid]'>" .
            "<input $checkboxchecked id='course_$courseid' type='checkbox' name='courses[$courseid]' value='1'></td>";

        echo "<td><label for='course_$courseid'>$coursefullname</label></td>";
        echo "<td><input type='number' value='$courseproductprice' name='price[$courseid]' id='price_$courseid' $pricestyle></td>";

        $keysselect = "<select name='keytype[$courseid]'><option value='-1'>" .
            get_string('no', 'tool_leeloo_courses_sync') .
            "</option>";

        if ($keysresponse->status == 'true') {
            foreach ($keysresponse->data->keys as $keytype) {
                if ($coursekeytype == $keytype->id) {
                    $selectedkeytype = 'selected';
                } else {
                    $selectedkeytype = '';
                }
                $keysselect .= "<option $selectedkeytype value='$keytype->id'>$keytype->name</option>";
            }
        }
        $keysselect .= "</select>";

        echo "<td>$keysselect</td>";

        echo "<td><input type='number' value='$coursekeyprice' name='keyprice[$courseid]' id='price_$courseid' $pricestyle></td>";
        echo '</tr>';
    }

    $btnstyle = 'padding: 10px 20px;color: #222222;background: #eeeeee;border: 1px solid #cccccc;border-radius: 5px;';

    $buttonsubmit = '<button style="' .
        $btnstyle .
        '"type="submit" value="Save and Create Departments">' .
        get_string('submit', 'tool_leeloo_courses_sync') .
        '</button>';

    echo '<tr><td colspan="5" style="text-align: center;">' . $buttonsubmit . '</td></tr></table></form>';
}

echo $OUTPUT->footer();
