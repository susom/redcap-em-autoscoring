<?php
/**

	Multi-Dimensional Anxiety Scale for Children, 2nd edition

	A REDCap AutoScoring Algorithm File

	- There exists an array called $src that contains the data from the source project
	- There can exist an optional array called $manual_result_fields that can override the default_result_fields
	- The final results should be presented in an array called $algorithm_results
        - The answers are categorized as:
              0 = Never
              1 = Rarely
              2 = Sometimes
              3 = Often
        - There are 50 questions that the participant fills out.

**/

use Stanford\Autoscore\ReadCSVFileClass;
require_once $this->module->getModulePath() . "classes/readCSVFileClass.php";

# REQUIRED: Summarize this algorithm
$algorithm_summary = "Multidimensional Anxiety Scale for Children, 2nd edition";


# REQUIRED: Define an array of default result field_names to record the summary data
# The MASC2 Validity Scoring Totals are:
#   MASC2 Total Scale and MASC2 Anxiety Probability Scale
# The clinical scales are:
#   Total Raw Score [TR]
#   Separation Anxiety/Phobias [SP]
#   GAD Index [GAD]
#   Humiliation/Rejection [HR]
#   Performance Fears [PF]
#   Obsessions & Compulsions [OC]
#   Panic [P]
#   Tense/Restless [TR]
#   Harm Avoidance [HA]
#   Social Anxiety [SA]
#   Physical Symptoms Total [PS]
#   -- 2 extra values
#   Inconsistency Index (response style) [INC_INDEX]
#   Anxiety Probability Score [ANX_PROB_SCORE]
$categories = array('TRAW',
                    'SP',
                    'GAD',
                    'HR',
                    'PF',
                    'OC',
                    'P',
                    'TR',
                    'HA',
                    'SA',
                    'PS');

# REQUIRED: Result fields that must be present for this algorithm to run
$default_result_raw = array();
$default_result_tscore = array();
$default_classifications = array();
foreach ($categories as $c) {
    array_push($default_result_raw, $c."_raw");			// raw values
    array_push($default_result_tscore, $c."_tscore");	// normalized t-score
    array_push( $default_classifications, $c."_class"); // classifications
}

// There are 2 additional fields
array_push($default_result_tscore, 'inc_index');		// inconsistency index
array_push($default_result_tscore, 'anx_prob_score');	// anxiety/probability score

$default_result_fields = array_merge($default_result_raw, $default_result_tscore, $default_classifications);

# REQUIRED: Define an array of fields that must be present for this algorithm to run
$required_fields = array();
foreach (range(1,50) as $i) {
	array_push($required_fields, $i);
}

// Add age and gender
array_push($required_fields, 'age');
array_push($required_fields, 'gender');

### VALIDATION ##
# If we are simply verifying the result and required fields, we can exit now.
if (isset($verify) && $verify === true) return true;

# Define Log Array - optionally used if specified by the log_field in the configuration project
$algorithm_log = array();
$algorithm_log[] = "Scored using " . basename(__FILE__, ".php") . " at " . date("Y-m-d H:i:s");

# Override default input array with manual field names specified by user (optional)
$orig_required_fields = $required_fields;
if (!empty($manual_source_fields)) {
    if (count($manual_source_fields) == count($required_fields)) {
        foreach($manual_source_fields as $k => $field) {
            if ($field) {   // Only replace non-empty field names (this allows someone to use ,,,newname,, in list)
                $required_fields[$k] = $field;
                //$this->module->emDebug("Changing input field " . $k ." to ". $field);
            }
        }
        $log[] = "Overriding default input field names with ". implode(',',$manual_source_fields);
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
				//$this->module->emDebug("Changing result field " . $k . " to ". $field);
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
    $this->module->emDebug($missing_fields, "Missing Fields");
	return false;	//Since this is being called via include, the main script will continue to process other algorithms
}

# Check that all required fields have a value
$null_fields = array();
$input_values = array();
foreach ($required_fields as $rf) {
    if (empty($src[$rf]) && !is_numeric($src[$rf])) $null_fields[] = $rf;
    $input_values[$rf] = $src[$rf];
}
if (!empty($null_fields)) {
    $algorithm_log[] = "WARNING - Required fields are empty (".implode(',',$null_fields).")";
}

# Create a new array with names of the manual input fields and the values
# so we can use the required names to access the values
$norm_array = array_combine($orig_required_fields, $input_values);

### IMPLEMENT RAW SCORING ###
// Find the questions used to score each category
// SA = HR(raw) + PF(raw)
// PS = P(raw) + TR(raw)
$survey_questions = array(
    'TRAW'    => array(),
    'SP'    => array(4, 7, 9, 17, 19, 23, 26, 30, 33),
    'GAD'   => array(1, 6, 13, 17, 22, 27, 29, 31, 39, 40),
    'HR'    => array(3, 10, 16, 22, 29),
    'PF'    => array(14, 32, 36, 38),
    'OC'    => array(41, 42, 43, 44, 45, 46, 47, 48, 49, 50),
    'P'     => array(6, 12, 18, 20, 24, 31, 37),
    'TR'    => array(1, 8, 15, 27, 34),
    'HA'    => array(2, 5, 11, 13, 21, 25, 28, 35),
    'SA'    => array(),
    'PS'    => array()
);


// Calculate the raw scores based on the arrays above
$intersection = array();
$raw_results = array();
foreach ($categories as $c) {
    if (!empty($c)) {
        $intersection[$c] = array_intersect_key($norm_array, array_flip($survey_questions[$c]));
        $raw_results[$c] = array_sum($intersection[$c]);
    } else {
        $raw_results[$c] = null;
    }
}

// Now calculate SA, PS, Inconsistancy Index Total
$raw_results['SA'] = $raw_results['HR'] + $raw_results['PF'];
$raw_results['PS'] = $raw_results['P'] + $raw_results['TR'];
$raw_results['TRAW'] = $norm_array[39] + $norm_array[40] + $raw_results['SP'] + $raw_results['SA']
                    + $raw_results['OC'] + $raw_results['PS'] + $raw_results['HA'];
//$this->module->emDebug("These are the raw scores: " . json_encode($raw_results));

### Now get the T values from the files. There are 3 different age groups and 2 genders.
// Put together the file names
$filepath = $this->module->getModulePath() . '/DataFiles/MASC-2/';
$readFile = new ReadCSVFileClass();

// Create file name based on age and gender
$age = intval(floor($norm_array['age']));
$gender = (($norm_array['gender'] == '2')  ? 'Female' : 'Male');   // gender is 2=female or 1=male
$filename = "";
if ($age >= 8 and $age <= 11) {
    $filename = "MASC-2_Lookup_" . $gender . "_8to11.csv";
} else if ($age >=12 and $age <= 15) {
    $filename = "MASC-2_Lookup_" . $gender . "_12to15.csv";
} else if ($age >=16 and $age <= 19) {
    $filename = "MASC-2_Lookup_" . $gender . "_16to19.csv";
} else {
    $algorithm_log[] = "Age: $age is outside the required span of 8 to 19";
}

$tscore_results = array();
if (!empty($filename)) {
    $lookup = $readFile->returnResults($filepath . $filename);

    foreach ($categories as $c) {

        $tscore_results[$c . "_tscore"] = '';

        // Find what the minimum value is so we can set it
        if (($c <> 'TRAW') and (!empty($lookup[$c]))) {
            $min_lookup_value = $lookup[$c][count($lookup[$c]) - 1];
            $min_tscore_value = $lookup["tscore"][count($lookup[$c]) - 1];
        } else {
            $min_lookup_value = 0;
            $min_tscore_value = 0;
        }

        if ($c == 'TRAW') {
            // TRAW is special because it has min/max values that it has to fall between
            if (!empty($lookup["TRAW_MIN"][0]) and ($raw_results[$c] >= $lookup["TRAW_MIN"][0])) {
                $tscore_results[$c . "_tscore"] = $lookup["tscore"][0];
            } else {
                for ($ncnt = 0; $ncnt < count($lookup["TRAW_MIN"]); $ncnt++) {
                    if (($lookup["TRAW_MIN"][$ncnt] <= $raw_results[$c]) and
                        ($raw_results[$c] <= $lookup["TRAW_MAX"][$ncnt])) {

                        $tscore_results[$c . "_tscore"] = $lookup["tscore"][$ncnt];
                        break;
                    }
                }
            }
        } else if (($lookup[$c][0] != '') and ($raw_results[$c] >= $lookup[$c][0])) {
            $tscore_results[$c . "_tscore"] = $lookup["tscore"][0];
        } else if (($lookup[$c][0] != '') and ($raw_results[$c] > max($lookup[$c]))) {
            $tscore_results[$c . "_tscore"] = "";
        } else if (($min_lookup_value != '') and ($raw_results[$c] <= $min_lookup_value)) {
            $tscore_results[$c . "_tscore"] = $min_tscore_value;
        } else {
            for ($ncnt = 0; $ncnt < count($lookup[$c]); $ncnt++) {
                if (($lookup[$c][$ncnt] != '') and ($raw_results[$c] == $lookup[$c][$ncnt])) {
                    $tscore_results[$c . "_tscore"] = $lookup["tscore"][$ncnt];
                    break;
                }
            }
        }
    }

} else {
    foreach ($categories as $c) {
        $tscore_results[$c . "_tscore"] = '';
    }
}

# Calculate the Inconsistancy Index Total and Anxiety Probability Score.
$tscore_results["inc_index"] = abs($norm_array[3] - $norm_array[10]) +
                            abs($norm_array[4] - $norm_array[9]) +
                            abs($norm_array[8] - $norm_array[15]) +
                            abs($norm_array[13] - $norm_array[35]) +
                            abs($norm_array[20] - $norm_array[27]) +
                            abs($norm_array[22] - $norm_array[29]) +
                            abs($norm_array[43] - $norm_array[44]) +
                            abs($norm_array[47] - $norm_array[50]);
if (($tscore_results["SP_tscore"] != '') && ($tscore_results["GAD_tscore"] != '')
                        && ($tscore_results["SA_tscore"] != '')) {
    $tscore_results["anx_prob_score"] = ($tscore_results["SP_tscore"] >= 60 ? 1 : 0) +
        ($tscore_results["GAD_tscore"] >= 60 ? 1 : 0) +
        ($tscore_results["SA_tscore"] >= 60 ? 1 : 0);
} else {
    $tscore_results["anx_prob_score"] = '';
}

//Determine classifications
$classifications = array();
foreach ($categories as $c) {
    if ($tscore_results[$c."_tscore"] == '') {
        $classifications[$c."_class"] = '';
    } else if ($tscore_results[$c."_tscore"] >= 70) {
        $classifications[$c."_class"] = 5;
    } else if ($tscore_results[$c."_tscore"] >= 65) {
        $classifications[$c."_class"] = 4;
    } else if ($tscore_results[$c."_tscore"] >= 60) {
        $classifications[$c."_class"] = 3;
    } else if ($tscore_results[$c."_tscore"] >= 55) {
        $classifications[$c."_class"] = 2;
    } else {
        $classifications[$c."_class"] = 1;
    }
}

### DEFINE RESULTS ###
# REQUIRED: The algorithm_results variable MUST BE USED as it is relied upon from the parent script.
$all_results = array_merge($raw_results, $tscore_results, $classifications);
$algorithm_results = array_combine($default_result_fields, $all_results);
//$this->module->emDebug("Combined array: " . json_encode($algorithm_results));

# Append result field for algorithm log if specified via the log_field variable in the config project
# Because we aren't pulling the entire data dictionary, we can't confirm whether or not the field actually exists
if ($job['log_field']) {
	$algorithm_results[$job['log_field']] = implode("\n",$algorithm_log);
	$msg = "Custom log_field {$job['log_field']}";
	$algorithm_log[] = $msg;
	$algorithm_results = array_merge($algorithm_results, array($job['log_field'] => $algorithm_log));
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

