<?php

// Copyright (c) 2016  Joe Clarke <jclarke@cisco.com>
// All rights reserved.

// Redistribution and use in source and binary forms, with or without
// modification, are permitted provided that the following conditions
// are met:
// 1. Redistributions of source code must retain the above copyright
//    notice, this list of conditions and the following disclaimer.
// 2. Redistributions in binary form must reproduce the above copyright
//    notice, this list of conditions and the following disclaimer in the
//    documentation and/or other materials provided with the distribution.

// THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND
// ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
// IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
// ARE DISCLAIMED.  IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE
// FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
// DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS
// OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
// HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
// LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
// OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
// SUCH DAMAGE.

include_once 'yang_db.inc.php';
include_once 'yang_catalog.inc.php';

$alerts = [];
$nodes = [];
$edges = [];
$module = '';
$title = 'Empty Impact Graph';

$dsn = $db_driver.':'.$db_file;
$opt = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $dbh = new PDO($dsn, $db_user, $db_pass, $opt);
} catch (PDOException $e) {
    push_exception('Failed to connect to DB', $e, $alerts);
}

if (!isset($_GET['module'])) {
    array_push($alerts, 'Module was not specified');
} else {
    $module = $_GET['module'];
    $nmodule = basename($module);
    if ($nmodule != $module) {
        array_push($alerts, 'Invalid module name specified');
        $module = '';
    } else {
        $title = "YANG Impact Graph for Module: '$module'";
        $f = YDEPS_DIR.'/'.$module.'.json';
        if (is_file($f)) {
            try {
                $contents = file_get_contents($f);
                $json = json_decode($contents, true);
                if ($json === null) {
                    array_push($alerts, 'Failed to decode JSON data');
                } else {
                    $color = get_color($module, $dbh, $alerts);
                    array_push($nodes, ['data' => ['id' => 'root', 'name' => $module, 'objColor' => $color]]);
                    $i = 0;
                    if (isset($json['impacted_modules'][$module])) {
                        foreach ($json['impacted_modules'][$module] as $mod) {
                            $color = get_color($mod, $dbh, $alerts);
                            array_push($nodes, ['data' => ['id' => "mod_$i", 'name' => $mod, 'objColor' => $color]]);
                            array_push($edges, ['data' => ['source' => 'root', 'target' => "mod_$i", 'objColor' => $color]]);
                            ++$i;
                        }
                    }
                    if (isset($json['impacting_modules'][$module])) {
                        foreach ($json['impacting_modules'][$module] as $mod) {
                            $color = get_color($mod, $dbh, $alerts);
                            array_push($nodes, ['data' => ['id' => "mod_$i", 'name' => $mod, 'objColor' => $color]]);
                            array_push($edges, ['data' => ['source' => "mod_$i", 'target' => 'root', 'objColor' => $color]]);
                            ++$i;
                        }
                    }
                }
            } catch (Exception $e) {
                push_exception("Failed to read dependency data for $module", $e, $alerts);
            }
        } else {
            array_push($alerts, "YANG dependency graph data does not exist for $module");
        }
    }
}

?>
<!DOCTYPE html>
<html>
  <head>
    <title><?=$title?></title>
    <?=BOOTSTRAP_CSS?>

		<?=BOOTSTRAP_THEME_CSS?>

		<?=JQUERY_JS?>

		<?=BOOTSTRAP_JS?>

		<?=CYTOSCAPE_JS?>

		<script language="javascript">
$(function() {
	$("#cy").cytoscape({
		layout: {
			name: 'cose',
			padding: 10,
			randomize: true,
		},
		style: cytoscape.stylesheet()
		  .selector('node')
		    .css({
		      'content'            : 'data(name)',
		      'text-valign'        : 'center',
		      'color'              : '#fff',
		      'text-outline-width' : 2,
		      'background-color'   : 'data(objColor)',
		      'text-outline-color' : 'data(objColor)'
		    })
		  .selector(':selected')
		    .css({
		      'border-width' : 3,
		      'border-color' : '#333'
		    })
		  .selector('edge')
		    .css({
		      'curve-style'        : 'bezier',
		      'target-arrow-shape' : 'triangle',
		      'source-arrow-shape' : 'circle',
		      'line-color'         : 'data(objColor)',
		      'opacity'            : 0.666,
		      'source-arrow-color' : 'data(objColor)',
		      'target-arrow-color' : 'data(objColor)'
		    })
		  .selector('.faded')
		    .css({
		      'opacity'      : 0.25,
		      'text-opacity' : 0
		    }),
		elements: {
		  nodes: <?=json_encode($nodes)?>,
		  edges: <?=json_encode($edges)?>
		},
		ready: function() {
		  window.cy = this;
		}
	});
});
</script>
    <meta name="viewport" content="width=device-width, initial-scale=1">
  </head>
  <body>
    <div class="container" role="main">
    <div style="margin-top:20px;" id="alert_container">
<?php

foreach ($alerts as $alert) {
    ?>
    <div class="row">
      <div class="col-sm-8">
        <div class="alert alert-danger alert-dismissible" role="alert">
          <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          <strong>ERROR!</strong> <?=$alert?>
        </div>
      </div>
    </div>
<?php

}
?>
    </div>
    <div class="page-header">
      <h3><?=$title?></h3>
    </div>
    <div id="cy" style="width:100%;height:100%;position:absolute;left:0;"></div>
    </div>
  </body>
</html>
