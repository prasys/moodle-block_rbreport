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
 * Chart.js output with percentage suffixes.
 *
 * @copyright  2026 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @module     block_rbreport/chart_output_percent
 */
define(['core/chart_output_chartjs'], function(Base) {
    /**
     * Percentage chart output.
     *
     * @class
     * @extends {module:core/chart_output_chartjs}
     */
    function Output() {
        Base.apply(this, arguments);
    }
    Output.prototype = Object.create(Base.prototype);
    Output.prototype.constructor = Output;

    /** @override */
    Output.prototype._makeConfig = function() {
        var config = Base.prototype._makeConfig.apply(this, arguments);
        if (this._chart.getType() !== 'pie') {
            var axis = config.options.indexAxis === 'y' ? 'x' : 'y';
            config.options.scales = config.options.scales || {};
            config.options.scales[axis] = config.options.scales[axis] || {};
            config.options.scales[axis].ticks = config.options.scales[axis].ticks || {};
            config.options.scales[axis].ticks.callback = function(value) {
                return value + '%';
            };
        }
        return config;
    };

    /** @override */
    Output.prototype._makeTooltip = function() {
        return Base.prototype._makeTooltip.apply(this, arguments).map(function(line) {
            return line + '%';
        });
    };

    return Output;
});
