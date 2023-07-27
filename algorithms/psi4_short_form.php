<?php
/**

	PSI4

	A REDCap AutoScoring Algorithm File

	- There exists an array called $src that contains the data from the source project
	- There can exist an optional array called $manual_result_fields that can override the default_result_fields
	- The final results should be presented in an array called $algorithm_results

**/

use Stanford\Autoscore\ReadCSVFileClass;
require_once $this->module->getModulePath() . "classes/readCSVFileClass.php";

# REQUIRED: Summarize this algorithm - There are lookup tables based on gender.
$algorithm_summary = "PSI-4 Short Form.  It assumes the questions are coded as 1 (generally Strongly agree)  to 5 (generally Strongly disagree) for the 36 questions. Once the raw scores are calculated, the percentiles and tvals are looked up based on gender.";

# REQUIRED: Define an array of fields that must be present for this algorithm to run
$prefix = 'psi_sf_';
// Replace as necessary for your particular scoring algorithm
$required_fields = array();
foreach (range(1,36) as $i) {
	array_push($required_fields, $prefix.$i);
}

# REQUIRED: Define $default_result_fields array of default result field_names to record the summary data
$categories = array(
	'pd',	            // Parent Distress
	'pcdi',		        // Parent-Children Dysfunctional Interaction
	'dc',	            // Difficult Child
	'ts',	            // Total Stress
	'dr' 			    // Defensive Responding
);
$default_result_fields = array();
$raw_fields = array();
$perc_fields = array();
$tval_fields = array();
foreach ($categories as $c) {
	array_push($raw_fields, $prefix.$c."_raw");			// raw score
	if ($c != 'dr') {
		array_push($perc_fields, $prefix.$c."_perc");		// raw score => lookup percentile
        array_push($tval_fields, $prefix.$c."_tval");		// raw score => lookup tvalue score
	}
}
array_push($tval_fields, $prefix."dr");
$default_result_fields = array_merge($raw_fields, $perc_fields, $tval_fields);
//$this->module->emDebug("DRF: " . json_encode($default_result_fields));

### VALIDATION ###
# If we are simply verifying the result and required fields, we can exit now.
if (isset($verify) && $verify === true) return true;


# Define Log Array - optionally used if specified by the log_field in the configuration project
$algorithm_log = array();
$algorithm_log[] = "Scored using " . basename(__FILE__, ".php") . " at " . date("Y-m-d H:i:s");

$default_required_fields = $required_fields;
# Override required_fields array with manual field names specified by user (optional)
if (!empty($manual_source_fields)) {
	//$this->module->emDebug("Manual Source Fields: " . $manual_source_fields);
	//$this->module->emDebug("Required Fields: " . $required_fields);
	if (count($manual_source_fields) == count($required_fields)) {
		foreach($manual_source_fields as $k => $field) {
			if ($field) {	// Only replace non-empty field names (this allows someone to use ,,,newname,, in list)
				$required_fields[$k] = $field;
				//$this->module->emDebug("changing $k to $field");
			}
		}
		$log[] = "Overriding required fields with ". implode(',',$manual_source_fields);
		//$this->module->emDebug("Required Fields After: " . $required_fields);
	} else {
		$msg = count($manual_source_fields) . " manual source fields specified, but the algorithm needs " . count($required_fields) . " fields.";
		$this->module->emError($msg);
		$algorithm_log[] = $msg;
		return false;
	}
}

# Override default result array with manual field names specified by user (optional)
$result_fields = $default_result_fields;
if (!empty($manual_result_fields)) {
	if (count($manual_result_fields) == count($default_result_fields)) {
		foreach($manual_result_fields as $k => $field) {
			if ($field) {	// Only replace non-empty field names (this allows someone to use ,,,newname,, in list)
				$default_result_fields[$k] = $field;
				//$this->module->emDebug('changing $k to $field');
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
	return false;  // prevent scoring during partial submissions (section breaks)
}


### IMPLEMENT SCORING ###

# Since this is a subgroup scoring algorithm, divide the fields into the desired groups
// To get raw values, we need to sum up scores for each category based on the following.
// PD raw score: sum items 1-12
// P-CDI raw score: sum items 13-24
// DC raw score: sum items 25-36
// Total Stress raw: sum items 1-36
// DR raw score: sum items 1,2,3,7,8,9,11

// Put the raw results in the correct order
$result_values = [];
foreach($result_fields as $index => $field) {
    $result_values[$field] = 0;
}

// Sum up all the raw values
for($i = 1; $i <= 36; $i++) {

    if ($i >= 1 and $i <= 12) {
        // These are PD Raw values
        $result_values['psi_sf_pd_raw'] += $src[$manual_source_fields[$i-1]];
    } else if ($i >= 13 and $i <= 24) {
        // These are P-CDI Raw values
        $result_values['psi_sf_pcdi_raw'] += $src[$manual_source_fields[$i-1]];
    } else if ($i >= 25 and $i <= 36) {
        // These are DC Raw Values
        $result_values['psi_sf_dc_raw'] += $src[$manual_source_fields[$i-1]];
    }

    // This is Total Stress Raw Value
    $result_values['psi_sf_ts_raw'] += $src[$manual_source_fields[$i-1]];

    if ($i <= 3 or ($i >= 7 and $i <= 9) or $i == 11) {
        // This is the Defensive Responding Raw Score
        $result_values['psi_sf_dr_raw'] += $src[$manual_source_fields[$i-1]];
    }
}

$this->module->emDebug("Results: ", $result_values);

# Now that we have the raw scores, look up the percentiles and tvals from the raw scores.
$filepath_tval = $this->module->getModulePath() . '/DataFiles/psi4_short_form/psi4_sf_tval.csv';
//$filepath_perc = $this->module->getModulePath() . '/DataFiles/psi4_short_form/psi4_sf_perc.csv';
$readFile = new ReadCSVFileClass();

# Retrieve the tvalue lookup tables

$tval_table = $readFile->returnResults($filepath_tval);
//$perc_table = $readFile->returnResults($filepath_perc);
$table_length = count($tval_table['tval']);

for($index=0; $index < $table_length; $index++) {
    if ($result_values['psi_sf_pd_raw'] == $tval_table['pd'][$index]) {
        $result_values['psi_sf_pd_tval'] = $tval_table['tval'][$index];
    }
    if ($result_values['psi_sf_pcdi_raw'] == $tval_table['pcdi'][$index]) {
        $result_values['psi_sf_pcdi_tval'] = $tval_table['tval'][$index];
    }
    if ($result_values['psi_sf_dc_raw'] == $tval_table['dc'][$index]) {
        $result_values['psi_sf_dc_tval'] = $tval_table['tval'][$index];
    }
    if ($result_values['psi_sf_ts_raw'] >= $tval_table['ts_min'][$index] and $result_values['psi_sf_ts_raw'] <= $tval_table['ts_max'][$index]) {
        $result_values['psi_sf_ts_tval'] = $tval_table['tval'][$index];
    }
}

# if the defensive response value is less than or equal to 24, the defensive significance is true.  Otherwise it is false.
$result_values['psi_sf_dr'] = ($result_values['psi_sf_dr_raw'] <= 10 ? 1 : 0);

// Retrieve the percentile tables and lookup the raw scores
$filepath_perc = $this->module->getModulePath() . '/DataFiles/psi4_short_form/psi4_sf_perc.csv';
$perc_table = $readFile->returnResults($filepath_perc);
$table_length = count($perc_table['perc']);

for($index=0; $index < $table_length; $index++) {
    if (($result_values['psi_sf_pd_raw'] >= $perc_table['pd_min'][$index])
                                and
       ($result_values['psi_sf_pd_raw'] <= $perc_table['pd_max'][$index])) {
        $result_values['psi_sf_pd_perc'] = $perc_table['perc'][$index];
    }

    if (($result_values['psi_sf_pcdi_raw'] >= $perc_table['pcdi_min'][$index])
                                and
       ($result_values['psi_sf_pcdi_raw'] <= $perc_table['pcdi_max'][$index])) {
        $result_values['psi_sf_pcdi_perc'] = $perc_table['perc'][$index];
    }

    if (($result_values['psi_sf_dc_raw'] >= $perc_table['dc_min'][$index])
                                and
       ($result_values['psi_sf_dc_raw'] <= $perc_table['dc_max'][$index])) {
        $result_values['psi_sf_dc_perc'] = $perc_table['perc'][$index];
    }

    if (($result_values['psi_sf_ts_raw'] >= $perc_table['ts_min'][$index])
                                and
       ($result_values['psi_sf_ts_raw'] <= $perc_table['ts_max'][$index])) {
        $result_values['psi_sf_ts_perc'] = $perc_table['perc'][$index];
    }
}

$this->module->emDebug("Results: ", $result_values);


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
