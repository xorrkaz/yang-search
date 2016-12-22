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

$alerts = [];
$sth = null;

$dbh = yang_db_conn($alerts);

if (count($alerts) != 0) {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-type: application/json');
    echo json_encode(['status' => 'ERROR', 'message' => $alerts[0]]);
    exit(1);
}

header('Content-type: application/json');
$res = [];

if (!isset($_GET['type']) || ($_GET['type'] != 'org' && $_GET['type'] != 'module')) {
    echo json_encode($res);
    exit(0);
}

if (!isset($_GET['pattern']) || $_GET['pattern'] == '') {
    echo json_encode($res);
    exit(0);
}

$selector = null;
if ($_GET['type'] == 'org') {
    $selector = 'organization';
} elseif ($_GET['type'] == 'module') {
    $selector = 'module';
}

$sql = "SELECT DISTINCT({$selector}}) FROM modules WHERE {$selector} LIKE :pattern ESCAPE :esc LIMIT 10";
try {
    $sth = $dbh->prepare($sql);
    $sth->execute(['pattern' => str_replace(['\\', '%', '_'], ['\\'.'\\', '\\'.'%', '\\'.'_'], $_GET['pattern']).'%', 'esc' => '\\']);
    while ($row = $sth->fetch()) {
        array_push($res, $row[$selector]);
    }
} catch (PDOException $e) {
}

echo json_encode($res);
