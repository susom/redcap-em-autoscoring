<?php
/**

	Repetitive Behavior Scale - Revised (RBS-R)
	
	A REDCap AutoScoring Algorithm File
	
	- There exists an array called $src that contains the data from the source project
	- There can exist an optional array called $manual_result_fields that can override the default_result_fields
	- The final results should be presented in an array called $algorithm_results

**/

# REQUIRED: Summarize this algorithm
$algorithm_summary = "The Repetitive Behavior Scale - Revised (RBS-R)\nSee Bodfish, J.W., Symons, F.J., Parker, D.E., and Lewis, M.H. (2000). Varieties of repetitive behavior in autism.  Journal of Autism and Development Disabilities, 30. 237-243.";

# REQUIRED: Define an array of fields that must be present for this algorithm to run
$required_fields = array();
// This is just a short-hand way to create a list of 58 fields (abc_1, abc_2, ...).  Replace as necessary for your particular scoring algorithm
foreach (range(1,43) as $i) {
	array_push($required_fields, "rbsr_$i");
}

# REQUIRED: Define $default_result_fields array of default result field_names to record the summary data
$categories = array(
	'rbsr_stereo',	//stereotyped
	'rbsr_selfinjury', //self-injurious
	'rbsr_compulsive', //compulsive
	'rbsr_ritual', //ritualistic
	'rbsr_sameness', //sameness
	'rbsr_restrict', //restricted
	'rbsr_overall'
);
$default_result_fields = array();
foreach ($categories as $c) {
	array_push($default_result_fields, $c."_endorsed");
	array_push($default_result_fields, $c."_total");
}

//$this->module->emDebug("DRF: " . $default_result_fields);

### VALIDATION ###

# If we are simply verifying the result and required fields, we can exit now.
if (isset($verify) && $verify === true)  return true;

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
	$algorithm_log[] = "WARNING - Required fields are empty (".implode(',',$null_fields).")";
	return false;
}


### IMPLEMENT SCORING ###

# Since this is a subgroup scoring algoritm, I first divide the question numbers into the desired groups
$groups = array(
	'rbsr_stereo' => range(1,6),
	'rbsr_selfinjury' => range(7,14),
	'rbsr_compulsive' => range(15,22),
	'rbsr_ritual' => range(23,28),
	'rbsr_sameness' => range(29,39),
	'rbsr_restrict' => range(40,43),
	'rbsr_overall' => range(1,43)
);

# Next, we go through each group and substitute in the actual soruce data for each question
# When this is done, we have an array where the key is each group and the elemnts are an array of
# question numbers and results:
// [rbs_sub3] => Array ([rbs_15] => 3,[rbs_16] => 3,[rbs_17] => 3, ...),
// [rbs_sub4] => Array (...)
$src_groups = array();
foreach($groups as $name => $q_numbers) {
	# Add the algorithm prefix to our arrays of question numbers above
	array_walk($q_numbers, 'add_prefix', 'rbsr_');
	$src_groups[$name] = array_intersect_key($src, array_flip($q_numbers));
}
//$this->module->emDebug("SOURCE GROUPS: " . $src_groups);

# Calculate our Totals
# In this algorithm, the _endorsed number is the count of non-zero responses while the score is the sum of values.
$result_values = array();
foreach ($src_groups as $name => $data) {
	# Calculate non-zero entries across group
	$endorsed = 0;
	foreach ($data as $fieldname => $val) if ($val != '0') $endorsed++;
	$result_values[$name."_endorsed"] = $endorsed;
	
	# Calculate sum across group
	$score = array_sum($data);
	$result_values[$name."_total"] = $score;
}

//$this->module->emDebug("DRF: " . $default_result_fields);
//$this->module->emDebug("RV: " . $result_values);


### DEFINE RESULTS ###

# REQUIRED: The algorithm_results variable MUST BE USED as it is relied upon from the parent script.
$algorithm_results = array_combine($default_result_fields, $result_values);

$this->module->emDebug("AR: " . $algorithm_results);

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