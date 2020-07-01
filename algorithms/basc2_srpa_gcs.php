<?php
/**

	BASC2 - Self-Report of Personality - Adolescent
	
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

# REQUIRED: Summarize this algorithm
$algorithm_summary = "BASC2, Self Report of Personality - Adolescent";
$this->module->emDebug("Scoring Title: " . $algorithm_summary);

	$default_result_fields = array(
		'srpa_anx_raw', 'srpa_apr_raw','srpa_ata_raw', 'srpa_ats_raw',
		'srpa_att_raw','srpa_dep_raw', 'srpa_rwp_raw', 'srpa_sfe_raw', 
		'srpa_sfr_raw', 'srpa_soi_raw', 'srpa_som_raw', 'srpa_sos_raw',
		'srpa_loc_raw', 'srpa_hyp_raw', 'srpa_ipr_raw', 'srpa_ssk_raw',

		'srpa_anx_null', 'srpa_apr_null','srpa_ata_null', 'srpa_ats_null',
		'srpa_att_null','srpa_dep_null', 'srpa_rwp_null', 'srpa_sfe_null', 
		'srpa_sfr_null', 'srpa_soi_null', 'srpa_som_null', 'srpa_sos_null',
		'srpa_loc_null', 'srpa_hyp_null', 'srpa_ipr_null', 'srpa_ssk_null',

		'srpa_anx_tval', 'srpa_apr_tval','srpa_ata_tval', 'srpa_ats_tval',
		'srpa_att_tval','srpa_dep_tval', 'srpa_rwp_tval', 'srpa_sfe_tval', 
		'srpa_sfr_tval', 'srpa_soi_tval', 'srpa_som_tval', 'srpa_sos_tval',
		'srpa_loc_tval', 'srpa_hyp_tval', 'srpa_ipr_tval', 'srpa_ssk_tval',

		'srpa_anx_tvalp', 'srpa_apr_tvalp','srpa_ata_tvalp', 'srpa_ats_tvalp',
		'srpa_att_tvalp','srpa_dep_tvalp', 'srpa_rwp_tvalp', 'srpa_sfe_tvalp', 
		'srpa_sfr_tvalp', 'srpa_soi_tvalp', 'srpa_som_tvalp', 'srpa_sos_tvalp',
		'srpa_loc_tvalp', 'srpa_hyp_tvalp', 'srpa_ipr_tvalp', 'srpa_ssk_tvalp',


		 'srpa_emosymp_raw', 'srpa_inthyp_raw', 'srpa_intprob_raw', 'srpa_peradj_raw', 'srpa_sprob_raw',
		 'srpa_emosymp_tval', 'srpa_inthyp_tval', 'srpa_intprob_tval', 'srpa_peradj_tval', 'srpa_sprob_tval', 'srpa_emosymp_imean',
#		 'srpa_inthyp_tval', 'srpa_intprob_tval', 'srpa_peradj_tval',
#		 'srpa_sprob_tval', 'srpa_emosymp_tval', 'srpa_emosymp_imean',

		'srpa_anx_valid', 'srpa_apr_valid','srpa_ata_valid', 'srpa_ats_valid',
		'srpa_att_valid','srpa_dep_valid', 'srpa_rwp_valid', 'srpa_sfe_valid', 
		'srpa_sfr_valid', 'srpa_soi_valid', 'srpa_som_valid', 'srpa_sos_valid',
		'srpa_loc_valid', 'srpa_hyp_valid', 'srpa_ipr_valid', 'srpa_ssk_valid',

		'srpa_findex', 'srpa_fv', 'srpa_v',
		'srpa_vv', 'srpa_l', 'srpa_lv', 'srpa_con' ,'srpa_conv','srpa_patt',
		'srpa_pattv','srpa_allval','srpa_90val','srpa_scaletotal',
		'srpa_anx_sig', 'srpa_apr_sig', 'srpa_ata_sig',
		'srpa_ats_sig', 'srpa_att_sig', 'srpa_dep_sig',
		'srpa_hyp_sig', 'srpa_inthyp_sig', 'srpa_intprob_sig',
		'srpa_loc_sig','srpa_peradj_sig','srpa_soi_sig',
		'srpa_som_sig', 'srpa_sos_sig', 'srpa_sprob_sig',
		'srpa_ssk_sig', 'srpa_rwp_sig', 'srpa_ipr_sig',
		'srpa_sfe_sig','srpa_sfr_sig'

	);

	# REQUIRED: Define an array of fields that must be present for this algorithm to run

	$required_fields = array();
	foreach (range(1,176) as $i) {
		array_push($required_fields, "basc_srp_a_q$i");
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


	$source_names = array("anx","apr","ata","ats","att","dep","rwp","sfe","sfr","soi","som","sos","loc","hyp","ipr", "ssk"); 
	$source_indexes = array (
		'ats' => array(10,40,70,82,112,142,172),
		'att' => array(37,67,97,127,157,85,115,145,175),
		'ssk' => array(47,77,107,137,27,57,87,117,147),
		'ata' => array(62,92,122,152,100,130,160,119,149),
		'loc' => array(6,36,66,19,49,79,109,139,169),
		'sos' => array(75,105,135,165,26,56,86,116,146,176),
		'anx' => array(11,41,71,101,131,108,138,110,140,80, 50, 20, 170),
		'dep' => array(3,33,63,93,8,38,68,98,21,51,81,111),
		'soi' => array(24,54,84,114,144,30,60,90,120,150),
		'som' => array(4,34,64,9,39,69,99),
		'apr' => array(5,35,65,95,125,53,83,113,143),
		'hyp' => array(124,154,134,164,88,118,148),
		'rwp' => array(155,126,156,42,72,102,132,141,171,173),
		'ipr' => array(151,13,43,73,103,133,163),
		'sfe' => array(1,31,61,91,121,44,74,104),
		'sfr' => array(123,153,16,46,76,106,136,166)
	);

	$validity_arr = array(
		'ats' => 0,
		'att' => 0,
		'ssk' => 0,
		'ata' => 0,
		'loc' => 0,
		'sos' => 0,
		'anx' => 0,
		'dep' => 0,
		'soi' => 0,
		'som' => 0,
		'apr' => 0,
		'hyp' => 0,
		'rwp' => 0,
		'ipr' => 0,
		'sfe' => 0,
		'sfr' => 0
		);


	$mult_factor = array(
		'ats' => 1,
		'att' => 1,
		'ssk' => 1,
		'ata' => 0,
		'loc' => 1,
		'sos' => 1,
		'anx' => 1,
		'dep' => 1,
		'soi' => 1,
		'som' => 0,
		'apr' => 1,
		'hyp' => 1,
		'rwp' => 2,
		'ipr' => 2,
		'sfe' => 2,
		'sfr' => 2
		);

	$validity_key_source = array(

	'anx' => 'srpa_anx_valid',
	'apr' => 'srpa_apr_valid',
	'ata' => 'srpa_ata_valid',
	'ats' => 'srpa_ats_valid',
	'att' => 'srpa_att_valid',
	'dep' => 'srpa_dep_valid',
	'hyp' => 'srpa_hyp_valid',
	'ipr' => 'srpa_ipr_valid',
	'loc' => 'srpa_loc_valid',
	'rwp' => 'srpa_rwp_valid',
	'sfe' => 'srpa_sfe_valid',
	'sfr' => 'srpa_sfr_valid',
	'soi' => 'srpa_soi_valid',
	'som' => 'srpa_som_valid',
	'sos' => 'srpa_sos_valid',
	'ssk' => 'srpa_ssk_valid'
		);

	$F_index = 0; 

	$F_index_values = array(
		104	=> 0,
		152	=> 3,
		99	=> 3,
		71	=> 3,
		1	=> 1,
		95	=> 0,
		13	=> 0,
		68	=> 0,
		21	=> 0,
		43	=> 0,
		73	=> 3,
		176	=> 3,
		86	=> 3,
		149	=> 3,
		100	=> 3
	);

	$L_index = 0; 

	$L_index_values = array(
		58	=> 0,
		28	=> 0,
		12	=> 0,
		48	=> 1,
		7	=> 1,
		161	=> 0,
		25	=> 0,
		55	=> 1,
		52	=> 0,
		129	=> 3,
		17	=> 0,
		22	=> 0,
		45	=> 0,
		23	=> 0,
		15	=> 0,
	);

	$cons_index_values = array(
		61 => 1,
		63 => 3,
		43 => 13,
		51 => 24,
		44 => 31,
		69 => 34,
		53 => 35,
		141 => 72,
		166 => 76,
		147 => 77,
		139 => 79,
		172 => 82,
		145 => 85,
		124 => 88,
		121 => 91,
		105 => 93,
		140 => 110,
		160 => 122,
		163 => 133,
		173 => 155
		);

	$v_index = 0;
	$cons_index = 0;
	$resp_pattern = 0;
	$last_response = 0;


	$raw_answers = array();


	# These are some fields that need their values flipped before scoring. Instead of going from 0 => 3, they go from 3 => 0
	$new_source = array();
	$flipped_values = array(70,73,85,91,95,103,121,125,145,151);
	$flipped_tf = array(1,2,3,4,5,6,8,9,10,11,14,16,18,19,20,21,24,26,27,29,30,32,33,34,35,36,38,39,40,41,42,46,47,49,50,51,53,54,56,57,59,60,61,62,63,64,65,66,68,69);
	$one_response_tf = array(7,12,15,17,22,23,25,28,45,48,52,55,58);
	$one_response_flipped_tf = array(12,15,17,22,23,25,28,45,52,58);
	$one_response_reg = array(129,161);
	foreach ($required_fields as $i => $req_name) {

		$index = $i+1;
		$val = $src[$req_name];
		if (isset($val) and strlen($val) > 0)
		{
			$val = $src[$req_name]-1;
		}

		if ($index == 13 or $index == 43 or $index == 31 or $index == 44)
		{
			if ($val == 0 or !isset($val) or strlen($val) <= 0)
			{
				$raw_answers[$req_name] = 0;
			}
			else
			{
				$raw_answers[$req_name] = 2;
			}
		}
		else
		{
			$raw_answers[$req_name] = (isset($val) and strlen($val) > 0) ? $val : 0;
		}

		
		if ($index == 159 or $index == 94)
		{
#			$this->module->emDebug("At index: " . $index);
#			$this->module->emDebug("Curr: " . $v_index . " and adding orig: " . $val);
			$v_index += (isset($val) and strlen($val) > 0) ? $val : 0;
#			$this->module->emDebug("Now: " . $v_index);

		}
		if ($index == 32 or $index == 18 or $index == 59)
		{
#			$this->module->emDebug("At Index: " . $index);
#			$this->module->emDebug("Val: " . $val);
#			$this->module->emDebug("Curr: " . $v_index . " and adding orig: " . $val . " for index " + $index);

			if ((isset($val) and strlen($val) > 0))
			{
				if ($val == 0)
				{
					$v_index += 2;

				}
			}
#			$this->module->emDebug("Now: " . $v_index);

		}

		if (in_array(($index), $flipped_values)) {
			$new_source[$req_name] = (isset($val) and strlen($val) > 0)  ? 3-$val : null;
	#		$this->module->emDebug("Flipped values: i=$i, field_name = $req_name, val = $val, new source = $new_source[$req_name]");
		} 
		else if (($index)<70) {
			if (in_array(($index), $one_response_tf))
			{
				$realval = 0;
				if (in_array(($index), $one_response_flipped_tf))
				{
					$new_source[$req_name] = (isset($val) and strlen($val) > 0) ? 1-$val : null;
				}
				else
				{
					$new_source[$req_name] = (isset($val) and strlen($val) > 0) ? $val : null;
				}
			}
			else if (in_array(($index), $flipped_tf))
			{
				$realval = 0;
				if ($val == 0)
				{
					$realval = 2;
				}
				$new_source[$req_name] = (isset($val) and strlen($val) > 0) ? $realval: null;
			}
			else
			{
				$realval = 0;
				if ($val == 1)
				{
					$realval = 2;
				}
				$new_source[$req_name] = (isset($val) and strlen($val) > 0) ? $realval : null;
			}
		}
		else
		{
			if (in_array(($index), $one_response_reg))
			{
				$realval = 0;
				if (($index) == 129)
				{
					if ($val == 3)
					{
						$realval = 1;
					}
					$new_source[$req_name] = (isset($val) and strlen($val) > 0) ? $realval : null;
				}
				else if (($index) == 161) 
				{
					if ($val == 0)
					{
						$realval = 1;
					}
					$new_source[$req_name] = (isset($val) and strlen($val) > 0) ? $realval : null;
				}
			}
			else
			{
				$new_source[$req_name] = (isset($val) and strlen($val) > 0) ? $val : null;
			}
		}


		if (in_array($i+1, $source_indexes['anx']))
		{
#			$this->module->emDebug("for " . ($i+1) . " Orig: " . $src[$req_name] . " added: " . $new_source[$req_name]);
		}

		if (array_key_exists(($index), $F_index_values))
		{
			if ($val == $F_index_values[($index)])
			{
				$F_index++;
			}
		}

		if (array_key_exists(($index), $L_index_values))
		{
			if ($val == $L_index_values[($index)])
			{
				$L_index++;
			}
		}

		if (array_key_exists(($index), $cons_index_values))
			{
				$cons_pair_val = $raw_answers[$required_fields[$cons_index_values[$index]-1]];
				$thisval = $raw_answers[$req_name];
#				$this->module->emDebug($req_name . ":" . $required_fields[$cons_index_values[$index]-1] . " || " . $raw_answers[$req_name] . ":" . $cons_pair_val);
				$cons_index += abs($cons_pair_val - $thisval);
#				$this->module->emDebug("adding: " . $cons_index);
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
			$last_response = (isset($val) and strlen($val) > 0) ? $val : -1;
		}
		else 
		{
			if ($last_response == -1)
			{
				$last_response =  (isset($val) and strlen($val) > 0) ? $val : -1;
			}
			else
			{
				if ($val != $last_response)
				{
					$resp_pattern++;
				}
				$last_response =  (isset($val) and strlen($val) > 0) ? $val : -1;
			}
		}
	}

	### IMPLEMENT RAW SCORING ###
	# This is the array source
	# Define lists of questions that correspond to each subscale
	# Note: Most questions are scored as N=0, S=1, O=2, A=3 but some questions have reversed scoring
	#    The following scoring should already be reversed from above: 5,14,26,63,65,76,81,91,104,106,107,108,122,146
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
	//				$this->module->emDebug("null value $val for field_name = $field_name, index = $index, null counter = $null_counter[$result_name]");
				}
	       		}
		}

		
	}

	foreach ($source_names as $j => $result_name) {
		$this->module->emDebug($result_name . ": " . $raw_scores[$result_name]);
	}

	$tvalue = array();
	$tvalue_perc = array();
	$composite_comb_tvalues = array();

	## These are 15-18

	if ($src['basc_srp_a_age'] >= 15 and $src['basc_srp_a_age'] <=18)
	{
#		$this->module->emDebug("His age is " . $src['basc_srp_a_age']);
		$tvalue = array(
			'ats' => array(
					0 => 32, 1 => 35, 2 => 37, 3 => 40, 4 => 42, 5 => 45,
					6 => 47, 7 => 50, 8 => 52, 9 => 55, 10 => 57,
					11 => 60, 12 => 63, 13 => 65, 14 => 68, 15 => 70,
					16 => 73, 17 => 75, 18 => 78, 19 => 80
				),
			'att' => array(
					0 => 34, 1 => 36, 2 => 39, 3 => 41, 4 => 43, 5 => 46,
					6 => 48, 7 => 50, 8 => 53, 9 => 55, 10 => 58,
					11 => 60, 12 => 62, 13 => 65, 14 => 67, 15 => 70,
					16 => 72, 17 => 74, 18 => 77, 19 => 79, 20 => 82,
					21 => 84, 22 => 86, 23 => 89, 24 => 91, 25 => 93
				),
			'ssk' => array(
					0 => 24, 1 => 26, 2 => 28, 3 => 30, 4 => 33, 5 => 35,
					6 => 37, 7 => 40, 8 => 42, 9 => 44, 10 => 47,
					11 => 49, 12 => 51, 13 => 53, 14 => 56, 15 => 58,
					16 => 60, 17 => 63, 18 => 65, 19 => 67, 20 => 70,
					21 => 72, 22 => 74, 23 => 76, 24 => 79
				),
			'ata' => array(
					0 => 42, 1 => 45, 2 => 48, 3 => 50, 4 => 53, 5 => 56,
					6 => 59, 7 => 62, 8 => 65, 9 => 68, 10 => 70,
					11 => 73, 12 => 76, 13 => 79, 14 => 82, 15 => 85,
					16 => 87, 17 => 90, 18 => 93, 19 => 96, 20 => 99,
					21 => 102, 22 => 105, 23 => 107, 24 => 110, 25 => 113,
					26 => 116
				),
			'loc' => array(
					0 => 37, 1 => 39, 2 => 41, 3 => 44, 4 => 46, 5 => 48,
					6 => 51, 7 => 53, 8 => 55, 9 => 57, 10 => 60,
					11 => 62, 12 => 64, 13 => 67, 14 => 69, 15 => 71,
					16 => 73, 17 => 76, 18 => 78, 19 => 80, 20 => 83,
					21 => 85, 22 => 87
				),
			'sos' => array(
					0 => 34, 1 => 36, 2 => 38, 3 => 40, 4 => 43, 5 => 45,
					6 => 47, 7 => 49, 8 => 51, 9 => 53, 10 => 56,
					11 => 58, 12 => 60, 13 => 62, 14 => 64, 15 => 66,
					16 => 69, 17 => 71, 18 => 73, 19 => 75, 20 => 77,
					21 => 80, 22 => 82, 23 => 84, 24 => 86, 25 => 88,
					26 => 90, 27 => 93, 28 => 95
				),
			'anx' => array(
					0 => 32, 1 => 34, 2 => 35, 3 => 37, 4 => 38, 5 => 40,
					6 => 42, 7 => 43, 8 => 45, 9 => 46, 10 => 48,
					11 => 50, 12 => 51, 13 => 53, 14 => 54, 15 => 56,
					16 => 58, 17 => 59, 18 => 61, 19 => 62, 20 => 64,
					21 => 65, 22 => 67, 23 => 69, 24 => 70, 25 => 72,
					26 => 73, 27 => 75, 28 => 77, 29 => 78, 30 => 80,
					31 => 81, 32 => 83, 33 => 85, 34 => 86, 35 => 88
				),
			'dep' => array(
					0 => 40, 1 => 41, 2 => 43, 3 => 45, 4 => 47, 5 => 49,
					6 => 51, 7 => 53, 8 => 55, 9 => 57, 10 => 59,
					11 => 61, 12 => 62, 13 => 64, 14 => 66, 15 => 68,
					16 => 70, 17 => 72, 18 => 74, 19 => 76, 20 => 78,
					21 => 80, 22 => 82, 23 => 84, 24 => 85, 25 => 87,
					26 => 89, 27 => 91, 28 => 93
				),
			'soi' => array(
					0 => 35, 1 => 37, 2 => 40, 3 => 42, 4 => 44, 5 => 47,
					6 => 49, 7 => 51, 8 => 54, 9 => 56, 10 => 58,
					11 => 61, 12 => 63, 13 => 65, 14 => 68, 15 => 70,
					16 => 72, 17 => 75, 18 => 77, 19 => 79, 20 => 82,
					21 => 84, 22 => 86, 23 => 89, 24 => 91, 25 => 93,
					26 => 96
				),
			'som' => array(
					0 => 40, 1 => 44, 2 => 47, 3 => 50, 4 => 53, 5 => 56,
					6 => 60, 7 => 63, 8 => 66, 9 => 69, 10 => 73,
					11 => 76, 12 => 79, 13 => 82, 14 => 86, 15 => 89
				),
			'apr' => array(
					0 => 34, 1 => 36, 2 => 38, 3 => 41, 4 => 43, 5 => 45,
					6 => 47, 7 => 50, 8 => 52, 9 => 54, 10 => 56,
					11 => 59, 12 => 61, 13 => 63, 14 => 66, 15 => 68,
					16 => 70, 17 => 72, 18 => 75, 19 => 77, 20 => 79,
					21 => 82, 22 => 84, 23 => 86
				),
			'hyp' => array(
					0 => 33, 1 => 36, 2 => 39, 3 => 42, 4 => 45, 5 => 48,
					6 => 51, 7 => 54, 8 => 57, 9 => 60, 10 => 63,
					11 => 66, 12 => 69, 13 => 72, 14 => 75, 15 => 78,
					16 => 81, 17 => 84, 18 => 87, 19 => 90, 20 => 94,
					21 => 97
				),
			'rwp' => array(
					0 => 18, 1 => 19, 2 => 21, 3 => 23, 4 => 25, 5 => 26,
					6 => 28, 7 => 30, 8 => 31, 9 => 33, 10 => 35,
					11 => 36, 12 => 38, 13 => 40, 14 => 41, 15 => 43,
					16 => 45, 17 => 46, 18 => 48, 19 => 50, 20 => 51,
					21 => 53, 22 => 55, 23 => 57, 24 => 58, 25 => 60,
					26 => 62, 27 => 63, 28 => 65, 29 => 67
				),
			'ipr' => array(
					0 => 10, 1 => 10, 2 => 10, 3 => 10, 4 => 13, 5 => 16,
					6 => 19, 7 => 22, 8 => 26, 9 => 29, 10 => 32,
					11 => 36, 12 => 39, 13 => 42, 14 => 45, 15 => 49,
					16 => 52, 17 => 55, 18 => 59, 19 => 62
				),
			'sfe' => array(
					0 => 13, 1 => 15, 2 => 18, 3 => 20, 4 => 23, 5 => 25,
					6 => 28, 7 => 30, 8 => 33, 9 => 35, 10 => 37,
					11 => 40, 12 => 42, 13 => 45, 14 => 47, 15 => 50,
					16 => 52, 17 => 55, 18 => 57, 19 => 60, 20 => 62
				),
			'sfr' => array(
					0 => 10, 1 => 10, 2 => 13, 3 => 16, 4 => 18, 5 => 21,
					6 => 24, 7 => 27, 8 => 30, 9 => 33, 10 => 35,
					11 => 38, 12 => 41, 13 => 44, 14 => 47, 15 => 50,
					16 => 53, 17 => 55, 18 => 58, 19 => 61, 20 => 64,
					21 => 67, 22 => 70
				)
			);


		$tvalue_perc = array(
			'ats' => array(
					0 => 1, 1 => 1, 2 => 6, 3 => 14, 4 => 24, 5 => 35, 
					6 => 46, 7 => 56, 8 => 64, 9 => 72, 10 => 78,
					11 => 84, 12 => 88, 13 => 91, 14 => 94, 15 => 96,
					16 => 97, 17 => 98, 18 => 99, 19 => 99
				),
			'att' => array(
					0 => 2, 1 => 5, 2 => 10, 3 => 18, 4 => 27, 5 => 38,
					6 => 48, 7 => 58, 8 => 66, 9 => 74, 10 => 80,
					11 => 85, 12 => 89, 13 => 92, 14 => 94, 15 => 96,
					16 => 97, 17 => 98, 18 => 99, 19 => 99, 20 => 99,
					21 => 99, 22 => 99, 23 => 99, 24 => 99, 25 => 99
				),
			'ssk' => array(
					0 => 1, 1 => 1, 2 => 1, 3 => 2, 4 => 4, 5 => 7,
					6 => 11, 7 => 16, 8 => 22, 9 => 29, 10 => 37,
					11 => 46, 12 => 54, 13 => 63, 14 => 71, 15 => 78,
					16 => 84, 17 => 89, 18 => 93, 19 => 96, 20 => 98,
					21 => 99, 22 => 99, 23 => 99, 24 => 99
				),
			'ata' => array(
					0 => 14, 1 => 38, 2 => 54, 3 => 66, 4 => 74, 5 => 81, 
					6 => 85, 7 => 88, 8 => 91, 9 => 93, 10 => 95,
					11 => 96, 12 => 97, 13 => 98, 14 => 98, 15 => 99,
					16 => 99, 17 => 99, 18 => 99, 19 => 99, 20 => 99, 
					21 => 99, 22 => 99, 23 => 99, 24 => 99, 25 => 99, 
					26 => 99
				),
			'loc' => array(
					0 => 4, 1 => 12, 2 => 21, 3 => 31, 4 => 41, 5 => 50,
					6 => 59, 7 => 66, 8 => 73, 9 => 78, 10 => 83,
					11 => 87, 12 => 90, 13 => 93, 14 => 95, 15 => 96,
					16 => 97, 17 => 98, 18 => 99, 19 => 99, 20 => 99,
					21 => 99, 22 => 99
				),
			'sos' => array(
					0 => 2, 1 => 5, 2 => 10, 3 => 16, 4 => 24, 5 => 33,
					6 => 43, 7 => 51, 8 => 60, 9 => 67, 10 => 74,
					11 => 80, 12 => 84, 13 => 88, 14 => 91, 15 => 93,
					16 => 95, 17 => 96, 18 => 97, 19 => 98, 20 => 99,
					21 => 99, 22 => 99, 23 => 99, 24 => 99, 25 => 99,
					26 => 99, 27 => 99, 28 => 99
				),
			'anx' => array(
					0 => 1, 1 => 2, 2 => 4, 3 => 8, 4 => 12, 5 => 17,
					6 => 23, 7 => 29, 8 => 35, 9 => 41, 10 => 47,
					11 => 53, 12 => 58, 13 => 64, 14 => 68, 15 => 73,
					16 => 77, 17 => 81, 18 => 84, 19 => 87, 20 => 90,
					21 => 92, 22 => 94, 23 => 95, 24 => 97, 25 => 98,
					26 => 98, 27 => 99, 28 => 99, 29 => 99, 30 => 99, 
					31 => 99, 32 => 99, 33 => 99, 34 => 99, 35 => 99

				),
			'dep' => array(
					0 => 1, 1 => 10, 2 => 29, 3 => 43, 4 => 53, 5 => 62, 
					6 => 68, 7 => 73, 8 => 78, 9 => 81, 10 => 84,
					11 => 86, 12 => 89, 13 => 90, 14 => 92, 15 => 93,
					16 => 94, 17 => 95, 18 => 96, 19 => 97, 20 => 97,
					21 => 98, 22 => 98, 23 => 99, 24 => 99, 25 => 99,
					26 => 99, 27 => 99, 28 => 99
				),
			'soi' => array(
					0 => 1, 1 => 4, 2 => 12, 3 => 22, 4 => 33, 5 => 44,
					6 => 54, 7 => 63, 8 => 70, 9 => 76, 10 => 81,
					11 => 86, 12 => 89, 13 => 92, 14 => 94, 15 => 95,
					16 => 97, 17 => 97, 18 => 98, 19 => 99, 20 => 99,
					21 => 99, 22 => 99, 23 => 99, 24 => 99, 25 => 99,
					26 => 99
				),
			'som' => array(
					0 => 13, 1 => 33, 2 => 48, 3 => 61, 4 => 70, 5 => 78,
					6 => 83, 7 => 88, 8 => 91, 9 => 94, 10 => 96,
					11 => 98, 12 => 99, 13 => 99, 14 => 99, 15 => 99
				),
			'apr' => array(
					0 => 1, 1 => 3, 2 => 10, 3 => 19, 4 => 28, 5 => 38,
					6 => 47, 7 => 55, 8 => 62, 9 => 69, 10 => 75,
					11 => 80, 12 => 85, 13 => 88, 14 => 91, 15 => 94,
					16 => 96, 17 => 97, 18 => 99, 19 => 99, 20 => 99,
					21 => 99, 22 => 99, 23 => 99
				),
			'hyp' => array(
					0 => 1, 1 => 5, 2 => 12, 3 => 22, 4 => 34, 5 => 47,
					6 => 60, 7 => 70, 8 => 79, 9 => 85, 10 => 90,
					11 => 93, 12 => 96, 13 => 97, 14 => 98, 15 => 99,
					16 => 99, 17 => 99, 18 => 99, 19 => 99, 20 => 99,
					21 => 99
				),
			'rwp' => array(
					0 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 2,
					6 => 2, 7 => 3, 8 => 5, 9 => 6, 10 => 8,
					11 => 11, 12 => 13, 13 => 16, 14 => 20, 15 => 24,
					16 => 29, 17 => 34, 18 => 39, 19 => 45, 20 => 51,
					21 => 57, 22 => 64, 23 => 70, 24 => 77, 25 => 83,
					26 => 88, 27 => 93, 28 => 96, 29 => 99
				),
			'ipr' => array(
					0 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1,
					6 => 1, 7 => 2, 8 => 3, 9 => 4, 10 => 6, 
					11 => 9, 12 => 13, 13 => 18, 14 => 25, 15 => 35, 
					16 => 48, 17 => 64, 18 => 82, 19 => 97
				),
			'sfe' => array(
					0 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 2, 5 => 3,
					6 => 4, 7 => 6, 8 => 7, 9 => 10, 10 => 12,
					11 => 16, 12 => 20, 13 => 25, 14 => 31, 15 => 38,
					16 => 47, 17 => 58, 18 => 72, 19 => 88, 20 => 99
				),
			'sfr' => array(
					0 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1,
					6 => 1, 7 => 2, 8 => 3, 9 => 5, 10 => 9,
					11 => 13, 12 => 19, 13 => 27, 14 => 36, 15 => 46,
					16 => 57, 17 => 68, 18 => 78, 19 => 87, 20 => 93,
					21 => 97, 22 => 99
				)
			);

$composite_comb_tvalues = array(
				'sprob' => array( 
					23 => array('min' => 90, 'max' => 90), 24 => array('min' => 91, 'max' => 92), 25 => array('min' => 93, 'max' => 94),
					26 => array('min' => 95, 'max' => 96), 27 => array('min' => 97, 'max' => 99), 28 => array('min' => 100, 'max' => 101),
					29 => array('min' => 102, 'max' => 103), 30 => array('min' => 104, 'max' => 105), 31 => array('min' => 106, 'max' => 108),
					32 => array('min' => 109, 'max' => 110), 33 => array('min' => 111, 'max' => 112), 34 => array('min' => 113, 'max' => 114),
					35 => array('min' => 115, 'max' => 117), 36 => array('min' => 118, 'max' => 119), 37 => array('min' => 120, 'max' => 121),
					38 => array('min' => 122, 'max' => 123), 39 => array('min' => 124, 'max' => 126), 40 => array('min' => 127, 'max' => 128),
					41 => array('min' => 129, 'max' => 130), 42 => array('min' => 131, 'max' => 133), 43 => array('min' => 134, 'max' => 135), 
					44 => array('min' => 136, 'max' => 137), 45 => array('min' => 138, 'max' => 139), 46 => array('min' => 140, 'max' => 141),
					47 => array('min' => 142, 'max' => 144), 48 => array('min' => 145, 'max' => 146), 49 => array('min' => 147, 'max' => 148),
					50 => array('min' => 149, 'max' => 151), 51 => array('min' => 152, 'max' => 153), 52 => array('min' => 154, 'max' => 155),
					53 => array('min' => 156, 'max' => 157), 54 => array('min' => 158, 'max' => 160), 55 => array('min' => 161, 'max' => 162),
					56 => array('min' => 163, 'max' => 164), 57 => array('min' => 165, 'max' => 166), 58 => array('min' => 167, 'max' => 169),
					59 => array('min' => 170, 'max' => 171), 60 => array('min' => 172, 'max' => 173), 61 => array('min' => 174, 'max' => 176), 
					62 => array('min' => 177, 'max' => 178), 63 => array('min' => 179, 'max' => 180), 64 => array('min' => 181, 'max' => 182),
					65 => array('min' => 183, 'max' => 185), 66 => array('min' => 186, 'max' => 187), 67 => array('min' => 188, 'max' => 189), 
					68 => array('min' => 190, 'max' => 191), 69 => array('min' => 192, 'max' => 194), 70 => array('min' => 195, 'max' => 196),
					71 => array('min' => 197, 'max' => 198), 72 => array('min' => 199, 'max' => 200), 73 => array('min' => 201, 'max' => 203),
					74 => array('min' => 204, 'max' => 205), 75 => array('min' => 206, 'max' => 207), 76 => array('min' => 208, 'max' => 209),
					77 => array('min' => 210, 'max' => 212), 78 => array('min' => 213, 'max' => 214), 79 => array('min' => 215, 'max' => 216),
					80 => array('min' => 217, 'max' => 219), 81 => array('min' => 220, 'max' => 221), 82 => array('min' => 222, 'max' => 223),
					83 => array('min' => 224, 'max' => 225), 84 => array('min' => 226, 'max' => 228), 85 => array('min' => 229, 'max' => 230), 
					86 => array('min' => 231, 'max' => 232), 87 => array('min' => 233, 'max' => 234), 88 => array('min' => 235, 'max' => 237),
					89 => array('min' => 238, 'max' => 239), 90 => array('min' => 240, 'max' => 241), 91 => array('min' => 242, 'max' => 243),
					92 => array('min' => 244, 'max' => 246), 93 => array('min' => 247, 'max' => 248), 94 => array('min' => 249, 'max' => 250),
					95 => array('min' => 251, 'max' => 252),  
					),
				'intprob' => array(
					33 => array('min' => 260, 'max' => 261), 34 => array('min' => 262, 'max' => 266), 35 => array('min' => 267, 'max' => 272),
					36 => array('min' => 273, 'max' => 277), 37 => array('min' => 278, 'max' => 282), 38 => array('min' => 283, 'max' => 288),
					39 => array('min' => 289, 'max' => 293), 40 => array('min' => 294, 'max' => 298), 41 => array('min' => 299, 'max' => 304),
					42 => array('min' => 305, 'max' => 309), 43 => array('min' => 310, 'max' => 315), 44 => array('min' => 316, 'max' => 320), 
					45 => array('min' => 321, 'max' => 325), 46 => array('min' => 326, 'max' => 331), 47 => array('min' => 332, 'max' => 336),
					48 => array('min' => 337, 'max' => 341), 49 => array('min' => 342, 'max' => 347), 50 => array('min' => 348, 'max' => 352),
					51 => array('min' => 353, 'max' => 358), 52 => array('min' => 359, 'max' => 363), 53 => array('min' => 364, 'max' => 368),
					54 => array('min' => 369, 'max' => 374), 55 => array('min' => 375, 'max' => 379), 56 => array('min' => 380, 'max' => 384),
					57 => array('min' => 385, 'max' => 390), 58 => array('min' => 391, 'max' => 395), 59 => array('min' => 196, 'max' => 401),
					60 => array('min' => 402, 'max' => 406), 61 => array('min' => 407, 'max' => 411), 62 => array('min' => 412, 'max' => 417),
					63 => array('min' => 418, 'max' => 422), 64 => array('min' => 423, 'max' => 427), 65 => array('min' => 428, 'max' => 433),
					66 => array('min' => 434, 'max' => 438), 67 => array('min' => 439, 'max' => 444), 68 => array('min' => 445, 'max' => 449),
					69 => array('min' => 450, 'max' => 454), 70 => array('min' => 455, 'max' => 460), 71 => array('min' => 461, 'max' => 465),
					72 => array('min' => 466, 'max' => 470), 73 => array('min' => 471, 'max' => 476), 74 => array('min' => 477, 'max' => 481),
					75 => array('min' => 482, 'max' => 487), 76 => array('min' => 488, 'max' => 492), 77 => array('min' => 493, 'max' => 497),
					78 => array('min' => 498, 'max' => 503), 79 => array('min' => 504, 'max' => 508), 80 => array('min' => 509, 'max' => 513),
					81 => array('min' => 514, 'max' => 519), 82 => array('min' => 520, 'max' => 524), 83 => array('min' => 525, 'max' => 530),
					84 => array('min' => 531, 'max' => 535), 85 => array('min' => 536, 'max' => 540), 86 => array('min' => 541, 'max' => 546),
					87 => array('min' => 547, 'max' => 551), 88 => array('min' => 552, 'max' => 556), 89 => array('min' => 557, 'max' => 562),
					90 => array('min' => 563, 'max' => 567), 91 => array('min' => 568, 'max' => 573), 92 => array('min' => 574, 'max' => 578), 
					93 => array('min' => 579, 'max' => 583), 94 => array('min' => 584, 'max' => 589), 95 => array('min' => 590, 'max' => 594),
					96 => array('min' => 595, 'max' => 599), 97 => array('min' => 600, 'max' => 605), 98 => array('min' => 606, 'max' => 610),
					99 => array('min' => 611, 'max' => 615), 100 => array('min' => 616, 'max' => 621), 101 => array('min' => 622, 'max' => 626),
					102 => array('min' => 627, 'max' => 632), 103 => array('min' => 633, 'max' => 637), 104 => array('min' => 638, 'max' => 642),
					105 => array('min' => 643, 'max' => 648), 106 => array('min' => 649, 'max' => 653), 107 => array('min' => 654, 'max' => 658),
					108 => array('min' => 659, 'max' => 664)
					),
				'inthyp' => array(
					31 => array('min' => 67, 'max' => 67), 32 => array('min' => 68, 'max' => 69), 33 => array('min' => 70, 'max' => 71),
					34 => array('min' => 72, 'max' => 73), 35 => array('min' => 74, 'max' => 74), 36 => array('min' => 75, 'max' => 76),
					37 => array('min' => 77, 'max' => 78), 38 => array('min' => 79, 'max' => 80), 39 => array('min' => 81, 'max' => 81),
					40 => array('min' => 82, 'max' => 83), 41 => array('min' => 84, 'max' => 85), 42 => array('min' => 86, 'max' => 86),
					43 => array('min' => 87, 'max' => 88), 44 => array('min' => 89, 'max' => 90), 45 => array('min' => 91, 'max' => 92),
					46 => array('min' => 93, 'max' => 93), 47 => array('min' => 94, 'max' => 95), 48 => array('min' => 96, 'max' => 97), 
					49 => array('min' => 98, 'max' => 99), 50 => array('min' => 100, 'max' => 100), 51 => array('min' => 101, 'max' => 102),
					52 => array('min' => 103, 'max' => 104), 53 => array('min' => 105, 'max' => 106), 54 => array('min' => 107, 'max' => 107),
					55 => array('min' => 108, 'max' => 109), 56 => array('min' => 110, 'max' => 111), 57 => array('min' => 112, 'max' => 113),
					58 => array('min' => 114, 'max' => 114), 59 => array('min' => 115, 'max' => 116), 60 => array('min' => 117, 'max' => 118),
					61 => array('min' => 119, 'max' => 119), 62 => array('min' => 120, 'max' => 121), 63 => array('min' => 122, 'max' => 123),
					64 => array('min' => 124, 'max' => 125), 65 => array('min' => 126, 'max' => 126), 66 => array('min' => 127, 'max' => 128),
					67 => array('min' => 129, 'max' => 130), 68 => array('min' => 131, 'max' => 132), 69 => array('min' => 133, 'max' => 133),
					70 => array('min' => 134, 'max' => 135), 71 => array('min' => 136, 'max' => 137), 72 => array('min' => 138, 'max' => 139),
					73 => array('min' => 140, 'max' => 140), 74 => array('min' => 141, 'max' => 142), 75 => array('min' => 143, 'max' => 144),
					76 => array('min' => 145, 'max' => 145), 77 => array('min' => 146, 'max' => 147), 78 => array('min' => 148, 'max' => 149),
					79 => array('min' => 150, 'max' => 151), 80 => array('min' => 152, 'max' => 152), 81 => array('min' => 153, 'max' => 154),
					82 => array('min' => 155, 'max' => 156), 83 => array('min' => 157, 'max' => 158), 84 => array('min' => 159, 'max' => 159),
					85 => array('min' => 160, 'max' => 161), 86 => array('min' => 162, 'max' => 163), 87 => array('min' => 164, 'max' => 165),
					88 => array('min' => 166, 'max' => 166), 89 => array('min' => 167, 'max' => 168), 90 => array('min' => 169, 'max' => 170),
					91 => array('min' => 171, 'max' => 171), 92 => array('min' => 172, 'max' => 173), 93 => array('min' => 174, 'max' => 175),
					94 => array('min' => 176, 'max' => 177), 95 => array('min' => 178, 'max' => 178), 96 => array('min' => 179, 'max' => 180),
					97 => array('min' => 181, 'max' => 182), 98 => array('min' => 183, 'max' => 183)
					),
				'peradj' => array(
					10 => array('min' => 10, 'max' => 85), 11 => array('min' => 86, 'max' => 88), 12 => array('min' => 89, 'max' => 91),
					13 => array('min' => 92, 'max' => 94), 14 => array('min' => 95, 'max' => 97), 15 => array('min' => 98, 'max' => 99),
					16 => array('min' => 100, 'max' => 102), 17 => array('min' => 103, 'max' => 105), 18 => array('min' => 106, 'max' => 108),
					19 => array('min' => 109, 'max' => 111), 20 => array('min' => 112, 'max' => 114), 21 => array('min' => 115, 'max' => 117),
					22 => array('min' => 118, 'max' => 120), 23 => array('min' => 121, 'max' => 123), 24 => array('min' => 124, 'max' => 126), 
					25 => array('min' => 127, 'max' => 128), 26 => array('min' => 129, 'max' => 131), 27 => array('min' => 132, 'max' => 134),
					28 => array('min' => 135, 'max' => 137), 29 => array('min' => 138, 'max' => 140), 30 => array('min' => 141, 'max' => 143),
					31 => array('min' => 144, 'max' => 146), 32 => array('min' => 147, 'max' => 149), 33 => array('min' => 150, 'max' => 152),
					34 => array('min' => 153, 'max' => 155), 35 => array('min' => 156, 'max' => 157), 36 => array('min' => 158, 'max' => 160),
					37 => array('min' => 161, 'max' => 163), 38 => array('min' => 164, 'max' => 166), 39 => array('min' => 167, 'max' => 169),
					40 => array('min' => 170, 'max' => 172), 41 => array('min' => 173, 'max' => 175), 42 => array('min' => 176, 'max' => 178),
					43 => array('min' => 179, 'max' => 181), 44 => array('min' => 182, 'max' => 184), 45 => array('min' => 185, 'max' => 186),
					46 => array('min' => 186, 'max' => 189), 47 => array('min' => 190, 'max' => 192), 48 => array('min' => 193, 'max' => 195),
					49 => array('min' => 196, 'max' => 198), 50 => array('min' => 199, 'max' => 201), 51 => array('min' => 202, 'max' => 204),
					52 => array('min' => 205, 'max' => 207), 53 => array('min' => 208, 'max' => 210), 54 => array('min' => 211, 'max' => 213), 
					55 => array('min' => 214, 'max' => 215), 56 => array('min' => 216, 'max' => 218), 57 => array('min' => 219, 'max' => 221),
					58 => array('min' => 222, 'max' => 224), 59 => array('min' => 225, 'max' => 227), 60 => array('min' => 228, 'max' => 230),
					61 => array('min' => 231, 'max' => 233), 62 => array('min' => 234, 'max' => 236), 63 => array('min' => 237, 'max' => 239),
					64 => array('min' => 240, 'max' => 242), 65 => array('min' => 243, 'max' => 244), 66 => array('min' => 245, 'max' => 247),
					67 => array('min' => 248, 'max' => 250), 68 => array('min' => 251,' max' => 253), 69 => array('min' => 254, 'max' => 256),
					70 => array('min' => 257, 'max' => 259), 71 => array('min' => 260, 'max' => 261)
					),
				'emosymp' => array(
					20 => array('min' => 164, 'max' => 164), 21 => array('min' => 165, 'max' => 169), 22 => array('min' => 170, 'max' => 174),
					23 => array('min' => 175, 'max' => 178), 24 => array('min' => 179, 'max' => 183), 25 => array('min' => 184, 'max' => 188),
					26 => array('min' => 189, 'max' => 193), 27 => array('min' => 194, 'max' => 197), 28 => array('min' => 198, 'max' => 202),
					29 => array('min' => 203, 'max' => 206), 30 => array('min' => 207, 'max' => 210), 31 => array('min' => 211, 'max' => 215),
					32 => array('min' => 216, 'max' => 219), 33 => array('min' => 220, 'max' => 224), 34 => array('min' => 225, 'max' => 228),
					35 => array('min' => 229, 'max' => 233), 36 => array('min' => 234, 'max' => 238), 37 => array('min' => 239, 'max' => 242),
					38 => array('min' => 243, 'max' => 247), 39 => array('min' => 248, 'max' => 251), 40 => array('min' => 252, 'max' => 256),
					41 => array('min' => 257, 'max' => 261), 42 => array('min' => 262, 'max' => 265), 43 => array('min' => 266, 'max' => 270),
					44 => array('min' => 271, 'max' => 274), 45 => array('min' => 275, 'max' => 279), 46 => array('min' => 280, 'max' => 283),
					47 => array('min' => 284, 'max' => 288), 48 => array('min' => 289, 'max' => 293), 49 => array('min' => 294, 'max' => 297),
					50 => array('min' => 298, 'max' => 302), 51 => array('min' => 303, 'max' => 306), 52 => array('min' => 307, 'max' => 311),
					53 => array('min' => 312, 'max' => 316), 54 => array('min' => 317, 'max' => 320), 55 => array('min' => 321, 'max' => 325),
					56 => array('min' => 326, 'max' => 329), 57 => array('min' => 330, 'max' => 334), 58 => array('min' => 335, 'max' => 338),
					59 => array('min' => 339, 'max' => 343), 60 => array('min' => 344, 'max' => 348), 61 => array('min' => 349, 'max' => 352),
					62 => array('min' => 353, 'max' => 357), 63 => array('min' => 358, 'max' => 361), 64 => array('min' => 362, 'max' => 366),
					65 => array('min' => 367, 'max' => 371), 66 => array('min' => 372, 'max' => 375), 67 => array('min' => 376, 'max' => 380),
					68 => array('min' => 381, 'max' => 384), 69 => array('min' => 385, 'max' => 389), 70 => array('min' => 390, 'max' => 393),
					71 => array('min' => 394, 'max' => 398), 72 => array('min' => 399, 'max' => 403), 73 => array('min' => 404, 'max' => 407),
					74 => array('min' => 408, 'max' => 412), 75 => array('min' => 413, 'max' => 416), 76 => array('min' => 417, 'max' => 421),
					77 => array('min' => 422, 'max' => 425), 78 => array('min' => 426, 'max' => 430), 79 => array('min' => 431, 'max' => 435),
					80 => array('min' => 436, 'max' => 439), 81 => array('min' => 440, 'max' => 444), 82 => array('min' => 445, 'max' => 448),
					83 => array('min' => 449, 'max' => 453), 84 => array('min' => 454, 'max' => 458), 85 => array('min' => 459, 'max' => 462),
					86 => array('min' => 463, 'max' => 467), 87 => array('min' => 468, 'max' => 471), 88 => array('min' => 472, 'max' => 476),
					89 => array('min' => 477, 'max' => 480), 90 => array('min' => 481, 'max' => 485), 91 => array('min' => 486, 'max' => 490),
					92 => array('min' => 491, 'max' => 494), 93 => array('min' => 495, 'max' => 499), 94 => array('min' => 500, 'max' => 503),
					95 => array('min' => 504, 'max' => 508), 96 => array('min' => 509, 'max' => 513), 97 => array('min' => 514, 'max' => 517),
					98 => array('min' => 518, 'max' => 522), 99 => array('min' => 523, 'max' => 526), 100 => array('min' => 527, 'max' => 531),
					101 => array('min' => 532, 'max' => 535), 102 => array('min' => 536, 'max' => 540), 103 => array('min' => 541, 'max' => 545),
					104 => array('min' => 546, 'max' => 549)
					)
			);

	
	}
	else
	{
		## These are 12-14
		$tvalue = array(
			'ats' => array(
					0 => 35, 1 => 37, 2 => 39, 3 => 41, 4 => 43, 5 => 45,
					6 => 48, 7 => 50, 8 => 52, 9 => 54, 10 => 56,
					11 => 58, 12 => 61, 13 => 63, 14 => 65, 15 => 67,
					16 => 69, 18 => 73, 19 => 76
				),
			'att' => array(
					0 => 36, 1 => 38, 2 => 39, 3 => 41, 4 => 43, 5 => 45,
					6 => 47, 7 => 49, 8 => 51, 9 => 52, 10 => 54,
					11 => 56, 12 => 58, 13 => 60, 14 => 62, 15 => 64,
					16 => 66, 17 => 67, 18 => 69, 19 => 71, 20 => 73,
					21 => 75, 22 => 77, 23 => 79, 24 => 80, 25 => 82
				),
			'ssk' => array(
					0 => 23, 1 => 26, 2 => 28, 3 => 30, 4 => 32, 5 => 34,
					6 => 37, 7 => 39, 8 => 41, 9 => 43, 10 => 45, 
					11 => 48, 12 => 50, 13 => 52, 14 => 54, 15 => 56,
					16 => 59, 17 => 61, 18 => 63, 19 => 65, 20 => 68, 
					21 => 70, 22 => 72, 23 => 74, 24 => 76
				),
			'ata' => array(
					0 => 41, 1 => 43, 2 => 45, 3 => 48, 4 => 50, 5 => 52,
					6 => 54, 7 => 57, 8 => 59, 9 => 61, 10 => 64,
					11 => 66, 12 => 68, 13 => 70, 14 => 73, 15 => 75,
					16 => 77, 17 => 79, 18 => 82, 19 => 84, 20 => 86,
					21 => 89, 22 => 91, 23 => 93, 24 => 95, 25 => 98,
					26 => 100
				),
			'loc' => array(
					0 => 36, 1 => 38, 2 => 40, 3 => 42, 4 => 44, 5 => 46,
					6 => 48, 7 => 50, 8 => 52, 9 => 54, 10 => 56,
					11 => 58, 12 => 60, 13 => 62, 14 => 64, 15 => 66,
					16 => 68, 17 => 70, 18 => 72, 19 => 74, 20 => 76,
					21 => 78, 22 => 80
				),
			'sos' => array(
					0 => 35, 1 => 37, 2 => 39, 3 => 41, 4 => 43, 5 => 45,
					6 => 47, 7 => 49, 8 => 51, 9 => 53, 10 => 55,
					11 => 56, 12 => 58, 13 => 60, 14 => 62, 15 => 64,
					16 => 66, 17 => 68, 18 => 70, 19 => 72, 20 => 74,
					21 => 76, 22 => 78, 23 => 80, 24 => 82, 25 => 84,
					26 => 86, 27 => 88, 28 => 90
				),
			'anx' => array(
					0 => 33, 1 => 35, 2 => 36, 3 => 38, 4 => 39, 5 => 41,
					6 => 42, 7 => 44, 8 => 45, 9 => 47, 10 => 48,
					11 => 50, 12 => 51, 13 => 53, 14 => 54, 15 => 56,
					16 => 57, 17 => 59, 18 => 60, 19 => 62, 20 => 64,
					21 => 65, 22 => 67, 23 => 68, 24 => 70, 25 => 71,
					26 => 73, 27 => 74, 28 => 76, 29 => 77, 30 => 79,
					31 => 80, 32 => 82, 33 => 83, 34 => 85, 35 => 86
				),
			'dep' => array(
					0 => 40, 1 => 41, 2 => 43, 3 => 45, 4 => 46, 5 => 48,
					6 => 50, 7 => 51, 8 => 53, 9 => 55, 10 => 56,
					11 => 58, 12 => 60, 13 => 61, 14 => 63, 15 => 65,
					16 => 66, 17 => 68, 18 => 70, 19 => 71, 20 => 73,
					21 => 75, 22 => 76, 23 => 78, 24 => 80, 25 => 81,
					26 => 83, 27 => 85, 28 => 86
				),
			'soi' => array(
					0 => 36, 1 => 38, 2 => 40, 3 => 42, 4 => 44, 5 => 46,
					6 => 48, 7 => 50, 8 => 52, 9 => 54, 10 => 56,
					11 => 58, 12 => 60, 13 => 62, 14 => 64, 15 => 66,
					16 => 69, 17 => 71, 18 => 73, 19 => 75, 20 => 77, 
					21 => 79, 22 => 81, 23 => 83, 24 => 85, 25 => 87,
					26 => 89
				),
			'som' => array(
					0 => 40, 1 => 43, 2 => 46, 3 => 49, 4 => 52, 5 => 56,
					6 => 59, 7 => 62, 8 => 65, 9 => 68, 10 => 71,
					11 => 74, 12 => 77, 13 => 80, 14 => 84, 15 => 87
				),
			'apr' => array(
					0 => 34, 1 => 35, 2 => 38, 3 => 41, 4 => 43, 5 => 45,
					6 => 47, 7 => 49, 8 => 51, 9 => 54, 10 => 56,
					11 => 58, 12 => 60, 13 => 62, 14 => 65, 15 => 67,
					16 => 69, 17 => 71, 18 => 73, 19 => 76, 20 => 78, 
					21 => 80, 22 => 82, 23 => 84
				),
			'hyp' => array(
					0 => 33, 1 => 36, 2 => 38, 3 => 41, 4 => 44, 5 => 46,
					6 => 49, 7 => 52, 8 => 54, 9 => 57, 10 => 60,
					11 => 62, 12 => 65, 13 => 68, 14 => 70, 15 => 73,
					16 => 76, 17 => 78, 18 => 81, 19 => 83, 20 => 86,
					21 => 89
				),
			'rwp' => array(
					0 => 19, 1 => 20, 2 => 22, 3 => 23, 4 => 25, 5 => 27,
					6 => 28, 7 => 30, 8 => 31, 9 => 33, 10 => 34,
					11 => 36, 12 => 38, 13 => 39, 14 => 41, 15 => 42,
					16 => 44, 17 => 46, 18 => 47, 19 => 49, 20 => 50,
					21 => 52, 22 => 54, 23 => 54, 24 => 57, 25 => 58,
					26 => 60, 27 => 62, 28 => 63, 29 => 65
				),
			'ipr' => array(
					0 => 10, 1 => 11, 2 => 14, 3 => 17, 4 => 20, 5 => 23,
					6 => 25, 7 => 28, 8 => 31, 9 => 34, 10 => 37,
					11 => 39, 12 => 42, 13 => 45, 14 => 48, 15 => 51,
					16 => 53, 17 => 56, 18 => 59, 19 => 62
				),
			'sfe' => array(
					0 => 16, 1 => 18, 2 => 20, 3 => 23, 4 => 25, 5 => 27,
					6 => 30, 7 => 32, 8 => 34, 9 => 36, 10 => 39,
					11 => 41, 12 => 43, 13 => 45, 14 => 48, 15 => 50,
					16 => 52, 17 => 55, 18 => 57, 19 => 59, 20 => 61
				),
			'sfr' => array(
					0 => 14, 1 => 17, 2 => 19, 3 => 22, 4 => 24, 5 => 27,
					6 => 30, 7 => 32, 8 => 35, 9 => 37, 10 => 40,
					11 => 43, 12 => 45, 13 => 48, 14 => 50, 15 => 53, 
					16 => 56, 17 => 58, 18 => 61, 19 => 63, 20 => 66,
					21 => 69, 22 => 71
				)
			);

$tvalue_perc = array(
			'ats' => array(
					0 => 1, 1 => 4, 2 =>12, 3 => 22, 4 => 31, 5 => 40,
					6 => 48, 7 => 56, 8 => 63, 9 => 69, 10 => 74,
					11 => 79, 12 => 83, 13 => 87, 14 => 90, 15 => 93,
					16 => 95, 17 => 97, 18 => 98, 19 => 99
				),
			'att' => array(
					0 => 2, 1 => 7, 2 => 13, 3 => 21, 4 => 29, 5 => 37,
					6 => 45, 7 => 52, 8 => 59, 9 => 65, 10 => 70,
					11 => 75, 12 => 79, 13 => 83, 14 => 86, 15 => 89, 
					16 => 91, 17 => 93, 18 => 95, 19 => 96, 20 => 97,
					21 => 98, 22 => 99, 23 => 99, 24 => 99, 25 => 99
				),
			'ssk' => array(
					0 => 1, 1 => 1, 2 => 1, 3 => 2, 4 => 3, 5 => 6,
					6 => 9, 7 => 14, 8 => 20, 9 => 26, 10 => 34,
					11 => 42, 12 => 50, 13 => 58, 14 => 66, 15 => 73,
					16 => 80, 17 => 85, 18 => 90, 19 => 93, 20 => 96,
					21 => 98, 22 => 99, 23 => 99, 24 => 99
				),
			'ata' => array(
					0 => 12, 1 => 28, 2 => 42, 3 => 53, 4 => 62, 5 => 69,
					6 => 75, 7 => 80, 8 => 84, 9 => 87, 10 => 90,
					11 => 92, 12 => 93, 13 => 95, 14 => 96, 15 => 97,
					16 => 98, 17 => 98, 18 => 99, 19 => 99, 20 => 99,
					21 => 99, 22 => 99, 23 => 99, 24 => 99, 25 => 99, 
					26 => 99
				),
			'loc' => array(
					0 => 2, 1 => 9, 2 => 17, 3 => 26, 4 => 35, 5 => 43,
					6 => 50, 7 => 58, 8 => 64, 9 => 70, 10 => 75,
					11 => 79, 12 => 83, 13 => 87, 14 => 89, 15 => 92,
					16 => 94, 17 => 96, 18 => 97, 19 => 98, 20 => 99,
					21 => 99, 22 => 99
				),
			'sos' => array(
					0 => 3, 1 => 7, 2 => 12, 3 => 19, 4 => 27, 5 => 34,
					6 => 42, 7 => 50, 8 => 58, 9 => 64, 10 => 70,
					11 => 76, 12 => 80, 13 => 84, 14 => 88, 15 => 91,
					16 => 93, 17 => 95, 18 => 96, 19 => 97, 20 => 98,
					21 => 99, 22 => 99, 23 => 99, 24 => 99, 25 => 99,
					26 => 99, 27 => 99, 28 => 99
				),
			'anx' => array(
					0 => 1, 1 => 3, 2 => 4, 3 => 9, 4 => 14, 5 => 19,
					6 => 24, 7 => 30, 8 => 37, 9 => 43, 10 => 49,
					11 => 55, 12 => 60, 13 => 65, 14 => 70, 15 => 74,
					16 => 78, 17 => 81, 18 => 84, 19 => 87, 20 => 89,
					21 => 91, 22 => 93, 23 => 95, 24 => 96, 25 => 97,
					26 => 98, 27 => 98, 28 => 99, 29 => 99, 30 => 99,
					31 => 99, 32 => 99, 33 => 99, 34 => 99, 35 => 99

				),
			'dep' => array(
					0 => 1, 1 => 15, 2 => 32, 3 => 42, 4 => 51, 5 => 58,
					6 => 63, 7 => 68, 8 => 72, 9 => 76, 10 => 79,
					11 => 81, 12 => 84, 13 => 86, 14 => 88, 15 => 89,
					16 => 91, 17 => 92, 18 => 93, 19 => 94, 20 => 95,
					21 => 96, 22 => 97, 23 => 98, 24 => 98, 25 => 99,
					26 => 99, 27 => 99, 28 => 99
				),
			'soi' => array(
					0 => 1, 1 => 6, 2 => 13, 3 => 22, 4 => 32, 5 => 41,
					6 => 50, 7 => 58, 8 => 65, 9 => 71, 10 => 76,
					11 => 81, 12 => 85, 13 => 88, 14 => 91, 15 => 93,
					16 => 94, 17 => 96, 18 => 97, 19 => 98, 20 => 98,
					21 => 99, 22 => 99, 23 => 99, 24 => 99, 25 => 99,
					26 => 99
				),
			'som' => array(
					0 => 13, 1 => 31, 2 => 46, 3 => 57, 4 => 67, 5 => 75,
					6 => 81, 7 => 86, 8 => 90, 9 => 93, 10 => 95, 
					11 => 97, 12 => 99, 13 => 99, 14 => 99, 15 => 99
				),
			'apr' => array(
					0 => 2, 1 => 5, 2 => 11, 3 => 19, 4 => 27, 5 => 35,
					6 => 44, 7 => 52, 8 => 60, 9 => 67, 10 => 73,
					11 => 79, 12 => 83, 13 => 87, 14 => 91, 15 => 93,
					16 => 95, 17 => 97, 18 => 98, 19 => 99, 20 => 99,
					21 => 99, 22 => 99, 23 => 99
				),
			'hyp' => array(
					0 => 1, 1 => 5, 2 => 10, 3 => 18, 4 => 29, 5 => 40,
					6 => 51, 7 => 61, 8 => 70, 9 => 78, 10 => 84,
					11 => 88, 12 => 92, 13 => 94, 14 => 96, 15 => 97,
					16 => 98, 17 => 99, 18 => 99, 19 => 99, 20 => 99,
					21 => 99
				),
			'rwp' => array(
					0 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 2,
					6 => 3, 7 => 4, 8 => 5, 9 => 7, 10 => 9,
					11 => 11, 12 => 13, 13 => 16, 14 => 19, 15 => 22,
					16 => 26, 17 => 31, 18 => 35, 19 => 40, 20 => 46,
					21 => 52, 22 => 58, 23 => 64, 24 => 71, 25 => 77,
					26 => 83, 27 => 89, 28 => 94, 29 => 98
				),
			'ipr' => array(
					0 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 2,
					6 => 3, 7 => 4, 8 => 6, 9 => 8, 10 => 11,
					11 => 15, 12 => 20, 13 => 26, 14 => 33, 15 => 43,
					16 => 54, 17 => 68, 18 => 83, 19 => 96
				),
			'sfe' => array(
					0 => 1, 1 => 1, 2 => 1, 3 => 2, 4 => 3, 5 => 4,
					6 => 5, 7 => 7, 8 => 9, 9 => 11, 10 => 14,
					11 => 17, 12 => 21, 13 => 26, 14 => 32, 15 => 39,
					16 => 47, 17 => 57, 18 => 70, 19 => 84, 20 => 98
				),
			'sfr' => array(
					0 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 2,
					6 => 3, 7 => 5, 8 => 8, 9 => 12, 10 => 17,
					11 => 23, 12 => 31, 13 => 39, 14 => 49, 15 => 59,
					16 => 69, 17 => 78, 18 => 86, 19 => 92, 20 => 96,
					21 => 99, 22 => 99
				)
			);


$composite_comb_tvalues = array(
				'sprob' => array( 
					26 => array('min' => 94, 'max' => 94), 27 => array('min' => 95, 'max' => 97), 28 => array('min' => 98, 'max' => 99),
					29 => array('min' => 100, 'max' => 102), 30 => array('min' => 103, 'max' => 104), 31 => array('min' => 105, 'max' => 106),
					32 => array('min' => 107, 'max' => 109), 33 => array('min' => 110, 'max' => 111), 34 => array('min' => 112, 'max' => 113),
					35 => array('min' => 114, 'max' => 116), 36 => array('min' => 117, 'max' => 118), 37 => array('min' => 119, 'max' => 120),
					38 => array('min' => 121, 'max' => 123), 39 => array('min' => 124, 'max' => 125), 40 => array('min' => 126, 'max' => 127),
					41 => array('min' => 128, 'max' => 130), 42 => array('min' => 131, 'max' => 132), 43 => array('min' => 133, 'max' => 134),
					44 => array('min' => 135, 'max' => 137), 45 => array('min' => 138, 'max' => 139), 46 => array('min' => 140, 'max' => 141),
					47 => array('min' => 142, 'max' => 144), 48 => array('min' => 145, 'max' => 146), 49 => array('min' => 147, 'max' => 148), 
					50 => array('min' => 149, 'max' => 151), 51 => array('min' => 152, 'max' => 153), 52 => array('min' => 154, 'max' => 155),
					53 => array('min' => 156, 'max' => 158), 54 => array('min' => 159, 'max' => 160), 55 => array('min' => 161, 'max' => 162),
					56 => array('min' => 163, 'max' => 165), 57 => array('min' => 166, 'max' => 167), 58 => array('min' => 168, 'max' => 169),
					59 => array('min' => 170, 'max' => 172), 60 => array('min' => 173, 'max' => 174), 61 => array('min' => 175, 'max' => 176),
					62 => array('min' => 177, 'max' => 179), 63 => array('min' => 180, 'max' => 181), 64 => array('min' => 182, 'max' => 183),
					65 => array('min' => 184, 'max' => 186), 66 => array('min' => 187, 'max' => 188), 67 => array('min' => 189, 'max' => 190),
					68 => array('min' => 191, 'max' => 193), 69 => array('min' => 194, 'max' => 195), 70 => array('min' => 196, 'max' => 197),
					71 => array('min' => 198, 'max' => 200), 72 => array('min' => 201, 'max' => 202), 73 => array('min' => 203, 'max' => 204),
					74 => array('min' => 205, 'max' => 207), 75 => array('min' => 208, 'max' => 209), 76 => array('min' => 210, 'max' => 212),
					77 => array('min' => 213, 'max' => 214), 78 => array('min' => 215, 'max' => 216), 79 => array('min' => 217, 'max' => 219),
					80 => array('min' => 220, 'max' => 221), 81 => array('min' => 222, 'max' => 223), 82 => array('min' => 224, 'max' => 226),
					83 => array('min' => 227, 'max' => 228), 84 => array('min' => 229, 'max' => 230), 85 => array('min' => 231, 'max' => 233), 
					86 => array('min' => 234, 'max' => 234)
					),
				'intprob' => array(
					34 => array('min' => 261, 'max' => 264), 35 => array('min' => 265, 'max' => 269), 36 => array('min' => 270, 'max' => 275),
					37 => array('min' => 276, 'max' => 280), 38 => array('min' => 281, 'max' => 286), 39 => array('min' => 287, 'max' => 291),
					40 => array('min' => 292, 'max' => 297), 41 => array('min' => 298, 'max' => 302), 42 => array('min' => 303, 'max' => 308),
					43 => array('min' => 309, 'max' => 313), 44 => array('min' => 314, 'max' => 319), 45 => array('min' => 320, 'max' => 325),
					46 => array('min' => 326, 'max' => 330), 47 => array('min' => 331, 'max' => 336), 48 => array('min' => 337, 'max' => 341),
					49 => array('min' => 342, 'max' => 347), 50 => array('min' => 348, 'max' => 352), 51 => array('min' => 353, 'max' => 358),
					52 => array('min' => 359, 'max' => 363), 53 => array('min' => 364, 'max' => 369), 54 => array('min' => 370, 'max' => 374),
					55 => array('min' => 375, 'max' => 380), 56 => array('min' => 381, 'max' => 386), 57 => array('min' => 387, 'max' => 391),
					58 => array('min' => 392, 'max' => 397), 59 => array('min' => 398, 'max' => 402), 60 => array('min' => 403, 'max' => 408),
					61 => array('min' => 409, 'max' => 413), 62 => array('min' => 414, 'max' => 419), 63 => array('min' => 420, 'max' => 424),
					64 => array('min' => 425, 'max' => 430), 65 => array('min' => 431, 'max' => 435), 66 => array('min' => 436, 'max' => 441),
					67 => array('min' => 442, 'max' => 447), 68 => array('min' => 448, 'max' => 452), 69 => array('min' => 453, 'max' => 458),
					70 => array('min' => 459, 'max' => 464), 71 => array('min' => 465, 'max' => 469), 72 => array('min' => 470, 'max' => 474),
					73 => array('min' => 475, 'max' => 480), 74 => array('min' => 481, 'max' => 485), 75 => array('min' => 486, 'max' => 491),
					76 => array('min' => 492, 'max' => 496), 77 => array('min' => 497, 'max' => 502), 78 => array('min' => 503, 'max' => 508),
					79 => array('min' => 509, 'max' => 513), 80 => array('min' => 514, 'max' => 519), 81 => array('min' => 520, 'max' => 524),
					82 => array('min' => 525, 'max' => 530), 83 => array('min' => 531, 'max' => 535), 84 => array('min' => 536, 'max' => 541),
					85 => array('min' => 542, 'max' => 546), 86 => array('min' => 547, 'max' => 552), 87 => array('min' => 553, 'max' => 557),
					88 => array('min' => 558, 'max' => 563), 89 => array('min' => 564, 'max' => 569), 90 => array('min' => 570, 'max' => 574),
					91 => array('min' => 575, 'max' => 580), 92 => array('min' => 581, 'max' => 585), 93 => array('min' => 586, 'max' => 591),
					94 => array('min' => 592, 'max' => 596), 95 => array('min' => 597, 'max' => 602), 96 => array('min' => 603, 'max' => 607),
					97 => array('min' => 608, 'max' => 613), 98 => array('min' => 614, 'max' => 618)

					),
				'inthyp' => array(
					31 => array('min' => 67, 'max' => 67), 32 => array('min' => 68, 'max' => 69), 33 => array('min' => 70, 'max' => 70),
					34 => array('min' => 71, 'max' => 72), 35 => array('min' => 73, 'max' => 74), 36 => array('min' => 75, 'max' => 76),
					37 => array('min' => 77, 'max' => 78), 38 => array('min' => 79, 'max' => 79), 39 => array('min' => 80, 'max' => 81),
					40 => array('min' => 82, 'max' => 83), 41 => array('min' => 84, 'max' => 85), 42 => array('min' => 86, 'max' => 86), 
					43 => array('min' => 87, 'max' => 88), 44 => array('min' => 89, 'max' => 90), 45 => array('min' => 91, 'max' => 92),
					46 => array('min' => 93, 'max' => 93), 47 => array('min' => 94, 'max' => 95), 48 => array('min' => 96, 'max' => 97),
					49 => array('min' => 98, 'max' => 99), 50 => array('min' => 100, 'max' => 100), 51 => array('min' => 101, 'max' => 102),
					52 => array('min' => 103, 'max' => 104), 53 => array('min' => 105, 'max' => 106), 54 => array('min' => 107, 'max' => 107),
					55 => array('min' => 108, 'max' => 109), 56 => array('min' => 110, 'max' => 111), 57 => array('min' => 112, 'max' => 113),
					58 => array('min' => 114, 'max' => 114), 59 => array('min' => 115, 'max' => 116), 60 => array('min' => 117, 'max' => 118),
					61 => array('min' => 119, 'max' => 120), 62 => array('min' => 121, 'max' => 121), 63 => array('min' => 122, 'max' => 123),
					64 => array('min' => 124, 'max' => 125), 65 => array('min' => 126, 'max' => 127), 66 => array('min' => 128, 'max' => 129),
					67 => array('min' => 130, 'max' => 130), 68 => array('min' => 131, 'max' => 132), 69 => array('min' => 133, 'max' => 134),
					70 => array('min' => 135, 'max' => 136), 71 => array('min' => 137, 'max' => 137), 72 => array('min' => 138, 'max' => 139),
					73 => array('min' => 140, 'max' => 141), 74 => array('min' => 142, 'max' => 143), 75 => array('min' => 144, 'max' => 144),
					76 => array('min' => 145, 'max' => 146), 77 => array('min' => 147, 'max' => 148), 78 => array('min' => 149, 'max' => 150),
					79 => array('min' => 151, 'max' => 151), 80 => array('min' => 152, 'max' => 153), 81 => array('min' => 154, 'max' => 155),
					82 => array('min' => 156, 'max' => 157), 83 => array('min' => 158, 'max' => 158), 84 => array('min' => 159, 'max' => 160),
					85 => array('min' => 161, 'max' => 162), 86 => array('min' => 163, 'max' => 164), 87 => array('min' => 165, 'max' => 165), 
					88 => array('min' => 166, 'max' => 167), 89 => array('min' => 168, 'max' => 169), 90 => array('min' => 170, 'max' => 171),
					91 => array('min' => 172, 'max' => 172), 92 => array('min' => 173, 'max' => 173)
					),
				'peradj' => array(
					10 => array('min' => 59, 'max' => 81), 11 => array('min' => 82, 'max' => 84), 12 => array('min' => 85, 'max' => 87),
					13 => array('min' => 88, 'max' => 90), 14 => array('min' => 91, 'max' => 93), 15 => array('min' => 94, 'max' => 96),
					16 => array('min' => 97, 'max' => 99), 17 => array('min' => 100, 'max' => 102), 18 => array('min' => 103, 'max' => 105),
					19 => array('min' => 106, 'max' => 108), 20 => array('min' => 109, 'max' => 111), 21 => array('min' => 112, 'max' => 114),
					22 => array('min' => 115, 'max' => 117), 23 => array('min' => 118, 'max' => 120), 24 => array('min' => 121, 'max' => 123), 
					25 => array('min' => 124, 'max' => 126), 26 => array('min' => 127, 'max' => 129), 27 => array('min' => 130, 'max' => 132),
					28 => array('min' => 133, 'max' => 135), 29 => array('min' => 136, 'max' => 138), 30 => array('min' => 139, 'max' => 141),
					31 => array('min' => 142, 'max' => 144), 32 => array('min' => 145, 'max' => 147), 33 => array('min' => 148, 'max' => 150), 
					34 => array('min' => 151, 'max' => 153), 35 => array('min' => 154, 'max' => 156), 36 => array('min' => 157, 'max' => 159),
					37 => array('min' => 160, 'max' => 162), 38 => array('min' => 163, 'max' => 165), 39 => array('min' => 166, 'max' => 168),
					40 => array('min' => 169, 'max' => 171), 41 => array('min' => 172, 'max' => 174), 42 => array('min' => 175, 'max' => 177),
					43 => array('min' => 178, 'max' => 180), 44 => array('min' => 181, 'max' => 183), 45 => array('min' => 184, 'max' => 186),
					46 => array('min' => 187, 'max' => 189), 47 => array('min' => 190, 'max' => 192), 48 => array('min' => 193, 'max' => 195),
					49 => array('min' => 196, 'max' => 198), 50 => array('min' => 199, 'max' => 201), 51 => array('min' => 202, 'max' => 204),
					52 => array('min' => 205, 'max' => 207), 53 => array('min' => 208, 'max' => 210), 54 => array('min' => 211, 'max' => 213),
					55 => array('min' => 214, 'max' => 216), 56 => array('min' => 217, 'max' => 219), 57 => array('min' => 220, 'max' => 222),
					58 => array('min' => 223, 'max' => 225), 59 => array('min' => 226, 'max' => 228), 60 => array('min' => 228, 'max' => 231),
					61 => array('min' => 232, 'max' => 234), 62 => array('min' => 235, 'max' => 237), 63 => array('min' => 238, 'max' => 240),
					64 => array('min' => 241, 'max' => 243), 65 => array('min' => 244, 'max' => 246), 66 => array('min' => 247, 'max' => 249),
					67 => array('min' => 250, 'max' => 252), 68 => array('min' => 253, 'max' => 255), 69 => array('min' => 256, 'max' => 258),
					70 => array('min' => 259, 'max' => 259)
					),
				'emosymp' => array(
					23 => array('min' => 174, 'max' => 178), 24 => array('min' => 179, 'max' => 182), 25 => array('min' => 183, 'max' => 187),
					26 => array('min' => 288, 'max' => 191), 27 => array('min' => 192, 'max' => 196), 28 => array('min' => 197, 'max' => 201),
					29 => array('min' => 202, 'max' => 205), 30 => array('min' => 206, 'max' => 210), 31 => array('min' => 211, 'max' => 214),
					32 => array('min' => 215, 'max' => 219), 33 => array('min' => 220, 'max' => 224), 34 => array('min' => 225, 'max' => 228),
					35 => array('min' => 229, 'max' => 233), 36 => array('min' => 234, 'max' => 237), 37 => array('min' => 238, 'max' => 242),
					38 => array('min' => 243, 'max' => 247), 39 => array('min' => 248, 'max' => 251), 40 => array('min' => 252, 'max' => 256),
					41 => array('min' => 257, 'max' => 260), 42 => array('min' => 261, 'max' => 265), 43 => array('min' => 266, 'max' => 270), 
					44 => array('min' => 271, 'max' => 274), 45 => array('min' => 275, 'max' => 279), 46 => array('min' => 280, 'max' => 283),
					47 => array('min' => 284, 'max' => 288), 48 => array('min' => 289, 'max' => 293), 49 => array('min' => 294, 'max' => 297),
					50 => array('min' => 298, 'max' => 302), 51 => array('min' => 303, 'max' => 306), 52 => array('min' => 307, 'max' => 311),
					53 => array('min' => 312, 'max' => 316), 54 => array('min' => 317, 'max' => 320), 55 => array('min' => 321, 'max' => 325),
					56 => array('min' => 326, 'max' => 329), 57 => array('min' => 330, 'max' => 334), 58 => array('min' => 335, 'max' => 338),
					59 => array('min' => 339, 'max' => 343), 60 => array('min' => 344, 'max' => 347), 61 => array('min' => 348, 'max' => 352),
					62 => array('min' => 353, 'max' => 357), 63 => array('min' => 358, 'max' => 362), 64 => array('min' => 363, 'max' => 366),
					65 => array('min' => 367, 'max' => 371), 66 => array('min' => 372, 'max' => 375), 67 => array('min' => 376, 'max' => 380), 
					68 => array('min' => 381, 'max' => 385), 69 => array('min' => 386, 'max' => 389), 70 => array('min' => 390, 'max' => 394),
					71 => array('min' => 395, 'max' => 398), 72 => array('min' => 399, 'max' => 403), 73 => array('min' => 404, 'max' => 408), 
					74 => array('min' => 409, 'max' => 412), 75 => array('min' => 413, 'max' => 417), 76 => array('min' => 418, 'max' => 421),
					77 => array('min' => 422, 'max' => 426), 78 => array('min' => 427, 'max' => 431), 79 => array('min' => 432, 'max' => 435),
					80 => array('min' => 436, 'max' => 440), 81 => array('min' => 441, 'max' => 444), 82 => array('min' => 445, 'max' => 449),
					83 => array('min' => 450, 'max' => 454), 84 => array('min' => 455, 'max' => 458), 85 => array('min' => 459, 'max' => 463),
					86 => array('min' => 464, 'max' => 467), 87 => array('min' => 468, 'max' => 472), 88 => array('min' => 273, 'max' => 477),
					89 => array('min' => 478, 'max' => 481), 90 => array('min' => 482, 'max' => 486), 91 => array('min' => 487, 'max' => 490),
					92 => array('min' => 491, 'max' => 495), 93 => array('min' => 496, 'max' => 500), 94 => array('min' => 501, 'max' => 504),
					95 => array('min' => 505, 'max' => 509), 96 => array('min' => 510, 'max' => 513), 97 => array('min' => 514, 'max' => 518),
					98 => array('min' => 519, 'max' => 521)
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
	$q_answered = 176;

	foreach ($source_names as $i => $result_name) {
		$raw_score_val = $raw_scores[$result_name];
		#$this->module->emDebug($raw_score_val . " " . $result_name);
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
			if ($result_name == 'hyp')
			{
#				$this->module->emDebug($raw_score_val . "HYP w T " . $tvalue_scores[$result_name . '_tval']);
			}
			#$this->module->emDebug($result_name . " had a raw score of " . $raw_score_val . " and a tscore of " . lookup_tvalue($tvalue[$result_name], $raw_score_val));
			$tvalue_percent[$result_name . '_tvalp'] = lookup_tvalue($tvalue_perc[$result_name], $raw_score_val);
		} else {
			$tvalue_scores[$result_name . '_tval'] = NULL;
			$tvalue_percent[$result_name . '_tvalp'] = NULL;

		}
	}

	foreach ($source_names as $i => $result_name) {
		$this->module->emDebug($result_name . ": " . $tvalue_scores[$result_name . '_tval']);
	}


	# Now calculate the composite scores
	# These are the lookup tables for the composite score t values
	$composite_names = array('emosymp', 'inthyp', 'intprob', 'peradj', 'sprob');
	$composite_calc = array(
			'sprob' => array('ats', 'att', 'ssk'),
			'intprob' => array('ata', 'loc', 'sos', 'anx', 'dep', 'soi', 'som'),
			'inthyp' => array('apr', 'hyp'),
			'peradj' => array('rwp', 'ipr', 'sfe', 'sfr'),
			'emosymp' => array('sos', 'anx', 'dep', 'soi', 'sfe', 'sfr')
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
//			if ($field_names == 'emosymp')
//			{
//				if ($subscale == 'sfe' or $subscale == 'sfr')
//				{
//					$result += 100-$tvalue_scores[$name];
//				}
//				else
//				{
//					$result += $tvalue_scores[$name];
//				}
//			}
//			else
//			{
				$result += $tvalue_scores[$name];
//			}
		}

		$result_tval = lookup_composite_tvalue($composite_comb_tvalues[$field_names], $result); 
		$composite_raw[$field_names . '_comp'] = $result;
//		if ($field_names != 'emosymp')
//		{
			$composite_tval[$field_names . '_comp_tval'] = $result_tval;
//		}
		if ($field_names == 'emosymp') {
			$num_items = count($composite_calc[$field_names]);
			$composite_mean[$field_names . '_imean'] = 100-$result/$num_items;
		}
	}

	### INDEXES ###

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


		# L_index result
		$L_interpret = 1;
		if ($L_index >= 9 and $L_index <= 11)
		{
			$L_interpret = 2;
		}
		else if ($L_index >= 12 and $L_index <= 15)
		{
			$L_interpret = 3;
		}
		$L_index_result = array($L_index, $L_interpret);

		#V_Index result
		$V_interpret = 1;
		if ($v_index == 3)
		{
			$V_interpret = 2;
		}
		else if ($v_index >= 4 and $v_index <= 12)
		{
			$V_interpret = 3;
		}
		$V_index_result = array($v_index, $V_interpret);

		#Consistency Index result
		$cons_interpret = 1;
		if ($cons_index >= 15 and $cons_index <= 20)
		{
			$cons_interpret = 2;
		}
		else if ($cons_index >= 21)
		{
			$cons_interpret = 3;
		}
		$cons_index_result = array($cons_index, $cons_interpret);


		# Response Pattern result
		$resp_pattern_interpret = 0;
		if ($resp_pattern >= 100 and $resp_pattern <= 129)
		{
			$resp_pattern_interpret = 1;
		}
		else if ($resp_pattern >= 130 and $resp_pattern <= 185)
		{
			$resp_pattern_interpret = 2;
		}
		$resp_pattern_result = array($resp_pattern, $resp_pattern_interpret);

		#Overall Statistics result

		# AllVal
		$allval = 0;
		if ($q_answered == 176)
		{
			$allval = 1;
		}



		#90Val
		$val_90 = 0;
		$limit = 0.9*176;

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

$forty = array('rwp', 'ipr', 'sfe', 'sfr');
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
			if ($field_names == 'emosymp') continue;
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
	foreach ($raw_score_totals as $i => $result_name) {
		$this->module->emDebug($i . ":" . $raw_score_totals[$i]);
	}

	### DEFINE RESULTS ###
	# REQUIRED: The algorithm_results variable MUST BE USED as it is relied upon from the parent script.
	$totals =  array_merge($raw_score_totals, $null_counter, $tvalue_scores, $tvalue_percent, $composite_raw, $composite_tval, $composite_mean, $completeness_interpret, $F_index_result, $V_index_result, $L_index_result, $cons_index_result, $resp_pattern_result, $stat_result, $sig_vals);
	$this->module->emDebug("\nRaw total: " . count($raw_score_totals) . "\nNull Counter: " . count($null_counter) . "\nTvalue: " . count($tvalue_scores) . "\n Tvalp: " . count($tvalue_percent) . "\nComp Raw: " . count($composite_raw) . "\nComp Tval: " . count($composite_tval) . "\nComp Mean: " . count($composite_mean) . "\nComplete: " . count($completeness_interpret) . "\nF: " . count($F_index_result) . "\nV: " . count($V_index_result) . "\nL: " . count($L_index_result) . "\nCons: " . count($cons_index_result) . "\nResp: " . count($resp_pattern_result) . "\nStats: " . count($stat_result));
	$algorithm_results = array_combine($default_result_fields, $totals);

	foreach ($algorithm_results as $i => $result_name) {
		$this->module->emDebug($i . ":" . $algorithm_results[$i]);
	}

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
