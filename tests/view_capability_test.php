<?php
// This file is part of the block_rbreport plugin for Moodle - http://moodle.org/
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

namespace block_rbreport;

use advanced_testcase;

/**
 * Tests for the block view capability.
 *
 * @package    block_rbreport
 * @copyright  2026 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class view_capability_test extends advanced_testcase {
    /**
     * Tests that block content is hidden from users without the view capability.
     */
    public function test_view_capability(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        require_once($CFG->libdir . '/blocklib.php');

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        // The plugin's own generator does not create block instances, so insert one directly.
        $blockrecord = (object) [
            'blockname' => 'rbreport',
            'parentcontextid' => $coursecontext->id,
            'showinsubcontexts' => 0,
            'pagetypepattern' => 'course-view-*',
            'defaultregion' => 'side-pre',
            'defaultweight' => 0,
            'configdata' => '',
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $blockrecord->id = $DB->insert_record('block_instances', $blockrecord);
        $blockcontext = \context_block::instance($blockrecord->id);
        $block = block_instance_by_id($blockrecord->id);

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        $this->assertFalse(has_capability('block/rbreport:view', $blockcontext));
        $this->assertSame('', $block->get_content()->text);

        $editingteacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($editingteacher);
        $this->assertTrue(has_capability('block/rbreport:view', $blockcontext));
    }
}
