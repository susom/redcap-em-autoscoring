<?php
/**

	RCADS 25 - Short Form Children

	Developed by Kim Wijaya and Alex Basile for ELSPAP June 2016

	Based on SPSS Scorign syntax, available at http://www.childfirst.ucla.edu/Resources.html



**/

$algorithm_summary = "RCADS - Revised Children's Anxiety and Depression Scale - Child";
$this->module->emDebug("Scoring Title: " . $algorithm_summary);

$default_result_fields = array(
	'rcadsc_dep_raw', 'rcadsc_anx_raw', 'rcadsc_tot_raw',
	'rcadsc_dep_null', 'rcadsc_anx_null', 'rcadsc_tot_null',
	'rcadsc_dep_tval', 'rcadsc_anx_tval', 'rcadsc_tot_tval',
	'rcadsc_dep_elev', 'rcadsc_anx_elev', 'rcadsc_tot_elev'
	);

$required_fields = array();
foreach (range(1,25) as $i) {
	array_push($required_fields, "rcads_c_25_q$i");
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

$source_names = array("dep", "anx", "tot"); 
$source_indexes = array (
	'dep' => array(1, 4, 8, 10, 13, 15, 16, 19, 21, 24),
	'anx' => array(2,3,5,6,7,9,11,12,14,17,18,20,22,23,25),
	'tot' => array(1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25)
);

$raw_scores = array();
$null_counter = array();
$tvalue_scores = array();
foreach ($source_names as $i => $field_name) {
	$raw_scores[$field_name . '_raw'] = 0;
	$null_counter[$field_name . '_null'] = 0;
}

foreach ($required_fields as $i => $field_name) {
	$val = $src[$field_name];
	$index = $i+1;

	foreach ($source_names as $j => $result_name) {
		$target_result = $source_indexes[$result_name];
		if (in_array($index, $target_result)) {
			if (isset($val) and strlen($val) > 0) {
				$raw_scores[$result_name . '_raw'] += $val;
			} else {
				$null_counter[$result_name . '_null']++;
//				$this->module->emDebug("null value $val for field_name = $field_name, index = $index, null counter = $null_counter[$result_name]");
			}
       		}
	}
}

if ($null_counter['dep_null'] <= 2)
{
	$raw_scores['dep_raw'] = ($raw_scores['dep_raw']/(10-$null_counter['dep_null']))*10;
}
else
{
	$raw_scores['dep_anx'] = -1;
}

if ($null_counter['anx_null'] <= 2)
{
	$raw_scores['anx_raw'] = ($raw_scores['anx_raw']/(15-$null_counter['anx_null']))*15;
}
else
{
	$raw_scores['anx_raw'] = -1;
}

if ($null_counter['tot_null'] <= 4)
{
	$raw_scores['tot_raw'] = ($raw_scores['tot_raw']/(25-$null_counter['tot_null']))*25;
}
else
{
	$raw_scores['tot_raw'] = -1;
}

$grade = $src['rcads_c_grade'];
$sex = $src['rcads_c_sex'];

if ($grade <= 4)
{
	if ($sex == 0)
	{
		$tvalue_scores['dep_tval'] = (($raw_scores['dep_raw']-9.90)*10)/4.93+50;
		$tvalue_scores['anx_tval'] = (($raw_scores['anx_raw']-15.19)*10)/7.09+50;
		$tvalue_scores['tot_tval'] = (($raw_scores['tot_raw']-25.10)*10)/11.10+50;
	}
	else
	{
		$tvalue_scores['dep_tval'] = (($raw_scores['dep_raw']-9.68)*10)/4.97+50;
		$tvalue_scores['anx_tval'] = (($raw_scores['anx_raw']-16.25)*10)/8.42+50;
		$tvalue_scores['tot_tval'] = (($raw_scores['tot_raw']-25.93)*10)/12.24+50;
	}
}
else if ($grade == 5 or $grade == 6)
{
	if ($sex == 0)
	{
		$tvalue_scores['dep_tval'] = (($raw_scores['dep_raw']-7.13)*10)/4.22+50;
		$tvalue_scores['anx_tval'] = (($raw_scores['anx_raw']-11.63)*10)/6.45+50;
		$tvalue_scores['tot_tval'] = (($raw_scores['tot_raw']-18.76)*10)/9.38+50;
	}
	else
	{
		$tvalue_scores['dep_tval'] = (($raw_scores['dep_raw']-8.03)*10)/5.00+50;
		$tvalue_scores['anx_tval'] = (($raw_scores['anx_raw']-13.49)*10)/7.62+50;
		$tvalue_scores['tot_tval'] = (($raw_scores['tot_raw']-21.53)*10)/11.73+50;
	}
}
else if ($grade == 7 or $grade == 8)
{
		if ($sex == 0)
	{
		$tvalue_scores['dep_tval'] = (($raw_scores['dep_raw']-7.56)*10)/3.75+50;
		$tvalue_scores['anx_tval'] = (($raw_scores['anx_raw']-10.48)*10)/5.36+50;
		$tvalue_scores['tot_tval'] = (($raw_scores['tot_raw']-18.04)*10)/7.92+50;
	}
	else
	{
		$tvalue_scores['dep_tval'] = (($raw_scores['dep_raw']-8.08)*10)/4.34+50;
		$tvalue_scores['anx_tval'] = (($raw_scores['anx_raw']-12.74)*10)/6.22+50;
		$tvalue_scores['tot_tval'] = (($raw_scores['tot_raw']-20.82)*10)/9.46+50;
	}
}
else if ($grade == 9 or $grade == 10)
{
		if ($sex == 0)
	{
		$tvalue_scores['dep_tval'] = (($raw_scores['dep_raw']-7.50)*10)/4.18+50;
		$tvalue_scores['anx_tval'] = (($raw_scores['anx_raw']-9.70)*10)/5.45+50;
		$tvalue_scores['tot_tval'] = (($raw_scores['tot_raw']-17.20)*10)/8.53+50;
	}
	else
	{
		$tvalue_scores['dep_tval'] = (($raw_scores['dep_raw']-8.14)*10)/4.37+50;
		$tvalue_scores['anx_tval'] = (($raw_scores['anx_raw']-11.31)*10)/5.33+50;
		$tvalue_scores['tot_tval'] = (($raw_scores['tot_raw']-19.45)*10)/8.63+50;
	}
}
else if ($grade >= 11)
{
		if ($sex == 0)
	{
		$tvalue_scores['dep_tval'] = (($raw_scores['dep_raw']-7.64)*10)/4.37+50;
		$tvalue_scores['anx_tval'] = (($raw_scores['anx_raw']-9.96)*10)/4.32+50;
		$tvalue_scores['tot_tval'] = (($raw_scores['tot_raw']-17.60)*10)/7.24+50;
	}
	else
	{
		$tvalue_scores['dep_tval'] = (($raw_scores['dep_raw']-8.59)*10)/3.67+50;
		$tvalue_scores['anx_tval'] = (($raw_scores['anx_raw']-11.50)*10)/5.34+50;
		$tvalue_scores['tot_tval'] = (($raw_scores['tot_raw']-20.09)*10)/7.79+50;
	}
}


$elevations = array();
if ($tvalue_scores['tot_tval'] < 65)
{
	$elevations['tot_elev'] = "Normal";
}
else if ($tvalue_scores['tot_tval'] >= 65 and $tvalue_scores['tot_tval'] <= 69)
{
	$elevations['tot_elev'] = "Borderline";
}
else if ($tvalue_scores['tot_tval'] >= 70)
{
	$elevations['tot_elev'] = "Clinical";
}

if ($tvalue_scores['dep_tval'] < 65)
{
	$elevations['dep_elev'] = "Normal";
}
else if ($tvalue_scores['dep_tval'] >= 65 and $tvalue_scores['dep_tval'] <= 69)
{
	$elevations['dep_elev'] = "Borderline";
}
else if ($tvalue_scores['dep_tval'] >= 70)
{
	$elevations['dep_elev'] = "Clinical";
}

if ($tvalue_scores['anx_tval'] < 65)
{
	$elevations['anx_elev'] = "Normal";
}
else if ($tvalue_scores['anx_tval'] >= 65 and $tvalue_scores['anx_tval'] <= 69)
{
	$elevations['anx_elev'] = "Borderline";
}
else if ($tvalue_scores['anx_tval'] >= 70)
{
	$elevations['anx_elev'] = "Clinical";
}

### DEFINE RESULTS ###
# REQUIRED: The algorithm_results variable MUST BE USED as it is relied upon from the parent script.
$totals =  array_merge($raw_scores, $null_counter, $tvalue_scores, $elevations);
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