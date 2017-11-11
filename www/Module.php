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

class Module
{
    private static $objectHash = [
    'name' => true,
    'revision' => true,
    'organization' => true,
    'ietf' => true,
    'namespace' => true,
    'schema' => true,
    'generated-from' => true,
    'maturity-level' => true,
    'document-name' => true,
    'author-email' => true,
    'reference' => true,
    'module-classification' => true,
    'compilation-status' => true,
    'compilation-result' => true,
    'prefix' => true,
    'yang-version' => true,
    'description' => true,
    'contact' => true,
    'module-type' => true,
    'belongs-to' => true,
    'tree-type' => true,
    'yang-tree' => true,
    'expires' => true,
    'submodule' => true,
    'dependencies' => false,
    'dependents' => false,
    'semantic-version' => true,
    'derived-semantic-version' => true,
    'implementations' => true
  ];
    private $rester;
    private $yang_suite;
    private $ys_url = null;
    private $initialized = false;

    private static $seen_modules = [];

    private function __construct($rester, $name, $revision, $organization, $yang_suite = false, $attrs = [])
    {
        $this->rester = $rester;

        foreach (Module::$objectHash as $key => $value) {
            if (array_key_exists($key, $attrs)) {
                $this->$key = $attrs[$key];
            } else {
                $this->$key = null;
            }
        }

        if (count($attrs) > 0) {
            $this->initialized = true;
        }

        $this->name = $name;
        if ($revision == '') {
            $revision = '1970-01-01';
        }
        $this->revision = $revision;
        $this->organization = $organization;

        $this->yang_suite = $yang_suite;
    }

    public static function moduleFactory($rester, $name, $revision, $organization, $override = false, $yang_suite = false, $attrs = [])
    {
        $mod_sig = "{$name}@{$revision}/{$organization}";
        $create_new = false;
        if (!isset(Module::$seen_modules[$mod_sig])) {
            $create_new = true;
        } elseif ($override) {
            Module::$seen_modules[$mod_sig] = null;
            $create_new = true;
        }

        if ($create_new) {
            Module::$seen_modules[$mod_sig] = new Module($rester, $name, $revision, $organization, $yang_suite, $attrs);
        }

        return Module::$seen_modules[$mod_sig];
    }

    private function fetch()
    {
        if ($this->initialized === true) {
            return;
        }
        $headers = [];
        if ($this->yang_suite) {
            $headers['yangsuite'] = 'true';
            $headers['yangset_name'] = $this->name;
        }
        $result = $this->rester->get('/search/modules/'.urlencode($this->name).','.urlencode($this->revision).','.urlencode($this->organization), $headers);
        foreach ($result['module'][0] as $key => $value) {
            if (isset(Module::$objectHash[$key])) {
                $this->$key = $value;
            } else {
                throw new Exception("Failed to set key {$key}: not defined");
            }
        }

        if ($this->yang_suite && isset($result['yangsuite-url'])) {
            $this->ys_url = $result['yangsuite-url'];
        }

        $this->initialized = true;
    }

    public function get($field)
    {
        if (!array_key_exists($field, Module::$objectHash)) {
            throw new Exception("Field $field does not exist; please specify one of:\n\n".implode("\n", array_keys(Module::$objectHash)));
        }

        if ($this->initialized === false) {
            $this->fetch();
        }

        return $this->$field;
    }

    public static function isField($field)
    {
        return array_key_exists($field, Module::$objectHash);
    }

    public static function autoExpand($field)
    {
        if (!array_key_exists($field, Module::$objectHash)) {
            throw new Exception("Field $field does not exist; please specify one of:\n\n".implode("\n", array_keys(Module::$objectHash)));
        }

        return Module::$objectHash[$field];
    }

    public function getName()
    {
        return $this->name;
    }

    public function getOrganization()
    {
        return $this->organization;
    }

    public function getRevision()
    {
        return $this->revision;
    }

    public function getRester()
    {
        return $this->rester;
    }

    public function getModSig()
    {
        return "{$this->name}@{$this->revision}/{$this->organization}";
    }

    public static function getFields()
    {
        return array_keys(Module::$objectHash);
    }

    public function getYangSuiteURL()
    {
        if ($this->initialized === false) {
            $this->fetch();
        }

        return $this->ys_url;
    }

    public function toArray()
    {
        if ($this->initialized === false) {
            $this->fetch();
        }

        $arr = [];

        foreach (Module::$objectHash as $key => $value) {
            $arr[$key] = $this->$key;
        }

        return $arr;
    }

    public function __destruct()
    {
        $mod_sig = $this->getModSig();
        if (isset(Module::$seen_modules[$mod_sig])) {
            unset(Module::$seen_modules[$mod_sig]);
        }
    }
}
