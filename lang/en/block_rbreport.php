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

/**
 * Plugin strings are defined here.
 *
 * @package     block_rbreport
 * @author      Marina Glancy
 * @copyright   2021 Moodle Pty Ltd <support@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['charttypebar'] = 'Bar';
$string['charttypebarhorizontal'] = 'Horizontal Bar';
$string['charttypebarstacked'] = 'Stacked Bar';
$string['charttypedoughnut'] = 'Doughnut';
$string['charttypeline'] = 'Line';
$string['charttypepie'] = 'Pie';
$string['columnnumber'] = 'Column {$a}';
$string['configchartexcludeempty'] = 'Exclude empty values';
$string['configchartexcludeempty_help'] = 'Skip rows from the chart when the label, series/group value, or split value ' .
    'is blank. This is useful for excluding accounts with no data, such as administrators.';
$string['configchartexcludezero'] = 'Exclude zero values';
$string['configchartlabelcolumn'] = 'Chart labels (X-axis) column';
$string['configchartlegend'] = 'Chart legend';
$string['configchartlegend_help'] = 'Controls the Chart.js legend visibility and position. Default keeps the chart\'s ' .
    'standard legend.';
$string['configchartlineseries'] = 'Line series';
$string['configchartlineseries_help'] = 'Comma-separated series names to render as lines on bar or stacked bar charts. ' .
    'Names can be either the original or renamed series names.';
$string['configchartmax'] = 'Maximum';
$string['configchartmin'] = 'Minimum';
$string['configchartpercent'] = 'Display values as percentages';
$string['configchartpercent_help'] = 'Values are converted to percentages. With multiple series, each value becomes ' .
    'its share of that category\'s total, so stacked bars total 100%. With a single series, each value becomes its ' .
    'share of the overall total. The value axis and tooltips display a % suffix.';
$string['configchartpiepercent'] = 'Use percentage for Pie charts';
$string['configchartseriescolumn'] = 'Group series by';
$string['configchartseriescolumn_help'] = 'Each distinct value of this column becomes its own chart series. ' .
    'Values are summed per label. Combine this with the stacked bar chart type for grouped breakdowns. ' .
    'This option is not available for pie or doughnut charts.';
$string['configchartseriesnames'] = 'Rename chart series';
$string['configchartseriesnames_help'] = 'Enter one mapping per line in the format "original = new". ' .
    'Series without a matching original name keep their existing names.';
$string['configchartsplitcolumn'] = 'Split charts by';
$string['configchartsplitcolumn_help'] = 'Create one chart for each distinct value of this column.';
$string['configchartstepsize'] = 'Step size';
$string['configcharttitle'] = 'Chart title';
$string['configcharttitlesize'] = 'Chart title size';
$string['configcharttype'] = 'Chart type';
$string['configchartvaluecolumn'] = 'Chart values column';
$string['configchartxaxislabel'] = 'X-axis label';
$string['configchartyaxislabel'] = 'Y-axis label';
$string['configcumulative'] = 'Accumulate data successively';
$string['configlayout'] = 'Layout';
$string['configlayout_help'] = '**Adaptive:** Display as cards only in small blocks<br>
**Cards:** Always display as cards<br>
**Table:** Always display as table';
$string['configreport'] = 'Select report';
$string['configreport_help'] = 'Custom report that will be embedded into the block';
$string['configsetminmax'] = 'Set minimum and maximum values for Y axis';
$string['configsetstepsize'] = 'Set step size value for Y axis';
$string['configtitle'] = 'Block title';
$string['displayadaptive'] = 'Adaptive';
$string['displayascards'] = 'Cards';
$string['displayaschart'] = 'Chart';
$string['displayastable'] = 'Table';
$string['entriesperpage'] = 'Entries per page';
$string['errormessage'] = 'Error occurred while retrieving the report';
$string['gotofullreport'] = 'Go to full report';
$string['legendbottom'] = 'Bottom';
$string['legenddefault'] = 'Default';
$string['legendhidden'] = 'Hidden';
$string['legendleft'] = 'Left';
$string['legendright'] = 'Right';
$string['legendtop'] = 'Top';
$string['pluginname'] = 'Report';
$string['privacy:metadata:block'] = 'The Report block stores all of its data within the block subsystem.';
$string['rbreport:addinstance'] = 'Add a new Report block';
$string['rbreport:myaddinstance'] = 'Add a new Report block to Dashboard';
$string['rbreport:view'] = 'View report block';
$string['reportnotsetmessage'] = 'Please configure this block and select which report it should display.';
$string['seriescolumnnone'] = 'None (one series per report)';
$string['splitcolumnnone'] = 'None (single chart)';
$string['titlesizeh1'] = 'Heading 1';
$string['titlesizeh2'] = 'Heading 2';
$string['titlesizeh3'] = 'Heading 3';
$string['titlesizeh4'] = 'Heading 4';
$string['titlesizeh5'] = 'Heading 5';
$string['titlesizeh6'] = 'Heading 6';
$string['toomanycharts'] = 'Too many charts ({$a}) to show';
$string['toomanyreportstoshow'] = 'Too many reports ({$a}) to show';

// Deprecated since Moodle 5.0.4.
$string['reporttypecore'] = 'Custom report';
$string['reporttypetool'] = 'Custom report from outdated Report builder';
