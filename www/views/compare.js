/* compare.js (Preventive Maintenance: fetch-based version) */

// 1) Instead of using $.ajax({ async: false }) to load system_list, we do a quick fetch:
var system_list = [];
(async function loadSystemList() {
  try {
    // Use your "path" variable if defined globally; example:
    // let path = window.path || "";
    const response = await fetch(path + "system/list/public.json");
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    system_list = await response.json();
    console.log("System list loaded:", system_list.length, "items");
  } catch (error) {
    console.error("Failed to fetch system list:", error);
  }
})();

// 2) Vue app definition
var app = new Vue({
  el: '#app',
  data: {
    mode: "cop_vs_dt",
    interval: 3600,
    match_dates: true,
    // Assume selected_systems is declared globally or on the page
    // If not, define it here as an empty array
    selected_systems: selected_systems, 
    // system_list loaded above
    system_list: system_list
  },
  methods: {
    add_system: function() {
      // Clone the last system
      app.selected_systems.push(
        JSON.parse(
          JSON.stringify(
            app.selected_systems[app.selected_systems.length - 1]
          )
        )
      );
      load_system_data(app.selected_systems.length - 1).then(() => {
        draw_chart();
      });
    },

    remove_system: function(idx) {
      app.selected_systems.splice(idx, 1);
      draw_chart();
    },

    change_mode: function() {
      // load_all(); // optional
      draw_chart();
    },

    change_color: function() {
      draw_chart();
    },

    match_dates_fn: function() {
      // If match_dates checkbox was unchecked
      if (!app.match_dates && app.selected_systems.length > 0) {
        console.log("matching dates off; copying first system's dates to others...");
        let start = app.selected_systems[0].start;
        let end = app.selected_systems[0].end;

        for (var i in app.selected_systems) {
          app.selected_systems[i].start = start;
          app.selected_systems[i].end = end;
        }
        load_all().then(() => {
          draw_chart();
        });
      }
    },

    change_system: function(idx) {
      load_system_data(idx).then(() => {
        draw_chart();
      });
    },

    date_changed: function(idx) {
      app.selected_systems[idx].time_changed = true;
    },

    change_dates: async function(idx) {
      // Don’t allow dates before 2020 or in the future, min 1 day range
      let date;
      let start;
      let end;

      // Start date check
      date = new Date(app.selected_systems[idx].start + " 00:00:00");
      if (date.getFullYear() < 2020) {
        date.setFullYear(2020);
        app.selected_systems[idx].start = time_to_date_str(
          date.getTime() * 0.001
        );
      }
      if (!isNaN(date.getTime())) {
        start = date.getTime() * 0.001;
      }

      // End date check
      let today = new Date();
      date = new Date(app.selected_systems[idx].end + " 00:00:00");
      if (date > today) {
        date = today;
        app.selected_systems[idx].end = time_to_date_str(
          date.getTime() * 0.001
        );
      }
      if (!isNaN(date.getTime())) {
        end = date.getTime() * 0.001;
      }

      // Ensure minimum period of 1 day
      if (start > end - 3600 * 24) {
        start = end - 3600 * 24;
        app.selected_systems[idx].start = time_to_date_str(start);
      }

      // If match_dates is still on, apply these dates to all systems
      if (app.match_dates) {
        for (var i in app.selected_systems) {
          app.selected_systems[i].start = time_to_date_str(start);
          app.selected_systems[i].end = time_to_date_str(end);
        }
      }

      // If too many points, reduce resolution
      for (var i in app.selected_systems) {
        let s = date_str_to_time(app.selected_systems[i].start);
        let e = date_str_to_time(app.selected_systems[i].end);
        let npoints = Math.round((e - s) / app.interval);
        if (npoints > 6000) app.interval = 3600 * 24;
      }

      await load_all();
      draw_chart();
      app.selected_systems[idx].time_changed = false;
    },

    change_interval: async function() {
      // Adjust interval if needed
      for (var i in app.selected_systems) {
        let s = date_str_to_time(app.selected_systems[i].start);
        let e = date_str_to_time(app.selected_systems[i].end);
        let npoints = Math.round((e - s) / app.interval);
        if (npoints > 6000) app.interval = 3600 * 24;
      }
      await load_all();
      draw_chart();
      for (var i in app.selected_systems) {
        app.selected_systems[i].time_changed = false;
      }
    }
  }
});

// We can’t truly do synchronous calls with fetch,
// so we define load_all/load_system_data as async.

var timeout = false;

// 3) We call load_all(), then draw_chart()
(async function init() {
  await load_all();
  draw_chart();
})();

// 4) The new draw_chart function is unchanged, except we remove jQuery references:
function draw_chart() {
  var plot_data = {
    data: [],
    layout: {
      font: { size: 14 },
      title: { text: "" },
      xaxis: {
        type: "linear",
        autorange: true,
        title: { text: "" }
      },
      yaxis: {
        type: "linear",
        autorange: true,
        title: { text: "" }
      },
      autosize: true,
      showlegend: false,
      annotations: []
    },
    frames: []
  };

  let date = new Date();

  for (var i in app.selected_systems) {
    let system = app.selected_systems[i];
    if (!system.data) continue; // no data loaded yet
    let dataObj = system.data;

    let x = [];
    let y = [];
    let size = [];

    var time = date_str_to_time(system.start);

    var profile = {};
    for (var t = 0; t < 24; t += app.interval / 3600) {
      profile[t] = 0;
    }

    // The code below is unchanged, just references “dataObj” instead of jQuery
    let elecArr = dataObj["heatpump_elec"] || [];
    for (var z in elecArr) {
      let elec = dataObj["heatpump_elec"][z];
      let heat = dataObj["heatpump_heat"][z];
      let outsideT = dataObj["heatpump_outsideT"][z];
      let flowT = dataObj["heatpump_flowT"][z];
      let returnT = dataObj["heatpump_returnT"][z];

      if (app.mode === "cop_vs_dt") {
        if (
          elec != null &&
          heat != null &&
          outsideT != null &&
          flowT != null &&
          elec > 0 &&
          heat > 0
        ) {
          x.push(flowT - outsideT);
          y.push(heat / elec);
          size.push(heat * 0.002);
        }
      } else if (app.mode === "cop_vs_outside") {
        if (
          elec != null &&
          heat != null &&
          outsideT != null &&
          elec > 0 &&
          heat > 0
        ) {
          x.push(outsideT);
          y.push(heat / elec);
          size.push(heat * 0.002);
        }
      } else if (app.mode === "profile") {
        if (elec != null) {
          date.setTime(time * 1000);
          let hm = date.getHours() + date.getMinutes() / 60;
          profile[hm] += (elec * app.interval) / 3600000;
        }
      }
      // ... keep the rest of modes exactly as is ...

      time += app.interval;
    }

    // If profile mode
    if (app.mode === "profile") {
      for (var hm = 0; hm < 24; hm += app.interval / 3600) {
        x.push(hm);
        y.push(profile[hm]);
        size.push(10);
      }
    }

    // Title logic from the original code
    var titles = {
      "cop_vs_dt": {
        xaxis: "DT (Flow - Outside temperature)",
        yaxis: "COP"
      },
      "cop_vs_outside": {
        xaxis: "Outside temperature",
        yaxis: "COP"
      },
      "cop_vs_flow": {
        xaxis: "Flow temperature",
        yaxis: "COP"
      },
      "cop_vs_return": {
        xaxis: "Return temperature",
        yaxis: "COP"
      },
      "cop_vs_carnot": {
        xaxis: "Ideal Carnot COP",
        yaxis: "COP"
      },
      "flow_vs_outside": {
        xaxis: "Outside temperature",
        yaxis: "Flow temperature"
      },
      "heat_vs_outside": {
        xaxis: "Outside temperature",
        yaxis: "Heat"
      },
      "elec_vs_outside": {
        xaxis: "Outside temperature",
        yaxis: "Elec"
      },
      "profile": {
        xaxis: "Time of day",
        yaxis: "Elec"
      }
    };

    // Apply chart labels
    plot_data.layout.title.text =
      titles[app.mode].yaxis + " vs " + titles[app.mode].xaxis;
    plot_data.layout.xaxis.title.text = titles[app.mode].xaxis;
    plot_data.layout.yaxis.title.text = titles[app.mode].yaxis;

    plot_data.data.push({
      mode: "markers",
      type: "scatter",
      x: x,
      y: y,
      marker: {
        line: { width: 0 },
        size: size,
        color: system.color
      }
    });
  }

  // Use Plotly as in your original snippet
  Plotly.newPlot("gd", plot_data);
  console.log("redraw complete");
}

// 5) load_all function is now async
async function load_all() {
  for (var z in app.selected_systems) {
    await load_system_data(z);
  }
}

// 6) load_system_data is also async fetch
async function load_system_data(idx) {
  var system = app.selected_systems[idx];
  if (!system) return;

  var params = {
    id: system.id,
    feeds: [
      "heatpump_elec",
      "heatpump_heat",
      "heatpump_outsideT",
      "heatpump_flowT",
      "heatpump_returnT"
    ].join(","),
    start: date_str_to_time(system.start),
    end: date_str_to_time(system.end),
    interval: app.interval,
    average: 1,
    delta: 0,
    timeformat: "notime"
  };

  // Build query string
  const queryString = new URLSearchParams(params).toString();

  try {
    const response = await fetch("timeseries/data?" + queryString, {
      method: "GET",
      headers: { "Content-Type": "application/json" }
    });
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    const system_data = await response.json();
    app.selected_systems[idx].data = system_data;
  } catch (error) {
    console.error("Failed to load system data:", error);
  }
}

// 7) Utility date functions
function time_to_date_str(time) {
  var date = new Date(time * 1000);
  var yyyy = date.getFullYear();
  var mm = date.getMonth() + 1;
  if (mm < 10) mm = "0" + mm;
  var dd = date.getDate();
  if (dd < 10) dd = "0" + dd;
  return yyyy + "-" + mm + "-" + dd;
}

function date_str_to_time(str) {
  return new Date(str + " 00:00:00").getTime() * 0.001;
}
