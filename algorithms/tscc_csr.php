<?php
/**

	Trauma Sympton Checklist for Children
	
	A REDCap AutoScoring Algorithm File

  Developed by Kim Wijaya and Alex Basile for ELSPAP June 2016
	
	- There exists an array called $src that contains the data from the source project
	- There can exist an optional array called $manual_result_fields that can override the default_result_fields
	- The final results should be presented in an array called $algorithm_results
        - Each answer in the source file is:
                  0 = Never
                  1 = Sometimes
                  2 = Lots of Times
                  3 = Almost all of the time

**/

# REQUIRED: Summarize this algorithm
$algorithm_summary = "The Trauma Sympton Checklist for Children Scoring";
$this->module->emDebug("Scoring Title: " . $algorithm_summary);


# REQUIRED: Define an array of default result field_names to record the summary data
# The TSCC Validity Scales are:
#    Missing Responses (MIS), Underresponse (UND), Hyperresponse (HYP)
# This clinical scales/subscales are: 
#    Anxiety (ANX), Depression (DEP), Anger (ANG), Posttraumatic Stress (PTS),
#    Dissociation (DIS), Overt Dissociation (DIS-O), Fantasy (DIS-F), Sexual Concerns (SC),
#    Sexual Preoccupation (SC-P), Sexual Distress (SC-D)
$default_result_fields = array(
        'tscc_missed_total',
        'tscc_und_raw',
        'tscc_hyp_raw',
	'tscc_anx_raw',
	'tscc_dep_raw',
	'tscc_ang_raw',
	'tscc_pts_raw',
	'tscc_dis_raw',
	'tscc_dis_o_raw',
        'tscc_dis_f_raw',
        'tscc_sc_raw',
        'tscc_sc_p_raw',
        'tscc_sc_d_raw',
        'tscc_und_tscore',
        'tscc_hyp_tscore',
        'tscc_anx_tscore', 
        'tscc_dep_tscore',
        'tscc_ang_tscore',
        'tscc_pts_tscore',
        'tscc_dis_tscore',
        'tscc_dis_o_tscore',
        'tscc_dis_f_tscore',
        'tscc_sc_tscore',
        'tscc_sc_p_tscore',
        'tscc_sc_d_tscore',
        'tscc_und_sig',
        'tscc_hyp_sig',
        'tscc_anx_sig',
        'tscc_dep_sig',
        'tscc_ang_sig',
        'tscc_pts_sig',
        'tscc_dis_sig',
        'tscc_dis_o_sig',
        'tscc_dis_f_sig',
        'tscc_sc_sig',
        'tscc_sc_p_sig',
        'tscc_sc_d_sig'
);

# REQUIRED: Define an array of fields that must be present for this algorithm to run
$required_fields = array();
foreach (range(1,54) as $i) {
	array_push($required_fields, "tscc_q$i");
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
#	return false;
}

### IMPLEMENT RAW SCORING ###
# This is the array source
# Define lists of questions that correspond to each subscale
$und_idx = array(1,2,6,9,10,19,28,41,49,53);
$hyp_idx = array(1,18,24,25,26,27,39,45);
$anx_idx = array(2,15,24,25,32,33,39,41,50);
$dep_idx = array(7,9,14,20,26,27,28,42,52);
$ang_idx = array(6,13,16,19,21,36,37,46,49);
$pts_idx = array(1,3,10,11,12,24,25,35,43,51);
$dis_idx = array(5,11,18,29,30,31,38,45,48,53);
$dis_o_idx = array(11,18,29,30,31,45,48);
$dis_f_idx = array(5,38,53);
$sc_idx = array(4,8,17,22,23,34,40,44,47,54);
$sc_p_idx = array(4,8,17,22,23,44,47,);
$sc_d_idx = array(23,34,40,54);
$und_val = 0;
$hyp_val = 0;
$anx_val = 0;
$dep_val = 0;
$ang_val = 0;
$pts_val = 0;
$dis_val = 0;
$dis_o_val = 0;
$dis_f_val = 0;
$sc_val = 0;
$sc_p_val = 0;
$sc_d_val = 0;

foreach ($required_fields as $i => $field_name) {
	$val = $src[$field_name];
	if (in_array($i+1, $und_idx) && ($val == 0)) $und_val += 1;
	if (in_array($i+1, $hyp_idx) && ($val == 3)) $hyp_val += 1;
	if (in_array($i+1, $anx_idx)) $anx_val += $val;
	if (in_array($i+1, $dep_idx)) $dep_val += $val;
	if (in_array($i+1, $ang_idx)) $ang_val += $val;
	if (in_array($i+1, $pts_idx)) $pts_val += $val;
	if (in_array($i+1, $dis_idx)) $dis_val += $val;
	if (in_array($i+1, $dis_o_idx)) $dis_o_val += $val;
	if (in_array($i+1, $dis_f_idx)) $dis_f_val += $val;
	if (in_array($i+1, $sc_idx)) $sc_val += $val;
	if (in_array($i+1, $sc_p_idx)) $sc_p_val += $val;
	if (in_array($i+1, $sc_d_idx)) $sc_d_val += $val;
}


### Now get the T values from the grid
# This is the grid that will be used correlating raw values to T values

$und_tmatrix = array(
      0 =>  42,
      1 =>  47,
      2 =>  52,
      3 =>  57,
      4 =>  62,
      5 =>  67,
      6 =>  72,
      7 =>  77,
      8 =>  81,
      9 =>  86,
     10 =>  91
      );

$hyp_tmatrix = array(
      0 =>  47,
      1 =>  65,
      2 =>  83,
      3 =>  102,
      4 =>  111,
      5 =>  111,
      6 =>  111,
      7 =>  111,
      8 =>  111
      );

$anx_tmatrix = array(
      0 =>  35,
      1 =>  35,
      2 =>  37,
      3 =>  39,
      4 =>  41,
      5 =>  43,
      6 =>  46,
      7 =>  48,
      8 =>  51,
      9 =>  54,
     10 =>  57,
     11 =>  59,
     12 =>  61,
     13 =>  64,
     14 =>  66,
     15 =>  69,
     16 =>  71,
     17 =>  74,
     18 =>  76,
     19 =>  79,
     20 =>  81,
     21 =>  84,
     22 =>  86,
     23 =>  89,
     24 =>  91,
     25 =>  93,
     26 =>  96,
     27 =>  98
      );

$dep_tmatrix = array(
      0 =>  35,
      1 =>  35,
      2 =>  36,
      3 =>  39,
      4 =>  41,
      5 =>  43,
      6 =>  46,
      7 =>  48,
      8 =>  51,
      9 =>  53,
     10 =>  55,
     11 =>  58,
     12 =>  60,
     13 =>  62,
     14 =>  65,
     15 =>  67,
     16 =>  69,
     17 =>  72,
     18 =>  74,
     19 =>  77,
     20 =>  79,
     21 =>  81,
     22 =>  84,
     23 =>  86,
     24 =>  88,
     25 =>  91,
     26 =>  93,
     27 =>  95
      );

$ang_tmatrix = array(
      0 =>  35,
      1 =>  36,
      2 =>  38,
      3 =>  40,
      4 =>  42,
      5 =>  44,
      6 =>  46,
      7 =>  48,
      8 =>  50,
      9 =>  51,
     10 =>  53,
     11 =>  55,
     12 =>  57,
     13 =>  59,
     14 =>  61,
     15 =>  63,
     16 =>  65,
     17 =>  67,
     18 =>  68,
     19 =>  70,
     20 =>  72,
     21 =>  74,
     22 =>  76,
     23 =>  78,
     24 =>  80,
     25 =>  82,
     26 =>  84,
     27 =>  85
      );

$pts_tmatrix = array(
      0 =>  35,
      1 =>  35,
      2 =>  36,
      3 =>  38,
      4 =>  40,
      5 =>  42,
      6 =>  44,
      7 =>  45,
      8 =>  47,
      9 =>  49,
     10 =>  51,
     11 =>  53,
     12 =>  54,
     13 =>  56,
     14 =>  58,
     15 =>  60,
     16 =>  62,
     17 =>  64,
     18 =>  65,
     19 =>  67,
     20 =>  69,
     21 =>  71,
     22 =>  73,
     23 =>  74,
     24 =>  76,
     25 =>  78,
     26 =>  80,
     27 =>  82,
     28 =>  83,
     29 =>  85,
     30 =>  87
      );

$dis_tmatrix = array(
      0 =>  35,
      1 =>  37,
      2 =>  39,
      3 =>  41,
      4 =>  43,
      5 =>  45,
      6 =>  47,
      7 =>  49,
      8 =>  51,
      9 =>  53,
     10 =>  55,
     11 =>  57,
     12 =>  59,
     13 =>  61,
     14 =>  63,
     15 =>  65,
     16 =>  67,
     17 =>  69,
     18 =>  71,
     19 =>  73,
     20 =>  75,
     21 =>  77,
     22 =>  79,
     23 =>  81,
     24 =>  83,
     25 =>  85,
     26 =>  87,
     27 =>  89,
     28 =>  91,
     29 =>  93,
     30 =>  95
      );

$dis_o_tmatrix = array(
      0 =>  37,
      1 =>  40,
      2 =>  42,
      3 =>  45,
      4 =>  48,
      5 =>  51,
      6 =>  54,
      7 =>  56,
      8 =>  59,
      9 =>  62,
     10 =>  65,
     11 =>  68,
     12 =>  70,
     13 =>  73,
     14 =>  76,
     15 =>  79,
     16 =>  82,
     17 =>  84,
     18 =>  87,
     19 =>  90,
     20 =>  93,
     21 =>  96
      );

$dis_f_tmatrix = array(
      0 =>  37,
      1 =>  42,
      2 =>  47,
      3 =>  52,
      4 =>  57,
      5 =>  62,
      6 =>  67,
      7 =>  72,
      8 =>  77,
      9 =>  82
      );

$sc_tmatrix = array(
      0 =>  41,
      1 =>  46,
      2 =>  52,
      3 =>  57,
      4 =>  62,
      5 =>  67,
      6 =>  72,
      7 =>  77,
      8 =>  82,
      9 =>  88,
     10 =>  93,
     11 =>  98,
     12 =>  103,
     13 =>  108,
     14 =>  111,
     15 =>  111,
     16 =>  111,
     17 =>  111,
     18 =>  111,
     19 =>  111,
     20 =>  111,
     21 =>  111,
     22 =>  111,
     23 =>  111,
     24 =>  111,
     25 =>  111,
     26 =>  111,
     27 =>  111,
     28 =>  111,
     29 =>  111,
     30 =>  111
      );

$sc_p_tmatrix = array(
      0 =>  43,
      1 =>  50,
      2 =>  58,
      3 =>  66,
      4 =>  73,
      5 =>  81,
      6 =>  89,
      7 =>  96,
      8 =>  104,
      9 =>  111,
     10 =>  111,
     11 =>  111,
     12 =>  111
      );

$sc_d_tmatrix = array(
      0 =>  43,
      1 =>  52,
      2 =>  61,
      3 =>  69,
      4 =>  78,
      5 =>  87,
      6 =>  96,
      7 =>  104,
      8 =>  111,
      9 =>  111,
     10 =>  111,
     11 =>  111,
     12 =>  111
      );

function lookup_tvalue($tvalue_matrix, $raw_score) {

   if (isset($tvalue_matrix[$raw_score])) {
	$result = $tvalue_matrix[$raw_score];
	if ($result == 35) {
		$result = "&#8804;" . $result;
	}
   } else {
	$result = NULL;
   }
   return $result;
}

$und_tval   = lookup_tvalue($und_tmatrix, $und_val);
$hyp_tval   = lookup_tvalue($hyp_tmatrix, $hyp_val);
$anx_tval   = lookup_tvalue($anx_tmatrix, $anx_val);
$dep_tval   = lookup_tvalue($dep_tmatrix, $dep_val);
$ang_tval   = lookup_tvalue($ang_tmatrix, $ang_val);
$pts_tval   = lookup_tvalue($pts_tmatrix, $pts_val);
$dis_tval   = lookup_tvalue($dis_tmatrix, $dis_val);
$dis_o_tval = lookup_tvalue($dis_o_tmatrix, $dis_o_val);
$dis_f_tval = lookup_tvalue($dis_f_tmatrix, $dis_f_val);
$sc_tval    = lookup_tvalue($sc_tmatrix, $sc_val);
$sc_d_tval  = lookup_tvalue($sc_d_tmatrix, $sc_d_val);
$sc_p_tval  = lookup_tvalue($sc_p_tmatrix, $sc_p_val);


# Put the raw values into the return array
$result_values = array($num_null_fields, $und_val, $hyp_val, $anx_val, $dep_val, $ang_val, $pts_val, $dis_val,
                       $dis_o_val, $dis_f_val, $sc_val, $sc_p_val, $sc_d_val,
                       $und_tval, $hyp_tval, $anx_tval, $dep_tval, $ang_tval, $pts_tval, $dis_tval,
                       $dis_o_tval, $dis_f_tval, $sc_tval, $sc_p_tval, $sc_d_tval);


$sig_vals = array();

if ($und_tval <= 59)
{
  $sig_vals['tscc_und_sig'] = "Average";
}
else if ($und_tval >= 60 and $und_tval <= 64)
{
  $sig_vals['tscc_und_sig'] = "Suggested Difficulty";
}
else if ($und_tval >= 65)
{
  $sig_vals['tscc_und_sig'] = "Clinically Significant";
}

if ($hyp_tval <= 59)
{
  $sig_vals['tscc_hyp_sig'] = "Average";
}
else if ($hyp_tval >= 60 and $hyp_tval <= 64)
{
  $sig_vals['tscc_hyp_sig'] = "Suggested Difficulty";
}
else if ($hyp_tval >= 65)
{
  $sig_vals['tscc_hyp_sig'] = "Clinically Significant";
}

if ($anx_tval <= 59)
{
  $sig_vals['tscc_anx_sig'] = "Average";
}
else if ($anx_tval >= 60 and $anx_tval <= 64)
{
  $sig_vals['tscc_anx_sig'] = "Suggested Difficulty";
}
else if ($anx_tval >= 65)
{
  $sig_vals['tscc_anx_sig'] = "Clinically Significant";
}

if ($dep_tval <= 59)
{
  $sig_vals['tscc_dep_sig'] = "Average";
}
else if ($dep_tval >= 60 and $dep_tval <= 64)
{
  $sig_vals['tscc_dep_sig'] = "Suggested Difficulty";
}
else if ($dep_tval >= 65)
{
  $sig_vals['tscc_dep_sig'] = "Clinically Significant";
}

if ($ang_tval <= 59)
{
  $sig_vals['tscc_ang_sig'] = "Average";
}
else if ($ang_tval >= 60 and $ang_tval <= 64)
{
  $sig_vals['tscc_ang_sig'] = "Suggested Difficulty";
}
else if ($ang_tval >= 65)
{
  $sig_vals['tscc_ang_sig'] = "Clinically Significant";
}

if ($pts_tval <= 59)
{
  $sig_vals['tscc_pts_sig'] = "Average";
}
else if ($pts_tval >= 60 and $pts_tval <= 64)
{
  $sig_vals['tscc_pts_sig'] = "Suggested Difficulty";
}
else if ($pts_tval >= 65)
{
  $sig_vals['tscc_pts_sig'] = "Clinically Significant";
}

if ($dis_tval <= 59)
{
  $sig_vals['tscc_dis_sig'] = "Average";
}
else if ($dis_tval >= 60 and $dis_tval <= 64)
{
  $sig_vals['tscc_dis_sig'] = "Suggested Difficulty";
}
else if ($dis_tval >= 65)
{
  $sig_vals['tscc_dis_sig'] = "Clinically Significant";
}

if ($dis_o_tval <= 59)
{
  $sig_vals['tscc_dis_o_sig'] = "Average";
}
else if ($dis_o_tval >= 60 and $dis_o_tval <= 64)
{
  $sig_vals['tscc_dis_o_sig'] = "Suggested Difficulty";
}
else if ($dis_o_tval >= 65)
{
  $sig_vals['tscc_dis_o_sig'] = "Clinically Significant";
}

if ($dis_f_tval <= 59)
{
  $sig_vals['tscc_dis_f_sig'] = "Average";
}
else if ($dis_f_tval >= 60 and $dis_f_tval <= 64)
{
  $sig_vals['tscc_dis_f_sig'] = "Suggested Difficulty";
}
else if ($dis_f_tval >= 65)
{
  $sig_vals['tscc_dis_f_sig'] = "Clinically Significant";
}

if ($sc_tval <= 59)
{
  $sig_vals['tscc_sc_sig'] = "Average";
}
else if ($sc_tval >= 60 and $sc_tval <= 64)
{
  $sig_vals['tscc_sc_sig'] = "Suggested Difficulty";
}
else if ($sc_tval >= 65)
{
  $sig_vals['tscc_sc_sig'] = "Clinically Significant";
}


if ($sc_d_tval <= 59)
{
  $sig_vals['tscc_sc_p_sig'] = "Average";
}
else if ($sc_d_tval >= 60 and $sc_d_tval <= 64)
{
  $sig_vals['tscc_sc_p_sig'] = "Suggested Difficulty";
}
else if ($sc_d_tval >= 65)
{
  $sig_vals['tscc_sc_p_sig'] = "Clinically Significant";
}

if ($sc_p_tval <= 59)
{
  $sig_vals['tscc_sc_d_sig'] = "Average";
}
else if ($sc_p_tval >= 60 and $sc_p_tval <= 64)
{
  $sig_vals['tscc_sc_d_sig'] = "Suggested Difficulty";
}
else if ($sc_p_tval >= 65)
{
  $sig_vals['tscc_sc_d_sig'] = "Clinically Significant";
}


### DEFINE RESULTS ###
# REQUIRED: The algorithm_results variable MUST BE USED as it is relied upon from the parent script.
$algorithm_results = array_combine($default_result_fields, $result_values, $sig_vals);

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

