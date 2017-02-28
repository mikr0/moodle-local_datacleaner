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
 * Settings for Environment matrix.
 *
 * @package    cleaner_environment_matrix
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  2017 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('cleaner_environment_matrix');

$searchresult = []; // key => 'id, name, value'
$configitems = [];  // key => 'envid, config, value'
$environments = ['e1', 'e2', 'e3']; // key => 'environement, wwwroot'

// Lookup possible {config} table entries.
$search = optional_param('search', null, PARAM_TEXT);
if (!empty($search)) {
    $searchresult = \cleaner_environment_matrix\local\matrix::search($search);
//    var_dump($searchresult);
}

$customdata = [
    'searchitems' => $searchresult,
    'configitems' => $configitems,
    'environments' => $environments,
];

$post = new moodle_url('/local/datacleaner/cleaner/environment_matrix/index.php');
$matrix = new \cleaner_environment_matrix\form\matrix($post, $customdata);

echo $OUTPUT->header();

$matrix->display();

echo $OUTPUT->footer();
