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

function unescape_str($str, $indent)
{
    $indent .= "\t";
    $str = str_replace('\n', "\n$indent", $str);
    $str = str_replace('\r', "\r", $str);
    $str = str_replace('\t', "\t", $str);
    $str = str_replace('\\\\', '\\', $str);

    return $str;
}

function print_properties($properties, $indent = "\t")
{
    foreach ($properties as $property) {
        foreach ($property as $key => $val) {
            if ($val['value'] != '') {
                echo "$indent<b>$key</b> ".htmlentities(unescape_str($val['value'], $indent));
            } else {
                echo "$indent<b>$key</b> ";
            }
            if ($val['has_children'] && count($val['children']) == 0) {
                echo " {\n$indent\t...\n$indent}\n";
            } elseif (count($val['children']) > 0) {
                echo " {\n";
                print_properties($val['children'], $indent."\t");
                echo "\n$indent}\n";
            } else {
                echo ";\n";
            }
        }
    }
}

$alerts = [];
$sth = null;
$title = '';

$dbh = yang_db_conn($alerts);

if (!isset($_GET['path']) || !isset($_GET['module'])) {
    array_push($alerts, 'Module and path must be specified');
} else {
    $title = "YANG Definition for '{$_GET['path']}'";
    $module = $_GET['module'];
    $revision = '';
    if (!isset($_GET['revision'])) {
        $mod_rev = get_latest_mod($module, $dbh, $alerts);
        $mod_parts = explode('@', $mod_rev);
        $module = $mod_parts[0];
        if (count($mod_parts) == 2) {
            $revision = $mod_parts[1];
        }
    } else {
        $revision = $_GET['revision'];
    }
    try {
        if ($dbh !== null) {
            $sth = $dbh->prepare('SELECT * FROM yindex WHERE path = :path AND module = :module AND revision=:rev');
            $sth->execute(['path' => $_GET['path'], 'module' => $module, 'rev' => $revision]);
        }
    } catch (PDOException $e) {
        push_exception('', $e, $alerts);
    }
}

$properties = null;
if ($sth) {
    $row = $sth->fetch();
  //var_dump($row);
    $properties = json_decode($row['properties'], true);
    if ($properties === null) {
        array_push($alerts, 'Failed to decode JSON properties '.json_error_to_str(json_last_error()));
    }
}

print_header($title);
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
<?php
if ($properties !== null) {
    ?>
      <div class="page-header">
        <h3><?=$title?></h3>
      </div>
      <pre>
<i>// From : <?=$row['module']?>@<?=$row['revision']?></i>

<b><?=$row['statement']?></b> <?=$row['argument']?> {
<?php
    //var_dump($properties);
    print_properties($properties); ?>
}
<?php

}
?>
    </pre>
    </div>
  </body>
</html>
