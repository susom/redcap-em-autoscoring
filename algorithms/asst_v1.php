<?php
/**

	ASST
	
	A REDCap AutoScoring Algorithm File
	
	- There exists an array called $src that contains the data from the source project
	- There can exist an optional array called $manual_result_fields that can override the default_result_fields
	- The final results should be presented in an array called $algorithm_results

**/

# REQUIRED: Summarize this algorithm
$algorithm_summary = "ASST";

# REQUIRED: Define an array of default result field_names to record the summary data
$default_result_fields = array('asst_score_tbc','asst_score_alc','asst_score_cnbs','asst_score_ccn','asst_score_amph','asst_score_inh','asst_score_sed','asst_score_hal','asst_score_ops','asst_score_oth');

# REQUIRED: Define an array of fields that must be present for this algorithm to run
$required_fields = array(
	'asst_cncrn_alc','asst_cncrn_amph','asst_cncrn_ccn','asst_cncrn_cnbs','asst_cncrn_hal','asst_cncrn_inh','asst_cncrn_ops','asst_cncrn_oth','asst_cncrn_sed','asst_cncrn_tbc','asst_fail_alc','asst_fail_amph','asst_fail_ccn','asst_fail_cnbs','asst_fail_hal','asst_fail_inh','asst_fail_ops','asst_fail_oth','asst_fail_sed','asst_frq_alc','asst_frq_amph','asst_frq_ccn','asst_frq_cnbs','asst_frq_hal','asst_frq_inh','asst_frq_ops','asst_frq_oth','asst_frq_sed','asst_frq_tbc','asst_prb_alc','asst_prb_amph','asst_prb_ccn','asst_prb_cnbs','asst_prb_hal','asst_prb_inh','asst_prb_ops','asst_prb_oth','asst_prb_sed','asst_prb_tbc','asst_stp_alc','asst_stp_amph','asst_stp_ccn','asst_stp_cnbs','asst_stp_hal','asst_stp_inh','asst_stp_ops','asst_stp_oth','asst_stp_sed','asst_stp_tbc','asst_urg_alc','asst_urg_amph','asst_urg_ccn','asst_urg_cnbs','asst_urg_hal','asst_urg_inh','asst_urg_ops','asst_urg_oth','asst_urg_sed','asst_urg_tbc');

### VALIDATION ###

# If we are simply verifying the result and required fields, we can exit now.
if (isset($verify) && $verify === true) return true;

# Define Log Array - optionally used if specified by the log_field in the configuration project
$algorithm_log = array();
$algorithm_log[] = "Scored using " . basename(__FILE__, ".php") . " at " . date("Y-m-d H:i:s");


# Override default result array with manual field names specified by user (optional)
if (!empty($manual_result_fields)) {
	if (count($manual_result_fields) == count($default_result_fields)) {
		foreach($manual_result_fields as $k => $field) {
			if ($field) {	// Only replace non-empty field names (this allows someone to use ,,,newname,, in list)
				$default_result_fields[$k] = $field;
				$this->module->emDebug('changing $k to $field');
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

# Test for presense of all required fields and report missing fields
$source_fields = array_keys($src);
$missing_fields = array_diff($required_fields, $source_fields);
if ($missing_fields) {
	$msg = "Source project ($project_id) is trying to run the {$job['algorithm']} algorithm but is missing [" . implode(',',$missing_fields) . "]";
	$algorithm_log[] = $msg;
	$this->module->emError($msg);
	$this->module->emDebug("Missing Fields: " . $missing_fields);
	return false;	//Since this is being called via include, the main script will continue to process other algorithms
}

# Check that all required fields have a value
$null_fields = array();
foreach ($required_fields as $rf) {
	if (empty($src[$rf]) && !is_numeric($src[$rf])) $null_fields[] = $rf;
}
if (!empty($null_fields)) {
// Not forcing results to be populated for this routine
	$algorithm_log[] = "WARNING - Required fields are empty (".implode(',',$null_fields).")";
	return false;
}


### IMPLEMENT SCORING ###

$groups = array(
	'asst_score_tbc'	=> array('asst_cncrn_tbc','asst_frq_tbc','asst_prb_tbc','asst_stp_tbc','asst_urg_tbc'),
	'asst_score_alc'	=> array('asst_cncrn_alc','asst_fail_alc','asst_frq_alc','asst_prb_alc','asst_stp_alc','asst_urg_alc'),
	'asst_score_cnbs'	=> array('asst_cncrn_cnbs','asst_fail_cnbs','asst_frq_cnbs','asst_prb_cnbs','asst_stp_cnbs','asst_urg_cnbs'),
	'asst_score_ccn'	=> array('asst_cncrn_ccn','asst_fail_ccn','asst_frq_ccn','asst_prb_ccn','asst_stp_ccn','asst_urg_ccn'),
	'asst_score_amph'	=> array('asst_cncrn_amph','asst_fail_amph','asst_frq_amph','asst_prb_amph','asst_stp_amph','asst_urg_amph'),
	'asst_score_inh'	=> array('asst_cncrn_inh','asst_fail_inh','asst_frq_inh','asst_prb_inh','asst_stp_inh','asst_urg_inh'),
	'asst_score_sed'	=> array('asst_cncrn_sed','asst_fail_sed','asst_frq_sed','asst_prb_sed','asst_stp_sed','asst_urg_sed'),
	'asst_score_hal'	=> array('asst_cncrn_hal','asst_fail_hal','asst_frq_hal','asst_prb_hal','asst_stp_hal','asst_urg_hal'),
	'asst_score_ops'	=> array('asst_cncrn_ops','asst_fail_ops','asst_frq_ops','asst_prb_ops','asst_stp_ops','asst_urg_ops'),
	'asst_score_oth'	=> array('asst_cncrn_oth','asst_fail_oth','asst_frq_oth','asst_prb_oth','asst_stp_oth','asst_urg_oth')
);	


# Next, we go through each group and substitute in the actual source data for each question
# When this is done, we have an array where the key is each group and the elemnts are an array of
# question numbers and results:
// [rbs_sub3] => Array ([rbs_15] => 3,[rbs_16] => 3,[rbs_17] => 3, ...),
// [rbs_sub4] => Array (...)
$src_groups = array();
foreach($groups as $name => $questions) {
	# Add the algorithm prefix to our arrays of question numbers above
	$src_groups[$name] = array_intersect_key($src, array_flip($questions));
}

# Calculate our Totals
$result_values = array();
foreach ($src_groups as $name => $data) {
	# Calculate non-zero entries across group
//	$endorsed = 0;
//	foreach ($data as $fieldname => $val) if ($val != '0') $endorsed++;
//	$result_values[$name."_endorsed"] = $endorsed;
	
	# Calculate sum across group
	$score = array_sum($data);
	$result_values[$name] = $score;
}


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