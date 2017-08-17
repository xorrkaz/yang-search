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
require_once 'Rester.php';
require_once 'Module.php';

function get_type_str($json)
{
    $type_str = '';
    if (isset($json['type'])) {
        $type_str .= $json['type'];
    }
    foreach ($json as $k => $v) {
        if ($k == 'type') {
            continue;
        }
        if ($k == 'typedef') {
            $type_str .= ' '.get_type_str($v);
        } else {
            if (is_array($v)) {
                $type_str .= ' { '.implode(',', $v).' }';
            } else {
                $type_str .= ' { '.$v.' }';
            }
        }
    }

    return $type_str;
}

function build_tree($json, $module)
{
    $node['text'] = $json['name'];
    if (isset($json['description'])) {
        $node['a_attr']['title'] = str_replace('\n', ' ', $json['description']);
    } else {
        $node['a_attr']['title'] = $json['name'];
    }
    $node['data'] = [
      'schema' => '',
      'type' => '',
      'type_title' => '',
      'type_class' => 'abbrCls',
      'flags' => '',
      'opts' => '',
      'status' => '',
      'path' => '',
    ];
    if ($json['name'] == $module) {
        $node['data']['schema'] = 'module';
    } elseif (isset($json['schema_type'])) {
        $node['data']['schema'] = $json['schema_type'];
    }
    if (isset($json['type'])) {
        $node['data']['type'] = $json['type'];
        $node['data']['type_title'] = $json['type'];
        if (isset($json['type_info'])) {
            $node['data']['type_title'] = get_type_str($json['type_info']);
        }
    } elseif (isset($json['schema_type'])) {
        $node['data']['type'] = $json['schema_type'];
        $node['data']['type_title'] = $json['schema_type'];
    }
    if (isset($json['flags']) && isset($json['flags']['config'])) {
        if ($json['flags']['config']) {
            $node['data']['flags'] = 'config';
        } else {
            $node['data']['flags'] = 'no config';
        }
    }
    if (isset($json['options'])) {
        $node['data']['opts'] = $json['options'];
    }
    if (isset($json['status'])) {
        $node['data']['status'] = $json['status'];
    }
    if (isset($json['path'])) {
        $node['data']['path'] = $json['path'];
    }
    if ($json['name'] != $module && (!isset($json['children']) || count($json['children']) == 0)) {
        $node['icon'] = 'glyphicon glyphicon-leaf';
        if (isset($json['path'])) {
            $node['a_attr']['href'] = "show_node.php?module={$module}&path=".urlencode($json['path']);
        }
        $node['a_attr']['class'] = 'nodeClass';
        $node['a_attr']['style'] = 'color: #00e;';
    } elseif (isset($json['children'])) {
        $node['children'] = [];
        foreach ($json['children'] as $child) {
            array_push($node['children'], build_tree($child, $module));
        }
    }

    return $node;
}

$alerts = [];
$module = null;
$jstree_json = null;
$title = 'YANG Tree';

$dbh = yang_db_conn($alerts);

$rester = new Rester(YANG_CATALOG_URL);

if (!isset($_GET['module'])) {
    array_push($alerts, 'Module was not specified');
} else {
    $module = $_GET['module'];
    $nmodule = basename($module);
    if ($nmodule != $module) {
        array_push($alerts, 'Invalid module name specified');
        $module = '';
    } else {
        $title = "YANG Tree for Module: '$module'";
        $rev_org = get_rev_org($module, $dbh, $alerts);
        $modn = explode('@', $module)[0];
        $module = "{$modn}@{$rev_org['rev']}";
        $f = YTREES_DIR.'/'.$module.'.json';
        $mod_obj = Module::moduleFactory($rester, $modn, $rev_org['rev'], $rev_org['org']);
        $maturity = get_maturity($mod_obj, $alerts);
        if (is_file($f)) {
            try {
                $contents = file_get_contents($f);
                $json = json_decode($contents, true);
                if (!isset($json['namespace'])) {
                    $json['namespace'] = '';
                }
                if ($json === null) {
                    array_push($alerts, 'Failed to decode JSON data: '.json_error_to_str(json_last_error()));
                } else {
                    $data_nodes = build_tree($json, $modn);
                    $jstree_json = [];
                    $jstree_json['data'] = [$data_nodes];
                    if (isset($json['rpcs'])) {
                        $rpcs['name'] = $json['prefix'].':rpcs';
                        $rpcs['children'] = $json['rpcs'];
                        array_push($jstree_json['data'], build_tree($rpcs, $modn));
                    }
                    if (isset($json['notifications'])) {
                        $notifs['name'] = $json['prefix'].':notifs';
                        $notifs['children'] = $json['notifications'];
                        array_push($jstree_json['data'], build_tree($notifs, $modn));
                    }
                    if (isset($json['augments'])) {
                        $augments['name'] = $json['prefix'].':augments';
                        $augments['children'] = $json['augments'];
                        array_push($jstree_json['data'], build_tree($augments, $modn));
                    }
                }
            } catch (Exception $e) {
                push_exception("Failed to read YANG tree data for $module", $e, $alerts);
            }
        } else {
            array_push($alerts, "YANG Tree data does not exist for $module");
        }
    }
}

print_header($title, [JSTREE_CSS, '<style>.abbrCls { border-bottom: 1px dotted; } a.nodeClass i { color: #228b22; }</style>'], [JQUERY_UI_JS, JSTREE_JS, '<script src="js/jstreegrid.js"></script>']);
?>
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
<?php
if ($jstree_json !== null) {
    ?>
    <div style="margin-bottom: 10px;">
      <b>Module: <span style="color: <?=$maturity['color']?>;"><?=$module?></span>, Namespace: <span style="color: <?=$maturity['color']?>;"><?=$json['namespace']?></span>, Prefix: <span style="color: <?=$maturity['color']?>;"><?=$json['prefix']?></span><br/>
        <a href="impact_analysis.php?modules[]=<?=$module?>">Impact Analysis</a> for <?=$module?></b>
    </div>
    <div id="yangtree"></div>
    <script>
$(document).ready(function () {
  $('#yangtree').jstree({
    plugins: ['themes', 'json', 'grid'],
    grid: {
      resizable : true,
      draggable : true,
      columns : [
        {width : '100%', header : '<b>Element</b> &nbsp;&nbsp;<a href="#" onClick="expandTree();">[+] Expand All</a> <a href="#" onClick="collapseAll();">[-] Collapse All</a>'},
        {header : '<b>Schema</b>', value : 'schema'},
        {header : '<b>Type</b>', value : 'type', title : 'type_title', valueClass : 'type_class'},
        {header : '<b>Flags</b>', value : 'flags'},
        {header : '<b>Opts</b>', value : 'opts'},
        {header : '<b>Status</b>', value : 'status'},
        {header : '<b>Path</b>', value : 'path'},
      ]
    },
    core: <?=json_encode($jstree_json)?>}).on("activate_node.jstree", function(e, data){
      window.location.href = data.node.a_attr.href;
  });
});

function expandTree() {
  $('#yangtree').jstree('open_all');
}

function collapseAll() {
  $('#yangtree').jstree('close_all');
}
    </script>
    </div>
    <?php

} ?>
  </body>
</html>
