<?php
/**

	BASC2 - Parent Rating Scales - Adolescent
	
	A REDCap AutoScoring Algorithm File
	
	- There exists an array called $src that contains the data from the source project
	- There can exist an optional array called $manual_result_fields that can override the default_result_fields
	- The final results should be presented in an array called $algorithm_results
        - The answers are categorized as:
              0 = Never
              1 = Sometimes
              2 = Often
              3 = Almost Always
        - There are 150 questions that the participant fills out.

**/




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

$required_fields = array();

$this->module->emDebug("Form used:  " . $src['basc_prs_formused']);
if ($src['basc_prs_formused'] == "02")
{
	$this->module->emDebug("Using PRS-A");
	# REQUIRED: Summarize this algorithm
	$algorithm_summary = "BASC2, Parent Rating Scales - Adolescent";
	$this->module->emDebug("Scoring Title: " . $algorithm_summary);

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
	        'prsa_ada_valid', 'prsa_adl_valid', 'prsa_agg_valid', 'prsa_anx_valid',
	        'prsa_apr_valid', 'prsa_aty_valid', 'prsa_cp_valid', 'prsa_dep_valid',
	        'prsa_fc_valid', 'prsa_hyp_valid',  'prsa_ldr_valid', 'prsa_som_valid',
	        'prsa_ssk_valid', 'prsa_wdl_valid', 'prsa_findex', 'prsa_fv', 
	        'prsa_con',  'prsa_conv', 'prsa_patt', 'prsa_pattv', 'prsa_allval',
	        'prsa_90val', 'prsa_scaletotal',
		'prsa_extprob_raw', 'prsa_intprob_raw', 'prsa_behsymp_raw', 'prsa_adaskill_raw',
		'prsa_behsymp_mean_val', 'prsa_adaskill_mean_val',
		'prsa_extprob_tval', 'prsa_intprob_tval', 'prsa_behsymp_tval', 'prsa_adaskill_tval'
	);

	# REQUIRED: Define an array of fields that must be present for this algorithm to run
	$required_fields = array();
	foreach (range(1,150) as $i) {
		//array_push($required_fields, "prsa_$i");
		array_push($required_fields, "basc_prs_a_$i");
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

	$validity_arr = array(
		'hyp' => 0,
		'agg' => 0,
		'cp'  => 0,
		'anx' => 0,
		'dep' => 0,
		'som' => 0,
		'aty' => 0,
		'wdl' => 0,
		'apr' => 0,
		'ada' => 0,
		'ssk' => 0,
		'ldr' => 0,
		'adl' => 0,
		'fc'  => 0
	);

	$validity_key_source = array(
		'ada' => 'prsa_ada_valid',
		'adl' => 'prsa_adl_valid', 
		'agg' => 'prsa_agg_valid', 
		'anx' => 'prsa_anx_valid',
	    'apr' => 'prsa_apr_valid', 
	    'aty' => 'prsa_aty_valid', 
	    'cp' => 'prsa_cp_valid',
	    'dep' => 'prsa_dep_valid',
	    'fc' => 'prsa_fc_valid', 
	    'hyp' => 'prsa_hyp_valid',  
	    'ldr' => 'prsa_ldr_valid', 
	    'som' => 'prsa_som_valid',
	    'ssk' => 'prsa_ssk_valid', 
	    'wdl' => 'prsa_wdl_valid'
	);

	$F_index = 0;

	$F_index_values = array(
		33 => 0,
		75	=> 3,
		147	=> 3,
		148	=> 3,
		73	=> 3,
		32	=> 0,
		69	=> 3,
		9	=> 3,
		99	=> 3,
		133	=> 3,
		89	=> 3,
		130	=> 3,
		102	=> 3,
		23	=> 3,
		142	=> 3,
		79	=> 3,
		65	=> 0,
		5	=> 0,
		98	=> 3,
		34	=> 3
	);

	$cons_index_values = array(
		65 => 5,
		34 => 10,
		143 => 12,
		134 => 14,
		120 => 17,
		31 => 18,
		66 => 24,
		136 => 35,
		70 => 40,
		119 => 43,
		75 => 45,
		80 => 50, 
		90 => 60,
		97 => 62,
		133 => 73,
		106 => 76,
		139 => 79,
		100 => 82,
		128 => 112,
		145 => 129
	);

	$cons_index = 0;
	$resp_pattern = 0;
	$last_response = 0;


	# These are some fields that need their values flipped before scoring. Instead of going from 0 => 3, they go from 3 => 0
	$new_source = array();
	$flipped_values = array(5,14,26,63,65,76,81,91,104,106,107,108,122,146);
	foreach ($required_fields as $i => $req_name) {
		$index = $i+1;
		$val = $src[$req_name]-1;

		if (in_array(($index), $flipped_values)) {
			#$new_source[$req_name] = (isset($val) and (strlen($val) > 0)) ? 3-$val : null;
			$new_source[$req_name] = ($val != 4) ? 3-$val : null;
	#		$this->module->emDebug("Flipped values: i=$i, field_name = $req_name, val = $val, new source = $new_source[$req_name]");
		} else {
			#$new_source[$req_name] = (isset($val)) ? $val : null;
			$new_source[$req_name] = ($val != 4) ? $val : null;
			if (array_key_exists(($index), $F_index_values))
			{
				if ($val == $F_index_values[($index)])
				{
					$F_index++;
				}
			}
		}

		if (array_key_exists(($index), $cons_index_values))
			{
				$cons_pair_val = $new_source[$required_fields[$cons_index_values[($index)]]];
				if ($cons_pair_val != $new_source[$req_name])
				{
					$cons_index++;
				}

		}

		foreach ($source_indexes as $scale => $qs)
		{
			if (in_array(($index), $qs))
			{
				$currVal = $validity_arr[$scale];
				if (!(isset($val) and (strlen($val) <= 0)))
				{
					$validity_arr[$scale] = $currVal+1;
				}
			}
		}

		if ($index == 1)
		{
			$last_response = $val;
		}
		else 
		{
			if ($val != $last_response)
			{
				$resp_pattern++;
			}
			$last_response = $val;
		}
	}




	### IMPLEMENT RAW SCORING ###
	# This is the array source
	# Define lists of questions that correspond to each subscale
	# Note: Most questions are scored as N=0, S=1, O=2, A=3 but some questions have reversed scoring
	#    The following scoring should already be reversed from above: 5,14,26,63,65,76,81,91,104,106,107,108,122,146

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

	$q_answered = 150;

	foreach ($source_names as $i => $result_name) {
		$raw_score_val = $raw_scores[$result_name];
		$null_vals = $null_counter[$result_name];
		$q_answered -= $null_vals;
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

	# Completeness + Validity index 

	$completeness_interpret = array();
	foreach ($validity_key_source as $scale => $field_name)
	{
		$null_count = $null_counter[$scale];
		if ($null_count > 2)
		{
			$completeness_interpret[$field_name] = 0;
		}
		else
		{
			$completeness_interpret[$field_name] = 1;
		}
	}

	# F_index result
	$F_interpret = 1;
	if ($F_index >= 4 and $F_index <= 6)
	{
		$F_interpret = 2;
	}
	else if ($F_index >= 7 and $F_index <= 15)
	{
		$F_interpret = 3;
	}
	$F_index_result = array($F_index, $F_interpret);

	#Consistency Index result
	$cons_interpret = 1;
	if ($cons_index >= 14 and $cons_index <= 18)
	{
		$cons_interpret = 2;
	}
	else if ($cons_index >= 19)
	{
		$cons_interpret = 3;
	}
	$cons_index_result = array($cons_index, $cons_interpret);


	# Response Pattern result
	$resp_pattern_interpret = 0;
	if ($resp_pattern >= 62 and $resp_pattern <= 117)
	{
		$resp_pattern_interpret = 1;
	}
	else if ($resp_pattern >= 118 and $resp_pattern <= 150)
	{
		$resp_pattern_interpret = 2;
	}
	$resp_pattern_result = array($resp_pattern, $resp_pattern_interpret);

	#Overall Statistics result

	# AllVal
	$allval = 0;
	if ($q_answered == 150)
	{
		$allval = 1;
	}



	#90Val
	$val_90 = 0;
	$limit = 0.9*150;

	if ($q_answered >= $limit)
	{
		$val_90 = 1;
	}


	$validity_count = 0;
	foreach ($completeness_interpret as $field => $valid)
	{
		if ($valid == 1)
		{
			$validity_count++;
		}
	}
	$stat_result = array($allval, $val_90, $validity_count);

	#$this->module->emDebug("composite raw: " . implode(',',$composite_raw));
	#$this->module->emDebug("composite mean: " . implode(',', $composite_mean));
	#$this->module->emDebug("composite tval: " . implode(',', $composite_tval));

	### DEFINE RESULTS ###
	# REQUIRED: The algorithm_results variable MUST BE USED as it is relied upon from the parent script.
	$totals =  array_merge($raw_score_totals, $null_counter, $tvalue_scores, $tvalue_percent, $completeness_interpret, $F_index_result, $cons_index_result, $resp_pattern_result, $stat_result, $composite_raw, $composite_mean, $composite_tval);
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

}
else
{
	$algorithm_summary = "BASC2, Parent Rating Scales - Children";
	$this->module->emDebug("Scoring Title: " . $algorithm_summary);

	$this->module->emDebug("Using PRS-C");

	$default_result_fields = array();

	$default_result_fields = array(
	        'prsc_hyp_raw',  'prsc_agg_raw',  'prsc_cp_raw',   'prsc_anx_raw',
	        'prsc_dep_raw',  'prsc_som_raw',  'prsc_aty_raw',  'prsc_wdl_raw',
	        'prsc_apr_raw',  'prsc_ada_raw',  'prsc_ssk_raw',  'prsc_ldr_raw',
	        'prsc_adl_raw',  'prsc_fc_raw',
	        'prsc_hyp_null', 'prsc_agg_null', 'prsc_cp_null',  'prsc_anx_null',
	        'prsc_dep_null', 'prsc_som_null', 'prsc_aty_null', 'prsc_wdl_null',
	        'prsc_apr_null', 'prsc_ada_null', 'prsc_ssk_null', 'prsc_ldr_null',
	        'prsc_adl_null', 'prsc_fc_null',
	        'prsc_hyp_tval', 'prsc_agg_tval', 'prsc_cp_tval',  'prsc_anx_tval',
	        'prsc_dep_tval', 'prsc_som_tval', 'prsc_aty_tval', 'prsc_wdl_tval',
	        'prsc_apr_tval', 'prsc_ada_tval', 'prsc_ssk_tval', 'prsc_ldr_tval',
	        'prsc_adl_tval', 'prsc_fc_tval',
	        'prsc_hyp_tvalp', 'prsc_agg_tvalp', 'prsc_cp_tvalp',  'prsc_anx_tvalp',
	        'prsc_dep_tvalp', 'prsc_som_tvalp', 'prsc_aty_tvalp', 'prsc_wdl_tvalp',
	        'prsc_apr_tvalp', 'prsc_ada_tvalp', 'prsc_ssk_tvalp', 'prsc_ldr_tvalp',
	        'prsc_adl_tvalp', 'prsc_fc_tvalp',
	        'prsc_ada_valid', 'prsc_adl_valid', 'prsc_agg_valid', 'prsc_anx_valid',
			'prsc_apr_valid','prsc_aty_valid','prsc_cp_valid', 'prsc_dep_valid',
			'prsc_fc_valid', 'prsc_hyp_valid', 'prsc_ldr_valid', 'prsc_som_valid',
			'prsc_ssk_valid', 'prsc_wdl_valid', 'prsc_findex', 'prsc_fv', 'prsc_con',
			'prsc_conv', 'prsc_patt', 'prsc_pattv', 'prsc_allval', 'prsc_90val', 'prsc_scaletotal',
		'prsc_extprob_raw', 'prsc_intprob_raw', 'prsc_behsymp_raw', 'prsc_adaskill_raw',
		'prsc_behsymp_mean_val', 'prsc_adaskill_mean_val',
		'prsc_extprob_tval', 'prsc_intprob_tval', 'prsc_behsymp_tval', 'prsc_adaskill_tval'
	);

	$required_fields = array();
	foreach (range(1,160) as $i){
	        array_push($required_fields, "basc_prs_c_$i");
	}

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

	# Override default result array with manual field names specified by user (optional)
	if (!empty($manual_result_fields)) {
	        if (count($manual_result_fields) == count($default_result_fields)) {
	                foreach($manual_result_fields as $k => $field) {
	                        if ($field) {   // Only replace non-empty field names (this allows someone to use ,,,newname,, in list)
	                                $default_result_fields[$k] = $field;
									$this->module->emDebug("Changing result field ".$k." to ". $field);
	                        }
	                }
	                $log[] = "Overriding default result field names with ". implode(',',$manual_result_fields);
	        } else {
	                $msg = count($manual_result_fields) . " manual result fields specified, but the algorithm needs " . count($default_result_fields) . " fields.";
					$this->module->emError($msg);
	                $algorithm_log[] = $msg;
	#               return false;
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
	#       return false; //Since this is being called via include, the main script will continue to process other algorithms
	}

	#    The following scoring should already be reversed from above: 3,16,17,41,49,66,80,81,98,103,105,131,142,145
	$source_names = array("hyp","agg","cp","anx","dep","som","aty","wdl","apr","ada","ssk","ldr","adl","fc"); 
	$source_indexes = array(
	        'hyp' => array(6,38,70,102,134,20,52,84,116,148),
	        'agg' => array(8,40,72,104,136,24,56,88,26,58,91),
	        'cp'  => array(15,47,79,111,29,61,93,125,157),
	        'anx' => array(5,37,69,101,133,12,44,13,45,77,109,141,32,64),
	        'dep' => array(10,42,74,106,138,18,50,82,114,28,60,92,124,156),
	        'som' => array(54,86,118,150,59,91,123,30,62,94,126,158),
	        'aty' => array(11,43,75,107,139,23,55,87,119,151,96,128,160),
	        'wdl' => array(16,48,80,112,144,21,53,25,57,89,121,153),
	        'apr' => array(9,41,73,105,17,49),
	        'ada' => array(1,33,65,14,46,78,110,142),
	        'ssk' => array(85,117,149,31,63,95,127,159),
	        'ldr' => array(4,36,68,100,132,19,51,83),
	        'adl' => array(3,35,67,99,131,39,71,103),
	        'fc'  => array(34,66,98,130,76,108,140,81,113,145,122,154)
	);

	$validity_arr = array(
		'hyp' => 0,
		'agg' => 0,
		'cp'  => 0,
		'anx' => 0,
		'dep' => 0,
		'som' => 0,
		'aty' => 0,
		'wdl' => 0,
		'apr' => 0,
		'ada' => 0,
		'ssk' => 0,
		'ldr' => 0,
		'adl' => 0,
		'fc'  => 0
	);

	$validity_key_source = array(
		'ada' => 'prsc_ada_valid',
		'adl' => 'prsc_adl_valid', 
		'agg' => 'prsc_agg_valid', 
		'anx' => 'prsc_anx_valid',
	    'apr' => 'prsc_apr_valid', 
	    'aty' => 'prsc_aty_valid', 
	    'cp' => 'prsc_cp_valid',
	    'dep' => 'prsc_dep_valid',
	    'fc' => 'prsc_fc_valid', 
	    'hyp' => 'prsc_hyp_valid',  
	    'ldr' => 'prsc_ldr_valid', 
	    'som' => 'prsc_som_valid',
	    'ssk' => 'prsc_ssk_valid', 
	    'wdl' => 'prsc_wdl_valid'
	);

	$F_index = 0;

	$F_index_values = array(
		35 => 0,
		52	=> 3,
		160	=> 3,
		112	=> 3,
		47	=> 3,
		154	=> 0,
		91	=> 3,
		54	=> 3,
		157	=> 3,
		68	=> 0,
		136	=> 3,
		69	=> 3,
		114	=> 3,
		111	=> 3,
		41	=> 0,
		17	=> 0,
		124	=> 3,
		1	=> 0,
		8	=> 3,
		32	=> 3
	);

	$cons_index_values = array(
		73 => 9,
		90 => 10,
		160 => 11,
		47 => 15,
		121 => 16,
		49 => 17,
		84 => 20,
		136 => 24,
		63 => 31,
		56 => 40,
		105 => 41,
		78 => 46, 
		82 => 50,
		111 => 61,
		127 => 71,
		117 => 85,
		138 => 92,
		134 => 102,
		148 => 116,
		154 => 130
	);

	$cons_index = 0;
	$resp_pattern = 0;
	$last_response = 0;


	# These are some fields that need their values flipped before scoring. Instead of going from 0 => 3, they go from 3 => 0
	$new_source = array();
	$flipped_values = array(3,16,17,41,49,66,80,81,98,103,105,131,142,145);
	foreach ($required_fields as $i => $req_name) {
		$index = $i-3;
		$val = $src[$req_name]-1;

       # $val = $src[$req_name];
        
        if (in_array(($index), $flipped_values)) {
                $new_source[$req_name] = ($val != 4) ? 3-$val : null;
#               $this->module->emDebug("Flipped values: i=$i, field_name = $req_name, val = $val, new source = $new_source[$req_name]");
        } else {
                $new_source[$req_name] = ($val != 4) ? $val : null;
        }

        if (array_key_exists(($index), $F_index_values))
		{
			if ($val == $F_index_values[($index)])
			{
				$F_index++;
			}
		}

		if (array_key_exists(($index), $cons_index_values))
		{
				$cons_pair_val = $new_source[$required_fields[$cons_index_values[$index]]];
				if ($cons_pair_val != $new_source[$req_name])
				{
					$cons_index++;
				}
		}

		foreach ($source_indexes as $scale => $qs)
		{
			if (in_array(($index), $qs))
			{
				$currVal = $validity_arr[$scale];
				if (!(isset($val) and (strlen($val) <= 0)))
				{
					$validity_arr[$scale] = $currVal+1;
				}
			}
		}

		if ($index == 1)
		{
			$last_response = $val;
		}
		else 
		{
			if ($val != $last_response)
			{
				$resp_pattern++;
			}
			$last_response = $val;
		}
	}


	### IMPLEMENT RAW SCORING ###
	# This is the array source
	# Define lists of questions that correspond to each subscale
	# Note: Most questions are scored as N=0, S=1, O=2, A=3 but some questions have reversed scoring

	$mult_factor = array(
	        'hyp' => 1,
	        'agg' => 1,
	        'cp'  => 1,
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
	                         0 => 32, 1 => 34, 2 => 35, 3 => 37, 4 => 39, 5 => 40, 6 => 42, 7 => 44, 8 => 45,
	                         9 => 47, 10 => 49, 11 => 51, 12 => 52, 13 => 54, 14 => 56, 15 => 57, 16 => 59,
	                         17 => 61, 18 => 63, 19 => 64, 20 => 66, 21 => 68, 22 => 69, 23 => 71, 24 => 73,
	                         25 => 74, 26 => 76, 27 => 78, 28 => 80, 29 => 81, 30 => 83
	                ),
	        'agg' => array(
	                         0 => 36, 1 => 38, 2 => 39, 3 => 41, 4 => 43, 5 => 45, 6 => 47, 7 => 48, 8 => 50,
	                         9 => 52, 10 => 54, 11 => 56, 12 => 58, 13 => 59, 14 => 61, 15 => 63, 16 => 65, 
	                         17 => 67, 18 => 69, 19 => 70, 20 => 72, 21 => 74, 22 => 76, 23 => 78, 24 => 79,
	                         25 => 81, 26 => 83, 27 => 85, 28 => 87, 29 => 89, 30 => 90, 31 => 92, 32 => 94, 33 => 96
	                ),
	        'cp' => array(
	                         0 => 36, 1 => 38, 2 => 40, 3 => 43, 4 => 45, 5 => 47, 6 => 49, 7 => 51, 8 => 53,
	                         9 => 56, 10 => 58, 11 => 60, 12 => 62, 13 => 64, 14 => 66, 15 => 69, 16 => 71, 
	                         17 => 73, 18 => 75, 19 => 77, 20 => 80, 21 => 82, 22 => 84, 23 => 86, 24 => 88,
	                         25 => 90, 26 => 93, 27 => 95
	                ),
	        'anx' => array(
	                         0 => 31, 1 => 32, 2 => 34, 3 => 35, 4 => 37, 5 => 28, 6 => 39, 7 => 41, 8 => 42,
	                         9 => 44, 10 => 45, 11 => 47, 12 => 48, 13 => 50, 14 => 51, 15 => 53, 16 => 54,
	                         17 => 55, 18 => 57, 19 => 58, 20 => 60, 21 => 61, 22 => 63, 23 => 64, 24 => 66,
	                         25 => 67, 26 => 69, 27 => 70, 28 => 71, 29 => 73, 30 => 74, 31 => 76, 32 => 77,
	                         33 => 79, 34 => 80, 35 => 82, 36 => 83, 37 => 85, 38 => 86, 39 => 87, 40 => 89,
	                         41 => 90, 42 => 92
	                ),
	        'dep' => array(
	                         0 => 36, 1 => 37, 2 => 39, 3 => 41, 4 => 42, 5 => 44, 6 => 45, 7 => 47, 8 => 48, 
	                         9 => 50, 10 => 51, 11 => 53, 12 => 54, 13 => 56, 14 => 58, 15 => 59, 16 => 61, 
	                         17 => 62, 18 => 64, 19 => 65, 20 => 67, 21 => 68, 22 => 70, 23 => 71, 24 => 73, 
	                         25 => 75, 26 => 76, 27 => 78, 28 => 79, 29 => 81, 30 => 82, 31 => 84, 32 => 85, 
	                         33 => 87, 34 => 89, 35 => 90, 36 => 92, 37 => 93, 38 => 95, 39 => 96, 40 => 98,
	                         41 => 99, 42 => 101
	                ),
	        'som' => array(
	                         0 => 37, 1 => 39, 2 => 42, 3 => 44, 4 => 47, 5 => 49, 6 => 51, 7 => 54, 8 => 56,
	                         9 => 59, 10 => 61, 11 => 64, 12 => 66, 13 => 69, 14 => 71, 15 => 74, 16 => 76,
	                         17 => 79, 18 => 81, 19 => 83, 20 => 86, 21 => 88, 22 => 91, 23 => 93, 24 => 96,
	                         25 => 98, 26 => 101, 17 => 103, 28 => 106, 29 => 108, 30 => 111, 31 => 113, 32 => 115,
	                         33 => 118, 34 => 120, 35 => 120, 36 => 120  
	                ),
	        'aty' => array(
	                         0 => 38, 1 => 40, 2 => 42, 3 => 44, 4 => 46, 5 => 48, 6 => 50, 7 => 52, 8 => 54,
	                         9 => 56, 10 => 58, 11 => 60, 12 => 62, 13 => 64, 14 => 66, 15 => 68, 16 => 70,
	                         17 => 72, 18 => 74, 19 => 76, 20 => 78, 21 => 80, 22 => 82, 23 => 84, 24 => 86, 
	                         25 => 88, 26 => 90, 27 => 92, 28 => 94, 29 => 96, 30 => 98, 31 => 100, 32 => 102,
	                         33 => 104, 34 => 106, 35 => 108, 36 => 110, 37 => 112, 38 => 114, 39 => 116
	                ),
	        'wdl' => array(
	                         0 => 34, 1 => 35, 2 => 37, 3 => 39, 4 => 41, 5 => 43, 6 => 45, 7 => 47, 8 => 49,
	                         9 => 51, 10 => 53, 11 => 54, 12 => 56, 13 => 58, 14 => 60, 15 => 62, 16 => 64, 
	                         17 => 66, 18 => 68, 19 => 70, 20 => 71, 21 => 73, 22 => 75, 23 => 77, 24 => 79,
	                         25 => 81, 26 => 83, 27 => 85, 28 => 87, 29 => 89, 30 => 90, 31 => 92, 32 => 94,
	                         33 => 96, 34 => 98, 35 => 100, 36 => 102
	                ),
	        'apr' => array(
	                         0 => 29, 1 => 31, 2 => 34, 3 => 36, 4 => 39, 5 => 41, 6 => 44, 7 => 46, 8 => 49,
	                         9 => 51, 10 => 54, 11 => 56, 12 => 59, 13 => 61, 14 => 64, 15 => 67, 16 => 69,
	                         17 => 72, 18 => 74
	                ),
	        'ada' => array(
	                         0 => 22, 1 => 24, 2 => 26, 3 => 28, 4 => 30, 5 => 32, 6 => 35, 7 => 37, 8 => 39,
	                         9 => 41, 10 => 43, 11 => 45, 12 => 47, 13 => 49, 14 => 51, 15 => 54, 16 => 56, 
	                         17 => 58, 18 => 60, 19 => 62, 20 => 64, 21 => 66, 22 => 68, 23 => 71, 24 => 73
	                ),
	        'ssk' => array(
	                        0 => 24, 1 => 26, 2 => 28, 3 => 30, 4 => 32, 5 => 34, 6 => 36, 7 => 38, 8 => 40,
	                        9 => 42, 10 => 44, 11 => 46, 12 => 48, 13 => 50, 14 => 52, 15 => 54, 16 => 56,
	                        17 => 58, 18 => 60, 19 => 62, 20 => 64, 21 => 66, 22 => 68, 23 => 70, 24 => 72
	                ),
	        'ldr' => array(
	                         0 => 27, 1 => 29, 2 => 31, 3 => 33, 4 => 35, 5 => 38, 6 => 40, 7 => 42, 8 => 44,
	                         9 => 47, 10 => 49, 11 => 51, 12 => 53, 13 => 55, 14 => 58, 15 => 60, 16 => 62, 
	                         17 => 64, 18 => 67, 19 => 69, 20 => 71, 21 => 73, 22 => 75, 23 => 78, 24 => 80
	                ),
	        'adl' => array(
	                         0 => 20, 1 => 22, 2 => 24, 3 => 26, 4 => 29, 5 => 31, 6 => 33, 7 => 35, 8 => 38,
	                        9 => 40, 10 => 42, 11 => 44, 12 => 47, 13 => 49, 14 => 51, 15 => 54, 16 => 56,
	                        17 => 58, 18 => 60, 19 => 63, 20 => 65, 21 => 67, 22 => 69, 23 => 72, 24 => 74
	                ),
	        'fc' => array(
	                        0 => 18, 1 => 19, 2 => 21, 3 => 22, 4 => 24, 5 => 25, 6 => 27, 7 => 28, 8 => 30,
	                        9 => 31, 10 => 33, 11 => 34, 12 => 35, 13 => 37, 14 => 38, 15 => 40, 16 => 41,
	                        17 => 43, 18 => 44, 19 => 46, 20 => 47, 21 => 49, 22 => 50, 23 => 52, 24 => 53,
	                        25 => 55, 26 => 56, 27 => 58, 28 => 59, 29 => 60, 30 => 62, 31 => 63, 32 => 65,
	                        33 => 66, 34 => 68, 35 => 69, 36 => 71
	                )
	        );


	$tvalue_perc = array(
	        'hyp' => array(
	                        0 => 1, 1 => 1, 2 => 3, 3 => 6, 4 => 11, 5 => 17, 6 => 24, 7 => 31, 8 => 38,
	                        9 => 45, 10 => 51, 11 => 58, 12 => 63, 13 => 69, 14 => 74, 15 => 78, 16 => 82,
	                        17 => 85, 18 => 88, 19 => 90, 20 => 92, 21 => 94, 22 => 95, 23 => 97, 24 => 97, 
	                        25 => 98, 26 => 99, 27 => 99, 28 => 99, 29 => 99, 30 => 99
	                ),
	        'agg' => array(
	                        0 => 1, 1 => 6, 2 => 12, 3 => 19, 4 => 27, 5 => 36, 6 =>44, 7 => 52, 8 => 59,
	                        9 => 65, 10 => 71, 11 => 76, 12 => 80, 13 => 84, 14 => 87, 15 => 89, 16 => 91, 
	                        17 => 93, 18 => 95, 19 => 96, 20 => 97, 21 => 97, 22 => 98, 23 => 98, 24 => 99,
	                        25 => 99, 26 => 99, 27 => 99, 28 => 99, 29 => 99, 30 => 99, 31 => 99, 32 => 99,
	                        33 => 99
	                ),
	        'cp' => array(
	                        0 => 1, 1 => 6, 2 => 14, 3 => 25, 4 => 35, 5 => 46, 6 => 55, 7 => 63, 8 => 70,
	                        9 => 76, 10 => 81, 11 => 85, 12 => 88, 13 => 91, 14 => 93, 15 => 94, 16 => 96,
	                        17 => 97, 18 => 98, 19 => 98, 20 => 99, 21 => 99, 22 => 99, 23 => 99, 24 => 99,
	                        25 => 99, 26 => 99, 27 => 99
	                ),
	        'anx' => array(
	                        0 => 1, 1 => 1, 2 => 3, 3 => 4, 4 => 7, 5 => 10, 6 => 14, 7 => 19, 8 => 24, 
	                        9 => 29, 10 => 35, 11 => 41, 12 => 47, 13 => 53, 14 => 58, 15 => 63, 16 => 68, 
	                        17 => 73, 18 => 77, 19 => 80, 20 => 84, 21 => 86, 22 => 89, 23 => 91, 24 => 93, 
	                        25 => 94, 26 => 95, 27 => 96, 28 => 97, 29 => 98, 30 => 98, 31 => 99, 32 => 99,
	                        33 => 99, 34 => 99, 35 => 99, 36 => 99, 37 => 99, 38 => 99, 39 => 99, 40 => 99, 
	                        41 => 99, 42 => 99
	                ),
	        'dep' => array(
	                        0 => 1, 1 => 3, 2 => 8, 3 => 15, 4 => 23, 5 => 31, 6 => 39, 7 => 46, 8 => 53,
	                        9 => 59, 10 => 64, 11 => 69, 12 => 73, 13 => 77, 14 => 80, 15 => 83, 16 => 86, 
	                        17 => 88, 18 => 90, 19 => 91, 20 => 93, 21 => 94, 22 => 95, 23 => 96, 24 => 97,
	                        25 => 97, 26 => 98, 27 => 98, 28 => 99, 29 => 99, 30 => 99, 31 => 99, 32 => 99,
	                        33 => 99, 34 => 99, 35 => 99, 36 => 99, 37 => 99, 38 => 99, 39 => 99, 40 => 99,
	                        41 => 99, 42 => 99
	                ),
	        'som' => array(
	                        0 => 4, 1 => 11, 2 => 21, 3 => 32, 4 => 43, 5 => 53, 6 => 62, 7 => 70, 8 => 77,
	                        9 => 82, 10 => 87, 11 => 90, 12 => 93, 13 => 95, 14 => 96, 15 => 97, 16 => 98, 
	                        17 => 99, 18 => 99, 19 => 99, 20 => 99, 21 => 99, 22 => 99, 23 => 99, 24 => 99,
	                        25 => 99, 26 => 99, 27 => 99, 28 => 99, 29 => 99, 30 => 99, 31 => 99, 32 => 99,
	                        33 => 99, 34 => 99, 35 => 99, 36 => 99     
	                ),
	        'aty' => array(
	                        0 => 5, 1 => 14, 2 => 24, 3 => 35, 4 => 44, 5 => 53, 6 => 60, 7 => 67, 8 => 72,
	                        9 => 77, 10 => 81, 11 => 85, 12 => 87, 13 => 90, 14 => 92, 15 => 94, 16 => 95,
	                        17 => 96, 18 => 97, 19 => 98, 20 => 98, 21 => 99, 22 => 99, 23 => 99, 24 => 99,
	                        25 => 99, 26 => 99, 27 => 99, 28 => 99, 29 => 99, 30 => 99, 31 => 99, 32 => 99,
	                        33 => 99, 34 => 99, 35 => 99, 36 => 99, 37 => 99, 38 => 99, 39 => 99
	                ),
	        'wdl' => array(
	                        0 => 1, 1 => 3, 2 => 7, 3 => 13, 4 => 20, 5 => 28, 6 => 36, 7 => 44, 8 => 51,
	                        9 => 58, 10 => 65, 11 => 71, 12 => 76, 13 => 80, 14 => 84, 15 => 87, 16 => 90,
	                        17 => 92, 18 => 94, 19 => 95, 20 => 97, 21 => 98, 22 => 98, 23 => 99, 24 => 99,
	                        25 => 99, 26 => 99, 27 => 99, 28 => 99, 29 => 99, 30 => 99, 31 => 99, 32 => 99,
	                        33 => 99, 34 => 99, 35 => 99, 36 => 99
	                ),
	        'apr' => array(
	                        0 => 1, 1 => 3, 2 => 6, 3 => 11, 4 => 16, 5 => 22, 6 => 29, 7 => 36, 8 => 44,
	                        9 => 52, 10 => 60, 11 => 69, 12 => 78, 13 => 86, 14 => 93, 15 => 98, 16 => 99,
	                        17 => 99, 18 => 99
	                ),
	        'ada' => array(
	                        0 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 2, 5 => 3, 6 => 6, 7 => 10, 8 => 14,
	                        9 => 20, 10 => 26, 11 => 33, 12 => 40, 13 => 48, 14 => 56, 15 => 63, 16 => 70,
	                        17 => 76, 18 => 82, 19 => 87, 20 => 91, 21 => 95, 22 => 97, 23 => 99, 24 => 99
	                ),
	        'ssk' => array(
	                        0 => 1, 1 => 1, 2 => 1, 3 => 2, 4 => 4, 5 => 6, 6 => 9, 7 => 13, 8 => 18, 
	                        9 => 23, 10 => 30, 11 => 36, 12 => 44, 13 => 51, 14 => 59, 15 => 66, 16 => 73,
	                        17 => 79, 18 => 84, 19 => 89, 20 => 92, 21 => 95, 22 => 97, 23 => 98, 24 => 99
	                ),
	        'ldr' => array(
	                         0 => 1, 1 => 1, 2 => 22, 3 => 4, 4 => 7, 5 => 11, 6 => 17, 7 => 23, 8 => 30, 
	                         9 => 38, 10 => 46, 11 => 54, 12 => 62, 13 => 70, 14 => 77, 15 => 83, 16 => 88,
	                         17 => 92, 18 => 95, 19 => 97, 20 => 98, 21 => 99, 22 => 99, 23 => 99, 24 => 99
	                ),
	        'adl' => array(
	                        0 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 2, 5 => 3, 6 => 5, 7 => 8, 8 => 12,
	                        9 => 17, 10 => 22, 11 => 29, 12 => 36, 13 => 45, 14 => 53, 15 => 62, 16 => 70,
	                        17 => 78, 18 => 84, 19 => 90, 20 => 94, 21 => 97, 22 => 99, 23 => 99, 24 => 99
	                ),
	        'fc' => array(
	                        0 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1, 6 => 2, 7 => 2, 8 => 3,
	                        9 => 4, 10 => 5, 11 => 7, 12 => 9, 13 => 11, 14 => 13, 15 => 16, 16 => 20,
	                        17 => 23, 18 => 28, 19 => 32, 20 => 37, 21 => 42, 22 => 48, 23 => 54, 24 => 59,
	                        25 => 65, 26 => 71, 27 => 76, 28 => 81, 29 => 85, 30 => 89, 31 => 92, 32 => 95,
	                        33 => 97, 34 => 98, 35 => 99, 36 => 99
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
	        $q_answered = 160;

	        foreach ($source_names as $i => $result_name) {
                $raw_score_val = $raw_scores[$result_name];
                $null_vals = $null_counter[$result_name];

                $q_answered -= $null_vals;

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
	                        33 => array('min' => 104, 'max' => 105), 34 => array('min' => 106, 'max' => 108), 35 => array('min' => 109, 'max' => 110),
	                        36 => array('min' => 111, 'max' => 113), 37 => array('min' => 114, 'max' => 116), 38 => array('min' => 117, 'max' => 118),
	                        39 => array('min' => 119, 'max' => 121), 40 => array('min' => 122, 'max' => 124), 41 => array('min' => 125, 'max' => 126),
	                        42 => array('min' => 127, 'max' => 129), 43 => array('min' => 130, 'max' => 132), 44 => array('min' => 133, 'max' => 135),
	                        45 => array('min' => 136, 'max' => 137), 46 => array('min' => 138, 'max' => 140), 47 => array('min' => 141, 'max' => 143),
	                        48 => array('min' => 144, 'max' => 145), 49 => array('min' => 146, 'max' => 148), 50 => array('min' => 149, 'max' => 151),
	                        51 => array('min' => 152, 'max' => 154), 52 => array('min' => 155, 'max' => 156), 53 => array('min' => 157, 'max' => 159),
	                        54 => array('min' => 160, 'max' => 162), 55 => array('min' => 163, 'max' => 164), 56 => array('min' => 165, 'max' => 167),
	                        57 => array('min' => 168, 'max' => 170), 58 => array('min' => 171, 'max' => 173), 59 => array('min' => 174, 'max' => 175),
	                        60 => array('min' => 176, 'max' => 178), 61 => array('min' => 179, 'max' => 181), 62 => array('min' => 182, 'max' => 183),
	                        63 => array('min' => 184, 'max' => 186), 64 => array('min' => 187, 'max' => 189), 65 => array('min' => 190, 'max' => 191)
	                        ),
	                'intprob' => array(
	                        31 => array('min' => 104, 'max' => 105), 32 => array('min' => 106, 'max' => 107), 33 => array('min' => 108, 'max' => 110),
	                        34 => array('min' => 111, 'max' => 112), 35 => array('min' => 113, 'max' => 114), 36 => array('min' => 115, 'max' => 117),
	                        37 => array('min' => 116, 'max' => 118), 38 => array('min' => 119, 'max' => 121), 39 => array('min' => 122, 'max' => 124),
	                        40 => array('min' => 125, 'max' => 127), 41 => array('min' => 128, 'max' => 129), 42 => array('min' => 130, 'max' => 132),
	                        43 => array('min' => 132, 'max' => 134), 44 => array('min' => 135, 'max' => 136), 45 => array('min' => 137, 'max' => 139),
	                        46 => array('min' => 140, 'max' => 141), 47 => array('min' => 142, 'max' => 143), 48 => array('min' => 144, 'max' => 146),
	                        49 => array('min' => 147, 'max' => 148), 50 => array('min' => 149, 'max' => 151), 51 => array('min' => 152, 'max' => 153),
	                        52 => array('min' => 154, 'max' => 156), 53 => array('min' => 157, 'max' => 158), 54 => array('min' => 159, 'max' => 160),
	                        55 => array('min' => 161, 'max' => 163), 56 => array('min' => 164, 'max' => 165), 57 => array('min' => 166, 'max' => 168),
	                        58 => array('min' => 169, 'max' => 170), 59 => array('min' => 171, 'max' => 172), 60 => array('min' => 173, 'max' => 175),
	                        61 => array('min' => 176, 'max' => 177), 62 => array('min' => 178, 'max' => 180), 63 => array('min' => 181, 'max' => 182),
	                        64 => array('min' => 183, 'max' => 185), 65 => array('min' => 186, 'max' => 187)
	                        ),
	                'behsymp' => array(
	                        30 => array('min' => 205, 'max' => 209), 31 => array('min' => 210, 'max' => 213), 32 => array('min' => 214, 'max' => 218),
	                        33 => array('min' => 219, 'max' => 223), 34 => array('min' => 224, 'max' => 227), 35 => array('min' => 228, 'max' => 232),
	                        36 => array('min' => 233, 'max' => 237), 37 => array('min' => 238, 'max' => 241), 38 => array('min' => 242, 'max' => 246),
	                        39 => array('min' => 247, 'max' => 251), 40 => array('min' => 252, 'max' => 255), 41 => array('min' => 256, 'max' => 260),
	                        42 => array('min' => 261, 'max' => 265), 43 => array('min' => 266, 'max' => 269), 44 => array('min' => 270, 'max' => 274),
	                        45 => array('min' => 275, 'max' => 279), 46 => array('min' => 280, 'max' => 283), 47 => array('min' => 284, 'max' => 288),
	                        48 => array('min' => 289, 'max' => 293), 49 => array('min' => 294, 'max' => 297), 50 => array('min' => 298, 'max' => 302),
	                        51 => array('min' => 303, 'max' => 307), 52 => array('min' => 308, 'max' => 312), 53 => array('min' => 313, 'max' => 316),
	                        54 => array('min' => 317, 'max' => 320), 55 => array('min' => 321, 'max' => 325), 56 => array('min' => 326, 'max' => 330),
	                        57 => array('min' => 331, 'max' => 334), 58 => array('min' => 335, 'max' => 339), 59 => array('min' => 340, 'max' => 344),
	                        60 => array('min' => 345, 'max' => 348), 61 => array('min' => 349, 'max' => 353), 62 => array('min' => 354, 'max' => 358),
	                        63 => array('min' => 359, 'max' => 362), 64 => array('min' => 363, 'max' => 367), 65 => array('min' => 368, 'max' => 372)
	                        ),
	                'adaskill' => array(
	                        17 => array('min' => 111, 'max' => 111), 18 => array('min' => 112, 'max' => 116), 19 => array('min' => 117, 'max' => 120),
	                        20 => array('min' => 121, 'max' => 124), 21 => array('min' => 125, 'max' => 128), 22 => array('min' => 129, 'max' => 133),
	                        23 => array('min' => 134, 'max' => 137), 24 => array('min' => 138, 'max' => 141), 25 => array('min' => 142, 'max' => 145),
	                        26 => array('min' => 146, 'max' => 150), 27 => array('min' => 151, 'max' => 154), 28 => array('min' => 155, 'max' => 158),
	                        29 => array('min' => 159, 'max' => 162), 30 => array('min' => 163, 'max' => 167), 31 => array('min' => 168, 'max' => 171),
	                        32 => array('min' => 172, 'max' => 175), 33 => array('min' => 176, 'max' => 179), 34 => array('min' => 180, 'max' => 184),
	                        35 => array('min' => 185, 'max' => 188), 36 => array('min' => 189, 'max' => 192), 37 => array('min' => 193, 'max' => 196),
	                        38 => array('min' => 197, 'max' => 201), 39 => array('min' => 202, 'max' => 205), 40 => array('min' => 206, 'max' => 209),
	                        41 => array('min' => 210, 'max' => 213), 42 => array('min' => 214, 'max' => 218), 43 => array('min' => 219, 'max' => 222),
	                        44 => array('min' => 223, 'max' => 226), 45 => array('min' => 227, 'max' => 230), 46 => array('min' => 231, 'max' => 235),
	                        47 => array('min' => 236, 'max' => 239), 48 => array('min' => 240, 'max' => 243), 49 => array('min' => 244, 'max' => 247),
	                        50 => array('min' => 248, 'max' => 252), 51 => array('min' => 253, 'max' => 256), 52 => array('min' => 257, 'max' => 260),
	                        53 => array('min' => 261, 'max' => 264), 54 => array('min' => 265, 'max' => 269), 55 => array('min' => 270, 'max' => 273),
	                        56 => array('min' => 274, 'max' => 277), 57 => array('min' => 278, 'max' => 281), 58 => array('min' => 282, 'max' => 286),
	                        59 => array('min' => 287, 'max' => 290), 60 => array('min' => 291, 'max' => 294), 61 => array('min' => 295, 'max' => 298), 
	                        62 => array('min' => 299, 'max' => 303), 63 => array('min' => 304, 'max' => 307), 64 => array('min' => 308, 'max' => 311),
	                        65 => array('min' => 312, 'max' => 315)
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

	# Completeness + Validity index 

	$completeness_interpret = array();
	foreach ($validity_key_source as $scale => $field_name)
	{
		$null_count = $null_counter[$scale];
		if ($null_count > 2)
		{
			$completeness_interpret[$field_name] = 0;
		}
		else
		{
			$completeness_interpret[$field_name] = 1;
		}
	}

	# F_index result
	$F_interpret = 1;
	if ($F_index == 6)
	{
		$F_interpret = 2;
	}
	else if ($F_index >= 7 and $F_index <= 20)
	{
		$F_interpret = 3;
	}
	$F_index_result = array($F_index, $F_interpret);

	#Consistency Index result
	$cons_interpret = 1;
	if ($cons_index >= 14 and $cons_index <= 17)
	{
		$cons_interpret = 2;
	}
	else if ($cons_index >= 18)
	{
		$cons_interpret = 3;
	}
	$cons_index_result = array($cons_index, $cons_interpret);


	# Response Pattern result
	$resp_pattern_interpret = 0;
	if ($resp_pattern >= 65 and $resp_pattern <= 125)
	{
		$resp_pattern_interpret = 1;
	}
	else if ($resp_pattern >= 126 and $resp_pattern <= 160)
	{
		$resp_pattern_interpret = 2;
	}
	$resp_pattern_result = array($resp_pattern, $resp_pattern_interpret);

	#Overall Statistics result

	# AllVal
	$allval = 0;
	if ($q_answered == 160)
	{
		$allval = 1;
	}



	#90Val
	$val_90 = 0;
	$limit = 0.9*160;

	if ($q_answered >= $limit)
	{
		$val_90 = 1;
	}


	$validity_count = 0;
	foreach ($completeness_interpret as $field => $valid)
	{
		if ($valid == 1)
		{
			$validity_count++;
		}
	}
	$stat_result = array($allval, $val_90, $validity_count);

	#$this->module->emDebug("composite raw: " . implode(',',$composite_raw));
	#$this->module->emDebug("composite mean: " . implode(',', $composite_mean));
	#$this->module->emDebug("composite tval: " . implode(',', $composite_tval));

	### DEFINE RESULTS ###
	# REQUIRED: The algorithm_results variable MUST BE USED as it is relied upon from the parent script.
	$totals =  array_merge($raw_score_totals, $null_counter, $tvalue_scores, $tvalue_percent, $completeness_interpret, $F_index_result, $cons_index_result, $resp_pattern_result, $stat_result, $composite_raw, $composite_mean, $composite_tval);
	$algorithm_results = array_combine($default_result_fields, $totals);


	#$this->module->emDebug("composite raw: " . implode(',',$composite_raw));
	#$this->module->emDebug("composite mean: " . implode(',', $composite_mean));
	#$this->module->emDebug("composite tval: " . implode(',', $composite_tval));

	### DEFINE RESULTS ###
	# REQUIRED: The algorithm_results variable MUST BE USED as it is relied upon from the parent script.
	#$totals =  array_merge($raw_score_totals, $null_counter, $tvalue_scores, $tvalue_percent, $composite_raw, $composite_mean, $composite_tval);
	#$algorithm_results = array_combine($default_result_fields, $totals);

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

}

return true;

?>

