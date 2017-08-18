<?php

// Copyright (c) 2017  Joe Clarke <jclarke@cisco.com>
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

$alerts = [];
$title = 'Module Details';

$dbh = yang_db_conn($alerts);

$rester = new Rester(YANG_CATALOG_URL);

if (!isset($_GET['module'])) {
    //array_push($alerts, 'Modules were not specified');
} else {
    $module = $_GET['module'];
    $module = str_replace('.yang', '', $module);
    $module = str_replace('.yin', '', $module);
    $rev_org = get_rev_org($module, $dbh, $alerts);
    $module = explode('@', $module)[0];
    $properties = null;

    $mod_obj = Module::moduleFactory($rester, $module, $rev_org['rev'], $rev_org['org']);
    try {
        $properties = $mod_obj->toArray();
    } catch (Exception $e) {
        push_exception("Failed to get module details for {$mod_obj->getModSig()}", $e, $alerts);
    }

    $title = "Module Details for {$module}@{$rev_org['rev']}/{$rev_org['org']}";
}

?>
<!DOCTYPE html>
<html>
  <head>
    <title><?=$title?></title>
    <?=BOOTSTRAP_CSS?>

		<?=BOOTSTRAP_THEME_CSS?>

    <?=DATATABLES_BOOTSTRAP_CSS?>

    <style>

    /* Style taken from https://bootstrap-tagsinput.github.io/bootstrap-tagsinput/examples/assets/app.css */
    .twitter-typeahead .tt-query,
.twitter-typeahead .tt-hint {
    margin-bottom: 0;
}

.twitter-typeahead .tt-hint
{
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

.tt-suggestion:hover,
.tt-suggestion:focus {
  color: #ffffff;
  text-decoration: none;
  outline: 0;
  background-color: #428bca;
}

table.controls {
  border-collapse: separate; border-spacing: 5px;
}

.tooltip-inner {
  text-align: left;
}
    </style>

		<?=JQUERY_JS?>

		<?=BOOTSTRAP_JS?>

    <?=DATATABLES_JS?>

    <?=DATATABLES_BOOTSTRAP_JS?>

    <?=TYPEAHEAD_JS?>

		<script>

    var dt;

function reloadPage() {
  var url = "<?=$_SERVER['PHP_SELF']?>?";
  var uargs = [];
  uargs.push("module=" + $('#module').val());

  url += uargs.join("&");

  window.location.href = url;
}

$(document).ready(function() {
  $('#details_commit').on('click', function(e) {
    reloadPage();
  });
  dt = $('#datatable').DataTable({
    "scrollY": "600px",
    "scrollCollapse": true,
    "paging":false,
    "searching": false
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

if (!isset($_GET['module'])) {
    ?>
  <div class="row">
    <div class="col-sm-8">
      <div class="alert alert-info alert-dismissible" role="alert">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        Please specify a module to get its details.
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
        <label class="panel-title">Specify Module</label>
        <span class="pull-right clickable" style="cursor: pointer;"><i class="glyphicon glyphicon-chevron-down"></i></span>
      </div>
      <div class="panel-body">
      <div>
        <div>
          <div>
            <form>
              <table border="0" class="controls">
                <tbody>
                  <tr>
                    <td><b>Module:</b></td>
                    <td><input type="text" value="<?=(isset($module) ? $module : '')?>" id="module" class="form-control" placeholder="Module Name"></td>
                  </tr>
                  <tr>
                    <td><button type="button" class="btn btn-primary" id="details_commit">Get Details</button></td>
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

    $('#module').typeahead(null, {
      name: 'mod_completions',
      limit: 100,
      source: function (query, syncResults, asyncResults) {
        $.get('completions.php?type=module&pattern=' + query, function (data) {
          asyncResults(data);
        });
      }
    });
    </script>
  </div>
  <?php
  if ($properties !== null) {
      ?>
  <table id="datatable" class="table table-bordered table-responsive" width="100%" cellspacing="0">
    <thead>
      <tr>
        <th>Property Name</th>
        <th>Property Value</th>
      </tr>
    </thead>
    <tbody>
      <?php
      foreach ($properties as $key => $val) {
          ?>
        <tr>
          <td style="align: right"><b><?=$key?></b></td>
          <td><?=$val?></td>
        </tr>
        <?php

      } ?>
    </tbody>
  </table>
  <?php
  }?>
  </body>
</html>
