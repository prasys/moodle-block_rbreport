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

use block_rbreport\constants;
use core_reportbuilder\local\models\report;
use core_reportbuilder\permission;

/**
 * Form for editing Custom report block instances.
 *
 * @package    block_rbreport
 * @author     Marina Glancy
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_rbreport_edit_form extends block_edit_form {
    /**
     * Block settings definitions
     *
     * @param MoodleQuickForm $mform
     * @throws coding_exception
     */
    protected function specific_definition($mform) {
        // Fields for editing Custom report block title and contents.
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        $mform->addElement('text', 'config_title', get_string('configtitle', 'block_rbreport'));
        $mform->setType('config_title', PARAM_TEXT);

        $mform->addElement('autocomplete', 'config_corereport', get_string('configreport', 'block_rbreport'), [], [
            'ajax' => 'block_rbreport/form_report_selector',
            'multiple' => true,
            'data-pagetype' => $this->page->pagetype,
            'data-subpage' => $this->page->subpage,
            'data-pageurl' => $this->page->url->out(false),
            'valuehtmlcallback' => static function (int $reportid): ?string {
                $persistent = report::get_record(['id' => $reportid]);
                if ($persistent !== false && permission::can_view_report($persistent)) {
                    return $persistent->get_formatted_name();
                }
                return null;
            },
        ]);
        $mform->addHelpButton('config_corereport', 'configreport', 'block_rbreport');
        $mform->addRule('config_corereport', null, 'required', null, 'client');

        $options = [
            constants::LAYOUT_ADAPTIVE => get_string('displayadaptive', 'block_rbreport'),
            constants::LAYOUT_CARDS => get_string('displayascards', 'block_rbreport'),
            constants::LAYOUT_TABLE => get_string('displayastable', 'block_rbreport'),
            constants::LAYOUT_CHART => get_string('displayaschart', 'block_rbreport'),
        ];
        $mform->addElement(
            'select',
            'config_layout',
            get_string('configlayout', 'block_rbreport'),
            $options,
        );
        $mform->addHelpButton('config_layout', 'configlayout', 'block_rbreport');

        $chartoptions = [
            constants::CHARTTYPE_BAR => get_string('charttypebar', 'block_rbreport'),
            constants::CHARTTYPE_BAR_STACKED => get_string('charttypebarstacked', 'block_rbreport'),
            constants::CHARTTYPE_BAR_HORIZONTAL => get_string('charttypebarhorizontal', 'block_rbreport'),
            constants::CHARTTYPE_LINE => get_string('charttypeline', 'block_rbreport'),
            constants::CHARTTYPE_PIE => get_string('charttypepie', 'block_rbreport'),
            constants::CHARTTYPE_DOUGHNUT => get_string('charttypedoughnut', 'block_rbreport'),
        ];
        $mform->addElement('select', 'config_charttype', get_string('configcharttype', 'block_rbreport'), $chartoptions);
        $mform->hideIf('config_charttype', 'config_layout', 'ne', constants::LAYOUT_CHART);

        $columnoptions = [];
        $configuredreports = $this->block->config->corereport ?? [];
        $configuredreports = is_array($configuredreports) ? $configuredreports : [$configuredreports];
        $reportid = (int) reset($configuredreports);
        if ($reportid) {
            try {
                $report = \core_reportbuilder\manager::get_report_from_id($reportid);
                if (permission::can_view_report($report->get_report_persistent())) {
                    foreach ($report->get_active_columns_by_alias() as $column) {
                        $heading = $column->get_persistent()->get_formatted_heading($report->get_context());
                        $columnoptions[] = $heading !== '' ? $heading : $column->get_title();
                    }
                }
            } catch (\Throwable $e) {
                $columnoptions = [];
            }
        }
        if (!$columnoptions) {
            for ($index = 0; $index < 8; $index++) {
                $columnoptions[$index] = get_string('columnnumber', 'block_rbreport', $index + 1);
            }
        }

        $mform->addElement(
            'select',
            'config_chartlabelcolumn',
            get_string('configchartlabelcolumn', 'block_rbreport'),
            $columnoptions,
        );
        $mform->setDefault('config_chartlabelcolumn', 0);
        $mform->setType('config_chartlabelcolumn', PARAM_INT);
        $mform->hideIf('config_chartlabelcolumn', 'config_layout', 'ne', constants::LAYOUT_CHART);

        $mform->addElement(
            'select',
            'config_chartvaluecolumn',
            get_string('configchartvaluecolumn', 'block_rbreport'),
            $columnoptions,
        );
        $mform->setDefault('config_chartvaluecolumn', 1);
        $mform->setType('config_chartvaluecolumn', PARAM_INT);
        $mform->hideIf('config_chartvaluecolumn', 'config_layout', 'ne', constants::LAYOUT_CHART);

        $seriescolumnoptions = [-1 => get_string('seriescolumnnone', 'block_rbreport')] + $columnoptions;
        $mform->addElement(
            'select',
            'config_chartseriescolumn',
            get_string('configchartseriescolumn', 'block_rbreport'),
            $seriescolumnoptions,
        );
        $mform->setDefault('config_chartseriescolumn', -1);
        $mform->setType('config_chartseriescolumn', PARAM_INT);
        $mform->addHelpButton('config_chartseriescolumn', 'configchartseriescolumn', 'block_rbreport');
        $mform->hideIf('config_chartseriescolumn', 'config_layout', 'ne', constants::LAYOUT_CHART);
        $mform->hideIf('config_chartseriescolumn', 'config_charttype', 'eq', constants::CHARTTYPE_PIE);
        $mform->hideIf('config_chartseriescolumn', 'config_charttype', 'eq', constants::CHARTTYPE_DOUGHNUT);

        $mform->addElement('text', 'config_chartxaxislabel', get_string('configchartxaxislabel', 'block_rbreport'));
        $mform->setType('config_chartxaxislabel', PARAM_TEXT);
        $mform->hideIf('config_chartxaxislabel', 'config_layout', 'ne', constants::LAYOUT_CHART);

        $mform->addElement('text', 'config_chartyaxislabel', get_string('configchartyaxislabel', 'block_rbreport'));
        $mform->setType('config_chartyaxislabel', PARAM_TEXT);
        $mform->hideIf('config_chartyaxislabel', 'config_layout', 'ne', constants::LAYOUT_CHART);

        $mform->addElement('textarea', 'config_chartseriesnames', get_string('configchartseriesnames', 'block_rbreport'));
        $mform->setType('config_chartseriesnames', PARAM_TEXT);
        $mform->addHelpButton('config_chartseriesnames', 'configchartseriesnames', 'block_rbreport');
        $mform->hideIf('config_chartseriesnames', 'config_layout', 'ne', constants::LAYOUT_CHART);

        $mform->addElement('text', 'config_chartlineseries', get_string('configchartlineseries', 'block_rbreport'));
        $mform->setType('config_chartlineseries', PARAM_TEXT);
        $mform->addHelpButton('config_chartlineseries', 'configchartlineseries', 'block_rbreport');
        $mform->hideIf('config_chartlineseries', 'config_layout', 'ne', constants::LAYOUT_CHART);
        $mform->hideIf(
            'config_chartlineseries',
            'config_charttype',
            'in',
            constants::CHARTTYPE_LINE . '|' . constants::CHARTTYPE_PIE . '|' . constants::CHARTTYPE_DOUGHNUT . '|' .
                constants::CHARTTYPE_BAR_HORIZONTAL,
        );

        $splitcolumnoptions = [-1 => get_string('splitcolumnnone', 'block_rbreport')] + $columnoptions;
        $mform->addElement(
            'select',
            'config_chartsplitcolumn',
            get_string('configchartsplitcolumn', 'block_rbreport'),
            $splitcolumnoptions,
        );
        $mform->setDefault('config_chartsplitcolumn', -1);
        $mform->setType('config_chartsplitcolumn', PARAM_INT);
        $mform->addHelpButton('config_chartsplitcolumn', 'configchartsplitcolumn', 'block_rbreport');
        $mform->hideIf('config_chartsplitcolumn', 'config_layout', 'ne', constants::LAYOUT_CHART);

        $mform->addElement(
            'advcheckbox',
            'config_chartexcludeempty',
            get_string('configchartexcludeempty', 'block_rbreport'),
        );
        $mform->addHelpButton('config_chartexcludeempty', 'configchartexcludeempty', 'block_rbreport');
        $mform->hideIf('config_chartexcludeempty', 'config_layout', 'ne', constants::LAYOUT_CHART);

        $mform->addElement(
            'advcheckbox',
            'config_chartexcludezero',
            get_string('configchartexcludezero', 'block_rbreport'),
        );
        $mform->hideIf('config_chartexcludezero', 'config_layout', 'ne', constants::LAYOUT_CHART);

        $mform->addElement('advcheckbox', 'config_cumulative', get_string('configcumulative', 'block_rbreport'));
        $mform->hideIf('config_cumulative', 'config_layout', 'ne', constants::LAYOUT_CHART);

        $mform->addElement('advcheckbox', 'config_chartpiepercent', get_string('configchartpiepercent', 'block_rbreport'));
        $mform->hideIf('config_chartpiepercent', 'config_layout', 'ne', constants::LAYOUT_CHART);

        $mform->addElement('advcheckbox', 'config_setminmax', get_string('configsetminmax', 'block_rbreport'));
        $mform->hideIf('config_setminmax', 'config_layout', 'ne', constants::LAYOUT_CHART);

        $mform->addElement('text', 'config_chartmin', get_string('configchartmin', 'block_rbreport'));
        $mform->setType('config_chartmin', PARAM_FLOAT);
        $mform->hideIf('config_chartmin', 'config_setminmax', 'ne', 1);

        $mform->addElement('text', 'config_chartmax', get_string('configchartmax', 'block_rbreport'));
        $mform->setType('config_chartmax', PARAM_FLOAT);
        $mform->hideIf('config_chartmax', 'config_setminmax', 'ne', 1);

        $mform->addElement('advcheckbox', 'config_setstepsize', get_string('configsetstepsize', 'block_rbreport'));
        $mform->hideIf('config_setstepsize', 'config_layout', 'ne', constants::LAYOUT_CHART);

        $mform->addElement('text', 'config_chartstepsize', get_string('configchartstepsize', 'block_rbreport'));
        $mform->setType('config_chartstepsize', PARAM_INT);
        $mform->hideIf('config_chartstepsize', 'config_setstepsize', 'ne', 1);

        $cardsarray = [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 10 => 10, 25 => 25, 50 => 50];
        $mform->addElement('select', 'config_pagesize', get_string('entriesperpage', 'block_rbreport'), $cardsarray);
        $mform->setDefault('config_pagesize', 5);
        $mform->setType('config_pagesize', PARAM_INT);
    }

    /**
     * Display the configuration form when block is being added to the page
     *
     * @return bool
     */
    public static function display_form_when_adding(): bool {
        return true;
    }
}
