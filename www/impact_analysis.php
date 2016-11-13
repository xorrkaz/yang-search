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

include_once 'yang_catalog.inc.php';

function get_org(&$dbh, $module)
{
    try {
        $sth = $dbh->prepare('SELECT organization FROM modules WHERE module=:mod');
        $sth->execute(['mod' => $module]);
        $row = $sth->fetch();

        return $row['organization'];
    } catch (Exception $e) {
        return '';
    }

    return '';
}

function get_doc(&$dbh, $module)
{
    try {
        $sth = $dbh->prepare('SELECT document FROM modules WHERE module=:mod');
        $sth->execute(['mod' => $module]);
        $row = $sth->fetch();

        return $row['document'];
    } catch (Exception $e) {
        return '';
    }

    return '';
}

function build_graph($module, $orgs, &$dbh, &$nodes, &$edges, &$edge_counts, &$nseen, &$eseen, &$alerts, $recurse = 0)
{
    global $CMAP;

    if (isset($nseen[$module])) {
        return;
    }
    if (count($orgs) > 0) {
        $org = get_org($dbh, $module);
        if (array_search($org, $orgs) === false) {
            return;
        }
    }
    $f = YDEPS_DIR.'/'.$module.'.json';
    if (is_file($f)) {
        try {
            $contents = file_get_contents($f);
            $json = json_decode($contents, true);
            if ($json === null) {
                array_push($alerts, "Failed to decode JSON data for {$module}: ".json_error_to_str(json_last_error()));
            } else {
                $color = get_color($module, $dbh, $alerts);
                $document = get_doc($dbh, $module);
                array_push($nodes, ['data' => ['id' => "mod_$module", 'name' => $module, 'objColor' => $color, 'document' => $document]]);
                $edge_counts[$module] = 0;
                $nseen[$module] = true;
                if (isset($json['impacted_modules'][$module])) {
                    foreach ($json['impacted_modules'][$module] as $mod) {
                        if (isset($eseen["mod_$module:mod_$mod"])) {
                            continue;
                        }
                        $eseen["mod_$module:mod_$mod"] = true;
                        if (count($orgs) > 0) {
                            $org = get_org($dbh, $mod);
                            if (array_search($org, $orgs) === false) {
                                continue;
                            }
                        }
                        $color = get_color($mod, $dbh, $alerts);
                        if ($color == $CMAP['DRAFT']) {
                            ++$edge_counts[$module];
                        }
                        array_push($edges, ['data' => ['source' => "mod_$module", 'target' => "mod_$mod", 'objColor' => $color]]);
                        if ($recurse > 0 || $recurse < 0) {
                            $r = $recurse - 1;
                            build_graph($mod, $orgs, $dbh, $nodes, $edges, $edge_counts, $nseen, $eseen, $alerts, $r);
                        } else {
                            $document = get_doc($dbh, $module);
                            array_push($nodes, ['data' => ['id' => "mod_$mod", 'name' => $mod, 'objColor' => $color, 'document' => $document]]);
                        }
                    }
                }
                if (isset($json['impacting_modules'][$module])) {
                    foreach ($json['impacting_modules'][$module] as $mod) {
                        if (isset($eseen["mod_$mod:mod_$module"])) {
                            continue;
                        }
                        if (isset($eseen["mod_$module:mod_$mod"])) {
                            array_push($alerts, "Loop found $module <=> $mod");
                        }
                        $eseen["mod_$mod:mod_$module"] = true;
                        if (count($orgs) > 0) {
                            $org = get_org($dbh, $mod);
                            if (array_search($org, $orgs) === false) {
                                continue;
                            }
                        }
                        $color = get_color($mod, $dbh, $alerts);
                        if ($color == $CMAP['DRAFT']) {
                            if (!isset($edge_counts[$mod])) {
                                $edge_counts[$mod] = 1;
                            } else {
                                ++$edge_counts[$mod];
                            }
                        }
                        array_push($edges, ['data' => ['source' => "mod_$mod", 'target' => "mod_$module", 'objColor' => $color]]);
                        if ($recurse > 0 || $recurse < 0) {
                            $r = $recurse - 1;
                            build_graph($mod, $orgs, $dbh, $nodes, $edges, $edge_counts, $nseen, $eseen, $alerts, $r);
                        } else {
                            $document = get_doc($dbh, $module);
                            array_push($nodes, ['data' => ['id' => "mod_$mod", 'name' => $mod, 'objColor' => $color, 'document' => $document]]);
                        }
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

$alerts = [];
$nodes = [];
$edges = [];
$edge_counts = [];
$nseen = [];
$eseen = [];
$modules = [];
$good_mods = [];
$orgs = [];
$recurse = 0;
$found_bottleneck = false;
$bottlenecks = [];
$title = 'Empty Impact Graph';

$dbh = yang_db_conn($alerts);

if (!isset($_GET['modules'])) {
    array_push($alerts, 'Modules were not specified');
} else {
    $modules = $_GET['modules'];
    if (isset($_GET['orgs'])) {
        $orgs = $_GET['orgs'];
    }
    if (isset($_GET['recurse']) && is_numeric($_GET['recurse'])) {
        $recurse = $_GET['recurse'];
    }
    foreach ($modules as $module) {
        $nmodule = basename($module);
        if ($nmodule != $module) {
            array_push($alerts, 'Invalid module name specified');
            $module = '';
        } else {
            array_push($good_mods, $module);
            build_graph($module, $orgs, $dbh, $nodes, $edges, $edge_counts, $nseen, $eseen, $alerts, $recurse);
        }
    }
    if (count($good_mods) > 0) {
        $title = 'YANG Impact Graph for Module(s): '.implode(', ', $good_mods);
    }
    arsort($edge_counts, SORT_NUMERIC);
    $curr_count = 0;
    foreach ($edge_counts as $m => $c) {
        if ($c <= 1 || $c < $curr_count) {
            break;
        }
        array_push($bottlenecks, "node#mod_{$m}");
        $found_bottleneck = true;
        $curr_count = $c;
    }
}

?>
<!DOCTYPE html>
<html>
  <head>
    <title><?=$title?></title>
    <?=BOOTSTRAP_CSS?>

		<?=BOOTSTRAP_THEME_CSS?>

    <?=BOOTSTRAP_TAGINPUT_CSS?>

    <?=QTIP_CSS?>

		<?=JQUERY_JS?>

		<?=BOOTSTRAP_JS?>

    <?=BOOTSTRAP_TAGINPUT_JS?>

    <?=QTIP_JS?>

		<?=CYTOSCAPE_JS?>

    <?=CYTOSCAPE_SPREAD_JS?>

    <?=CYTOSCAPE_QTIP_JS?>

		<script language="javascript">
$(function() {
	$("#cy").cytoscape({
		layout: {
			name: 'spread',
			minDist: 40
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
      <?php
      if ($found_bottleneck) {
          ?>
      this.elements('<?=implode(',', $bottlenecks)?>').css({'border-width':5, 'border-color': '#333'});
      <?php

      }
      foreach ($nodes as $n) {
          ?>
        this.elements('node#<?=$n['data']['id']?>').qtip({content: 'Document: <?=$n['data']['document']?>'});
        <?php

      }?>
		}
	});
});

function reloadPage() {
  var url = "<?=$_SERVER['PHP_SELF']?>?";
  var uargs = [];
  $.each($('#modtags').val().split(","), function(k, v) {
    if (v != '') {
      uargs.push("modules[]=" + v);
    }
  });
  $.each($('#orgtags').val().split(","), function(k, v) {
    if (v != '') {
      uargs.push("orgs[]=" + v);
    }
  });
  uargs.push("recurse=<?=$recurse?>");
  url += uargs.join("&");

  window.location.href = url;
}

$(document).ready(function() {
  $('#modtags').on('itemAdded', function(e) {
    reloadPage();
  });
  $('#orgtags').on('itemAdded', function(e) {
    reloadPage();
  });
  $('#modtags').on('itemRemoved', function(e) {
    reloadPage();
  });
  $('#orgtags').on('itemRemoved', function(e) {
    reloadPage();
  });
});

$(document).on('click', '.panel-heading span.clickable', function(e){
  if(!$(this).hasClass('panel-collapsed')) {
    $(this).parents('.panel').find('.panel-body').slideUp();
    $(this).addClass('panel-collapsed');
    $(this).find('i').removeClass('glyphicon-chevron-up').addClass('glyphicon-chevron-down');
  } else {
    $(this).parents('.panel').find('.panel-body').slideDown();
    $(this).removeClass('panel-collapsed');
    $(this).find('i').removeClass('glyphicon-chevron-down').addClass('glyphicon-chevron-up');
  }
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
    <div class="panel panel-default">
      <div class="panel-heading">
        <label for="impactOptions" class="panel-title">Graph Options</label>
        <span class="pull-right clickable" style="cursor: pointer;"><i class="glyphicon glyphicon-chevron-down"></i></span>
      </div>
      <div class="panel-body">
        <fieldset>
          <label>Legend</label>
          <table border="0">
            <tbody>
            <?php
            foreach ($CMAP as $des => $col) {
                ?>
                <tr>
                  <td style="background-color: <?=$col?>">&nbsp;&nbsp;</td>
                  <td>Status: <?=$des?></td>
                </tr>
              <?php

            } ?>
            </tbody>
          </table>
          <?php if ($found_bottleneck) {
                ?>
          <p><b>NOTE:</b> Highlighted node(s) represent bottleneck(s)</p>
          <?php

            } ?>
        </fieldset>
      <div>
        <div>
          <div>
            <form>
              <table border="0">
                <tbody>
                  <tr>
                    <td><b>Modules:</b></td>
                    <td><input type="text" value="<?=implode(',', $modules)?>" data-role="tagsinput" id="modtags"></td>
                  </tr>
                  <tr>
                    <td><b>Orgs:</b></td>
                    <td><input type="text" value="<?=implode(',', $orgs)?>" data-role="tagsinput" id="orgtags"></td>
                  </tr>
                </tbody>
              </table>
            </form>
          </div>
        </div>
      </div>
    </div>
    <div id="cy" style="width:100%;height:100%;position:absolute;left:0;"></div>
  </div>
  </body>
</html>
