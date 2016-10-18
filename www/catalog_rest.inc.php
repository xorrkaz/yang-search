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
include_once 'httpful.phar';

/*
 * Test that the REST response was good.  This function throws an exception
 * if the response is not in the 200 range.
 *
 * Input:
 *  $resp : (\Httpful\Response Obj) Response object
 *  $msg  : (string) Message to include in the exception
 */
function _assert_response($resp, $msg)
{
    if ($resp->code < 200 || $resp->code >= 300) {
        throw new RuntimeException("Failed to $msg: {$resp->code}");
    }
}

/*
 * Return an associative array reflecting a module's entry in the Catalog.
 *
 * Input:
 *  $org    : (string) organization name
 *  $modname: (string) YANG module name
 *  $user   : (string) Basic Auth username
 *  $pass   : (string) Basic Auth password
 *  $timeout: (integer) Number of seconds to wait before timing out (300)
 * Output:
 *  Associative array of all module properties.
 */
function get_module($org, $modname, $user = REST_USER, $pass = REST_PASS, $timeout = REST_TIMEOUT)
{
    $url = YANG_CATALOG_URL;

    $url .= "/organizations/organization/{$org}/modules/module/{$modname}?deep";
    $response = \Httpful\Request::get($url)->authenticateWithBasic($user, $pass)
    ->expects(RESTCONF_JSON_MIME_TYPE)->timeoutIn($timeout)->parseWith(function ($body) {
        $json = json_decode($body, true);

        return $json[OPENCONFIG_CATALOG_MOD_NS];
    })->send();

    _assert_response($response, "get module info for $org::$modname");

    return $response->body;
}

/*
 * Get a given module's prefix.
 *
 * Input:
 *  $org    : (string) organization name
 *  $modname: (string) YANG module name
 *  $user   : (string) Basic Auth username
 *  $pass   : (string) Basic Auth password
 *  $timeout: (integer) Number of seconds to wait before timing out (300)
 * Output:
 *  (string) Module's prefix
 */
function get_module_prefix($org, $modname, $user = REST_USER, $pass = REST_PASS, $timeout = REST_TIMEOUT)
{
    $mod = get_module($org, $modname, $user, $pass, $timeout);

    return $mod['prefix'];
}

/*
 * Get a given module's namespace.
 *
 * Input:
 *  $org    : (string) organization name
 *  $modname: (string) YANG module name
 *  $user   : (string) Basic Auth username
 *  $pass   : (string) Basic Auth password
 *  $timeout: (integer) Number of seconds to wait before timing out (300)
 * Output:
 *  (string) Module's namespace
 */
function get_module_namespace($org, $modname, $user = REST_USER, $pass = REST_PASS, $timeout = REST_TIMEOUT)
{
    $mod = get_module($org, $modname, $user, $pass, $timeout);

    return $mod['namespace'];
}

/*
 * Get a given module's revision.
 *
 * Input:
 *  $org    : (string) organization name
 *  $modname: (string) YANG module name
 *  $user   : (string) Basic Auth username
 *  $pass   : (string) Basic Auth password
 *  $timeout: (integer) Number of seconds to wait before timing out (300)
 * Output:
 *  (string) Module's revision date
 */
function get_module_revision($org, $modname, $user = REST_USER, $pass = REST_PASS, $timeout = REST_TIMEOUT)
{
    $mod = get_module($org, $modname, $user, $pass, $timeout);

    return $mod['revision'];
}

/*
 * Get a given module's list of required modules (i.e., imports).
 *
 * Input:
 *  $org    : (string) organization name
 *  $modname: (string) YANG module name
 *  $user   : (string) Basic Auth username
 *  $pass   : (string) Basic Auth password
 *  $timeout: (integer) Number of seconds to wait before timing out (300)
 * Output:
 *  (array) List of module's required modules
 */
function get_module_required_modules($org, $modname, $user = REST_USER, $pass = REST_PASS, $timeout = REST_TIMEOUT)
{
    $mod = get_module($org, $modname, $user, $pass, $timeout);

    return $mod['dependencies']['required_module'];
}
