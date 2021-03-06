<?php

// Copyright (c) 2016-2017  Joe Clarke <jclarke@cisco.com>
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
require_once 'Rester.php';
require_once 'Module.php';

$found_orgs = [];
$found_mats = [];

$DIR_HELP_TEXT = "<b>Both:</b> Show a graph that consists of both dependencies (modules imported by the target module(s)) and dependents (modules that import the target module(s))<br/>&nbsp;<br/>\n" .
                 "<b>Dependencies Only:</b> Only show those modules that are imported by the target module(s)<br/>&nbsp;<br/>\n" .
                 '<b>Dependents Only:</b> Only show those modules that depend on the target module(s)';

function nodify($n)
{
    return 'node#mod_' . $n;
}

function get_doc(&$mod_obj)
{
    try {
        $doc_name = $mod_obj->get('document-name');
        $ref = $mod_obj->get('reference');
        if ($ref !== null && $doc_name !== null && $ref != '' && $doc_name != '') {
            return '<a href="' . $ref . '">' . $doc_name . '</a>';
        } elseif ($ref !== null && $ref != '') {
            return '<a href="' . $ref . '">' . $ref . '</a>';
        } elseif ($doc_name !== null && $doc_name != '') {
            return $doc_name;
        }
    } catch (Exception $e) {
    }

    return 'N/A';
}

function get_parent(&$mod_obj)
{
    try {
        $bt = $mod_obj->get('belongs-to');
        if ($bt === null || $bt == '') {
            return $mod_obj->getName();
        }

        return $bt;
    } catch (Exception $e) {
        return $mod_obj->getName();
    }
}

function is_submod(&$mod_obj)
{
    try {
        $bt = $mod_obj->get('belongs-to');
        if ($bt === null || $bt == '') {
            return false;
        }

        return true;
    } catch (Exception $e) {
        return false;
    }
}

function build_graph($module, &$mod_obj, $orgs, &$dbh, &$nodes, &$edges, &$edge_counts, &$nseen, &$eseen, &$alerts, $show_rfcs, $recurse = 0, $nested = false, $show_subm = true, $show_dir = 'both')
{
    global $found_orgs, $found_mats;

    $is_subm = false;

    if (!$show_subm && $nested) {
        $module = get_parent($mod_obj);
    } elseif ($show_subm) {
        $is_subm = is_submod($mod_obj);
    }

    if ($nested && isset($nseen[$module])) {
        return;
    }

    $org = $mod_obj->getOrganization();
    if ($org == '') {
        $org = 'independent';
    }

    if ($nested > 0 && count($orgs) > 0 && !(count($orgs) == 1 && $orgs[0] == '')) {
        if (array_search($org, $orgs) === false) {
            return;
        }
    }
    $found_orgs[$org] = true;
    try {
        $dependents = $mod_obj->get('dependents');
        $dependencies = $mod_obj->get('dependencies');
        $mmat = get_maturity($mod_obj);
        if ($nested && $mmat['olevel'] == 'RATIFIED' && !$show_rfcs) {
            return;
        }
        $color = color_gen($dbh, $org);
        if (!isset($found_mats[$mmat['level']])) {
            $found_mats[$mmat['level']] = [$module];
        } else {
            array_push($found_mats[$mmat['level']], $module);
        }
        $document = get_doc($mod_obj);
        array_push($nodes, ['data' => ['id' => "mod_$module", 'name' => $module, 'objColor' => $color, 'document' => $document, 'sub_mod' => $is_subm, 'org' => strtoupper($org), 'mat' => $mmat['level']]]);
        if (!isset($edge_counts[$module])) {
            $edge_counts[$module] = 0;
        }
        $nseen[$module] = true;
        if (($show_dir == 'both' || $show_dir == 'dependents') && !is_null($dependents)) {
            foreach ($dependents as $moda) {
                $mod = $moda['name'];
                $is_msubm = false;
                $mobj = get_rev_org_obj($mod, $mod_obj->getRester(), $dbh, $alerts);
                if (!$show_subm) {
                    $mod = get_parent($mobj);
                } else {
                    $is_msubm = is_submod($mobj);
                }

                if (isset($eseen["mod_$module:mod_$mod"])) {
                    continue;
                }

                $eseen["mod_$module:mod_$mod"] = true;
                $maturity = get_maturity($mobj);
                if ($maturity['olevel'] == 'RATIFIED' && !$show_rfcs) {
                    continue;
                }

                $org = $mobj->getOrganization();
                if ($org == '') {
                    $org = 'UNKNOWN';
                }
                $mcolor = color_gen($dbh, $org);

                if (!isset($found_mats[$maturity['level']])) {
                    $found_mats[$maturity['level']] = [$mod];
                } else {
                    array_push($found_mats[$maturity['level']], $mod);
                }
                if (count($orgs) > 0) {
                    if (array_search($org, $orgs) === false) {
                        continue;
                    }
                }
                $found_orgs[$org] = true;

                if ($mmat['olevel'] == 'INITIAL' || $mmat['olevel'] == 'ADOPTED') {
                    ++$edge_counts[$module];
                }
                if ("mod_$module" != "mod_$mod") {
                    array_push($edges, ['data' => ['source' => "mod_$module", 'target' => "mod_$mod", 'objColor' => $mcolor, 'org' => strtoupper($org), 'mat' => $maturity['level']]]);
                }
                if ($recurse > 0 || $recurse < 0) {
                    $r = $recurse - 1;
                    build_graph($mod, $mobj, $orgs, $dbh, $nodes, $edges, $edge_counts, $nseen, $eseen, $alerts, $show_rfcs, $r, true, $show_subm, $show_dir);
                } else {
                    $document = get_doc($mobj);
                    array_push($nodes, ['data' => ['id' => "mod_$mod", 'name' => $mod, 'objColor' => $mcolor, 'document' => $document, 'sub_mod' => $is_msubm, 'org' => strtoupper($org), 'mat' => $maturity['level']]]);
                }
            }
        }
        if (($show_dir == 'both' || $show_dir == 'dependencies') && !is_null($dependencies)) {
            foreach ($dependencies as $moda) {
                $mod = $moda['name'];
                $is_msubm = false;
                $mobj = get_rev_org_obj($mod, $mod_obj->getRester(), $dbh, $alerts);

                if (!$show_subm) {
                    $mod = get_parent($mobj);
                } else {
                    $is_msubm = is_submod($mobj);
                }
                if (isset($eseen["mod_$mod:mod_$module"])) {
                    continue;
                }

                if (isset($eseen["mod_$module:mod_$mod"])) {
                    array_push($alerts, "Loop found $module <=> $mod");
                }
                $eseen["mod_$mod:mod_$module"] = true;
                $maturity = get_maturity($mobj);
                if ($maturity['olevel'] == 'RATIFIED' && !$show_rfcs) {
                    continue;
                }

                $org = $mobj->getOrganization();
                if ($org == '') {
                    $org = 'UNKNOWN';
                }
                if (!isset($found_mats[$maturity['level']])) {
                    $found_mats[$maturity['level']] = [$mod];
                } else {
                    array_push($found_mats[$maturity['level']], $mod);
                }

                if (count($orgs) > 0) {
                    if (array_search($org, $orgs) === false) {
                        continue;
                    }
                }
                $found_orgs[$org] = true;

                $mcolor = color_gen($dbh, $org);
                if ($maturity['olevel'] == 'INITIAL' || $maturity['olevel'] == 'ADOPTED') {
                    if (!isset($edge_counts[$mod])) {
                        $edge_counts[$mod] = 1;
                    } else {
                        ++$edge_counts[$mod];
                    }
                }
                if (!$nested) {
                    if ("mod_$mod" != "mod_$module") {
                        array_push($edges, ['data' => ['source' => "mod_$mod", 'target' => "mod_$module", 'objColor' => $mcolor, 'org' => strtoupper($org), 'mat' => $maturity['level']]]);
                    }
                }
                if ($nested && ($recurse > 0 || $recurse < 0)) {
                    $r = $recurse - 1;
                            //build_graph($mod, $orgs, $dbh, $nodes, $edges, $edge_counts, $nseen, $eseen, $alerts, $show_rfcs, $r, true);
                } elseif (!$nested) {
                    $document = get_doc($mobj);
                    array_push($nodes, ['data' => ['id' => "mod_$mod", 'name' => $mod, 'objColor' => $mcolor, 'document' => $document, 'sub_mod' => $is_msubm, 'org' => strtoupper($org), 'mat' => $maturity['level']]]);
                }
            }
        }
    } catch (Exception $e) {
        push_exception("Failed to read dependency data for $module", $e, $alerts);
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
$show_rfcs = true;
$recurse = 0;
$show_subm = true;
$show_dir = 'both';
$found_bottleneck = false;
$bottlenecks = [];
$title = 'Empty Impact Graph';
$num_legend_cols = 1;
$rim_cols = 0;

$dbh = yang_db_conn($alerts);

$rester = new Rester(YANG_CATALOG_URL);

if (!isset($_GET['modules']) && !isset($_GET['ietf_wg'])) {
    //array_push($alerts, 'Modules were not specified');
} else {
    if (isset($_GET['modules']) && is_array($_GET['modules'])) {
        $modules = $_GET['modules'];
    } elseif (isset($_GET['modules'])) {
        array_push($modules, $_GET['modules']);
    }
    if (isset($_GET['orgs'])) {
        $orgs = $_GET['orgs'];
    }
    if (isset($_GET['recurse']) && is_numeric($_GET['recurse'])) {
        $recurse = $_GET['recurse'];
    }
    if (isset($_GET['rfcs']) && $_GET['rfcs'] == 0) {
        $show_rfcs = false;
    }
    if (isset($_GET['show_subm']) && $_GET['show_subm'] == 0) {
        $show_subm = false;
    }
    if (isset($_GET['show_dir'])) {
        $show_dir = $_GET['show_dir'];
        if ($show_dir != 'both' && $show_dir != 'dependencies' && $show_dir != 'dependents') {
            $show_dir = 'both';
        }
    }
    if (isset($_GET['ietf_wg']) && $_GET['ietf_wg'] != '') {
        $mod_objs = Module::moduleFactoryFromSearch($rester, "/ietf/ietf-wg/{$_GET['ietf_wg']}");
        if ($mod_objs === null || count($mod_objs) == 0) {
            array_push($alerts, "No modules found for {$_GET['ietf_wg']}");
        } else {
            foreach ($mod_objs as $mod_obj) {
                $module = $mod_obj->getName();
                if ($mod_obj->get('maturity-level') != 'adopted' && $mod_obj->get('maturity-level') != 'ratified') {
                    continue;
                }
                array_push($good_mods, $module);
                array_push($modules, $module);
                build_graph($module, $mod_obj, $orgs, $dbh, $nodes, $edges, $edge_counts, $nseen, $eseen, $alerts, $show_rfcs, $recurse, false, $show_subm, $show_dir);
            }
        }
    } else {
        foreach ($modules as $module) {
            $nmodule = basename($module);
            if ($nmodule != $module) {
                array_push($alerts, 'Invalid module name specified');
                $module = '';
            } else {
                $module = str_replace('.yang', '', $module);
                $module = str_replace('.yin', '', $module);
                $mod_obj = get_rev_org_obj($module, $rester, $dbh, $alerts);
                $module = explode('@', $module)[0];
                array_push($good_mods, $module);

                build_graph($module, $mod_obj, $orgs, $dbh, $nodes, $edges, $edge_counts, $nseen, $eseen, $alerts, $show_rfcs, $recurse, false, $show_subm, $show_dir);
            }
        }
    }
    if (count($good_mods) > 0) {
        $title = 'YANG Impact Graph for Module(s): '.implode(', ', $good_mods);
    }
    arsort($edge_counts, SORT_NUMERIC);
    $curr_count = 0;
    $tbottlenecks = [];
    $rim_cols = count(array_keys($found_mats));
    foreach ($edge_counts as $m => $c) {
        if ($c < 1 || $c < $curr_count) {
            break;
        }
        array_push($tbottlenecks, $m);
        $found_bottleneck = true;
        $curr_count = $c;
    }
    foreach ($tbottlenecks as $bn) {
        $found_dep = false;
        foreach ($edges as $edge) {
            if ($edge['data']['target'] == "mod_{$bn}") {
                $mn = str_replace('mod_', '', $edge['data']['source']);
                $mo = get_rev_org_obj($mn, $rester, $dbh, $alerts);
                $maturity = get_maturity($mo);
                if ($maturity['level'] == 'INITIAL' || $maturity['level'] == 'ADOPTED') {
                    array_push($bottlenecks, "node#{$edge['data']['source']}");
                    $found_dep = true;
                }
            }
        }
        if (!$found_dep) {
            array_push($bottlenecks, "node#mod_{$bn}");
        }
    }

    $num_legend_cols = ceil(count(array_keys($found_orgs)) / 6);
    if ($num_legend_cols < 1) {
        $num_legend_cols = 1;
    }
    if ($found_bottleneck) {
        $rim_cols++;
    }
    if ($rim_cols > 1) {
        $rim_cols--;
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

    <?=FONT_AWESOME_CSS?>

    <style>

    /* Style taken from https://bootstrap-tagsinput.github.io/bootstrap-tagsinput/examples/assets/app.css */

    .twitter-typeahead .tt-query, .twitter-typeahead .tt-hint {
      margin-bottom: 0;
    }

    .twitter-typeahead .tt-hint {
      display: none;
    }

    .tt-menu {
      position: absolute;
      top: 100%;
      left: 0;
      z-index: 1000;
      display: none;
      float: left;
      min-width: 160px;
      padding: 5px 0;
      margin: 2px 0 0;
      list-style: none;
      font-size: 14px;
      background-color: #ffffff;
      border: 1px solid #cccccc;
      border: 1px solid rgba(0, 0, 0, 0.15);
      border-radius: 4px;
      -webkit-box-shadow: 0 6px 12px rgba(0, 0, 0, 0.175);
      box-shadow: 0 6px 12px rgba(0, 0, 0, 0.175);
      background-clip: padding-box;
      cursor: pointer;
      max-height: 150px;
      overflow-y: auto;
    }

    .tt-suggestion {
      display: block;
      padding: 3px 20px;
      clear: both;
      font-weight: normal;
      line-height: 1.428571429;
      color: #333333;
      white-space: nowrap;
    }

    .tt-suggestion:hover, .tt-suggestion:focus {
      color: #ffffff;
      text-decoration: none;
      outline: 0;
      background-color: #428bca;
    }

    table.controls {
      border-collapse: separate;
      border-spacing: 5px;
    }

    .tooltip-inner {
      text-align: left;
    }

    ul.color-list {
      list-style: none;
      padding-left: 0;
      column-count: <?=$num_legend_cols?>;
      -moz-column-count: <?=$num_legend_cols?>;
      -webkit-column-count: <?=$num_legend_cols?>;
    }

    ul.rim-list {
      list-style: none;
      padding-left: 0;
      column-count: <?=$rim_cols?>;
      -moz-column-count: <?=$rim_cols?>;
      -webkit-column-count: <?=$rim_cols?>;
      column-gap: 1px;
      -moz-column-gap: 1px;
      -webkit-column-gap: 1px;
    }
    </style>

		<?=JQUERY_JS?>

		<?=BOOTSTRAP_JS?>

    <?=BOOTSTRAP_TAGINPUT_JS?>

    <?=QTIP_JS?>

		<?=CYTOSCAPE_JS?>

    <?=CYTOSCAPE_SPREAD_JS?>

    <?=CYTOSCAPE_QTIP_JS?>

    <?=TYPEAHEAD_JS?>

		<script>

    var opacity_full = 1.0;
    var opacity_faded = 0.25;

    $(function() {
      $("#cy").cytoscape({
        layout: {
          name: 'spread',
          minDist: 40
        },
        style: cytoscape.stylesheet()
          .selector('node')
          .css({
            'content': 'data(name)',
            'text-valign': 'center',
            'color': '#fff',
            'text-outline-width': 2,
            'background-color': 'data(objColor)',
            'text-outline-color': 'data(objColor)'
          })
          .selector(':selected')
          .css({
            'border-width': 3,
            'border-color': '#2c1ec1'
          })
          .selector('edge')
          .css({
            'curve-style': 'bezier',
            'target-arrow-shape': 'triangle',
            'source-arrow-shape': 'circle',
            'line-color': 'data(objColor)',
            'opacity': 0.666,
            'source-arrow-color': 'data(objColor)',
            'target-arrow-color': 'data(objColor)'
          })
          .selector('.faded')
          .css({
            'opacity': opacity_faded,
            'text-opacity': 0
          }),
        elements: {
          nodes: <?=json_encode($nodes)?>,
          edges: <?=json_encode($edges)?>
        },
        ready: function() {
          window.cy = this;
          <?php
          foreach ($found_mats as $mat => $mlist) {
              ?>
          this.elements('<?=implode(',', array_map('nodify', $mlist))?>').css({
            'border-width': 5,
            'border-color': '<?=$MATURITY_MAP[$mat]?>'
          });
          <?php

          }
          if ($found_bottleneck) {
              ?>
          this.elements('<?=implode(',', $bottlenecks)?>').css({
            'border-width': 5,
            'border-color': '#333'
          });
          this.elements('<?=implode(',', $bottlenecks)?>').data('bottleneck', true);
          <?php

          }
          foreach ($nodes as $node) {
              if ($node['data']['sub_mod'] === true) {
                  ?>
          this.elements('node[name = "<?=$node['data']['name']?>"]').data('name', 'sub-module: ' + this.elements('node[name = "<?=$node['data']['name']?>"]').data('name')).css({
            'font-size': '8px'
          });
          <?php

              }
          }
          ?>
          window.cy.nodes().qtip({
            content: function() {
              return 'Document ' + this.data('document')
            },
            position: {
              my: 'bottom right',
              at: 'top left'
            },
            show: {
              event: 'mouseover'
            },
            hide: {
              event: false,
              inactive: 2000
            },
            style: {
              classes: 'qtip-bootstrap',
              tip: {
                width: 16,
                height: 8
              }
            }
          });
        }
      });
    });

    function reloadPage() {
      var url = "<?=$_SERVER['PHP_SELF']?>?";
      var uargs = [];
      $.each($('#modtags').val().split(","), function(k, v) {
        if (v !== '') {
          uargs.push("modules[]=" + v);
        }
      });
      $.each($('#orgtags').val().split(","), function(k, v) {
        if (v !== '') {
          uargs.push("orgs[]=" + v);
        }
      });
      var recursion = $('#recursion').val();
      if (recursion === '') {
        recusrion = 0;
      }
      uargs.push("recurse=" + recursion);
      if ($('#show_rfcs').is(':checked')) {
        uargs.push("rfcs=1");
      } else {
        uargs.push("rfcs=0");
      }
      if ($('#show_subm').is(':checked')) {
        uargs.push("show_subm=1");
      } else {
        uargs.push("show_subm=0");
      }
      uargs.push("show_dir=" + $('#show_dir').val());

      url += uargs.join("&");

      window.location.href = url;
    }

    var highlighted = {};
    var allHighlighted = true;

    function highlight(what, match) {
      var wcy = window.cy;
      var owhat = what;
      if (what === 'maturity') {
        what = 'mat';
      }
      if (match === '__ALL__') {
        wcy.elements('node').css({
          'opacity': opacity_full
        });
        wcy.elements('edge').css({
          'opacity': opacity_full
        });
        allHighlighted = true;
        highlighted = {};
      } else {
        var eop = '=';
        var seop = '';
        if (allHighlighted === true) {
          eop = '!=';
          seop = '!';
        }
        var nexpr = what + ' ' + eop + ' "' + match + '"';
        var eexpr = nexpr;
        if (what === 'bottleneck') {
          nexpr = seop + 'bottleneck';
          eexpr = '';
        }
        var key = what + ':' + match;
        if (allHighlighted === true) {
          if (nexpr !== '') {
            wcy.elements('node[' + nexpr + ']').css({
              'opacity': opacity_faded
            });
          }
          if (eexpr !== '') {
            wcy.elements('edge[' + eexpr + ']').css({
              'opacity': opacity_faded
            });
          }
          allHighlighted = false;
          return highlight(owhat, match);
        }

        if (!(key in highlighted) || highlighted[key] === false) {
          if (nexpr !== '') {
            wcy.elements('node[' + nexpr + ']').css({
              'opacity': opacity_full
            });
          }
          if (eexpr !== '') {
            wcy.elements('edge[' + eexpr + ']').css({
              'opacity': opacity_full
            });
          }
          highlighted[key] = true;
        } else if (highlighted[key] === true) {
          if (nexpr !== '') {
            wcy.elements('node[' + nexpr + ']').css({
              'opacity': opacity_faded
            });
          }
          if (eexpr !== '') {
            wcy.elements('edge[' + eexpr + ']').css({
              'opacity': opacity_faded
            });
          }
          highlighted[key] = false;
        }
      }
      return false;
    }

    $(document).ready(function() {
      $('#graph_commit').on('click', function(e) {
        reloadPage();
      });
      $('#graph_export').on('click', function(e) {
        var png = window.cy.png({
          full: true
        });
        var img = new Image();
        img.src = png;

        var win = window.open("");
        win.document.write(img.outerHTML);
      });

      $('[data-toggle="tooltip"]').tooltip();
    });

    $(document).on('click', '.panel-heading span.clickable', function(e) {
      if (!$(this).hasClass('panel-collapsed')) {
        $(this).parents('.panel').find('.panel-body').slideUp();
        $(this).addClass('panel-collapsed');
        $(this).find('i').removeClass('glyphicon-chevron-up').addClass('glyphicon-chevron-down');
        window.cy.resize();
      } else {
        $(this).parents('.panel').find('.panel-body').slideDown();
        $(this).removeClass('panel-collapsed');
        $(this).find('i').removeClass('glyphicon-chevron-down').addClass('glyphicon-chevron-up');
        window.cy.resize();
      }
    });
</script>
    <meta name="viewport" content="width=device-width, initial-scale=1">
  </head>
  <body>
    <div class="container" role="main">
    <div style="margin-top:20px;" id="alert_container">
<?php

if (!isset($_GET['modules']) && !isset($_GET['ietf_wg'])) {
    ?>
  <div class="row">
    <div class="col-sm-8">
      <div class="alert alert-info alert-dismissible" role="alert">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        Please specify at least one module to generate the impact analysis.
      </div>
    </div>
  </div>
  <?php

}

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
        <label class="panel-title">Graph Options</label>
        <span class="pull-right clickable" style="cursor: pointer;"><i class="glyphicon glyphicon-chevron-down"></i></span>
      </div>
      <div class="panel-body">
        <fieldset>
          <p>Click on legend elements below to toggle highlighting on the graph.</p>
          <p><a href="#" onClick="return highlight('org', '__ALL__')";>Highlight All</a></p>
          <label>Element Colors</label>
          <ul class="color-list">
            <?php
            foreach ($found_orgs as $fo => $val) {
                $fo = strtoupper($fo);
                if (!isset($ORG_CACHE[$fo])) {
                    continue;
                } ?>
              <li><a href="#" onClick="return highlight('org', '<?=$fo?>');"><span class="fas fa-square" style="color: <?=$ORG_CACHE[$fo]?>;"></span> <?=$fo?></a></li>
              <?php

            } ?>
          </ul>
          <label>Rim Colors</label>
          <ul class="rim-list">
          <?php
          foreach (array_keys($found_mats) as $mat) {
              ?>
            <li><a href="#" onClick="return highlight('maturity', '<?=$mat?>');"><span class="far fa-circle" style="color: <?=$MATURITY_MAP[$mat]?>;"></span> Maturity: <?=$mat?></a></li>
            <?php

          }
          if ($found_bottleneck) {
              ?>
          <li><a href="#" onClick="return highlight('bottleneck', '');"><span class="far fa-circle" style="color: #000000;"></span> Bottleneck to Ratification</a></li>
          <?php

          } ?>
        </ul>
        </fieldset>
      <div>
        <div>
          <div>
            <form>
              <table border="0" class="controls">
                <tbody>
                  <tr>
                    <td><b>Modules:</b></td>
                    <td><input type="text" value="<?=implode(',', $modules)?>" data-role="tagsinput" id="modtags"></td>
                  </tr>
                  <tr>
                    <td><b>Orgs:</b></td>
                    <td><input type="text" value="<?=implode(',', $orgs)?>" data-role="tagsinput" id="orgtags"></td>
                  </tr>
                  <tr>
                    <td><b>Recursion Levels:</b>&nbsp;&nbsp;&nbsp;<input type="text" id="recursion" size="2" value="<?=$recurse?>"></td>
                    <td><b>Include Ratified Standards?</b>&nbsp;&nbsp;&nbsp;<input type="checkbox" id="show_rfcs" value="1" <?=($show_rfcs) ? 'checked' : ''?>></td>
                    <td><b>Include Sub-modules?</b>&nbsp;&nbsp;&nbsp;<input type="checkbox" id="show_subm" value="1" <?=($show_subm) ? 'checked' : ''?>></td>
                    <td><b>Show Graph Direction:</b>&nbsp;&nbsp;&nbsp;<select id="show_dir" data-html="true" data-toggle="tooltip" title="<?=$DIR_HELP_TEXT?>">
                      <option value="both" <?=($show_dir == 'both') ? 'selected' : ''?>>Both</option>
                      <option value="dependencies" <?=($show_dir == 'dependencies') ? 'selected' : ''?>>Dependencies Only</option>
                      <option value="dependents" <?=($show_dir == 'dependents') ? 'selected' : ''?>>Dependents Only</option>
                    </select></td>
                  </tr>
                  <tr>
                    <td><button type="button" class="btn btn-primary" id="graph_commit">Generate</button></td>
                    <td><button type="button" class="btn" id="graph_export">Export</button></td>
                  </tr>
                </tbody>
              </table>
            </form>
          </div>
        </div>
      </div>
    </div>
    </div>
    <script>
    /*var orgCompletions = new Bloodhound({
      datumTokenizer: Bloodhound.tokenizers.whitespace,
      queryTokenizer: Bloodhound.tokenizers.whitespace,
      local: <?php //echo json_encode(array_values($SDOS));?>,
      remote: {
        url: 'completions.php?type=org&pattern=%QUERY',
        wildcard: '%QUERY'
      }
    });
    orgCompletions.initialize();

    var moduleCompletions = new Bloodhound({
      datumTokenizer: Bloodhound.tokenizers.whitespace,
      queryTokenizer: Bloodhound.tokenizers.whitespace,
      local: [],
      remote: {
        url: 'completions.php?type=module&pattern=%QUERY',
        wildcard: '%QUERY'
      }
    });
    moduleCompletions.initialize();*/

    $('#orgtags').tagsinput({
      typeaheadjs: {
        name: 'org_completions',
        limit: 100,
        source: function (query, syncResults, asyncResults) {
          $.get('completions.php?type=org&pattern=' + query, function (data) {
            asyncResults(data);
          });
        }
      }
    });

    $('#modtags').tagsinput({
      typeaheadjs: {
        name: 'mod_completions',
        limit: 100,
        source: function (query, syncResults, asyncResults) {
          $.get('completions.php?type=module&pattern=' + query, function (data) {
            asyncResults(data);
          });
        }
      }
    });
    </script>
    <div id="cy" style="width:100%;height:100%;position:absolute;left:0;"></div>
  </div>
  </body>
</html>
