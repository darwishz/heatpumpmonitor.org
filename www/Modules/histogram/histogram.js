console.log("jQuery Version:", $.fn.jquery);
console.log("Flot Library:", typeof $.plot === "function" ? "Loaded" : "Not Loaded");

// Fetch the list of systems
var system_list = [];
var system_map = {};
$.ajax({
    dataType: "json", 
    url: path+"system/list/public.json", 
    async: false, 
    success: function(result) { 

        // Sort systems by location
        result.sort(function(a,b) {
            if (a.location < b.location) return -1;
            if (a.location > b.location) return 1;
            return 0;
        });

        system_list = result; 

        // Map systems by ID for quick access
        for (var i=0; i<system_list.length; i++) {
            system_map[system_list[i].id] = i;
        }

    }
});

// Default date range for selected systems
var default_start = "2023-10-01";
var default_end = "2024-04-01";

// Vue app initialization
var date = new Date();
var yyyy_start = date.getFullYear()-1;
var yyyy_end = date.getFullYear();
var mm = date.getMonth()+1;
if (mm<10) mm = "0"+mm;
var dd = date.getDate();
if (dd<10) dd = "0"+dd;
default_start = yyyy_start+"-"+mm+"-"+dd;
default_end = yyyy_end+"-"+mm+"-"+dd;




var colours = ["#fec601","#ea7317","#73bfb8","#3da5d9","#2364aa"];

var app = new Vue({
    el: '#app',
    data: {
        histogram_type: "kwh_at_cop",
        system_list: system_list,
        selected_systems: [
            {id: id, color: colours[0], start: default_start, end: default_end, time_changed: false, data: []}
        ],
        match_dates: true,
        interval: 600,
        plot_type: "points",
        months: ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"],
        xaxis_title: "COP",
        x_min: 1.0,
        x_max: 8.0,
        average_x_values: []
    },
    methods: {
        change_histogram_type: function() {
            // Change histogram type and reload data

            if (this.histogram_type == "kwh_at_cop") {
                this.xaxis_title = "COP";
                this.x_min = 1.0;
                this.x_max = 8.0;
            } else if (this.histogram_type == "kwh_at_flow") {
                this.xaxis_title = "Flow temperature";
                this.x_min = 20;
                this.x_max = 55;
            } else if (this.histogram_type == "kwh_at_outside") {
                this.xaxis_title = "Outside temperature";
                this.x_min = -10;
                this.x_max = 20;
            } else if (this.histogram_type == "kwh_at_flow_minus_outside") {
                this.xaxis_title = "Flow minus outside temperature";
                this.x_min = 0;
                this.x_max = 60;
            } else if (this.histogram_type == "kwh_at_ideal_carnot") {
                this.xaxis_title = "Ideal Carnot COP";
                this.x_min = 0;
                this.x_max = 20;
            }

            for (var i=0; i<app.selected_systems.length; i++) {
                load_system_data(i);
            }
            draw();
        },
        add_system: function () {
            // Add a new system
            if (this.selected_systems.length == 0) {
                // add empty system
                this.selected_systems.push({id: 1, color: colours[0], start: default_start, end: default_end, time_changed: false, data: []});
                load_system_data(this.selected_systems.length-1);
                draw();
            } else {
                // add copy of last system
                this.selected_systems.push(JSON.parse(JSON.stringify(this.selected_systems[this.selected_systems.length-1])));
                this.selected_systems[this.selected_systems.length-1].color = colours[this.selected_systems.length-1];
                draw();
            }
            
        },
        // Handle color change
        change_color: function() {
            draw();
        },
        change_system: function(idx) {
            load_system_data(idx);
            draw();
        },
        // Update the date ranges
        date_changed: function(idx) {
            if (app.match_dates) {
                // load all systems
                for (var i=0; i<app.selected_systems.length; i++) {
                    app.selected_systems[i].start = app.selected_systems[idx].start;
                    app.selected_systems[i].end = app.selected_systems[idx].end;
                    load_system_data(i);
                }
                draw();
            } else {
                load_system_data(idx);
                draw();
            }
        },
        // Synchronize date ranges across systems
        update_match_dates: function() {
            if (!this.match_dates) {
                for (var i=0; i<app.selected_systems.length; i++) {
                    app.selected_systems[i].start = app.selected_systems[0].start;
                    app.selected_systems[i].end = app.selected_systems[0].end;
                    load_system_data(i);
                }
                draw();        
            }
        },
        // Remove a selected system
        remove_system: function(idx) {
            this.selected_systems.splice(idx, 1);
            draw();
        },
        // Update the plot type
        update_plot_type: function() {
            draw();
        },
        // Update the minimum x-axis value
        update_min: function() {
            for (var i=0; i<app.selected_systems.length; i++) {
                load_system_data(i);
            }
            draw();
        },
        // Update the maximum x-axis value
        update_max: function() {
            for (var i=0; i<app.selected_systems.length; i++) {
                load_system_data(i);
            }
            draw();
        }

    }
});

// Flot chart options
var options = {
    series: {
    },
    xaxis: {
        axisLabel: 'COP'
    },
    yaxis: {
        min: 0,
        axisLabel: 'kWh heat'
    },
    grid: {
        hoverable: true,
        clickable: true
    },
    axisLabels: {
        show: true
    }
};

// Create flot chart
draw();

/**
 * Draws the chart on the page using the Flot library.
 * Updates chart configurations dynamically based on user inputs.
 *
 * @function
 */

// Function to draw the chart based on the current plot type and options
function draw() {
    // Configure options based on the selected plot type
    if (app.plot_type=="lines") {
        options.series.bars = {show: false};
        options.series.lines = {show: true};
        options.series.points = {show: false};
        options.series.lines.fill = false;
    } else if (app.plot_type=="points") {
        options.series.bars = {show: false};
        options.series.lines = {show: true};
        options.series.points = {show: true};
        options.series.lines.fill = false;
    } else if (app.plot_type=="filled") {
        options.series.bars = {show: false};
        options.series.lines = {show: true};
        options.series.points = {show: false};
        options.series.lines.fill = true;
    } else if (app.plot_type=="bars") {
        options.series.bars = {show: true};
        options.series.lines = {show: false};
        options.series.points = {show: false};
        options.series.bars.barWidth = 0.1;
    }

    // Add markings for average x values
    options.grid.markings = [];
    for (var i=0; i<app.selected_systems.length; i++) {
        var avg_x = app.average_x_values[i];
        var color = app.selected_systems[i].color;
        if (avg_x!=undefined) {
            options.grid.markings.push({xaxis: {from: avg_x, to: avg_x}, color: color});
        }
    }

    var chart = $.plot("#placeholder", app.selected_systems, options);

    // Add vertical line for average x values
    for (var i=0; i<app.selected_systems.length; i++) {
        var avg_x = app.average_x_values[i];

        // Add a marking if the average x value is defined
        if (avg_x!=undefined) {

            var o = chart.pointOffset({ x: avg_x, y: 0});
            var top = chart.getPlotOffset().top + 5 + i*20;

            chart.getPlaceholder().append("<div style='position:absolute;left:" + (o.left + 8) + "px;top:" + top + "px;color:#666;font-size:smaller'>Weighted average: "+avg_x.toFixed(2)+"</div>");

        }
    }
}

/**
* Fetches histogram data for a specific system and updates the chart.
*
* @param {number} idx - The index of the system in the selected_systems array.
*/

// Load system data
load_system_data(0);
draw();


/**
* Fetches histogram data for a specific system and updates the chart.
*
* @param {number} idx - The index of the system in the selected_systems array.
*/

function load_system_data(idx) {
// Retrieve the system object from the selected_systems array using the provided index
var system = app.selected_systems[idx];
console.log(system); // Log the system object for debugging purposes

// Convert the start and end dates of the system to Unix timestamps
var view_start = date_str_to_time(system.start);

// Make an AJAX request to fetch histogram data for the system
$.ajax({
    dataType: "json", // Expect JSON data in the response
    url: path + "histogram/" + app.histogram_type, // Endpoint to fetch histogram data
    data: {
        id: system.id, // System ID
        start: date_str_to_time(system.start), // Start date in Unix timestamp
        end: date_str_to_time(system.end), // End date in Unix timestamp
        x_min: app.x_min, // Minimum x-axis value
        x_max: app.x_max, // Maximum x-axis value
    },
    async: false, // Synchronous request (blocks execution until the response is received)
    success: function (result) {
        console.log("AJAX Response:", result); // Add this line
        // Check if the response indicates an error
        if (result.success !== undefined && !result.success) {
            alert("Error: " + result.message); // Display an error message to the user
            return; // Exit the function early
        }

        // Initialize variables for processing data and calculating the weighted average
        let data = []; // Array to hold processed data points
        let index = 0; // Index for iterating over the result data
        let sum = 0; // Sum of (x * y) for weighted average calculation
        let sum_y = 0; // Sum of y values for weighted average calculation

        // Iterate through the data range and populate the data array
        for (var i = result.min; i <= result.max; i += result.div) {
            data.push([i, result.data[index]]); // Add the data point [x, y] to the array
            sum += i * result.data[index]; // Accumulate the weighted sum of x
            sum_y += result.data[index]; // Accumulate the sum of y
            index++; // Move to the next data point
        }

        // Calculate the weighted average x value for the system
        let avg_x = sum / sum_y;
        console.log("system: " + system.id + ", average x: " + avg_x); // Log the calculated average for debugging
        app.average_x_values[idx] = avg_x; // Store the calculated average in the app state
        app.selected_systems[idx].data = data; // Update the system's data property with processed data

        // Update the bar width for bar charts to match the data interval
        options.series.bars.barWidth = result.div;

        // Update the x-axis label based on the current histogram type
        if (app.histogram_type=="kwh_at_cop") {
                options.xaxis.axisLabel = "COP";
            } else if (app.histogram_type=="kwh_at_flow") {
                options.xaxis.axisLabel = "Flow temperature (°C)";
            } else if (app.histogram_type=="kwh_at_outside") {
                options.xaxis.axisLabel = "Outside temperature (°C)";
            } else if (app.histogram_type=="kwh_at_flow_minus_outside") {
                options.xaxis.axisLabel = "Flow minus outside temperature (°K)";
            } else if (app.histogram_type=="kwh_at_ideal_carnot") {
                options.xaxis.axisLabel = "Ideal carnot COP";
            } else if (app.histogram_type=="flow_temp_curve") {
                options.xaxis.axisLabel = "Outside temperature (°C)";
            }
    },
});
}

resize();

/**
* Resizes the chart dynamically to fit the available screen space.
*/

function resize() {
// Calculate the height for the chart based on the window height and placeholder offset
var height = $(window).height() - $("#placeholder").offset().top - 80;

// Adjust the placeholder height to fit the calculated height
$("#placeholder").height(height);

// Redraw the chart to adapt to the new size
draw();
}

/**
* Event listener for resizing the window to trigger chart resizing.
*/

// Add an event listener to trigger the resize function when the window size changes
$(window).resize(function () {
resize();
});

/**
* Converts a Unix timestamp (in seconds) to a formatted date string (yyyy-mm-dd).
*
* @param {number} time - The timestamp in seconds since the Unix epoch.
* @returns {string} The formatted date string.
*/

// Utility function: Convert a timestamp (in seconds) to a date string in the format yyyy-mm-dd
function time_to_date_str(time) {
var date = new Date(time * 1000); // Convert seconds to milliseconds and create a Date object
var yyyy = date.getFullYear(); // Get the full year
var mm = date.getMonth() + 1; // Get the month (0-based, so add 1)
if (mm < 10) mm = "0" + mm; // Pad single-digit months with a leading zero
var dd = date.getDate(); // Get the day of the month
if (dd < 10) dd = "0" + dd; // Pad single-digit days with a leading zero
return yyyy + "-" + mm + "-" + dd; // Return the formatted date string
}

/**
* Converts a date string (yyyy-mm-dd) to a Unix timestamp (in seconds).
*
* @param {string} str - The date string in yyyy-mm-dd format.
* @returns {number} The timestamp in seconds since the Unix epoch.
*/

// Utility function: Convert a date string (yyyy-mm-dd) to a timestamp (in seconds since Unix epoch)
function date_str_to_time(str) {
// Append time to the date string and convert it to milliseconds, then divide by 1000 for seconds
return new Date(str + " 00:00:00").getTime() * 0.001;
}

/**
* Adds tooltips to the chart on hover.
*/

// Add tooltips to the chart on hover
var previousPoint = null; // Store the previously hovered point to avoid redundant tooltip updates
$("#placeholder").bind("plothover", function (event, pos, item) {
if (item) {
    // Check if the hovered point is different from the previously hovered point
    if (previousPoint != item.dataIndex) {
        previousPoint = item.dataIndex; // Update the previously hovered point

        $("#tooltip").remove(); // Remove any existing tooltip
        var x = item.datapoint[0].toFixed(2), // Format the x-coordinate to 2 decimal places
            y = item.datapoint[1].toFixed(2); // Format the y-coordinate to 2 decimal places

        // Display the tooltip at the specified position with the content
        showTooltip(item.pageX, item.pageY, x + ", " + y);
    }
} else {
    // Remove the tooltip when the pointer leaves the plot area
    $("#tooltip").remove();
    previousPoint = null;
}
});

/**
* Displays a tooltip at a specified position with given content.
*
* @param {number} x - The x-coordinate on the page for the tooltip.
* @param {number} y - The y-coordinate on the page for the tooltip.
* @param {string} contents - The content to display inside the tooltip.
*/

// Function to display the tooltip at the specified position
function showTooltip(x, y, contents) {
// Create a tooltip div with the specified contents and style it
$('<div id="tooltip">' + contents + '</div>')
    .css({
        position: "absolute", // Position the tooltip absolutely on the page
        display: "none", // Initially hide the tooltip
        top: y - 30, // Position the tooltip 30px above the cursor
        left: x + 5, // Position the tooltip 5px to the right of the cursor
        border: "1px solid #fdd", // Add a border with a light red color
        padding: "2px", // Add padding inside the tooltip
        "background-color": "#fee", // Set a light red background color
        opacity: 0.8, // Set tooltip opacity for better readability
    })
    .appendTo("body") // Append the tooltip to the body
    .fadeIn(200); // Fade in the tooltip over 200ms
}