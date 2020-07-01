<?php

/**

	Edinburgh Handedness Inventory for the Turner Project
	
	A REDCap AutoScoring Algorithm File
	
	- There exists an array called $src that contains the data from the source project
	- There can exist an optional array called $manual_result_fields that can override the default_result_fields
	- The final results should be presented in an array called $algorithm_results
	- Unfortunately the Turner project EHI is different that the Fragile X EHI so they cannot
          be combined.
        - There are 4 inputs for handedness: Writing, Throwing, Toothbrush and Spoon
        - All 4 fields have values of: 100 - Always Right, 50 - Usually Right, 0 - Both Equally, -50 - Usually Left and -100 Always Left.
        - The summary score adds up all the values and then categorizes them into: 1 - Right Handed (61 to 100), 2 - Mixed Hand (-60 to 60) and 3 - Left Handed (-100 to -60). 

**/

# REQUIRED: Summarize this algorithm
$algorithm_summary = "EHI for Turner Project - The average of the 4 handed inputs (Writing, Throwing, Toothbrush, Spoon) is calculated and the handedness is determined by the score.";
$this->module->emDebug("Algorithm Summary: " . $algorithm_summary);

# REQUIRED: Define an array of default result field_names to record the summary data
$default_result_fields = array('ehi_lq', 'ehi_handedness');

# REQUIRED: Define an array of fields that must be present for this algorithm to run
$required_fields = array('ehand_write','ehand_throw','ehand_toothbrush','ehand_spoon');

### VALIDATION ###

# If we are simply verifying the result and required fields, we can exit now.
if (isset($verify) && $verify === true) return true;

# Define Log Array - optionally used if specified by the log_field in the configuration project
$algorithm_log = array();
$algorithm_log[] = "Scored using " . basename(__FILE__, ".php") . " at " . date("Y-m-d H:i:s");
$this->module->emDebug("Algorithm Log 1: " . json_encode($algorithm_log));

# Override required_fields array with manual field names specified by user (optional)
if (!empty($manual_source_fields)) {
        if (count($manual_source_fields) == count($required_fields)) {
                foreach($manual_source_fields as $k => $field) {
                        if ($field) {   // Only replace non-empty field names (this allows someone to use ,,,newname,, in list)
                                $required_fields[$k] = $field;
                        }
                }
                $log[] = "Overriding required fields with ". implode(',',$manual_source_fields);
        } else {
                $msg = count($manual_source_fields) . " manual source fields specified, but the algorithm needs " . count($required_fields) . " fields.";
                $this->module->emError($msg);
                $algorithm_log[] = $msg;
                return false;
        }
}

# Override default result array with manual field names specified by user (optional)
if (!empty($manual_result_fields)) {
	if (count($manual_result_fields) == count($default_result_fields)) {
		foreach($manual_result_fields as $k => $field) {
			if ($field) {	// Only replace non-empty field names (this allows someone to use ,,,newname,, in list)
				$default_result_fields[$k] = $field;
				//debugit('changing $k to $field');
			}
		}
		$log[] = "Overriding default result field names with ". implode(',',$manual_result_fields);
	} else {
		$msg = count($manual_result_fields) . " manual result fields specified, but the algorithm needs " . count($default_result_fields) . " fields.";
		$this->module->emError($msg);
		$algorithm_log[] = $msg;
		return false;
	}
}

$this->module->emDebug("Src: " . json_encode($src));
# Test for presense of all required fields and report missing fields
$source_fields = array_keys($src);
$this->module->emDebug("Source keys: " . json_encode($source_fields));
$missing_fields = array_diff($required_fields, $source_fields);
$this->module->emDebug("Req fields: " . json_encode($required_fields) . ", and source: " . json_encode($source_fields));
if ($missing_fields) {
	$msg = "Source project ($project_id) is trying to run the {$job['algorithm']} algorithm but is missing [" . implode(',',$missing_fields) . "]";
	$algorithm_log[] = $msg;
	$this->module->emError("Missing Fields: " . json_encode($missing_fields));
	return false;	//Since this is being called via include, the main script will continue to process other algorithms
}

# Check that all required fields have a value
$null_fields = array();
$source_values = array();
foreach ($required_fields as $rf) {
	if (empty($src[$rf]) && !is_numeric($src[$rf])) {
           $null_fields[] = $rf;
	} else {
	   $source_values[$rf] = $src[$rf];
	}
}
if (!empty($null_fields)) {
// Not forcing results to be populated for this routine (actually by request we now are!)
	$algorithm_log[] = "WARNING - Required fields are empty (".implode(',',$null_fields).")";
	return false;  // prevent scoring during partial submissions (section breaks)
}


### IMPLEMENT SCORING ###
# Calculate the Total for Laterality Quotient
# Send back 1 = Right handedness for lq values of 61 to 100, 2 = Mixed handedness for lq values of -60 to 60 or 3 = Left handedness for lq values of -61 to -100 
$lq = array_sum($source_values)/count($source_values);
$result_values['total'] = $lq;
$result_values['handedness'] = ($lq > 60 ? 1 : ($lq  < -60 ? 3 : 2));

### DEFINE RESULTS ###

# REQUIRED: The algorithm_results variable MUST BE USED as it is relied upon from the parent script.
$algorithm_results = array_combine($default_result_fields, $result_values);

# Append result field for algorithm log if specified via the log_field variable in the config project
# Because we aren't pulling the entire data dictionary, we can't confirm whether or not the field actually exists
if ($job['log_field']) {
	$algorithm_results[$job['log_field']] = implode("\n",$algorithm_log);
	$msg = "Custom log_field {$job['log_field']}";
	$algorithm_log[] = $msg;
	//$algorithm_results = array_merge($algorithm_results, array($job['log_field'] => $algorithm_log));
}

# Append additional data to the results if specified via the 'append_data' field in the config project.
# This data is in the format of fieldname=value, fieldname2=value2, etc... and is useful for setting the result
# form status to complete
if ($job['append_data']) {
	$data_pairs = array_map('trim',explode(',', $job['append_data']));
	foreach ($data_pairs as $pair) {
		list($fname, $fvalue) = array_map('trim',explode('=', $pair));
		$algorithm_results[$fname] = $fvalue;
		$algorithm_log[] = "Appended [$fname] = $fvalue";
	}
}

return true;

?>
