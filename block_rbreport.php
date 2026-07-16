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
    /** Maximum number of charts rendered when splitting by a column. */
    public const MAX_SPLIT_CHARTS = 12;

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
        $labelidx = (int) ($this->config->chartlabelcolumn ?? 0);
        $valueidx = (int) ($this->config->chartvaluecolumn ?? 1);
        $groupidx = (int) ($this->config->chartseriescolumn ?? -1);
        $splitidx = (int) ($this->config->chartsplitcolumn ?? -1);
        $chartexcludeempty = !empty($this->config->chartexcludeempty ?? false);
        $chartexcludezero = !empty($this->config->chartexcludezero ?? false);
        $splitting = $splitidx >= 0;
        $reportids = $this->get_report_ids();
        $reports = [];
        $buckets = $splitting ? [] : ['' => ['name' => '', 'rows' => []]];

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
            $reports[$key] = [
                'header' => $table->headers[$reportvalueidx],
                'name' => $report->get_report_persistent()->get_formatted_name(),
                'grouping' => $reportgroupidx >= 0 && $charttype !== constants::CHARTTYPE_PIE &&
                    $charttype !== constants::CHARTTYPE_DOUGHNUT,
            ];
            $reportsplitidx = $splitidx >= 0 && $splitidx < $columncount ? $splitidx : -1;
            if ($reportsplitidx < 0 && !isset($buckets[''])) {
                $buckets[''] = ['name' => '', 'rows' => []];
            }

            foreach ($table->rawdata as $row) {
                $arrayrow = (array) $row;
                $formattedrow = $table->format_row($row);
                $rawsplit = $reportsplitidx >= 0 ? $arrayrow[$columns[$reportsplitidx]] : '';
                $formattedsplit = $reportsplitidx >= 0 ? strip_tags($formattedrow[$columns[$reportsplitidx]]) : '';
                $formattedlabel = strip_tags($formattedrow[$columns[$reportlabelidx]]);
                $formattedvalue = floatval(str_replace(',', '.', $formattedrow[$columns[$reportvalueidx]]));
                $formattedgroup = $reportgroupidx >= 0
                    ? strip_tags($formattedrow[$columns[$reportgroupidx]]) : '';

                if ($chartexcludeempty && ($formattedlabel === '' ||
                        ($reports[$key]['grouping'] && $formattedgroup === '') ||
                        ($reportsplitidx >= 0 && $formattedsplit === ''))) {
                    continue;
                }
                if ($chartexcludezero && $formattedvalue === 0.0) {
                    continue;
                }

                if (!isset($buckets[$rawsplit])) {
                    $buckets[$rawsplit] = ['name' => $formattedsplit, 'rows' => []];
                }
                $buckets[$rawsplit]['rows'][] = [
                    'reportkey' => $key,
                    'reportname' => $reports[$key]['name'],
                    'header' => $reports[$key]['header'],
                    'index' => $arrayrow[$columns[$reportlabelidx]],
                    'label' => $formattedlabel,
                    'value' => $formattedvalue,
                    'group' => $formattedgroup,
                    'rawsplit' => $rawsplit,
                    'split' => $formattedsplit,
                ];
            }
        }

        ksort($buckets);
        $bucketcount = count($buckets);
        $html = '';
        foreach (array_slice($buckets, 0, self::MAX_SPLIT_CHARTS, true) as $bucket) {
            $chart = $this->build_chart($bucket['rows'], $reports, count($reportids), $splitting, $bucket['name']);
            $html .= '<div class="container-fluid">' . $OUTPUT->render_chart($chart) . '</div>';
        }
        if ($bucketcount > self::MAX_SPLIT_CHARTS) {
            $html .= html_writer::div(
                get_string('toomanycharts', 'block_rbreport', $bucketcount),
                'alert alert-warning',
            );
        }

        return $html;
    }

    /**
     * Build a chart for a normalized set of report rows.
     *
     * @param array $rows Normalized report rows
     * @param array $reports Report metadata, keyed by configured report position
     * @param int $reportcount Number of configured reports
     * @param bool $splitting Whether the chart is one of multiple split charts
     * @param string $splitname Formatted split value
     * @return \core\chart_base
     */
    protected function build_chart(
        array $rows,
        array $reports,
        int $reportcount,
        bool $splitting = false,
        string $splitname = '',
    ): \core\chart_base {
        $charttype = $this->config->charttype ?? constants::CHARTTYPE_BAR;
        $cumulative = !empty($this->config->cumulative ?? false);
        $chartpiepercent = !empty($this->config->chartpiepercent ?? false);
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

        if (!empty($this->config->setminmax ?? false)) {
            if (!empty($this->config->chartmin ?? null)) {
                $yaxis = $chart->get_yaxis(0, true);
                $yaxis->set_min($this->config->chartmin);
            }
            if (!empty($this->config->chartmax ?? null)) {
                $yaxis = $chart->get_yaxis(0, true);
                $yaxis->set_max($this->config->chartmax);
            }
        }
        if (!empty($this->config->setstepsize ?? false) && !empty($this->config->chartstepsize ?? null)) {
            $yaxis = $chart->get_yaxis(0, true);
            $yaxis->set_stepsize($this->config->chartstepsize);
        }
        if ((string) ($this->config->chartxaxislabel ?? '') !== '') {
            $chart->get_xaxis(0, true)->set_label($this->config->chartxaxislabel);
        }
        if ((string) ($this->config->chartyaxislabel ?? '') !== '') {
            $chart->get_yaxis(0, true)->set_label($this->config->chartyaxislabel);
        }
        if ($splitting) {
            $chart->set_title($splitname === '' ? '-' : $splitname);
        }

        $allseries = [];
        $labels = [];
        $headers = [];
        $groupedserieskeys = [];
        foreach ($reports as $key => $report) {
            $reportrows = array_filter($rows, static fn(array $row): bool => $row['reportkey'] === $key);
            $series = [];
            $serieskey = count($allseries);
            if (($charttype === constants::CHARTTYPE_PIE || $charttype === constants::CHARTTYPE_DOUGHNUT) &&
                    $chartpiepercent) {
                $total = 0;
                foreach ($reportrows as $row) {
                    $total += $row['value'];
                }
                foreach ($reportrows as $row) {
                    $series[$row['index']] = floatval(number_format(($row['value'] / $total) * 100, 2));
                    if (!isset($labels[$row['index']])) {
                        $labels[$row['index']] = $row['label'];
                    }
                    if ($serieskey > 0 && !isset($allseries[$serieskey - 1][$row['index']])) {
                        $allseries[$serieskey - 1][$row['index']] = 0;
                    }
                }
                $headers[] = $report['header'];
                $allseries[$serieskey] = $series;
            } else if ($report['grouping']) {
                $reportseries = [];
                foreach ($reportrows as $row) {
                    $groupname = $row['group'];
                    $groupname = $groupname === '' ? '-' : $groupname;
                    $reportseries[$groupname][$row['index']] =
                        ($reportseries[$groupname][$row['index']] ?? 0) + $row['value'];
                    if (!isset($labels[$row['index']])) {
                        $labels[$row['index']] = $row['label'];
                    }
                }
                foreach ($reportseries as $groupname => $groupseries) {
                    $serieskey = count($allseries);
                    $header = $groupname;
                    if ($reportcount > 1) {
                        $header = $report['name'] . ': ' . $groupname;
                    }
                    $headers[$serieskey] = $header;
                    $allseries[$serieskey] = $groupseries;
                    $groupedserieskeys[$serieskey] = true;
                }
            } else {
                foreach ($reportrows as $row) {
                    if ($cumulative && $row['index'] > 0) {
                        $series[$row['index']] = end($series) + $row['value'];
                    } else {
                        $series[$row['index']] = $row['value'];
                    }
                    if (!isset($labels[$row['index']])) {
                        $labels[$row['index']] = $row['label'];
                    }
                }
                $headers[] = $report['header'];
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
            $originalheader = $headers[$key];
            $header = $this->get_chart_series_name($originalheader);
            $seriesobj = new core\chart_series($header, array_values($series));
            if (($charttype === constants::CHARTTYPE_BAR || $charttype === constants::CHARTTYPE_BAR_STACKED) &&
                    $this->is_chart_line_series($originalheader, $header)) {
                $seriesobj->set_type(\core\chart_series::TYPE_LINE);
            }
            $chart->add_series($seriesobj);
        }

        $chart->set_labels(array_values($labels));
        return $chart;
    }

    /**
     * Apply a configured series name mapping.
     *
     * @param string $originalname Original series name
     * @return string
     */
    private function get_chart_series_name(string $originalname): string {
        $lines = preg_split('/\R/', (string) ($this->config->chartseriesnames ?? ''));
        foreach ($lines as $line) {
            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }
            [$original, $new] = array_map('trim', $parts);
            if ($original !== '' && $new !== '' && $original === $originalname) {
                return $new;
            }
        }
        return $originalname;
    }

    /**
     * Whether a series is configured to render as a line.
     *
     * @param string $originalname Original series name
     * @param string $renamedname Renamed series name
     * @return bool
     */
    private function is_chart_line_series(string $originalname, string $renamedname): bool {
        $names = array_filter(
            array_map('trim', explode(',', (string) ($this->config->chartlineseries ?? ''))),
            static fn(string $name): bool => $name !== '',
        );
        return in_array($originalname, $names, true) || in_array($renamedname, $names, true);
    }
}
