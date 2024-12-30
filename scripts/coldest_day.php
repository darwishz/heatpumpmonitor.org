$dir = dirname(__FILE__);
chdir("$dir/../www");

// Load required modules
require_once __DIR__ . '/Modules/user/user_model.php';
require_once __DIR__ . '/Modules/system/system_model.php';
require_once __DIR__ . '/Modules/system/system_stats_model.php';

use Modules\User\User;
use Modules\System\System;
use Modules\System\SystemStats;

// Initialize database connection
$mysqli = new mysqli('host', 'username', 'password', 'database');
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Initialize objects
$user = new User($mysqli, false);
$system = new System($mysqli);
$system_stats = new SystemStats($mysqli, $system);

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
    if ($result instanceof mysqli_result) {
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
                $mysqli->query(
                    "UPDATE system_meta 
                     SET measured_outside_temp_coldest_day = '$outside_temp' 
                     WHERE `id` = '$system_id'"
                );

                // Update system_meta measured_mean_flow_temp_coldest_day
                $mysqli->query(
                    "UPDATE system_meta 
                     SET measured_mean_flow_temp_coldest_day = '$flow_temp' 
                     WHERE `id` = '$system_id'"
                );

                echo "$system_id\t$outside_temp\t$room_temp\t$room_minus_outside\t$cop\t" . $date->format('Y-m-d') . "\n";

                $n++;
                if ($n >= 1) {
                    break; // Stop after the first valid result
                }
            }
        }
    } else {
        error_log("Unexpected query result for system ID $system_id: " . $mysqli->error);
    }
}