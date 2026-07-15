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
use core_reportbuilder\table\custom_report_table_view;
use core_reportbuilder\table\custom_report_table_view_filterset;
use core_table\local\filter\integer_filter;

/**
 * Custom report block.
 *
 * @package    block_rbreport
 * @author     Marina Glancy
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_rbreport extends block_base {
    /** @var stdClass $content */
    public $content = null;

    /** @var string */
    protected $statusmessage = '';

    /**
     * Initializes class member variables.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_rbreport');
    }

    /**
     * Returns the block contents.
     *
     * @return stdClass The block contents.
     */
    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';

        if ($report = $this->get_core_report()) {
            $report->set_default_per_page(((int) ($this->config->pagesize ?? 0)) ?: $report->get_default_per_page());

            // Add custom attributes to force cards/table view depending on settings.
            $configlayout = $this->config->layout ?? '';
            if ($configlayout === constants::LAYOUT_CARDS) {
                $report->add_attributes(['data-force-card' => '']);
            }
            if ($configlayout === constants::LAYOUT_TABLE) {
                $report->add_attributes(['data-force-table' => '']);
            }
            if ($configlayout === constants::LAYOUT_CHART) {
                $report->add_attributes(['data-force-chart' => '']);
                $this->content->text = $this->chart_html();
            } else {
                $outputpage = new \core_reportbuilder\output\custom_report($report->get_report_persistent(), false);
                $output = $this->page->get_renderer('core_reportbuilder');
                $export = $outputpage->export_for_template($output);
                $outputhtml = $output->render_from_template('core_reportbuilder/report', $export);
                $this->content->text = html_writer::div($outputhtml);
                $fullreporturl = new moodle_url('/reportbuilder/view.php', [
                    'id' => $report->get_report_persistent()->get('id'),
                ]);
                $this->content->footer = html_writer::link(
                    $fullreporturl,
                    get_string('gotofullreport', 'block_rbreport'),
                );
            }
        } else {
            $this->content->text = $this->user_can_edit() && $this->page->user_is_editing() ? $this->statusmessage : '';
        }

        return $this->content;
    }

    /**
     * Defines configuration data.
     *
     * The function is called immediatly after init().
     */
    public function specialization() {
        // Load user defined title and make sure it's never empty.
        if ((string) $this->config?->title !== '') {
            $this->title = $this->config->title;
        } else if ($report = $this->get_core_report()) {
            $this->title = $report->get_report_persistent()->get_formatted_name();
        }

        if (!empty($this->config?->corereport) && !$this->get_core_report()) {
            $this->statusmessage = html_writer::div(get_string('errormessage', 'block_rbreport'), 'alert alert-danger');
        } else {
            $this->statusmessage = html_writer::div(get_string('reportnotsetmessage', 'block_rbreport'));
        }
    }

    /**
     * Sets the applicable formats for the block.
     *
     * @return string[] Array of pages and permissions.
     */
    public function applicable_formats() {
        return ['all' => true];
    }

    /**
     * Allow multiple instances
     * @return bool
     */
    public function instance_allow_multiple() {
        return true;
    }

    /**
     * Return the plugin config settings for external functions
     *
     * @return stdClass
     */
    public function get_config_for_external() {
        $instanceconfigs = !empty($this->config) ? $this->config : new stdClass();

        return (object) [
            'instance' => $instanceconfigs,
            'plugin' => new stdClass(),
        ];
    }

    /**
     * Return the configured report IDs in their configured order.
     *
     * @return int[]
     */
    private function get_report_ids(): array {
        $configured = $this->config?->corereport ?? [];
        $reportids = is_array($configured) ? $configured : [$configured];

        return array_values(array_filter(array_map('intval', $reportids)));
    }

    /**
     * Get a configured report.
     *
     * @param int $key Position of the report in the configured report list
     * @return \core_reportbuilder\local\report\base|null
     */
    protected function get_core_report(int $key = 0): ?\core_reportbuilder\local\report\base {
        $reportid = $this->get_report_ids()[$key] ?? 0;
        if ($reportid) {
            try {
                $report = \core_reportbuilder\manager::get_report_from_id($reportid);
                if (\core_reportbuilder\permission::can_view_report($report->get_report_persistent())) {
                    return $report;
                }
            } catch (moodle_exception $e) {
                return null;
            }
        }
        return null;
    }

    /**
     * Render the configured reports as a chart.
     *
     * @return string
     */
    protected function chart_html(): string {
        global $OUTPUT;

        $charttype = $this->config->charttype ?? constants::CHARTTYPE_BAR;
        $cumulative = !empty($this->config->cumulative ?? false);
        $chartpiepercent = !empty($this->config->chartpiepercent ?? false);
        $setminmax = !empty($this->config->setminmax ?? false);
        $chartmin = $this->config->chartmin ?? null;
        $chartmax = $this->config->chartmax ?? null;
        $setstepsize = !empty($this->config->setstepsize ?? false);
        $chartstepsize = $this->config->chartstepsize ?? null;
        $labelidx = (int) ($this->config->chartlabelcolumn ?? 0);
        $valueidx = (int) ($this->config->chartvaluecolumn ?? 1);
        $groupidx = (int) ($this->config->chartseriescolumn ?? -1);
        switch ($charttype) {
            case constants::CHARTTYPE_BAR:
                $chart = new core\chart_bar();
                break;
            case constants::CHARTTYPE_BAR_STACKED:
                $chart = new core\chart_bar();
                $chart->set_stacked(true);
                break;
            case constants::CHARTTYPE_BAR_HORIZONTAL:
                $chart = new core\chart_bar();
                $chart->set_horizontal(true);
                break;
            case constants::CHARTTYPE_LINE:
                $chart = new core\chart_line();
                break;
            case constants::CHARTTYPE_PIE:
                $chart = new core\chart_pie();
                break;
            case constants::CHARTTYPE_DOUGHNUT:
                $chart = new core\chart_pie();
                $chart->set_doughnut(true);
                break;
            default:
                $chart = new core\chart_bar();
        }

        if ($setminmax) {
            if (!empty($chartmin)) {
                $yaxis = $chart->get_yaxis(0, true);
                $yaxis->set_min($chartmin);
            }
            if (!empty($chartmax)) {
                $yaxis = $chart->get_yaxis(0, true);
                $yaxis->set_max($chartmax);
            }
        }
        if ($setstepsize && !empty($chartstepsize)) {
            $yaxis = $chart->get_yaxis(0, true);
            $yaxis->set_stepsize($chartstepsize);
        }

        $allseries = [];
        $labels = [];
        $headers = [];
        $groupedserieskeys = [];
        $reportids = $this->get_report_ids();
        foreach ($reportids as $key => $id) {
            $report = $this->get_core_report($key);
            if ($report === null) {
                continue;
            }

            // Store the pagesize in the table filterset so it is available between AJAX requests.
            $filterset = new custom_report_table_view_filterset();
            $filterset->add_filter(new integer_filter('pagesize', null, [(int) ($this->config->pagesize ?? 0)]));

            $table = custom_report_table_view::create($report->get_report_persistent()->get('id'));
            $table->set_filterset($filterset);
            $table->pagesize = 0;
            $table->setup();
            $table->query_db(0);

            $columns = array_keys($report->get_active_columns_by_alias());
            $columncount = count($columns);
            $reportlabelidx = $labelidx >= 0 && $labelidx < $columncount ? $labelidx : 0;
            $reportvalueidx = $valueidx >= 0 && $valueidx < $columncount ? $valueidx : min(1, $columncount - 1);
            $reportgroupidx = $groupidx >= 0 && $groupidx < $columncount ? $groupidx : -1;
            $grouping = $reportgroupidx >= 0 && $charttype !== constants::CHARTTYPE_PIE &&
                $charttype !== constants::CHARTTYPE_DOUGHNUT;
            $series = [];
            $serieskey = count($allseries);
            if (($charttype === constants::CHARTTYPE_PIE || $charttype === constants::CHARTTYPE_DOUGHNUT) &&
                    $chartpiepercent) {
                $total = 0;
                $data = [];
                foreach ($table->rawdata as $row) {
                    $arrayrow = (array) $row;
                    $formattedrow = $table->format_row($row);
                    $value = floatval(str_replace(',', '.', $formattedrow[$columns[$reportvalueidx]]));
                    $total += $value;
                    $data[] = $arrayrow;
                }
                foreach ($data as $arrayrow) {
                    $index = $arrayrow[$columns[$reportlabelidx]];
                    $formattedrow = $table->format_row((object) $arrayrow);
                    $label = strip_tags($formattedrow[$columns[$reportlabelidx]]);
                    $value = floatval(str_replace(',', '.', $formattedrow[$columns[$reportvalueidx]]));
                    $series[$index] = floatval(number_format(($value / $total) * 100, 2));
                    if (!isset($labels[$index])) {
                        $labels[$index] = $label;
                    }
                    if ($serieskey > 0 && !isset($allseries[$serieskey - 1][$index])) {
                        $allseries[$serieskey - 1][$index] = 0;
                    }
                }
                $headers[] = $table->headers[$reportvalueidx];
                $allseries[$serieskey] = $series;
            } else if ($grouping) {
                $reportseries = [];
                foreach ($table->rawdata as $row) {
                    $arrayrow = (array) $row;
                    $index = $arrayrow[$columns[$reportlabelidx]];
                    $formattedrow = $table->format_row($row);
                    $label = strip_tags($formattedrow[$columns[$reportlabelidx]]);
                    $value = floatval(str_replace(',', '.', $formattedrow[$columns[$reportvalueidx]]));
                    $groupname = strip_tags($formattedrow[$columns[$reportgroupidx]]);
                    $groupname = $groupname === '' ? '-' : $groupname;
                    $reportseries[$groupname][$index] = ($reportseries[$groupname][$index] ?? 0) + $value;
                    if (!isset($labels[$index])) {
                        $labels[$index] = $label;
                    }
                }
                foreach ($reportseries as $groupname => $groupseries) {
                    $serieskey = count($allseries);
                    $header = $groupname;
                    if (count($reportids) > 1) {
                        $header = $report->get_report_persistent()->get_formatted_name() . ': ' . $groupname;
                    }
                    $headers[$serieskey] = $header;
                    $allseries[$serieskey] = $groupseries;
                    $groupedserieskeys[$serieskey] = true;
                }
            } else {
                foreach ($table->rawdata as $row) {
                    $arrayrow = (array) $row;
                    $index = $arrayrow[$columns[$reportlabelidx]];
                    $formattedrow = $table->format_row($row);
                    $label = strip_tags($formattedrow[$columns[$reportlabelidx]]);
                    $value = floatval(str_replace(',', '.', $formattedrow[$columns[$reportvalueidx]]));
                    if ($cumulative && $index > 0) {
                        $series[$index] = end($series) + $value;
                    } else {
                        $series[$index] = $value;
                    }
                    if (!isset($labels[$index])) {
                        $labels[$index] = $label;
                    }
                }
                $headers[] = $table->headers[$reportvalueidx];
                $allseries[$serieskey] = $series;
            }
        }

        ksort($labels);
        $lastlabel = null;
        foreach ($labels as $labelkey => $label) {
            foreach ($allseries as $serieskey => $series) {
                if (!isset($series[$labelkey])) {
                    if ($cumulative && !isset($groupedserieskeys[$serieskey]) && !empty($lastlabel)) {
                        $lastkey = null;
                        foreach ($series as $key => $value) {
                            if ($key < $labelkey) {
                                $lastkey = $key;
                            } else {
                                break;
                            }
                        }
                        if ($lastkey) {
                            $allseries[$serieskey][$labelkey] = $series[$lastkey];
                        } else {
                            $allseries[$serieskey][$labelkey] = 0;
                        }
                    } else {
                        $allseries[$serieskey][$labelkey] = 0;
                    }
                }
                $lastlabel = $labelkey;
            }
        }
        foreach ($allseries as $key => $series) {
            ksort($series);
            if ($cumulative && isset($groupedserieskeys[$key])) {
                $runningtotal = 0;
                foreach ($series as $labelkey => $value) {
                    $runningtotal += $value;
                    $series[$labelkey] = $runningtotal;
                }
            }
            $chart->add_series(new core\chart_series($headers[$key], array_values($series)));
        }

        $chart->set_labels(array_values($labels));
        return '<div class="container-fluid">' . $OUTPUT->render_chart($chart) . '</div>';
    }
}
