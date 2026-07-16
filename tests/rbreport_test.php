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
use core_user\reportbuilder\datasource\users;

/**
 * Unit tests for block_rbreport class.
 *
 * @package     block_rbreport
 * @author      Ruslan Kabalin
 * @covers      \block_rbreport
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rbreport_test extends advanced_testcase {
    public static function setUpBeforeClass(): void {
        global $CFG; // Required for CFG availability in require files.
        require_once(__DIR__ . '/../../moodleblock.class.php');
        require_once(__DIR__ . '/../../edit_form.php');
        require_once(__DIR__ . '/../block_rbreport.php');
        require_once(__DIR__ . '/../edit_form.php');
        parent::setUpBeforeClass();
    }

    /**
     * Test get_config_for_external method.
     */
    public function test_get_config_for_external(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        /** @var \core_reportbuilder_generator $rbgenerator */
        $rbgenerator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');

        // Create course and add rbreport block.
        $course = $this->getDataGenerator()->create_course();
        $block = $this->create_block($course);

        // Change block instance settings and save.
        if (\core_component::get_component_directory('tool_tenant')) {
            $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
            $report = $rbgenerator->create_report(['source' => users::class,
                'component' => 'tool_tenant', 'itemid' => $defaulttenantid, 'name' => 'R1', ]);
        } else {
            $report = $rbgenerator->create_report(['source' => users::class, 'name' => 'R1']);
        }
        $data = (object)[
            'title' => 'Block title',
            'corereport' => $report->get('id'),
            'layout' => constants::LAYOUT_CARDS,
            'chartlabelcolumn' => 2,
            'chartlineseries' => 'R1',
            'chartseriesnames' => 'R1 = Report one',
            'chartvaluecolumn' => 1,
            'chartseriescolumn' => 3,
            'chartsplitcolumn' => 4,
            'chartxaxislabel' => 'Users',
            'chartyaxislabel' => 'Count',
            'pagesize' => 10,
        ];
        $block->instance_config_save($data);

        // Load the block.
        $page = self::construct_page($course);
        $page->blocks->load_blocks();
        $blocks = $page->blocks->get_blocks_for_region($page->blocks->get_default_region());
        $block = end($blocks);

        // Test values.
        $config = $block->get_config_for_external();
        $this->assertEquals($data->title, $config->instance->title);
        $this->assertEquals($data->corereport, $config->instance->corereport);
        $this->assertEquals($data->layout, $config->instance->layout);
        $this->assertEquals($data->chartlabelcolumn, $config->instance->chartlabelcolumn);
        $this->assertEquals($data->chartlineseries, $config->instance->chartlineseries);
        $this->assertEquals($data->chartseriesnames, $config->instance->chartseriesnames);
        $this->assertEquals($data->chartvaluecolumn, $config->instance->chartvaluecolumn);
        $this->assertEquals($data->chartseriescolumn, $config->instance->chartseriescolumn);
        $this->assertEquals($data->chartsplitcolumn, $config->instance->chartsplitcolumn);
        $this->assertEquals($data->chartxaxislabel, $config->instance->chartxaxislabel);
        $this->assertEquals($data->chartyaxislabel, $config->instance->chartyaxislabel);
        $this->assertEquals($data->pagesize, $config->instance->pagesize);
    }

    /**
     * Test splitting a report into multiple charts.
     */
    public function test_split_report_into_multiple_charts(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->getDataGenerator()->create_user(['username' => 'splituser1']);
        $this->getDataGenerator()->create_user(['username' => 'splituser2']);
        $this->getDataGenerator()->create_user(['username' => 'splituser3']);

        /** @var \core_reportbuilder_generator $rbgenerator */
        $rbgenerator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        if (\core_component::get_component_directory('tool_tenant')) {
            $defaulttenantid = \tool_tenant\tenancy::get_default_tenant_id();
            $report = $rbgenerator->create_report([
                'source' => users::class,
                'component' => 'tool_tenant',
                'itemid' => $defaulttenantid,
                'name' => 'Split users',
            ]);
        } else {
            $report = $rbgenerator->create_report(['source' => users::class, 'name' => 'Split users']);
        }

        $course = $this->getDataGenerator()->create_course();
        $block = $this->create_block($course);
        $block->instance_config_save((object) [
            'title' => 'Split users block',
            'corereport' => $report->get('id'),
            'layout' => constants::LAYOUT_CHART,
            'charttype' => constants::CHARTTYPE_BAR,
            'chartlabelcolumn' => 0,
            'chartvaluecolumn' => 1,
            'chartseriescolumn' => -1,
            'chartsplitcolumn' => 1,
        ]);

        // Reload the block so the saved configuration is applied.
        $page = self::construct_page($course);
        $page->blocks->load_blocks();
        $blocks = $page->blocks->get_blocks_for_region($page->blocks->get_default_region());
        $block = end($blocks);

        $content = $block->get_content();
        $this->assertSame(4, substr_count($content->text, '<div class="container-fluid">'));
        $this->assertSame(4, substr_count($content->text, 'class="chart-area"'));
    }

    /**
     * Test chart labels, series renaming and combo line series.
     */
    public function test_build_chart_customisation(): void {
        $block = new class extends \block_rbreport {
            /**
             * Expose chart construction for testing.
             *
             * @param array $rows Normalized rows
             * @param array $reports Report metadata
             * @return \core\chart_base
             */
            public function create_chart(array $rows, array $reports): \core\chart_base {
                return $this->build_chart($rows, $reports, 1, true, 'Department A');
            }
        };
        $block->config = (object) [
            'charttype' => constants::CHARTTYPE_BAR,
            'chartseriesnames' => "Malformed\nYes = Suspended",
            'chartlineseries' => 'Suspended',
            'chartxaxislabel' => 'Users',
            'chartyaxislabel' => 'Count',
        ];
        $chart = $block->create_chart([
            [
                'reportkey' => 0,
                'index' => 1,
                'label' => 'One',
                'value' => 2.0,
                'group' => 'Yes',
                'rawsplit' => 'a',
                'split' => 'Department A',
            ],
        ], [
            0 => ['header' => 'Count', 'name' => 'Users', 'grouping' => true],
        ]);

        $this->assertSame('Department A', $chart->get_title());
        $this->assertSame('Users', $chart->get_xaxis()->get_label());
        $this->assertSame('Count', $chart->get_yaxis()->get_label());
        $this->assertSame('Suspended', $chart->get_series()[0]->get_label());
        $this->assertSame(\core\chart_series::TYPE_LINE, $chart->get_series()[0]->get_type());
    }

    /**
     * Creates an HTML block on a course.
     *
     * @param \stdClass $course Course object
     * @return \block_rbreport Block instance object
     */
    protected function create_block(\stdClass $course): \block_rbreport {
        $page = self::construct_page($course);
        $page->blocks->add_block_at_end_of_default_region('rbreport');

        // Load the block.
        $page = self::construct_page($course);
        $page->blocks->load_blocks();
        $blocks = $page->blocks->get_blocks_for_region($page->blocks->get_default_region());
        $block = end($blocks);
        return $block;
    }

    /**
     * Constructs a page object for the test course.
     *
     * @param \stdClass $course Moodle course object
     * @return \moodle_page Page object representing course view
     */
    protected static function construct_page(\stdClass $course): \moodle_page {
        $context = \context_course::instance($course->id);
        $page = new \moodle_page();
        $page->set_context($context);
        $page->set_course($course);
        $page->set_pagelayout('standard');
        $page->set_pagetype('course-view');
        $page->blocks->load_blocks();
        return $page;
    }
}
