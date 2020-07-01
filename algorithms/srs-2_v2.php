<?php
/**

	SRS-2
	
	A REDCap AutoScoring Algorithm File
	
	- There exists an array called $src that contains the data from the source project
	- There can exist an optional array called $manual_result_fields that can override the default_result_fields
	- The final results should be presented in an array called $algorithm_results

**/

# REQUIRED: Summarize this algorithm
$algorithm_summary = "SRS-2 School-Age v2 Testing.  This algorithm calculates the RAW scores and looks up the tvalue scores.  It assumes the questions are coded as 1-4 for all questions.  The algorithm handles reversing the scoring on certain questions.";

# REQUIRED: Define an array of fields that must be present for this algorithm to run
$prefix = 'srs_';
// Replace as necessary for your particular scoring algorithm
$required_fields = array();
foreach (range(1,65) as $i) {
	array_push($required_fields, $prefix.$i);
}

# Since the tvalue lookup tables are based on gender, we need to include gender in the required list.
array_push($required_fields, "gender");

# REQUIRED: Define $default_result_fields array of default result field_names to record the summary data
$categories = array(
	'awr',	//
	'cog',	//
	'com',	//
	'mot',	//
	'rrb',	//
	'sci',	// sum of awr, cog, com, mot
	'total'
);
$default_result_fields = array();
$tval_scores = array();
foreach ($categories as $c) {
	array_push($default_result_fields, $prefix.$c."_raw");		//raw score
	array_push($tval_scores, $prefix.$c."_tval");
}
$default_result_fields = array_merge($default_result_fields, $tval_scores);
$this->module->emDebug("DRF: " . $default_result_fields);

### VALIDATION ###
# If we are simply verifying the result and required fields, we can exit now.
if (isset($verify) && $verify === true) return true;


# Define Log Array - optionally used if specified by the log_field in the configuration project
$algorithm_log = array();
$algorithm_log[] = "Scored using " . basename(__FILE__, ".php") . " at " . date("Y-m-d H:i:s");

# Override required_fields array with manual field names specified by user (optional)
if (!empty($manual_source_fields)) {
	$this->module->emDebug("Manual Source Fields" . $manual_source_fields);
	$this->module->emDebug("Required Fields" . $required_fields);
	if (count($manual_source_fields) == count($required_fields)) {
		foreach($manual_source_fields as $k => $field) {
			if ($field) {	// Only replace non-empty field names (this allows someone to use ,,,newname,, in list)
				$required_fields[$k] = $field;
				$this->module->emDebug("changing $k to $field");
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
	return;	//Since this is being called via include, the main script will continue to process other algorithms
}
# Check that all required fields have a value
$null_fields = array();
foreach ($required_fields as $rf) {
	if (empty($src[$rf]) && !is_numeric($src[$rf])) $null_fields[] = $rf;
}
if (!empty($null_fields)) {
	$algorithm_log[] = "WARNING - Required fields are empty (".implode(',',$null_fields).")";
	return false;	// Skip.  This is most commonly occurring in a multi-page survey.  We don't want to compute unless all is done.
}


### IMPLEMENT SCORING ###

# Since this is a subgroup scoring algoritm, divide the fields into the desired groups
// To get raw values, we need to summ up scores for each category based on scoreing sheet.
// The redcap values are 1-4 but the score sheet uses 0-3.  Some questions are reversed.
$reversedQuestions = array(3,7,11,12,15,17,21,22,26,32,38,40,43,45,48,52,55);
$normalizedSource = array();	// This is an array to hold the source data converted to a 0-3 scale
foreach ($required_fields as $i => $field_name) {
	if (in_array($i+1, $reversedQuestions,true)) {
		// reverse (1=>3, 2=>2, 3=>1, 4=>0)
		$normalizedSource[$field_name] = (($src[$field_name] * -1) + 4);
		$this->module->emDebug("Question $i should be reversed");
	} else {
		// convert 1-4 to 0-3
		$normalizedSource[$field_name] = $src[$field_name] -1;
		$this->module->emDebug("Question $i should be NOT reversed");
	}
}
//$this->module->emDebug("SRC: " . $src);
$this->module->emDebug("NSRC: " . $normalizedSource);


// Create groups for scoring
$groups = array(
	'awr' => array(2,7,25,32,45,52,54,56),
	'cog' => array(5,10,15,17,30,40,42,44,48,58,59,62),
	'com' => array(12,13,16,18,19,21,22,26,33,35,36,37,38,41,46,47,51,53,55,57,60,61),
	'mot' => array(1,3,6,9,11,23,27,34,43,64,65),
	'rrb' => array(4,8,14,20,24,28,29,31,39,49,50,63)
);
$groups['sci'] = array_values(array_diff(range(1,65),$groups['rrb']));
$groups['total'] = range(1,65);
$this->module->emDebug("GROUPS: " . $groups);


# Next, we go through each group and substitute in the actual source data for each question
# When this is done, we have an array where the key is each group and the elemnts are an array of
# question numbers and results:
// [rbs_sub3] => Array ([rbs_15] => 3,[rbs_16] => 3,[rbs_17] => 3, ...),
// [rbs_sub4] => Array (...)
// Since our required_fields array is indexed at 0 (so question 1 is at 0, I need to add a dummy value to do the alignment)
array_unshift($required_fields, 'dummy_value');
$src_groups = array();
foreach($groups as $name => $question_numbers) {
	// Take the list of question numbers and get the field_names from the required_fields array
	$question_fields = array_intersect_key($required_fields, array_flip($question_numbers));
	//$this->module->emDebug("Question Fields: " . $question_fields);
	
	// Now, get the values from the normalizedSource using the field_names from above.
	$src_groups[$name] = array_intersect_key($normalizedSource, array_flip($question_fields));
}
//$this->module->emDebug("SOURCE GROUPS: " . $src_groups);


# Calculate our Totals
$result_values = array();
foreach ($src_groups as $name => $data) {	
	$raw = array_sum($data);
//	list($std, $pct, $convmsg) = convert($name, $raw, $age, $sex);
//	if ($convmsg) $algorithm_log[] = $convmsg;	
	$result_values[$name.'_raw'] = $raw;
//	$result_values[$name.'_percent'] = $pct;
}
//$this->module->emDebug("DRF: " . $default_result_fields);
//$this->module->emDebug("RV: " . $result_values);

# Lookup the tscores
# The tables are based on gender.  The values for gender are: 1 = male and 2 = female
$tvalue_matrix = array(
	1 => array(
		'awr' => array(
			0 => 30, 1 => 33, 2 => 36, 3 => 39, 4 => 43, 5 => 46, 6 => 49, 7 => 52,
			8 => 55, 9 => 59, 10 => 62, 11 => 65, 12 => 68, 13 => 72, 14 => 75,
			15 => 78, 16 => 81, 17 => 85, 18 => 88, 19 => 91, 20 => 94, 21 => 97,
			22 => 101, 23 => 104, 24 => 107
			),
		'cog' => array(
			0 => 36, 1 => 39, 2 => 41, 3 => 43, 4 => 45, 5 => 48, 6 => 50, 7 => 52,
			8 => 54, 9 => 56, 10 => 59, 11 => 61, 12 => 63, 13 => 65, 14 => 68,
			15 => 70, 16 => 72, 17 => 74, 18 => 76, 19 => 79, 20 => 81, 21 => 83,
			22 => 85, 23 => 88, 24 => 90, 25 => 92, 26 => 94, 27 => 96, 28 => 99,
			29 => 101, 30 => 103, 31 => 105, 32 => 108, 33 => 110, 34 => 112,
			35 => 114, 36 => 116
			),
		'com' => array(
			0 => 36, 1 => 37, 2 => 38, 3 => 39, 4 => 41, 5 => 42, 6 => 43, 7 => 45,
			8 => 46, 9 => 47, 10 => 48, 11 => 50, 12 => 51, 13 => 52, 14 => 53,
			15 => 55, 16 => 56, 17 => 57, 18 => 58, 19 => 60, 20 => 61, 21 => 62,
			22 => 64, 23 => 65, 24 => 66, 25 => 67, 26 => 69, 27 => 70, 28 => 71,
			29 => 72, 30 => 74, 31 => 75, 32 => 76, 33 => 77, 34 => 79, 35 => 80,
			36 => 81, 37 => 83, 38 => 84, 39 => 85, 40 => 86, 41 => 88, 42 => 89,
			43 => 90, 44 => 91, 45 => 93, 46 => 94, 47 => 95, 48 => 96, 49 => 98,
			50 => 99, 51 => 100, 52 => 102, 53 => 103, 54 => 104, 55 => 105, 56 => 107,
			57 => 108, 58 => 109, 59 => 110, 60 => 112, 61 => 113, 62 => 114,
			63 => 115, 64 => 117, 65 => 118, 66 => 119
			),
		'mot' => array(
			0 => 37, 1 => 40, 2 => 42, 3 => 44, 4 => 47, 5 => 49, 6 => 51, 7 => 54,
			8 => 56, 9 => 59, 10 => 61, 11 => 63, 12 => 66, 13 => 68, 14 => 70,
			15 => 73, 16 => 75, 17 => 78, 18 => 80, 19 => 82, 20 => 85, 21 => 87,
			22 => 90, 23 => 92, 24 => 94, 25 => 97, 26 => 99, 27 => 101, 28 => 104,
			29 => 106, 30 => 109, 31 => 111, 32 => 113, 33 => 116
			),
		'rrb' => array(
			0 => 40, 1 => 42, 2 => 44, 3 => 46, 3 => 46, 5 => 51, 6 => 53, 7 => 55,
			8 => 58, 9 => 60, 10 => 62, 11 => 65, 12 => 67, 13 => 69, 14 => 71, 
			15 => 74, 16 => 76, 17 => 78, 18 => 80, 19 => 83, 20 => 85, 21 => 87,
			22 => 90, 23 => 92, 24 => 94, 25 => 96, 26 => 99, 27 => 101, 28 => 103,
			29 => 105, 30 => 108, 31 => 110, 32 => 112, 33 => 115, 34 => 117, 35 => 119,
			36 => 121
			),
		'total' => array(
			0 => 34, 1 => 34, 2 => 35, 3 => 35, 4 => 36, 5 => 36, 6 => 37,
			7 => 37, 8 => 38, 9 => 38, 10 => 39, 11 => 39, 12 => 40, 13 => 40,
			14 => 41, 15 => 41, 16 => 42, 17 => 42, 18 => 42, 19 => 43, 20 => 43,
			21 => 44, 22 => 44, 23 => 45, 24 => 45, 25 => 46, 26 => 46, 27 => 47,
			28 => 47, 29 => 48, 30 => 48, 31 => 49, 32 => 49, 33 => 50, 34 => 50,
			35 => 51, 36 => 51, 37 => 52, 38 => 52, 39 => 53, 40 => 53, 41 => 53,
			42 => 54, 43 => 54, 44 => 55, 45 => 55, 46 => 56, 47 => 56, 48 => 57,
			49 => 57, 50 => 58, 51 => 58, 52 => 59, 53 => 59, 54 => 60, 55 => 60,
			56 => 61, 57 => 61, 58 => 62, 59 => 62, 60 => 63, 61 => 63, 62 => 64,
			63 => 64, 64 => 64, 65 => 65, 66 => 65, 67 => 66, 68 => 66, 69 => 67,
			70 => 67, 71 => 68, 72 => 68, 73 => 69, 74 => 69, 75 => 70, 76 => 70,
			77 => 71, 78 => 71, 79 => 72, 80 => 72, 81 => 73, 82 => 73, 83 => 74,
			84 => 74, 85 => 75, 86 => 75, 87 => 76, 88 => 76, 89 => 76, 90 => 77,
			91 => 77, 92 => 78, 93 => 78, 94 => 79, 95 => 79, 96 => 80, 97 => 80,
			98 => 81, 99 => 81, 100 => 82, 101 => 82, 102 => 83, 103 => 83, 104 => 84,
			105 => 84, 106 => 85, 107 => 85, 108 => 86, 109 => 86, 110 => 87, 111 => 87,
			112 => 87, 113 => 88, 114 => 88, 115 => 89, 116 => 89, 117 => 90, 118 => 90,
			119 => 91, 120 => 91, 121 => 92, 122 => 92, 123 => 93, 124 => 93,
			125 => 94, 126 => 94, 127 => 95, 128 => 95, 129 => 96, 130 => 96,
			131 => 97, 132 => 97, 133 => 98, 134 => 98, 135 => 98, 136 => 99,
			137 => 99, 138 => 100, 139 => 100, 140 => 101, 141 => 101, 142 => 102,
			143 => 102, 144 => 103, 145 => 103, 146 => 104, 147 => 104, 148 => 105,
			149 => 105, 150 => 106, 151 => 106, 152 => 107, 153 => 107, 154 => 108,
			155 => 108, 156 => 109, 157 => 109, 158 => 109, 159 => 110, 160 => 110,
			161 => 111, 162 => 111, 163 => 112, 164 => 112, 165 => 113, 166 => 113,
			167 => 114, 168 => 114, 169 => 115, 170 => 115, 171 => 116, 172 => 116,
			173 => 117, 174 => 117, 175 => 118, 176 => 118, 177 => 119, 178 => 119,
			179 => 120, 180 => 120, 181 => 120, 182 => 121, 183 => 121, 184 => 122,
			185 => 122, 186 => 123, 187 => 123, 188 => 124, 189 => 124, 190 => 125,
			191 => 125, 192 => 126, 193 => 126, 194 => 127, 195 => 127
			)
		),
	2 => array(
		'awr' => array(
			0 => 30, 1 => 34, 2 => 38, 3 => 41, 4 => 45, 5 => 48, 6 => 52,
			7 => 55, 8 => 59, 9 => 63, 10 => 66, 11 => 70, 12 => 73, 13 => 77,
			14 => 80, 15 => 84, 16 => 88, 17 => 91, 18 => 95, 19 => 98, 20 => 102,
			21 => 105, 22 => 109, 23 => 113, 24 => 116
			),
		'cog' => array(
			0 => 38, 1 => 40, 2 => 42, 3 => 45, 4 => 47, 5 => 50, 6 => 52,
			7 => 55, 8 => 57, 9 => 60, 10 => 62, 11 => 64, 12 => 67, 13 => 69,
			14 => 72, 15 => 74, 16 => 77, 17 => 79, 18 => 81, 19 => 84, 19 => 84,
			20 => 86, 21 => 89, 22 => 91, 23 => 94, 24 => 96, 25 => 99, 26 => 101,
			27 => 103, 28 => 106, 29 => 108, 30 => 111, 31 => 113, 32 => 116, 33 => 118,
			34 => 120, 35 => 123, 36 => 125			
			),	
		'com' => array(
			0 => 37, 1 => 38, 2 => 40, 3 => 41, 4 => 43, 5 => 44, 6 => 46,
			7 => 47, 8 => 49, 9 => 50, 10 => 52, 11 => 53, 12 => 55, 13 => 56,
			14 => 58, 15 => 59, 16 => 60, 17 => 62, 18 => 63, 19 => 65,
			20 => 66, 21 => 68, 22 => 69, 23 => 71, 24 => 72, 25 => 74, 26 => 75,
			27 => 77, 28 => 78, 29 => 80, 30 => 81, 31 => 83, 32 => 84, 33 => 85,
			34 => 87, 35 => 88, 36 => 90, 37 => 91, 38 => 93, 39 => 94, 40 => 96,
			41 => 97, 42 => 99, 43 => 100, 44 => 102, 45 => 103, 46 => 105, 47 => 106,
			48 => 108, 49 => 109, 50 => 110, 51 => 112, 52 => 113, 53 => 115, 54 => 116,
			55 => 118, 56 => 119, 57 => 121, 58 => 122, 59 => 124, 60 => 125, 61 => 127,
			62 => 128, 63 => 130, 64 => 131, 65 => 133, 66 => 134
			),	
		'mot' => array(
			0 => 38, 1 => 40, 2 => 43, 3 => 45, 4 => 48, 5 => 50, 6 => 53, 
			7 => 55, 8 => 58, 9 => 60, 10 => 62, 11 => 65, 12 => 67, 13 => 70,
			14 => 72, 15 => 75, 16 => 77, 17 => 80, 18 => 82, 19 => 84, 20 => 87,
			21 => 89, 22 => 92, 23 => 94, 24 => 97, 25 => 99, 26 => 101, 27 => 104,
			28 => 106, 29 => 109, 30 => 111, 31 => 114, 32 => 116, 33 => 119
			),	
		'rrb' => array(
			0 => 41, 1 => 44, 2 => 46, 3 => 49, 4 => 52, 5 => 55, 6 => 58, 
			7 => 61, 8 => 64, 9 => 67, 10 => 70, 11 => 73, 12 => 76, 13 => 79,
			14 => 82, 15 => 85, 16 => 88, 17 => 91, 18 => 94, 19 => 96, 20 => 99,
			21 => 102, 22 => 105, 23 => 108, 24 => 111, 25 => 114, 26 => 117, 27 => 120,
			28 => 123, 29 => 126, 30 => 129, 31 => 132, 32 => 135, 33 => 138, 34 => 141,
			35 => 144, 36 => 146
			),	
		'total' => array(
			0 => 35, 1 => 35, 2 => 36, 3 => 36, 4 => 37, 5 => 38, 6 => 38,
			7 => 39, 8 => 39, 9 => 40, 10 => 40, 11 => 41, 12 => 41, 13 => 42,
			14 => 42, 15 => 43, 16 => 44, 17 => 44, 18 => 45, 19 => 45, 20 => 46,
			21 => 46, 22 => 47, 23 => 47, 24 => 48, 25 => 49, 26 => 49, 27 => 50,
			28 => 50, 29 => 51, 30 => 51, 31 => 52, 32 => 52, 33 => 53, 34 => 54,
			35 => 54, 36 => 55, 37 => 55, 38 => 56, 39 => 56, 40 => 57, 41 => 57,
			42 => 58, 43 => 59, 44 => 59, 45 => 60, 46 => 60, 47 => 61, 48 => 61,
			49 => 62, 50 => 62, 51 => 63, 52 => 63, 53 => 64, 54 => 65, 55 => 65,
			56 => 66, 57 => 66, 58 => 67, 59 => 67, 60 => 68, 61 => 68, 62 => 69,
			63 => 70, 64 => 70, 65 => 71, 66 => 71, 67 => 72, 68 => 72, 69 => 73,
			70 => 73, 71 => 74, 72 => 75, 73 => 75, 74 => 76, 75 => 76, 76 => 77,
			77 => 77, 78 => 78, 79 => 78, 80 => 79, 81 => 80, 82 => 80, 83 => 81,
			84 => 81, 85 => 82, 86 => 82, 87 => 83, 88 => 83, 89 => 84, 90 => 84,
			91 => 85, 92 => 86, 93 => 86, 94 => 87, 95 => 87, 96 => 88, 97 => 88,
			98 => 89, 99 => 89, 100 => 90, 101 => 91, 102 => 91, 103 => 92, 104 => 92,
			105 => 93, 106 => 93, 107 => 94, 108 => 94, 109 => 95, 110 => 96, 111 => 96,
			112 => 97, 113 => 97, 114 => 98, 115 => 98, 116 => 99, 117 => 99, 118 => 100,
			119 => 100, 120 => 101, 121 => 102, 122 => 102, 123 => 103, 124 => 103,
			125 => 104, 126 => 104, 127 => 105, 128 => 105, 129 => 106, 130 => 107,
			131 => 107, 132 => 108, 133 => 108, 134 => 109, 135 => 109, 136 => 110,
			137 => 110, 138 => 111, 139 => 112, 140 => 112, 141 => 113, 142 => 113,
			143 => 114, 144 => 114, 145 => 115, 146 => 115, 147 => 116, 148 => 117,
			149 => 117, 150 => 118, 151 => 118, 152 => 119, 153 => 119, 154 => 120,
			155 => 120, 156 => 121, 157 => 121, 158 => 122, 159 => 123, 160 => 123,
			161 => 124, 162 => 124, 163 => 125, 164 => 125, 165 => 126, 166 => 126,
			167 => 127, 168 => 128, 169 => 128, 170 => 129, 171 => 129, 172 => 130,
			173 => 130, 174 => 131, 175 => 131, 176 => 132, 177 => 133, 178 => 133,
			179 => 134, 180 => 134, 181 => 135, 182 => 135, 183 => 136, 184 => 136,
			185 => 137, 186 => 138, 187 => 138, 188 => 139, 189 => 139, 190 => 140,
			191 => 140, 192 => 141, 193 => 141, 194 => 142, 195 => 142
			)
		)	
	);

# find the value of gender and make sure it is a valid number
$valid_gender_values = array(1, 2);
$gender_field_name = $required_fields[66];
$gender = $src[$gender_field_name];


# Go lookup the tvalue for the raw scores that were calculated above
# There is no lookup for sci so skip it.  Sharon said they may just delete the calculation of
# sci also but until then, just skip the lookup.
$result_tvals = array();
if (in_array($gender, $valid_gender_values) ) {
	$gender_matrix = $tvalue_matrix[$gender];
	foreach($categories as $name => $cat_name) {
		if ($cat_name != 'sci') {
			$raw_scores = $result_values[$cat_name.'_raw'];
			$tmatrix = $gender_matrix[$cat_name];
			if (isset($tmatrix[$raw_scores])) {
				$result_tvals[$cat_name.'_tvals'] = $tmatrix[$raw_scores];
			} else {
				$result_tvals[$cat_name.'_tvals'] = NULL; 
			}
		} else {
			$result_tvals[$cat_name.'_tvals'] = 'n/a';
		}
	}
}

$result_values = array_merge($result_values, $result_tvals);

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
