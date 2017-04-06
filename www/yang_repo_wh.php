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

$payload = file_get_contents('php://input');
$json = json_decode($payload, true);

while (file_exists(LOCKF)) {
    sleep(3);
}
try {
    $res = touch(LOCKF);
    if (!$res) {
        throw new Exception('Failed to obtain lock '.LOCKF);
    }

    $changes_cache = [];
    if (file_exists(CHANGES_CACHE)) {
        $changes_cache = json_decode(file_get_contents(CHANGES_CACHE), true);
    }

    if (!isset($json['commits'])) {
        $json['commits'] = [];
    }

    foreach ($json['commits'] as $commit) {
        $files = array_merge($commit['added'], $commit['modified']);
        foreach ($files as $file) {
            $dir = dirname($file);
            if (array_search($dir, $changes_cache) !== false) {
                array_push($changes_cache, $dir);
            }
        }
    }

    $fd = fopen(CHANGE_CACHE, 'w');
    fwrite($fd, json_encode($changes_cache));
    fclose($fd);
} catch (Exception $e) {
    error_log("Caught exception $e");
}
unlink(LOCKF);
