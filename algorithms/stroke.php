<?php
/**

   Stroke Scoring
	
	A REDCap AutoScoring Algorithm File
	
	- There exists an array called $src that contains the data from the source project
	- There can exist an optional array called $manual_result_fields that can override the default_result_fields
	- The final results should be presented in an array called $algorithm_results

        - The raw scores for the following are entered and the normalized T-scores are returned: Trails A, Trails B,
                Boston Naming, Animal Naming, Digit Span, Symbol Search xx, Symbol Digit, COWAT, Word Reading,
                Color Naming, Color-Word, Interference, HVLT Imm, HVLT Del, HVLT Ret.  Also, lookup scores are
                found for COWAT through the CFL MOANS Norms for Persons Aged 56-97 table.
        - The algorithm for this scoring is from a Excel spreadsheet called 'Stroke Scoring 11.31.xlsm'

**/

use Stanford\Autoscore\ReadCSVFileClass;
require_once $this->module->getModulePath() . "classes/readCSVFileClass.php";

# REQUIRED: Summarize this algorithm
$algorithm_summary = "Stroke Scoring. This algorithm calculates the SS normalized scores when raw scores are entered in the REDCap project.";

# REQUIRED: Define $default_result_fields array of default input and result field_names to record the summary data
$categories = array(
	'trails_a',
	'trails_b',
	'boston_naming',
	'animal_naming',
	'digit_span',
	'symbol_search',
	'symbol_digit',
	'cowat',
	'word_reading',
	'color_naming',
	'color_word',
	'interference',
	'hvlt_imm',
	'hvlt_del',
	'hvlt_ret'
);
$required_fields = array();
$default_result_fields = array();

# REQUIRED: Define an array of fields that must be present for this algorithm to run
foreach ($categories as $c) {
	if ($c != 'interference') {
		array_push($required_fields, $c."_raw");		// raw score
	}
	array_push($default_result_fields, $c."_tscore_norm");		// normalized t-score
}

# We also need age and education as inputes
array_push($required_fields, "age");
array_push($required_fields, "education");
array_push($default_result_fields, "cowat_standardized_score");

### VALIDATION ###
# If we are simply verifying the result and required fields, we can exit now.
if (isset($verify) && $verify === true) return true;


# Define Log Array - optionally used if specified by the log_field in the configuration project
$algorithm_log = array();
$algorithm_log[] = "Scored using " . basename(__FILE__, ".php") . " at " . date("Y-m-d H:i:s");

# Override required_fields array with manual field names specified by user (optional)
$orig_required_fields = $required_fields;
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
	$this->module->emError($msg);
	$this->module->emDebug("Missing Fields: " . $missing_fields);
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
// continue processing even if some values are missing
//	return false;  // prevent scoring during partial submissions (section breaks)
}

# Create a new array with names of the manual input fields and the values
# so we can use the required names to access the values
$norm_array = array_combine($orig_required_fields, $input_values);

### IMPLEMENT SCORING ###

# Each category has their own calculation so calculate each separately
$age = $norm_array['age'];
$education = $norm_array['education'];
# $this->module->emDebug("age: ". $age . "; and education " . $education);
$result_values = array();

# This is for Trails A
$raw = $norm_array['trails_a_raw'];
$m = 26.50094 - 0.2665049*$age + 0.0069935*$age*$age;
$sd = 8.760348 - 0.1138093*$age + 0.0028324*$age*$age;
if ($sd <> 0) {
	$norm_z = ($m - $raw)/$sd;
        $result  = round(100 + ($norm_z*15), 2);
} else {
	$result = "N/A";
}
$result_values['trails_a_tscore_norm'] = $result;
# $this->module->emDebug("Result for trails A is " . $result);
	

# This is for Trails B
$raw = $norm_array['trails_b_raw'];
$m = 64.07469 - 0.9881013*$age + 0.0235581*$age*$age;
$sd = 29.8444 - 0.8080508*$age + 0.0148732*$age*$age;
if (isset($sd) and $sd <> 0) {
	$norm_z = ($m - $raw)/$sd;
	$result = round(100 + ($norm_z*15), 2);
} else {
	$result = "N/A";
}
$result_values['trails_b_tscore_norm'] = $result;
# $this->module->emDebug("Result for trails B " . $result);
	

# This is for Boston Naming
$raw = $norm_array['boston_naming_raw'];
$adj = -8.124 + ($raw*2.345);
$m = 47.36842 + 0.4489501*$age - 0.0052924*$age*$age;
$sd = 4.542304 - 0.0992503*$age + 0.0016771*$age*$age;
if (isset($sd) and $sd <> 0) {	
	$norm_z = ($adj - $m)/$sd;
	$result = round(100 + ($norm_z*15), 2);
} else {
	$result = "N/A";
}
$result_values['boston_naming_tscore_norm'] = $result;
# $this->module->emDebug("Result for boston naming: " . $result);


# This is for Animal Naming
$raw = $norm_array['animal_naming_raw']; 
$m = 28.45972 - 0.1521419*$age;
$sd = 4.65;
$norm_z = ($raw - $m)/$sd;
$result = round(100 + ($norm_z*15), 2);

$result_values['animal_naming_tscore_norm'] = $result;
# $this->module->emDebug("Result for animal naming: " . $result);
	

# These are the lookup tables based on age for Digit Span, Symbol Search, 
if ($age >= 18 && $age <=19) {
	$digit_span_file = 'Digit_span_18_19.csv';
        $symbol_search_file = 'Symbol_search_18_19.csv';
} elseif ($age >= 20 && $age <= 24) {
	$digit_span_file = 'Digit_span_20_24.csv';
        $symbol_search_file = 'Symbol_search_20_24.csv';
} elseif ($age >= 25 && $age <= 29) {
	$digit_span_file = 'Digit_span_25_29.csv';
        $symbol_search_file = 'Symbol_search_25_29.csv';
} elseif ($age >= 30 && $age <= 34) {
	$digit_span_file = 'Digit_span_30_34.csv';
        $symbol_search_file = 'Symbol_search_30_34.csv';
} elseif ($age >= 35 && $age <= 44) {
	$digit_span_file = 'Digit_span_35_44.csv';
        $symbol_search_file = 'Symbol_search_35_44.csv';
} elseif ($age >= 45 && $age <= 54) {
	$digit_span_file = 'Digit_span_45_54.csv';
        $symbol_search_file = 'Symbol_search_45_54.csv';
} elseif ($age >= 55 && $age <= 64) {
	$digit_span_file = 'Digit_span_55_64.csv';
        $symbol_search_file = 'Symbol_search_55_64.csv';
} elseif ($age >= 65 && $age <= 69) {
	$digit_span_file = 'Digit_span_65_69.csv';
        $symbol_search_file = 'Symbol_search_65_69.csv';
} elseif ($age >= 70 && $age <= 74) {
	$digit_span_file = 'Digit_span_70_74.csv';
        $symbol_search_file = 'Symbol_search_70_74.csv';
} elseif ($age >= 75 && $age <= 79) {
	$digit_span_file = 'Digit_span_75_79.csv';
        $symbol_search_file = 'Symbol_search_75_79.csv';
} elseif ($age >= 80 && $age <= 84) {
	$digit_span_file = 'Digit_span_80_84.csv';
        $symbol_search_file = 'Symbol_search_80_84.csv';
} elseif ($age >= 85 && $age <= 90) {
	$digit_span_file = 'Digit_span_85_90.csv';
        $symbol_search_file = 'Symbol_search_85_90.csv';
} else {
	$digit_span_file = null;
        $symbol_search_file = null;
}

$filePath = $data_path . "stroke/";
$readFile = new ReadCSVFileClass();

# This is for Digit Span
$digit_raw = $norm_array['digit_span_raw'];
if (isset($digit_span_file) && ($digit_raw >= 0 && $digit_raw <= 30)) {
        $digit_span_lookup = $readFile->returnResults($filePath . $digit_span_file);
	$norm_z = $digit_span_lookup['Scaled Score'][$digit_raw];
	$result = round(($norm_z - 10)/3*15 + 100, 2);
} else {
	$result = "N/A";
}
$result_values['digit_span_tscore_norm'] = $result;
# $this->module->emDebug("Result for digit span: " . $result);


# This is for Symbol Search
$symbol_raw = $norm_array['symbol_search_raw'];
if (isset($symbol_search_file) && ($symbol_raw >= 0 && $symbol_raw <= 60)) {
	$symbol_search_lookup = $readFile->returnResults($filePath . $symbol_search_file);
	$norm_z = $symbol_search_lookup['Scaled Score'][$symbol_raw];
	$result = ($norm_z - 10)/3*15 + 100;
} else {
	$result = null;
}
$result_values['symbol_search_tscore_norm'] = $result;
# $this->module->emDebug("Result for symbol search: " . $result);


# This is for Symbol Digit
if ($age < 25 && $education < 13) {
	$m = 61.31;
	$sd = 11.39;
} elseif ($age < 25 && $education > 12) {
	$m = 69.91;
	$sd = 12.64;
} elseif ($age < 35 && $age > 24 && $education < 13) {
	$m = 60.57;
	$sd = 9.14;
} elseif ($age < 35 && $age > 24 && $education > 12) {
	$m = 65.71;
	$sd = 11.64;
} elseif ($age > 34 && $age < 45 && $education < 13) {
	$m = 59.87;
	$sd = 10.49;
} elseif ($age > 34 && $age < 45 && $education > 12) {
	$m = 60.95;
	$sd = 11.32;
} elseif ($age > 44 && $age < 55 && $education < 13) {
	$m = 53.91;
	$sd = 10.40;
} elseif ($age > 44 && $age < 55 && $education > 12) {
	$m = 58.31;
	$sd = 8.67;
} elseif ($age > 54 && $age < 65 && $education < 13) {
	$m = 49.03;
	$sd = 9.03;
} elseif ($age > 54 && $age < 65 && $education > 12) {
	$m = 54.47;
	$sd = 8.93;
} elseif ($age > 64 && $education < 13) {
	$m = 33.31;
	$sd = 11.26;
} elseif ($age > 64 && $education > 12) {
	$m = 52.89;
	$sd = 13.54;
} else {
	$m = null;
	$sd = null;
}

if (isset($sd) && !empty($sd) && $sd <> 0) {
	$norm_z = ($norm_array['symbol_digit_raw'] - $m)/$sd;
	$result = round(100 + ($norm_z*15), 2);
} else {
	$result = "N/A";
}
$result_values['symbol_digit_tscore_norm'] = $result;
# $this->module->emDebug("Result for symbol digit: " . $result);


# This is for COWAT
if ($education < 13) {
	$m = 36.5;
	$sd = 9.9;
} elseif ($education > 12 && $education < 16) {
	$m = 40;
	$sd = 9.7;
} elseif ($education > 15) {
	$m = 43.8;
	$sd = 10.6;
} else {
	$m = null;
	$sd = null;
}

if (isset($sd) && !empty($sd) && $sd <> 0) {
	$norm_z = ($norm_array['cowat_raw'] - $m)/$sd;
	$result = round(100 + ($norm_z*15), 2);
} else {
	$result = "N/A";
}
$result_values['cowat_tscore_norm'] = $result;
# $this->module->emDebug("Result for cowat: " . $result);


# This is for Word Reading
$src_wr = $norm_array['word_reading_raw'];
$src_cn = $norm_array['color_naming_raw'];
$src_cw = $norm_array['color_word_raw'];

if (isset($src_wr) && !empty($src_wr)) {
	$word_reading_lookup = $readFile->returnResults($filePath . "word_reading.csv");
	$stroop_dev = round($src_wr - (80.305 + 1.971*$education - 0.105*$age), 0);
	# We're adding 50 because the index should go from 0-101 instead of -50 to 50.
	if ($stroop_dev >= -50 && $stroop_dev <= 50) {
		$stroop_tscore = $word_reading_lookup['T'][$stroop_dev+50];
		$result = round(($stroop_tscore - 50)/10*15 + 100, 2);
	} else {
		$result = "OOB";
	}
} else {
	$result = "N/A";
}
$result_values['word_reading_tscore_norm'] = $result;
# $this->module->emDebug("Result for word reading: " . $result);


# This is for Color Naming
if (isset($src_cn) && !empty($src_cn)) {
	$color_naming_lookup = $readFile->returnResults($filePath . "color_naming.csv");
	$stroop_dev = round($src_cn - (68.81 + 1.026*$education - 0.1434*$age), 0);
	# We're adding 50 because the index should go from 0-101 instead of -50 to 50.
	if ($stroop_dev >= -50 && $stroop_dev <= 50) {
		$stroop_tscore = $color_naming_lookup['T'][$stroop_dev+50];
		$result = round(($stroop_tscore - 50)/10*15 + 100, 2);
	} else {
		$result = "OOB";
	}
} else {
	$result = "N/A";
}
$result_values['color_naming_tscore_norm'] = $result;
# $this->module->emDebug("Result for color naming: " . $result);


# This is for Color Word
if (isset($src_cw) && !empty($src_cw)) {
	$color_word_lookup = $readFile->returnResults($filePath . "color_word.csv");
	$stroop_dev = round($src_cw - (32.3655 + 1.351*$education - 0.231*$age), 0);
	# We're adding 50 because the index should go from 0-101 instead of -50 to 50.
	if ($stroop_dev >= -50 && $stroop_dev <= 50) {
		$stroop_tscore = $color_word_lookup['T'][$stroop_dev+50];
		$result = round(($stroop_tscore - 50)/10*15 + 100, 2);
	} else {
		$result = "OOB";
	}
} else {
	$result = null;
}
$result_values['color_word_tscore_norm'] = $result;
# $this->module->emDebug("Result for color word: " . $result);


# This is for interference
$adjusted = ($src_wr * $src_cn)/($src_wr + $src_cn);
$stroop_dev = round($src_cw - $adjusted, 0);
# We're adding 50 because the index should go from 0-101 instead of -50 to 50.
if ($stroop_dev >= -50 && $stroop_dev <= 50) {
	$interference_lookup = $readFile->returnResults($filePath . "interference.csv");
	$stroop_tscore = $interference_lookup['T'][$stroop_dev+50];
	$result = round(($stroop_tscore - 50)/10*15 + 100, 2);
} else {
	$result = "OOB";
}
$result_values['interference_tscore_norm'] = $result;
# $this->module->emDebug("Result for interference: " . $result);


# This is for the 3 HVLT scoring: Imm, Del, Ret
if ($age >= 16 && $age <= 19) {
	$hvlt_file = 'hvlt_16_19.csv';
} elseif ($age >= 20 && $age <= 29) {
	$hvlt_file = 'hvlt_20_29.csv';
} elseif ($age >= 30 && $age <= 34) {
	$hvlt_file = 'hvlt_30_34.csv';
} elseif ($age >= 35 && $age <= 39) {
	$hvlt_file = 'hvlt_35_39.csv';
} elseif ($age >= 40 && $age <= 44) {
	$hvlt_file = 'hvlt_40_44.csv';
} elseif ($age >= 45 && $age <= 49) {
	$hvlt_file = 'hvlt_45_49.csv';
} elseif ($age >= 50 && $age <= 54) {
	$hvlt_file = 'hvlt_50_54.csv';
} elseif ($age >= 55 && $age <= 59) {
	$hvlt_file = 'hvlt_55_59.csv';
} elseif ($age >= 60 && $age <= 64) {
	$hvlt_file = 'hvlt_60_64.csv';
} elseif ($age >= 65 && $age <= 69) {
	$hvlt_file = 'hvlt_65_69.csv';
} elseif ($age >= 70 && $age <= 74) {
	$hvlt_file = 'hvlt_70_74.csv';
} elseif ($age >= 75 && $age <= 79) {
	$hvlt_file = 'hvlt_75_79.csv';
} elseif ($age >= 80 && $age <= 84) {
	$hvlt_file = 'hvlt_80_84.csv';
} elseif ($age >= 85) {
	$hvlt_file = 'hvlt_85_and_up.csv';
} else {
	$hvlt_file = null;
}

if (!is_null($hvlt_file) && !empty($hvlt_file)) {
	$hvlt_imm = $norm_array['hvlt_imm_raw'];
	$hvlt_del = $norm_array['hvlt_del_raw'];
	$hvlt_ret = $norm_array['hvlt_ret_raw'];

	$hvlt_lookup = $readFile->returnResults($filePath . $hvlt_file);

	# For hvlt_imm
	$index = array_search($hvlt_imm, $hvlt_lookup['Total Recall']);
	$norm_z = $hvlt_lookup['T-Score'][$index];
	# $this->module->emDebug("hmlt_imm value: " . $hvlt_imm . "; and lookup value: " . $norm_z);
	if (is_numeric($norm_z)) {
		$result_imm = ($norm_z - 50)/10*15 + 100;		
	} else {
		$result_imm = "<55";
	}

	# For hvlt_del
	$index = array_search($hvlt_del, $hvlt_lookup['Delayed Recall']);
	$norm_z = $hvlt_lookup['T-Score'][$index];
	# $this->module->emDebug("hmlt_del value: " . $hvlt_del . "; and lookup value: " . $norm_z);
	if (is_numeric($norm_z)) {
		$result_del = ($norm_z - 50)/10*15 + 100;
	} else {
		$result_del = "<55";
	}

	# For hvlt_ret
	$index = array_search(floor($hvlt_ret), $hvlt_lookup['Retention']);
	$norm_z = $hvlt_lookup['T-Score'][$index];
	$this->module->emDebug("hvlt_ret: " . floor($hvlt_ret) . ", index: " . $index . ", norm_z: " . $norm_z);
	if (is_numeric($norm_z)) {
		$result_ret = ($norm_z - 50)/10*15 + 100;
	} else {
		$result_ret = "<55";
	}

} else {
	$result_imm = "N/A";
	$result_del = "N/A";
	$result_ret = "N/A";
}

$result_values['hvlt_imm_tscore_norm'] = $result_imm;
$result_values['hvlt_del_tscore_norm'] = $result_del;
$result_values['hvlt_ret_tscore_norm'] = $result_ret;
#$this->module->emDebug("Result for hvlt imm: " . $result_imm);
#$this->module->emDebug("Result for hvlt del: " . $result_del);
#$this->module->emDebug("Result for hvlt ret: " . $result_ret);


# This is an alternate COWAT lookup table for CFL MOANS Norms for Persons Aged 56-97
if ($age >= 56 && $age <=97) {
	if ($age >= 56 && $age <= 62) {
		$cowat_lookup = $readFile->returnResults($filePath . "cowat_56_62.csv");
	} elseif ($age >= 63 && $age <= 65 ) {
		$cowat_lookup = $readFile->returnResults($filePath . "cowat_63_65.csv");
	} elseif ($age >= 66 && $age <= 68 ) {
		$cowat_lookup = $readFile->returnResults($filePath . "cowat_66_68.csv");
	} elseif ($age >= 69 && $age <= 71 ) {
		$cowat_lookup = $readFile->returnResults($filePath . "cowat_69_71.csv");
	} elseif ($age >= 72 && $age <= 74 ) {
		$cowat_lookup = $readFile->returnResults($filePath . "cowat_72_74.csv");
	} elseif ($age >= 75 && $age <= 77 ) {
		$cowat_lookup = $readFile->returnResults($filePath . "cowat_75_77.csv");
	} elseif ($age >= 78 && $age <= 80 ) {
		$cowat_lookup = $readFile->returnResults($filePath . "cowat_78_80.csv");
	} elseif ($age >= 81 && $age <= 83 ) {
		$cowat_lookup = $readFile->returnResults($filePath . "cowat_81_83.csv");
	} elseif ($age >= 84 && $age <= 86 ) {
		$cowat_lookup = $readFile->returnResults($filePath . "cowat_84_86.csv");
	} elseif ($age >= 87 && $age <= 89 ) {
		$cowat_lookup = $readFile->returnResults($filePath . "cowat_87_89.csv");
	} elseif ($age >= 90 && $age <= 97 ) {
		$cowat_lookup = $readFile->returnResults($filePath . "cowat_90_97.csv");
	}

	$cowat_raw = $norm_array['cowat_raw'];
	$scaled_score = null;
	$cowat_size = count($cowat_lookup['raw_min']);

	if ($cowat_raw < $cowat_lookup['raw_min'][0]) {
		$scaled_score = $cowat_lookup['Scaled Score'][0];
	} elseif ($cowat_raw > $cowat_lookup['raw_max'][$cowat_size-1]) {
		$scaled_score = $cowat_lookup['Scaled Score'][$cowat_size-1];
	} else {
		for ($i = 1; $i < $cowat_size-2; $i++) {
			if (($cowat_raw >= $cowat_lookup['raw_min'][$i]) && ($cowat_raw <= $cowat_lookup['raw_max'][$i])) {
				$scaled_score = $cowat_lookup['Scaled Score'][$i];
				break;
			} 
		}	
	}
	
	# Convert scaled score to Standardized score
	$standardized_score = ($scaled_score - 10)/3*15 + 100;
} else {
	$standardized_score = "N/A";
}

$result_values['cowat_standardized_score'] = $standardized_score;
#$this->module->emDebug("Cowat standardized score: " . $result_values['cowat_standardized_score']);



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
