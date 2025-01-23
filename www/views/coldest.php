<!-- coldest.php -->
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Coldest Days</title>
  <script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
  <script src="https://code.jquery.com/jquery-3.6.3.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/flot/0.8.3/jquery.flot.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/flot/0.8.3/jquery.flot.time.min.js"></script>
  <script src="Lib/jquery.flot.axislabels.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
</head>
<body>

<div id="app">
  <div style=" background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
    <div class="container-fluid" style="max-width:1320px">
      <div class="input-group mb-3" style="width:600px; float:right">
        <span class="input-group-text">Select system</span>
        <select class="form-control" v-model="systemid" @change="change_system">
          <option v-for="(s,i) in filtered_system_list" :value="s.id">
            {{ s.location }}, {{ s.hp_model }}, {{ s.hp_output }} kW
          </option>
        </select>
        <button class="btn btn-primary" @click="next_system(-1)">&lt;</button>
        <button class="btn btn-primary" @click="next_system(1)">&gt;</button>
      </div>
      <h3>Daily</h3>
    </div>
  </div>

  <div class="container-fluid" style="margin-top:20px; max-width:1320px">
    <!-- Table of the 5 coldest days -->
    <div class="row">
      <table class="table table-striped">
        <thead>
          <tr>
            <th>Date</th>
            <th>Mean Outside Temp</th>
            <th>Mean Flow Temp (running)</th>
            <th>RoomT Running</th>
            <th>Elec</th>
            <th>Heat</th>
            <th>Select</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="day in coldest_days">
            <td>{{ day.timestamp | toDate }}</td>
            <td>{{ day.combined_outsideT_mean.toFixed(1) }}°C</td>
            <td>{{ day.running_flowT_mean.toFixed(2) }}°C</td>
            <td>{{ day.running_roomT_mean.toFixed(1) }}°C</td>
            <td>{{ day.combined_elec_kwh.toFixed(1) }} kWh</td>
            <td>{{ day.combined_heat_kwh.toFixed(1) }} kWh</td>
            <td>
              <input
                type="radio"
                v-model="selected_day"
                :value="day.timestamp"
                @change="change_selected_day"
              />
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="row" style="margin-right:-5px">
      <div class="col-lg-8">
        <div id="placeholder" style="width:100%; height:500px; margin-bottom:20px"></div>
      </div>
      <div class="col-lg-4">
        <div class="input-group mb-3">
          <span class="input-group-text">Mean FlowT (When running)</span>
          <input type="text" class="form-control" v-model="mean_flowT" disabled />
        </div>
        <div class="input-group mb-3">
          <span class="input-group-text">Mean OutsideT</span>
          <input type="text" class="form-control" v-model="mean_outsideT" disabled />
        </div>
        <div class="input-group mb-3">
          <span class="input-group-text">Max FlowT</span>
          <input type="text" class="form-control" v-model="max_flowT" disabled />
        </div>
        <button class="btn btn-primary" @click="save_coldest" :disabled="!enable_save">
          Save
        </button>
      </div>
    </div>
  </div>
</div>

<script>
  // Suppose we have global definitions:
  // var userid = <?php echo $userid; ?>;
  // var systemid = <?php echo $systemid; ?>;
  // var path = ...
  // We'll just assume they're available

  var data = [];
  var timeseries = {};
  var systemid_map = {};

  var app = new Vue({
    el: "#app",
    data() {
      return {
        enable_save: false,
        systemid: systemid,
        system_list: [],
        filtered_system_list: [],
        coldest_days: [],
        max_flowT: "",
        mean_flowT: "",
        mean_outsideT: "",
        selected_day: "",
        flowT_mean_window: ""
      };
    },
    methods: {
      async change_system() {
        await this.load();
      },
      load() {
        return load();
      },
      async next_system(direction) {
        let index = this.find_system(this.systemid);
        if (index < 0) return;
        index += direction;
        if (!this.filtered_system_list[index]) return;
        this.systemid = this.filtered_system_list[index].id;
        await this.load();
      },
      find_system(id) {
        return this.filtered_system_list.findIndex((sys) => sys.id == id);
      },
      filter_system_list() {
        //  Only systems w/ >290 days data
        let filtered = this.system_list.filter((s) => {
          return s.combined_data_length > 290 * 24 * 3600;
        });
        // sort by combined_cop descending
        filtered.sort((a, b) => b.combined_cop - a.combined_cop);
        this.filtered_system_list = filtered;
      },
      async change_selected_day() {
        console.log(this.selected_day);
        await load_timeseries(this.selected_day);

        // apply mean_flowT & mean_outsideT from selected day
        let idx = this.coldest_days.findIndex((x) => x.timestamp == this.selected_day);
        this.mean_flowT = this.coldest_days[idx].running_flowT_mean.toFixed(2);
        this.mean_outsideT = this.coldest_days[idx].combined_outsideT_mean.toFixed(2);
      },
      async save_coldest() {
        let data_to_save = {
          id: this.systemid,
          data: {
            measured_max_flow_temp_coldest_day: this.max_flowT
          }
        };
        try {
          let resp = await fetch(path + "system/save", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(data_to_save)
          });
          if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
          let result = await resp.json();
          alert(result.message);
          // update system_list
          let index = this.find_system(this.systemid);
          if (index >= 0) {
            this.filtered_system_list[index].measured_max_flow_temp_coldest_day =
              this.max_flowT;
            this.filtered_system_list[index].measured_mean_flow_temp_coldest_day =
              this.mean_flowT;
            this.filtered_system_list[index].measured_outside_temp_coldest_day =
              this.mean_outsideT;
          }
        } catch (err) {
          console.error("Failed to save system data:", err);
          alert("Error saving coldest data");
        }
      }
    },
    filters: {
      toFixed(value, dp) {
        if (!value) value = 0;
        return value.toFixed(dp);
      },
      toDate(value) {
        return moment(value * 1000).format("DD MMM YYYY");
      }
    },
    async mounted() {
      // 1) Load systems
      try {
        let resp = await fetch(path + "system/list/public.json");
        if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
        let list = await resp.json();
        this.system_list = list;
        // 2) load last365 stats
        let statsResp = await fetch(path + "system/stats/last365");
        if (!statsResp.ok) throw new Error(`HTTP ${statsResp.status}`);
        let statsObj = await statsResp.json();

        // apply stats
        for (let sys of this.system_list) {
          let s = statsObj[sys.id];
          if (s) {
            for (let key in s) {
              sys[key] = s[key];
            }
          }
        }
        this.filter_system_list();
        await load(); // triggers daily loading
        resize();
      } catch (err) {
        console.error("Failed to load systems or stats:", err);
      }
    }
  });

  async function load() {
    // set max_flowT
    let idx = app.find_system(app.systemid);
    if (idx < 0) return;
    app.max_flowT =
      app.filtered_system_list[idx].measured_max_flow_temp_coldest_day || "";
    app.mean_flowT =
      app.filtered_system_list[idx].measured_mean_flow_temp_coldest_day || "";
    app.mean_outsideT =
      app.filtered_system_list[idx].measured_outside_temp_coldest_day || "";

    // check write access
    try {
      let res = await fetch(path + "system/hasaccess?id=" + app.systemid);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      let canWrite = await res.text();
      app.enable_save = parseInt(canWrite) ? true : false;
    } catch (err) {
      console.error("Access check failed:", err);
    }

    // fetch daily stats
    try {
      let resp = await fetch(path + "system/stats/daily?id=" + app.systemid);
      if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
      let text = await resp.text();
      let lines = text.split("\n");
      let fields = lines[0].split(",").map((f) => f.trim());

      data = [];
      for (let i = 1; i < lines.length; i++) {
        let parts = lines[i].split(",");
        if (parts.length != fields.length) continue;
        let day = {};
        for (let j = 1; j < parts.length; j++) {
          let val = parseFloat(parts[j]);
          day[fields[j]] = val;
        }
        if (day.combined_heat_kwh === 0) continue;
        data.push(day);
      }
      // find 5 coldest
      data.sort((a, b) => a.combined_outsideT_mean - b.combined_outsideT_mean);
      let coldest_days = data.slice(0, 6);
      app.coldest_days = coldest_days;
      let timestamp = coldest_days[0].timestamp;
      app.selected_day = timestamp;
      await load_timeseries(timestamp);
    } catch (err) {
      console.error("Failed to load daily stats:", err);
    }
  }

  async function load_timeseries(timestamp) {
    // GET timeseries data
    let params = new URLSearchParams({
      id: app.systemid,
      feeds: "heatpump_flowT,heatpump_returnT,heatpump_dhw",
      start: timestamp,
      end: timestamp + 24 * 3600,
      interval: 60,
      average: 1,
      timeformat: "notime"
    }).toString();
    try {
      let resp = await fetch(path + "timeseries/data?" + params);
      if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
      let result = await resp.json();

      let flowT_values = result.heatpump_flowT || [];
      let returnT_values = result.heatpump_returnT || [];
      let dhw_values = result.heatpump_dhw || [];
      timeseries.flowT = [];
      timeseries.returnT = [];
      timeseries.dhw = [];

      let sumFlow = 0;
      let countFlow = 0;
      for (let i in flowT_values) {
        let time = (timestamp + i * 60) * 1000;
        let fval = flowT_values[i] < -10 ? null : flowT_values[i];
        let rval = returnT_values[i] < -10 ? null : returnT_values[i];
        timeseries.flowT.push([time, fval]);
        timeseries.returnT.push([time, rval]);
        if (dhw_values.length) {
          timeseries.dhw.push([time, dhw_values[i]]);
        }
        if (fval != null) {
          sumFlow += fval;
          countFlow++;
        }
      }

      // 2h average (120 minutes)
      timeseries.flowT_mean = [];
      for (let i = 0; i < timeseries.flowT.length; i++) {
        let sum2 = 0;
        let count2 = 0;
        for (let j = i - 60; j <= i + 60; j++) {
          if (j >= 0 && j < timeseries.flowT.length) {
            sum2 += timeseries.flowT[j][1];
            count2++;
          }
        }
        let avg = count2 ? sum2 / count2 : null;
        timeseries.flowT_mean.push([timeseries.flowT[i][0], avg]);
      }
      app.flowT_mean_window = countFlow ? (sumFlow / countFlow).toFixed(2) : "0.0";
      draw();
    } catch (err) {
      console.error("Failed to load timeseries data:", err);
    }
  }

  function draw() {
    let options = {
      series: {},
      xaxis: {
        mode: "time",
        timezone: "browser",
        timeformat: "%H:%M"
      },
      yaxes: [
        {
          position: "left",
          axisLabel: "Temperature (°C)"
        },
        {
          min: 0,
          max: 1,
          show: false
        }
      ],
      grid: {
        hoverable: true,
        clickable: true
      },
      axisLabels: { show: true }
    };

    let flotSeries = [];
    if (timeseries.flowT) {
      flotSeries.push({
        data: timeseries.flowT,
        label: "FlowT",
        color: 2,
        lines: { show: true }
      });
    }
    if (timeseries.returnT) {
      flotSeries.push({
        data: timeseries.returnT,
        label: "ReturnT",
        color: 3,
        lines: { show: true }
      });
    }
    if (timeseries.dhw) {
      flotSeries.push({
        data: timeseries.dhw,
        label: "DHW",
        yaxis: 2,
        color: "#88F",
        lines: { lineWidth: 0, show: true, fill: 0.15 }
      });
    }
    if (timeseries.flowT_mean) {
      flotSeries.push({
        data: timeseries.flowT_mean,
        label: "FlowT Mean",
        color: 4,
        lines: { show: true }
      });
    }

    $.plot("#placeholder", flotSeries, options);
  }

  // Flot tooltip
  let previousPoint = null;
  $("#placeholder").bind("plothover", (event, pos, item) => {
    if (item) {
      if (previousPoint !== item.datapoint) {
        previousPoint = item.datapoint;
        $("#tooltip").remove();
        let y = item.datapoint[1];
        let label = `${item.series.label}: ${
          y !== null ? y.toFixed(1) + "°C" : "null"
        }`;
        showTooltip(item.pageX, item.pageY, label, "#fff", "#000");
      }
    } else {
      $("#tooltip").remove();
      previousPoint = null;
    }
  });

  $("#placeholder").bind("plotclick", function (event, pos, item) {
    if (item) {
      let y = item.datapoint[1];
      app.max_flowT = y.toFixed(1);
    }
  });

  $(window).resize(() => {
    resize();
  });

  function resize() {
    let w = $("#placeholder").width();
    let h = w * 1.2;
    if (h > 450) h = 450;
    $("#placeholder").height(h);
    draw();
  }

  function showTooltip(x, y, contents, bg, border = "rgb(255, 221, 221)") {
    let offset = 10;
    let elem = $(`<div id="tooltip">${contents}</div>`).css({
      position: "absolute",
      color: "#000",
      display: "none",
      "font-weight": "bold",
      border: `1px solid ${border}`,
      padding: "2px",
      "background-color": bg,
      opacity: "0.8",
      "text-align": "left"
    });
    $("body").append(elem);
    let elemY = y - elem.height() - offset;
    let elemX = x - elem.width() - offset;
    if (elemY < 0) elemY = 0;
    if (elemX < 0) elemX = 0;
    elem.css({ top: elemY, left: elemX }).fadeIn(200);
  }
</script>

</body>
</html>
