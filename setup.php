<?php
namespace Stanford\Autoscore;
/** @var \Stanford\Autoscore\Autoscore $module */

global $project_id;

$autoscore_url = $module->getUrl("index", true, true) . "&pid=$project_id&NOAUTH";

echo "<b>Use this url to access AutoScoring from outside the EM (i.e. MassDET)</b><pre>" .
    $autoscore_url . "<br>";

exit();
?>
