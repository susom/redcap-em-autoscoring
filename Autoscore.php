<?php
namespace Stanford\Autoscore;

use \Exception;
use \REDCap;

require_once "emLoggerTrait.php";
require_once "classes/autoscoreAlgorithm.php";
require_once "classes/autoscoreSetup.php";

class Autoscore extends \ExternalModules\AbstractExternalModule
{

    use emLoggerTrait;

    public function __construct()
    {
        parent::__construct();
    }

    public function redcap_save_record($project_id, $record = NULL,  $instrument,  $event_id,  $group_id = NULL,  $survey_hash = NULL,  $response_id = NULL, $repeat_instance) {

        global $USERID;

        $setup_pid = $this->getSystemSetting('autoscore-project');
        if ($setup_pid == $project_id) {

            $this->emDebug("In setup project record $record");
            // First check to see if this is the Autoscore Setup Project.  If so, we are setting up a project
            // with an autoscoring config
            try {
                $as = new autoscoreSetup($project_id, $record, $instrument, $event_id, $this);
                $status = $as->storeSetup();
                $this->emDebug("Back from storeSetup with status $status");
            } catch (Exception $ex) {
                $this->emError("Could not create class autoscoreAlgorithm - exception: " . $ex);
            }

            $this->emDebug("This is the autoscore Setup project $project_id");

        } else {

            // Find the event name from the event id
            $event_name = REDCap::getEventNames(true, false, $event_id);
            if ($event_name == false) $event_name = '';

            // Package up the data and send to index.php
            $autoscore_url = $this->getUrl("index.php", true, true) . "&pid=" . $project_id;
            $autoscore_params = array(
                            "project_id"            => $project_id,
                            "username"              => $USERID,
                            "instrument"            => $instrument,
                            "record"                => $record,
                            "repeat_instance"       => $repeat_instance,
                            "redcap_event_name"     => $event_name
                );
            $this->emDebug("JSON parameter list: " . json_encode($autoscore_params));

            $response = http_post($autoscore_url, $autoscore_params);
            $this->emDebug("Return from http_post: " . json_encode($response));
        }

    }

}
