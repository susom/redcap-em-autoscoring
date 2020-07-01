<?php
/**
	BASC2 - Parent Rating Scales - Children

	A REDCAP AutoScoring Algorithm File

  Developed by Kim Wijaya and Alex Basile for ELSPAP June 2016
  Uses General Combined Sex Scales Ages 6-7 and 8-11
  See BASC2 Manual for Scale Details  

    - There exists an array called $src that contains the data from the source project
    - There can exist an optional array called $manual_result_fields that can override the default_result_fields
    - The final results should be presented in an array called $algorithm_results
        - The answers are categorized as:
              0 = Never
              1 = Sometimes
              2 = Often
              3 = Almost Always
        - There are 160 questions that the participant fills out.
**/
$algorithm_summary = "BASC2, Parent Rating Scales - Children";
$this->module->emDebug("Scoring Title: " . $algorithm_summary);

$this->module->emDebug("Using PRS-C");

$default_result_fields_orig = array(
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
    'prsc_extprob_tval', 'prsc_intprob_tval', 'prsc_behsymp_tval', 'prsc_adaskill_tval',
    'prsc_adaskill_sig', 'prsc_agg_sig', 'prsc_anx_sig',
    'prsc_apr_sig', 'prsc_aty_sig', 'prsc_behsymp_sig',
    'prsc_cp_sig', 'prsc_dep_sig', 'prsc_extprob_sig',
    'prsc_hyp_sig', 'prsc_intprob_sig', 'prsc_som_sig',
    'prsc_wdl_sig', 'prsc_ssk_sig', 'prsc_ldr_sig',
    'prsc_fc_sig', 'prsc_ada_sig', 'prsc_adl_sig'
);

// Default input fields
$required_fields = array();
foreach (range(1,160) as $i){
        array_push($required_fields, "basc_prs_c_q$i");
}
array_push($required_fields, "basc_prs_c_age");

// Default output fields
$default_result_fields = array();
foreach($default_result_fields_orig as $key => $field) {
    $default_result_fields[$key] = $field;
}

# Override default input array with manual field names specified by user (optional)
$translated_fields = array();
if (!empty($manual_source_fields)) {
    if (count($manual_source_fields) == count($required_fields)) {
        foreach($manual_source_fields as $k => $field) {
            if ($field) {   // Only replace non-empty field names (this allows someone to use ,,,newname,, in list)
                $translated_fields[$required_fields[$k]] = $field;
                $required_fields[$k] = $field;
                //$this->module->emDebug("Changing input field ".$k." to ".$field);
            } else {
                $translated_fields[$required_fields[$k]] = $required_fields[$k];
            }
        }
        $log[] = "Overriding default input field names with ". implode(',',$manual_source_fields);
    } else {
        $msg = count($manual_source_fields) . " manual source fields specified, but the algorithm needs " . count($required_fields) . " fields.";
        $this->module->emError($msg);
        $algorithm_log[] = $msg;
        return false;
    }
} else {
    foreach($required_fields as $k => $field) {
        $translated_fields[$required_fields[$k]] = $required_fields[$k];
    }

}
#$this->module->emDebug("After substitution, translated source fields: " . json_encode($translated_fields));


### VALIDATION ##
# If we are simply verifying the result and required fields, we can exit now.
if (isset($verify) && $verify === true) return true;

# Define Log Array - optionally used if specified by the log_field in the configuration project
$algorithm_log = array();
$algorithm_log[] = "Scored using " . basename(__FILE__, ".php") . " at " . date("Y-m-d H:i:s");

# Override default result array with manual field names specified by user (optional)
$translated_result_fields = array();
if (!empty($manual_result_fields)) {
        if (count($manual_result_fields) == count($default_result_fields)) {
                foreach($manual_result_fields as $k => $field) {
                        if ($field) {   // Only replace non-empty field names (this allows someone to use ,,,newname,, in list)
                            $translated_result_fields[$default_result_fields[$k]] = $field;
                            $default_result_fields[$k] = $field;
                        } else {
                            $translated_result_fields[$default_result_fields[$k]] = $default_result_fields[$k];
                        }
                }
                $log[] = "Overriding default result field names with ". implode(',',$manual_result_fields);
        } else {
                $msg = count($manual_result_fields) . " manual result fields specified, but the algorithm needs " . count($default_result_fields) . " fields.";
                $this->module->emError($msg);
                $algorithm_log[] = $msg;
                return false;
        }
} else {
    foreach($default_result_fields as $k => $field) {
        $translated_result_fields[$default_result_fields[$k]] = $default_result_fields[$k];
    }
}
#$this->module->emDebug("Translated result fields after substitution: " . json_encode($translated_result_fields));


# Test for presense of all required fields and report missing fields
$source_fields = array_keys($src);
$missing_fields = array_diff($required_fields, $source_fields);
if ($missing_fields) {
    $msg = "Source project ($project_id) is trying to run the {$job['algorithm']} algorithm but is missing [" . implode(',',$missing_fields) . "]";
    $algorithm_log[] = $msg;
    $this->module->emError($msg);
    $this->module->emDebug("Missing Fields: " . $missing_fields);
    return false; //Since this is being called via include, the main script will continue to process other algorithms
}

#    The following scoring should already be reversed from above: 3,16,17,41,49,66,80,81,98,103,105,131,142,145
$source_names = array("hyp","agg","cp","anx","dep","som","aty","wdl","apr","ada","ssk","ldr","adl","fc"); 
$source_indexes = array(
        'hyp' => array(6,38,70,102,134,20,52,84,116,148),
        'agg' => array(8,40,72,104,136,24,56,88,26,58,90),
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
    'ada' => $translated_result_fields['prsc_ada_valid'],
    'adl' => $translated_result_fields['prsc_adl_valid'],
    'agg' => $translated_result_fields['prsc_agg_valid'],
    'anx' => $translated_result_fields['prsc_anx_valid'],
    'apr' => $translated_result_fields['prsc_apr_valid'],
    'aty' => $translated_result_fields['prsc_aty_valid'],
    'cp' =>  $translated_result_fields['prsc_cp_valid'],
    'dep' => $translated_result_fields['prsc_dep_valid'],
    'fc' =>  $translated_result_fields['prsc_fc_valid'],
    'hyp' => $translated_result_fields['prsc_hyp_valid'],
    'ldr' => $translated_result_fields['prsc_ldr_valid'],
    'som' => $translated_result_fields['prsc_som_valid'],
    'ssk' => $translated_result_fields['prsc_ssk_valid'],
    'wdl' => $translated_result_fields['prsc_wdl_valid']
);

$F_index = 0;

$F_index_values = array(
    35 => 0,
    52  => 3,
    160 => 3,
    112 => 3,
    47  => 3,
    154 => 0,
    91  => 3,
    54  => 3,
    157 => 3,
    68  => 0,
    136 => 3,
    69  => 3,
    114 => 3,
    111 => 3,
    41  => 0,
    17  => 0,
    124 => 3,
    1   => 0,
    8   => 3,
    32  => 3
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

#   The last value in the required fields array is the age.  Peal off the age and don't go through the
#   rest of the processing because I'm not sure what it is doing.
    if ($req_name == $translated_fields['basc_prs_c_age']) {
	    $age = $src[$req_name];
    }
    else
    {

    	$index = $i+1;
    	$val = $src[$req_name];
    	if (isset($val) and strlen($val) > 0)
    	{
    	  $val--;
    	}

   	# $val = $src[$req_name];
    
    	if (in_array(($index), $flipped_values)) {
      	      $new_source[$req_name] = (isset($val) and strlen($val) > 0) ? 3-$val : null;
#   	      $this->module->emDebug("Flipped values: i=$i, field_name = $req_name, val = $val, new source = $new_source[$req_name]");
    	} else {
       	     $new_source[$req_name] = (isset($val) and strlen($val) > 0) ? $val : null;
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
      		$cons_pair_val = $new_source[$required_fields[$cons_index_values[$index]-1]];
      		$thisval = $new_source[$req_name];
#      		$this->module->emDebug($req_name . ":" . $required_fields[$cons_index_values[$index]-1] . " || " . $new_source[$req_name] . ":" . $cons_pair_val);
      		$cons_index += abs($cons_pair_val - $thisval);
#      		$this->module->emDebug("adding: " . $cons_index);
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
$tvalue_6_to_7 = array(
        'hyp' => array(
			30 => 97, 29 => 95, 28 => 92, 27 => 90, 26 => 88, 
			25 => 86, 24 => 84, 23 => 82, 22 => 79, 21 => 77,
			20 => 75, 19 => 73, 18 => 71, 17 => 68, 16 => 66,
			15 => 64, 14 => 62, 13 => 60, 12 => 58, 11 => 55,
			10 => 53,  9 => 51,  8 => 49,  7 => 47,  6 => 44,
			 5 => 42,  4 => 40,  3 => 38,  2 => 36,  1 => 34,
			 0 => 31 
                ),
        'agg' => array(
                         33 => 112, 32 => 110, 31 => 107, 30 => 105, 29 => 103,
                         28 => 101, 27 => 98,  26 => 96,  25 => 94,  24 => 91,
                         23 => 89,  22 => 87,  21 => 84,  20 => 82,  19 => 80,
                         18 => 78,  17 => 75,  16 => 73,  15 => 71,  14 => 68,
                         13 => 66,  12 => 64,  11 => 61,  10 => 59,   9 => 57,
                          8 => 55,   7 => 52,   6 => 50,   5 => 48,   4 => 45,
                          3 => 43,   2 => 41,   1 => 38,   0 => 36 
               ),
        'cp' => array(
                         27 => 117, 26 => 114, 25 => 111, 24 => 108, 23 => 105,
                         22 => 102, 21 => 98,  20 => 95,  19 => 92,  18 => 89,
                         17 => 86,  16 => 83,  15 => 80,  14 => 77,  13 => 74,
                         12 => 71,  11 => 68,  10 => 65,   9 => 62,   8 => 59,
                          7 => 56,   6 => 53,   5 => 49,   4 => 46,   3 => 43,
                          2 => 40,   1 => 37,   0 => 34 
                ),
        'anx' => array(
                         42 => 103,41 => 101, 40 => 99, 39 => 97, 38 => 96,
                         37 => 94, 36 => 92,  35 => 90, 34 => 89, 33 => 87,
                         32 => 85, 31 => 83,  30 => 82, 29 => 80, 28 => 78,
                         27 => 76, 26 => 75,  25 => 73, 24 => 71, 23 => 69,
                         22 => 68, 21 => 66,  20 => 64, 19 => 62, 18 => 61,
                         17 => 59, 16 => 57,  15 => 55, 14 => 54, 13 => 52,
                         12 => 50, 11 => 48,  10 => 47, 9 => 45,   8 => 43,
                         7 => 41,   6 => 40,   5 => 38, 4 => 36,   3 => 34,
                         2 => 33,   1 => 31,   0 => 29
                ),
        'dep' => array(
                         42 => 120, 41 => 120, 40 => 118, 39 => 116, 38 => 114,
                         37 => 112, 36 => 110, 35 => 108, 34 => 106, 33 => 104,
                         32 => 102, 31 => 100, 30 => 98,  29 => 96,  28 => 94,
                         27 => 92,  26 => 89,  25 => 87,  24 => 85,  23 => 83,
                         22 => 81,  21 => 79,  20 => 77,  19 => 75,  18 => 73,
                         17 => 71,  16 => 69,  15 => 67,  14 => 65,  13 => 63,
                         12 => 61,  11 => 59,  10 => 57,   9 => 55,   8 => 53,
                         7 => 51,    6 => 49,   5 => 47,   4 => 45,   3 => 43,
                         2 => 41,    1 => 39,   0 => 37
                ),
        'som' => array(
                         36 => 120, 35 => 120, 34 => 120, 33 => 120, 32 => 120,
                         31 => 120, 30 => 120, 29 => 118, 28 => 115, 27 => 112,
                         26 => 110, 25 => 107, 24 => 104, 23 => 101, 22 => 98,
                         21 => 95,  20 => 93,  19 => 90,  18 => 87,  17 => 84,
                         16 => 81,  15 => 79,  14 => 76,  13 => 73,  12 => 70,
                         11 => 67,  10 => 64,   9 => 62,   8 => 59,   7 => 56,
                          6 => 53,   5 => 50,   4 => 48,   3 => 45,   2 => 42,
                          1 => 39,   0 => 36 
                ),
        'aty' => array(
			39 => 120, 38 => 120, 37 => 120, 36 => 120, 35 => 120,
			34 => 120, 33 => 120, 32 => 120, 31 => 120, 30 => 120,
			29 => 120, 28 => 117, 27 => 114, 26 => 111, 25 => 109,
			24 => 106, 23 => 103, 22 => 101, 21 => 98,  20 => 95,
			19 => 93,  18 => 90,  17 => 87,  16 => 84,  15 => 82,
			14 => 79,  13 => 76,  12 => 74,  11 => 71,  10 => 68,
		  	 9 => 65,   8 => 63,   7 => 60,   6 => 57,   5 => 55,
			 4 => 52,   3 => 49,   2 => 46,   1 => 44,   0 => 41 
                ),
        'wdl' => array(
			36 => 120, 35 => 119, 34 => 117, 33 => 114, 32 => 112,
			31 => 109, 30 => 107, 29 => 104, 28 => 102, 27 => 100,
			26 => 97,  25 => 95,  24 => 92,  23 => 90,  22 => 87,
			21 => 85,  20 => 83,  19 => 80,  18 => 78,  17 => 75,
			16 => 73,  15 => 71,  14 => 68,  13 => 66,  12 => 63,
			11 => 61,  10 => 58,   9 => 56,   8 => 54,   7 => 51,
		 	 6 => 49,   5 => 46,   4 => 44,   3 => 42,   2 => 39,
			 1 => 37,   0 => 34 
                ),
        'apr' => array(
			18 => 84, 17 => 81, 16 => 78, 15 => 76, 14 => 73,
			13 => 70, 12 => 67, 11 => 64, 10 => 62,  9 => 59,
			 8 => 56,  7 => 53,  6 => 50,  5 => 47,  4 => 45,
			 3 => 42,  2 => 39,  1 => 36,  0 => 33
                ),
        'ada' => array(
			24 => 69, 23 => 67, 22 => 64, 21 => 62, 20 => 60,
			19 => 58, 18 => 56, 17 => 53, 16 => 51, 15 => 48,
			14 => 46, 13 => 44, 12 => 42, 11 => 39, 10 => 37,
			9 => 35,   8 => 32,  7 => 30,  6 => 28,  5 => 25,
			4 => 23,   3 => 21,  2 => 19,  1 => 16,  0 => 14
                ),
        'ssk' => array(
			24 => 70, 23 => 68, 22 => 66, 21 => 64, 20 => 61,
			19 => 59, 18 => 57, 17 => 55, 16 => 52, 15 => 50,
			14 => 48, 13 => 46, 12 => 43, 11 => 41, 10 => 39,
			9 => 37,   8 => 34,  7 => 32,  6 => 30,  5 => 28,
			4 => 25,   3 => 23,  2 => 21,  1 => 19,  0 => 16
                ),
        'ldr' => array(
                        24 => 75, 23 => 73, 22 => 70, 21 => 68, 20 => 66,
                        19 => 63, 18 => 61, 17 => 59, 16 => 57, 15 => 54,
                        14 => 52, 13 => 50, 12 => 47, 11 => 45, 10 => 43,
                        9 => 41,   8 => 38,  7 => 36,  6 => 34,  5 => 31,
                        4 => 29,   3 => 27,  2 => 25,  1 => 22,  0 => 20    
                ),
        'adl' => array(
                        24 => 72, 23 => 70, 22 => 67, 21 => 65, 20 => 62,
                        19 => 60, 18 => 57, 17 => 54, 16 => 52, 15 => 49,
                        14 => 47, 13 => 44, 12 => 42, 11 => 39, 10 => 36,
                        9 => 34,   8 => 31,  7 => 29,  6 => 26,  5 => 24,
                        4 => 21,   3 => 19,  2 => 16,  1 => 13,  0 => 11        
                ),
        'fc' => array(
                        36 => 68, 35 => 66, 34 => 64, 33 => 63, 32 => 61,
                        31 => 59, 30 => 57, 29 => 56, 28 => 54, 27 => 52,
                        26 => 51, 25 => 49, 24 => 47, 23 => 45, 22 => 44,
                        21 => 42, 20 => 40, 19 => 39, 18 => 37, 17 => 35,
                        16 => 33, 15 => 32, 14 => 30, 13 => 28, 12 => 27,
                        11 => 25, 10 => 23,  9 => 21,  8 => 20,  7 => 18,
                        6 => 16,   5 => 14,  4 => 13,  3 => 11,  2 => 10,
                        1 => 10,   0 => 10 
                )
        );

$tvalue_8_to_11 = array(
        'hyp' => array(
                         0 => 34, 1 => 36, 2 => 39, 3 => 41, 4 => 43, 5 => 45,
                         6 => 47, 7 => 50, 8 => 52, 9 => 54, 10 => 56, 
                         11 => 58, 12 => 61, 13 => 63, 14 => 65, 15 => 67,
                         16 => 69, 17 => 72, 18 => 74, 19 => 76, 20 => 78,
                         21 => 80, 22 => 83, 23 => 85, 24 => 87, 25 => 89,
                         26 => 91, 27 => 94, 28 => 96, 29 => 98, 30 => 100
                ),
        'agg' => array(
                         0 => 37, 1 => 40, 2 => 42, 3 => 44, 4 => 46, 5 => 48,
                         6 => 51, 7 => 53, 8 => 55, 9 => 57, 10 => 60,
                         11 => 62, 12 => 64, 13 => 66, 14 => 68, 15 => 71,
                         16 => 73, 17 => 75, 18 => 77, 19 => 79, 20 => 82,
                         21 => 84, 22 => 86, 23 => 88, 24 => 91, 25 => 93,
                         26 => 95, 27 => 97, 28 => 99, 29 => 102, 30 => 104,
                         31 => 106, 32 => 108, 33 => 110
                ),
        'cp' => array(
                         0 => 37, 1 => 40, 2 => 43, 3 => 46, 4 => 48, 5 => 51,
                         6 => 54, 7 => 56, 8 => 59, 9 => 62, 10 => 65,
                         11 => 67, 12 => 70, 13 => 73, 14 => 75, 15 => 78,
                         16 => 81, 17 => 84, 18 => 86, 19 => 89, 20 => 92,
                         21 => 94, 22 => 97, 23 => 100, 24 => 103, 25 => 105,
                         26 => 108, 27 => 111
                ),
        'anx' => array(
                         0 => 28, 1 => 30, 2 => 32, 3 => 33, 4 => 35, 5 => 37,
                         6 => 38, 7 => 40, 8 => 42, 9 => 43, 10 => 45,
                         11 => 47, 12 => 49, 13 => 50, 14 => 52, 15 => 54,
                         16 => 55, 17 => 57, 18 => 59, 19 => 60, 20 => 62,
                         21 => 64, 22 => 65, 23 => 67, 24 => 69, 25 => 70,
                         26 => 72, 27 => 74, 28 => 75, 29 => 77, 30 => 79,
                         31 => 80, 32 => 82, 33 => 84, 34 => 86, 35 => 87,
                         36 => 89, 37 => 91, 38 => 92, 39 => 94, 40 => 96,
                         41 => 97, 42 => 99
                ),
        'dep' => array(
                         0 => 37, 1 => 39, 2 => 41, 3 => 43, 4 => 45, 5 => 47,
                         6 => 49, 7 => 51, 8 => 53, 9 => 55, 10 => 57, 
                         11 => 59, 12 => 60, 13 => 62, 14 => 64, 15 => 66,
                         16 => 68, 17 => 70, 18 => 72, 19 => 74, 20 => 76,
                         21 => 78, 22 => 80, 23 => 82, 24 => 83, 25 => 85,
                         26 => 87, 27 => 89, 28 => 91, 29 => 93, 30 => 95,
                         31 => 97, 32 => 99, 33 => 101, 34 => 103, 35 => 105,
                         36 => 107, 37 => 108, 38 => 110, 39 => 112, 40 => 114,
                         41 => 116, 42 => 118
                ),
        'som' => array(
                         0 => 36, 1 => 39, 2 => 42, 3 => 44, 4 => 47, 5 => 50,
                         6 => 53, 7 => 56, 8 => 59, 9 => 61, 10 => 64,
                         11 => 67, 12 => 70, 13 => 73, 14 => 75, 15 => 78,
                         16 => 81, 17 => 84, 18 => 87, 19 => 90, 20 => 92,
                         21 => 95, 22 => 98, 23 => 101, 24 => 104, 25 => 106,
                         26 => 109, 27 => 112, 28 => 115, 29 => 118, 30 => 120,
                         31 => 120, 32 => 120, 33 => 120, 34 => 120, 35 => 120,
                         36 => 120
                ),
        'aty' => array(
                   0 => 41, 1 => 44, 2 => 46, 3 => 49, 4 => 52, 5 => 54,
                   6 => 58, 7 => 60, 8 => 62, 9 => 65, 10 => 68,
                   11 => 70, 12 => 73, 13 => 75, 14 => 78, 15 => 81,
                   16 => 83, 17 => 86, 18 => 89, 19 => 91, 20 => 94,
                   21 => 97, 22 => 99, 23 => 102, 24 => 105, 25 => 107,
                   26 => 110, 27 => 112, 28 => 115, 29 => 118, 30 => 120,
                   31 => 120, 32 => 120, 33 => 120, 34 => 120, 35 => 120,
                   36 => 120, 37 => 120, 38 => 120, 39 => 120
                ),
        'wdl' => array(
                      0 => 35, 1 => 38, 2 => 40, 3 => 42, 4 => 44, 5 => 47,
                      6 => 49, 7 => 51, 8 => 53, 9 => 56, 10 => 58,
                      11 => 60, 12 => 62, 13 => 65, 14 => 67, 15 => 69,
                      16 => 71, 17 => 74, 18 => 76, 19 => 78, 20 => 80,
                      21 => 83, 22 => 85, 23 => 87, 24 => 89, 25 => 91,
                      26 => 94, 27 => 96, 28 => 98, 29 => 100, 30 => 103,
                      31 => 105, 32 => 107, 33 => 109, 34 => 112, 35 => 114,
                      36 => 116
                ),
        'apr' => array(
                         0 => 35, 1 => 37, 2 => 40, 3 => 43, 4 => 45, 5 => 48,
                         6 => 51, 7 => 53, 8 => 56, 9 => 59, 10 => 61,
                         11 => 64, 12 => 67, 13 => 69, 14 => 72, 15 => 74,
                         16 => 77, 17 => 80, 18 => 82  
                ),
        'ada' => array(
                     0 => 14, 1 => 16, 2 => 19, 3 => 21, 4 => 23, 5 => 25,
                         6 => 28, 7 => 30, 8 => 32, 9 => 35, 10 => 37,
                         11 => 39, 12 => 41, 13 => 44, 14 => 46, 15 => 48,
                         16 => 50, 17 => 53, 18 => 55, 19 => 57, 20 => 60,
                         21 => 62, 22 => 64, 23 => 66, 24 => 69   
                ),
        'ssk' => array(
                    0 => 18, 1 => 20, 2 => 22, 3 => 25, 4 => 27, 5 => 29,
                         6 => 31, 7 => 33, 8 => 35, 9 => 37, 10 => 39,
                         11 => 42, 12 => 44, 13 => 46, 14 => 48, 15 => 50,
                         16 => 52, 17 => 54, 18 => 56, 19 => 59, 20 => 61,
                         21 => 63, 22 => 65, 23 => 67, 24 => 69   
                ),
        'ldr' => array(
                         0 => 21, 1 => 23, 2 => 25, 3 => 27, 4 => 29, 5 => 31,
                         6 => 34, 7 => 36, 8 => 38, 9 => 40, 10 => 42,
                         11 => 44, 12 => 46, 13 => 49, 14 => 51, 15 => 53,
                         16 => 55, 17 => 57, 18 => 59, 19 => 61, 20 => 64,
                         21 => 66, 22 => 68, 23 => 70, 24 => 72    
                ),
        'adl' => array(
                         0 => 10, 1 => 11, 2 => 13, 3 => 16, 4 => 19, 5 => 21,
                         6 => 24, 7 => 26, 8 => 29, 9 => 31, 10 => 34,
                         11 => 37, 12 => 39, 13 => 42, 14 => 44, 15 => 47,
                         16 => 49, 17 => 52, 18 => 55, 19 => 57, 20 => 60,
                         21 => 62, 22 => 65, 23 => 67, 24 => 70
                         
                ),
        'fc' => array(
                        0 => 10, 1 => 10, 2 => 10, 3 => 10, 4 => 11, 5 => 13,
                        6 => 14, 7 => 16, 8 => 18, 9 => 20, 10 => 21,
                        11 => 23, 12 => 25, 13 => 26, 14 => 28, 15 => 30,
                        16 => 32, 17 => 33, 18 => 35, 19 => 37, 20 => 38,
                        21 => 40, 22 => 42, 23 => 43, 24 => 45, 25 => 47,
                        26 => 49, 27 => 50, 28 => 52, 29 => 54, 30 => 55,
                        31 => 57, 32 => 59, 33 => 60, 34 => 62, 35 => 64,
                        36 => 66
                )
        );


$tvalue_perc_8_to_11 = array(
        'hyp' => array(
                        0 => 1, 1 => 2, 2 => 8, 3 => 17, 4 => 27, 5 => 38,
                        6 => 48, 7 => 57, 8 => 65, 9 => 72, 10 => 77,
                        11 => 82, 12 => 86, 13 => 89, 14 => 92, 15 => 94,
                        16 => 95, 17 => 96, 18 => 87, 19 => 98, 20 => 98,
                        21 => 99, 22 => 99, 23 => 99, 24 => 99, 25 => 99,
                        26 => 99, 27 => 99, 28 => 99, 29 => 99, 30 => 99
                ),
        'agg' => array(
                        0 => 3, 1 => 9, 2 => 19, 3 => 31, 4 => 43, 5 => 53,
                        6 => 62, 7 => 70, 8 => 76, 9 => 81, 10 => 85,
                        11 => 88, 12 => 91, 13 => 93, 14 => 94, 15 => 96,
                        16 => 97, 17 => 97, 18 => 98, 19 => 98, 20 => 99,
                        21 => 99, 22 => 99, 23 => 99, 24 => 99, 25 => 99,
                        26 => 99, 27 => 99, 28 => 99, 29 => 99, 30 => 99,
                        31 => 99, 32 => 99, 33 => 99
                ),
        'cp' => array(
                        0 => 3, 1 => 11, 2 => 24, 3 => 39, 4 => 52, 5 => 64,
                        6 => 73, 7 => 80, 8 => 85, 9 => 89, 10 => 92,
                        11 => 94, 12 => 95, 13 => 97, 14 => 97, 15 => 98,
                        16 => 99, 17 => 99, 18 => 99, 19 => 99, 20 => 99,
                        21 => 99, 22 => 99, 23 => 99, 24 => 99, 25 => 99, 
                        26 => 99, 27 => 99
                ),
        'anx' => array(
                        0 => 1, 1 => 1, 2 => 2, 3 => 3, 4 => 5, 5 => 8,
                        6 => 11, 7 => 16, 8 => 21, 9 => 27, 10 => 33,
                        11 => 40, 12 => 47, 13 => 54, 14 => 61, 15 => 67,
                        16 => 72, 17 => 77, 18 => 81, 19 => 85, 20 => 88,
                        21 => 91, 22 => 93, 23 => 94, 24 => 96, 25 => 97,
                        26 => 98, 27 => 98, 28 => 99, 29 => 99, 30 => 99,
                        31 => 99, 32 => 99, 33 => 99, 34 => 99, 35 => 99,
                        36 => 99, 37 => 99, 38 => 99, 39 => 99, 40 => 99,
                        41 => 99, 42 => 99
                ),
        'dep' => array(
                        0 => 1, 1 => 7, 2 => 16, 3 => 27, 4 => 38, 5 => 47,
                        6 => 56, 7 => 64, 8 => 70, 9 => 76, 10 => 80,
                        11 => 84, 12 => 87, 13 => 89, 14 => 91, 15 => 93,
                        16 => 94, 17 => 95, 18 => 96, 19 => 97, 20 => 97,
                        21 => 98, 22 => 98, 23 => 99, 24 => 99, 25 => 99,
                        26 => 99, 27 => 99, 28 => 99, 29 => 99, 30 => 99,
                        31 => 99, 32 => 99, 33 => 99, 34 => 99, 35 => 99,
                        36 => 99, 37 => 99, 38 => 99, 39 => 99, 40 => 99,
                        41 => 99, 42 => 99
                ),
        'som' => array(
                        0 => 4, 1 => 11, 2 => 22, 3 => 33, 4 => 45, 5 => 56,
                        6 => 66, 7 => 74, 8 => 81, 9 => 86, 10 => 90,
                        11 => 93, 12 => 96, 13 => 97, 14 => 96, 15 => 99,
                        16 => 99, 17 => 99, 18 => 99, 19 => 99, 20 => 99,
                        21 => 99, 22 => 99, 23 => 99, 24 => 99, 25 => 99, 
                        26 => 99, 27 => 99, 28 => 99, 29 => 99, 30 => 99, 
                        31 => 99, 32 => 99, 33 => 99, 34 => 99, 35 => 99,
                        36 => 99

                ),
        'aty' => array(
                       0 => 13, 1 => 30, 2 => 46, 3 => 59, 5 => 69, 5 => 76,
                       6 => 82, 7 => 86, 8 => 89, 9 => 92, 10 => 94,
                       11 => 95, 12 => 96, 13 => 97, 14 => 98, 15 => 98, 
                       16 => 99, 17 => 99, 18 => 99, 19 => 99, 20 => 99, 
                       21 => 99, 22 => 99, 23 => 99, 24 => 99, 25 => 99, 
                       26 => 99, 27 => 99, 28 => 99, 29 => 99, 30 => 99,
                       31 => 99, 32 => 99, 33 => 99, 34 => 99, 35 => 99,
                       36 => 99, 37 => 99, 38 => 99, 39 => 99
                ),
        'wdl' => array(
                        0 => 1, 1 => 5, 2 => 13, 3 => 23, 4 => 34, 5 => 44,
                        6 => 54, 7 => 63, 8 => 70, 9 => 76, 10 => 81,
                        11 => 85, 12 => 88, 13 => 91, 14 => 93, 15 => 95,
                        16 => 96, 17 => 97, 18 => 98, 19 => 98, 20 => 99,
                        21 => 99, 22 => 99, 23 => 99, 24 => 99, 25 => 99, 
                        26 => 99, 27 => 99, 28 => 99, 29 => 99, 30 => 99,
                        31 => 99, 32 => 99, 33 => 99, 34 => 99, 35 => 99,
                        36 => 99
                ),
        'apr' => array(
                        0 => 3, 1 => 10, 2 => 18, 3 => 28, 4 => 37, 5 => 47,
                        6 => 56, 7 => 64, 8 => 72, 9 => 79, 10 => 84,
                        11 => 89, 12 => 93, 13 => 96, 14 => 98, 15 => 99, 
                        16 => 99, 17 => 99, 18 => 99
                ),
        'ada' => array(
                        0 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1,
                        6 => 2, 7 => 3, 8 => 5, 9 => 7, 10 => 10,
                        11 => 15, 12 => 20, 13 => 26, 14 => 33, 15 => 41,
                        16 => 50, 17 => 58, 18 => 67, 19 => 75, 20 => 82,
                        21 => 88, 22 => 93, 23 => 96, 24 => 98
                ),
        'ssk' => array(
                       0 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 2,
                       6 => 3, 7 => 5, 8 => 8, 9 => 12, 10 => 16,
                       11 => 21, 12 => 28, 13 => 34, 14 => 42, 15 => 49,
                       16 => 57, 17 => 64, 18 => 72, 19 => 78, 20 => 84,
                       21 => 89, 22 => 93, 23 => 96, 24 => 98
                ),
        'ldr' => array(
                        0 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 2, 5 => 3,
                        6 => 5, 7 => 8, 8 => 12, 9 => 17, 10 => 23,
                        11 => 29, 12 => 36, 13 => 44, 14 => 52, 15 => 60,
                        16 => 68, 17 => 75, 18 => 81, 19 => 87, 20 => 91,
                        21 => 95, 22 => 97, 23 => 99, 24 => 99
                ),
        'adl' => array(
                        0 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1,
                        6 => 1, 7 => 2, 8 => 3, 9 => 4, 10 => 7,
                        11 => 10, 12 => 15, 13 => 20, 14 => 28, 15 => 36, 
                        16 => 45, 17 => 55, 18 => 65, 19 => 75, 20 => 83,
                        21 => 90, 22 => 95, 23 => 98, 24 => 99
                ),
        'fc' => array(
                        0 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1,
                        6 => 1, 7 => 1, 8 => 1, 9 => 1, 10 => 1, 
                        11 => 1, 12 => 1, 13 => 2, 14 => 3, 15 => 4, 
                        16 => 5, 17 => 7, 18 => 8, 19 => 11, 20 => 14,
                        21 => 17, 22 => 20, 23 => 25, 24 => 29, 25 => 34,
                        26 => 40, 27 => 46, 28 => 53, 29 => 59, 30 => 66,
                        31 => 73, 32 => 79, 33 => 85, 34 => 90, 35 => 94,
                        36 => 97
                )
        );


$tvalue_perc_6_to_7 = array(
        'hyp' => array(
                        30 => 99,29 => 99,28 => 99,27 => 99,26 => 99,
                        25 => 99,24 => 99,23 => 99,22 => 99,21 => 98,
                        20 => 98,19 => 97,18 => 96,17 => 95,16 => 93,
                        15 => 91,14 => 89,13 => 85,12 => 81,11 => 76,
                        10 => 69,9 => 62,8 => 53,7 => 43,6 => 33,
                        5 => 22,4 => 13,3 => 6,2 => 2,1 => 1,
                        0 => 1
                ),
        'agg' => array(
                        33 => 99,32 => 99,31 => 99,30 => 99,29 => 99,
                        28 => 99,27 => 99,26 => 99,25 => 99,24 => 99,
                        23 => 99,22 => 99,21 => 99,20 => 99,19 => 99,
                        18 => 98,17 => 98,16 => 97,15 => 96,14 => 94,
                        13 => 93,12 => 90,11 => 87,10 => 84,9 => 79,
                        8 => 73,7 => 66,6 => 58,5 => 48,4 => 38,
                        3 => 26,2 => 16,1 => 7,0 => 2
                ),
        'cp' => array(
                        27 => 99,26 => 99,25 => 99,24 => 99,23 => 99,
                        22 => 99,21 => 99,20 => 99,19 => 99,18 => 99,
                        17 => 99,16 => 99,15 => 99,14 => 98,13 => 98,
                        12 => 96,11 => 95,10 => 92,9 => 89,8 => 83,
                        7 => 76,6 => 66,5 => 54,4 => 40,3 => 26,
                        2 => 14,1 => 6,0 => 2
                ),
        'anx' => array(
                        42 => 99,41 => 99,40 => 99,39 => 99,38 => 99,
                        37 => 99,36 => 99,35 => 99,34 => 99,33 => 99,
                        32 => 99,31 => 99,30 => 99,29 => 99,28 => 99,
                        27 => 99,26 => 99,25 => 98,24 => 97,23 => 96,
                        22 => 95,21 => 93,20 => 91,19 => 89,18 => 85,
                        17 => 82,16 => 77,15 => 72,14 => 67,13 => 61,
                        12 => 54,11 => 47,10 => 40,9 => 33,8 => 26,
                        7 => 20,6 => 15,5 => 11,4 => 7,3 => 4,2 => 2,
                        1 => 1,0 => 1
                ),
        'dep' => array(
                        42 => 99,41 => 99,40 => 99,39 => 99,38 => 99,
                        37 => 99,36 => 99,35 => 99,34 => 99,33 => 99,
                        32 => 99,31 => 99,30 => 99,29 => 99,28 => 99,
                        27 => 99,26 => 99,25 => 99,24 => 99,23 => 99,
                        22 => 99,21 => 98,20 => 98,19 => 97,18 => 97,
                        17 => 96,16 => 95,15 => 94,14 => 92,13 => 90,
                        12 => 87,11 => 84,10 => 80,9 => 76,8 => 70,
                        7 => 63,6 => 55,5 => 46,4 => 36,3 => 25,
                        2 => 15,1 => 6,0 => 1 
                ),
        'som' => array(
                        36 => 99,35 => 99,34 => 99,33 => 99,32 => 99,
                        31 => 99,30 => 99,29 => 99,28 => 99,27 => 99,
                        26 => 99,25 => 99,24 => 99,23 => 99,22 => 99,
                        21 => 99,20 => 99,19 => 99,18 => 99,17 => 99,
                        16 => 99,15 => 99,14 => 98,13 => 97,12 => 96,
                        11 => 94,10 => 91,9 => 88,8 => 83,7 => 77,
                        6 => 69,5 => 59,4 => 47,3 => 34,2 => 21,
                        1 => 10,0 => 3 
                ),
        'aty' => array(
			39 => 99,38 => 99,37 => 99,36 => 99,35 => 99,
			34 => 99,33 => 99,32 => 99,31 => 99,30 => 99,
			29 => 99,28 => 99,27 => 99,26 => 99,25 => 99,
			24 => 99,23 => 99,22 => 99,21 => 99,20 => 99,
			19 => 99,18 => 99,17 => 99,16 => 99,15 => 98,
			14 => 98,13 => 97,12 => 96,11 => 95,10 => 94,
			9 => 92,8 => 90,7 => 86,6 => 83,5 => 77,
			4 => 70,3 => 61,2 => 48,1 => 30,0 => 9 
                ),
        'wdl' => array(
			36 => 99,35 => 99,34 => 99,33 => 99,32 => 99,
			31 => 99,30 => 99,29 => 99,28 => 99,27 => 99,
			26 => 99,25 => 99,24 => 99,23 => 99,22 => 99,
			21 => 99,20 => 99,19 => 99,18 => 99,17 => 98,
			16 => 97,15 => 96,14 => 95,13 => 93,12 => 90,
			11 => 86,10 => 82,9 => 76,8 => 69,7 => 60,
			6 => 51,5 => 41,4 => 30,3 => 20,2 => 12,
			1 => 5,0 => 2   
                ),
        'apr' => array(
			18 => 99,17 => 99,16 => 99,15 => 99,14 => 98,
			13 => 97,12 => 94,11 => 90,10 => 85,9 => 79,
			8 => 72,7 => 64,6 => 54,5 => 44,4 => 34,
			3 => 23,2 => 14,1 => 6,0 => 1  
                ),
        'ada' => array(
			24 => 98,23 => 96,22 => 93,21 => 88,20 => 82,
			19 => 75,18 => 68,17 => 60,16 => 51,15 => 43,
			14 => 35,13 => 28,12 => 21,11 => 16,10 => 11,
			9 => 7,8 => 4,7 => 2,6 => 1,5 => 1,4 => 1,
			3 => 1,2 => 1,1 => 1,0 => 1  
                ),
        'ssk' => array(
			24 => 99,23 => 98,22 => 96,21 => 92,20 => 87,
			19 => 81,18 => 73,17 => 65,16 => 56,15 => 48,
			14 => 40,13 => 32,12 => 25,11 => 19,10 => 14,
			9 => 10,8 => 7,7 => 5,6 => 3,5 => 2,4 => 1,
			3 => 1,2 => 1,1 => 1,0 => 1 
                ),
        'ldr' => array(
                        24 => 99,23 => 99,22 => 98,21 => 97,20 => 94,
                        19 => 91,18 => 86,17 => 80,16 => 73,15 => 65,
                        14 => 57,13 => 48,12 => 39,11 => 31,10 => 24,
                        9 => 18,8 => 13,7 => 9,6 => 6,5 => 3,4 => 2,
                        3 => 1,2 => 1,1 => 1,0 => 1 
                ),
        'adl' => array(
                        24 => 99,23 => 99,22 => 98,21 => 95,20 => 90,
                        19 => 83,18 => 74,17 => 64,16 => 54,15 => 44,
                        14 => 35,13 => 26,12 => 20,11 => 14,10 => 10,
                        9 => 7,8 => 4,7 => 3,6 => 2,5 => 1,4 => 1,
                        3 => 1,2 => 1,1 => 1,0 => 1 
                ),
        'fc' => array(
                        36 => 99,35 => 99,34 => 96,33 => 93,32 => 88,
                        31 => 82,30 => 75,29 => 68,28 => 60,27 => 53,
                        26 => 46,25 => 40,24 => 34,23 => 29,22 => 24,
                        21 => 20,20 => 16,19 => 13,18 => 11,17 => 9,
                        16 => 7,15 => 5,14 => 4,13 => 3,12 => 2,
                        11 => 2,10 => 1,9 => 1,8 => 1,7 => 1,
                        6 => 1,5 => 1,4 => 1,3 => 1,2 => 1,
                        1 => 1,0 => 1 
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

	# Find the correct tvalue and tvalue percentage tables for lookup
        # There is one table for 6 and 7 yr olds and another table for 8 to 11 yr olds.
    //if ($age >= 6 and $age <= 7) {
    if ($age >= 6 and $age < 8) {
		$tvalue = $tvalue_6_to_7;
		$tvalue_perc = $tvalue_perc_6_to_7;
	} else if ($age >= 8 and $age < 12) {
		$tvalue = $tvalue_8_to_11;
		$tvalue_perc = $tvalue_perc_8_to_11;
	} else {
		$tvalue = null;
		$tvalue_perc = null;
	}

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
            'extprob'  => array('hyp', 'agg', 'cp'),
            'intprob'  => array('anx', 'dep', 'som'),
            'behsymp'  => array('aty', 'wdl', 'apr', 'hyp', 'agg', 'dep'),
            'adaskill' => array('ada', 'ssk', 'ldr', 'adl', 'fc')
        );

# This table has problems
        $composite_comb_tvalues_8_to_11 = array(
                'extprob' => array( 
                        34 => array('min' => 108, 'max' => 108), 35 => array('min' => 109, 'max' => 110), 36 => array('min' => 111, 'max' => 113),
                        37 => array('min' => 114, 'max' => 116), 38 => array('min' => 117, 'max' => 119), 39 => array('min' => 120, 'max' => 121),
                        40 => array('min' => 122, 'max' => 124), 41 => array('min' => 125, 'max' => 127), 42 => array('min' => 128, 'max' => 129),
                        43 => array('min' => 130, 'max' => 132), 44 => array('min' => 133, 'max' => 135), 45 => array('min' => 136, 'max' => 137),
                        46 => array('min' => 138, 'max' => 140), 47 => array('min' => 141, 'max' => 143), 48 => array('min' => 144, 'max' => 145),
                        49 => array('min' => 146, 'max' => 148), 50 => array('min' => 149, 'max' => 151), 51 => array('min' => 152, 'max' => 154),
                        52 => array('min' => 155, 'max' => 156), 53 => array('min' => 157, 'max' => 159), 54 => array('min' => 160, 'max' => 162),
                        55 => array('min' => 163, 'max' => 164), 56 => array('min' => 165, 'max' => 167), 57 => array('min' => 168, 'max' => 170), 
                        58 => array('min' => 171, 'max' => 172), 59 => array('min' => 173, 'max' => 175), 60 => array('min' => 176, 'max' => 178),
                        61 => array('min' => 179, 'max' => 180), 62 => array('min' => 181, 'max' => 183), 63 => array('min' => 184, 'max' => 186),
                        64 => array('min' => 187, 'max' => 189), 65 => array('min' => 190, 'max' => 191), 66 => array('min' => 192, 'max' => 194),
                        67 => array('min' => 195, 'max' => 197), 68 => array('min' => 198, 'max' => 199), 69 => array('min' => 200, 'max' => 202),
                        70 => array('min' => 203, 'max' => 205), 71 => array('min' => 206, 'max' => 207), 72 => array('min' => 208, 'max' => 210),
                        73 => array('min' => 211, 'max' => 213), 74 => array('min' => 214, 'max' => 215), 75 => array('min' => 216, 'max' => 218),
                        76 => array('min' => 219, 'max' => 221), 77 => array('min' => 222, 'max' => 224), 78 => array('min' => 225, 'max' => 226),
                        79 => array('min' => 227, 'max' => 229), 80 => array('min' => 230, 'max' => 232), 81 => array('min' => 233, 'max' => 234),
                        82 => array('min' => 235, 'max' => 237), 83 => array('min' => 238, 'max' => 240), 84 => array('min' => 241, 'max' => 242),
                        85 => array('min' => 243, 'max' => 245), 86 => array('min' => 246, 'max' => 248), 87 => array('min' => 249, 'max' => 250),
                        88 => array('min' => 251, 'max' => 253), 89 => array('min' => 254, 'max' => 256), 90 => array('min' => 257, 'max' => 259),
                        91 => array('min' => 260, 'max' => 261), 92 => array('min' => 262, 'max' => 264), 93 => array('min' => 265, 'max' => 267),
                        94 => array('min' => 268, 'max' => 269), 95 => array('min' => 270, 'max' => 272), 96 => array('min' => 273, 'max' => 275),
                        97 => array('min' => 276, 'max' => 277), 98 => array('min' => 278, 'max' => 280), 99 => array('min' => 281, 'max' => 283),
                        100 => array('min' => 284, 'max' => 285), 101 => array('min' => 286, 'max' => 288), 102 => array('min' => 289, 'max' => 291),
                        103 => array('min' => 292, 'max' => 294), 104 => array('min' => 295, 'max' => 296), 105 => array('min' => 297, 'max' => 299),
                        106 => array('min' => 300, 'max' => 302), 107 => array('min' => 303, 'max' => 304), 108 => array('min' => 305, 'max' => 307),
                        109 => array('min' => 308, 'max' => 310), 110 => array('min' => 311, 'max' => 312), 111 => array('min' => 313, 'max' => 315),
                        112 => array('min' => 316, 'max' => 318), 113 => array('min' => 319, 'max' => 320), 114 => array('min' => 321, 'max' => 321)
                        ),
                'intprob' => array(
                        30 => array('min' => 101, 'max' => 102), 31 => array('min' => 103, 'max' => 105), 32 => array('min' => 106, 'max' => 107),
                        33 => array('min' => 108, 'max' => 110), 34 => array('min' => 111, 'max' => 112), 35 => array('min' => 113, 'max' => 114),
                        36 => array('min' => 115, 'max' => 117), 37 => array('min' => 118, 'max' => 119), 38 => array('min' => 120, 'max' => 122),
                        39 => array('min' => 123, 'max' => 124), 40 => array('min' => 125, 'max' => 127), 41 => array('min' => 128, 'max' => 129),
                        42 => array('min' => 130, 'max' => 131), 43 => array('min' => 132, 'max' => 134), 44 => array('min' => 135, 'max' => 136),
                        45 => array('min' => 137, 'max' => 139), 46 => array('min' => 140, 'max' => 141), 47 => array('min' => 142, 'max' => 143),
                        48 => array('min' => 144, 'max' => 146), 49 => array('min' => 147, 'max' => 148), 50 => array('min' => 149, 'max' => 151),
                        51 => array('min' => 152, 'max' => 153), 52 => array('min' => 154, 'max' => 156), 53 => array('min' => 157, 'max' => 158),
                        54 => array('min' => 159, 'max' => 160), 55 => array('min' => 161, 'max' => 163), 56 => array('min' => 164, 'max' => 165),
                        57 => array('min' => 166, 'max' => 168), 58 => array('min' => 169, 'max' => 170), 59 => array('min' => 171, 'max' => 172),
                        60 => array('min' => 173, 'max' => 175), 61 => array('min' => 176, 'max' => 178), 62 => array('min' => 179, 'max' => 180),
                        63 => array('min' => 181, 'max' => 182), 64 => array('min' => 183, 'max' => 184), 65 => array('min' => 185, 'max' => 187),
                        66 => array('min' => 188, 'max' => 189), 67 => array('min' => 190, 'max' => 192), 68 => array('min' => 193, 'max' => 194),
                        69 => array('min' => 195, 'max' => 197), 70 => array('min' => 198, 'max' => 199), 71 => array('min' => 200, 'max' => 201), 
                        72 => array('min' => 202, 'max' => 204), 73 => array('min' => 205, 'max' => 206), 74 => array('min' => 207, 'max' => 209), 
                        75 => array('min' => 210, 'max' => 211), 76 => array('min' => 212, 'max' => 213), 77 => array('min' => 214, 'max' => 216),
                        78 => array('min' => 217, 'max' => 218), 79 => array('min' => 219, 'max' => 221), 80 => array('min' => 222, 'max' => 223),
                        81 => array('min' => 224, 'max' => 226), 82 => array('min' => 227, 'max' => 228), 83 => array('min' => 229, 'max' => 230), 
                        84 => array('min' => 231, 'max' => 233), 85 => array('min' => 234, 'max' => 235), 86 => array('min' => 236, 'max' => 238),
                        87 => array('min' => 239, 'max' => 240), 88 => array('min' => 241, 'max' => 242), 89 => array('min' => 243, 'max' => 245),
                        90 => array('min' => 246, 'max' => 247), 91 => array('min' => 248, 'max' => 250), 92 => array('min' => 251, 'max' => 252),
                        93 => array('min' => 253, 'max' => 254), 94 => array('min' => 255, 'max' => 257), 95 => array('min' => 258, 'max' => 259),
                        96 => array('min' => 260, 'max' => 262), 97 => array('min' => 263, 'max' => 264), 98 => array('min' => 265, 'max' => 267),
                        99 => array('min' => 268, 'max' => 269), 100 => array('min' => 170, 'max' => 271), 101 => array('min' => 272, 'max' => 274),
                        102 => array('min' => 275, 'max' => 276), 103 => array('min' => 277, 'max' => 279), 104 => array('min' => 280, 'max' => 281),
                        105 => array('min' => 282, 'max' => 283), 106 => array('min' => 284, 'max' => 286), 107 => array('min' => 287, 'max' => 288),
                        108 => array('min' => 289, 'max' => 291), 109 => array('min' => 292, 'max' => 293), 110 => array('min' => 294, 'max' => 296),
                        111 => array('min' => 297, 'max' => 298), 112 => array('min' => 299, 'max' => 300), 113 => array('min' => 301, 'max' => 303),
                        114 => array('min' => 304, 'max' => 305), 115 => array('min' => 306, 'max' => 308), 116 => array('min' => 309, 'max' => 310),
                        117 => array('min' => 311, 'max' => 312), 118 => array('min' => 313, 'max' => 315), 119 => array('min' => 316, 'max' => 317),
                        120 => array('min' => 318, 'max' => 337)
                        ),
                'behsymp' => array(
			 33 => array('min' => 219, 'max' => 222),  34 => array('min' => 223, 'max' => 227),  35 => array('min' => 228, 'max' => 231),
			 36 => array('min' => 232, 'max' => 236),  37 => array('min' => 237, 'max' => 241),  38 => array('min' => 242, 'max' => 245),
			 39 => array('min' => 246, 'max' => 250),  40 => array('min' => 251, 'max' => 255),  41 => array('min' => 256, 'max' => 260),
			 42 => array('min' => 261, 'max' => 264),  43 => array('min' => 265, 'max' => 269),  44 => array('min' => 270, 'max' => 274),
			 44 => array('min' => 275, 'max' => 278),  46 => array('min' => 279, 'max' => 283),  47 => array('min' => 284, 'max' => 288),
			 48 => array('min' => 289, 'max' => 292),  49 => array('min' => 293, 'max' => 297),  50 => array('min' => 298, 'max' => 302),
			 51 => array('min' => 303, 'max' => 307),  52 => array('min' => 308, 'max' => 311),  53 => array('min' => 312, 'max' => 316),
			 54 => array('min' => 317, 'max' => 321),  55 => array('min' => 322, 'max' => 326),  56 => array('min' => 327, 'max' => 330),
			 57 => array('min' => 331, 'max' => 335),  58 => array('min' => 336, 'max' => 339),  59 => array('min' => 340, 'max' => 344),
			 60 => array('min' => 345, 'max' => 349),  61 => array('min' => 350, 'max' => 354),  62 => array('min' => 355, 'max' => 358),
			 63 => array('min' => 359, 'max' => 363),  64 => array('min' => 364, 'max' => 368),  65 => array('min' => 369, 'max' => 372),
			 66 => array('min' => 373, 'max' => 377),  67 => array('min' => 378, 'max' => 382),  68 => array('min' => 383, 'max' => 386),
			 69 => array('min' => 387, 'max' => 391),  70 => array('min' => 392, 'max' => 396),  71 => array('min' => 397, 'max' => 400),
			 72 => array('min' => 401, 'max' => 405),  73 => array('min' => 406, 'max' => 410),  74 => array('min' => 411, 'max' => 415),
			 75 => array('min' => 416, 'max' => 419),  76 => array('min' => 420, 'max' => 424),  77 => array('min' => 425, 'max' => 429),
			 78 => array('min' => 430, 'max' => 433),  79 => array('min' => 434, 'max' => 438),  80 => array('min' => 439, 'max' => 443),
			 81 => array('min' => 444, 'max' => 447),  82 => array('min' => 448, 'max' => 452),  83 => array('min' => 453, 'max' => 457),
			 84 => array('min' => 458, 'max' => 462),  85 => array('min' => 463, 'max' => 466),  86 => array('min' => 467, 'max' => 471),
			 87 => array('min' => 472, 'max' => 476),  88 => array('min' => 477, 'max' => 480),  89 => array('min' => 481, 'max' => 485),
			 90 => array('min' => 486, 'max' => 490),  91 => array('min' => 491, 'max' => 494),  92 => array('min' => 495, 'max' => 499),
			 93 => array('min' => 500, 'max' => 504),  94 => array('min' => 505, 'max' => 509),  95 => array('min' => 510, 'max' => 513),
			 96 => array('min' => 514, 'max' => 518),  97 => array('min' => 519, 'max' => 523),  98 => array('min' => 524, 'max' => 527),
			 99 => array('min' => 528, 'max' => 532), 100 => array('min' => 533, 'max' => 537), 101 => array('min' => 538, 'max' => 541),
			102 => array('min' => 542, 'max' => 546), 103 => array('min' => 547, 'max' => 551), 104 => array('min' => 552, 'max' => 556),
			105 => array('min' => 557, 'max' => 560), 106 => array('min' => 561, 'max' => 565), 107 => array('min' => 566, 'max' => 570),
			108 => array('min' => 571, 'max' => 574), 109 => array('min' => 575, 'max' => 579), 110 => array('min' => 580, 'max' => 584),
			111 => array('min' => 585, 'max' => 588), 112 => array('min' => 589, 'max' => 593), 113 => array('min' => 594, 'max' => 598),
			114 => array('min' => 599, 'max' => 602), 115 => array('min' => 603, 'max' => 607), 116 => array('min' => 608, 'max' => 612),
			117 => array('min' => 613, 'max' => 617), 118 => array('min' => 618, 'max' => 621), 119 => array('min' => 622, 'max' => 626),
			120 => array('min' => 627, 'max' => 646)
                     ),
               'adaskill' => array(
                        10 => array('min' => 73, 'max' => 79), 11 => array('min' => 80, 'max' => 84), 12 => array('min' => 85, 'max' => 88),
                        13 => array('min' => 89, 'max' => 92), 14 => array('min' => 93, 'max' => 97), 15 => array('min' => 98, 'max' => 101),
                        16 => array('min' => 102, 'max' => 105), 17 => array('min' => 106, 'max' => 109), 18 => array('min' => 110, 'max' => 114),
                        19 => array('min' => 115, 'max' => 118), 20 => array('min' => 119, 'max' => 122), 21 => array('min' => 123, 'max' => 127),
                        22 => array('min' => 128, 'max' => 131), 23 => array('min' => 132, 'max' => 135), 24 => array('min' => 136, 'max' => 140),
                        25 => array('min' => 141, 'max' => 144), 26 => array('min' => 145, 'max' => 148), 27 => array('min' => 149, 'max' => 153),
                        28 => array('min' => 154, 'max' => 157), 29 => array('min' => 158, 'max' => 161), 30 => array('min' => 162, 'max' => 165),
                        31 => array('min' => 166, 'max' => 170), 32 => array('min' => 171, 'max' => 174), 33 => array('min' => 175, 'max' => 178),
                        34 => array('min' => 179, 'max' => 183), 35 => array('min' => 184, 'max' => 187), 36 => array('min' => 188, 'max' => 191),
                        37 => array('min' => 192, 'max' => 196), 38 => array('min' => 197, 'max' => 200), 39 => array('min' => 201, 'max' => 204),
                        40 => array('min' => 205, 'max' => 209), 41 => array('min' => 210, 'max' => 213), 42 => array('min' => 214, 'max' => 217),
                        43 => array('min' => 218, 'max' => 221), 44 => array('min' => 222, 'max' => 226), 45 => array('min' => 227, 'max' => 230),
                        46 => array('min' => 231, 'max' => 234), 47 => array('min' => 235, 'max' => 239), 48 => array('min' => 240, 'max' => 243),
                        49 => array('min' => 244, 'max' => 247), 50 => array('min' => 248, 'max' => 252), 51 => array('min' => 253, 'max' => 256),
                        52 => array('min' => 257, 'max' => 260), 53 => array('min' => 261, 'max' => 265), 54 => array('min' => 266, 'max' => 269),
                        55 => array('min' => 270, 'max' => 273), 56 => array('min' => 274, 'max' => 277), 57 => array('min' => 278, 'max' => 282),
                        58 => array('min' => 283, 'max' => 286), 59 => array('min' => 287, 'max' => 290), 60 => array('min' => 291, 'max' => 295),
                        61 => array('min' => 296, 'max' => 299), 62 => array('min' => 300, 'max' => 303), 63 => array('min' => 304, 'max' => 308),
                        64 => array('min' => 309, 'max' => 312), 65 => array('min' => 313, 'max' => 316), 66 => array('min' => 317, 'max' => 321),
                        67 => array('min' => 322, 'max' => 325), 68 => array('min' => 326, 'max' => 329), 69 => array('min' => 330, 'max' => 334),
                        70 => array('min' => 335, 'max' => 338), 71 => array('min' => 339, 'max' => 342), 72 => array('min' => 343, 'max' => 346)
                        )
        );


        $composite_comb_tvalues_6_to_7 = array(
                'extprob' => array(
			116 => array('min' =>  325, 'max' =>  326 ),115 => array('min' =>  322, 'max' =>  324 ),114 => array('min' =>  320, 'max' =>  321 ),
			113 => array('min' =>  317, 'max' =>  319 ),112 => array('min' =>  314, 'max' =>  316 ),111 => array('min' =>  312, 'max' =>  313 ),
			110 => array('min' =>  309, 'max' =>  311 ),109 => array('min' =>  306, 'max' =>  308 ),108 => array('min' =>  304, 'max' =>  305 ),
			107 => array('min' =>  301, 'max' =>  303 ),106 => array('min' =>  298, 'max' =>  300 ),105 => array('min' =>  296, 'max' =>  297 ),
			104 => array('min' =>  293, 'max' =>  295 ),103 => array('min' =>  290, 'max' =>  292 ),102 => array('min' =>  288, 'max' =>  289 ),
			101 => array('min' =>  285, 'max' =>  287 ),100 => array('min' =>  282, 'max' =>  284 ),99 => array('min' =>  280, 'max' =>  281 ),
			98 => array('min' =>  277, 'max' =>  279 ),97 => array('min' =>  274, 'max' =>  276 ),96 => array('min' =>  272, 'max' =>  273 ),
			95 => array('min' =>  269, 'max' =>  271 ),94 => array('min' =>  266, 'max' =>  268 ),93 => array('min' =>  264, 'max' =>  265 ),
			92 => array('min' =>  261, 'max' =>  263 ),91 => array('min' =>  258, 'max' =>  260 ),90 => array('min' =>  256, 'max' =>  257 ),
			89 => array('min' =>  253, 'max' =>  255 ),88 => array('min' =>  250, 'max' =>  252 ),87 => array('min' =>  248, 'max' =>  249 ),
			86 => array('min' =>  245, 'max' =>  247 ),85 => array('min' =>  242, 'max' =>  244 ),84 => array('min' =>  240, 'max' =>  241 ),
			83 => array('min' =>  237, 'max' =>  239 ),82 => array('min' =>  234, 'max' =>  236 ),81 => array('min' =>  232, 'max' =>  233 ),
			80 => array('min' =>  229, 'max' =>  231 ),79 => array('min' =>  226, 'max' =>  228 ),78 => array('min' =>  224, 'max' =>  225 ),
			77 => array('min' =>  221, 'max' =>  223 ),76 => array('min' =>  218, 'max' =>  220 ),75 => array('min' =>  216, 'max' =>  217 ),
			74 => array('min' =>  213, 'max' =>  215 ),73 => array('min' =>  210, 'max' =>  212 ),72 => array('min' =>  208, 'max' =>  209 ),
			71 => array('min' =>  205, 'max' =>  207 ),70 => array('min' =>  202, 'max' =>  204 ),69 => array('min' =>  200, 'max' =>  201 ),
			68 => array('min' =>  197, 'max' =>  199 ),67 => array('min' =>  194, 'max' =>  196 ),66 => array('min' =>  192, 'max' =>  193 ),
			65 => array('min' =>  189, 'max' =>  191 ),64 => array('min' =>  186, 'max' =>  188 ),63 => array('min' =>  184, 'max' =>  185 ),
			62 => array('min' =>  181, 'max' =>  183 ),61 => array('min' =>  178, 'max' =>  180 ),60 => array('min' =>  176, 'max' =>  177 ),
			59 => array('min' =>  173, 'max' =>  175 ),58 => array('min' =>  170, 'max' =>  172 ),57 => array('min' =>  168, 'max' =>  169 ),
			56 => array('min' =>  165, 'max' =>  167 ),55 => array('min' =>  162, 'max' =>  164 ),54 => array('min' =>  160, 'max' =>  161 ),
			53 => array('min' =>  157, 'max' =>  159 ),52 => array('min' =>  154, 'max' =>  156 ),51 => array('min' =>  152, 'max' =>  153 ),
			50 => array('min' =>  149, 'max' =>  151 ),49 => array('min' =>  147, 'max' =>  148 ),48 => array('min' =>  144, 'max' =>  146 ),
			47 => array('min' =>  141, 'max' =>  143 ),46 => array('min' =>  139, 'max' =>  140 ),45 => array('min' =>  136, 'max' =>  138 ),
			44 => array('min' =>  133, 'max' =>  135 ),43 => array('min' =>  131, 'max' =>  132 ),42 => array('min' =>  128, 'max' =>  130 ),
			41 => array('min' =>  125, 'max' =>  127 ),40 => array('min' =>  123, 'max' =>  124 ),39 => array('min' =>  120, 'max' =>  122 ),
			38 => array('min' =>  117, 'max' =>  119 ),37 => array('min' =>  115, 'max' =>  116 ),36 => array('min' =>  112, 'max' =>  114 ),
			35 => array('min' =>  109, 'max' =>  111 ),34 => array('min' =>  107, 'max' =>  108 ),33 => array('min' =>  104, 'max' =>  106 ),
			32 => array('min' =>  101, 'max' =>  103 ) 
                        ),
                'intprob' => array(
                        120 => array('min' =>  318, 'max' =>  343 ),119 => array('min' =>  316, 'max' =>  317 ),118 => array('min' =>  313, 'max' =>  315 ),
                        117 => array('min' =>  311, 'max' =>  312 ),116 => array('min' =>  308, 'max' =>  310 ),115 => array('min' =>  306, 'max' =>  307 ),
                        114 => array('min' =>  304, 'max' =>  305 ),113 => array('min' =>  301, 'max' =>  303 ),112 => array('min' =>  299, 'max' =>  300 ),
                        111 => array('min' =>  296, 'max' =>  298 ),110 => array('min' =>  294, 'max' =>  295 ),109 => array('min' =>  292, 'max' =>  293 ),
                        108 => array('min' =>  289, 'max' =>  291 ),107 => array('min' =>  287, 'max' =>  288 ),106 => array('min' =>  284, 'max' =>  286 ),
                        105 => array('min' =>  282, 'max' =>  283 ),104 => array('min' =>  279, 'max' =>  281 ),103 => array('min' =>  277, 'max' =>  278 ),
                        102 => array('min' =>  275, 'max' =>  276 ),101 => array('min' =>  272, 'max' =>  274 ),100 => array('min' =>  270, 'max' =>  271 ),
                        99 => array('min' =>  267, 'max' =>  269 ),98 => array('min' =>  265, 'max' =>  266 ),97 => array('min' =>  263, 'max' =>  264 ),
                        96 => array('min' =>  260, 'max' =>  262 ),95 => array('min' =>  258, 'max' =>  259 ),94 => array('min' =>  255, 'max' =>  257 ),
                        93 => array('min' =>  253, 'max' =>  254 ),92 => array('min' =>  251, 'max' =>  252 ),91 => array('min' =>  248, 'max' =>  250 ),
                        90 => array('min' =>  246, 'max' =>  247 ),89 => array('min' =>  243, 'max' =>  245 ),88 => array('min' =>  241, 'max' =>  242 ),
                        87 => array('min' =>  238, 'max' =>  240 ),86 => array('min' =>  236, 'max' =>  237 ),85 => array('min' =>  234, 'max' =>  235 ),
                        84 => array('min' =>  231, 'max' =>  233 ),83 => array('min' =>  229, 'max' =>  230 ),82 => array('min' =>  226, 'max' =>  228 ),
                        81 => array('min' =>  224, 'max' =>  225 ),80 => array('min' =>  222, 'max' =>  223 ),79 => array('min' =>  219, 'max' =>  221 ),
                        78 => array('min' =>  217, 'max' =>  218 ),77 => array('min' =>  214, 'max' =>  216 ),76 => array('min' =>  212, 'max' =>  213 ),
                        75 => array('min' =>  210, 'max' =>  211 ),74 => array('min' =>  207, 'max' =>  209 ),73 => array('min' =>  205, 'max' =>  206 ),
                        72 => array('min' =>  202, 'max' =>  204 ),71 => array('min' =>  200, 'max' =>  201 ),70 => array('min' =>  198, 'max' =>  199 ),
                        69 => array('min' =>  195, 'max' =>  197 ),68 => array('min' =>  193, 'max' =>  194 ),67 => array('min' =>  190, 'max' =>  192 ),
                        66 => array('min' =>  188, 'max' =>  189 ),65 => array('min' =>  185, 'max' =>  187 ),64 => array('min' =>  183, 'max' =>  184 ),
                        63 => array('min' =>  181, 'max' =>  182 ),62 => array('min' =>  178, 'max' =>  180 ),61 => array('min' =>  176, 'max' =>  177 ),
                        60 => array('min' =>  173, 'max' =>  175 ),59 => array('min' =>  171, 'max' =>  172 ),58 => array('min' =>  169, 'max' =>  170 ),
                        57 => array('min' =>  166, 'max' =>  168 ),56 => array('min' =>  164, 'max' =>  165 ),55 => array('min' =>  161, 'max' =>  163 ),
                        54 => array('min' =>  159, 'max' =>  160 ),53 => array('min' =>  157, 'max' =>  158 ),52 => array('min' =>  154, 'max' =>  156 ),
                        51 => array('min' =>  152, 'max' =>  153 ),50 => array('min' =>  149, 'max' =>  151 ),49 => array('min' =>  147, 'max' =>  148 ),
                        48 => array('min' =>  144, 'max' =>  146 ),47 => array('min' =>  142, 'max' =>  143 ),46 => array('min' =>  140, 'max' =>  141 ),
                        45 => array('min' =>  137, 'max' =>  139 ),44 => array('min' =>  135, 'max' =>  136 ),43 => array('min' =>  132, 'max' =>  134 ),
                        42 => array('min' =>  130, 'max' =>  131 ),41 => array('min' =>  128, 'max' =>  129 ),40 => array('min' =>  125, 'max' =>  427 ),
                        39 => array('min' =>  123, 'max' =>  124 ),38 => array('min' =>  120, 'max' =>  122 ),37 => array('min' =>  118, 'max' =>  119 ),
                        36 => array('min' =>  116, 'max' =>  117 ),35 => array('min' =>  113, 'max' =>  115 ),34 => array('min' =>  111, 'max' =>  112 ),
                        33 => array('min' =>  108, 'max' =>  110 ),32 => array('min' =>  106, 'max' =>  107 ),31 => array('min' =>  103, 'max' =>  105 ),
                        30 => array('min' =>  102, 'max' =>  102 )
                       ),
                'behsymp' => array(
                        120 => array('min' =>  619, 'max' =>  653 ),119 => array('min' =>  614, 'max' =>  618 ),118 => array('min' =>  610, 'max' =>  613 ),
                        117 => array('min' =>  605, 'max' =>  609 ),116 => array('min' =>  600, 'max' =>  604 ),115 => array('min' =>  596, 'max' =>  599 ),
                        114 => array('min' =>  591, 'max' =>  595 ),113 => array('min' =>  587, 'max' =>  590 ),112 => array('min' =>  582, 'max' =>  586 ),
                        111 => array('min' =>  577, 'max' =>  581 ),110 => array('min' =>  573, 'max' =>  576 ),109 => array('min' =>  568, 'max' =>  572 ),
                        108 => array('min' =>  564, 'max' =>  567 ),107 => array('min' =>  559, 'max' =>  563 ),106 => array('min' =>  555, 'max' =>  558 ),
                        105 => array('min' =>  550, 'max' =>  554 ),104 => array('min' =>  545, 'max' =>  549 ),103 => array('min' =>  541, 'max' =>  544 ),
                        102 => array('min' =>  536, 'max' =>  540 ),101 => array('min' =>  532, 'max' =>  535 ),100 => array('min' =>  527, 'max' =>  531 ),
                        99 => array('min' =>  523, 'max' =>  526 ),98 => array('min' =>  518, 'max' =>  522 ),97 => array('min' =>  513, 'max' =>  517 ),
                        96 => array('min' =>  509, 'max' =>  512 ),95 => array('min' =>  504, 'max' =>  508 ),94 => array('min' =>  500, 'max' =>  503 ),
                        93 => array('min' =>  495, 'max' =>  499 ),92 => array('min' =>  490, 'max' =>  494 ),91 => array('min' =>  486, 'max' =>  489 ),
                        90 => array('min' =>  481, 'max' =>  485 ),89 => array('min' =>  477, 'max' =>  480 ),88 => array('min' =>  472, 'max' =>  476 ),
                        87 => array('min' =>  468, 'max' =>  471 ),86 => array('min' =>  463, 'max' =>  467 ),85 => array('min' =>  458, 'max' =>  462 ),
                        84 => array('min' =>  454, 'max' =>  457 ),83 => array('min' =>  449, 'max' =>  453 ),82 => array('min' =>  445, 'max' =>  448 ),
                        81 => array('min' =>  440, 'max' =>  444 ),80 => array('min' =>  436, 'max' =>  439 ),79 => array('min' =>  431, 'max' =>  435 ),
                        78 => array('min' =>  426, 'max' =>  430 ),77 => array('min' =>  422, 'max' =>  425 ),76 => array('min' =>  417, 'max' =>  421 ),
                        75 => array('min' =>  413, 'max' =>  416 ),74 => array('min' =>  408, 'max' =>  412 ),73 => array('min' =>  404, 'max' =>  407 ),
                        72 => array('min' =>  399, 'max' =>  403 ),71 => array('min' =>  394, 'max' =>  398 ),70 => array('min' =>  390, 'max' =>  393 ),
                        69 => array('min' =>  385, 'max' =>  389 ),68 => array('min' =>  381, 'max' =>  384 ),67 => array('min' =>  376, 'max' =>  380 ),
                        66 => array('min' =>  371, 'max' =>  375 ),65 => array('min' =>  367, 'max' =>  370 ),64 => array('min' =>  362, 'max' =>  366 ),
                        63 => array('min' =>  358, 'max' =>  361 ),62 => array('min' =>  353, 'max' =>  357 ),61 => array('min' =>  349, 'max' =>  352 ),
                        60 => array('min' =>  344, 'max' =>  348 ),59 => array('min' =>  339, 'max' =>  343 ),58 => array('min' =>  335, 'max' =>  338 ),
                        57 => array('min' =>  330, 'max' =>  334 ),56 => array('min' =>  326, 'max' =>  329 ),55 => array('min' =>  321, 'max' =>  325 ),
                        54 => array('min' =>  317, 'max' =>  320 ),53 => array('min' =>  312, 'max' =>  316 ),52 => array('min' =>  307, 'max' =>  311 ),
                        51 => array('min' =>  303, 'max' =>  306 ),50 => array('min' =>  298, 'max' =>  302 ),49 => array('min' =>  294, 'max' =>  297 ),
                        48 => array('min' =>  289, 'max' =>  293 ),47 => array('min' =>  284, 'max' =>  288 ),46 => array('min' =>  280, 'max' =>  203 ),
                        45 => array('min' =>  275, 'max' =>  279 ),44 => array('min' =>  271, 'max' =>  274 ),43 => array('min' =>  266, 'max' =>  270 ),
                        42 => array('min' =>  262, 'max' =>  265 ),41 => array('min' =>  257, 'max' =>  261 ),40 => array('min' =>  252, 'max' =>  256 ),
                        39 => array('min' =>  248, 'max' =>  251 ),38 => array('min' =>  243, 'max' =>  247 ),37 => array('min' =>  239, 'max' =>  242 ),
                        36 => array('min' =>  234, 'max' =>  238 ),35 => array('min' =>  230, 'max' =>  233 ),34 => array('min' =>  225, 'max' =>  229 ),
                        33 => array('min' =>  220, 'max' =>  224 ),32 => array('min' =>  216, 'max' =>  219 ),31 => array('min' =>  212, 'max' =>  215 )
                       ),
                'adaskill' => array(
                        75 => array('min' =>  354, 'max' =>  354 ),74 => array('min' =>  350, 'max' =>  353 ),73 => array('min' =>  345, 'max' =>  349 ),
                        72 => array('min' =>  341, 'max' =>  344 ),71 => array('min' =>  337, 'max' =>  340 ),70 => array('min' =>  333, 'max' =>  336 ),
                        69 => array('min' =>  329, 'max' =>  332 ),68 => array('min' =>  324, 'max' =>  328 ),67 => array('min' =>  320, 'max' =>  323 ),
                        66 => array('min' =>  316, 'max' =>  319 ),65 => array('min' =>  312, 'max' =>  315 ),64 => array('min' =>  307, 'max' =>  311 ),
                        63 => array('min' =>  303, 'max' =>  306 ),62 => array('min' =>  299, 'max' =>  302 ),61 => array('min' =>  295, 'max' =>  298 ),
                        60 => array('min' =>  291, 'max' =>  294 ),59 => array('min' =>  286, 'max' =>  290 ),58 => array('min' =>  282, 'max' =>  285 ),
                        57 => array('min' =>  278, 'max' =>  281 ),56 => array('min' =>  274, 'max' =>  277 ),55 => array('min' =>  269, 'max' =>  273 ),
                        54 => array('min' =>  265, 'max' =>  268 ),53 => array('min' =>  261, 'max' =>  264 ),52 => array('min' =>  257, 'max' =>  260 ),
                        51 => array('min' =>  253, 'max' =>  256 ),50 => array('min' =>  248, 'max' =>  252 ),49 => array('min' =>  244, 'max' =>  247 ),
                        48 => array('min' =>  240, 'max' =>  243 ),47 => array('min' =>  236, 'max' =>  239 ),46 => array('min' =>  232, 'max' =>  235 ),
                        45 => array('min' =>  227, 'max' =>  231 ),44 => array('min' =>  223, 'max' =>  226 ),43 => array('min' =>  219, 'max' =>  222 ),
                        42 => array('min' =>  215, 'max' =>  218 ),41 => array('min' =>  210, 'max' =>  214 ),40 => array('min' =>  206, 'max' =>  209 ),
                        39 => array('min' =>  202, 'max' =>  205 ),38 => array('min' =>  198, 'max' =>  201 ),37 => array('min' =>  194, 'max' =>  197 ),
                        36 => array('min' =>  189, 'max' =>  193 ),35 => array('min' =>  185, 'max' =>  188 ),34 => array('min' =>  181, 'max' =>  184 ),
                        33 => array('min' =>  177, 'max' =>  180 ),32 => array('min' =>  172, 'max' =>  176 ),31 => array('min' =>  168, 'max' =>  171 ),
                        30 => array('min' =>  164, 'max' =>  167 ),29 => array('min' =>  160, 'max' =>  163 ),28 => array('min' =>  156, 'max' =>  159 ),
                        27 => array('min' =>  151, 'max' =>  155 ),26 => array('min' =>  147, 'max' =>  150 ),25 => array('min' =>  143, 'max' =>  146 ),
                        24 => array('min' =>  139, 'max' =>  142 ),23 => array('min' =>  135, 'max' =>  138 ),22 => array('min' =>  130, 'max' =>  134 ),
                        21 => array('min' =>  126, 'max' =>  129 ),20 => array('min' =>  122, 'max' =>  125 ),19 => array('min' =>  118, 'max' =>  121 ),
                        18 => array('min' =>  113, 'max' =>  117 ),17 => array('min' =>  109, 'max' =>  112 ),16 => array('min' =>  105, 'max' =>  108 ),
                        15 => array('min' =>  101, 'max' =>  104 ),14 => array('min' =>  97, 'max' =>  100 ),13 => array('min' =>  92, 'max' =>   96 ),
                        12 => array('min' =>  88, 'max' =>   91 ),11 => array('min' =>  84, 'max' =>   87 ),10 => array('min' =>  76, 'max' =>   83 ) 
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

# Select the tables we will use for lookup based on age
# There is one set of tables for 6 and 7 yr olds and another set of tables for 8 to  11 yr olds
//if ($age >=6 and $age <= 7) {
if ($age >=6 and $age < 8) {
	$composite_comb_tvalues = $composite_comb_tvalues_6_to_7;
//} else if ($age >= 8 and $age <= 11) {
} else if ($age >= 8 and $age < 12) {
	$composite_comb_tvalues = $composite_comb_tvalues_8_to_11; 
} else {
	$composite_comb_tvalues = null;
} 

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

### DEFINE RESULTS ###
# REQUIRED: The algorithm_results variable MUST BE USED as it is relied upon from the parent script.
$totals =  array_merge($raw_score_totals, $null_counter, $tvalue_scores, $tvalue_percent, $completeness_interpret, $F_index_result, $cons_index_result, $resp_pattern_result, $stat_result, $composite_raw, $composite_mean, $composite_tval, $sig_vals);
$algorithm_results = array_combine($default_result_fields, $totals);
#$this->module->emDebug("Results: " . json_encode($algorithm_results));

# Append result field for algorithm log if specified via the log_field variable in the config project
# Because we aren't pulling the entire data dictionary, we can't confirm whether or not the field actually exists

# Append result field for algorithm log if specified via the log_field variable in the config project
# Because we aren't pulling the entire data dictionary, we can't confirm whether or not the field actually exists
if ($job['log_field']) {
    $algorithm_results[$job['log_field']] = implode("\n",$algorithm_log);
    $msg = "Custom log_field {$job['log_field']}";
    $algorithm_log[] = $msg;
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
