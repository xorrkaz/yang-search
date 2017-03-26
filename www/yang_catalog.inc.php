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

include_once 'yang_db.inc.php';

// Where to find various files.
define('YTREES_DIR', '/var/yang/ytrees');
define('YDEPS_DIR', '/var/yang/ydeps');

// JS and CSS components from CDNs.
define('BOOTSTRAP_CSS', '<link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">');
define('BOOTSTRAP_THEME_CSS', '<link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap-theme.min.css" integrity="sha384-rHyoN1iRsVXV4nD0JutlnGaslCJuC7uwjduW9SVrLvRYooPp2bWYgmgJQIXwl/Sp" crossorigin="anonymous">');
define('BOOTSTRAP_JS', '<script src="//maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>');
define('CYTOSCAPE_JS', '<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/cytoscape/2.7.10/cytoscape.min.js" integrity="sha256-Nb/w8L97ZY7g1BTV4SkV2w+mB5+mtSb6fv2UsSry3UE=" crossorigin="anonymous"></script>');
define('JQUERY_JS', '<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/jquery/1.12.4/jquery.min.js" integrity="sha256-ZosEbRLbNQzLpnKIkEdrPv7lOy9C27hHQ+Xp8a4MxAQ=" crossorigin="anonymous"></script>');
define('DATATABLES_BOOTSTRAP_CSS', '<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/datatables/1.10.12/css/dataTables.bootstrap.min.css" integrity="sha256-7MXHrlaY+rYR1p4jeLI23tgiUamQVym2FWmiUjksFDc=" crossorigin="anonymous" />');
define('DATATABLES_CSS', '<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/datatables/1.10.12/css/jquery.dataTables.min.css" integrity="sha256-+Z1rYa3ys5OdZNUck5G7lBvb8A13OrYwvf+d8PfEaHQ=" crossorigin="anonymous" />');
define('DATATABLES_BOOTSTRAP_JS', '<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/datatables/1.10.12/js/dataTables.bootstrap.min.js" integrity="sha256-90YqnHom4j8OhcEQgyUI2IhmGYTBO54Adcf3YDZU9xM=" crossorigin="anonymous"></script>');
define('DATATABLES_JS', '<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/datatables/1.10.12/js/jquery.dataTables.min.js" integrity="sha256-TX6POJQ2u5/aJmHTJ/XUL5vWCbuOw0AQdgUEzk4vYMc=" crossorigin="anonymous"></script>');
define('JSTREE_JS', '<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/jstree/3.3.2/jstree.min.js" integrity="sha256-/N7f/1nHQUQkXl4HET7s457ciiCHHjVaa4vWHa7JMWI=" crossorigin="anonymous"></script>');
define('JSTREE_CSS', '<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/jstree/3.3.2/themes/default/style.min.css" integrity="sha256-riSdF36gKV63v22ujIMlNzON5f7AS9MNzwFn0ZgGt0Q=" crossorigin="anonymous" />');
define('JQUERY_UI_JS', '<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js" integrity="sha256-KM512VNnjElC30ehFwehXjx1YCHPiQkOPmqnrWtpccM=" crossorigin="anonymous"></script>');
define('BOOTSTRAP_TAGINPUT_CSS', '<link rel="stylesheet" href="css/bootstrap-tagsinput.css">');
define('BOOTSTRAP_TAGINPUT_JS', '<script type="text/javascript" src="js/bootstrap-tagsinput.js"></script>');
define('CYTOSCAPE_SPREAD_JS', '<script src="//cdn.rawgit.com/cytoscape/cytoscape.js-spread/1.0.9/cytoscape-spread.js"></script>');
define('CYTOSCAPE_QTIP_JS', '<script src="js/cytoscape-qtip.js" type="text/javascript"></script>');
define('QTIP_CSS', '<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/qtip2/2.2.1/jquery.qtip.min.css" integrity="sha256-ETJXPMaFFQmwk7vArlWKUK3Pr/s84J3zfiXC83Pi2xg=" crossorigin="anonymous" />');
define('QTIP_JS', '<script src="//cdnjs.cloudflare.com/ajax/libs/qtip2/2.2.1/jquery.qtip.min.js" integrity="sha256-Mir9XErZ/xEi1+rIQUpp7nFnZLsJfUS325iUvHDUpyY=" crossorigin="anonymous"></script>');
define('TYPEAHEAD_JS', '<script src="js/typeahead.bundle.js" type="text/javascript"></script>');

// Global data_definitions
define('YANG_CATALOG_URL', 'http://yangcatalog.org/api/operational');
define('REST_USER', 'oper');
define('REST_PASS', 'oper');
define('REST_TIMEOUT', 300);
define('RESTCONF_JSON_MIME_TYPE', 'application/vnd.yang.data+json');
define('OPENCONFIG_CATALOG_MOD_NS', 'openconfig-module-catalog:module');
define('LOCKF', '/tmp/webhook.lock');
define('CHANGES_CACHE', '/usr/share/nginx/yang_repo_cache.dat');

// Global variables
//$COLOR_UNKNOWN = '#F5A45D';
$MATURITY_UNKNOWN = [
  'level' => 'UNKNOWN',
  'color' => '#663300',
];
$SDO_CMAP = [
  'IETF' => [
    'N/A' => [
      'level' => 'UNKNOWN',
      'color' => '#F5A45D',
    ],
    'RFC' => [
      'level' => 'STANDARD',
      'color' => '#0066FF',
    ],
    'INDIVIDUAL DRAFT' => [
      'level' => 'IDRAFT',
      'color' => '#FF0066',
    ],
    'WG DRAFT' => [
      'level' => 'WGDRAFT',
      'color' => '#86B342',
    ],
  ],
  'BBF' => [
    'N/A' => [
      'level' => 'UNKNOWN',
      'color' => '#FFCC99',
    ],
    'PERSONAL' => [
      'level' => 'IDRAFT',
      'color' => '#FF99FF',
    ],
    'DRAFT' => [
      'level' => 'WGDRAFT',
      'color' => '#00FF99',
    ],
    'STANDARD' => [
      'level' => 'STANDARD',
      'color' => '#3366FF',
    ],
  ],
];

/*$CMAP = [
    'N/A' => $COLOR_UNKNOWN,
    'RFC' => '#FA0528',
    'DRAFT' => '#86B342',
];*/

/*
 * Mapping table of URN to catalog org tree name for Standards Definition
 * Organizations.
*/
$SDOS = [
  'ietf' => 'ietf',
  'ieee' => 'ieee',
  'bbf' => 'bbf',
  'odp' => 'odp',
];

/*
 * List of columns shown in the search results.
 *
*/
$SEARCH_COLUMNS = [
    'Name',
    'Revision',
    'Schema Type',
    'Path',
    'Module',
    'Origin',
    'Organization',
    'Maturity',
    'Description',
];

// Functions

/*
 * Generate the HTML header for yang-search pages.
 *
 * Input:
 *  $title           : HTML title of the page
 *  $extra_css_items : (optional) Array of CSS items (full HTML tags included) to add to the header
 *  $extra_js_items  : (optional) Array of Javascript items (fill HTML tags included) to add to the header
 * Output:
 *  None
 */
function print_header($title, $extra_css_items = [], $extra_js_items = [])
{
    ?>
  <!DOCTYPE html>
  <html>
    <head>
      <title><?=$title?></title>
      <?=BOOTSTRAP_CSS?>

      <?=BOOTSTRAP_THEME_CSS?>
      <?php foreach ($extra_css_items as $item) {
        echo "$item\n";
    } ?>

      <meta name="viewport" content="width=device-width, initial-scale=1">
      <?=JQUERY_JS?>

  		<?=BOOTSTRAP_JS?>
      <?php foreach ($extra_js_items as $item) {
        echo "$item\n";
    } ?>
    </head>
    <?php

}

/*
 * Open a connection to the YANG index database.
 *
 * Input:
 *  $alerts : Pointer to array containing any error messages
 * Output:
 *  Database connection handle
 */
function yang_db_conn(&$alerts)
{
    global $db_driver, $db_file, $db_user, $db_pass;

    $dsn = $db_driver.':'.$db_file;
    $opt = [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $dbh = null;

    try {
        $dbh = new PDO($dsn, $db_user, $db_pass, $opt);
    } catch (PDOException $e) {
        push_exception('Failed to connect to DB', $e, $alerts);
    }

    return $dbh;
}

/*
 * This function gets an object's maturity.
 *
 * Input:
 *  $module : YANG module name
 *  $dbh    : Pointer to the YANG index database handle
 *  $alerts : Pointer to an array containing errors
 * Output:
 *  The maturity level and color for the given module
 */
function get_maturity($module, &$dbh, &$alerts)
{
    global $SDO_CMAP, $MATURITY_UNKNOWN;

    $maturity = $MATURITY_UNKNOWN;
    try {
        if (!preg_match('/@/', $module)) {
            $module = get_latest_mod($module, $dbh, $alerts);
        }
        $mod_parts = explode('@', $module);
        $modn = $mod_parts[0];
        $rev = $mod_parts[1];
        $sth = $dbh->prepare('SELECT maturity, organization FROM modules WHERE module=:mod AND revision=:rev');
        $sth->execute(['mod' => $modn, 'rev' => $rev]);
        $row = $sth->fetch();
        $organization = strtoupper($row['organization']);
        $mmat = strtoupper($row['maturity']);
        if (isset($SDO_CMAP[$organization]) && isset($SDO_CMAP[$organization][$mmat])) {
            $maturity = $SDO_CMAP[$organization][$mmat];
        }
    } catch (Exception $e) {
        push_exception("Failed to get module maturity for $module (perhaps it doesn't validate?)", $e, $alerts);
    }

    return $maturity;
}

/*
 * Get the latest module@revision given a module name.
 *
 * Input:
 *  $module : YANG module name
 *  $dbh    : Pointer to the YANG index database handle
 *  $alerts : Pointer to an array containing errors
 * Output:
 *  The latest module in module@revision format
 */
 function get_latest_mod($module, &$dbh, &$alerts)
 {
     try {
         $sth = $dbh->prepare('SELECT revision FROM modules WHERE module=:mod ORDER BY revision DESC LIMIT 1');
         $sth->execute(['mod' => $module]);
         $row = $sth->fetch();

         if ($row && isset($row['revision'])) {
             return "{$module}@{$row['revision']}";
         }
     } catch (Exception $e) {
         push_exception("Failed to get revision for $module", $e, $alerts);
     }

     return $module;
 }

/*
 * Turn all PHP errors and warnings into exceptions.
 *
 * Input:
 *  $severity : Severity of the error
 *  $message  : The error message to display
 *  $file     : File in which the error occurred
 *  $line     : Line number at which the error occurred
 * Output:
 *  None
 */
function error_to_exception($severity, $message, $file, $line)
{
    if (!(error_reporting() & $severity)) {
        return;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
}

/*
 * Add an exception message to the list of errors.
 *
 * Input:
 *  $msg     : Additional error message to display
 *  $e       : Exception object
 *  $alerts  : Pointer to an array onto which the error will be pushed
 *  $add_msg : (optional) If true, the additional error message is added to the exception error message
 *  $add_file: (optional) If true, the file name and line number are added to the error message
 * Output:
 *  None
 */
function push_exception($msg, $e, &$alerts, $add_msg = true, $add_file = true)
{
    if ($add_msg) {
        if ($msg != '') {
            $msg .= ' : ';
        }
        $msg .= $e->getMessage();
    }
    if ($add_file) {
        if ($msg != '') {
            $msg .= ' : ';
        }
        $msg .= "({$e->getFile()}:{$e->getLine()})";
    }

    array_push($alerts, $msg);
}

/*
 * Convert JSON error codes to strings.
 *
 * Input:
 *  $error: JSON error code
 * Output:
 *  String representation of the JSON error code
 */
function json_error_to_str($error)
{
    switch ($error) {
    case JSON_ERROR_NONE:
        return 'No Error';
        break;
    case JSON_ERROR_DEPTH:
        return 'Maximum Stack Depth Exceeded';
        break;
    case JSON_ERROR_STATE_MISMATCH:
        return 'Underflow or the Nodes Mismatch';
        break;
    case JSON_ERROR_CTRL_CHAR:
        return 'Unexecpted Control Character';
        break;
    case JSON_ERROR_SYNTAX:
        return 'Syntax Error';
        break;
    case JSON_ERROR_UTF8:
        return 'Malformed UTF-8 Character';
        break;
    default:
        return 'Unknown Error';
        break;
  }
}

// Global main functions.
set_error_handler('error_to_exception');

?>
