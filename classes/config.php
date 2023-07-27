<?php

namespace Stanford\Autoscore;
/** @var \Stanford\Autoscore\Autoscore $module **/

global $defined_algorithms;
# SET PARAMETERS FOR ACCESSING REDCap Autoscore Configuration PROJECT
define('CONFIG_ALGORITHM_PATH', $module->getModulePath() . 'algorithms/');

# DEFINE ALL ALGORITHMS HERE WITH THEIR CODED VALUE FROM THE DROPDOWN AND THEIR FILENAME
$defined_algorithms = array(
	//CODED VALUE	//FILENAME
	'abc' 				=>	'abc_v2.php',	// ABC scoring algorithm - changed to v2 11/11/2015 LY
	'rbs'				=>	'rbs-r_v1.php',	// RBS-R scoring algorithm
	'scq' 				=>	'scq_v1.php',	// Social Communication Questionnaire
	'bdi'				=>	'bdi_v1.php',
	'comm'				=>	'comm_v1.php',
	'asst'				=>	'asst_v1.php',
	'adhdt'				=>  'adhdt_v1.php',
	'srs2'				=>	'srs-2_v1.php',
	'srs2v2'			=>	'srs-2_v2.php',
	'rcmas2'			=>	'rcmas2_v1.php',
	'psi4'				=>  'psi4_v2.php',
	'psi4turner'		=>  'psi4_turner_v1.php',
	'psi4turner2'		=>  'psi4_turner_v2.php',
	'ehi'				=>  'ehi_v1.php',
	'ehiturner'			=>  'ehi_turner_v1.php',
	'pedsql'			=>	'pedsql_v1.php',
	'fes'				=>	'fes_v1.php',
	'brief'				=>	'brief_v2.php',
	'briefpre'			=>	'briefpre_v1.php',
	'tscc'              =>  'tscc.php',
	'masc2'             =>  'masc2_v1.php',
	'neo_ffi'           =>  'neo_ffi_v2.php',
	'rcmas2v2'			=>	'rcmas2_v2.php',
	'aadi'				=>  'aadi_v1.php',
	'basc_prs'			=>	'basc_prs.php',		// Created by Kimberly Wijaya working for Alex Basile
	'basc_srp'			=>	'basc_srp.php',		// Created by Kimberly Wijaya working for Alex Basile
	'basc2_prsa_gcs'    =>  'basc2_prsa_gcs.php',
	'basc2_prsc_gcs'    =>  'basc2_prsc_gcs.php',
	'basc2_srpa_gcs'    =>  'basc2_srpa_gcs.php',
	'basc2_srpc_gcs'    =>  'basc2_srpc_gcs.php',
	'basc2_srpi_gcs'    =>  'basc2_srpi_gcs.php',
	'masc2_parent_csr'  =>  'masc2_parent_csr.php',
	'masc2_child_csr'   =>  'masc2_child_csr.php',
	'tscc_csr'          =>  'tscc_csr.php',
	'rcadsc'            =>  'rcadsc.php',
	'rcadsp'            =>  'rcadsp.php',
	'rcad47sc'          =>  'rcad47sc.php',
	'stroke'			=>	'stroke.php',
	'rcft'				=>	'rcft.php',
	'brief2_v1'         =>  'brief2_v1.php',
	'stroke_v2'         =>  'stroke_v2.php',          // Same as stroke.php but with additional scoring
	'conner'			=>	'conners.php',
	'conner_teacher'	=> 	'conner_teachers.php',
	'cdi2'				=>  'cdi2.php',
	'ktea3'				=> 	'ktea3.php',
	'fes2'				=>  'fes2_v1.php',
	'stai'				=>  'stai_v1.php',
	'prq'				=>  'basc3_prq_v1.php',
	'scl90r'			=>  'scl90r_v1.php',
	'brief_a'			=>  'brief_a_sr_v1.php',
    'ped_ds8a_v2'       =>  'promise_pds8a_v2.php',
    'psi4_sf'           =>  'psi4_short_form.php'
);

?>
