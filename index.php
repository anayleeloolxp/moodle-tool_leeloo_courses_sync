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

require (__DIR__ . '/../../../config.php');
require_once ($CFG->libdir . '/adminlib.php');

require_login();
admin_externalpage_setup('toolleeloo_courses_sync');

global $SESSION;

$postcourses = optional_param('courses', null, PARAM_RAW);
$postprices = optional_param('price', null, PARAM_RAW);
$postkeytypes = optional_param('keytype', null, PARAM_RAW);
$postkeyprices = optional_param('keyprice', null, PARAM_RAW);

$vendorkey = get_config('tool_leeloo_courses_sync', 'vendorkey');

$leeloolxplicense = get_config('tool_leeloo_courses_sync')->license;

$url = 'https://leeloolxp.com/api_moodle.php/?action=page_info';
$postdata = '&license_key=' . $leeloolxplicense;

$curl = new curl;

$options = array(
    'CURLOPT_RETURNTRANSFER' => true,
    'CURLOPT_HEADER' => false,
    'CURLOPT_POST' => count($postdata),
);

if (!$output = $curl->post($url, $postdata, $options)) {
    $error = get_string('nolicense', 'tool_leeloo_courses_sync');
}

$infoleeloolxp = json_decode($output);

if ($infoleeloolxp->status != 'false') {
    $leeloolxpurl = $infoleeloolxp->data->install_url;
} else {
    $error = get_string('nolicense', 'block_leeloo_prodcuts');
}

$leelooapibaseurl = 'https://leeloolxp.com/api/moodle_sell_course_plugin/';

echo '<style>.sellcoursesynctable td,.sellcoursesynctable th {border: 1px solid;padding: 5px;}</style>';

function encrption_data($texttoencrypt) {

    $encryptionmethod = "AES-256-CBC";
    $secrethash = "25c6c7ff35b9979b151f2136cd13b0ff";
    return openssl_encrypt($texttoencrypt, $encryptionmethod, $secrethash);
}

$post = [
    'license_key' => encrption_data($vendorkey),
];

$url = $leelooapibaseurl . 'get_keytypes_by_licensekey.php';
$postdata = '&license_key=' . encrption_data($vendorkey);
$curl = new curl;
$options = array(
    'CURLOPT_RETURNTRANSFER' => true,
    'CURLOPT_POSTFIELDS' => $post,
);

if (!$output = $curl->post($url, $postdata, $options)) {
    $error = get_string('nolicense', 'tool_leeloo_courses_sync');
}
$keysresponse = json_decode($output);

if ($postcourses) {
    foreach ($postcourses as $postcourseid => $postcourse) {
        if ($postcourse == 0) {
            $leeloodept = $DB->get_record_sql('SELECT productid FROM {tool_leeloo_courses_sync} WHERE courseid = ' . $postcourseid . '');

            $productid = $leeloodept->productid;
            $course = $DB->get_record_sql('SELECT fullname,summary FROM {course} where id = ' . $postcourseid);

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
            );

            if (!$output = $curl->post($url, $post, $options)) {
                $error = get_string('nolicense', 'tool_leeloo_courses_sync');
            }
            $infoleeloo = json_decode($output);

            if ($infoleeloo->status == 'true') {
                $DB->execute("UPDATE {tool_leeloo_courses_sync} SET enabled = 0,productprice = '$courseprice',keytype = '$coursesynckeytype',keyprice = '$coursesynckeyprice' WHERE courseid = '$postcourseid'");
            }
        }

        if ($postcourse == 1) {
            $leeloocourse = $DB->get_record_sql('SELECT COUNT(*) as countcourse FROM {tool_leeloo_courses_sync} WHERE courseid = ' . $postcourseid . '');

            if ($leeloocourse->countcourse == 0) {
                $course = $DB->get_record_sql('SELECT fullname,summary FROM {course} where id = ' . $postcourseid);

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
                );

                if (!$output = $curl->post($url, $post, $options)) {
                    $error = get_string('nolicense', 'tool_leeloo_courses_sync');
                }
                $infoleeloo = json_decode($output);

                if ($infoleeloo->status == 'true') {
                    $productid = $infoleeloo->data->id;
                    $product_alias = $infoleeloo->data->product_alias;
                    $DB->execute("INSERT INTO {tool_leeloo_courses_sync} (courseid, productid, enabled, productprice,product_alias,keytype,keyprice)VALUES ('$postcourseid', '$productid', '1','$courseprice','$product_alias','$coursesynckeytype','$coursesynckeyprice')");
                }
            } else {

                $leeloodept = $DB->get_record_sql('SELECT productid FROM {tool_leeloo_courses_sync} WHERE courseid = ' . $postcourseid . '');

                $productid = $leeloodept->productid;
                $course = $DB->get_record_sql('SELECT fullname,summary FROM {course} where id = ' . $postcourseid);

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
                );

                if (!$output = $curl->post($url, $post, $options)) {
                    $error = get_string('nolicense', 'tool_leeloo_courses_sync');
                }
                $infoleeloo = json_decode($output);
                if ($infoleeloo->status == 'true') {
                    $DB->execute("UPDATE {tool_leeloo_courses_sync} SET enabled = 1,productprice = '$courseprice',keytype = '$coursesynckeytype',keyprice = '$coursesynckeyprice' WHERE courseid = '$postcourseid'");
                }
            }
        }
    }
}

$courses = $DB->get_records_sql('SELECT c.id,c.fullname,wd.enabled,wd.productprice,wd.keytype,wd.keyprice FROM {course} as c LEFT JOIN {tool_leeloo_courses_sync} as wd ON c.id = wd.courseid ORDER BY c.id ASC');

echo $OUTPUT->header();
echo $OUTPUT->heading_with_help(get_string('leeloo_courses_sync', 'tool_leeloo_courses_sync'), 'leeloo_courses_sync', 'tool_leeloo_courses_sync');
if (!empty($error)) {
    echo $OUTPUT->container($error, 'leeloo_courses_sync_myformerror');
}

if (!empty($courses)) {
    echo '<form method="post">
    <table class="sellcoursesynctable">
    <thead>
        <th>&nbsp;</th>
        <th>Course</th>
        <th>Price($)</th>
        <th>Key Allowed</th>
        <th>Key Price</th>
    </thead>';
    foreach ($courses as $course) {
        $courseid = $course->id;
        $coursefullname = $course->fullname;
        $courseenabled = $course->enabled;
        $courseproductprice = $course->productprice;
        $coursekeyprice = $course->keyprice;
        $coursekeytype = $course->keytype;
        if ($courseenabled == 1) {
            $checkbox_checked = 'checked';
            $pricestyle = '';
        } else {
            $checkbox_checked = '';
            $pricestyle = '';
        }
        echo '<tr>';
        echo "<td><input type='hidden' value='0' name='courses[$courseid]'><input $checkbox_checked id='course_$courseid' type='checkbox' name='courses[$courseid]' value='1'></td>";
        echo "<td><label for='course_$courseid'>$coursefullname</label></td>";
        echo "<td><input type='number' value='$courseproductprice' name='price[$courseid]' id='price_$courseid' $pricestyle></td>";

        $keys_select = "<select name='keytype[$courseid]'><option value='-1'>No</option>";
        if ($keysresponse->status == 'true') {
            foreach ($keysresponse->data->keys as $keytype) {
                if ($coursekeytype == $keytype->id) {
                    $selected_keytype = 'selected';
                } else {
                    $selected_keytype = '';
                }
                $keys_select .= "<option $selected_keytype value='$keytype->id'>$keytype->name</option>";
            }
        }
        $keys_select .= "</select>";

        echo "<td>$keys_select</td>";

        echo "<td><input type='number' value='$coursekeyprice' name='keyprice[$courseid]' id='price_$courseid' $pricestyle></td>";
        echo '</tr>';
    }
    echo '</table><button type="submit" value="Save and Create Departments">Submit</button></form>';
}

echo $OUTPUT->footer();