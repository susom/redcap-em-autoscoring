<?php
namespace Stanford\Autoscore;
/** @var \Stanford\Autoscore\Autoscore $module */

use \REDCap;
use \Project;

class autoscoreSetup
{

    private $project_id, $record, $instrument, $event_id, $repeat_instance, $setup_pid, $setup_id, $module;

    // these are from the Autoscore Setup project
    private $setup_stored_pid, $algorithm, $req_source_fields, $req_result_fields,
            $form_list, $fields_in_form, $algorithm_details, $required_source_fields,
            $required_source_fields_missing, $required_result_fields,
            $data_dictionary, $project_form;

    public function __construct($project_id, $record, $instrument, $event_id,  $module)
    {
        // These are Autoscore project parameters
        $this->project_id                   = $project_id;
        $this->record                       = $record;
        $this->instrument                   = $instrument;
        $this->event_id                     = $event_id;
        $this->module                       = $module;
        $this->module->emDebug("In constructor: project $this->project_id, record $this->record, instrument $instrument, event $event_id");

        // Retrieve the information setup so far
        $retrieved_parameters = REDCap::getData($this->project_id, 'array', $this->record);
        $setup_parameters = $retrieved_parameters[$this->record][$this->event_id];

        $this->algorithm = $setup_parameters['algorithm'];
        $this->redcap_project_id = $setup_parameters['project_id'];
        $this->project_form = $setup_parameters['instrument_list'];

        $this->data_dictionary = new Project($this->redcap_project_id);
        $this->data_dictionary->loadMetadata();

        require_once "config.php";
    }

    public function storeSetup() {
        global $defined_algorithms;

        $status = true;
        $scoring_algorithm = $defined_algorithms[$this->algorithm];
        $this->module->emDebug("In storeSetup: ");

        # Verify that scoring algorithm from config is defined
        if (empty($scoring_algorithm)) {
            $msg = "The specified algorithm ({$this->algorithm}) is not defined in config.php.";
            $this->module->emError($msg);
            $job_log[] = $msg;
            $status = false;
        }

        # Verify that the scoring algorithm defined for the job actually exists
        if (!file_exists(CONFIG_ALGORITHM_PATH . $scoring_algorithm)) {
            $msg = "Unable to find the algorithm file (" . CONFIG_ALGORITHM_PATH . $scoring_algorithm . ") for {" . $this->algorithm . "} 
				as  defined in config.php.";
            $job_log[] = $msg;
            $this->module->emError($msg);
            $status = false;
        }

        if ($status) {
            $verify = true;
            $required_fields = array();
            $default_result_fields = array();
            $algorithm_summary = '';

            $algorithm_complete = include(CONFIG_ALGORITHM_PATH . $scoring_algorithm);
            $this->module->emDebug("back from algorithm - return status " . $algorithm_complete);
            $this->module->emDebug("Default result fields: " . json_encode($default_result_fields));
            $this->module->emDebug("Required fields: " . json_encode($required_fields));

            // We need to be in project context to get the following data
            $form_list = $this->getDataDictionaryForms();
            $this->module->emDebug("Form List: " . json_encode($form_list));
            $fields_in_form = $this->getFieldsInForm();
            $this->module->emDebug("Fields in Form: " . json_encode($fields_in_form));

            // Save the data needed for setup
            $results = array();
            $results['algorithm_details']       = $algorithm_summary;
            $results['form_list']               = implode(', ', $form_list);
            $results['required_source_fields']  = implode(', ', $required_fields);
            $results['required_result_fields']  = implode(', ', $default_result_fields);
            $results['fields_in_form']          = implode(', ', $fields_in_form);
            $data_to_save[$this->record][$this->event_id] = $results;
            $this->module->emDebug("Data to save: " . json_encode($data_to_save));
            $response = REDCap::saveData($this->project_id, 'array', $data_to_save, 'overwrite');
            $this->module->emDebug("Response from save data: " . json_encode($response));

        } else {
            $this->module->emDebug("Cannot call algorithm " . CONFIG_ALGORITHM_PATH . $scoring_algorithm);
        }

        return $status;
    }

    private function getDataDictionaryForms() {

        return array_keys($this->data_dictionary->forms);
    }

    private function getFieldsInForm() {

        return array_keys($this->data_dictionary->forms[$this->project_form]['fields']);
    }
}