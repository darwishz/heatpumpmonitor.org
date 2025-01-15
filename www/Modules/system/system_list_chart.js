let first_chart_load = true;

// Function to draw the scatter plot
function draw_scatter() {
    if (!app.chart_enable) return;

    app.url_update();
    console.log("Drawing scatter chart");

    const trace = initializeTrace();

    // Populate data for the scatter plot
    populateTraceData(trace);

    const data = [trace];

    // Add correlation and line of best fit
    addCorrelationAndBestFit(trace, data);

    // Prepare layout and configuration
    const layout = prepareLayout();
    const config = { displayModeBar: false };

    // Plot the scatter chart
    Plotly.newPlot('chart', data, layout, config);

    // Display chart info
    displayChartInfo(trace);

    // Handle first chart load resize
    if (first_chart_load) {
        first_chart_load = false;
        resizeChart();
    }
}

// Function to initialize the trace object
function initializeTrace() {
    return {
        mode: 'markers',
        marker: {
            size: 10,
            colorscale: 'Viridis',
            showscale: false
        },
        hovertemplate: '%{text}',
        x: [],
        y: [],
        marker: { color: [] },
        text: []
    };
}

// Function to populate trace data
function populateTraceData(trace) {
    for (const system of Object.values(app.fSystems)) {
        const x = system[app.selected_xaxis];
        const y = system[app.selected_yaxis];

        if (!isValidCoordinate(x, y)) continue;

        trace.x.push(x);
        trace.y.push(y);

        trace.marker.color.push(getColor(system));
        trace.text.push(createTooltip(system, x, y));
    }
}

// Function to validate coordinates
function isValidCoordinate(x, y) {
    return x !== 0 && y !== 0 && x !== null && y !== null;
}

// Function to determine color for the marker
function getColor(system) {
    if (columns[app.selected_color]?.options) {
        const index = columns[app.selected_color].options.indexOf(system[app.selected_color]);
        return index !== -1 ? index : null;
    }
    return system[app.selected_color];
}

// Function to create a tooltip for a data point
function createTooltip(system, x, y) {
    const xFormatted = formatValue(x, app.selected_xaxis);
    const yFormatted = formatValue(y, app.selected_yaxis);

    return `System: ${system.id}, ${system.location}<br>${system.hp_output} kW ${system.hp_model}<br>
            ${columns[app.selected_xaxis].name}: ${xFormatted}<br>
            ${columns[app.selected_yaxis].name}: ${yFormatted}<br>
            ${columns[app.selected_color].name}: ${system[app.selected_color]}`;
}

// Function to format a value based on decimal points
function formatValue(value, axis) {
    const dp = columns[axis]?.dp;
    return dp !== undefined ? value.toFixed(dp) : value;
}

// Function to calculate and add correlation and line of best fit
function addCorrelationAndBestFit(trace, data) {
    const { x, y } = trace;

    app.correlation = calculatePearsonCorrelation(x, y);
    const line = calculateLineOfBestFit(x.map((xi, i) => [xi, y[i]]), 0);
    app.r2 = calculate_determination_coefficient(x, y, line.m, line.b);

    if (app.enable_line_best_fit) {
        const min_x = Math.min(...x);
        const max_x = Math.max(...x);

        data.push({
            type: 'scatter',
            x: [min_x, max_x],
            y: [line.m * min_x + line.b, line.m * max_x + line.b],
            mode: 'lines',
            line: { color: "#1f77b4", width: 2 }
        });
    }
}

// Function to prepare the layout for the chart
function prepareLayout() {
    const x_name = cleanStatName(app.selected_xaxis);
    const y_name = cleanStatName(app.selected_yaxis, true);

    return {
        xaxis: { title: `${x_name.group}: ${x_name.name}`, showgrid: true, zeroline: false },
        yaxis: { title: `${y_name.group}: ${y_name.name}`, showgrid: true, zeroline: false },
        margin: { t: 10, r: 10 },
        dragmode: false,
        showlegend: false
    };
}

// Helper function to clean and format stat names
function cleanStatName(axis, isYAxis = false) {
    const group = columns[axis].group.replace("Stats: ", "");
    let name = columns[axis].name;

    if (isYAxis && name === "COP") {
        name = app.stats_time_start === 'last365' ? "Seasonal Performance Factor (SPF)" : "Coefficient of Performance (COP)";
    }

    return { group, name };
}

// Function to display chart info
function displayChartInfo(trace) {
    const { correlation, r2 } = app;
    const { m, b } = calculateLineOfBestFit(trace.x.map((x, i) => [x, trace.y[i]]), 0);

    app.chart_info = `R: ${correlation.toFixed(2)}, R²: ${r2.toFixed(2)}, n=${trace.x.length}, (y=${m.toFixed(2)}x + ${b.toFixed(2)})`;
}

// Pearson correlation calculation (unchanged)
function calculatePearsonCorrelation(x, y) {
    let sumX = 0, sumY = 0, sumXY = 0, sumXSquare = 0, sumYSquare = 0;
    const n = x.length;

    for (let i = 0; i < n; i++) {
        sumX += x[i];
        sumY += y[i];
        sumXY += x[i] * y[i];
        sumXSquare += x[i] * x[i];
        sumYSquare += y[i] * y[i];
    }

    const numerator = (n * sumXY) - (sumX * sumY);
    const denominator = Math.sqrt(((n * sumXSquare) - (sumX ** 2)) * ((n * sumYSquare) - (sumY ** 2)));

    return denominator === 0 ? 0 : numerator / denominator;
}

// Line of best fit calculation (unchanged)
function calculateLineOfBestFit(dataPoints, min_x) {
    let xSum = 0, ySum = 0, xySum = 0, xxSum = 0, n = 0;

    for (const [x, y] of dataPoints) {
        if (x >= min_x) {
            xSum += x;
            ySum += y;
            xxSum += x * x;
            xySum += x * y;
            n += 1;
        }
    }

    const m = (n * xySum - xSum * ySum) / (n * xxSum - xSum ** 2);
    const b = (ySum - m * xSum) / n;

    return { m, b };
}

// Coefficient of determination calculation (unchanged)
function calculate_determination_coefficient(x, y, m, b) {
    const yMean = y.reduce((a, b) => a + b) / y.length;
    const yPredicted = x.map(xi => m * xi + b);
    const ssTot = y.reduce((a, yi) => a + (yi - yMean) ** 2, 0);
    const ssRes = y.reduce((a, yi, i) => a + (yi - yPredicted[i]) ** 2, 0);

    return 1 - ssRes / ssTot;
}

// Chart resizing logic
function resizeChart() {
    if (!app.chart_enable) return;

    const chartDiv = document.getElementById('chart');
    const width = chartDiv.offsetWidth;
    if (!width) return;

    const height = Math.max(width * 0.4, 400);
    console.log(`Resizing chart to width: ${width}, height: ${height}`);

    Plotly.relayout(chartDiv, { width, height });
}

// Event listeners for resizing and window load
window.addEventListener('resize', resizeChart);
window.onload = resizeChart;
