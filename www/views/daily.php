<!-- daily.php -->

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Daily</title>
  <script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
  <script src="https://code.jquery.com/jquery-3.6.3.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/flot/0.8.3/jquery.flot.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/flot/0.8.3/jquery.flot.time.min.js"></script>
  <script src="Lib/jquery.flot.axislabels.js"></script>
</head>
<body>

<div id="app">
  <div style="background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
    <div class="container-fluid">
      <h3>Daily</h3>
    </div>
  </div>

  <div class="container-fluid" style="margin-top:20px; max-width:1320px">
    <div class="row">
      <div class="col-lg-6">
        <div class="input-group mb-3">
          <span class="input-group-text">Select system</span>
          <select class="form-control" v-model="systemid" @change="change_system">
            <option
              v-for="(s,i) in system_list"
              :value="s.id"
            >
              {{ s.location }}, {{ s.hp_model }}, {{ s.hp_output }} kW
            </option>
          </select>
          <button class="btn btn-primary" @click="next_system(-1)">&lt;</button>
          <button class="btn btn-primary" @click="next_system(1)">&gt;</button>
        </div>
      </div>
    </div>

    <!-- row with x/y axis selection -->
    <div class="row">
      <div class="col-lg-6">
        <div class="input-group mb-3">
          <span class="input-group-text">Y-axis</span>
          <select class="form-control" v-model="selected_yaxis" @change="load">
            <optgroup
              v-for="(group, group_name) in stats_schema_grouped"
              :label="group_name"
            >
              <option
                v-for="(row,key) in group"
                :value="key"
                v-if="row.name"
              >
                {{ row.name }}
              </option>
            </optgroup>
          </select>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="input-group mb-3">
          <span class="input-group-text">X-axis</span>
          <select class="form-control" v-model="selected_xaxis" @change="load">
            <optgroup
              v-for="(group, group_name) in stats_schema_grouped"
              :label="group_name"
            >
              <option
                v-for="(row,key) in group"
                :value="key"
                v-if="row.name"
              >
                {{ row.name }}
              </option>
            </optgroup>
          </select>
        </div>
      </div>
    </div>

    <div class="row" style="margin-right:-5px">
      <div id="placeholder" style="width:100%; height:600px; margin-bottom:20px"></div>
    </div>

    <!-- min/max axis text fields -->
    <div class="row">
      <div class="col-lg-3">
        <div class="input-group mb-3">
          <span class="input-group-text">Min Y-axis</span>
          <input type="text" class="form-control" v-model="min_yaxis" @change="draw">
        </div>
      </div>
      <div class="col-lg-3">
        <div class="input-group mb-3">
          <span class="input-group-text">Max Y-axis</span>
          <input type="text" class="form-control" v-model="max_yaxis" @change="draw">
        </div>
      </div>
      <div class="col-lg-3">
        <div class="input-group mb-3">
          <span class="input-group-text">Min X-axis</span>
          <input type="text" class="form-control" v-model="min_xaxis" @change="draw">
        </div>
      </div>
      <div class="col-lg-3">
        <div class="input-group mb-3">
          <span class="input-group-text">Max X-axis</span>
          <input type="text" class="form-control" v-model="max_xaxis" @change="draw">
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  var userid = <?php echo $userid; ?>;
  var systemid = <?php echo $systemid; ?>;
  var stats_schema = <?php echo json_encode($stats_schema); ?>;
  var mode = "combined"; // you use "combined" for your daily data

  // Group stats schema
  var stats_schema_grouped = {};
  for (let k in stats_schema) {
    let g = stats_schema[k].group;
    if (!stats_schema_grouped[g]) {
      stats_schema_grouped[g] = {};
    }
    stats_schema_grouped[g][k] = stats_schema[k];
  }

  // read URL params
  let urlParams = new URLSearchParams(window.location.search);
  let defaultX = urlParams.get("x") || "running_outsideT_mean";
  let defaultY = urlParams.get("y") || "running_flowT_mean";
  let defaultMinY = urlParams.get("min_yaxis") || "auto";
  let defaultMaxY = urlParams.get("max_yaxis") || "auto";
  let defaultMinX = urlParams.get("min_xaxis") || "auto";
  let defaultMaxX = urlParams.get("max_xaxis") || "auto";

  var data = {};
  var systemid_map = {};

  var app = new Vue({
    el: "#app",
    data: {
      systemid: systemid,
      system_list: [],
      stats_schema,
      stats_schema_grouped,
      selected_xaxis: defaultX,
      selected_yaxis: defaultY,
      min_yaxis: defaultMinY,
      max_yaxis: defaultMaxY,
      min_xaxis: defaultMinX,
      max_xaxis: defaultMaxX
    },
    methods: {
      async change_system() {
        await load();
      },
      load() {
        return load();
      },
      async next_system(dir) {
        let idx = systemid_map[this.systemid];
        if (idx === undefined) return;
        idx += dir;
        if (idx < 0 || idx >= this.system_list.length) return;
        this.systemid = this.system_list[idx].id;
        await load();
      },
      draw() {
        draw();
      }
    },
    async mounted() {
      // fetch system list
      try {
        let resp = await fetch(path + "system/list/public.json");
        if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
        let list = await resp.json();
        // sort by location
        list.sort((a, b) => a.location.localeCompare(b.location));
        this.system_list = list;
        for (let i = 0; i < list.length; i++) {
          systemid_map[list[i].id] = i;
        }
        await load();
        resize();
      } catch (err) {
        console.error("Error in init:", err);
      }
    }
  });

  async function load() {
    // check write access
    try {
      let res = await fetch(path + "system/hasaccess?id=" + app.systemid);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      let canWrite = await res.json();
      // you can store canWrite if you want
    } catch (err) {
      console.warn("Error checking write access:", err);
    }

    // fetch daily stats
    // we read text/csv but splitted lines
    try {
      let dailyUrl = path + "system/stats/daily?id=" + app.systemid;
      let resp = await fetch(dailyUrl);
      if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
      let text = await resp.text();
      let lines = text.split("\n");
      let headers = lines[0].split(",").map((f) => f.trim());
      // prepare data
      for (let c of headers) {
        data[c] = [];
      }
      for (let i = 1; i < lines.length; i++) {
        let parts = lines[i].split(",");
        if (parts.length != headers.length) continue;
        let timestamp = parseFloat(parts[1]) * 1000;
        for (let j = 1; j < parts.length; j++) {
          let val = parseFloat(parts[j]);
          data[headers[j]].push([timestamp, val]);
        }
      }
      data["series"] = [];
      let xData = data[app.selected_xaxis];
      let yData = data[app.selected_yaxis];
      if (xData && yData) {
        for (let k = 0; k < xData.length; k++) {
          let xVal = xData[k][1];
          let yVal = yData[k][1];
          data["series"].push([xVal, yVal, k]);
        }
      }
      draw();
    } catch (err) {
      console.error("Error loading daily stats:", err);
    }
  }

  function draw() {
    // push new URL state
    let newurl =
      window.location.protocol +
      "//" +
      window.location.host +
      window.location.pathname +
      "?id=" +
      app.systemid;
    if (app.selected_xaxis !== "running_outsideT_mean") {
      newurl += "&x=" + app.selected_xaxis;
    }
    if (app.selected_yaxis !== "running_flowT_mean") {
      newurl += "&y=" + app.selected_yaxis;
    }
    if (app.min_yaxis !== "auto") {
      newurl += "&min_yaxis=" + app.min_yaxis;
    }
    if (app.max_yaxis !== "auto") {
      newurl += "&max_yaxis=" + app.max_yaxis;
    }
    if (app.min_xaxis !== "auto") {
      newurl += "&min_xaxis=" + app.min_xaxis;
    }
    if (app.max_xaxis !== "auto") {
      newurl += "&max_xaxis=" + app.max_xaxis;
    }
    window.history.pushState({ path: newurl }, "", newurl);

    let xDef = stats_schema[app.selected_xaxis] || {};
    let yDef = stats_schema[app.selected_yaxis] || {};
    let xaxisLabel = (xDef.group || "") + ": " + (xDef.name || "");
    let yaxisLabel = (yDef.group || "") + ": " + (yDef.name || "");

    let options = {
      series: {},
      xaxis: { axisLabel: xaxisLabel },
      yaxis: { axisLabel: yaxisLabel },
      grid: { hoverable: true, clickable: true },
      axisLabels: { show: true }
    };

    if (app.min_yaxis !== "auto") {
      options.yaxis.min = parseFloat(app.min_yaxis);
    }
    if (app.max_yaxis !== "auto") {
      options.yaxis.max = parseFloat(app.max_yaxis);
    }
    if (app.min_xaxis !== "auto") {
      options.xaxis.min = parseFloat(app.min_xaxis);
    }
    if (app.max_xaxis !== "auto") {
      options.xaxis.max = parseFloat(app.max_xaxis);
    }

    let flotData = [
      {
        data: data["series"],
        color: "blue",
        lines: { show: false },
        points: { show: true, radius: 2 }
      }
    ];

    $.plot("#placeholder", flotData, options);
  }

  $(window).resize(() => {
    resize();
  });

  function resize() {
    let w = $("#placeholder").width();
    let h = w * 1.2;
    if (h > 600) h = 600;
    $("#placeholder").height(h);
    draw();
  }

  // simple flot tooltip
  let prevPoint = null;
  $("#placeholder").bind("plothover", (event, pos, item) => {
    if (item) {
      if (prevPoint !== item.datapoint) {
        prevPoint = item.datapoint;
        $("#tooltip").remove();
        let x = item.datapoint[0];
        let y = item.datapoint[1];
        let str = `X: ${x.toFixed(1)}<br>Y: ${y.toFixed(1)}`;
        showTip(item.pageX, item.pageY, str);
      }
    } else {
      $("#tooltip").remove();
      prevPoint = null;
    }
  });

  function showTip(x, y, contents) {
    let offset = 10;
    let elem = $(`<div id="tooltip">${contents}</div>`).css({
      position: "absolute",
      color: "#000",
      display: "none",
      "font-weight": "bold",
      border: "1px solid #000",
      padding: "2px",
      "background-color": "#fff",
      opacity: "0.8"
    });
    $("body").append(elem);
    let top = y - elem.height() - offset;
    let left = x - elem.width() - offset;
    if (top < 0) top = 0;
    if (left < 0) left = 0;
    elem.css({ top, left }).fadeIn(200);
  }
</script>

</body>
</html>
