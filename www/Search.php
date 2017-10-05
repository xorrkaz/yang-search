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

require_once 'Module.php';

class SearchResult
{
    private $node = [];
    private $module = [];

    public function __construct($result)
    {
        if (isset($result['node'])) {
            $this->node = $result['node'];
        }
        if (isset($result['module'])) {
            $this->module = $result['module'];
        }
    }

    public function getNode($field)
    {
        if (array_search($field, Search::getNodeFields()) === false) {
            throw new Exception("Field {$field} is not a node field; must be one of ".implode(', ', Search::getNodeFields()));
        }

        if (!isset($this->node[$field])) {
            return null;
        }

        return $this->node[$field];
    }

    public function getModule($field)
    {
        if (array_search($field, Module::getFields()) === false) {
            throw new Exception("Field {$field} is not a module field; must be one of ".implode(', ', Module::getFields()));
        }

        if (!isset($this->module[$field])) {
            return null;
        }

        return $this->module[$field];
    }
}

class Search
{
    private $rester;
    private $search_term;
    private $type;
    private $case_sensitive;
    private $include_mibs;
    private $latest_revisions;
    private $search_fields;
    private $yang_versions;
    private $schema_types;
    private $node_filter;
    private $mod_filter;

    private static $types = [
      'keyword',
      'regex',
    ];

    private static $allowedSearchFields = [
      'module',
      'argument',
      'description',
    ];

    private static $allowedYangVersions = [
      '1.0',
      '1.1',
    ];

    private static $allowedSchemaTypes = [
      'typedef',
      'grouping',
      'feature',
      'identity',
      'extension',
      'rpc',
      'container',
      'list',
      'leaf-list',
      'leaf',
      'notification',
      'action',
    ];

    private static $allowedNodeFilter = [
      'name',
      'description',
      'path',
      'type',
    ];

    public function __construct($rester, $search_term, $type = 'keyword',
                                $case_sensitive = false, $include_mibs = false,
                                $latest_revisions = true, $search_fields = null,
                                $yang_versions = null, $schema_types = null,
                                $node_filter = null, $mod_filter = null)
    {
        $this->rester = $rester;

        Search::assertValid('search-term', $search_term);
        $this->search_term = $search_term;

        Search::assertValid('type', $type);
        $this->type = $type;

        Search::assertValid('case-sensitive', $case_sensitive);
        $this->case_sensitive = $case_sensitive;

        Search::assertValid('include-mibs', $include_mibs);
        $this->include_mibs = $include_mibs;

        Search::assertValid('latest-revisions', $latest_revisions);
        $this->latest_revisions = $latest_revisions;

        Search::assertValid('search-fields', $search_fields);
        $this->search_fields = $search_fields;

        Search::assertValid('yang-versions', $yang_versions);
        $this->yang_versions = $yang_versions;

        Search::assertValid('schema-types', $schema_types);
        $this->schema_types = $schema_types;

        Search::assertValid('node-filter', $node_filter);
        $this->node_filter = $node_filter;

        Search::assertValid('mod-filter', $mod_filter);
        $this->mod_filter = $mod_filter;
    }

    public static function getNodeFields()
    {
        return Search::$allowedNodeFilter;
    }

    private static function assertBoolean($name, $value)
    {
        if ($value !== true && $value !== false) {
            throw Exception("$name must be either true or false.");
        }
    }

    private static function assertArray($name, $value, $allowed = null)
    {
        if ($value !== null && !is_array($value)) {
            throw Exception("$name must be either null or an array.");
        }
        if ($allowed !== null) {
            foreach ($value as $val) {
                if (array_search($value, $allowed) === false) {
                    throw Exception("$val is not an allowed value; must be one of ".implode(', ', $allowed));
                }
            }
        }
    }

    private static function assertValid($type, $value)
    {
        switch ($type) {
        case 'search-term':
        if ($value === null || $value == '') {
            throw Exception("Search term cannot be empty.");
        }
        break;
        case 'type':
        if ($value === null || array_search($value, Search::$types) === false) {
            throw Exception("Type must be one of ".implode(', ', Search::$types));
        }
        break;
        case 'case-sensitive':
        Search::assertBoolean('Case-sensitive', $value);
        break;
        case 'include-mibs':
        Search::assertBoolean('Include-mibs', $value);
        break;
        case 'latest_revisions':
        Search::assertBoolean('Latest-revisions', $value);
        break;
        case 'search-fields':
        Search::assertArray('Search-fields', $value, Search::$allowedSearchFields);
        break;
        case 'yang-versions':
        Search::assertArray('YANG-versions', $value, Search::$allowedYangVersions);
        break;
        case 'schema-types':
        Search::assertArray('Schema-types', $value, Search::$allowedSchemaTypes);
        break;
        case 'node-filter':
        Search::assertArray('Node-filter', $value, Search::$allowedNodeFilter);
        break;
        case 'mod-filter':
        Search::assertArray('Module-filter', $value, Module::getFields());
        break;
      }
    }

    protected function toSearchPayload()
    {
        $payload = [];
        $payload['search'] = $this->search_term;
        $payload['type'] = $this->type;
        $payload['case-sensitive'] = $this->case_sensitive;
        $payload['include-mibs'] = $this->include_mibs;
        $payload['latest-revisions'] = $this->latest_versions;
        if ($this->search_fields !== null) {
            $payload['search-fields'] = $this->search_fields;
        }
        if ($this->yang_versions !== null) {
            $payload['yang-versions'] = $this->yang_versions;
        }
        if ($this->schema_types !== null) {
            $payload['schema-types'] = $this->schema_types;
        }
        if ($this->node_filter != null || $this->mod_filter !== null) {
            $payload['filter'] = [];
            if ($this->node_filter !== null) {
                $payload['filter']['node'] = $this->node_filter;
            }
            if ($this->mod_filter !== null) {
                $payload['filter']['module'] = $this->mod_filter;
            }
        }

        return json_encode($payload);
    }

    public function setSearchTerm($search_term)
    {
        Search::assertValid('search-term', $search_term);

        $this->search_term = $search_term;
    }

    public function setType($type)
    {
        Search::assertValid('type', $type);

        $this->type = $type;
    }

    public function setCaseSensitive($case_sensitive)
    {
        Search::assertValid('case-sensitive', $case_sensitive);

        $this->case_sensitive = $case_sensitive;
    }

    public function setIncludeMibs($include_mibs)
    {
        Search::assertValid('include-mibs', $include_mibs);

        $this->include_mibs = $include_mibs;
    }

    public function setLatestRevisions($latest_revisions)
    {
        Search::assertValid('latest-revisions', $latest_revisions);

        $this->latest_revisions = $latest_revisions;
    }

    public function setSearchFields($search_fields)
    {
        Search::assertValid('search-fields', $search_fields);

        $this->search_fields = $search_fields;
    }

    public function setYangVersions($yang_versions)
    {
        Search::assertValid('yang-versions', $yang_versions);

        $this->yang_versions = $yang_versions;
    }

    public function setSchemaTypes($schema_types)
    {
        Search::assertValid('schema-types', $schema_types);

        $this->schema_types = $schema_types;
    }

    public function setNodeFilter($node_filter)
    {
        Search::assertValid('node-filter', $node_filter);

        $this->node_filter = $node_filter;
    }

    public function setModFilter($mod_filter)
    {
        Search::assertValid('mod-filter', $mod_filter);

        $this->mod_filter = $mod_filter;
    }

    public function search()
    {
        $response = $this->rester->post('/index/search', $this->toSearchPayload());
        $hits = [];
        foreach ($response as $hit) {
            array_push($hits, new SearchResult($hit));
        }

        return $hits;
    }
}
