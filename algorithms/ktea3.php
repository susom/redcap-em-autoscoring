<?php
/*
	KTEA-3 lookup of age based and grade based Scaled Score and Percentile Rank based off
	of an input raw score.

**/

use Stanford\Autoscore\ReadCSVFileClass;
require_once $this->module->getModulePath() . "classes/readCSVFileClass.php";

# REQUIRED: Summarize this algorithm
$algorithm_summary = "This scoring just takes the KTEA 3 Age based raw score and grade based raw score and look up the scaled score and percentile rank";

$categories = array('age', 'grade');

$required_fields = array();
$default_result_tscore = array();
$default_result_percent = array();

# REQUIRED: Result fields that must be present for this algorithm to run
foreach ($categories as $c) {
	array_push($required_fields, $c."_raw");				// raw values (input)
	array_push($default_result_tscore, $c."_tscore");		// normalized t-score (output)
	array_push($default_result_percent, $c."_perc_rank");		// percentile rank (output)
}

$default_result_fields = array_merge($default_result_tscore, $default_result_percent);

### VALIDATION ###
# If we are simply verifying the result and required fields, we can exit now.
if (isset($verify) && $verify === true) return true;


# Define Log Array - optionally used if specified by the log_field in the configuration project
$algorithm_log = array();
$algorithm_log[] = "Scored using " . basename(__FILE__, ".php") . " at " . date("Y-m-d H:i:s");

# Override required_fields array with manual field names specified by user (optional)
$orig_required_fields = $required_fields;
if (!empty($manual_source_fields)) {
	if (count($manual_source_fields) == count($required_fields)) {
		foreach($manual_source_fields as $k => $field) {
			if ($field) {	// Only replace non-empty field names (this allows someone to use ,,,newname,, in list)
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

//$this->module->emDebug("Required fields: " . json_encode($required_fields));

# Override default result array with manual field names specified by user (optional)
if (!empty($manual_result_fields)) {
	if (count($manual_result_fields) == count($default_result_fields)) {
		foreach($manual_result_fields as $k => $field) {
			if ($field) {	// Only replace non-empty field names (this allows someone to use ,,,newname,, in list)
				$default_result_fields[$k] = $field;
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


# Create a new array with names of the manual input fields and the values
# so we can use the required names to access the values
$input_values = array();
foreach ($required_fields as $rf) {
	$input_values[$rf] = $src[$rf];
}
//$this->module->emDebug("Input values: " . json_encode($input_values));

$norm_array = array_combine($orig_required_fields, $input_values);

$tscore_results = array();
$perc_rank = array();
$readFile = new ReadCSVFileClass();

foreach($categories as $c) {
	// Find the location of the file that we look up the tscores and percentile ranks
	$filepath = $data_path . 'KTEA-3/';
	$filename = "KTEA-3_Lookup_Tables_".$c.".csv";

	$lookup = $readFile->returnResults($filepath . $filename);

	$tscore_results[$c."_tscore"] = "";
	$perc_rank[$c."_perc_rank"] = "";
	if (!empty($lookup)) {
		for ($ncnt = 0; $ncnt < count($lookup["scaled_score"]); $ncnt++) {
			if (($lookup["raw_min"][$ncnt] <= $norm_array[$c . "_raw"]) and ($norm_array[$c . "_raw"] <= $lookup["raw_max"][$ncnt])) {
				$tscore_results[$c . "_tscore"] = $lookup["scaled_score"][$ncnt];
				$perc_rank[$c . "_perc_rank"] = $lookup["percentile_rank"][$ncnt];
				break;
			}
		}
	} else {
		$msg .= "No lookup values from file: " . $filepath . $filename . "\n";
		$this->module->emError($msg);
		$algorithm_log[] = $msg;
	}
}

$all_results = array_merge($tscore_results, $perc_rank);

### DEFINE RESULTS ###

# REQUIRED: The algorithm_results variable MUST BE USED as it is relied upon from the parent script.
$algorithm_results = array_combine($default_result_fields, $all_results);
//$this->module->emDebug("Combined array: " . json_encode($algorithm_results));

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
