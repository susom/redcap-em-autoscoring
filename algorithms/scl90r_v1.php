<?php
/**
 * SCL-90-R assessment having 90 questions with options ranging from 0-4 (Not at all to Extremely)
 *
 * The SCL-90-R assessment is designed to evaluate a broad range of psychological projects and symptoms of
 * psychopathology. It is also used to assess progress of treatments.
 *
 * There are 9 symptom dimensions calculated: Somatization, Obsessive-Compulsive, Interpersonal Sensitivity,
 * Depression, Anxiety, Hostility, Phobic Anxiety, Paranoid Ideation, and Psychoticism. In addition, 3 index scores
 * are calculated Global Severity Index, Positive Symptoms Distress Index and Positive Symptom Total Index.
 * Once these scores are calculated, the T scores are looked up based on gender.
 *
 **/

use Stanford\Autoscore\ReadCSVFileClass;
require_once $this->module->getModulePath() . "classes/readCSVFileClass.php";

# REQUIRED: Summarize this algorithm
$algorithm_summary = "Symptom Checklist-90-R (SCL-90-R) algorithm. This is a self-report psychometric instrument used to evaluate a
 broad range of psychological problems and symptoms of psychopathology. The primary symptom dimensions assessed are somatization,
 obsessive-compulsive, interpersonal sensitivity, depression, anxiety, hostility, phobic anxiety, paranoid ideation, and psychoticism.
 There are also 3 indexes that are calculated: Global Severity Index, Positive Symptom Distress Index, and Positive Symptom Total Index.";

# REQUIRED: Define an array of fields that must be present for this algorithm to run. There are 90 questions.
$prefix = 'scl90r_';
$required_fields = array();
foreach (range(1,90) as $i) {
	array_push($required_fields, $prefix.$i);
}
array_push($required_fields, "gender");

# REQUIRED: Define $default_result_fields array of default result field_names to record the summary data
$categories = array(
	'som',
	'oc',
	'is',
	'dep',
	'anx',
	'hos',
	'phob',
	'par',
	'psy',
    'gsi',
    'pst',
    'psdi'
);
$default_raw = array();
$default_tscores = array();
foreach ($categories as $c) {
	array_push($default_raw, $prefix.$c);
    array_push($default_tscores, $prefix.$c.'_tscores');
}
$default_result_fields = array_merge($default_raw, $default_tscores);
//$this->module->emDebug("Default Result Fields: " . $default_result_fields);

### VALIDATION ###
# If we are simply verifying the result and required fields, we can exit now.
if (isset($verify) && $verify === true) return true;


# Define Log Array - optionally used if specified by the log_field in the configuration project
$algorithm_log = array();
$algorithm_log[] = "Scored using " . basename(__FILE__, ".php") . " at " . date("Y-m-d H:i:s");

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
    $this->module->emDebug($msg, "Missing Fields for SCL-90-R");
}

### IMPLEMENT SCORING ###
// Create groups for scoring
$groups = array(
    $categories[0] 	=> array(1, 4, 12, 27, 40, 42, 48, 49, 52, 53, 56, 58),         // som
	$categories[1]	=> array(3, 9, 10, 28, 38, 45, 46, 51, 55, 65),                 // oc
    $categories[2] 	=> array(6, 21, 34, 36, 37, 41, 61, 69, 73),                    // is
    $categories[3] 	=> array(5, 14, 15, 20, 22, 26, 29, 30, 31, 32, 54, 71, 79),    // dep
    $categories[4]	=> array(2, 17, 23, 33, 39, 57, 72, 78, 80, 86),                // anx
    $categories[5] 	=> array(11, 24, 63, 67, 74, 81),                               // hos
    $categories[6]	=> array(13, 25, 47, 50, 70, 75, 82),                           // phob
    $categories[7] 	=> array(8, 18, 43, 68, 76, 83),                                // par
    $categories[8] 	=> array(7, 16, 35, 62, 77, 84, 85, 87, 88, 90)                 // psy
);
//$this->module->emDebug("GROUPS: " . $groups);

# Next, we go through each group and substitute in the actual source data for each question
# When this is done, we have an array where the key is each group and the elements are an array of
# question numbers and results:
$survey_data = array();
$gender = '';
foreach ($required_fields as $field_num => $field_name) {
    if ($field_num == 90) {
        $gender = $src[$field_name];
    } else {
        $survey_data[$field_num + 1] = $src[$field_name];
    }
}

// Since our required_fields array is indexed at 0 (so question 1 is at 0, I need to add a dummy value to do the alignment)
$category_fields = array();
$category_sums = array();
$category_blanks = array();
$raw_totals = array();
foreach($groups as $category => $question_numbers) {

	$category_fields[$category] = array_intersect_key($survey_data, array_flip($question_numbers));
	$category_sums[$category] = array_sum($category_fields[$category]);
	$options = array_count_values($category_fields[$category]);

	# Count up the number missing values for each category
	if (is_numeric($options[''])) {
        $category_blanks[$category] = $options[''];
    } else {
        $category_blanks[$category] = 0;
    }

	$answered_fields = count($category_fields[$category]) - $category_blanks[$category];
	if ($answered_fields > 0) {
        $raw_totals[$category] = round($category_sums[$category] / $answered_fields, 2);
    } else {
	    $raw_totals[$category] = 'N/A';
    }
}

# Now calculate the 3 global indices - sum all the fields and then find out how many non-blank fields there are.
$all_fields_sum = array_sum($survey_data);
$options = array_count_values($survey_data);
if (is_numeric($options[''])) {
    $answered_questions = count($survey_data) - $options[''];
} else {
    $answered_questions = count($survey_data);
}

# For the Global Severity Index (GSI), sum all the results and divide by the number of answered questions
if ($answered_questions > 0) {
    $raw_totals['gsi'] = round($all_fields_sum / $answered_questions, 2);
} else {
    $raw_totals['gsi'] = 'N/A';
}

# For Positive Symptom Total, add the number of non-zero items
$raw_totals['pst'] = 0;
foreach($options as $selection => $number_of_selections) {
    if (($selection != 0) && ($selection != '')) {
        $raw_totals['pst'] += $number_of_selections;
    }
}

# For the Positive Symptom Distress Index (PSDI) is dividing total sum by Positive Symptom Total
if (($raw_totals['pst'] != '') and ($raw_totals['pst'] > 0)) {
    $raw_totals['psdi'] = round($all_fields_sum / $raw_totals['pst'], 2);
} else {
    $raw_totals['psdi'] = 'N/A';
}
//$this->module->emDebug("Input values: " . json_encode($survey_data));
//$this->module->emDebug("Category Fields: " . json_encode($category_fields));
//$this->module->emDebug("Category Sums: " . json_encode($category_sums));
//$this->module->emDebug("Raw Totals:" . json_encode($raw_totals));

# Figure out which file to use based on gender - 1 = Male, 2 = Female
$tscore = array();
if ($gender == 1 or $gender == 2) {
    if ($gender == 1) {
        $tscore_file = $data_path . 'scl90r/scl90r_normb_nonpatient_males.csv';
        $tscore_pst_file = $data_path . 'scl90r/scl90r_normb_nonpatient_males_pst.csv';
    } else if ($gender == 2) {
        $tscore_file = $data_path . 'scl90r/scl90r_normb_nonpatient_females.csv';
        $tscore_pst_file = $data_path . 'scl90r/scl90r_normb_nonpatient_females_pst.csv';
    }

    try {
        $readFile = new ReadCSVFileClass();
        $lookup = $readFile->returnResults($tscore_file);
        $lookup_pst = $readFile->returnResults($tscore_pst_file);
    } catch (Exception $ex) {
        $msg = "Cannot create class to read CSV file for lookup tables - filename " . $tscore_file . " or " . $tscore_pst_file;
        $this->module->emError($msg);
        return false;
    }

    # Look up the standard score based on the raw score
    $INVALID_STATUS = 'Out of Range';
    foreach ($categories as $c) {

        if ($raw_totals[$c] === 'N/A') {
            # If a raw score could not be calculated, we won't have a T Score
            $tscore[$c . "_tscore"] = "N/A";

        } else if ($c == 'pst') {

            // The pst scale has all available possibilities so lookup the raw value and get the pst value
            $index = array_search($raw_totals[$c], $lookup_pst["raw"]);
            if (is_numeric($index)) {
                $tscore[$c . "_tscore"] = $lookup_pst[$c][$index];
            } else {
                $tscore[$c . "_tscore"] = $INVALID_STATUS;
            }
        } else {
            // The raw score may not be in the scale so we are looking for the interval where the raw score fits
            $size_of_array = count($lookup["raw"]) - 1;
            for ($ncnt = 0; $ncnt < $size_of_array; $ncnt++) {

                # Default the value as out of range but it will get overridden if we find a valid value
                $tscore[$c . "_tscore"] = $INVALID_STATUS;

                if ($raw_totals[$c] == $lookup["raw"][$ncnt]) {

                    # The raw score is in the lookup table
                    $tscore[$c . "_tscore"] = $lookup[$c][$ncnt];
                    break;

                } else if (($lookup["raw"][$ncnt] != '') && ($lookup["raw"][$ncnt+1] != '') &&
                            ($raw_totals[$c] > $lookup["raw"][$ncnt]) && ($raw_totals[$c] < $lookup["raw"][$ncnt+1])) {

                    # This raw score falls between 2 scores in the lookup table - either round up or down based on closest value
                    $percentage = ($raw_totals[$c] - $lookup["raw"][$ncnt]) / ($lookup["raw"][$ncnt + 1] - $lookup["raw"][$ncnt]);
                    if ($percentage >= .5) {
                        $tscore[$c . "_tscore"] = $lookup[$c][$ncnt + 1];
                    } else {
                        $tscore[$c . "_tscore"] = $lookup[$c][$ncnt];
                    }
                    break;
                }
            }
        }
    }
} else {
    $msg = "Gender is missing for algorithm SCL-90-R, project $project_id";
    $algorithm_log[] = $msg;
    foreach ($categories as $c) {
        $tscore[$c] = '';
    }
}
//$this->module->emDebug("Raw Scores: " . json_encode($raw_totals));
//$this->module->emDebug("T Scores: " . json_encode($tscore));

$result_values = array_merge($raw_totals, $tscore);

### DEFINE RESULTS ###

# REQUIRED: The algorithm_results variable MUST BE USED as it is relied upon from the parent script.
$algorithm_results = array_combine($default_result_fields, $result_values);
//$this->module->emDebug("AR: " . $algorithm_results);

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