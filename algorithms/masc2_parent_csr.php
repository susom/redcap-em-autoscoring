<?php

/**
	Multi-Dimensional Anxiety Scale for Children, 2nd edition - Parent Scale
	
	A REDCap AutoScoring Algorithm File

  50 Question Parent Report
  Based on Hand Score Form 

  Developed by Kim Wijaya and Alex Basile for ELSPAP June 2016
	
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

# REQUIRED: Summarize this algorithm
$algorithm_summary = "Multidimensional Anxiety Scale for Children, 2nd edition - Parents";
$this->module->emDebug("Scoring Title: " . $algorithm_summary);


# REQUIRED: Define an array of default result field_names to record the summary data
# The masc2-p Validity Scoring Totals are:
#   masc2-p Total Scale and masc2-p Anxiety Probability Scale 
# The clinical scales are: 
#   Separation Anxiety/Phobias
#   GAD Index
#   Humiliation/Rejection
#   Performance Fears
#   Obsessions & Compulsions
#   Panic
#   Tense/Restless
#   Harm Avoidance
#   Social Anxiety
#   Physical Symptoms Total
#   masc2-p Total
#   Inconsistency Index (response style)
$default_result_fields = array(
        'masc2_pr_sp_val',
        'masc2_pr_gad_val',
        'masc2_pr_hr_val',
	'masc2_pr_pf_val',
	'masc2_pr_oc_val',
	'masc2_pr_p_val',
	'masc2_pr_tr_val',
	'masc2_pr_ha_val',
        'masc2_pr_sa_val',
        'masc2_pr_pst_val',
        'masc2_pr_total',
        'masc2_pr_inconsis_idx',
        'masc2_pr_total_tval',
        'masc2_pr_sp_tval',
        'masc2_pr_gad_tval',
        'masc2_pr_sa_tval',
        'masc2_pr_hr_tval',
        'masc2_pr_pf_tval',
        'masc2_pr_oc_tval',
        'masc2_pr_pst_tval',
        'masc2_pr_p_tval',
        'masc2_pr_tr_tval',
        'masc2_pr_ha_tval',
        'masc2_pr_anx_prob',
        #'masc2_pr_total_sig',
        #'masc2_pr_sp_sig',
        #'masc2_pr_gad_sig',
        #'masc2_pr_sa_sig',
        #'masc2_pr_hr_sig',
        #'masc2_pr_pf_sig',
        #'masc2_pr_oc_sig',
        #'masc2_pr_pst_sig',
        #'masc2_pr_p_sig',
        #'masc2_pr_tr_sig',
        #'masc2_pr_ha_sig'
);

# REQUIRED: Define an array of fields that must be present for this algorithm to run
$required_fields = array();
foreach (range(1,50) as $i) {
	array_push($required_fields, "masc2_pr_q$i");
}

//$this->module->emDebug("Required input field names ".implode(',',$required_fields));

# Override default input array with manual field names specified by user (optional)
if (!empty($manual_source_fields)) {
    if (count($manual_source_fields) == count($required_fields)) {
            foreach($manual_source_fields as $k => $field) {
                        if ($field) {   // Only replace non-empty field names (this allows someone to use ,,,newname,, in list)
                                $required_fields[$k] = $field;
#                                $this->module->emDebug("Changing input field ".$k." to ".$field);
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

# Count up how many fields do not have a value
$null_fields = array();
$num_null_fields = 0;
foreach ($required_fields as $rf) {
	if (empty($src[$rf]) && !is_numeric($src[$rf])) {
               $null_fields[] = $rf;
               $num_null_fields++;
        }
}
if (!empty($null_fields)) {
	$algorithm_log[] = "WARNING - Required fields are empty (".implode(',',$null_fields).")";
	return false;
}

### IMPLEMENT RAW SCORING ###
# This is the array source
# Define lists of questions that correspond to each subscale
$sp_idx = array(26,30,33,4,7,9,17,19,23);
$gad_idx = array(27,29,31,1,6,39,40,13,17,22);
$hr_idx = array(29,3,10,16,22);
$pf_idx = array(32,36,38,14);
$oc_idx = array(41,42,43,44,45,46,47,48,49,50);
$p_idx = array(31,37,6,12,18,20,24);
$tr_idx = array(27,1,34,8,15);
$ha_idx = array(28,2,35,5,11,13,21,25);

foreach ($required_fields as $i => $field_name) {
	$val = $src[$field_name];
	if (in_array($i+1, $sp_idx))  $sp_val  += $val;
	if (in_array($i+1, $gad_idx)) $gad_val += $val;
	if (in_array($i+1, $hr_idx))  $hr_val  += $val;
	if (in_array($i+1, $pf_idx))  $pf_val  += $val;
	if (in_array($i+1, $oc_idx))  $oc_val  += $val;
	if (in_array($i+1, $p_idx))   $p_val   += $val;
	if (in_array($i+1, $tr_idx))  $tr_val  += $val;
	if (in_array($i+1, $ha_idx))  $ha_val  += $val;
    $this->module->emDebug("Index = $i, field name = $field_name, field value = $val");
}

$required_keys = array_keys($required_fields);
$sat_val = $hr_val + $pf_val;
$pst_val = $p_val + $tr_val;

# Adding the 39th and 40th value but since the index starts at 0, the index is 38 and 39.
$masc2_total = $sp_val + $sat_val + $oc_val + $pst_val + $ha_val + 
        $src[$required_fields[$required_keys[38]]] + $src[$required_fields[$required_keys[39]]];

### Calculate the Inconsistency Index
$inconsistancy_idx = abs($src[$required_fields[$required_keys[1]]] - $src[$required_fields[$required_keys[10]]])
                   + abs($src[$required_fields[$required_keys[2]]] - $src[$required_fields[$required_keys[9]]])
                   + abs($src[$required_fields[$required_keys[3]]] - $src[$required_fields[$required_keys[8]]])
                   + abs($src[$required_fields[$required_keys[4]]] - $src[$required_fields[$required_keys[12]]])
                   + abs($src[$required_fields[$required_keys[7]]] - $src[$required_fields[$required_keys[14]]])
                   + abs($src[$required_fields[$required_keys[25]]] - $src[$required_fields[$required_keys[21]]])
                   + abs($src[$required_fields[$required_keys[42]]] - $src[$required_fields[$required_keys[43]]])
                   + abs($src[$required_fields[$required_keys[44]]] - $src[$required_fields[$required_keys[45]]]);

$result_values = array($sp_val, $gad_val, $hr_val, $pf_val, $oc_val, $p_val, $tr_val, $ha_val,
                       $sat_val, $pst_val, $masc2_total, $inconsistancy_idx);

### Now get the T values from the grid. There are 3 different grids to choose from based on age.
#  Grid 1 = 8-11 yr olds, Grid 2 = 12-15 yr olds, and Grid 3 = 16-19 yr olds
#  Since the example I have uses Grid 1, I will use Grid 1 here.

$masc2_tmatrix = array(
    27 => 4, 28 => 42, 29 => 42,
    30 => 42, 31 => 43, 32 => 43,
    33 => 44, 34 => 44, 35 => 45,
    36 => 46, 37 => 46, 38 => 47,
    39 => 47, 40 => 48, 41 => 49,
    42 => 49, 43 => 50, 44 => 50,
    45 => 51, 46 => 52, 47 => 53,
    48 => 53, 49 => 54, 50 => 54,
    51 => 55, 52 => 55, 53 => 56,
    54 => 57, 55 => 57, 56 => 58,
    57 => 58, 58 => 59, 59 => 60,
    60 => 60, 61 => 61, 62 => 61,
    63 => 62, 64 => 63, 65 => 63,
    66 => 64, 67 => 64, 68 => 65,
    69 => 66, 70 => 66, 71 => 67,
    72 => 68, 73 => 68, 74 => 69,
    75 => 69, 76 => 70, 77 => 71,
    78 => 71, 79 => 72, 80 => 72,
    81 => 73, 82 => 74, 83 => 74,
    84 => 75, 85 => 75, 86 => 76,
    87 => 77, 88 => 77, 89 => 78,
    90 => 79, 91 => 79, 92 => 80,
    93 => 80, 94 => 81, 95 => 82,
    96 => 82, 97 => 83, 98 => 83,
    99 => 84, 100 => 85, 101 => 85,
    102 => 86, 103 => 86, 104 => 87,
    105 => 88, 106 => 88, 107 => 89,
    108 => 89, 109 => 90
   );

$sp_tmatrix = array(
      2 => 40,
      3 => 41,
      4 => 43,
      5 => 44,
      6 => 46,
      7 => 48,
      8 => 50,
      9 => 52,
      10 => 54,
      11 => 56,
      12 => 58,
      13 => 60,
      14 => 62,
      15 => 64,
      16 => 66,
      17 => 68,
      18 => 70,
      19 => 19,
      20 => 74,
      21 => 76,
      22 => 78,
      23 => 79,
      24 => 81,
      25 => 83,
      26 => 85,
      27 => 87
      );

$gad_tmatrix = array(
      5 => 41,
      6 => 43,
      7 => 46,
      8 => 48,
      9 => 50,
      10 => 53,
      11 => 55,
      12 => 57,
      13 => 60,
      14 => 62,
      15 => 64,
      16 => 67,
      17 => 69,
      18 => 71,
      19 => 74,
      20 => 76,
      21 => 78,
      22 => 81,
      23 => 83,
      24 => 85,
      25 => 88,
      26 => 90
      );

$sa_tmatrix = array(
      6 => 42,
      7 => 44,
      8 => 45,
      9 => 47,
      10 => 49,
      11 => 50,
      12 => 52,
      13 => 54,
      14 => 55,
      15 => 57,
      16 => 59,
      17 => 61,
      18 => 62,
      19 => 64,
      20 => 66,
      21 => 67,
      22 => 69,
      23 => 71,
      24 => 72,
      25 => 74,
      26 => 76,
      27 => 77
      );

$hr_tmatrix = array(
      2 => 40,
      3 => 41,
      4 => 44,
      5 => 46,
      6 => 49,
      7 => 51,
      8 => 54,
      9 => 56,
      10 => 59,
      11 => 62,
      12 => 64,
      13 => 67,
      14 => 69,
      15 => 72
      );

$pf_tmatrix = array(
      1 => 40,
      2 => 41,
      3 => 45,
      4 => 49,
      5 => 52,
      6 => 56,
      7 => 60,
      8 => 64,
      9 => 67,
      10 => 71,
      11 => 75,
      12 => 78
      );

$oc_tmatrix = array(
      0 => 42,
      1 => 45,
      2 => 48,
      3 => 50,
      4 => 53,
      5 => 56,
      6 => 59,
      7 => 61,
      8 => 64,
      9 => 67,
      10 => 70,
      11 => 73,
      12 => 75,
      13 => 78,
      14 => 81,
      15 => 84,
      16 => 86,
      17 => 89,
      18 => 90
      );

$pst_tmatrix = array(
      0 => 42,
      1 => 44,
      2 => 46,
      3 => 48,
      4 => 50,
      5 => 52,
      6 => 54,
      7 => 56,
      8 => 58,
      9 => 60,
      10 => 61,
      11 => 63,
      12 => 65,
      13 => 67,
      14 => 69,
      15 => 71,
      16 => 73,
      17 => 75,
      18 => 77,
      19 => 79,
      20 => 81,
      21 => 83,
      22 => 85,
      23 => 87,
      24 => 89,
      25 => 90
       );

$p_tmatrix = array(
     0 => 44,
     1 => 48,
     2 => 51,
     3 => 55,
     4 => 59,
     5 => 63,
     6 => 67,
     7 => 71,
     8 => 74,
     9 => 78,
     10 => 82,
     11 => 86
      );

$tr_tmatrix = array(
      0 => 41,
      1 => 44,
      2 => 46,
      3 => 52,
      4 => 56,
      5 => 59,
      6 => 63,
      7 => 67, 
      8 => 70,
      9 => 74,
      10 => 78,
      11 => 82,
      12 => 85,
      13 => 89,
      14 => 90
      );

$ha_tmatrix = array(
     12 => 40,
     13 => 42,
     14 => 45,
     15 => 47,
     16 => 50,
     17 => 52,
     18 => 55,
     19 => 57,
     20 => 60,
     21 => 62,
     22 => 65,
     23 => 67,
     24 => 70
      );


function lookup_tvalue($tvalue_matrix, $raw_score) {

   $first_value = reset($tvalue_matrix);
   $first_key = key($tvalue_matrix);
   $last_value = end($tvalue_matrix);
   $last_key = key($tvalue_matrix);

   if ($raw_score <= $first_key) {
       $result = $first_value;
   } elseif ($raw_score >= $last_key) {
       $result = $last_value;
   } elseif (isset($tvalue_matrix[$raw_score])) {
       $result = $tvalue_matrix[$raw_score];
   } else {
       $result = NULL;
   }
   return $result;
}

# Go to the look up tables and retrieve the tvalues.
$masc2_pr_total_tval = lookup_tvalue($masc2_tmatrix, $masc2_total);
$masc2_pr_sp_tval  = lookup_tvalue($sp_tmatrix, $sp_val);
$masc2_pr_gad_tval = lookup_tvalue($gad_tmatrix, $gad_val);
$masc2_pr_sa_tval  = lookup_tvalue($sa_tmatrix, $sat_val);
$masc2_pr_hr_tval  = lookup_tvalue($hr_tmatrix, $hr_val);
$masc2_pr_pf_tval  = lookup_tvalue($pf_tmatrix, $pf_val);
$masc2_pr_oc_tval  = lookup_tvalue($oc_tmatrix, $oc_val);
$masc2_pr_pst_tval = lookup_tvalue($pst_tmatrix, $pst_val);
$masc2_pr_p_tval   = lookup_tvalue($p_tmatrix, $p_val);
$masc2_pr_tr_tval  = lookup_tvalue($tr_tmatrix, $tr_val);
$masc2_pr_ha_tval  = lookup_tvalue($ha_tmatrix, $ha_val);

if ($masc2_pr_total_tval <= 39)
{
  $sig_vals['masc2_pr_total_sig'] = "Low";
}
else if ($masc2_pr_total_tval >= 40 and $masc2_pr_total_tval <= 54)
{
  $sig_vals['masc2_pr_total_sig'] = "Average";
}
else if ($masc2_pr_total_tval >= 55 and $masc2_pr_total_tval <= 59)
{
  $sig_vals['masc2_pr_total_sig'] = "High Average";
}
else if ($masc2_pr_total_tval >= 60 and $masc2_pr_total_tval <= 64)
{
  $sig_vals['masc2_pr_total_sig'] = "Slightly Elevated";
}
else if ($masc2_pr_total_tval >= 65 and $masc2_pr_total_tval <= 69)
{
  $sig_vals['masc2_pr_total_sig'] = "Elevated";
}
else if ($masc2_pr_total_tval >= 70)
{
  $sig_vals['masc2_pr_total_sig'] = "Very Elevated";
}



if ($masc2_pr_sp_tval <= 39)
{
  $sig_vals['masc2_pr_sp_sig'] = "Low";
}
else if ($masc2_pr_sp_tval >= 40 and $masc2_pr_sp_tval <= 54)
{
  $sig_vals['masc2_pr_sp_sig'] = "Average";
}
else if ($masc2_pr_sp_tval >= 55 and $masc2_pr_sp_tval <= 59)
{
  $sig_vals['masc2_pr_sp_sig'] = "High Average";
}
else if ($masc2_pr_sp_tval >= 60 and $masc2_pr_sp_tval <= 64)
{
  $sig_vals['masc2_pr_sp_sig'] = "Slightly Elevated";
}
else if ($masc2_pr_sp_tval >= 65 and $masc2_pr_sp_tval <= 69)
{
  $sig_vals['masc2_pr_sp_sig'] = "Elevated";
}
else if ($masc2_pr_sp_tval >= 70)
{
  $sig_vals['masc2_pr_sp_sig'] = "Very Elevated";
}

if ($masc2_pr_gad_tval <= 39)
{
  $sig_vals['masc2_pr_gad_sig'] = "Low";
}
else if ($masc2_pr_gad_tval >= 40 and $masc2_pr_gad_tval <= 54)
{
  $sig_vals['masc2_pr_gad_sig'] = "Average";
}
else if ($masc2_pr_gad_tval >= 55 and $masc2_pr_gad_tval <= 59)
{
  $sig_vals['masc2_pr_gad_sig'] = "High Average";
}
else if ($masc2_pr_gad_tval >= 60 and $masc2_pr_gad_tval <= 64)
{
  $sig_vals['masc2_pr_gad_sig'] = "Slightly Elevated";
}
else if ($masc2_pr_gad_tval >= 65 and $masc2_pr_gad_tval <= 69)
{
  $sig_vals['masc2_pr_gad_sig'] = "Elevated";
}
else if ($masc2_pr_gad_tval >= 70)
{
  $sig_vals['masc2_pr_gad_sig'] = "Very Elevated";
}

if ($masc2_pr_sa_tval <= 39)
{
  $sig_vals['masc2_pr_sa_sig'] = "Low";
}
else if ($masc2_pr_sa_tval >= 40 and $masc2_pr_sa_tval <= 54)
{
  $sig_vals['masc2_pr_sa_sig'] = "Average";
}
else if ($masc2_pr_sa_tval >= 55 and $masc2_pr_sa_tval <= 59)
{
  $sig_vals['masc2_pr_sa_sig'] = "High Average";
}
else if ($masc2_pr_sa_tval >= 60 and $masc2_pr_sa_tval <= 64)
{
  $sig_vals['masc2_pr_sa_sig'] = "Slightly Elevated";
}
else if ($masc2_pr_sa_tval >= 65 and $masc2_pr_sa_tval <= 69)
{
  $sig_vals['masc2_pr_sa_sig'] = "Elevated";
}
else if ($masc2_pr_sa_tval >= 70)
{
  $sig_vals['masc2_pr_sa_sig'] = "Very Elevated";
}

if ($masc2_pr_hr_tval <= 39)
{
  $sig_vals['masc2_pr_hr_sig'] = "Low";
}
else if ($masc2_pr_hr_tval >= 40 and $masc2_pr_hr_tval <= 54)
{
  $sig_vals['masc2_pr_hr_sig'] = "Average";
}
else if ($masc2_pr_hr_tval >= 55 and $masc2_pr_hr_tval <= 59)
{
  $sig_vals['masc2_pr_hr_sig'] = "High Average";
}
else if ($masc2_pr_hr_tval >= 60 and $masc2_pr_hr_tval <= 64)
{
  $sig_vals['masc2_pr_hr_sig'] = "Slightly Elevated";
}
else if ($masc2_pr_hr_tval >= 65 and $masc2_pr_hr_tval <= 69)
{
  $sig_vals['masc2_pr_hr_sig'] = "Elevated";
}
else if ($masc2_pr_hr_tval >= 70)
{
  $sig_vals['masc2_pr_hr_sig'] = "Very Elevated";
}

if ($masc2_pr_pf_tval <= 39)
{
  $sig_vals['masc2_pr_pf_sig'] = "Low";
}
else if ($masc2_pr_pf_tval >= 40 and $masc2_pr_pf_tval <= 54)
{
  $sig_vals['masc2_pr_pf_sig'] = "Average";
}
else if ($masc2_pr_pf_tval >= 55 and $masc2_pr_pf_tval <= 59)
{
  $sig_vals['masc2_pr_pf_sig'] = "High Average";
}
else if ($masc2_pr_pf_tval >= 60 and $masc2_pr_pf_tval <= 64)
{
  $sig_vals['masc2_pr_pf_sig'] = "Slightly Elevated";
}
else if ($masc2_pr_pf_tval >= 65 and $masc2_pr_pf_tval <= 69)
{
  $sig_vals['masc2_pr_pf_sig'] = "Elevated";
}
else if ($masc2_pr_pf_tval >= 70)
{
  $sig_vals['masc2_pr_pf_sig'] = "Very Elevated";
}

if ($masc2_pr_oc_tval <= 39)
{
  $sig_vals['masc2_pr_oc_sig'] = "Low";
}
else if ($masc2_pr_oc_tval >= 40 and $masc2_pr_oc_tval <= 54)
{
  $sig_vals['masc2_pr_oc_sig'] = "Average";
}
else if ($masc2_pr_oc_tval >= 55 and $masc2_pr_oc_tval <= 59)
{
  $sig_vals['masc2_pr_oc_sig'] = "High Average";
}
else if ($masc2_pr_oc_tval >= 60 and $masc2_pr_oc_tval <= 64)
{
  $sig_vals['masc2_pr_oc_sig'] = "Slightly Elevated";
}
else if ($masc2_pr_oc_tval >= 65 and $masc2_pr_oc_tval <= 69)
{
  $sig_vals['masc2_pr_oc_sig'] = "Elevated";
}
else if ($masc2_pr_oc_tval >= 70)
{
  $sig_vals['masc2_pr_oc_sig'] = "Very Elevated";
}

if ($masc2_pr_pst_tval <= 39)
{
  $sig_vals['masc2_pr_pst_sig'] = "Low";
}
else if ($masc2_pr_pst_tval >= 40 and $masc2_pr_pst_tval <= 54)
{
  $sig_vals['masc2_pr_pst_sig'] = "Average";
}
else if ($masc2_pr_pst_tval >= 55 and $masc2_pr_pst_tval <= 59)
{
  $sig_vals['masc2_pr_pst_sig'] = "High Average";
}
else if ($masc2_pr_pst_tval >= 60 and $masc2_pr_pst_tval <= 64)
{
  $sig_vals['masc2_pr_pst_sig'] = "Slightly Elevated";
}
else if ($masc2_pr_pst_tval >= 65 and $masc2_pr_pst_tval <= 69)
{
  $sig_vals['masc2_pr_pst_sig'] = "Elevated";
}
else if ($masc2_pr_pst_tval >= 70)
{
  $sig_vals['masc2_pr_pst_sig'] = "Very Elevated";
}

if ($masc2_pr_p_tval <= 39)
{
  $sig_vals['masc2_pr_p_sig'] = "Low";
}
else if ($masc2_pr_p_tval >= 40 and $masc2_pr_p_tval <= 54)
{
  $sig_vals['masc2_pr_p_sig'] = "Average";
}
else if ($masc2_pr_p_tval >= 55 and $masc2_pr_p_tval <= 59)
{
  $sig_vals['masc2_pr_p_sig'] = "High Average";
}
else if ($masc2_pr_p_tval >= 60 and $masc2_pr_p_tval <= 64)
{
  $sig_vals['masc2_pr_p_sig'] = "Slightly Elevated";
}
else if ($masc2_pr_p_tval >= 65 and $masc2_pr_p_tval <= 69)
{
  $sig_vals['masc2_pr_p_sig'] = "Elevated";
}
else if ($masc2_pr_p_tval >= 70)
{
  $sig_vals['masc2_pr_p_sig'] = "Very Elevated";
}

if ($masc2_pr_tr_tval <= 39)
{
  $sig_vals['masc2_pr_tr_sig'] = "Low";
}
else if ($masc2_pr_tr_tval >= 40 and $masc2_pr_tr_tval <= 54)
{
  $sig_vals['masc2_pr_tr_sig'] = "Average";
}
else if ($masc2_pr_tr_tval >= 55 and $masc2_pr_tr_tval <= 59)
{
  $sig_vals['masc2_pr_tr_sig'] = "High Average";
}
else if ($masc2_pr_tr_tval >= 60 and $masc2_pr_tr_tval <= 64)
{
  $sig_vals['masc2_pr_tr_sig'] = "Slightly Elevated";
}
else if ($masc2_pr_tr_tval >= 65 and $masc2_pr_tr_tval <= 69)
{
  $sig_vals['masc2_pr_tr_sig'] = "Elevated";
}
else if ($masc2_pr_tr_tval >= 70)
{
  $sig_vals['masc2_pr_tr_sig'] = "Very Elevated";
}


if ($masc2_pr_ha_tval <= 39)
{
  $sig_vals['masc2_pr_ha_sig'] = "Low";
}
else if ($masc2_pr_ha_tval >= 40 and $masc2_pr_ha_tval <= 54)
{
  $sig_vals['masc2_pr_ha_sig'] = "Average";
}
else if ($masc2_pr_ha_tval >= 55 and $masc2_pr_ha_tval <= 59)
{
  $sig_vals['masc2_pr_ha_sig'] = "High Average";
}
else if ($masc2_pr_ha_tval >= 60 and $masc2_pr_ha_tval <= 64)
{
  $sig_vals['masc2_pr_ha_sig'] = "Slightly Elevated";
}
else if ($masc2_pr_ha_tval >= 65 and $masc2_pr_ha_tval <= 69)
{
  $sig_vals['masc2_pr_ha_sig'] = "Elevated";
}
else if ($masc2_pr_ha_tval >= 70)
{
  $sig_vals['masc2_pr_ha_sig'] = "Very Elevated";
}


# Calculate the Anxiety Probability Score.  Add a point for each of the 3 tvals >= 60: Separation Anxiety/Phobias,
#                GAD Index and Separation Anxiety: Total
$masc2_pr_anx_prob = 0;
if ($masc2_pr_sp_tval >= 60) $masc2_pr_anx_prob++;
if ($masc2_pr_gad_tval >= 60) $masc2_pr_anx_prob++;
if ($masc2_pr_sa_tval >= 60) $masc2_pr_anx_prob++;

$result_values = array($sp_val, $gad_val, $hr_val, $pf_val, $oc_val, $p_val, $tr_val, $ha_val,
                       $sat_val, $pst_val, $masc2_total, $inconsistancy_idx,
                       $masc2_pr_total_tval, $masc2_pr_sp_tval, $masc2_pr_gad_tval, $masc2_pr_sa_tval,
                       $masc2_pr_hr_tval,
                       $masc2_pr_pf_tval, $masc2_pr_oc_tval,
                       $masc2_pr_pst_tval, $masc2_pr_p_tval,
                       $masc2_pr_tr_tval, $masc2_pr_ha_tval, $masc2_pr_anx_prob);


if ($masc2_pr_anx_prob == 0)
{
  $sig_vals['masc2_pr_anx_sig'] = "Low Probability";
}
else if ($masc2_pr_anx_prob == 1)
{
  $sig_vals['masc2_pr_anx_sig'] = "Borderline Probability";
}
else if ($masc2_pr_anx_prob == 2)
{
  $sig_vals['masc2_pr_anx_sig'] = "High Probability";
}
else if ($masc2_pr_anx_prob == 3)
{
  $sig_vals['masc2_pr_anx_sig'] = "Very High Probability";
}


### DEFINE RESULTS ###
# REQUIRED: The algorithm_results variable MUST BE USED as it is relied upon from the parent script.
#$algorithm_results = array_combine($default_result_fields, $result_values, $sig_vals);
$algorithm_results = array_combine($default_result_fields, $result_values);

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

