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

$payload = file_get_contents('php://input');

$lock_count = 0;

while (file_exists(LOCKF)) {
    if ($lock_count > 20) {
        http_response_code(500);
        die('Failed to get lock.');
    }
    sleep(3);
    ++$lock_count;
}
try {
    $res = touch(LOCKF);
    if (!$res) {
        throw new Exception('Failed to obtain lock '.LOCKF);
    }

    $signature = hash_hmac('sha1', $payload, WEBHOOK_SECRET);

    if (!isset($_SERVER['HTTP_REQUEST_METHOD']) || $_SERVER['HTTP_REQUEST_METHOD'] != 'POST') {
        throw new Exception('Invalid request method');
    }

    if (!isset($_SERVER['HTTP_X_GITHUB_EVENT']) || $_SERVER['HTTP_X_GITHUB_EVENT'] != 'push') {
        throw new Exception('Invalid GitHub event');
    }

    if (!isset($_SERVER['HTTP_X_HUB_SIGNATURE']) || $_SERVER['HTTP_X_HUB_SIGNATURE'] != 'sha='.$signature) {
        throw new Exception('Invalid message signature');
    }

    $changes_cache = [];
    if (file_exists(CHANGES_CACHE) && filesize(CHANGES_FILE) > 0) {
        $changes_cache = json_decode(file_get_contents(CHANGES_CACHE), true);
    }

    $json = json_decode($payload, true);

    if ($json['repository']['id'] != YANG_REPO_ID) {
        throw new Exception('Bad repository ID: '.$json['repository']['id']);
    }

    if (!isset($json['commits'])) {
        $json['commits'] = [];
    }

    foreach ($json['commits'] as $commit) {
        $files = array_merge($commit['added'], $commit['modified']);
        foreach ($files as $file) {
            if (!preg_match('/\.yang$/', $file)) {
                continue;
            }
            //$dir = dirname($file);
            if (array_search($file, $changes_cache) === false) {
                array_push($changes_cache, $file);
            }
        }
    }

    $fd = fopen(CHANGE_CACHE, 'w');
    fwrite($fd, json_encode($changes_cache));
    fclose($fd);
} catch (Exception $e) {
    error_log("Caught exception $e");
    unlink(LOCKF);
    http_response_code(403);
    die('Forbidden');
}
unlink(LOCKF);
