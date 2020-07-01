<?php
/**

	BASC2 - Parent Rating Scales - Adolescent

	A REDCap AutoScoring Algorithm File

	Developed by Kim Wijaya and Alex Basile for ELSPAP June 2016
	Uses General Combined Sex Scales Ages 12-14 and 15-18
	See BASC2 Manual for Scale Details 	
	
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

	$this->module->emDebug("Using PRS-A");
	# REQUIRED: Summarize this algorithm
	$algorithm_summary = "BASC2, Parent Rating Scales - Adolescent - Don't use this I don't think this working.  It does not require age but it uses age";
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
			'prsa_extprob_tval', 'prsa_intprob_tval', 'prsa_behsymp_tval', 'prsa_adaskill_tval',
			'prsa_hyp_sig', 'prsa_agg_sig', 'prsa_cp_sig',
			'prsa_anx_sig', 'prsa_dep_sig', 'prsa_som_sig', 'prsa_aty_sig',
			'prsa_wdl_sig', 'prsa_apr_sig', 'prsa_extprob_sig',
			'prsa_intprob_sig','prsa_behsymp_sig', 'prsa_adaskill_sig',
			'prsa_ssk_sig','prsa_ldr_sig','prsa_fc_sig','prsa_ada_sig',
			'prsa_adl_sig'
	);

	# REQUIRED: Define an array of fields that must be present for this algorithm to run
	$required_fields = array();
	foreach (range(1,150) as $i) {
		array_push($required_fields, "basc_prs_a_q$i");
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
		$val = $src[$req_name];
		if (isset($val) and strlen($val) > 0)
		{
			$val--;
		}

		if (in_array(($index), $flipped_values)) {
			#$new_source[$req_name] = (isset($val) and (strlen($val) > 0)) ? 3-$val : null;
			$new_source[$req_name] = (isset($val) and strlen($val) > 0) ? 3-$val : null;
	#		$this->module->emDebug("Flipped values: i=$i, field_name = $req_name, val = $val, new source = $new_source[$req_name]");
		} else {
			#$new_source[$req_name] = (isset($val)) ? $val : null;
			$new_source[$req_name] = (isset($val) and strlen($val) > 0) ? $val : null;
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
				$cons_pair_val = $new_source[$required_fields[$cons_index_values[$index]-1]];
			$thisval = $new_source[$req_name];
			$this->module->emDebug($req_name . ":" . $required_fields[$cons_index_values[$index]-1] . " || " . $new_source[$req_name] . ":" . $cons_pair_val);
			$cons_index += abs($cons_pair_val - $thisval);
			$this->module->emDebug("adding: " . $cons_index);

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


	## RIGHT NOW ITS 12-14

	$tvalue = array();
	$tvalue_perc = array();
	$composite_comb_tvalues = array();

	if ($src['basc_prs_a_age'] >= 12 and $src['basc_prs_a_age'] <=14)
	{
	# These are the Tvalue and Tvalue percentage tables
		$tvalue = array(
			'hyp' => array(
					 0 => 36, 1 => 38, 2 => 41, 3 => 44, 4 => 47, 5 => 50,
					 6 => 52, 7 => 55, 8 => 58, 9 => 61, 10 => 64,
					 11 => 66, 12 => 69, 13 => 72, 14 => 75, 15 => 78,
					 16 => 80, 17 => 83, 18 => 86, 19 => 89, 20 => 92,
					 21 => 94, 22 => 97, 23 => 100, 24 => 103
				),
			'agg' => array(
					 0 => 38, 1 => 40, 2 => 42, 3 => 45, 4 => 47, 5 => 49,
					 6 => 51, 7 => 54, 8 => 56, 9 => 58, 10 => 61,
					 11 => 63, 12 => 65, 13 => 68, 14 => 70, 15 => 72,
					 16 => 74, 17 => 77, 18 => 79, 19 => 81, 20 => 84,
					 21 => 86, 22 => 88, 23 => 90, 24 => 93, 25 => 95,
					 26 => 97, 27 => 100, 28 => 102, 29 => 104, 30 => 107
				),
			'cp' => array(
					0 => 39, 1 => 41, 2 => 44, 3 => 46, 4 => 48, 5 => 51,
					6 => 53, 7 => 55, 8 => 57, 9 => 60, 10 => 62,
					11 => 64, 12 => 66, 13 => 69, 14 => 71, 15 => 73,
					16 => 76, 17 => 78, 18 => 80, 19 => 82, 20 => 85,
					21 => 87, 22 => 89, 23 => 92, 24 => 94, 25 => 96,
					26 => 98, 27 => 101, 28 => 103, 29 => 105, 30 => 108,
					31 => 110, 32 => 112, 33 => 114, 34 => 117, 35 => 119,
					36 => 120, 37 => 120, 38 => 120, 39 => 120, 40 => 120,
					41 => 120, 42 => 120
				),
			'anx' => array(
					0 => 30, 1 => 32, 2 => 34, 3 => 36, 4 => 38, 5 => 41,
					6 => 43, 7 => 45, 8 => 47, 9 => 49, 10 => 51,
					11 => 53, 12 => 55, 13 => 57, 14 => 60, 15 => 62,
					16 => 64, 17 => 66, 18 => 68, 19 => 70, 20 => 72,
					21 => 74, 22 => 76, 23 => 79, 24 => 81, 25 => 83,
					26 => 85, 27 => 87, 28 => 89, 29 => 91, 30 => 93,
					31 => 95, 32 => 98, 33 => 100
				),
			'dep' => array(
					0 => 37, 1 => 39, 2 => 41, 3 => 43, 4 => 45, 5 => 48,
					6 => 50, 7 => 52, 8 => 54, 9 => 56, 10 => 58,
					11 => 60, 12 => 63, 13 => 65, 14 => 67, 15 => 69,
					16 => 71, 17 => 73, 18 => 75, 19 => 77, 20 => 80,
					21 => 82, 22 => 84, 23 => 86, 24 => 88, 25 => 90,
					26 => 92, 27 => 95, 28 => 97, 29 => 99, 30 => 101,
					31 => 103, 32 => 105, 33 => 107, 34 => 109, 35 => 112,
					36 => 114, 37 => 116, 38 => 118, 39 => 120
				),
			'som' => array(
					0 => 38, 1 => 41, 2 => 44, 3 => 46, 4 => 49, 5 => 52,
					6 => 55, 7 => 57, 8 => 60, 9 => 63, 10 => 66,
					11 => 69, 12 => 71, 13 => 74, 14 => 77, 15 => 80,
					16 => 82, 17 => 85, 18 => 88, 19 => 91, 20 => 93,
					21 => 96, 22 => 99, 23 => 102, 24 => 104, 25 => 107,
					26 => 110, 27 => 113, 28 => 116, 29 => 118, 30 => 120,
					31 => 120, 32 => 120, 33 => 120
				),
			'aty' => array(
					0 => 41, 1 => 44, 2 => 47, 3 => 51, 4 => 54, 5 => 57,
					6 => 60, 7 => 64, 8 => 67, 9 => 70, 10 => 73,
					11 => 76, 12 => 80, 13 => 83, 14 => 86, 15 => 89,
					16 => 93, 17 => 96, 18 => 99, 19 => 102, 20 => 106,
					21 => 109, 22 => 112, 23 => 115, 24 => 118, 25 => 120,
					26 => 120, 27 => 120, 28 => 120, 29 => 120, 30 => 120
				),
			'wdl' => array(
					0 => 36, 1 => 38, 2 => 41, 3 => 44, 4 => 46, 5 => 49,
					6 => 52, 7 => 54, 8 => 57, 9 => 60, 10 => 62,
					11 => 65, 12 => 68, 13 => 70, 14 => 73, 15 => 76,
					16 => 78, 17 => 81, 18 => 84, 19 => 86, 20 => 89,
					21 => 92, 22 => 94, 23 => 97, 24 => 100
				),
			'apr' => array(
					0 => 35, 1 => 38, 2 => 40, 3 => 43, 4 => 45, 5 => 48,
					6 => 50, 7 => 53, 8 => 55, 9 => 58, 10 => 60,
					11 => 63, 12 => 66, 13 => 68, 14 => 71, 15 => 73,
					16 => 76, 17 => 78, 18 => 81
				),
			'ada' => array(
					0 => 15, 1 => 18, 2 => 20, 3 => 22, 4 => 24, 5 => 26,
					6 => 28, 7 => 31, 8 => 33, 9 => 35, 10 => 37,
					11 => 39, 12 => 42, 13 => 44, 14 => 46, 15 => 48,
					16 => 50, 17 => 53, 18 => 55, 19 => 57, 20 => 59,
					21 => 61, 22 => 63, 23 => 66, 24 => 68
				),
			'ssk' => array(
					0 => 21, 1 => 23, 2 => 25, 3 => 27, 4 => 29, 5 => 31,
					6 => 33, 7 => 35, 8 => 37, 9 => 39, 10 => 41,
					11 => 43, 12 => 45, 13 => 47, 14 => 49, 15 => 51,
					16 => 54, 17 => 56, 18 => 58, 19 => 60, 20 => 62,
					21 => 64, 22 => 66, 23 => 68, 24 => 70
				),
			'ldr' => array(
					0 => 22, 1 => 24, 2 => 25, 3 => 27, 4 => 29, 5 => 31,
					6 => 32, 7 => 34, 8 => 36, 9 => 38, 10 => 39,
					11 => 41, 12 => 43, 13 => 44, 14 => 46, 15 => 48,
					16 => 50, 17 => 51, 18 => 53, 19 => 55, 20 => 57,
					21 => 58, 22 => 60, 23 => 62, 24 => 64, 25 => 65,
					26 => 67, 27 => 69, 28 => 71, 29 => 72, 30 => 74
				),
			'adl' => array(
					0 => 15, 1 => 18, 2 => 20, 3 => 22, 4 => 24, 5 => 27, 
					6 => 29, 7 => 31, 8 => 34, 9 => 36, 10 => 38,
					11 => 40, 12 => 43, 13 => 45, 14 => 47, 15 => 50,
					16 => 52, 17 => 54, 18 => 56, 19 => 59, 20 => 61,
					21 => 63, 22 => 65, 23 => 68, 24 => 70
				),
			'fc' => array(
					0 => 10, 1 => 10, 2 => 11, 3 => 12, 4 => 14, 5 => 16,
					6 => 17, 7 => 19, 8 => 20, 9 => 22, 10 => 24,
					11 => 25, 12 => 27, 13 => 29, 14 => 30, 15 => 32,
					16 => 33, 17 => 35, 18 => 37, 19 => 38, 20 => 40,
					21 => 41, 22 => 43, 23 => 45, 24 => 46, 25 => 48,
					26 => 50, 27 => 51, 28 => 53, 29 => 54, 30 => 56,
					31 => 58, 32 => 59, 33 => 61, 34 => 63, 35 => 64,
					36 => 66
				)
			);


		$tvalue_perc = array(
			'hyp' => array(
					 0 => 1, 1 => 7, 2 => 18, 3 => 32, 4 => 45, 5 => 57,
					 6 => 67, 7 => 75, 8 => 82, 9 => 86, 10 => 90,
					 11 => 93, 12 => 95, 13 => 96, 14 => 97, 15 => 98,
					 16 => 99, 17 => 99, 18 => 99, 19 => 99, 20 => 99,
					 21 => 99, 22 => 99, 23 => 99, 24 => 99
				),
			'agg' => array(
					0 => 2, 1 => 10, 2 => 23, 3 => 35, 4 => 47, 5 => 57,
					6 => 66, 7 => 72, 8 => 78, 9 => 83, 10 => 86,
					11 => 89, 12 => 92, 13 => 93, 14 => 95, 15 => 96,
					16 => 97, 17 => 98, 18 => 98, 19 => 99, 20 => 99, 
					21 => 99, 22 => 99, 23 => 99, 24 => 99, 25 => 99,
					26 => 99, 27 => 99, 28 => 99, 29 => 99, 30 => 99
				),
			'cp' => array(
					 0 => 4, 1 => 16, 2 => 29, 3 => 43, 4 => 54, 5 => 63,
					 6 => 71, 7 => 77, 8 => 82, 9 => 86, 10 => 89,
					 11 => 91, 12 => 93, 13 => 94, 14 => 96, 15 => 97, 
					 16 => 97, 17 => 98, 18 => 98, 19 => 99, 20 => 99, 
					 21 => 99, 22 => 99, 23 => 99, 24 => 99, 25 => 99, 
					 26 => 99, 27 => 99, 28 => 99, 29 => 99, 30 => 99, 
					 31 => 99, 32 => 99, 33 => 99, 34 => 99, 35 => 99, 
					 36 => 99, 37 => 99, 38 => 99, 39 => 99, 40 => 99, 
					 41 => 99, 42 => 99
				),
			'anx' => array(
					0 => 1, 1 => 2, 2 => 4, 3 => 7, 4 => 11, 5 => 17,
					6 => 24, 7 => 32, 8 => 40, 9 => 49, 10 => 58,
					11 => 66, 12 => 73, 13 => 79, 14 => 84, 15 => 88,
					16 => 91, 17 => 93, 18 => 95, 19 => 97, 20 => 98,
					21 => 98, 22 => 99, 23 => 99, 24 => 99, 25 => 99,
					26 => 9, 27 => 99, 28 => 99, 29 => 99, 30 => 99,
					31 => 99, 32 => 99, 33 => 99

				),
			'dep' => array(
					0 => 3, 1 => 9, 2 => 18, 3 => 29, 4 => 39, 5 => 49,
					6 => 58, 7 => 65, 8 => 72, 9 => 77, 10 => 82,
					11 => 86, 12 => 89, 13 => 91, 14 => 93, 15 => 95,
					16 => 96, 17 => 97, 18 => 98, 19 => 98, 20 => 99, 
					21 => 99, 22 => 99, 23 => 99, 24 => 99, 25 => 99,
					26 => 99, 27 => 99, 28 => 99, 29 => 99, 30 => 99,
					31 => 99, 32 => 99, 33 => 99, 34 => 99, 35 => 99,
					36 => 99, 37 => 99, 38 => 99, 39 => 99
				),
			'som' => array(
					0 => 4, 1 => 16, 2 => 30, 3 => 45, 4 => 57, 5 => 67,
					6 => 75, 7 => 81, 8 => 86, 9 => 89, 10 => 92,
					11 => 94, 12 => 96, 13 => 97, 14 => 98, 15 => 98, 
					16 => 99, 17 => 99, 18 => 99, 19 => 99, 20 => 99,
					21 => 99, 22 => 99, 23 => 99, 24 => 99, 25 => 99, 
					26 => 99, 27 => 99, 28 => 99, 29 => 99, 30 => 99,
					31 => 99, 32 => 99, 33 => 99
				),
			'aty' => array(
					0 => 11, 1 => 34, 2 => 52, 3 => 65, 4 => 75, 5 => 81,
					6 => 86, 7 => 90, 8 => 93, 9 => 95, 10 => 96,
					11 => 97, 12 => 98, 13 => 99, 14 => 99, 15 => 99, 
					16 => 99, 17 => 99, 18 => 99, 19 => 99, 20 => 99,
					21 => 99, 22 => 99, 23 => 99, 24 => 99, 25 => 99,
					26 => 99, 27 => 99, 28 => 99, 29 => 99, 30 => 99
				),
			'wdl' => array(
					0 => 4, 1 => 10, 2 => 18, 3 => 29, 4 => 41, 5 => 53,
					6 => 63, 7 => 72, 8 => 79, 9 => 85, 10 => 89,
					11 => 92, 12 => 94, 13 => 96, 14 => 97, 15 => 98,
					16 => 99, 17 => 99, 18 => 99, 19 => 99, 20 => 99,
					21 => 99, 22 => 99, 23 => 99, 24 => 99
				),
			'apr' => array(
					0 => 5, 1 => 11, 2 => 19, 3 => 28, 4 => 37, 5 => 46,
					6 => 54, 7 => 62, 8 => 70, 9 => 77, 10 => 83,
					11 => 88, 12 => 92, 13 => 95, 14 => 98, 15 => 99, 
					16 => 99, 17 => 99, 18 => 99
				),
			'ada' => array(
					0 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1,
					6 => 2, 7 => 4, 8 => 6, 9 => 9, 10 => 12,
					11 => 16, 12 => 21, 13 => 26, 14 => 33, 15 => 40,
					16 => 48, 17 => 56, 18 => 64, 19 => 72, 20 => 80,
					21 => 87, 22 => 93, 23 => 97, 24 => 99
				),
			'ssk' => array(
					0 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 2,
					6 => 4, 7 => 7, 8 => 11, 9 => 16, 10 => 21,
					11 => 27, 12 => 34, 13 => 41, 14 => 48, 15 => 55,
					16 => 62, 17 => 69, 18 => 75, 19 => 81, 20 => 86,
					21 => 91, 22 => 94, 23 => 97, 24 => 99
				),
			'ldr' => array(
					0 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 2,
					6 => 4, 7 => 6, 8 => 8, 9 => 11, 10 => 15,
					11 => 20, 12 => 25, 13 => 30, 14 => 36, 15 => 43,
					16 => 49, 17 => 55, 18 => 62, 19 => 68, 20 => 74,
					21 => 79, 22 => 83, 23 => 87, 24 => 91, 25 => 94,
					26 => 96, 27 => 97, 28 => 98, 29 => 99, 30 => 99
				),
			'adl' => array(
					0 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 2,
					6 => 3, 7 => 4, 8 => 6, 9 => 9, 10 => 13,
					11 => 17, 12 => 23, 13 => 29, 14 => 37, 15 => 45,
					16 => 54, 17 => 63, 18 => 71, 19 => 79, 20 => 86,
					21 => 92, 22 => 96, 23 => 98, 24 => 99
				),
			'fc' => array(
					0 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1,
					6 => 1, 7 => 1, 8 => 1, 9 => 1, 10 => 1,
					11 => 1, 12 => 2, 13 => 2, 14 => 4, 15 => 5, 
					16 => 7, 17 => 9, 18 => 11, 19 => 14, 20 => 17,
					21 => 21, 22 => 25, 23 => 29, 24 => 34, 25 => 39,
					26 => 45, 27 => 50, 28 => 56, 29 => 63, 30 => 69,
					31 => 75, 32 => 80, 33 => 86, 34 => 91, 35 => 94,
					36 => 97
				)
			);

$composite_comb_tvalues = array(
			'extprob' => array( 
				36 => array('min' => 113, 'max' => 113), 37 => array('min' => 114, 'max' => 115), 38 => array('min' => 116, 'max' => 118),
				39 => array('min' => 119, 'max' => 121), 40 => array('min' => 122, 'max' => 124), 41 => array('min' => 125, 'max' => 126),
				42 => array('min' => 127, 'max' => 129), 43 => array('min' => 130, 'max' => 132), 44 => array('min' => 133, 'max' => 134),
				45 => array('min' => 135, 'max' => 137), 46 => array('min' => 138, 'max' => 140), 47 => array('min' => 141, 'max' => 143),
				48 => array('min' => 144, 'max' => 145), 49 => array('min' => 146, 'max' => 148), 50 => array('min' => 149, 'max' => 151), 
				51 => array('min' => 152, 'max' => 154), 52 => array('min' => 155, 'max' => 156), 53 => array('min' => 157, 'max' => 159), 
				54 => array('min' => 160, 'max' => 162), 55 => array('min' => 163, 'max' => 165), 56 => array('min' => 166, 'max' => 167),
				57 => array('min' => 168, 'max' => 170), 58 => array('min' => 171, 'max' => 173), 59 => array('min' => 174, 'max' => 175),
				60 => array('min' => 176, 'max' => 178), 61 => array('min' => 179, 'max' => 181), 62 => array('min' => 182, 'max' => 184),
				63 => array('min' => 185, 'max' => 186), 64 => array('min' => 187, 'max' => 189), 65 => array('min' => 190, 'max' => 192),
				66 => array('min' => 193, 'max' => 195), 67 => array('min' => 196, 'max' => 197), 68 => array('min' => 198, 'max' => 200),
				69 => array('min' => 201, 'max' => 203), 70 => array('min' => 204, 'max' => 206), 71 => array('min' => 207, 'max' => 208),
				72 => array('min' => 209, 'max' => 211), 73 => array('min' => 212, 'max' => 214), 74 => array('min' => 215, 'max' => 216),
				75 => array('min' => 217, 'max' => 219), 76 => array('min' => 220, 'max' => 222), 77 => array('min' => 223, 'max' => 225),
				78 => array('min' => 226, 'max' => 227), 79 => array('min' => 228, 'max' => 230), 80 => array('min' => 231, 'max' => 233),
				81 => array('min' => 234, 'max' => 236), 82 => array('min' => 237, 'max' => 238), 83 => array('min' => 239, 'max' => 241),
				84 => array('min' => 242, 'max' => 244), 85 => array('min' => 245, 'max' => 246), 86 => array('min' => 247, 'max' => 249),
				87 => array('min' => 250, 'max' => 252), 88 => array('min' => 253, 'max' => 255), 89 => array('min' => 256, 'max' => 257),
				90 => array('min' => 258, 'max' => 260), 91 => array('min' => 261, 'max' => 263), 92 => array('min' => 264, 'max' => 266),
				93 => array('min' => 267, 'max' => 268), 94 => array('min' => 269, 'max' => 271), 95 => array('min' => 272, 'max' => 274),
				96 => array('min' => 275, 'max' => 277), 97 => array('min' => 278, 'max' => 279), 98 => array('min' => 280, 'max' => 282),
				99 => array('min' => 283, 'max' => 285), 100 => array('min' => 286, 'max' => 287), 101 => array('min' => 288, 'max' => 290),
				102 => array('min' => 291, 'max' => 293), 103 => array('min' => 294, 'max' => 296), 104 => array('min' => 297, 'max' => 298),
				105 => array('min' => 299, 'max' => 301), 106 => array('min' => 302, 'max' => 304), 107 => array('min' => 305, 'max' => 307),
				108 => array('min' => 308, 'max' => 309), 109 => array('min' => 310, 'max' => 312), 110 => array('min' => 313, 'max' => 315),
				111 => array('min' => 316, 'max' => 318), 112 => array('min' => 319, 'max' => 320), 113 => array('min' => 321, 'max' => 323), 
				114 => array('min' => 324, 'max' => 326), 115 => array('min' => 327, 'max' => 328), 116 => array('min' => 329, 'max' => 330)
				),
			'intprob' => array(
				32 => array('min' => 105, 'max' => 107), 33 => array('min' => 108, 'max' => 109), 34 => array('min' => 110, 'max' => 112),
				35 => array('min' => 113, 'max' => 114), 36 => array('min' => 115, 'max' => 117), 37 => array('min' => 118, 'max' => 119),
				38 => array('min' => 120, 'max' => 121), 39 => array('min' => 122, 'max' => 124), 40 => array('min' => 125, 'max' => 126),
				41 => array('min' => 127, 'max' => 129), 42 => array('min' => 130, 'max' => 131), 43 => array('min' => 132, 'max' => 134),
				44 => array('min' => 135, 'max' => 136), 45 => array('min' => 137, 'max' => 139), 46 => array('min' => 140, 'max' => 141),
				47 => array('min' => 142, 'max' => 143), 48 => array('min' => 144, 'max' => 146), 49 => array('min' => 147, 'max' => 148),
				50 => array('min' => 149, 'max' => 151), 51 => array('min' => 152, 'max' => 153), 52 => array('min' => 154, 'max' => 156),
				53 => array('min' => 157, 'max' => 158), 54 => array('min' => 159, 'max' => 160), 55 => array('min' => 161, 'max' => 163),
				56 => array('min' => 164, 'max' => 165), 57 => array('min' => 166, 'max' => 168), 58 => array('min' => 169, 'max' => 170),
				59 => array('min' => 171, 'max' => 173), 60 => array('min' => 174, 'max' => 175), 61 => array('min' => 176, 'max' => 178),
				62 => array('min' => 179, 'max' => 180), 63 => array('min' => 181, 'max' => 182), 64 => array('min' => 183, 'max' => 185),
				65 => array('min' => 186, 'max' => 187), 66 => array('min' => 188, 'max' => 190), 67 => array('min' => 191, 'max' => 192),
				68 => array('min' => 193, 'max' => 195), 69 => array('min' => 196, 'max' => 197), 70 => array('min' => 198, 'max' => 199),
				71 => array('min' => 200, 'max' => 202), 72 => array('min' => 203, 'max' => 204), 73 => array('min' => 205, 'max' => 207),
				74 => array('min' => 208, 'max' => 209), 75 => array('min' => 210, 'max' => 212), 76 => array('min' => 213, 'max' => 214),
				77 => array('min' => 215, 'max' => 217), 78 => array('min' => 218, 'max' => 219), 79 => array('min' => 220, 'max' => 221),
				80 => array('min' => 222, 'max' => 224), 81 => array('min' => 225, 'max' => 226), 82 => array('min' => 227, 'max' => 229),
				83 => array('min' => 230, 'max' => 231), 84 => array('min' => 232, 'max' => 234), 85 => array('min' => 235, 'max' => 236),
				86 => array('min' => 237, 'max' => 238), 87 => array('min' => 239, 'max' => 241), 88 => array('min' => 242, 'max' => 243),
				89 => array('min' => 244, 'max' => 246), 90 => array('min' => 247, 'max' => 248), 91 => array('min' => 249, 'max' => 251),
				92 => array('min' => 252, 'max' => 253), 93 => array('min' => 254, 'max' => 256), 94 => array('min' => 257, 'max' => 258),
				95 => array('min' => 259, 'max' => 260), 96 => array('min' => 261, 'max' => 263), 97 => array('min' => 264, 'max' => 265),
				98 => array('min' => 266, 'max' => 268), 99 => array('min' => 269, 'max' => 270), 100 => array('min' => 271, 'max' => 273),
				101 => array('min' => 274, 'max' => 275), 102 => array('min' => 276, 'max' => 278), 103 => array('min' => 279, 'max' => 280),
				104 => array('min' => 281, 'max' => 282), 105 => array('min' => 283, 'max' => 285), 106 => array('min' => 286, 'max' => 287),
				107 => array('min' => 288, 'max' => 290), 108 => array('min' => 291, 'max' => 292), 109 => array('min' => 293, 'max' => 295),
				110 => array('min' => 296, 'max' => 297), 111 => array('min' => 298, 'max' => 299), 112 => array('min' => 300, 'max' => 302),
				113 => array('min' => 303, 'max' => 304), 114 => array('min' => 305, 'max' => 307), 115 => array('min' => 308, 'max' => 309), 
				116 => array('min' => 310, 'max' => 312), 117 => array('min' => 313, 'max' => 314), 118 => array('min' => 315, 'max' => 317),
				119 => array('min' => 318, 'max' => 319), 120 => array('min' => 320, 'max' => 340)
				),
			'behsymp' => array(
				33 => array('min' => 223, 'max' => 223), 34 => array('min' => 224, 'max' => 227), 35 => array('min' => 228, 'max' => 232),
				36 => array('min' => 233, 'max' => 237), 37 => array('min' => 238, 'max' => 241), 38 => array('min' => 242, 'max' => 246),
				39 => array('min' => 247, 'max' => 251), 40 => array('min' => 252, 'max' => 256), 41 => array('min' => 257, 'max' => 260),
				42 => array('min' => 261, 'max' => 265), 43 => array('min' => 266, 'max' => 269), 44 => array('min' => 270, 'max' => 274),
				45 => array('min' => 275, 'max' => 279), 46 => array('min' => 280, 'max' => 283), 47 => array('min' => 284, 'max' => 288),
				48 => array('min' => 289, 'max' => 293), 49 => array('min' => 294, 'max' => 297), 50 => array('min' => 298, 'max' => 302),
				51 => array('min' => 303, 'max' => 306), 52 => array('min' => 307, 'max' => 311), 53 => array('min' => 312, 'max' => 316),
				54 => array('min' => 317, 'max' => 320), 55 => array('min' => 321, 'max' => 325), 56 => array('min' => 326, 'max' => 330),
				57 => array('min' => 331, 'max' => 334), 58 => array('min' => 335, 'max' => 339), 59 => array('min' => 340, 'max' => 343),
				60 => array('min' => 344, 'max' => 348), 61 => array('min' => 349, 'max' => 353), 62 => array('min' => 354, 'max' => 357),
				63 => array('min' => 358, 'max' => 362), 64 => array('min' => 363, 'max' => 367), 65 => array('min' => 368, 'max' => 372),
				66 => array('min' => 373, 'max' => 376), 67 => array('min' => 377, 'max' => 381), 68 => array('min' => 382, 'max' => 386),
				69 => array('min' => 387, 'max' => 390), 70 => array('min' => 391, 'max' => 395), 71 => array('min' => 396, 'max' => 400),
				72 => array('min' => 401, 'max' => 404), 73 => array('min' => 405, 'max' => 409), 74 => array('min' => 410, 'max' => 413),
				75 => array('min' => 414, 'max' => 418), 76 => array('min' => 419, 'max' => 423), 77 => array('min' => 424, 'max' => 427),
				78 => array('min' => 428, 'max' => 432), 79 => array('min' => 433, 'max' => 437), 80 => array('min' => 438, 'max' => 441),
				81 => array('min' => 442, 'max' => 446), 82 => array('min' => 447, 'max' => 451), 83 => array('min' => 452, 'max' => 455),
				84 => array('min' => 456, 'max' => 460), 85 => array('min' => 461, 'max' => 465), 86 => array('min' => 466, 'max' => 469),
				87 => array('min' => 470, 'max' => 474), 88 => array('min' => 475, 'max' => 479), 89 => array('min' => 480, 'max' => 483),
				90 => array('min' => 484, 'max' => 488), 91 => array('min' => 489, 'max' => 493), 92 => array('min' => 494, 'max' => 497),
				93 => array('min' => 498, 'max' => 502), 94 => array('min' => 503, 'max' => 507), 95 => array('min' => 508, 'max' => 511),
				96 => array('min' => 512, 'max' => 516), 97 => array('min' => 517, 'max' => 521), 98 => array('min' => 522, 'max' => 525),
				99 => array('min' => 526, 'max' => 530), 100 => array('min' => 531, 'max' => 534), 101 => array('min' => 535, 'max' => 539),
				102 => array('min' => 540, 'max' => 544), 103 => array('min' => 545, 'max' => 548), 104 => array('min' => 549, 'max' => 553),
				105 => array('min' => 554, 'max' => 558), 106 => array('min' => 559, 'max' => 562), 107 => array('min' => 563, 'max' => 567),
				108 => array('min' => 568, 'max' => 572), 109 => array('min' => 573, 'max' => 576), 110 => array('min' => 577, 'max' => 581),
				111 => array('min' => 582, 'max' => 586), 112 => array('min' => 287, 'max' => 590), 113 => array('min' => 591, 'max' => 595),
				114 => array('min' => 596, 'max' => 600), 115 => array('min' => 601, 'max' => 604), 116 => array('min' => 605, 'max' => 609),
				117 => array('min' => 610, 'max' => 614), 118 => array('min' => 615, 'max' => 618), 119 => array('min' => 619, 'max' => 623),
				120 => array('min' => 624, 'max' => 631)
				),
			'adaskill' => array(
				11 => array('min' => 83, 'max' => 85), 12 => array('min' => 86, 'max' => 90), 13 => array('min' => 91, 'max' => 94),
				14 => array('min' => 95, 'max' => 98), 15 => array('min' => 99, 'max' => 102), 16 => array('min' => 103, 'max' => 107),
				17 => array('min' => 108, 'max' => 111), 18 => array('min' => 112, 'max' => 115), 19 => array('min' => 116, 'max' => 120),
				20 => array('min' => 121, 'max' => 124), 21 => array('min' => 125, 'max' => 128), 22 => array('min' => 129, 'max' => 132),
				23 => array('min' => 133, 'max' => 137), 24 => array('min' => 138, 'max' => 141), 25 => array('min' => 142, 'max' => 145),
				26 => array('min' => 146, 'max' => 149), 27 => array('min' => 150, 'max' => 154), 28 => array('min' => 155, 'max' => 158),
				29 => array('min' => 159, 'max' => 162), 30 => array('min' => 163, 'max' => 166), 31 => array('min' => 167, 'max' => 171),
				32 => array('min' => 172, 'max' => 175), 33 => array('min' => 176, 'max' => 179), 34 => array('min' => 180, 'max' => 183),
				35 => array('min' => 184, 'max' => 188), 36 => array('min' => 189, 'max' => 192), 37 => array('min' => 193, 'max' => 196),
				38 => array('min' => 197, 'max' => 200), 39 => array('min' => 201, 'max' => 205), 40 => array('min' => 206, 'max' => 209), 
				41 => array('min' => 210, 'max' => 213), 42 => array('min' => 214, 'max' => 218), 43 => array('min' => 219, 'max' => 222),
				44 => array('min' => 223, 'max' => 226), 45 => array('min' => 227, 'max' => 230), 46 => array('min' => 231, 'max' => 235),
				47 => array('min' => 236, 'max' => 239), 48 => array('min' => 240, 'max' => 243), 49 => array('min' => 244, 'max' => 247),
				50 => array('min' => 248, 'max' => 252), 51 => array('min' => 253, 'max' => 256), 52 => array('min' => 257, 'max' => 260),
				53 => array('min' => 261, 'max' => 264), 54 => array('min' => 265, 'max' => 269), 55 => array('min' => 270, 'max' => 273),
				56 => array('min' => 274, 'max' => 277), 57 => array('min' => 278, 'max' => 281), 58 => array('min' => 282, 'max' => 286),
				59 => array('min' => 287, 'max' => 290), 60 => array('min' => 291, 'max' => 294), 61 => array('min' => 295, 'max' => 299),
				62 => array('min' => 300, 'max' => 303), 63 => array('min' => 304, 'max' => 307), 64 => array('min' => 308, 'max' => 311),
				65 => array('min' => 312, 'max' => 316), 66 => array('min' => 317, 'max' => 320), 67 => array('min' => 321, 'max' => 324),
				68 => array('min' => 325, 'max' => 328), 69 => array('min' => 329, 'max' => 333), 70 => array('min' => 334, 'max' => 337),
				71 => array('min' => 338, 'max' => 341), 72 => array('min' => 342, 'max' => 345), 73 => array('min' => 346, 'max' => 348)
				)
		);

	}
	else
	{

	## These are 15-18

		$tvalue = array(
			'hyp' => array(
					0 => 38, 1 => 41, 2 => 44, 3 => 47, 4 => 50, 5 => 54,
					6 => 57, 7 => 60, 8 => 63, 9 => 67, 10 => 70,
					11 => 73, 12 => 76, 13 => 80, 14 => 83, 15 => 86,
					16 => 89, 17 => 93, 18 => 96, 19 => 99, 20 => 102,
					21 => 105, 12 => 109, 13 => 112, 14 => 115
				),
			'agg' => array(
					0 => 38, 1 => 40, 2 => 43, 3 => 45, 4 => 48, 5 => 50,
					6 => 53, 7 => 55, 8 => 58, 9 => 61, 10 => 63,
					11 => 66, 12 => 68, 13 => 71, 14 => 73, 15 => 76,
					16 => 78, 17 => 81, 18 => 83, 19 => 86, 20 => 88,
					21 => 91, 22 => 93, 23 => 96, 24 => 98, 25 => 101,
					26 => 103, 27 => 106, 28 => 108, 29 => 111, 30 => 113
				),
			'cp' => array(
					0 => 40, 1 => 42, 2 => 44, 3 => 46, 4 => 48, 5 => 51,
					6 => 53, 7 => 55, 8 => 57, 9 => 59, 10 => 62,
					11 => 64, 12 => 66, 13 => 68, 14 => 71, 15 => 73,
					16 => 75, 17 => 77, 18 => 79, 19 => 82, 20 => 84,
					21 => 86, 22 => 88, 23 => 91, 24 => 93, 25 => 95,
					26 => 97, 27 => 99, 28 => 102, 29 => 104, 30 => 106,
					31 => 108, 32 => 111, 33 => 113, 34 => 115, 35 => 117,
					36 => 119, 37 => 120, 38 => 120, 39 => 120, 40 => 120,
					41 => 120, 42 => 120
				),
			'anx' => array(
					0 => 32, 1 => 34, 2 => 36, 3 => 38, 4 => 40, 5 => 42,
					6 => 44, 7 => 46, 8 => 48, 9 => 50, 10 => 52,
					11 => 54, 12 => 56, 13 => 58, 14 => 60, 15 => 62,
					16 => 54, 17 => 66, 18 => 68, 19 => 70, 20 => 72,
					21 => 74, 22 => 76, 23 => 78, 24 => 80, 25 => 82,
					26 => 84, 27 => 86, 28 => 89, 29 => 91, 30 => 93,
					31 => 95, 32 => 97, 33 => 99
				),
			'dep' => array(
					0 => 38, 1 => 41, 2 => 43, 3 => 45, 4 => 47, 5 => 49,
					6 => 52, 7 => 54, 8 => 56, 9 => 58, 10 => 60,
					11 => 63, 12 => 65, 13 => 67, 14 => 69, 15 => 71,
					16 => 74, 17 => 76, 18 => 78, 19 => 80, 20 => 83,
					21 => 85, 22 => 87, 23 => 89, 24 => 91, 25 => 94,
					26 => 96, 27 => 98, 28 => 100, 29 => 102, 30 => 105,
					31 => 107, 32 => 109, 33 => 111, 34 => 113, 35 => 116,
					36 => 118, 37 => 120, 38 => 120, 39 => 120
				),
			'som' => array(
					0 => 39, 1 => 41, 2 => 44, 3 => 47, 4 => 49, 5 => 52,
					6 => 55, 7 => 57, 8 => 60, 9 => 63, 10 => 65,
					11 => 68, 12 => 70, 13 => 73, 14 => 76, 15 => 78,
					16 => 81, 17 => 84, 18 => 86, 19 => 89, 20 => 92,
					21 => 94, 22 => 97, 23 => 100, 24 => 102, 25 => 105,
					26 => 107, 27 => 110, 28 => 113, 29 => 115, 30 => 118,
					31 => 120, 32 => 120, 33 => 120
				),
			'aty' => array(
					0 => 42, 1 => 45, 2 => 49, 3 => 52, 4 => 56, 5 => 59,
					6 => 63, 7 => 66, 8 => 70, 9 => 73, 10 => 76,
					11 => 80, 12 => 83, 13 => 87, 14 => 90, 15 => 94,
					16 => 97, 17 => 101, 18 => 104, 19 => 107, 20 => 111,
					21 => 114, 22 => 118, 23 => 120, 24 => 120, 25 => 120,
					26 => 120, 27 => 120, 28 => 120, 29 => 120, 30 => 120
				),
			'wdl' => array(
					0 => 36, 1 => 38, 2 => 41, 3 => 44, 4 => 47, 5 => 49,
					6 => 52, 7 => 55, 8 => 58, 9 => 61, 10 => 63,
					11 => 66, 12 => 69, 13 => 72, 14 => 75, 15 => 77,
					16 => 80, 17 => 83, 18 => 86, 19 => 89, 20 => 91,
					21 => 94, 22 => 97, 23 => 100, 24 => 102
				),
			'apr' => array(
					0 => 36, 1 => 39, 2 => 42, 3 => 45, 4 => 47, 5 => 50,
					6 => 53, 7 => 56, 8 => 58, 9 => 61, 10 => 64,
					11 => 67, 12 => 70, 13 => 72 ,14 => 75, 15 => 78,
					16 => 81, 17 => 83, 18 => 86
				),
			'ada' => array(
					0 => 12, 1 => 15, 2 => 17, 3 => 19, 4 => 22, 5 => 24,
					6 => 26, 7 => 29, 8 => 31, 9 => 33, 10 => 36,
					11 => 38, 12 => 40, 13 => 42, 14 => 45, 15 => 47,
					16 => 49, 17 => 52, 18 => 54, 19 => 56, 20 => 59,
					21 => 61, 22 => 63, 23 => 66, 24 => 68
				),
			'ssk' => array(
					0 => 18, 1 => 21, 2 => 23, 3 => 25, 4 => 27, 5 => 29,
					6 => 31, 7 => 33, 8 => 35, 9 => 37, 10 => 39,
					11 => 41, 12 => 44, 13 => 46, 14 => 48, 15 => 50,
					16 => 52, 17 => 54, 18 => 56, 19 => 58, 20 => 60,
					21 => 62, 22 => 64, 23 => 67, 24 => 69
				),
			'ldr' => array(
					0 => 20, 1 => 21, 2 => 23, 3 => 25, 4 => 27, 5 => 28,
					6 => 30, 7 => 32, 8 => 34, 9 => 35, 10 => 37,
					11 => 39, 12 => 40, 13 => 42, 14 => 44, 15 => 46,
					16 => 47, 17 => 49, 18 => 51, 19 => 53, 20 => 54,
					21 => 56, 22 => 58, 23 => 59, 24 => 61, 25 => 63,
					26 => 65, 27 => 66, 28 => 68, 29 => 70, 30 => 72
				),
			'adl' => array(
					0 => 10, 1 => 10, 2 => 12, 3 => 15, 4 => 17, 5 => 20,
					6 => 22, 7 => 25, 8 => 27, 9 => 30, 10 => 33,
					11 => 35, 12 => 38, 13 => 40, 14 => 43, 15 => 45,
					16 => 48, 17 => 50 , 18 => 53, 19 => 56, 20 => 58,
					21 => 61, 22 => 63, 23 => 66, 24 => 68
				),
			'fc' => array(
					0 => 10, 1 => 10, 2 => 10, 3 => 10, 4 => 10, 5 => 10, 
					6 => 11, 7 => 13, 8 => 15, 9 => 16, 10 => 18,
					11 => 20, 12 => 22, 13 => 23, 14 => 25, 15 => 27,
					16 => 29, 17 => 31, 18 => 32, 19 => 34, 20 => 36,
					21 => 38, 22 => 39, 23 => 41, 24 => 43, 25 => 45,
					26 => 46, 27 => 48, 28 => 50, 29 => 52, 30 => 54,
					31 => 55, 32 => 57, 33 => 59, 34 => 61, 35 => 62, 
					36 => 64
				)
			);


		$tvalue_perc = array(
			'hyp' => array(
					0 => 3, 1 => 15, 2 => 32, 3 => 48, 4 => 61, 5 => 72,
					6 => 80, 7 => 86, 8 => 90, 9 => 93, 10 => 95,
					11 => 97, 12 => 98, 13 => 99, 14 => 99, 15 => 99,
					16 => 99, 17 => 99, 18 => 99, 19 => 99, 20 => 99,
					21 => 99, 22 => 99, 23 => 99, 24 => 99
				),
			'agg' => array(
					0 => 4, 1 => 13, 2 => 26, 3 => 39, 4 => 51, 5 => 61,
					6 => 70, 7 => 77, 8 => 82, 9 => 86, 10 => 90, 
					11 => 92, 12 => 94, 13 => 76, 14 => 97, 15 => 98,
					16 => 98, 17 => 99, 18 => 99, 19 => 99, 20 => 99,
					21 => 99, 22 => 99, 23 => 99, 24 => 99, 25 => 99,
					26 => 99, 27 => 99, 28 => 99, 29 => 99, 30 => 99
				),
			'cp' => array(
					 0 => 7, 1 => 19, 2 => 32, 3 => 44, 4 => 55, 5 => 63,
					 6 => 70, 7 => 76, 8 => 81, 9 => 85, 10 => 88,
					 11 => 90, 12 => 92, 13 => 94, 14 => 95, 15 => 96,
					 16 => 97, 17 => 98, 18 => 98, 19 => 99, 20 => 99,
					 21 => 99, 22 => 99, 23 => 99, 24 => 99, 25 => 99,
					 26 => 99, 27 => 99, 28 => 99, 29 => 99, 30 => 99,
					 31 => 99, 32 => 99, 33 => 99, 34 => 99, 35 => 99,
					 36 => 99, 37 => 99, 38 => 99, 39 => 99, 40 => 99, 
					 41 => 99, 42 => 99
				),
			'anx' => array(
					0 => 1, 1 => 3, 2 => 5, 3 => 9, 4 => 15, 5 => 21,
					6 => 29, 7 => 37, 8 => 45, 9 => 54, 10 => 61,
					11 => 68, 12 => 75, 13 => 80, 14 => 85, 15 => 88,
					16 => 91, 17 => 93, 18 => 95, 19 => 97, 20 => 98,
					21 => 98, 22 => 99, 23 => 99, 24 => 99, 25 => 99,
					26 => 99, 27 => 99, 28 => 99, 29 => 99, 30 => 99,
					31 => 99, 32 => 99, 33 => 99

				),
			'dep' => array(
					0 => 4, 1 => 13, 2 => 25, 3 => 37, 4 => 48, 5 => 58,
					6 => 66, 7 => 73, 8 => 78, 9 => 83, 10 => 86,
					11 => 89, 12 => 91, 13 => 93, 14 => 95, 15 => 96,
					16 => 97, 17 => 97, 18 => 98, 19 => 99, 20 => 99,
					21 => 99, 22 => 99, 23 => 99, 24 => 99, 25 => 99,
					26 => 99, 27 => 99, 28 => 99, 29 => 99, 30 => 99, 
					31 => 99, 32 => 99, 33 => 99, 34 => 99, 35 => 99,
					36 => 99, 37 => 99, 38 => 99, 39 => 99
				),
			'som' => array(
					0 => 5, 1 => 17, 2 => 32, 3 => 45, 4 => 57, 5 => 67,
					6 => 75, 7 => 81, 8 => 86, 9 => 89, 10 => 92,
					11 => 94, 12 => 95, 13 => 97, 14 => 98, 15 => 98,
					16 => 99, 17 => 99, 18 => 99, 19 => 99, 20 => 99,
					21 => 99, 22 => 99, 23 => 99, 24 => 99, 25 => 99,
					26 => 99, 27 => 99, 28 => 99, 29 => 99, 30 => 99, 
					31 => 99, 32 => 99, 33 => 99
				),
			'aty' => array(
					0 => 17, 1 => 42, 2 => 59, 3 => 71, 4 => 79, 5 => 85,
					6 => 89, 7 => 92, 8 => 94, 9 => 96, 10 => 97,
					11 => 98, 12 => 99, 13 => 99, 14 => 99, 15 => 99, 
					16 => 99, 17 => 99, 18 => 99, 19 => 99, 20 => 99, 
					21 => 99, 22 => 99, 23 => 99, 24 => 99, 25 => 99,
					26 => 99, 27 => 99, 28 => 99, 29 => 99, 30 => 99
				),
			'wdl' => array(
					0 => 3, 1 => 9, 2 => 19, 3 => 31, 4 => 43, 5 => 55,
					6 => 65, 7 => 74, 8 => 80, 9 => 86, 10 => 90, 
					11 => 93, 12 => 95, 13 > 97, 14 => 98, 15 => 99, 
					16 => 99, 17 => 99, 18 => 99, 19 => 99, 20 => 99,
					21 => 99, 22 => 99, 23 => 99, 24 => 99
				),
			'apr' => array(
					0 => 6, 1 => 14, 2 => 24, 3 => 35, 4 => 45, 5 => 56,
					6 => 65, 7 => 73, 8 => 80, 9 => 85, 10 => 90,
					11 => 93, 12 => 96, 13 => 98, 14 => 99, 15 => 99,
					16 => 99, 17 => 99, 18 => 99
				),
			'ada' => array(
					0 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1,
					6 => 1, 7 => 2, 8 => 3, 9 => 6, 10 => 9,
					11 => 13, 12 => 18, 13 => 24, 14 => 30, 15 => 38,
					16 => 45, 17 => 53, 18 => 62, 19 => 70, 20 => 78,
					21 => 85, 22 => 91, 23 => 96, 24 => 99
				),
			'ssk' => array(
					0 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 2,
					6 => 3, 7 => 5, 8 => 8, 9 => 11, 10 => 16,
					11 => 21, 12 => 27, 13 => 33, 14 => 40, 15 => 48,
					16 => 66, 17 => 63, 18 => 70, 19 => 77, 20 => 83,
					21 => 89, 22 => 93, 23 => 96, 24 => 98
				),
			'ldr' => array(
					0 => 1, 1 => 1, 2 => 3, 3 => 1, 4 => 1, 5 => 2, 
					6 => 2, 7 => 4, 8 => 6, 9 => 8, 10 => 11,
					11 => 14, 12 => 18, 13 => 23, 14 => 28, 15 => 33,
					16 => 39, 17 => 45, 18 => 52, 19 => 58, 20 => 65,
					21 => 71, 22 => 76, 23 => 81, 24 => 86, 25 => 90,
					26 => 93, 27 => 96, 28 => 97, 29 => 99, 30 => 99
				),
			'adl' => array(
					0 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1,
					6 => 1, 7 => 2, 8 => 3, 9 => 4, 10 => 6,
					11 => 8, 12 => 12, 13 => 16, 14 => 22, 15 => 29,
					16 => 38, 17 => 47, 18 => 58, 19 => 68, 20 => 78,
					21 => 87, 22 => 93, 23 => 97, 24 => 99
				),
			'fc' => array(
					0 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1, 
					6 => 1, 7 => 1, 8 => 1, 9 => 1, 10 => 1, 
					11 => 1, 12 => 1, 13 => 1, 14 => 1, 15 => 2,
					16 => 3, 17 => 5, 18 => 6, 19 => 8, 20 => 10,
					21 => 13, 22 => 16, 23 => 20, 24 => 24, 25 => 28,
					26 => 33, 27 => 38, 28 => 44, 29 => 51, 30 => 57,
					31 => 64, 32 => 71, 33 => 79, 34 => 86, 35 => 92,
					36 => 97
				)
			);

$composite_comb_tvalues = array(
			'extprob' => array( 
				37 => array('min' => 116, 'max' => 116), 38 => array('min' => 117, 'max' => 119), 39 => array('min' => 120, 'max' => 121),
				40 => array('min' => 122, 'max' => 124), 41 => array('min' => 125, 'max' => 127), 42 => array('min' => 128, 'max' => 129), 
				43 => array('min' => 130, 'max' => 132), 44 => array('min' => 133, 'max' => 135), 45 => array('min' => 136, 'max' => 137),
				46 => array('min' => 138, 'max' => 140), 47 => array('min' => 141, 'max' => 143), 48 => array('min' => 144, 'max' => 145),
				49 => array('min' => 146, 'max' => 148), 50 => array('min' => 149, 'max' => 151), 51 => array('min' => 152, 'max' => 154),
				52 => array('min' => 155, 'max' => 156), 53 => array('min' => 157, 'max' => 159), 54 => array('min' => 160, 'max' => 162),
				55 => array('min' => 163, 'max' => 164), 56 => array('min' => 165, 'max' => 167), 57 => array('min' => 168, 'max' => 170), 
				58 => array('min' => 171, 'max' => 172), 59 => array('min' => 173, 'max' => 175), 60 => array('min' => 176, 'max' => 178),
				61 => array('min' => 179, 'max' => 180), 62 => array('min' => 181, 'max' => 183), 63 => array('min' => 184, 'max' => 186),
				64 => array('min' => 187, 'max' => 188), 65 => array('min' => 189, 'max' => 191), 66 => array('min' => 192, 'max' => 194), 
				67 => array('min' => 195, 'max' => 196), 68 => array('min' => 197, 'max' => 199), 69 => array('min' => 200, 'max' => 202),
				70 => array('min' => 203, 'max' => 205), 71 => array('min' => 206, 'max' => 207), 72 => array('min' => 208, 'max' => 210),
				73 => array('min' => 211, 'max' => 213), 74 => array('min' => 214, 'max' => 215), 75 => array('min' => 216, 'max' => 218),
				76 => array('min' => 219, 'max' => 221), 77 => array('min' => 222, 'max' => 223), 78 => array('min' => 224, 'max' => 226), 
				79 => array('min' => 227, 'max' => 229), 80 => array('min' => 230, 'max' => 231), 81 => array('min' => 232, 'max' => 234),
				82 => array('min' => 235, 'max' => 237), 83 => array('min' => 238, 'max' => 239), 84 => array('min' => 240, 'max' => 242),
				85 => array('min' => 243, 'max' => 245), 86 => array('min' => 246, 'max' => 248), 87 => array('min' => 249, 'max' => 250), 
				88 => array('min' => 251, 'max' => 253), 89 => array('min' => 254, 'max' => 256), 90 => array('min' => 257, 'max' => 258),
				91 => array('min' => 259, 'max' => 261), 92 => array('min' => 262, 'max' => 264), 93 => array('min' => 265, 'max' => 266),
				94 => array('min' => 267, 'max' => 269), 95 => array('min' => 270, 'max' => 272), 96 => array('min' => 273, 'max' => 274),
				97 => array('min' => 275, 'max' => 277), 98 => array('min' => 278, 'max' => 280), 99 => array('min' => 281, 'max' => 282),
				100 => array('min' => 283, 'max' => 285), 101 => array('min' => 286, 'max' => 288), 102 => array('min' => 289, 'max' => 290),
				103 => array('min' => 291, 'max' => 293), 104 => array('min' => 294, 'max' => 296), 105 => array('min' => 297, 'max' => 299),
				106 => array('min' => 300, 'max' => 301), 107 => array('min' => 302, 'max' => 304), 108 => array('min' => 305, 'max' => 307),
				109 => array('min' => 308, 'max' => 309), 110 => array('min' => 310, 'max' => 312), 111 => array('min' => 313, 'max' => 315),
				112 => array('min' => 316, 'max' => 317), 113 => array('min' => 318, 'max' => 320), 114 => array('min' => 321, 'max' => 323),
				115 => array('min' => 324, 'max' => 325), 116 => array('min' => 326, 'max' => 328), 117 => array('min' => 329, 'max' => 331),
				118 => array('min' => 332, 'max' => 333), 119 => array('min' => 334, 'max' => 336), 120 => array('min' => 337, 'max' => 348)
				),
			'intprob' => array(
				33 => array('min' => 109, 'max' => 109), 34 => array('min' => 110, 'max' => 111), 35 => array('min' => 112, 'max' => 114),
				36 => array('min' => 115, 'max' => 116), 37 => array('min' => 117, 'max' => 119), 38 => array('min' => 120, 'max' => 121),
				39 => array('min' => 122, 'max' => 123), 40 => array('min' => 124, 'max' => 126), 41 => array('min' => 127, 'max' => 128),
				42 => array('min' => 129, 'max' => 131), 43 => array('min' => 132, 'max' => 133), 44 => array('min' => 134, 'max' => 136),
				45 => array('min' => 137, 'max' => 138), 46 => array('min' => 139, 'max' => 141), 47 => array('min' => 142, 'max' => 143),
				48 => array('min' => 144, 'max' => 146), 49 => array('min' => 147, 'max' => 148), 50 => array('min' => 149, 'max' => 151),
				51 => array('min' => 152, 'max' => 153), 52 => array('min' => 154, 'max' => 156), 53 => array('min' => 157, 'max' => 158),
				54 => array('min' => 159, 'max' => 161), 55 => array('min' => 162, 'max' => 163), 56 => array('min' => 164, 'max' => 166),
				57 => array('min' => 167, 'max' => 168), 58 => array('min' => 169, 'max' => 171), 59 => array('min' => 172, 'max' => 173),
				60 => array('min' => 174, 'max' => 176), 61 => array('min' => 177, 'max' => 178), 62 => array('min' => 179, 'max' => 180),
				63 => array('min' => 181, 'max' => 183), 64 => array('min' => 184, 'max' => 185), 65 => array('min' => 186, 'max' => 188),
				66 => array('min' => 189, 'max' => 190), 67 => array('min' => 191, 'max' => 193), 68 => array('min' => 194, 'max' => 195),
				69 => array('min' => 196, 'max' => 198), 70 => array('min' => 199, 'max' => 200), 71 => array('min' => 201, 'max' => 203),
				72 => array('min' => 204, 'max' => 205), 73 => array('min' => 206, 'max' => 208), 74 => array('min' => 209, 'max' => 210),
				75 => array('min' => 211, 'max' => 213), 76 => array('min' => 214, 'max' => 215), 77 => array('min' => 216, 'max' => 218),
				78 => array('min' => 219, 'max' => 220), 79 => array('min' => 221, 'max' => 223), 80 => array('min' => 224, 'max' => 225),
				81 => array('min' => 226, 'max' => 228), 82 => array('min' => 229, 'max' => 230), 83 => array('min' => 231, 'max' => 233),
				84 => array('min' => 234, 'max' => 235), 85 => array('min' => 236, 'max' => 237), 86 => array('min' => 238, 'max' => 240),
				87 => array('min' => 241, 'max' => 242), 88 => array('min' => 243, 'max' => 245), 89 => array('min' => 246, 'max' => 247),
				90 => array('min' => 248, 'max' => 250), 91 => array('min' => 251, 'max' => 252), 92 => array('min' => 253, 'max' => 255),
				93 => array('min' => 256, 'max' => 257), 94 => array('min' => 258, 'max' => 260), 95 => array('min' => 261, 'max' => 262),
				96 => array('min' => 263, 'max' => 265), 97 => array('min' => 266, 'max' => 267), 98 => array('min' => 268, 'max' => 270),
				99 => array('min' => 271, 'max' => 272), 100 => array('min' => 273, 'max' => 275), 101 => array('min' => 276, 'max' => 277),
				102 => array('min' => 278, 'max' => 280), 103 => array('min' => 281, 'max' => 282), 104 => array('min' => 283, 'max' => 285),
				105 => array('min' => 286, 'max' => 287), 106 => array('min' => 288, 'max' => 290), 107 => array('min' => 291, 'max' => 292),
				108 => array('min' => 293, 'max' => 295), 109 => array('min' => 296, 'max' => 297), 110 => array('min' => 298, 'max' => 299),
				111 => array('min' => 300, 'max' => 302), 112 => array('min' => 303, 'max' => 304), 113 => array('min' => 305, 'max' => 307),
				114 => array('min' => 308, 'max' => 309), 115 => array('min' => 310, 'max' => 312), 116 => array('min' => 313, 'max' => 314),
				117 => array('min' => 315, 'max' => 317), 118 => array('min' => 318, 'max' => 319), 119 => array('min' => 320, 'max' => 322),
				120 => array('min' => 323, 'max' => 329)
				),
			'behsymp' => array(
				34 => array('min' => 228, 'max' => 229), 35 => array('min' => 230, 'max' => 234), 36 => array('min' => 235, 'max' => 238),
				37 => array('min' => 239, 'max' => 243), 38 => array('min' => 244, 'max' => 247), 39 => array('min' => 248, 'max' => 252),
				40 => array('min' => 253, 'max' => 256), 41 => array('min' => 257, 'max' => 261), 42 => array('min' => 262, 'max' => 265),
				43 => array('min' => 266, 'max' => 270), 44 => array('min' => 271, 'max' => 274), 45 => array('min' => 275, 'max' => 279),
				46 => array('min' => 280, 'max' => 284), 47 => array('min' => 285, 'max' => 288), 48 => array('min' => 289, 'max' => 293),
				49 => array('min' => 294, 'max' => 297), 50 => array('min' => 298, 'max' => 302), 51 => array('min' => 303, 'max' => 306),
				52 => array('min' => 307, 'max' => 311), 53 => array('min' => 312, 'max' => 315), 54 => array('min' => 316, 'max' => 320),
				55 => array('min' => 321, 'max' => 325), 56 => array('min' => 326, 'max' => 329), 57 => array('min' => 330, 'max' => 334),
				58 => array('min' => 335, 'max' => 338), 59 => array('min' => 339, 'max' => 343), 60 => array('min' => 344, 'max' => 347),
				61 => array('min' => 348, 'max' => 352), 62 => array('min' => 353, 'max' => 356), 63 => array('min' => 357, 'max' => 361),
				64 => array('min' => 362, 'max' => 365), 65 => array('min' => 366, 'max' => 370), 66 => array('min' => 371, 'max' => 375),
				67 => array('min' => 376, 'max' => 379), 68 => array('min' => 380, 'max' => 384), 69 => array('min' => 385, 'max' => 388),
				70 => array('min' => 389, 'max' => 393), 71 => array('min' => 394, 'max' => 397), 72 => array('min' => 398, 'max' => 402),
				73 => array('min' => 403, 'max' => 406), 74 => array('min' => 407, 'max' => 411), 75 => array('min' => 412, 'max' => 416),
				76 => array('min' => 417, 'max' => 420), 77 => array('min' => 421, 'max' => 425), 78 => array('min' => 426, 'max' => 429),
				79 => array('min' => 430, 'max' => 434), 80 => array('min' => 435, 'max' => 438), 81 => array('min' => 439, 'max' => 443),
				82 => array('min' => 444, 'max' => 447), 83 => array('min' => 448, 'max' => 452), 84 => array('min' => 453, 'max' => 457),
				85 => array('min' => 458, 'max' => 461), 86 => array('min' => 462, 'max' => 466), 87 => array('min' => 467, 'max' => 470),
				88 => array('min' => 471, 'max' => 475), 89 => array('min' => 476, 'max' => 479), 90 => array('min' => 480, 'max' => 484),
				91 => array('min' => 485, 'max' => 488), 92 => array('min' => 489, 'max' => 493), 93 => array('min' => 494, 'max' => 497),
				94 => array('min' => 498, 'max' => 502), 95 => array('min' => 503, 'max' => 507), 96 => array('min' => 508, 'max' => 511), 
				97 => array('min' => 512, 'max' => 516), 98 => array('min' => 517, 'max' => 520), 99 => array('min' => 521, 'max' => 525),
				100 => array('min' => 526, 'max' => 529), 101 => array('min' => 530, 'max' => 534), 102 => array('min' => 535, 'max' => 538),
				103 => array('min' => 539, 'max' => 543), 104 => array('min' => 544, 'max' => 548), 105 => array('min' => 549, 'max' => 552),
				106 => array('min' => 553, 'max' => 557), 107 => array('min' => 558, 'max' => 561), 108 => array('min' => 562, 'max' => 566),
				109 => array('min' => 567, 'max' => 570), 110 => array('min' => 571, 'max' => 575), 111 => array('min' => 576, 'max' => 579),
				112 => array('min' => 580, 'max' => 584), 113 => array('min' => 585, 'max' => 589), 114 => array('min' => 590, 'max' => 593),
				115 => array('min' => 594, 'max' => 598), 116 => array('min' => 599, 'max' => 602), 117 => array('min' => 603, 'max' => 607),
				118 => array('min' => 608, 'max' => 611), 119 => array('min' => 612, 'max' => 616), 120 => array('min' => 617, 'max' => 656)
				),
			'adaskill' => array(
				10 => array('min' => 70, 'max' => 83), 11 => array('min' => 84, 'max' => 88), 12 => array('min' => 89, 'max' => 92),
				13 => array('min' => 93, 'max' => 96), 14 => array('min' => 97, 'max' => 100), 15 => array('min' => 101, 'max' => 104),
				16 => array('min' => 105, 'max' => 109), 17 => array('min' => 110, 'max' => 113), 18 => array('min' => 114, 'max' => 117),
				19 => array('min' => 118, 'max' => 121), 20 => array('min' => 122, 'max' => 125), 21 => array('min' => 126, 'max' => 130), 
				22 => array('min' => 131 ,'max' => 134), 23 => array('min' => 135, 'max' => 138), 24 => array('min' => 139, 'max' => 142),
				25 => array('min' => 143, 'max' => 146), 26 => array('min' => 147, 'max' => 151), 27 => array('min' => 152, 'max' => 155),
				28 => array('min' => 156, 'max' => 159), 29 => array('min' => 160, 'max' => 163), 30 => array('min' => 164, 'max' => 167),
				31 => array('min' => 168, 'max' => 172), 32 => array('min' => 173, 'max' => 176), 33 => array('min' => 177, 'max' => 180), 
				34 => array('min' => 181, 'max' => 184), 35 => array('min' => 185, 'max' => 188), 36 => array('min' => 189, 'max' => 193),
				37 => array('min' => 194, 'max' => 197), 38 => array('min' => 198, 'max' => 201), 39 => array('min' => 202, 'max' => 205),
				40 => array('min' => 206, 'max' => 210), 41 => array('min' => 211, 'max' => 214), 42 => array('min' => 215, 'max' => 218),
				43 => array('min' => 219, 'max' => 222), 44 => array('min' => 223, 'max' => 226), 45 => array('min' => 227, 'max' => 231),
				46 => array('min' => 232, 'max' => 235), 47 => array('min' => 236, 'max' => 239), 48 => array('min' => 240, 'max' => 243),
				49 => array('min' => 244, 'max' => 247), 50 => array('min' => 248, 'max' => 252), 51 => array('min' => 253, 'max' => 256),
				52 => array('min' => 257, 'max' => 260), 53 => array('min' => 261, 'max' => 264), 54 => array('min' => 265, 'max' => 268),
				55 => array('min' => 269, 'max' => 273), 56 => array('min' => 274, 'max' => 277), 57 => array('min' => 278, 'max' => 281),
				58 => array('min' => 282, 'max' => 285), 59 => array('min' => 286, 'max' => 289), 60 => array('min' => 290, 'max' => 294),
				61 => array('min' => 295, 'max' => 298), 62 => array('min' => 299, 'max' => 302), 63 => array('min' => 303, 'max' => 306), 
				64 => array('min' => 307, 'max' => 311), 65 => array('min' => 312, 'max' => 315), 66 => array('min' => 316, 'max' => 319),
				67 => array('min' => 320, 'max' => 323), 68 => array('min' => 324, 'max' => 327), 69 => array('min' => 328, 'max' => 331),
				70 => array('min' => 332, 'max' => 336), 71 => array('min' => 337, 'max' => 340), 72 => array('min' => 341, 'max' => 345)
				)
		);

	}

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
		'extprob' =>  array('hyp', 'agg', 'cp'),
		'intprob' =>  array('anx', 'dep', 'som'),
        'behsymp'  => array('aty', 'wdl', 'apr', 'hyp', 'agg', 'dep'),
		'adaskill' => array('ada', 'ssk', 'ldr', 'adl', 'fc')
	);

	## 12 -14
	

	## 15-18

	

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

$forty = array('ssk', 'ldr', 'fc', 'ada', 'adl');
$sig_vals = array();
foreach ($source_names as $i => $result_name) {
    $tval = $tvalue_scores[$result_name . '_tval'];
    if ($tval == NULL)
    {
      $sig_vals[$result_name . '_sig'] = "Not enough information.";
    }
    else
    {
      if (in_array($result_name, $forty)) {
        if ($tval >= 41)
          {
            $sig_vals[$result_name . '_sig'] = "Average";
          }
          else if ($tval >= 31 and $tval <=40)
          {
            $sig_vals[$result_name . '_sig'] = "At Risk";
          }
          else if ($tval <= 30)
          {
            $sig_vals[$result_name . '_sig'] = "Clincially Significant";
          }
      }

      else
      {
          if ($tval < 60)
          {
            $sig_vals[$result_name . '_sig'] = "Average";
          }
          else if ($tval >= 60 and $tval <= 69)
          {
            $sig_vals[$result_name . '_sig'] = "At Risk";
          }
          else if ($tval > 69)
          {
            $sig_vals[$result_name . '_sig'] = "Clincially Significant";
          }
      }
    }
}

foreach ($composite_names as $i => $field_names) {
        $tval = $composite_tval[$field_names . '_comp_tval'];

        if ($tval == NULL)
        {
          $sig_vals[$field_names . '_sig'] = "Not enough information.";
        }
        else
        {
          if ($tval < 60)
          {
            $sig_vals[$field_names . '_sig'] = "Average";
          }
          else if ($tval >= 60 and $tval <= 69)
          {
            $sig_vals[$field_names . '_sig'] = "At Risk";
          }
          else if ($tval > 69)
          {
            $sig_vals[$field_names . '_sig'] = "Clincially Significant";
          }
        }
}

	#$this->module->emDebug("composite raw: " . implode(',',$composite_raw));
	#$this->module->emDebug("composite mean: " . implode(',', $composite_mean));
	#$this->module->emDebug("composite tval: " . implode(',', $composite_tval));

	### DEFINE RESULTS ###
	# REQUIRED: The algorithm_results variable MUST BE USED as it is relied upon from the parent script.
	$totals =  array_merge($raw_score_totals, $null_counter, $tvalue_scores, $tvalue_percent, $completeness_interpret, $F_index_result, $cons_index_result, $resp_pattern_result, $stat_result, $composite_raw, $composite_mean, $composite_tval, $sig_vals);
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

