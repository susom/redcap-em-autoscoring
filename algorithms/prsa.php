<?php
/**

	BASC2 - Parent Rating Scales - Adolescent
	
	A REDCap AutoScoring Algorithm File
	
	- There exists an array called $src that contains the data from the source project
	- There can exist an optional array called $manual_result_fields that can override the default_result_fields
	- The final results should be presented in an array called $algorithm_results
        - The answers are categorized as:
              1 = Never
              2 = Sometimes
              3 = Often
              4 = Almost Always
        - There are 150 questions that the participant fills out.

**/

# REQUIRED: Summarize this algorithm
$algorithm_summary = "BASC2, Parent Rating Scales - Adolescent.  The scales used in the lookup are the Clinical Combined Scales.";
$this->module->emDebug("Scoring Title: " . $algorithm_summary);


# REQUIRED: Define an array of default result field_names to record the summary data
# The BASC2 - PRS-A scoring is as follows:
#    Accumulated values and Omitted values
# If any of the subscales is missing more than 2 values, do not scale that subscale.
# This clinical scales/subscales are: 
#    Hyperactivity, Aggression, Conduct Problems, Anxiety, Depression, Somalization,
#    Atypicality, Withdrawal, Attention Problems, Adaptability, Social Skills,
#    Leadership, Activities of Daily Living, Functional Communication
# Once the clincal scales are calculated, calculate the composite score for:
#    Externalizing Problems Composite, Internalizing Problems Composite, 
#    Behaviorial Symptoms Index and Adaptive Skills Composite
# These composite scores also have lookup tables for T scores.

$default_result_fields = array(
        'prsa_hyp_raw',  'prsa_agg_raw',  'prsa_cp_raw',   'prsa_anx_raw',
        'prsa_dep_raw',  'prsa_som_raw',  'prsa_aty_raw',  'prsa_wdl_raw',
        'prsa_apr_raw',  'prsa_ada_raw',  'prsa_ssk_raw',  'prsa_ldr_raw',
        'prsa_adl_raw',  'prsa_fc_raw',
        'prsa_hyp_null', 'prsa_agg_null', 'prsa_cp_null',  'prsa_anx_null',
        'prsa_dep_null', 'prsa_som_null', 'prsa_aty_null', 'prsa_wdl_null',
        'prsa_apr_null', 'prsa_ada_null', 'prsa_ssk_null', 'prsa_ldr_null',
        'prsa_adl_null', 'prsa_fc_null',
        'prsa_hyp_tval', 'prsa_agg_tval', 'prsa_cp_tval',  'prsa_anx_tval',
        'prsa_dep_tval', 'prsa_som_tval', 'prsa_aty_tval', 'prsa_wdl_tval',
        'prsa_apr_tval', 'prsa_ada_tval', 'prsa_ssk_tval', 'prsa_ldr_tval',
        'prsa_adl_tval', 'prsa_fc_tval',
        'prsa_hyp_tvalp', 'prsa_agg_tvalp', 'prsa_cp_tvalp',  'prsa_anx_tvalp',
        'prsa_dep_tvalp', 'prsa_som_tvalp', 'prsa_aty_tvalp', 'prsa_wdl_tvalp',
        'prsa_apr_tvalp', 'prsa_ada_tvalp', 'prsa_ssk_tvalp', 'prsa_ldr_tvalp',
        'prsa_adl_tvalp', 'prsa_fc_tvalp',
	'prsa_extprob_raw', 'prsa_intprob_raw', 'prsa_behsymp_raw', 'prsa_adaskill_raw',
	'prsa_behsymp_mean_val', 'prsa_adaskill_mean_val',
	'prsa_extprob_tval', 'prsa_intprob_tval', 'prsa_behsymp_tval', 'prsa_adaskill_tval'
);

# REQUIRED: Define an array of fields that must be present for this algorithm to run
$required_fields = array();
foreach (range(1,150) as $i) {
	array_push($required_fields, "prsa_$i");
}

//$this->module->emDebug("Required input field names ".implode(',',$required_fields));

# Override default input array with manual field names specified by user (optional)
if (!empty($manual_source_fields)) {
    if (count($manual_source_fields) == count($required_fields)) {
            foreach($manual_source_fields as $k => $field) {
            	if ($field) {   // Only replace non-empty field names (this allows someone to use ,,,newname,, in list)
            		$required_fields[$k] = $field;
            		$this->module->emDebug("Changing input field ".$k." to ".$field);
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

### VALIDATION ##
# If we are simply verifying the result and required fields, we can exit now.
if (isset($verify) && $verify === true) return true;

# Define Log Array - optionally used if specified by the log_field in the configuration project
$algorithm_log = array();
$algorithm_log[] = "Scored using " . basename(__FILE__, ".php") . " at " . date("Y-m-d H:i:s");

$this->module->emDebug("number of manual result fields = " . count($manual_result_fields));
$this->module->emDebug("number of required result fields = " . count($default_result_fields));

# Override default result array with manual field names specified by user (optional)
if (!empty($manual_result_fields)) {
	if (count($manual_result_fields) == count($default_result_fields)) {
		foreach($manual_result_fields as $k => $field) {
			if ($field) {	// Only replace non-empty field names (this allows someone to use ,,,newname,, in list)
				$default_result_fields[$k] = $field;
				$this->module->emDebug("Changing result field ".$k." to ". $field);
			}
		}
		$log[] = "Overriding default result field names with ". implode(',',$manual_result_fields);
	} else {
		$msg = count($manual_result_fields) . " manual result fields specified, but the algorithm needs " . count($default_result_fields) . " fields.";
		$this->module->emError($msg);
		$algorithm_log[] = $msg;
#		return false;
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
#	return false;	//Since this is being called via include, the main script will continue to process other algorithms
}

# The values coming in are 1=Never, 2=Sometimes, 3=Often and 4=Almost Always. The first thing we need to do
# is to subtract one from the value because the scoring is assuming 0-3 instead of 1-4. 
# These are some fields that need their values flipped before scoring. Instead of going from 1 => 4, they go from 3 => 0
$new_source = array();
$flipped_values = array(5,14,26,63,65,76,81,91,104,106,107,108,122,146);
foreach ($required_fields as $i => $req_name) {
	$val = $src[$req_name];

	if (in_array(($i+1), $flipped_values)) {
		$new_source[$req_name] = (isset($val) and (strlen($val) > 0)) ? 4-$val : null;
#		$this->module->emDebug("Flipped values: i=$i, field_name = $req_name, val = $val, new source = $new_source[$req_name]");
	} else {
		$new_source[$req_name] = (isset($val)) ? $val-1 : null;
	}
}


### IMPLEMENT RAW SCORING ###
# This is the array source
# Define lists of questions that correspond to each subscale
# Note: Most questions are scored as N=0, S=1, O=2, A=3 but some questions have reversed scoring
#    The following scoring should already be reversed from above: 5,14,26,63,65,76,81,91,104,106,107,108,122,146
$source_names = array("hyp","agg","cp","anx","dep","som","aty","wdl","apr","ada","ssk","ldr","adl","fc"); 
$source_indexes = array (
	'hyp' => array(135,105,75,45,15,80,50,20),
	'agg' => array(124,94,64,34,4,130,100,70,40,10),
	'cp'  => array(133,103,73,43,13,139,109,79,49,19,119,89,59,29),
	'anx' => array(102,72,42,12,143,113,83,53,23,58,28),
	'dep' => array(128,98,68,38,8,142,112,82,52,22,90,60,30),
	'som' => array(129,99,69,39,9,46,16,145,115,85,55),
	'aty' => array(131,101,71,51,21,147,117,87,57,27),
	'wdl' => array(134,104,74,14,44,148,118,88),
	'apr' => array(5,35,65,136,106,76),
	'ada' => array(91,1,31,61,108,18,48,78),
	'ssk' => array(126,96,6,36,66,24,54,84),
	'ldr' => array(127,97,7,37,67,17,47,77,150,120),
	'adl' => array(3,33,63,93,123,81,111,141),
	'fc'  => array(122,2,32,62,92,137,107,146,26,56,86,116)
);
$mult_factor = array(
	'hyp' => 1,
	'agg' => 1,
	'cp'  => 0,
	'anx' => 1,
	'dep' => 0,
	'som' => 0,
	'aty' => 0,
	'wdl' => 1,
	'apr' => 1,
	'ada' => 2,
	'ssk' => 2,
	'ldr' => 2,
	'adl' => 2,
	'fc'  => 2
	);

$raw_scores = array();
$null_counter = array();
foreach ($source_names as $i => $field_name) {
	$raw_scores[$field_name] = 0;
	$null_counter[$field_name] = 0;
}

# Add up each array and count how many blank answers there are.
foreach ($required_fields as $i => $field_name) {
	$val = $new_source[$field_name];
	$index = $i+1;

	foreach ($source_names as $j => $result_name) {
		$target_result = $source_indexes[$result_name];
		if (in_array($index, $target_result)) {
			if (isset($val) and strlen($val) > 0) {
				$raw_scores[$result_name] += $val;
			} else {
				$null_counter[$result_name]++;
			}
       		}
	}
}


# These are the Tvalue and Tvalue percentage tables
$tvalue = array(
	'hyp' => array(
			 0 => 35,  1 => 37,  2 => 39,  3 => 42,  4 => 44,  5 => 46,  6 => 48,  7 => 51,
			 8 => 53,  9 => 55, 10 => 57, 11 => 59, 12 => 62, 13 => 64, 14 => 66, 15 => 68,
			16 => 71, 17 => 73, 18 => 75, 19 => 77, 20 => 80, 21 => 82, 22 => 84, 23 => 86,
			24 => 88
		),
	'agg' => array(
			 0 => 37,  1 => 39,  2 => 41,  3 => 43,  4 => 44,  5 => 46,  6 => 48,  7 => 50,
			 8 => 52,  9 => 54, 10 => 56, 11 => 58, 12 => 59, 13 => 61, 14 => 63, 15 => 65,
			16 => 67, 17 => 69, 18 => 71, 19 => 73, 20 => 74, 21 => 76, 22 => 78, 23 => 80,
			24 => 82, 25 => 84, 26 => 86, 27 => 88, 28 => 89, 29 => 91, 30 => 93
		),
	'cp' => array(
			 0 => 38,  1 => 40,  2 => 41,  3 => 43,  4 => 45,  5 => 47,  6 => 48,  7 => 50,
			 8 => 52,  9 => 54, 10 => 55, 11 => 57, 12 => 59, 13 => 61, 14 => 62, 15 => 64,
			16 => 66, 17 => 68, 18 => 69, 19 => 71, 20 => 73, 21 => 75, 22 => 76, 23 => 78,
			24 => 80, 25 => 82, 26 => 83, 27 => 85, 28 => 87, 29 => 89, 30 => 90, 31 => 92,
                        32 => 94, 33 => 96, 34 => 97, 35 => 99, 36 => 101, 37 => 103, 38 => 104, 39 => 106,
			40 => 108, 41 => 109, 42 => 111
		),
	'anx' => array(
			 0 => 30,  1 => 32,  2 => 34,  3 => 35,  4 => 37,  5 => 39,  6 => 41,  7 => 43,
			 8 => 45,  9 => 47, 10 => 49, 11 => 51, 12 => 53, 13 => 55, 14 => 57, 15 => 59,
			16 => 61, 17 => 63, 18 => 65, 19 => 67, 20 => 69, 21 => 71, 22 => 73, 23 => 75,
			24 => 76, 25 => 78, 26 => 80, 27 => 82, 28 => 84, 29 => 86, 30 => 88, 31 => 90,
			32 => 92, 33 => 94
		),
	'dep' => array(
			 0 => 37,  1 => 38,  2 => 40,  3 => 42,  4 => 43,  5 => 45,  6 => 47,  7 => 48,
			 8 => 50,  9 => 51, 10 => 53, 11 => 55, 12 => 56, 13 => 58, 14 => 60, 15 => 61,
			16 => 63, 17 => 64, 18 => 66, 19 => 68, 20 => 69, 21 => 71, 22 => 73, 23 => 74,
			24 => 76, 25 => 77, 26 => 79, 27 => 81, 28 => 82, 29 => 84, 30 => 86, 31 => 87,
			32 => 89, 33 => 90, 34 => 92, 35 => 94, 36 => 95, 37 => 97, 38 => 99, 39 => 100
		),
	'som' => array(
			 0 => 38,  1 => 41,  2 => 43,  3 => 45,  4 => 48,  5 => 50,  6 => 52,  7 => 55,
			 8 => 57,  9 => 59, 10 => 62, 11 => 64, 12 => 66, 13 => 69, 14 => 71, 15 => 73,
			16 => 76, 17 => 78, 18 => 80, 19 => 83, 20 => 85, 21 => 87, 22 => 90, 23 => 92,
			24 => 94, 25 => 97, 26 => 99, 27 => 101, 28 => 104, 29 => 106, 30 => 108, 31 => 111,
			32 => 113, 33 => 116	
		),
	'aty' => array(
			 0 => 40,  1 => 42,  2 => 45,  3 => 47,  4 => 50,  5 => 52,  6 => 55,  7 => 58,
			 8 => 60,  9 => 63, 10 => 65, 11 => 68, 12 => 70, 13 => 73, 14 => 75, 15 => 78,
			16 => 80, 17 => 83, 18 => 85, 19 => 88, 20 => 91, 21 => 93, 22 => 96, 23 => 98,
			24 => 101, 25 => 103, 26 => 106, 27 => 108, 28 => 111, 29 => 113, 30 => 116
		),
	'wdl' => array(
			 0 => 34,  1 => 36,  2 => 39,  3 => 41,  4 => 43,  5 => 46,  6 => 48,  7 => 50,
			 8 => 52,  9 => 55, 10 => 57, 11 => 59, 12 => 62, 13 => 64, 14 => 66, 15 => 68,
			16 => 71, 17 => 73, 18 => 75, 19 => 78, 20 => 80, 21 => 82, 22 => 84, 23 => 87,
			24 => 89
		),
	'apr' => array(
			 0 => 31,  1 => 33,  2 => 36,  3 => 38,  4 => 41,  5 => 43,  6 => 45,  7 => 48,
			 8 => 50,  9 => 53, 10 => 55, 11 => 57, 12 => 60, 13 => 62, 14 => 65, 15 => 67,
			16 => 69, 17 => 72, 18 => 74
		),
	'ada' => array(
			 0 => 23,  1 => 25,  2 => 27,  3 => 29,  4 => 31,  5 => 33,  6 => 35,  7 => 37,
			 8 => 39,  9 => 41, 10 => 43, 11 => 45, 12 => 47, 13 => 49, 14 => 51, 15 => 53,
			16 => 55, 17 => 57, 18 => 59, 19 => 61, 20 => 63, 21 => 65, 22 => 67, 23 => 69,
			24 => 71
		),
	'ssk' => array(
			 0 => 25,  1 => 27,  2 => 29,  3 => 30,  4 => 32,  5 => 34,  6 => 36,  7 => 38,
			 8 => 40,  9 => 42, 10 => 44, 11 => 46, 12 => 48, 13 => 50, 14 => 52, 15 => 54,
			16 => 56, 17 => 58, 18 => 60, 19 => 62, 20 => 64, 21 => 66, 22 => 68, 23 => 70,
			24 => 72
		),
	'ldr' => array(
			 0 => 27,  1 => 28,  2 => 30,  3 => 32,  4 => 33,  5 => 35,  6 => 37,  7 => 39,
			 8 => 40,  9 => 42, 10 => 44, 11 => 45, 12 => 47, 13 => 49, 14 => 50, 15 => 52,
			16 => 54, 17 => 55, 18 => 57, 19 => 59, 20 => 60, 21 => 62, 22 => 64, 23 => 65,
			24 => 67, 25 => 69, 26 => 71, 27 => 72, 28 => 74, 29 => 76, 30 => 77
		),
	'adl' => array(
			 0 => 22,  1 => 24,  2 => 26,  3 => 28,  4 => 30,  5 => 32,  6 => 34,  7 => 37,
			 8 => 39,  9 => 41, 10 => 43, 11 => 45, 12 => 47, 13 => 49, 14 => 51, 15 => 53,
			16 => 55, 17 => 57, 18 => 59, 19 => 61, 20 => 63, 21 => 65, 22 => 68, 23 => 70,
			24 => 72
		),
	'fc' => array(
			 0 => 15,  1 => 17,  2 => 18,  3 => 20,  4 => 21,  5 => 23,  6 => 24,  7 => 26,
			 8 => 27,  9 => 29, 10 => 30, 11 => 32, 12 => 33, 13 => 35, 14 => 36, 15 => 38,
			16 => 39, 17 => 41, 18 => 42, 19 => 44, 20 => 45, 21 => 46, 22 => 48, 23 => 49,
			24 => 51, 25 => 52, 26 => 54, 27 => 55, 28 => 57, 29 => 58, 30 => 60, 31 => 61,
			32 => 63, 33 => 64, 34 => 66, 35 => 67, 36 => 69
		)
	);


$tvalue_perc = array(
	'hyp' => array(
			 0 =>  1,  1 =>  5,  2 => 13,  3 => 22,  4 => 32,  5 => 41,  6 => 50,  7 => 59,
			 8 => 66,  9 => 73, 10 => 78, 11 => 83, 12 => 87, 13 => 90, 14 => 92, 15 => 94,
			16 => 96, 17 => 97, 18 => 98, 19 => 99, 20 => 99, 21 => 99, 22 => 99, 23 => 99,
			24 => 99
		),
	'agg' => array(
			 0 =>  2,  1 =>  8,  2 => 17,  3 => 26,  4 => 36,  5 => 44,  6 => 52,  7 => 59,
			 8 => 65,  9 => 71, 10 => 75, 11 => 80, 12 => 83, 13 => 86, 14 => 89, 15 => 91,
			16 => 93, 17 => 94, 18 => 95, 19 => 96, 20 => 97, 21 => 98, 22 => 99, 23 => 99,
			24 => 99, 25 => 99, 26 => 99, 27 => 99, 28 => 99, 29 => 99, 30 => 99
		),
	'cp' => array(
			 0 =>  3,  1 =>  9,  2 => 18,  3 => 28,  4 => 38,  5 => 46,  6 => 54,  7 => 61,
			 8 => 67,  9 => 72, 10 => 77, 11 => 80, 12 => 84, 13 => 86, 14 => 89, 15 => 91,
			16 => 92, 17 => 94, 18 => 95, 19 => 96, 20 => 96, 21 => 97, 22 => 98, 23 => 98,
			24 => 98, 25 => 99, 26 => 99, 27 => 99, 28 => 99, 29 => 99, 30 => 99, 31 => 99,
                        32 => 99, 33 => 99, 34 => 99, 35 => 99, 36 => 99, 37 => 99, 38 => 99, 39 => 99,
			40 => 99, 41 => 99, 42 => 99
		),
	'anx' => array(
			 0 =>  1,  1 =>  2,  2 =>  3,  3 =>  6,  4 =>  9,  5 => 13,  6 => 19,  7 => 26,
			 8 => 33,  9 => 42, 10 => 50, 11 => 58, 12 => 65, 13 => 72, 14 => 77, 15 => 82,
			16 => 86, 17 => 90, 18 => 92, 19 => 94, 20 => 96, 21 => 97, 22 => 98, 23 => 98,
			24 => 99, 25 => 99, 26 => 99, 27 => 99, 28 => 99, 29 => 99, 30 => 99, 31 => 99,
			32 => 99, 33 => 99
		),
	'dep' => array(
			 0 =>  1,  1 =>  6,  2 => 13,  3 => 21,  4 => 29,  5 => 37,  6 => 45,  7 => 52,
			 8 => 59,  9 => 65, 10 => 70, 11 => 74, 12 => 78, 13 => 81, 14 => 84, 15 => 87,
			16 => 89, 17 => 91, 18 => 92, 19 => 94, 20 => 95, 21 => 96, 22 => 96, 23 => 97,
			24 => 98, 25 => 98, 26 => 98, 27 => 99, 28 => 99, 29 => 99, 30 => 99, 31 => 99,
			32 => 99, 33 => 99, 34 => 99, 35 => 99, 36 => 99, 37 => 99, 38 => 99, 39 => 99
		),
	'som' => array(
			 0 =>  4,  1 => 14,  2 => 27,  3 => 39,  4 => 50,  5 => 59,  6 => 67,  7 => 74,
			 8 => 79,  9 => 84, 10 => 87, 11 => 90, 12 => 92, 13 => 94, 14 => 96, 15 => 97,
			16 => 98, 17 => 98, 18 => 99, 19 => 99, 20 => 99, 21 => 99, 22 => 99, 23 => 99,
			24 => 99, 25 => 99, 26 => 99, 27 => 99, 28 => 99, 29 => 99, 30 => 99, 31 => 99,
			32 => 99, 33 => 99	
		),
	'aty' => array(
			 0 =>  9,  1 => 24,  2 => 38,  3 => 50,  4 => 60,  5 => 68,  6 => 75,  7 => 80,
			 8 => 85,  9 => 88, 10 => 91, 11 => 93, 12 => 95, 13 => 96, 14 => 97, 15 => 98,
			16 => 99, 17 => 99, 18 => 99, 19 => 99, 20 => 99, 21 => 99, 22 => 99, 23 => 99,
			24 => 99, 25 => 99, 26 => 99, 27 => 99, 28 => 99, 29 => 99, 30 => 99
		),
	'wdl' => array(
			 0 =>  2,  1 =>  6,  2 => 12,  3 => 19,  4 => 28,  5 => 38,  6 => 47,  7 => 56,
			 8 => 64,  9 => 71, 10 => 77, 11 => 82, 12 => 87, 13 => 90, 14 => 93, 15 => 95,
			16 => 96, 17 => 98, 18 => 98, 19 => 99, 20 => 99, 21 => 99, 22 => 99, 23 => 99,
			24 => 99
		),
	'apr' => array(
			 0 =>  2,  1 =>  5,  2 =>  9,  3 => 14,  4 => 20,  5 => 27,  6 => 34,  7 => 42,
			 8 => 50,  9 => 58, 10 => 66, 11 => 73, 12 => 81, 13 => 87, 14 => 93, 15 => 97,
			16 => 99, 17 => 99, 18 => 99
		),
	'ada' => array(
			 0 =>  1,  1 =>  1,  2 =>  1,  3 =>  1,  4 =>  2,  5 =>  4,  6 =>  7,  7 => 10,
			 8 => 14,  9 => 19, 10 => 25, 11 => 31, 12 => 38, 13 => 45, 14 => 53, 15 => 60,
			16 => 67, 17 => 73, 18 => 79, 19 => 85, 20 => 89, 21 => 93, 22 => 96, 23 => 98,
			24 => 99
		),
	'ssk' => array(
			 0 =>  1,  1 =>  1,  2 =>  1,  3 =>  2,  4 =>  4,  5 =>  6,  6 =>  9,  7 => 13,
			 8 => 18,  9 => 24, 10 => 30, 11 => 36, 12 => 43, 13 => 50, 14 => 57, 15 => 64,
			16 => 71, 17 => 77, 18 => 82, 19 => 87, 20 => 91, 21 => 94, 22 => 97, 23 => 98,
			24 => 99
		),
	'ldr' => array(
			 0 =>  1,  1 =>  1,  2 =>  1,  3 =>  2,  4 =>  4,  5 =>  6,  6 =>  9,  7 => 13,
			 8 => 18,  9 => 23, 10 => 28, 11 => 34, 12 => 40, 13 => 47, 14 => 53, 15 => 59,
			16 => 65, 17 => 70, 18 => 75, 19 => 80, 20 => 84, 21 => 87, 22 => 90, 23 => 93,
			24 => 95, 25 => 97, 26 => 98, 27 => 99, 28 => 99, 29 => 99, 30 => 99
		),
	'adl' => array(
			 0 =>  1,  1 =>  1,  2 =>  1,  3 =>  2,  4 =>  3,  5 =>  5,  6 =>  7,  7 => 11,
			 8 => 14,  9 => 19, 10 => 24, 11 => 30, 12 => 37, 13 => 44, 14 => 51, 15 => 59,
			16 => 66, 17 => 73, 18 => 80, 19 => 86, 20 => 91, 21 => 95, 22 => 98, 23 => 99,
			24 => 99
		),
	'fc' => array(
			 0 =>  1,  1 =>  1,  2 =>  1,  3 =>  1,  4 =>  1,  5 =>  1,  6 =>  1,  7 =>  1,
			 8 =>  1,  9 =>  2, 10 =>  2, 11 =>  4, 12 =>  5, 13 =>  7, 14 => 10, 15 => 12,
			16 => 15, 17 => 19, 18 => 23, 19 => 27, 20 => 32, 21 => 36, 22 => 41, 23 => 47,
			24 => 52, 25 => 57, 26 => 62, 27 => 68, 28 => 73, 29 => 77, 30 => 82, 31 => 86,
			32 => 89, 33 => 92, 34 => 95, 35 => 97, 36 => 98
		)
	);

function lookup_tvalue($tvalue_matrix, $raw_score) {

   if (isset($tvalue_matrix[$raw_score])) {
	$result = $tvalue_matrix[$raw_score];
   } else {
	$result = NULL;
   }
   return $result;
}


# If there are more than 2 empty fields in the list, do not save the scoring.  
# Also, the score must not exceed the maximum value specified.
$raw_score_totals = array();
$tvalue_scores = array();
$tvalue_percent = array();
foreach ($source_names as $i => $result_name) {
	$raw_score_val = $raw_scores[$result_name];
	$null_vals = $null_counter[$result_name];

# Add in the null contributions to calculate the total raw score
	if ($null_vals > 2) {
		$raw_score_val = NULL;
	} else {
		$raw_score_val += $mult_factor[$result_name] * $null_vals;
	}

	$raw_score_totals[$result_name . '_null'] = $raw_score_val;

	if (isset($raw_score_val)) {
		$tvalue_scores[$result_name . '_tval'] = lookup_tvalue($tvalue[$result_name], $raw_score_val);
		$tvalue_percent[$result_name . '_tvalp'] = lookup_tvalue($tvalue_perc[$result_name], $raw_score_val);
	} else {
		$tvalue_scores[$result_name . '_tval'] = NULL;
		$tvalue_percent[$result_name . '_tvalp'] = NULL;
	}
}

# Now calculate the composite scores
# These are the lookup tables for the composite score t values
$composite_names = array('extprob', 'intprob', 'behsymp', 'adaskill');
$composite_calc = array(
		'extprob' => array('hyp', 'agg', 'cp'),
		'intprob' => array('anx', 'dep', 'som'),
		'behsymp' => array('aty', 'wdl', 'apr'),
		'adaskill' => array('ada', 'ssk', 'ldr', 'adl', 'fc')
			);
$composite_comb_tvalues = array(
		'extprob' => array( 
			35 => array('min' => 110, 'max' => 110), 36 => array('min' => 111, 'max' => 113), 37 => array('min' => 114, 'max' => 115),
			38 => array('min' => 116, 'max' => 118), 39 => array('min' => 119, 'max' => 121), 40 => array('min' => 122, 'max' => 124),
			41 => array('min' => 125, 'max' => 126), 42 => array('min' => 127, 'max' => 129), 43 => array('min' => 130, 'max' => 132),
			44 => array('min' => 133, 'max' => 134), 45 => array('min' => 135, 'max' => 137), 46 => array('min' => 138, 'max' => 140),
			47 => array('min' => 141, 'max' => 143), 48 => array('min' => 144, 'max' => 145), 49 => array('min' => 146, 'max' => 148),
			50 => array('min' => 149, 'max' => 151), 51 => array('min' => 152, 'max' => 154), 52 => array('min' => 155, 'max' => 156),
			53 => array('min' => 157, 'max' => 159), 54 => array('min' => 160, 'max' => 162), 55 => array('min' => 163, 'max' => 165),
			56 => array('min' => 166, 'max' => 167), 57 => array('min' => 168, 'max' => 170), 58 => array('min' => 171, 'max' => 173),
			59 => array('min' => 174, 'max' => 175), 60 => array('min' => 176, 'max' => 178), 61 => array('min' => 179, 'max' => 181),
			62 => array('min' => 182, 'max' => 184), 63 => array('min' => 185, 'max' => 186), 64 => array('min' => 187, 'max' => 189),
			65 => array('min' => 190, 'max' => 192), 66 => array('min' => 193, 'max' => 195), 67 => array('min' => 196, 'max' => 197),
			68 => array('min' => 198, 'max' => 200), 69 => array('min' => 201, 'max' => 203), 70 => array('min' => 204, 'max' => 206),
			71 => array('min' => 207, 'max' => 208), 72 => array('min' => 209, 'max' => 211), 73 => array('min' => 212, 'max' => 214),
			74 => array('min' => 215, 'max' => 217), 75 => array('min' => 218, 'max' => 219), 76 => array('min' => 220, 'max' => 222),
			77 => array('min' => 223, 'max' => 225), 78 => array('min' => 226, 'max' => 227), 79 => array('min' => 228, 'max' => 230),
			80 => array('min' => 231, 'max' => 233), 81 => array('min' => 234, 'max' => 236), 82 => array('min' => 237, 'max' => 238),
			83 => array('min' => 239, 'max' => 241), 84 => array('min' => 242, 'max' => 244), 85 => array('min' => 245, 'max' => 247),
			86 => array('min' => 248, 'max' => 249), 87 => array('min' => 250, 'max' => 252), 88 => array('min' => 253, 'max' => 255),
			89 => array('min' => 256, 'max' => 258), 90 => array('min' => 259, 'max' => 260), 91 => array('min' => 261, 'max' => 263),
			92 => array('min' => 264, 'max' => 266), 93 => array('min' => 267, 'max' => 268), 94 => array('min' => 269, 'max' => 271),
			95 => array('min' => 272, 'max' => 274), 96 => array('min' => 275, 'max' => 277), 97 => array('min' => 278, 'max' => 279),
			98 => array('min' => 280, 'max' => 282), 99 => array('min' => 283, 'max' => 285), 100 => array('min' => 286, 'max' => 288),
			101 => array('min' => 289, 'max' => 290), 102 => array('min' => 291, 'max' => 292)
			),
		'intprob' => array(
			32 => array('min' => 105, 'max' => 106), 33 => array('min' => 107, 'max' => 109), 34 => array('min' => 110, 'max' => 111),
			35 => array('min' => 112, 'max' => 114), 36 => array('min' => 115, 'max' => 116), 37 => array('min' => 117, 'max' => 119),
			38 => array('min' => 120, 'max' => 121), 39 => array('min' => 122, 'max' => 124), 40 => array('min' => 125, 'max' => 126),
			41 => array('min' => 127, 'max' => 129), 42 => array('min' => 130, 'max' => 131), 43 => array('min' => 132, 'max' => 134),
			44 => array('min' => 135, 'max' => 136), 45 => array('min' => 137, 'max' => 138), 46 => array('min' => 139, 'max' => 141),
			47 => array('min' => 142, 'max' => 143), 48 => array('min' => 144, 'max' => 146), 49 => array('min' => 147, 'max' => 148),
			50 => array('min' => 149, 'max' => 151), 51 => array('min' => 152, 'max' => 153), 52 => array('min' => 154, 'max' => 156),
			53 => array('min' => 157, 'max' => 158), 54 => array('min' => 159, 'max' => 161), 55 => array('min' => 162, 'max' => 163),
			56 => array('min' => 164, 'max' => 165), 57 => array('min' => 166, 'max' => 168), 58 => array('min' => 169, 'max' => 170),
			59 => array('min' => 171, 'max' => 173), 60 => array('min' => 174, 'max' => 175), 61 => array('min' => 176, 'max' => 178),
			62 => array('min' => 179, 'max' => 180), 63 => array('min' => 181, 'max' => 183), 64 => array('min' => 184, 'max' => 185),
			65 => array('min' => 186, 'max' => 188), 66 => array('min' => 189, 'max' => 190), 67 => array('min' => 191, 'max' => 193),
			68 => array('min' => 194, 'max' => 195), 69 => array('min' => 196, 'max' => 197), 70 => array('min' => 198, 'max' => 200),
			71 => array('min' => 201, 'max' => 202), 72 => array('min' => 203, 'max' => 205), 73 => array('min' => 206, 'max' => 207),
			74 => array('min' => 208, 'max' => 210), 75 => array('min' => 211, 'max' => 212), 76 => array('min' => 213, 'max' => 215),
			77 => array('min' => 216, 'max' => 217), 78 => array('min' => 218, 'max' => 220), 79 => array('min' => 221, 'max' => 222),
			80 => array('min' => 223, 'max' => 224), 81 => array('min' => 225, 'max' => 227), 82 => array('min' => 228, 'max' => 229),
			83 => array('min' => 230, 'max' => 232), 84 => array('min' => 233, 'max' => 234), 85 => array('min' => 235, 'max' => 237),
			86 => array('min' => 238, 'max' => 239), 87 => array('min' => 240, 'max' => 242), 88 => array('min' => 243, 'max' => 244),
			89 => array('min' => 245, 'max' => 247), 90 => array('min' => 248, 'max' => 249), 91 => array('min' => 250, 'max' => 252),
			92 => array('min' => 253, 'max' => 254), 93 => array('min' => 255, 'max' => 256), 94 => array('min' => 257, 'max' => 259),
			95 => array('min' => 260, 'max' => 261), 96 => array('min' => 262, 'max' => 264), 97 => array('min' => 265, 'max' => 266),
			98 => array('min' => 267, 'max' => 269), 99 => array('min' => 270, 'max' => 271), 100 => array('min' => 272, 'max' => 274),
			101 => array('min' => 275, 'max' => 276), 102 => array('min' => 277, 'max' => 279), 103 => array('min' => 280, 'max' => 281), 
			104 => array('min' => 282, 'max' => 283), 105 => array('min' => 284, 'max' => 286), 106 => array('min' => 287, 'max' => 288), 
			107 => array('min' => 289, 'max' => 291), 108 => array('min' => 292, 'max' => 293), 109 => array('min' => 294, 'max' => 296), 
			110 => array('min' => 297, 'max' => 298), 111 => array('min' => 299, 'max' => 301), 112 => array('min' => 302, 'max' => 303), 
			113 => array('min' => 304, 'max' => 306), 114 => array('min' => 307, 'max' => 308), 115 => array('min' => 309, 'max' => 310) 
			),
		'behsymp' => array(
			31 => array('min' => 214, 'max' => 214), 32 => array('min' => 215, 'max' => 218), 33 => array('min' => 219, 'max' => 223),
			34 => array('min' => 224, 'max' => 227), 35 => array('min' => 228, 'max' => 232), 36 => array('min' => 233, 'max' => 237),
			37 => array('min' => 238, 'max' => 241), 38 => array('min' => 242, 'max' => 246), 39 => array('min' => 247, 'max' => 251),
			40 => array('min' => 252, 'max' => 255), 41 => array('min' => 256, 'max' => 260), 42 => array('min' => 261, 'max' => 265),
			43 => array('min' => 266, 'max' => 269), 44 => array('min' => 270, 'max' => 274), 45 => array('min' => 275, 'max' => 279),
			46 => array('min' => 280, 'max' => 283), 47 => array('min' => 284, 'max' => 288), 48 => array('min' => 289, 'max' => 293),
			49 => array('min' => 294, 'max' => 297), 50 => array('min' => 298, 'max' => 302), 51 => array('min' => 303, 'max' => 306),
			52 => array('min' => 307, 'max' => 311), 53 => array('min' => 312, 'max' => 316), 54 => array('min' => 317, 'max' => 320),
			55 => array('min' => 321, 'max' => 325), 56 => array('min' => 326, 'max' => 330), 57 => array('min' => 331, 'max' => 334),
			58 => array('min' => 335, 'max' => 339), 59 => array('min' => 340, 'max' => 344), 60 => array('min' => 345, 'max' => 348),
			61 => array('min' => 349, 'max' => 353), 62 => array('min' => 354, 'max' => 358), 63 => array('min' => 359, 'max' => 362),
			64 => array('min' => 363, 'max' => 367), 65 => array('min' => 368, 'max' => 372), 66 => array('min' => 373, 'max' => 376),
			67 => array('min' => 377, 'max' => 381), 68 => array('min' => 382, 'max' => 385), 69 => array('min' => 386, 'max' => 390),
			70 => array('min' => 391, 'max' => 395), 71 => array('min' => 396, 'max' => 399), 72 => array('min' => 400, 'max' => 404),
			73 => array('min' => 405, 'max' => 409), 74 => array('min' => 410, 'max' => 413), 75 => array('min' => 414, 'max' => 418),
			76 => array('min' => 419, 'max' => 423), 77 => array('min' => 424, 'max' => 427), 78 => array('min' => 428, 'max' => 432),
			79 => array('min' => 433, 'max' => 437), 80 => array('min' => 438, 'max' => 441), 81 => array('min' => 442, 'max' => 446),
			82 => array('min' => 447, 'max' => 451), 83 => array('min' => 452, 'max' => 455), 84 => array('min' => 456, 'max' => 460),
			85 => array('min' => 461, 'max' => 464), 86 => array('min' => 465, 'max' => 469), 87 => array('min' => 470, 'max' => 474),
			88 => array('min' => 475, 'max' => 478), 89 => array('min' => 479, 'max' => 483), 90 => array('min' => 484, 'max' => 488),
			91 => array('min' => 489, 'max' => 492), 92 => array('min' => 493, 'max' => 497), 93 => array('min' => 498, 'max' => 502),
			94 => array('min' => 503, 'max' => 506), 95 => array('min' => 507, 'max' => 511), 96 => array('min' => 512, 'max' => 516),
			97 => array('min' => 517, 'max' => 520), 98 => array('min' => 521, 'max' => 525), 99 => array('min' => 526, 'max' => 530),
			100 => array('min' => 531, 'max' => 534), 101 => array('min' => 535, 'max' => 539), 102 => array('min' => 540, 'max' => 543), 
			103 => array('min' => 544, 'max' => 548), 104 => array('min' => 549, 'max' => 553), 105 => array('min' => 554, 'max' => 557), 
			106 => array('min' => 558, 'max' => 560)
			),
		'adaskill' => array(
			18 => array('min' => 112, 'max' => 115), 19 => array('min' => 116, 'max' => 120), 20 => array('min' => 121, 'max' => 124),
			21 => array('min' => 125, 'max' => 128), 22 => array('min' => 129, 'max' => 132), 23 => array('min' => 133, 'max' => 137),
			24 => array('min' => 138, 'max' => 141), 25 => array('min' => 142, 'max' => 145), 26 => array('min' => 146, 'max' => 149),
			27 => array('min' => 150, 'max' => 154), 28 => array('min' => 155, 'max' => 158), 29 => array('min' => 159, 'max' => 162),
			30 => array('min' => 163, 'max' => 166), 31 => array('min' => 167, 'max' => 171), 32 => array('min' => 172, 'max' => 175),
			33 => array('min' => 176, 'max' => 179), 34 => array('min' => 180, 'max' => 183), 35 => array('min' => 184, 'max' => 188),
			36 => array('min' => 189, 'max' => 192), 37 => array('min' => 193, 'max' => 196), 38 => array('min' => 197, 'max' => 200),
			39 => array('min' => 201, 'max' => 205), 40 => array('min' => 206, 'max' => 209), 41 => array('min' => 210, 'max' => 213),
			42 => array('min' => 214, 'max' => 218), 43 => array('min' => 219, 'max' => 222), 44 => array('min' => 223, 'max' => 226),
			45 => array('min' => 227, 'max' => 230), 46 => array('min' => 231, 'max' => 235), 47 => array('min' => 236, 'max' => 239),
			48 => array('min' => 240, 'max' => 243), 49 => array('min' => 244, 'max' => 247), 50 => array('min' => 248, 'max' => 252),
			51 => array('min' => 253, 'max' => 256), 52 => array('min' => 257, 'max' => 260), 53 => array('min' => 261, 'max' => 264),
			54 => array('min' => 265, 'max' => 269), 55 => array('min' => 270, 'max' => 273), 56 => array('min' => 274, 'max' => 277),
			57 => array('min' => 278, 'max' => 281), 58 => array('min' => 282, 'max' => 286), 59 => array('min' => 287, 'max' => 290),
			60 => array('min' => 291, 'max' => 294), 61 => array('min' => 295, 'max' => 299), 62 => array('min' => 300, 'max' => 303),
			63 => array('min' => 304, 'max' => 307), 64 => array('min' => 308, 'max' => 311), 65 => array('min' => 312, 'max' => 316),
			66 => array('min' => 317, 'max' => 320), 67 => array('min' => 321, 'max' => 324), 68 => array('min' => 325, 'max' => 328),
			69 => array('min' => 329, 'max' => 333), 70 => array('min' => 334, 'max' => 337), 71 => array('min' => 338, 'max' => 341),
			72 => array('min' => 342, 'max' => 345), 73 => array('min' => 346, 'max' => 350), 74 => array('min' => 351, 'max' => 354),
			75 => array('min' => 355, 'max' => 358), 76 => array('min' => 359, 'max' => 361)
			)
	);

function lookup_composite_tvalue($tvalue_matrix, $raw_score) {

	$composite_tval = NULL;
	$min_name = 'min';
	$max_name = 'max';

	foreach ($tvalue_matrix as $i => $range) {
		if ($raw_score >= $range[$min_name] and $raw_score <= $range[$max_name]) {
			$composite_tval = $i;
			break;
		}
	}

	return $composite_tval;
}

$composite_raw = array();
$composite_tval = array();
$composite_mean = array();
foreach ($composite_names as $i => $field_names) {
	$result = 0;

	foreach ($composite_calc[$field_names] as $j => $subscale) {
		$name = $subscale . '_tval';
		$result += $tvalue_scores[$name];
	}

	$result_tval = lookup_composite_tvalue($composite_comb_tvalues[$field_names], $result); 
	$composite_raw[$field_names . '_comp'] = $result;
	$composite_tval[$field_names . '_comp_tval'] = $result_tval;
	if ($i >= 2) {
		$num_items = count($composite_calc[$field_names]);
		$composite_mean[$field_names . '_comp_mean'] = $result/$num_items;
	}
}

#$this->module->emDebug("composite raw: " . implode(',',$composite_raw));
#$this->module->emDebug("composite mean: " . implode(',', $composite_mean));
#$this->module->emDebug("composite tval: " . implode(',', $composite_tval));

### DEFINE RESULTS ###
# REQUIRED: The algorithm_results variable MUST BE USED as it is relied upon from the parent script.
#$totals =  array_merge($raw_score_totals, $tvalue_scores, $tvalue_percent, $composite_raw, $composite_mean, $composite_tval);
$totals =  array_merge($raw_score_totals, $null_counter, $tvalue_scores, $tvalue_percent, $composite_raw, $composite_mean, $composite_tval);
$algorithm_results = array_combine($default_result_fields, $totals);

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

