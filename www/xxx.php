<?php

include_once 'yang_catalog.inc.php';
require_once 'Rester.php';
require_once 'Module.php';

$rester = new Rester(YANG_CATALOG_URL);
$module = Module::moduleFactory($rester, 'yuma-arp', '2012-01-13', 'Netconf Central');
$doc = $module->get('document-name');
echo "Document is $doc\n";

var_dump($module->toArray());
$module = null;

?>
