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

$dbh = yang_db_conn($alerts);

$rester = new Rester(YANG_CATALOG_URL);

if (!isset($_GET['module'])) {
    die("ERROR: You must specify a module.");
} else {
    $module = $_GET['module'];
    $module = str_replace('.yang', '', $module);
    $module = str_replace('.yin', '', $module);
    $rev_org = get_rev_org($module, $dbh, $alerts);
    $module = explode('@', $module)[0];
    $url = null;
    $error = false;

    $mod_obj = Module::moduleFactory($rester, $module, $rev_org['rev'], $rev_org['org'], false, true);
    try {
        $url = $mod_obj->getYangSuiteURL();
    } catch (Exception $e) {
        $error = true;
    }

    if ($error || $url === null || $url == '') {
        die("Failed to get Yang Suite URL for {$mod_obj->getModSig()}");
    }

    header("Location: " . $url);
    exit(0);
}
