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
    'tree-type' => true,
    'namespace' => true,
    'ietf' => true,
    'submodule' => true,
    'implementations' => true
  ];
    private $rester;
    private $initialized = false;

    private static $seen_modules = [];

    private function __construct($rester, $name, $revision, $organization, $attrs = [])
    {
        $this->rester = $rester;

        foreach (Module::$objectHash as $key => $value) {
            if (isset($attrs[$key])) {
                $this->$key = $attrs[$key];
            } else {
                $this->$key = null;
            }
        }

        if (count($attrs) > 0) {
            $this->initialized = true;
        }

        $this->name = $name;
        $this->revision = $revision;
        $this->organization = $organization;
    }

    public static function moduleFactory($rester, $name, $revision, $organization, $override = false, $attrs = [])
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
            Module::$seen_modules[$mod_sig] = new Module($rester, $name, $revision, $organization, $attrs);
        }

        return Module::$seen_modules[$mod_sig];
    }

    private function fetch()
    {
        if ($this->initialized === true) {
            return;
        }
        $result = $this->rester->get('/search/modules/'.urlencode($this->name).','.urlencode($this->revision).','.urlencode($this->organization));
        foreach ($result['module'][0] as $key => $value) {
            if (isset(Module::$objectHash[$key])) {
                $this->$key = $value;
            } else {
                throw new Exception("Failed to set key {$key}: not defined");
            }
        }

        $this->initialized = true;
    }

    public function get($field)
    {
        if (!isset(Module::$objectHash[$field])) {
            throw new Exception("Field $field does not exist; please specify one of:\n\n".implode("\n", array_keys(Module::$objectHash)));
        }

        if ($this->initialized === false) {
            $this->fetch();
        }

        return $this->$field;
    }

    public function getModSig()
    {
        return "{$this->name}@{$this->revision}/{$this->organization}";
    }

    public function toArray()
    {
        if ($this->initialized === false) {
            $this->fetch();
        }

        $arr = [];

        foreach (Module::$objectHash as $key => $value) {
            if (isset($this->$key)) {
                $arr[$key] = $this->$key;
            }
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
