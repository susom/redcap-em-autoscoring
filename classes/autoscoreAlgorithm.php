<?php
namespace Stanford\Autoscore;
/** @var \Stanford\Autoscore\Autoscore $module */

use \REDCap;
use \Exception;

class autoscoreAlgorithm
{
    private $project_id, $record, $instrument, $event_id, $repeat_instance, $setup_pid, $setup_id, $module;

    // these are from the Autoscore Setup project
    private $setup_stored_pid, $algorithm, $score_on_incomplete, $req_source_fields, $req_result_fields,
            $manual_source_fields, $manual_result_fields, $enable_find_and_replace,
            $find, $replace, $scoring_status_field, $append_data, $log_field, $active,
            $setup_event_id, $is_repeating_form, $is_repeating_event;

    public function __construct($project_id, $record, $instrument, $event_id, $repeat_instance, $setup_pid, $setup_id, $module)
    {
        global $Proj;

        // These are Autoscore project parameters
        $this->project_id                   = $project_id;
        $this->record                       = $record;
        $this->instrument                   = $instrument;
        $this->event_id                     = $event_id;
        $this->repeat_instance              = $repeat_instance;
        $this->setup_pid                    = $setup_pid;
        $this->setup_id                     = $setup_id;
        $this->module                       = $module;
        $this->is_repeating_form            = $Proj->isRepeatingForm($event_id, $instrument);
        $this->is_repeating_event           = $Proj->isRepeatingEvent($event_id);

        // Retrieve the setup parameters from the Autoscore Setup project
        $retrieved_parameters = REDCap::getData($this->setup_pid, 'array', $this->setup_id);
        $this->setup_event_id = array_keys($retrieved_parameters[$this->setup_id])[0];
        $setup_parameters = $retrieved_parameters[$this->setup_id][$this->setup_event_id];

        // Pull out the data from the setup project
        $this->setup_stored_pid = $setup_parameters['project_id'];
        $this->algorithm = $setup_parameters['algorithm'];
        $this->score_on_incomplete = $setup_parameters['score_on_incomplete']['1'];

        $no_spaces = str_replace(' ', '', $setup_parameters['required_source_fields']);
        $this->req_source_fields = explode(',', $no_spaces);
        $no_spaces = str_replace(' ', '', $setup_parameters['required_result_fields']);
        $this->req_result_fields = explode(',', $no_spaces);

        if (!empty($setup_parameters['manual_source_fields'])) {
            $this->manual_source_fields = $this->cleanInputString($setup_parameters['manual_source_fields']);
        }
        if (!empty($setup_parameters['manual_result_fields'])) {
            $this->manual_result_fields = $this->cleanInputString($setup_parameters['manual_result_fields']);
        }

        $this->enable_find_and_replace = $setup_parameters['enable_find_replace'];
        $this->find = $setup_parameters['find'];
        $this->replace = $setup_parameters['replace'];
        $this->scoring_status_field = $setup_parameters['scoring_status_field'];
        $this->append_data = $setup_parameters['append_data'];
        $this->log_field = $setup_parameters['log_field'];
        $this->active = $setup_parameters['active'];

        require_once "config.php";
    }

    private function cleanInputString($input_string) {
        $no_spaces = str_replace(' ', '', $input_string);
        $no_cntrl_r = str_replace("\r", '', $no_spaces);
        $no_cntrl_n = str_replace("\n", '', $no_cntrl_r);

        $input_array = explode(',', $no_cntrl_n);
        return $input_array;
    }

    public function runAlgorithm() {

        global $defined_algorithms;

        $status = true;

        // First check to see if this setup configuration matches our project
        if ($this->setup_stored_pid != $this->project_id) {
            $this->module->emError("The stored project id in the Setup project ($this->setup_stored_pid) does not match our project id ($this->project_id)");
            return false;
        }

        if ($this->active) {

            // check to see if we are only scoring if the instrument status is Complete
            // If score on incomplete, we don't care about the status so no need to check it
            $addl_fields = array();
            if (!$this->score_on_incomplete) {
                $instrument_complete = $this->instrument . '_complete';
                $addl_fields[] = $instrument_complete;
            }

            if (!empty($this->scoring_status_field)) {
                $addl_fields[] = $this->scoring_status_field;
            }

            // Retrieve the input fields and values
            $project_source = $this->findSourceFields($addl_fields);
            $this->updateResultFields();

            // If the form completion status needs to be complete (2) before running, check to make sure it is 2.
            if (!is_null($project_source[$instrument_complete]) && ($project_source[$instrument_complete] != 2)) {
                $this->module->emLog("Instrument $this->instrument for record $this->record is not scored because instrument status is not complete");
                return true;
            }

            // If the form should not be re-scored if the form status is complete, check to see if it was already scored
            if (!is_null($project_source[$this->scoring_status_field]) && ($project_source[$this->scoring_status_field] == 2)) {
                $this->module->emLog("Instrument $this->instrument for record $this->record is not scored because scoring status field ($this->scoring_status_field) is complete");
                return true;
            }

            // Track how long it takes to score the survey
            $msg = "Scoring with config_id " . $this->setup_id . " using the " . $defined_algorithms[$this->algorithm] . " algorithm";
            $job_log[] = $msg;
            $this->module->emDebug($msg);
            $time_start = microtime(true);
            $this->runJob($defined_algorithms[$this->algorithm], $project_source);
            $time_end = microtime(true);
            $msg = "Scoring complete at " . date("Y-m-d H:i:s") . " [". sprintf('%f', $time_end - $time_start) ." sec]";
            $job_log[] = $msg;
            $status = true;

        } else {

            // This setup is not active, just leave
            $this->module->emDebug("Config not active in Setup Project $this->setup_id");
            $status = true;
        }

        return $status;
    }

    private function updateResultFields() {

        $final_result = array();
        if (!empty($this->manual_source_fields)) {

            for($ncnt = 0; $ncnt < sizeof($this->req_result_fields); $ncnt++) {
                if (is_null($this->manual_result_fields[$ncnt]) || empty($this->manual_result_fields[$ncnt])) {
                    $field_name = $this->req_result_fields[$ncnt];
                } else {
                    $field_name = $this->manual_result_fields[$ncnt];
                }
                $final_result[] = $field_name;
            }

            $this->manual_result_fields = $final_result;

        } else if ($this->enable_find_and_replace) {

            for($ncnt = 0; $ncnt < sizeof($this->req_result_fields); $ncnt++) {
                $final_result[] = trim(str_replace($this->find, $this->replace, $this->req_result_fields[$ncnt]));
            }

            $this->manual_result_fields = $final_result;
        }
    }
    private function findSourceFields($addl_fields) {

        // First figure out the source fields
        if (!empty($this->manual_source_fields)) {

            $this->module->emDebug("Manual source: " . json_encode($this->manual_source_fields));

            // First check to see if there were manually entered fields
            $final_source = array();
            for($ncnt = 0; $ncnt < sizeof($this->req_source_fields); $ncnt++) {
                if (is_null($this->manual_source_fields[$ncnt]) || empty($this->manual_source_fields[$ncnt])) {
                    $field_name = $this->req_source_fields[$ncnt];
                } else {
                    $field_name = $this->manual_source_fields[$ncnt];
                }
                $final_source[] = $field_name;
            }

            $this->module->emDebug("After Manual source: " . json_encode($final_source));

        } else if ($this->enable_find_and_replace) {
            // Next check to see if there is a find/replace substitution
            $final_source = array();
            foreach ($this->req_source_fields as $key => $field) {
                $final_source[] = trim(str_replace($this->find, $this->replace, $field));
            }

            // Put these find/replace fields into the manual field array
            $this->manual_source_fields = $final_source;

        } else {
            // Just use the default values
            $final_source = $this->req_source_fields;
            $this->module->emDebug("Default source fields: " . json_encode($final_source));
        }

        // See if there are checkboxes so we can consolidate the field names
        $unique_field_names = array();
        foreach ($final_source as $field_name) {

            // See if there is an "___" in the name which means these are checkbox fields
            $position = strpos($field_name, "___");
            if ($position != false) {

                // Checkbox field
                $checkbox_name = substr($field_name, 0, $position);
                if (!in_array($checkbox_name, $unique_field_names)) {
                    $unique_field_names[] = $checkbox_name;
                }
            } else {
                $unique_field_names[] = $field_name;
            }
        }

        // Merge the scoring fields with any other fields we need for scoring
        $final_source_fields = array_merge($unique_field_names, $addl_fields);
        $this->module->emDebug("Final source fields: " . json_encode($final_source_fields));

        // Retrieve the project values for these fields
        if ($this->is_repeating_form || $this->is_repeating_event) {

            // Repeating forms and events don't work properly when supplying a event id so I'm leaving
            // it out so I can get all data for this form and look for the instance I want myself.
            $stored_values = REDCap::getData($this->project_id, 'array', $this->record, $final_source_fields);
            if ($this->is_repeating_event) {
                $this_instance = $stored_values[$this->record]["repeat_instances"][$this->event_id][""][$this->repeat_instance];
            } else {
                $this_instance = $stored_values[$this->record]["repeat_instances"][$this->event_id][$this->instrument][$this->repeat_instance];
            }

            // Check to see if there are any checkboxes in the list and if so, make each checkbox
            // an individual field (field_name: {"1": "0", "2": "1"})
            // ==> ("field_name___1" = "0", "field_name___2" = "1")
            foreach($this_instance as $field => $value) {
                if (is_array($value)) {
                    foreach($value as $checkbox => $checked) {
                        $source_values[$field . '___' . $checkbox] = $checked;
                    }
                } else {
                    $source_values[$field] = $value;
                }
            }

       } else {
            $stored_values = REDCap::getData($this->project_id, 'json', $this->record, $final_source_fields,
                                $this->event_id);
            $source_values = json_decode($stored_values, true)[0];
        }

        $this->module->emDebug("Source values: " . json_encode($source_values));
        return $source_values;
    }

    # The main function for executing a scoring routine
    function runJob($scoring_algorithm, $src)
    {

        $status = true;
        $algorithm_log = array();

        $job['algorithm'] = $this->algorithm;
        if (!is_null($this->log_field) && (!empty($this->log_field))) {
            $job['log_field'] = '';
        }
        $job['append_data'] = $this->append_data;
        $job['scoring_status_field'] = $this->scoring_status_field;

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

        // Is the calc status already set to complete - in which case we will skip re-scoring
        if (!empty($this->scoring_status_field)) {
            $scoring_status = isset($src[$this->scoring_status_field]) ? $src[$this->scoring_status_field] : '';
            if ($scoring_status == 2) {
                $job_log[] = "Not re-scoring because scoring status field ($this->scoring_status_field) = 2";

                // Update the record log_field if specified
                if ($job['log_field']) {
                    $data = array($job['log_field'] => implode("\n => ", $job_log));
                }
                $status = false;
            }
        }

        # Include the custom algorithm file (must follow the template)
        # The $src variable contains the source data
        # Results will be in algorithm_results variable when done
        if ($status) {
            $algorithm_results = array();
            $verify = false;
            $missing_fields = array();
            $manual_source_fields = $this->manual_source_fields;
            $manual_result_fields = $this->manual_result_fields;
            $data_path = $this->module->getModulePath() . "DataFiles/";
            $algorithm_complete = include(CONFIG_ALGORITHM_PATH . $scoring_algorithm);

            // First save the results of the scoring
            // If a status field is defined in the project, add the log to the data to save
            if (!empty($this->log_field)) {
                $job_log = " => " . implode("\n => ", $algorithm_log);
                $algorithm_results[$this->log_field] = $job_log;
            }

            // See if there is data to append to the results to save to the record being scored
            if ($algorithm_complete && !empty($this->append_data)) {
                $append = explode("=", $this->append_data);
                $algorithm_results[$append[0]] = $append[1];
            }

            // Add the record id field so we know where to save the data.
            $pk_field = REDCap::getRecordIdField();
            $algorithm_results = array_merge(array($pk_field => $this->record), $algorithm_results);

            // Save the data
            if ($this->is_repeating_event) {
                $data_to_save[$this->record]['repeat_instances'][$this->event_id][""][$this->repeat_instance] = $algorithm_results;
            } else if ($this->is_repeating_form) {
                $data_to_save[$this->record]['repeat_instances'][$this->event_id][$this->instrument][$this->repeat_instance] = $algorithm_results;
            } else {
                $data_to_save[$this->record][$this->event_id] = $algorithm_results;
            }

            $response = REDCap::saveData($this->project_id, 'array', $data_to_save, 'overwrite');
            if (empty($response['errors']) && ($response['item_count'] > 0)) {
                $saved_data = true;
                $this->module->emDebug("Response from Autoscore Project ($this->project_id), record $this->record: " . json_encode($response));
            } else {
                $saved_data = false;
                $this->module->emError("Data was NOT saved for Autoscore Project ($this->project_id), record $this->record: " . json_encode($response));
            }

            // Next, update the Setup Project with the status of this run
            if ($saved_data) {
                $algorithm_log[] = "RecordId $this->record in pid $this->project_id was scored in event $this->event_id";
            } else {
                $algorithm_log[] = "Data was not saved for recordId $this->record in pid $this->project_id and event $this->event_id";
            }

            $msg_log = " => " . implode("\n => ", $algorithm_log);
        } else {
            $msg_log = " => " . implode("\n => ", $job_log);
        }

        $setup_data_to_save[$this->setup_id][$this->setup_event_id]['required_source_fields_missing'] = $missing_fields;
        $setup_data_to_save[$this->setup_id][$this->setup_event_id]['last_results'] = $msg_log;
        $response = REDCap::saveData($this->setup_pid, 'array', $setup_data_to_save, 'overwrite');
        if (empty($response['errors']) && ($response['item_count'] > 0)) {
            $saved_data = true;
            $this->module->emDebug("Response from Setup Project ($this->setup_pid) saveData, record $this->setup_id: " . json_encode($response));
        } else {
            $saved_data = false;
            $this->module->emError("Data was NOT saved for Setup Project ($this->setup_pid), record $this->setup_id: " . json_encode($response));
        }

    }


}