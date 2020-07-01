<?php
namespace Stanford\Autoscore;

use \Exception;

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

        $setup_pid = $this->getSystemSetting('autoscore-project');
        $this->emDebug("In project " . $project_id);
        if ($setup_pid == $project_id) {

            $this->emDebug("In setup project record $record");
            // First check to see if this is the Autoscore Setup Project.  If so, we are setting up a project
            // with an autoscoring config
            try {
                $this->emDebug("Creating class autoscoreSetup");
                $as = new autoscoreSetup($project_id, $record, $instrument, $event_id, $this);
                $this->emDebug("Back from constructor");
                $status = $as->storeSetup();
                $this->emDebug("Back from storeSetup with status $status");
            } catch (Exception $ex) {
                $this->emError("Could not create class autoscoreAlgorithm - exception: " . $ex);
            }

            $this->emDebug("This is the autoscore Setup project $project_id");

        } else {

            // This is not the setup project, so we are in a project that has been setup for autoscoring
            // Retrieve the Reward configurations
            $configs = $this->getSubSettings("algorithms");
            foreach ($configs as $instance => $info) {
                $setup_id = $info['config-id'];
                $autoscore_form = $info['form-name'];
                if ($autoscore_form == $instrument) {
                    $this->emDebug("Record $record running algorithm " . json_encode($info));
                    try {
                        $as = new autoscoreAlgorithm($project_id, $record, $instrument, $event_id, $repeat_instance, $setup_pid, $setup_id, $this);
                        $status = $as->runAlgorithm();
                    } catch (Exception $ex) {
                        $this->emError("Could not create class autoscoreAlgorithm - exception: " . $ex);
                    }
                }
            }

        }
    }

}
