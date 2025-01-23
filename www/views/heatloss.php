<!-- heatloss.php -->
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Heat demand explorer</title>
  <script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
  <script src="https://code.jquery.com/jquery-3.6.3.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/flot/0.8.3/jquery.flot.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/flot/0.8.3/jquery.flot.time.min.js"></script>
  <script src="Lib/jquery.flot.axislabels.js"></script>
</head>
<body>

<div id="app">
  <div style=" background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
    <div class="container-fluid">
      <h3>Heat demand explorer</h3>
    </div>
  </div>

  <div class="container-fluid" style="margin-top:20px; max-width:1320px">
    <!-- system selection row -->
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
      <div class="col-lg-2 col-sm-4 col-6">
        <div class="input-group mb-3">
          <span class="input-group-text">Elec</span>
          <input type="text" class="form-control" :value="total_elec_kwh | toFixed(0)" disabled />
          <span class="input-group-text">kWh</span>
        </div>
      </div>
      <div class="col-lg-2 col-sm-4 col-6">
        <div class="input-group mb-3">
          <span class="input-group-text">Heat</span>
          <input type="text" class="form-control" :value="total_heat_kwh | toFixed(0)" disabled />
          <span class="input-group-text">kWh</span>
        </div>
      </div>
      <div class="col-lg-2 col-sm-4 col-12">
        <div class="input-group mb-3">
          <span class="input-group-text">COP</span>
          <input type="text" class="form-control" :value="total_cop | toFixed(2)" disabled />
        </div>
      </div>
    </div>

    <div class="row" style="margin-right:-5px">
      <div id="placeholder" style="width:100%; height:600px; margin-bottom:20px"></div>
    </div>

    <!-- Controls for base_DT, design_DT, etc. -->
    <div class="row mb-3">
      <div class="col-lg-3 col-md-6">
        <div class="input-group mb-3">
          <span class="input-group-text">Base DT</span>
          <input type="text" class="form-control" v-model.number="base_DT" @change="draw" />
          <span class="input-group-text">°K</span>
        </div>
      </div>
      <div class="col-lg-3 col-md-6">
        <div class="input-group mb-3">
          <span class="input-group-text">Design DT</span>
          <input type="text" class="form-control" v-model.number="design_DT" @change="draw" />
          <span class="input-group-text">°K</span>
        </div>
      </div>
      <div class="col-lg-3 col-md-6">
        <div class="input-group mb-3">
          <span class="input-group-text">Heat demand</span>
          <input type="text" class="form-control" v-model.number="measured_heatloss" @change="draw" />
          <span class="input-group-text">kW</span>
        </div>
      </div>
      <div class="col-lg-3 col-md-6">
        <div class="input-group">
          <span class="input-group-text">±</span>
          <input type="text" class="form-control" v-model.number="measured_heatloss_range" @change="draw" />
          <span class="input-group-text">kW</span>
          <button class="btn btn-primary" @click="save_heat_loss" :disabled="!enable_save">
            Save
          </button>
        </div>
      </div>
    </div>

    <div class="row mb-3">
      <div class="col">
        <div class="input-group">
          <span class="input-group-text">Filter out below</span>
          <input
            type="text"
            class="form-control"
            v-model.number="auto_min_DT"
            @change="draw"
          />
          <span class="input-group-text">°K DT</span>
          <button class="btn btn-primary" @click="auto_fit">Auto fit</button>
        </div>
      </div>
    </div>

    <div class="row mb-3">
      <div class="col-lg-4 col-md-6">
        <div class="input-group mb-3">
          <span class="input-group-text">Calculated heat loss</span>
          <input
            type="text"
            class="form-control"
            v-model.number="calculated_heatloss"
            :disabled="!enable_save"
            @change="draw"
          />
          <span class="input-group-text">kW</span>
        </div>
      </div>
      <div class="col-lg-4 col-md-6">
        <div class="input-group mb-3">
          <span class="input-group-text">Heat pump datasheet capacity</span>
          <input
            type="text"
            class="form-control"
            v-model.number="datasheet_hp_max"
            :disabled="!enable_save"
            @change="draw"
          />
          <span class="input-group-text">kW</span>
        </div>
      </div>
      <div class="col-lg-4 col-md-6">
        <div class="input-group mb-3">
          <span class="input-group-text">Max capacity test result</span>
          <input
            type="text"
            class="form-control"
            v-model.number="measured_hp_max"
            :disabled="!enable_save"
            @change="draw"
          />
          <span class="input-group-text">kW</span>
          <button class="btn btn-primary" @click="save_capacity_figures" :disabled="!enable_save">
            Save
          </button>
        </div>
      </div>
    </div>

    <div class="row mb-3">
      <div class="col-lg-4 col-md-6">
        <div class="input-group mb-3">
          <span class="input-group-text">Fixed room temperature</span>
          <span class="input-group-text">
            <input type="checkbox" v-model="fixed_room_tmp_enable" @change="load" />
          </span>
          <input
            type="text"
            class="form-control"
            v-model.number="fixed_room_tmp"
            @change="load"
          />
          <span class="input-group-text">°C</span>
        </div>
      </div>
    </div>

    <div class="row">
      <p>Each datapoint shows the average heat output over a 24 hour period...</p>
    </div>
  </div>
</div>

<script>
  var userid = <?php echo $userid; ?>;
  var systemid = <?php echo $systemid; ?>;
  var path = window.path || "";
  var mode = "combined";
  var data = {};
  var systemid_map = {};
  var max_heat = 0;

  new Vue({
    el: "#app",
    data() {
      return {
        enable_save: false,
        systemid: systemid,
        system_list: [],
        total_elec_kwh: 0,
        total_heat_kwh: 0,
        total_cop: 0,
        base_DT: 4,
        design_DT: 23,
        measured_heatloss: 0,
        measured_heatloss_range: 0.5,
        auto_min_DT: 0,
        calculated_heatloss: 0,
        datasheet_hp_max: 0,
        measured_hp_max: 0,
        fixed_room_tmp_enable: 0,
        fixed_room_tmp: 20
      };
    },
    methods: {
      async change_system() {
        this.fixed_room_tmp_enable = 0;
        await load();
      },
      load() {
        return load();
      },
      draw() {
        draw();
      },
      save_heat_loss() {
        let payload = {
          id: this.systemid,
          data: {
            measured_base_DT: this.base_DT,
            measured_design_DT: this.design_DT,
            measured_heat_loss: this.measured_heatloss,
            measured_heat_loss_range: this.measured_heatloss_range
          }
        };
        fetch(path + "system/save", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(payload)
        })
          .then((resp) => resp.json())
          .then((resObj) => {
            alert(resObj.message || "Saved");
          })
          .catch((err) => console.error("Error saving heat loss:", err));
      },
      save_capacity_figures() {
        let payload = {
          id: this.systemid,
          data: {
            hp_max_output: this.datasheet_hp_max,
            heat_loss: this.calculated_heatloss,
            hp_max_output_test: this.measured_hp_max
          }
        };
        fetch(path + "system/save", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(payload)
        })
          .then((resp) => resp.json())
          .then((resObj) => {
            alert(resObj.message || "Saved");
          })
          .catch((err) => console.error("Error saving capacity figures:", err));
      },
      async next_system(direction) {
        this.fixed_room_tmp_enable = 0;
        let idx = systemid_map[this.systemid];
        if (idx === undefined) return;
        idx += direction;
        if (idx < 0 || idx >= this.system_list.length) return;
        this.systemid = this.system_list[idx].id;
        await load();
      },
      auto_fit() {
        // line of best fit ignoring dt < auto_min_DT
        let points = data["heat_vs_dt"] || [];
        let min_x = parseFloat(this.auto_min_DT) || 0;
        let sums = { xSum: 0, ySum: 0, xySum: 0, xxSum: 0, n: 0 };
        for (let [x, y] of points) {
          if (x >= min_x) {
            sums.xSum += x;
            sums.ySum += y;
            sums.xySum += x * y;
            sums.xxSum += x * x;
            sums.n++;
          }
        }
        if (sums.n < 2) return;
        let m = (sums.n * sums.xySum - sums.xSum * sums.ySum) /
                (sums.n * sums.xxSum - sums.xSum * sums.xSum);
        let b = (sums.ySum - m * sums.xSum) / sums.n;

        this.design_DT = 23;
        this.measured_heatloss = parseFloat((m * this.design_DT + b).toFixed(2));
        this.base_DT = parseFloat(((0 - b) / m).toFixed(1));
        if (this.base_DT < 0) {
          // fallback
          let slope = slopeFromZero(points);
          this.base_DT = 0;
          this.measured_heatloss = parseFloat((slope * this.design_DT).toFixed(2));
        }
        draw();

        function slopeFromZero(pts) {
          let xy = 0, xx = 0;
          for (let [x, y] of pts) {
            xy += x * y;
            xx += x * x;
          }
          if (!xx) return 0;
          return xy / xx;
        }
      }
    },
    filters: {
      toFixed(value, dp) {
        if (!value) value = 0;
        return parseFloat(value).toFixed(dp);
      }
    },
    async mounted() {
      // fetch system list
      try {
        let resp = await fetch(path + "system/list/public.json");
        if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
        let arr = await resp.json();
        arr.sort((a, b) => a.location.localeCompare(b.location));
        this.system_list = arr;
        for (let i = 0; i < arr.length; i++) {
          systemid_map[arr[i].id] = i;
        }
        await load();
        resize();
      } catch (err) {
        console.error("Error loading system list:", err);
      }
    }
  });

  async function load() {
    // check write access
    try {
      let resp = await fetch(path + "system/hasaccess?id=" + app.systemid);
      if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
      let canWrite = await resp.text();
      app.enable_save = parseInt(canWrite) ? true : false;
    } catch (err) {
      console.warn("Error checking access:", err);
    }

    let idx = systemid_map[app.systemid];
    if (idx === undefined) return;
    let sys = app.system_list[idx];
    // update local references
    hp_output = sys.hp_output;
    heat_loss = sys.heat_loss;
    hp_max = sys.hp_max_output;

    app.measured_heatloss = sys.measured_heat_loss || 0;
    app.measured_heatloss_range = sys.measured_heat_loss_range || 0.5;
    app.calculated_heatloss = sys.heat_loss || 0;
    app.datasheet_hp_max = sys.hp_max_output || 0;
    app.measured_hp_max = sys.hp_max_output_test || 0;
    app.base_DT = sys.measured_base_DT || 4;
    app.design_DT = sys.measured_design_DT || 23;

    // fetch daily stats
    let fields = [
      "timestamp",
      mode + "_heat_mean",
      mode + "_roomT_mean",
      mode + "_outsideT_mean",
      "running_flowT_mean",
      "running_returnT_mean",
      "combined_elec_kwh",
      "combined_heat_kwh"
    ];
    let query = fields.join(",");
    let fetchUrl =
      path + "system/stats/daily?id=" + app.systemid + "&fields=" + query;
    try {
      let resp = await fetch(fetchUrl);
      if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
      let text = await resp.text();
      let lines = text.split("\n");
      for (let f of fields) {
        data[f] = [];
      }
      for (let i = 1; i < lines.length; i++) {
        let parts = lines[i].split(",");
        if (parts.length !== fields.length) continue;
        let ts = parseFloat(parts[0]) * 1000;
        for (let j = 1; j < parts.length; j++) {
          let val = parseFloat(parts[j]) || 0;
          data[fields[j]].push([ts, val]);
        }
      }

      // check if we have valid roomT
      let roomTarr = data[mode + "_roomT_mean"];
      let valid_room_temp = roomTarr.some(([t, val]) => val > 0);
      if (!valid_room_temp) {
        app.fixed_room_tmp_enable = 1;
        alert(
          "No room temperature data found; enabling fixed room temp (default 20°C)."
        );
      }
      if (app.fixed_room_tmp_enable) {
        for (let i = 0; i < roomTarr.length; i++) {
          roomTarr[i][1] = app.fixed_room_tmp;
        }
      }

      // create heat_vs_dt
      max_heat = app.calculated_heatloss;
      if (max_heat < hp_output) max_heat = hp_output;
      if (max_heat < hp_max) max_heat = hp_max;
      data["heat_vs_dt"] = [];

      let total_elec_kwh = 0;
      let total_heat_kwh = 0;
      let heatArr = data[mode + "_heat_mean"];
      for (let i = 0; i < heatArr.length; i++) {
        let rVal = data[mode + "_roomT_mean"][i][1];
        let oVal = data[mode + "_outsideT_mean"][i][1];
        if (rVal <= 0) continue;
        let dt = rVal - oVal;
        if (dt <= 0) continue;
        let y = heatArr[i][1] * 0.001;
        data["heat_vs_dt"].push([dt, y, i]);
        if (y > max_heat) max_heat = y;

        total_elec_kwh += data["combined_elec_kwh"][i][1] || 0;
        total_heat_kwh += data["combined_heat_kwh"][i][1] || 0;
      }
      app.total_elec_kwh = total_elec_kwh;
      app.total_heat_kwh = total_heat_kwh;
      app.total_cop = total_heat_kwh && total_elec_kwh
        ? total_heat_kwh / total_elec_kwh
        : 0;
      draw();
    } catch (err) {
      console.error("Error fetching daily stats for heatloss:", err);
    }
  }

  function draw() {
    let options = {
      series: {},
      xaxis: {
        axisLabel: "Room - Outside Temperature (°C)",
        max: app.design_DT
      },
      yaxis: {
        min: 0,
        max: max_heat * 1.1,
        axisLabel: "Heatpump heat output (kW)"
      },
      grid: { hoverable: true, clickable: true },
      axisLabels: { show: true }
    };
    let mainSeries = [
      {
        data: data["heat_vs_dt"] || [],
        color: "blue",
        lines: { show: false },
        points: { show: true, radius: 2 }
      }
    ];

    // lines for measured, hp_output, etc.
    mainSeries.push({
      data: [
        [0, app.calculated_heatloss],
        [app.design_DT, app.calculated_heatloss]
      ],
      color: "grey",
      lines: { show: true }
    });
    mainSeries.push({
      data: [
        [0, hp_output],
        [app.design_DT, hp_output]
      ],
      color: "black",
      lines: { show: true }
    });
    if (app.datasheet_hp_max > 0) {
      mainSeries.push({
        data: [
          [0, app.datasheet_hp_max],
          [app.design_DT, app.datasheet_hp_max]
        ],
        color: "#aa0000",
        lines: { show: true }
      });
    }
    if (app.measured_hp_max > 0) {
      mainSeries.push({
        data: [
          [0, app.measured_hp_max],
          [app.design_DT, app.measured_hp_max]
        ],
        color: "#ddaaaa",
        lines: { show: true }
      });
    }

    if (app.measured_heatloss > 0) {
      mainSeries.push({
        data: [
          [app.base_DT, 0],
          [app.design_DT, app.measured_heatloss]
        ],
        color: "#aaa",
        lines: { show: true }
      });
      let r = parseFloat(app.measured_heatloss_range);
      if (!isNaN(r) && r !== 0) {
        mainSeries.push({
          data: [
            [app.base_DT, r],
            [app.design_DT, app.measured_heatloss + r]
          ],
          color: "#ddd",
          lines: { show: true }
        });
        mainSeries.push({
          data: [
            [app.base_DT, -r],
            [app.design_DT, app.measured_heatloss - r]
          ],
          color: "#ddd",
          lines: { show: true }
        });
      }
    }

    let chart = $.plot("#placeholder", mainSeries, options);
    let placeholder = $("#placeholder");
    if (hp_output > 0) {
      let offset = chart.pointOffset({ x: 0, y: hp_output });
      placeholder.append(
        `<div style='position:absolute;left:${offset.left + 4}px;top:${
          offset.top - 23
        }px;color:#666;font-size:smaller'>Heatpump badge capacity</div>`
      );
    }
    if (app.datasheet_hp_max > 0) {
      let offset2 = chart.pointOffset({ x: 0, y: app.datasheet_hp_max });
      let yDiff = hp_output - app.datasheet_hp_max;
      let tOffset = yDiff < 0.5 ? 5 : -23;
      placeholder.append(
        `<div style='position:absolute;left:${offset2.left + 4}px;top:${
          offset2.top + tOffset
        }px;color:#666;font-size:smaller'>Heatpump datasheet capacity</div>`
      );
    }
    if (app.measured_hp_max > 0) {
      let offset3 = chart.pointOffset({ x: 0, y: app.measured_hp_max });
      placeholder.append(
        `<div style='position:absolute;left:${offset3.left + 4}px;top:${
          offset3.top - 23
        }px;color:#666;font-size:smaller'>Max capacity test result</div>`
      );
    }
    if (app.calculated_heatloss > 0) {
      let offset4 = chart.pointOffset({ x: 0, y: app.calculated_heatloss });
      placeholder.append(
        `<div style='position:absolute;left:${offset4.left + 4}px;top:${
          offset4.top - 23
        }px;color:#666;font-size:smaller'>Heat loss value on form</div>`
      );
    }
  }

  // Flot tooltip
  let prevPoint = null;
  $("#placeholder").bind("plothover", (event, pos, item) => {
    if (item) {
      if (prevPoint !== item.datapoint) {
        prevPoint = item.datapoint;
        $("#tooltip").remove();
        let DT = item.datapoint[0];
        let HEAT = item.datapoint[1];
        let str = `Heat: ${HEAT.toFixed(3)} kW<br>DT: ${DT.toFixed(1)} °K<br>`;
        let idx = data["heat_vs_dt"][item.dataIndex][2];
        let rVal = data[mode + "_roomT_mean"][idx][1].toFixed(1);
        let oVal = data[mode + "_outsideT_mean"][idx][1].toFixed(1);
        let fVal = data["running_flowT_mean"][idx][1].toFixed(1);
        let retVal = data["running_returnT_mean"][idx][1].toFixed(1);
        str += `Room: ${rVal} °C<br>`;
        str += `Outside: ${oVal} °C<br>`;
        str += `FlowT: ${fVal} °C<br>`;
        str += `ReturnT: ${retVal} °C<br>`;
        let d = new Date(data[mode + "_heat_mean"][idx][0]);
        str += `${d.getDate()} ${d.toLocaleString("default", {
          month: "short"
        })} ${d.getFullYear()}<br>`;
        showTip(item.pageX, item.pageY, str);
      }
    } else {
      $("#tooltip").remove();
      prevPoint = null;
    }
  });

  $(window).resize(() => {
    resize();
  });

  function resize() {
    let width = $("#placeholder").width();
    let height = width * 1.2;
    if (height > 600) height = 600;
    $("#placeholder").height(height);
    draw();
  }

  function showTip(x, y, html) {
    let offset = 10;
    let $d = $(`<div id="tooltip">${html}</div>`).css({
      position: "absolute",
      color: "#000",
      display: "none",
      "font-weight": "bold",
      border: "1px solid #888",
      padding: "2px",
      "background-color": "#fff",
      opacity: "0.85"
    });
    $("body").append($d);
    let top = y - $d.height() - offset;
    let left = x - $d.width() - offset;
    if (top < 0) top = 0;
    if (left < 0) left = 0;
    $d.css({ top, left }).fadeIn(200);
  }

  function calculateLineOfBestFit(dataPoints, min_x) {
    let xSum = 0,
      ySum = 0,
      xySum = 0,
      xxSum = 0;
    let n = 0;
    for (let [x, y] of dataPoints) {
      if (x >= min_x) {
        xSum += x;
        ySum += y;
        xySum += x * y;
        xxSum += x * x;
        n++;
      }
    }
    if (n < 2) return { m: 0, b: 0 };
    let m = (n * xySum - xSum * ySum) / (n * xxSum - xSum * xSum);
    let b = (ySum - m * xSum) / n;
    return { m, b };
  }

  function calculateSlopeWithZeroIntercept(dataPoints) {
    let xySum = 0,
      xxSum = 0;
    for (let [x, y] of dataPoints) {
      xySum += x * y;
      xxSum += x * x;
    }
    if (!xxSum) return 0;
    return xySum / xxSum;
  }
</script>

</body>
</html>
