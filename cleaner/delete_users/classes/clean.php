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
 * @package    cleaner_delete_users
 * @copyright  2015 Catalyst IT
 * @author     Nigel Cunningham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace cleaner_delete_users;

defined('MOODLE_INTERNAL') || die();

class clean extends \local_datacleaner\clean {
    const TASK = 'Removing old users';

    /**
     * Undelete a group of users
     *
     * There's an undelete_user function in Totara, but it only does one user at a time and
     * fires events that we don't care about.
     *
     * @param array $users Users who need to have their delete flag reset.
     */
    protected static function undelete_users(array $users) {
        global $DB;

        if (empty($users)) {
            return;
        }

        $userids = array_keys($users);
        $chunks = array_chunk($userids, 65000);
        foreach ($chunks as $chunk) {
            list($sql, $params) = $DB->get_in_or_equal($chunk);
            $DB->set_field_select('user', 'deleted', 0, 'id ' . $sql, $params);
        }
    }

    /**
     * Delete a group of users
     *
     * Based on the Ducere migration code originally written by Dima.
     *
     * @param array $users User IDs to delete.
     */
    private static function delete_users(array $users) {
        global $DB;

        if (empty($users)) {
            return;
        }

        // Clean up Assignment stuff.
        $userids = array_keys($users);
        $chunks = array_chunk($userids, 65000);
        foreach ($chunks as $chunk) {
            list($userinequal, $userparams) = $DB->get_in_or_equal($chunk);
            $userinequal = 'userid ' . $userinequal;

            $submissions = $DB->get_fieldset_select("assign_submission", "id", $userinequal, $userparams);
            if (!empty($submissions)) {
                // TODO: Actually delete the files.
                $DB->delete_records_list('assignsubmission_file', 'submission', $submissions);
                $DB->delete_records_list('assignsubmission_onlinetext', 'submission', $submissions);
            }

            $DB->delete_records_list('assign_submission', 'userid', $chunk);

            $grades = $DB->get_fieldset_select("assign_grades", "id", $userinequal, $userparams);
            if (!empty($grades)) {
                $DB->delete_records_list('assignfeedback_comments', 'grade', $grades);
                // TODO: Actually delete the files.
                $DB->delete_records_list('assignfeedback_file', 'grade', $grades);
                $DB->delete_records_list('assignfeedback_editpdf_annot', 'gradeid', $grades);
                $DB->delete_records_list('assignfeedback_editpdf_cmnt', 'gradeid', $grades);
            }

            $DB->delete_records_list('assign_grades', 'userid', $chunk);
            $DB->delete_records_list('assign_user_flags', 'userid', $chunk);
            $DB->delete_records_list('assign_user_mapping', 'userid', $chunk);
            $DB->delete_records_list('assignfeedback_editpdf_quick', 'userid', $chunk);

            // Clean up other tables that might be around and need it.
            $dbman = $DB->get_manager();

            foreach (array('userid' => array('local_messages_sent', 'block_leaderboard_data', 'block_leaderboard_points',
                            'assignment_submissions', 'block_totara_stats', 'config_log', 'course_completion_crit_compl',
                            'course_completions', 'course_modules_completion', 'facetoface_signups',
                            'grade_grades', 'grade_grades_history', 'log', 'logstore_standard_log', 'message_contacts',
                            'my_pages', 'post', 'prog_completion', 'prog_pos_assignment', 'prog_user_assignment',
                            'report_builder_saved', 'role_assignments', 'scorm_scoes_track', 'sessions', 'stats_user_daily',
                            'stats_user_monthly', 'stats_user_weekly'
                            ),
                        'useridfrom' => array('message', 'message_read'),
                        'useridto' => array('message', 'message_read')) as $field => $tables) {
                foreach ($tables as $table) {
                    if ($dbman->table_exists($table)) {
                        $DB->delete_records_list($table, $field, $chunk);
                    }
                }
            }
        }

        // This transaction is purely for speed, hence the committing in the middle of the loop.
        $transaction = $DB->start_delegated_transaction();

        $index = 0;
        $numusers = count($users);
        $steps = max($numusers / 20, 5);
        $interval = $numusers / $steps;

        foreach ($users as $user) {
            delete_user($user);

            $index ++;
            if (!($index % $interval)) {
                self::update_status(self::TASK, $index, $numusers);
            }
        }

        $transaction->allow_commit();

        // Finally clean up user table.
        foreach ($chunks as $chunk) {
            $DB->delete_records_list('user', 'id', $chunk);
        }
    }

    /**
     * Do the hard work of cleaning up users.
     */
    static public function execute() {
        // Get the settings, handling the case where new ones (dev) haven't been set yet.
        $config = get_config('cleaner_delete_users');

        $criteria = self::get_criteria($config);

        // Any users need undeleting before we properly delete them?
        $criteria['deleted'] = true;
        $users = self::get_users($criteria);

        self::undelete_users($users);

        unset($criteria['deleted']);

        // Get on with the real work!
        $users = self::get_users($criteria);
        $numusers = count($users);

        if ($numusers) {
            self::update_status(self::TASK, 0, $numusers);

            self::delete_users($users);

            self::update_status(self::TASK, $numusers, $numusers);
        }

        echo 'Deleted ' . count($users) . " users.\n";
    }
}
