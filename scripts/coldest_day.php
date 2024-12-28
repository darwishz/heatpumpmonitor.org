<?php
$dir = dirname(__FILE__);
chdir("$dir/../www");

// Load required modules
require "Lib/load_database.php";
require "Modules/user/user_model.php";
require "Modules/system/system_model.php";
require "Modules/system/system_stats_model.php";

// Initialize objects
$user = new User($mysqli, false);
$system = new System($mysqli);
// System stats model is used for loading system stats data
$system_stats = new SystemStats($mysqli,$system);

// Configuration
$start_1year_ago = time() - 60 * 60 * 24 * 365; // Start date: one year ago
$temperature_min = -10; // Min outside temperature
$temperature_max = 5;   // Max outside temperature
$timezone = 'Europe/London'; // Timezone for date formatting

// Get system data
$systems = $system->list_admin();

foreach ($systems as $system_row) {
    $system_id = $system_row->id;
    $query = "SELECT * FROM system_stats_daily 
              WHERE `id` = '$system_id' 
              AND `timestamp` > '$start_1year_ago' 
              ORDER BY `combined_outsideT_mean` ASC 
              LIMIT 50";

    $result = $mysqli->query($query);

    // Check for query errors
    if (!$result) {
        error_log("Error executing query: " . $mysqli->error);
        continue; // Skip to the next system if the query fails
    }

    $n = 0;

    while ($row = $result->fetch_object()) {
        $room_temp = $row->combined_roomT_mean;
        $outside_temp = $row->combined_outsideT_mean;
        $cop = $row->combined_cop;
        $flow_temp = $row->running_flowT_mean;
        $room_minus_outside = $room_temp - $outside_temp;

        if ($outside_temp > $temperature_min && $outside_temp < $temperature_max && $cop > 0) {
            $date = new DateTime();
            $date->setTimezone(new DateTimeZone($timezone));
            $date->setTimestamp($row->timestamp);

            // Update system_meta measured_outside_temp_coldest_day
            $update_outside_temp = $mysqli->query(
                "UPDATE system_meta 
                 SET measured_outside_temp_coldest_day = '$outside_temp' 
                 WHERE `id` = '$system_id'"
            );
            if (!$update_outside_temp) {
                error_log("Failed to update outside temp for system $system_id: " . $mysqli->error);
            }

            // Update system_meta measured_mean_flow_temp_coldest_day
            $update_flow_temp = $mysqli->query(
                "UPDATE system_meta 
                 SET measured_mean_flow_temp_coldest_day = '$flow_temp' 
                 WHERE `id` = '$system_id'"
            );
            if (!$update_flow_temp) {
                error_log("Failed to update flow temp for system $system_id: " . $mysqli->error);
            }

            // Log the result
            echo "$system_id\t$outside_temp\t$room_temp\t$room_minus_outside\t$cop\t" . $date->format('Y-m-d') . "\n";

            $n++;
            if ($n >= 1) {
                break; // Stop after the first valid result
            }
        }
    }
}
// $start_1year_ago = time() - 60*60*24*365;

// $data = $system->list_admin();
// foreach ($data as $row) {
//     $systemid = $row->id;
//     // print "System: " . $row->id." ".$row->name . "\n";

//     $n = 0;

//     $result = $mysqli->query("SELECT * FROM system_stats_daily WHERE `id` = '$systemid' AND `timestamp` > '$start_1year_ago' ORDER BY `combined_outsideT_mean` ASC LIMIT 50");

//     while ($row = $result->fetch_object()) {
//         $roomT = $row->combined_roomT_mean;
//         $outsideT = $row->combined_outsideT_mean;
//         $combined_cop = $row->combined_cop;
//         $flowT = $row->running_flowT_mean;

//         $room_minus_outside = $roomT - $outsideT;

//         if ($outsideT>-10 && $outsideT<5 && $combined_cop>0) {

//             $date = new DateTime();
//             // London
//             $date->setTimezone(new DateTimeZone('Europe/London'));
//             $date->setTimestamp($row->timestamp);

//             // update system_meta measured_outside_temp_coldest_day
//             $mysqli->query("UPDATE system_meta SET measured_outside_temp_coldest_day = '$outsideT' WHERE `id` = '$systemid'");

//             // update system_meta measured_mean_flow_temp_coldest_day
//             $mysqli->query("UPDATE system_meta SET measured_mean_flow_temp_coldest_day = '$flowT' WHERE `id` = '$systemid'");

//             echo $systemid."\t".$outsideT . "\t" . $roomT . "\t" . $room_minus_outside. "\t" .$combined_cop . "\t" . $date->format('Y-m-d') . "\n";
//             $n++;
//             if ($n>=1) {
//                 break;
//             }
//         }
//     }

//     // die;
// }
?>