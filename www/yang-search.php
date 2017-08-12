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

function __sqlite_regexp($pattern, $buf, $modifiers = 'is')
{
    $pattern = str_replace('/', '\/', $pattern);
    if (isset($pattern, $buf)) {
        $res = @preg_match("/$pattern/$modifiers", $buf);
        if ($res === false) {
            throw new RuntimeException("Invalid regular expression: '$pattern'");
        }

        return $res > 0;
    }

    return null;
}

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
$sth = null;
$title = 'YANG DB Search';
$search_string = null;

$dbh = yang_db_conn($alerts);
$rester = new Rester(YANG_CATALOG_URL);

if (isset($_POST['search_string'])) {
    $search_string = $_POST['search_string'];
}

if ($search_string !== null && $search_string == '') {
    $search_string = null;
    array_push($alerts, 'No search term(s) specified');
}

if ($dbh !== null && $search_string !== null) {
    $do_regexp = false;
    $case_sensitive = false;
    $modifiers = 'is';
    if (isset($_POST['case']) && $_POST['case'] == 1) {
        $modifiers = 's';
        $case_sensitive = true;
    }
    if (isset($_POST['regexp']) && $_POST['regexp'] == 1) {
        $do_regexp = true;
    } else {
        $search_string = '%'.$search_string.'%';
    }

    if (!isset($_POST['schemaAll']) && !isset($_POST['schemaTypes'])) {
        $_POST['schemaAll'] = 1;
    }

    $title = "YANG DB Search Results for '{$_POST['search_string']}'";
    try {
        if ($do_regexp) {
            $dbh->sqliteCreateFunction('REGEXP', '__sqlite_regexp');
        } else {
            if ($case_sensitive) {
                $dbh->query('PRAGMA case_sensitive_like=ON');
            } else {
                $dbh->query('PRAGMA case_sensitive_like=OFF');
            }
        }

        $sql = 'SELECT yia.*, MAX(yib.revision) AS latest_revision FROM yindex yia, yindex yib WHERE ';
        $qparams['descr'] = $search_string;
        $sts = ['argument', 'description', 'module'];
        if (isset($_POST['searchFields']) && count($_POST['searchFields']) > 0) {
            $sts = $_POST['searchFields'];
        }
        $wclause = [];
        if ($do_regexp) {
            foreach ($sts as $field) {
                array_push($wclause, "REGEXP(:descr, yia.{$field})");
            }
        } else {
            foreach ($sts as $field) {
                array_push($wclause, "yia.{$field} LIKE :descr");
            }
        }
        $sql .= '('. implode(' OR ', $wclause) . ')';
        if (!isset($_POST['schemaAll']) || $_POST['schemaAll'] != 1) {
            $queries = [];
            $sql .= ' AND (';
            foreach ($_POST['schemaTypes'] as $st) {
                array_push($queries, "yia.statement = '$st'");
            }
            $sql .= implode(' OR ', $queries);
            $sql .= ')';
        }

        $sql .= ' AND (yib.module = yia.module) GROUP BY yia.module, yia.revision';

        $sth = $dbh->prepare($sql);
        $sth->execute($qparams);
    } catch (Exception $e) {
        push_exception('', $e, $alerts);
        $sth = null;
    }

    $res_set = [];
    if ($sth !== null) {
        $rejects = [];
        $not_founds = [];
        # Post-filter the returned data based on additional MD options.
        while ($row = $sth->fetch()) {
            $mod_obj = Module::moduleFactory($rester, $row['module'], $row['revision'], $row['organization']);
            $mod_sig = $mod_obj->getModSig();
            if (isset($rejects[$mod_sig])) {
                continue;
            }
            $try_checks = true;
            $maturity = '';
            $comp_status = 'unknown';
            try {
                if (!isset($not_founds[$mod_sig])) {
                    try {
                        $maturity = $mod_obj->get('maturity-level');
                        $comp_status = $mod_obj->get('compilation-status');
                    } catch (RestException $re) {
                        if ($re->getCode() == 404) {
                            #array_push($alerts, "Metadata for {$mod_sig} was not found in the Catalog.");
                          $try_checks = false;
                            $not_founds[$mod_sig] = true;
                        } else {
                            push_exception('Failed to pull metadata from the API', $re, $alerts);
                            break;
                        }
                    }
                } else {
                    $try_checks = false;
                }
                if ($try_checks && !isset($_POST['includeMIBs']) || $_POST['includeMIBs'] != 1) {
                    if (@preg_match('/yang:smiv2:/', $mod_obj->get('namespace'))) {
                        $rejects[$mod_sig] = true;
                        continue;
                    }
                }
                if ($try_checks && isset($_POST['yangVersions']) && count($_POST['yangVersions']) > 0) {
                    if (array_search($mod_obj->get('yang-version'), $_POST['yangVersions']) === false) {
                        $rejects[$mod_sig] = true;
                        continue;
                    }
                }
                $res_mod = $row;
                $res_mod['maturity'] = $maturity;
                $res_mod['compile_status'] = $comp_status;
                $res_mod['sig'] = $mod_sig;
                array_push($res_set, $res_mod);
            } catch (Exception $e) {
                push_exception('Failed to pull metadata from the API', $e, $alerts);
                break;
            }
        }
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
        dt.search(stext).columns().search(stext).draw();
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
    if (count($res_set) > 0) {
        $modules = [];
        foreach ($res_set as $res_mod) {
            if (isset($_POST['onlyLatest']) && $_POST['onlyLatest'] == 1) {
                if ($res_mod['latest_revision'] != $res_mod['revision']) {
                    continue;
                }
            }
            $organization = $res_mod['organization'];
            $maturity = $res_mod['maturity'];
            $compile_status = $res_mod['compile_status'];

            if ($organization === null || $organization == '') {
                $organization = 'N/A';
            }

            $origin = 'N/A';
            if ($organization != 'N/A' && isset($SDOS[$organization])) {
                $origin = 'Industry Standard';
            } elseif ($organization != 'N/A') {
                $origin = 'Vendor-Specific';
            } ?>
          <tr>
            <td><a href="show_node.php?module=<?=$res_mod['module']?>&amp;path=<?=urlencode($res_mod['path'])?>&amp;revision=<?=$res_mod['revision']?>"><?=$row['argument']?></a></td>
            <td><?=$res_mod['revision']?></td>
            <td><?=$res_mod['statement']?></td>
            <td><?=$res_mod['path']?></td>
<?php
            if ((isset($modules[$res_mod['sig']]) && $modules[$res_mod['sig']] === true) || (!isset($modules[$res_mod['module']]) && is_file(YTREES_DIR.'/'.$res_mod['module'].'@'.$res_mod['revision'].'.json'))) {
                ?>
            <td><a href="yang_tree.php?module=<?=$res_mod['module']?>"><?=$res_mod['module']?></a> <span style="font-size: small">(<a href="impact_analysis.php?modules[]=<?=$res_mod['module']?>">Impact Analysis</a>)</span></td>
<?php
                $modules[$res_mod['sig']] = true;
            } else {
                ?>
            <td><?=$res_mod['module']?></td>
<?php
                $modules[$res_mod['sig']] = false;
            } ?>
            <td><?=$origin?></td>
            <td><?=htmlentities($organization)?></td>
            <td><?=$maturity?></td>
            <?php
            if (is_file(YDEPS_DIR.'/'.$res_mod['module'].'.json')) {
                try {
                    $deps = json_decode(file_get_contents(YDEPS_DIR.'/'.$res_mod['module'].'.json'), true);
                    echo "<td>" . (count($deps['impacted_modules'][$res_mod['module']])) . "</td>\n";
                } catch (Exception $e) {
                    echo "<td>0</td>\n";
                }
            } else {
                echo "<td>0</td>\n";
            } ?>
            <td><?=($compile_status != '' ? $compile_status : 'N/A')?></td>
            <td><?=htmlentities($res_mod['description'])?></td>
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
