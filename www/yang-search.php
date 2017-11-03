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
require_once 'Module.php';
require_once 'Rester.php';
require_once 'Search.php';

$schema_types = [
    ['Typedef' => 'typedef', 'Grouping' => 'grouping', 'Feature' => 'feature'],
    ['Identity' => 'identity', 'Extension' => 'extension', 'RPC' => 'rpc'],
    ['Container' => 'container', 'List' => 'list', 'Leaf-List' => 'leaf-list'],
    ['Leaf' => 'leaf', 'Notification' => 'notification', 'Action' => 'action'],
];

$yang_versions = [
    ['1.0', '1.1'],
];

$search_fields = [
    ['Module Name' => 'module', 'Node Name' => 'argument', 'Node Description' => 'description'],
];

$alerts = [];
$results = null;
$title = 'YANG DB Search';
$search_string = null;

$rester = new Rester(YANG_CATALOG_URL);

if (isset($_POST['search_string'])) {
    $search_string = $_POST['search_string'];
}

if ($search_string !== null && $search_string == '') {
    $search_string = null;
    array_push($alerts, 'No search term(s) specified');
}

if ($search_string !== null) {
    $search = new Search($rester, $search_string);
    $do_regexp = false;
    $case_sensitive = false;
    if (isset($_POST['case']) && $_POST['case'] == 1) {
        $case_sensitive = true;
    }
    if (isset($_POST['regexp']) && $_POST['regexp'] == 1) {
        $do_regexp = true;
    }

    if (!isset($_POST['schemaAll']) && !isset($_POST['schemaTypes'])) {
        $_POST['schemaAll'] = 1;
    }

    $title = "YANG DB Search Results for '{$_POST['search_string']}'";
    try {
        if ($do_regexp) {
            $search->setType('regex');
        } else {
            $search->setType('keyword');
            if ($case_sensitive) {
                $search->setCaseSensitive(true);
            } else {
                $search->setCaseSensitive(false);
            }
        }

        $search->setModFilter(['name', 'revision', 'organization', 'maturity-level', 'compilation-status', 'dependents']);

        $sts = array_values($search_fields);
        if (isset($_POST['searchFields']) && count($_POST['searchFields']) > 0) {
            $sts = $_POST['searchFields'];
        }
        $search->setSearchFields($sts);

        if (!isset($_POST['schemaAll']) || $_POST['schemaAll'] != 1) {
            $search->setSchemaTypes($_POST['schemaTypes']);
        }

        if (isset($_POST['onlyLatest']) && $_POST['onlyLatest'] == 1) {
            $search->setLatestRevisions(true);
        } else {
            $search->setLatestRevisions(false);
        }

        if (!isset($_POST['includeMIBs']) || $_POST['includeMIBs'] != 1) {
            $search->setIncludeMibs(false);
        } else {
            $search->setIncludeMibs(true);
        }

        if (isset($_POST['yangVersions']) && count($_POST['yangVersions']) > 0) {
            $search->setYangVersions($_POST['yangVersions']);
        }

        $results = $search->search();
    } catch (Exception $e) {
        push_exception('', $e, $alerts);
    }
}

print_header($title, [DATATABLES_BOOTSTRAP_CSS], [DATATABLES_JS, DATATABLES_BOOTSTRAP_JS]);
?>
  <body>
    <div class="container" role="main" style="width: 100%;">
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
if (isset($_POST['search_string'])) {
    ?>
		<script>
    var tableColumns = <?=json_encode($SEARCH_COLUMNS)?>;
    var prev_idx = -1;
    var dt;

    $.fn.dataTable.ext.search.push(function(settings, sdata, rindex, rdata, counter) {
      var idx = $('#column_filter').val();
      if (idx == -1) {
        return true;
      }

      var stext = $('#datatable_filter label input').val();
      var col = sdata[idx] || '';
      if (col.toLowerCase().indexOf(stext.toLowerCase()) != -1) {
        return true;
      }

      return false;
    });

    function do_search(s) {
      var idx = s.value;

      if (prev_idx == idx) {
        return;
      }

      stext = $('#datatable_filter label input').val();
      dt.search('').columns().search('').draw();

      prev_idx = idx;

      if (idx == -1) {
        dt.search(stext).draw();
      } else {
        dt.search(stext).columns(idx).search(stext).draw();
      }

    }

		$(document).ready(function() {
			dt = $('#datatable').DataTable({
        "columnDefs": [
          { "type": "num", "targets": 8 }
        ]
      });

      var newHtml = '<select id="column_filter" name="column_filter" onChange="do_search(this);"><option value="-1">Entire Table</option>';
      $.each(tableColumns, function(key, val) {
        newHtml += '<option value="' + key + '">' + val + '</option>';
      });
      newHtml += '</select>';
      $('#datatable_filter label').after(' ' + newHtml);
		});
    </script>
      <div class="page-header">
        <h3><?=$title?></h3>
      </div>
      <table id="datatable" class="table table-bordered table-responsive" width="100%" cellspacing="0">
        <thead>
          <tr>
            <?php
            foreach ($SEARCH_COLUMNS as $tc) {
                ?>
              <th><?=$tc?></th>
              <?php

            } ?>
          </tr>
        </thead>
        <tbody>
<?php
    if (count($results) > 0) {
        $modules = [];
        foreach ($results as $res_mod) {
            if ($res_mod->getModule('error') !== null) {
                continue;
            }
            if ($res_mod->getModule('name') === null) {
                continue;
            }
            $organization = $res_mod->getModule('organization');
            $maturity = $res_mod->getModule('maturity-level');
            $compile_status = $res_mod->getModule('compilation-status');

            if ($organization === null || $organization == '') {
                $organization = 'N/A';
            }

            $mod_sig = "{$res_mod->getModule('name')}@{$res_mod->getModule('revision')}/{$res_mod->getModule('organization')}";

            $origin = 'N/A';
            if ($organization != 'N/A' && isset($SDOS[$organization])) {
                $origin = 'Industry Standard';
            } elseif ($organization != 'N/A') {
                $origin = 'Vendor-Specific';
            } ?>
          <tr>
            <td><a href="show_node.php?module=<?=$res_mod->getModule('name')?>&amp;path=<?=urlencode($res_mod->getNode('path'))?>&amp;revision=<?=$res_mod->getModule('revision')?>"><?=$res_mod->getNode('name')?></a></td>
            <td><?=$res_mod->getModule('revision')?></td>
            <td><?=$res_mod->getNode('type')?></td>
            <td><?=$res_mod->getNode('path')?></td>
<?php
            if ((isset($modules[$mod_sig]) && $modules[$mod_sig] === true) || (!isset($modules[$res_mod->getModule('name')]) && is_file(YTREES_DIR.'/'.$res_mod->getModule('name').'@'.$res_mod->getModule('revision').'.json'))) {
                ?>
            <td><?=$res_mod->getModule('name')?><br/><span style="font-size: small">(<a href="module_details.php?module=<?=$res_mod->getModule('name')?>"><img src="img/details.png" border="0" title="Module Details for <?=$res_mod->getModule('name')?>"> Module Details</a> |
              <a href="yang_tree.php?module=<?=$res_mod->getModule('name')?>"><img border="0" src="img/leaf.png" title="Tree View for <?=$res_mod->getModule('name')?>">
              Tree View</a>
              |
              <a href="impact_analysis.php?modules[]=<?=$res_mod->getModule('name')?>"><img src="img/impact.png" border="0" title="Impact Analysis for <?=$res_mod->getModule('name')?>">
                Impact Analysis</a>)</span></td>
<?php
                $modules[$mod_sig] = true;
            } else {
                ?>
            <td><?=$res_mod->getModule('name')?></td>
<?php
                $modules[$mod_sig] = false;
            } ?>
            <td><?=$origin?></td>
            <td><?=htmlentities($organization)?></td>
            <td><?=$maturity?></td>
            <td><?=count($res_mod->getModule('dependents'))?></td>
            <td><?=($compile_status != '' ? $compile_status : 'N/A')?></td>
            <td><?=htmlentities($res_mod->getNode('description'))?></td>
          </tr>
<?php

        }
    } ?>
         </tbody>
       </table>
<?php

} else {
    ?>
    <script>
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
$(document).on('click', '#schemaAll', function(e) {
	if ($(this).is(':checked')) {
		$('.yang-schema-select').prop('checked', true);
	} else {
		$('.yang-schema-select').prop('checked', false);
	}
});
$(document).on('click', '.yang-schema-select', function(e) {
	if (!$(this).is(':checked')) {
		$('#schemaAll').prop('checked', false);
	} else {
		var allChecked = true;
		$('.yang-schema-select').each(function(i, e) {
			if (!$(this).is(':checked')) {
				allChecked = false;
				return;
			}
		});
		if (allChecked) {
			$('#schemaAll').prop('checked', true);
		}
	}
});
$(document).on('click', '#fieldsAll', function(e) {
	if ($(this).is(':checked')) {
		$('.yang-fields-select').prop('checked', true);
	} else {
		$('.yang-fields-select').prop('checked', false);
	}
});
$(document).on('click', '.yang-fields-select', function(e) {
	if (!$(this).is(':checked')) {
		$('#fieldsAll').prop('checked', false);
	} else {
		var allChecked = true;
		$('.yang-fields-select').each(function(i, e) {
			if (!$(this).is(':checked')) {
				allChecked = false;
				return;
			}
		});
		if (allChecked) {
			$('#fieldsAll').prop('checked', true);
		}
	}
});
$(document).on('click', '#regexp', function(e) {
	if ($(this).is(':checked')) {
		$('#search_string').prop('placeholder', 'Search Pattern');
	} else {
		$('#search_string').prop('placeholder', 'Search String');
	}
});
function verify() {
	if (!$('#search_string').val().trim()) {
		alert('Please specify search terms.');
		return false;
	}
	return true;
}
    </script>
    <div class="page-header">
      <h3><?=$title?></h3>
    </div>

    <div class="row">
      <div class="col-sm-8">

    <form method="POST" onSubmit="return verify();">
      <div class="form-group">
        <label for="search_string">Enter your search term(s) below:</label>
        <input type="text" name="search_string" id="search_string" class="form-control" placeholder="Search String">
      </div>
      <div class="panel panel-default">
        <div class="panel-heading">
          <label class="panel-title">Search Options</label>
          <span class="pull-right clickable panel-collapsed" style="cursor: pointer;"><i class="glyphicon glyphicon-chevron-down"></i></span>
        </div>
        <div class="panel-body" style="display: none;">
          <table class="table table-default">
            <tbody>
              <tr>
                <td>
                  <div class="checkbox">
                    <label for="caseSensitive">
                      <input id="caseSensitive" type="checkbox" name="case" style="margin-top: 0;" value="1"> Case-Sensitive
                    </label>
                  </div>
                </td>
                <td>
                  <div class="checkbox">
                    <label for="regexp">
                      <input id="regexp" type="checkbox" name="regexp" style="margin-top: 0;" value="1"> Regular Expression
                    </label>
                  </div>
                </td>
                <td>
                  <div class="checkbox">
                    <label for="includeMIBs">
                      <input id="includeMIBs" type="checkbox" name="includeMIBs" style="margin-top: 0;" value="1"> Include MIBs
                    </label>
                  </div>
                </td>
                <td>
                  <div class="checkbox">
                    <label for="onlyLatest">
                      <input id="onlyLatest" type="checkbox" name="onlyLatest" style="margin-top: 0;" value="1" checked> Only Show Latest Revisions
                    </label>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
          <label>Search Fields</label>
          <div class="checkbox">
            <label for="fieldsAll">
              <input type="checkbox" id="fieldsAll" name="fieldsAll" style="margin-top: 0;" value="1" checked> All
            </label>
          </div>
          <table class="table table-bordered">
            <tbody>
              <?php
              foreach ($search_fields as $vrow) {
                  ?>
                <tr>
                  <?php
                  foreach ($vrow as $vname => $vval) {
                      if ($vname == '__EMPTY__') {
                          ?>
                      <td>&nbsp;</td>
                      <?php

                      } else {
                          ?>
                      <td>
                        <div class="checkbox">
                          <label for="field_<?=$vval?>">
                            <input id="field_<?=$vval?>" type="checkbox" name="searchFields[]" class="yang-fields-select" style="margin-top: 0;" value="<?=$vval?>" checked> <?=$vname?>
                          </label>
                        </div>
                      </td>
                      <?php

                      }
                  } ?>
                </tr>
                <?php

              } ?>
            </tbody>
          </table>
          <label>YANG Versions</label>
          <table class="table table-bordered">
            <tbody>
              <?php
              foreach ($yang_versions as $vrow) {
                  ?>
                <tr>
                  <?php
                  foreach ($vrow as $ver) {
                      if ($ver == '__EMPTY__') {
                          ?>
                      <td>&nbsp;</td>
                      <?php

                      } else {
                          ?>
                      <td>
                        <div class="checkbox">
                          <label for="ver_<?=$ver?>">
                            <input id="ver_<?=$ver?>" type="checkbox" name="yangVersions[]" style="margin-top: 0;" value="<?=$ver?>" checked> <?=$ver?>
                          </label>
                        </div>
                      </td>
                      <?php

                      }
                  } ?>
                </tr>
                <?php

              } ?>
            </tbody>
          </table>
          <label>Schema Types</label>
          <div class="checkbox">
            <label for="schemaAll">
              <input type="checkbox" id="schemaAll" name="schemaAll" style="margin-top: 0;" value="1" checked> All
            </label>
          </div>
          <table class="table table-bordered">
            <tbody>
<?php
    foreach ($schema_types as $srow) {
        ?>
              <tr>
<?php
        foreach ($srow as $skey => $sval) {
            if ($sval == '__EMPTY__') {
                ?>
            <td>&nbsp;</td>
            <?php

            } else {
                ?>
                <td>
                  <div class="checkbox">
                    <label for="schema<?=$skey?>">
                      <input id="schema<?=$skey?>" type="checkbox" name="schemaTypes[]" class="yang-schema-select" style="margin-top: 0;" value="<?=$sval?>" checked> <?=$skey?>
                    </label>
                  </div>
                </td>
<?php

            }
        } ?>
              </tr>
<?php

    } ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="form-group">
        <input type="submit" name="submit" value="Search!" class="btn btn-primary">
        <input type="reset" name="reset" value="Reset" class="btn btn-default">
      </div>
    </form>
    </div>
    </div>
<?php

}
?>
    </div>
  </body>
</html>
