<?php

define('EMONCMS_EXEC', 1);

error_reporting(E_ALL);
ini_set('display_errors', 'on');

require "core.php";
require "route.php";
require("user_model.php");
$user = new User();

$path = get_application_path(false);
$route = new Route(get('q'), server('DOCUMENT_ROOT'), server('REQUEST_METHOD'));

$session = $user->emon_session_start();

switch ($route->controller) {

    case "":
        $route->format = "html";
        $output = view("views/main.html", array());
        break;
        
    case "stats":
        $route->format = "html";
        $output = view("views/stats.html", array());
        break;
        
    case "costs":
        $route->format = "html";
        $output = view("views/costs.html", array());
        break;
        
    case "graph":
        $route->format = "html";
        $output = view("views/graph.html", array());
        break;

    case "compare":
        $route->format = "html";
        $output = view("views/compare.html", array());
        break;

    case "form":
        $route->format = "html";
        $systemid = (int) $route->action;
        
        $data = file_get_contents("data.json");
        $data_obj = json_decode($data);
        if (isset($data_obj[$systemid])) {
            $system = $data_obj[$systemid];
            $output = view("views/form.php", array("system"=>$system,"session"=>$session));
        }
        break;
        
    case "login":
        if ($route->format=="html") {
            $output = view("views/login.html", array());  
        } else if ($route->format=="json") {
            $output = $user->login(post("username"),post("password"));
        }
        break;
        
    case "api":
        $route->format = "json";
        $data = file_get_contents("data.json");
        $data_obj = json_decode($data);
        
        if (isset($_GET['system'])) {
            // Find ID
            $system = false;
            $id = (int) $_GET['system'];
            foreach ($data_obj as $key=>$row) {
                if ($row->id==$id) {
                    $system = $row;
                    break;
                }
            }
            if ($system) {
                if ($route->action=="data") {
                    
                    $url_parts = parse_url($system->url);
                    $server = $url_parts['scheme'] . '://' . $url_parts['host'];
                    # check if url was to /app/view instead of username
                    if (preg_match('/^(.*)\/app\/view$/', $url_parts['path'], $matches)) {
                      $getconfig = "$server$matches[1]/app/getconfig";
                    } else {
                      $getconfig = $server . $url_parts['path'] . "/app/getconfig";
                    }        
                    
                    $apikeystr = "";     
                    # if url has query string, pull out the readkey
                    if (isset($url_parts['query'])) {
                      parse_str($url_parts['query'], $url_args);
                      if (isset($url_args['readkey'])) {
                        $readkey = $url_args['readkey'];
                        $getconfig .= '?' . $url_parts['query'];
                        $apikeystr = "&apikey=".$readkey;
                      }
                    }
                    
                    $config = json_decode(file_get_contents($getconfig));
                    
                    $elec_feedid = (int) $config->config->heatpump_elec;
                    $heat_feedid = (int) $config->config->heatpump_heat;
                    $flowT_feedid = (int) $config->config->heatpump_flowT;
                    $returnT_feedid = (int) $config->config->heatpump_returnT;
                    $outsideT_feedid = (int) $config->config->heatpump_outsideT;

                    $output = $config->config;

                    $start = $_GET['start'];
                    $end = $_GET['end'];
                    $interval = $_GET['interval']; 
                    
                    if ($route->subaction=="all") {
                    
                        $result = json_decode(file_get_contents("$server/feed/data.json?ids=$elec_feedid,$heat_feedid,$outsideT_feedid,$flowT_feedid,$returnT_feedid&start=$start&end=$end&interval=$interval&average=1&skipmissing=0&timeformat=notime".$apikeystr));
                        
                        $output = array(
                          "elec"=>$result[0]->data,
                          "heat"=>$result[1]->data,
                          "outsideT"=>$result[2]->data,
                          "flowT"=>$result[3]->data,
                          "returnT"=>$result[4]->data
                        );   
                    }
                } else {
                    $output = $system;
                }
            } else {
                $output = array("success"=>false, "message"=>"invalid system id");
            }
        } else {
            $output = $data_obj;
        }
        
        break;
}

switch ($route->format) {

    case "html":
        echo view("theme/theme.php", array("content"=>$output, "route"=>$route));
        break;
        
    case "json":
        header('Content-Type: application/json');
        echo json_encode($output);   
        break; 
}
