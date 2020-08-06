<?php
namespace Stanford\Autoscore;
/** @var \Stanford\Autoscore\Autoscore $module */

use \Exception;
use \REDCap;

$params = isset($_POST) && !empty($_POST) ? $_POST : null;
$pid = isset($_GET['pid']) && !empty($_GET['pid']) ? $_GET['pid'] : null;

if (!is_null($params)) {

    // These are the parameters from MassDET
    $selected_instrument = $params['instrument'];

    // Find the configuration that uses this instrument
    $configs = $module->getSubSettings("algorithms");
    foreach ($configs as $instance => $config) {

        // Check the config setting form against the DET form name to see if this is the config
        $autoscore_form = $config['form-name'];
        if ($autoscore_form == $selected_instrument) {

            // Extract the list of configuration setup parameters
            $record = $params['record'];
            $event_name = $params['redcap_event_name'];
            $repeat_instance = $params['repeat_instance'];
            if (!is_null($event_name) && !empty($event_name)) {
                $event_id = REDCap::getEventIdFromUniqueEvent($event_name);
            } else {
                $event_id = null;
            }

            // Find the autoscore configuration project id and find the record in the
            // setup project which holds the autoscore configuration
            $setup_pid = $module->getSystemSetting('autoscore-project');
            $setup_id = $config['config-id'];

            try {
                $as = new autoscoreAlgorithm($pid, $record, $selected_instrument, $event_id, $repeat_instance, $setup_pid, $setup_id, $module);
                $status = $as->runAlgorithm();
            } catch (Exception $ex) {
                $module->emError("Cannot create the autoscoreAlgorithm class for project $pid form $autoscore_form");
            }
        }
    }

}


?>
