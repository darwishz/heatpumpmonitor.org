<?php

chdir("/var/www/heatpumpmonitororg");
require "www/Lib/load_database.php";

require("Modules/user/user_model.php");
$user = new User($mysqli);

require ("Modules/system/system_model.php");
$system = new System($mysqli);

require ("Modules/system/system_stats_model.php");
$system_stats = new SystemStats($mysqli,$system);

$data = $system->list_admin();
foreach ($data as $row) {
    $userid = (int) $row->userid;
    //if ($user->emoncmsorg_link_exists($userid)) continue;
    // if ($row->id!=36) continue;

    if ($user_data = $user->get($userid)) {
        print json_encode($user_data) . " ".$row->url."\n\n";

        // timestamp start of July
        $date = new DateTime();
        // set timezone Europe/London
        $date->setTimezone(new DateTimeZone('Europe/London'));

        // get start timestamp of current month
        $date->setDate(date('Y'), date('m'), 1);
        $date->setTime(0, 0, 0);
        $start = $date->getTimestamp();

        // Print $date formatted e.g 1st of January 2023
        print $date->format('jS \of F Y') . "\n";

        // +1 month
        $date->modify('+1 month');
        $end = $date->getTimestamp();

        // print "start: $start end: $end\n";
        $stats = $system_stats->load_from_url($row->url,$start,$end);
        if (isset($stats['success']) && !$stats['success']) {
            print "ERROR: ".$stats['message']."\n";
            continue;
        }

        print json_encode($stats['stats'])."\n";

        $system_stats->save_monthly($row->id,$start,$stats['stats']);

        print json_encode($stats,JSON_PRETTY_PRINT) . "\n";
    }
}