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
$res['status'] = 'SUCCESS';
$res['response'] = [];

if (!isset($_GET['org_pattern']) || $_GET['org_pattern'] == '') {
    echo json_encode($res);
    exit(0);
}

$sql = 'SELECT DISTINCT(organization) FROM modules WHERE organization LIKE :orgpat ESCAPE :esc LIMIT 10';
try {
    $sth = $dbh->prepare($sql);
    $sth->execute(['orgpat' => str_replace(['\\', '%', '_'], ['\\'.'\\', '\\'.'%', '\\'.'_'], $_GET['org_pattern']).'%', 'esc' => '\\']);
    while ($row = $sth->fetch()) {
        array_push($res['response'], $row['organization']);
    }
} catch (PDOException $e) {
}

echo json_encode($res);
