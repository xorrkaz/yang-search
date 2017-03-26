#!/usr/bin/env python
#
# Copyright (c) 2016  Joe Clarke <jclarke@cisco.com>
# All rights reserved.
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions
# are met:
# 1. Redistributions of source code must retain the above copyright
#    notice, this list of conditions and the following disclaimer.
# 2. Redistributions in binary form must reproduce the above copyright
#    notice, this list of conditions and the following disclaimer in the
#    documentation and/or other materials provided with the distribution.
#
# THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND
# ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
# IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
# ARE DISCLAIMED.  IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE
# FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
# DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS
# OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
# HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
# LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
# OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
# SUCH DAMAGE.

import sqlite3
import requests
import re
import os
import sys

# Script should be called with the following arguments
#  Database file: SQLite database file

if len(sys.argv) != 2:
    print("Usage: {} <DB file>".format(sys.argv[0]))
    sys.exit(1)

dbf = sys.argv[1]

# Map propietary namespaces to known org strings.
NS_MAP = {
    "http://cisco.com/ns/yang/": "cisco"
}

mods = {}

MATURITY_MAP = {
    "RFC": "http://www.claise.be/IETFYANGRFC.json",
    "DRAFT": "http://www.claise.be/IETFYANGDraft.json"
}

for m, u in MATURITY_MAP.items():
    try:
        r = requests.request("GET", u)
        r.raise_for_status()
    except requests.exceptions.HTTPError as e:
        print("Error fetching JSON data from {}: {}".format(u, e.args[0]))
        sys.exit(1)

    j = r.json()

    for mod, props in j.items():
        mod = os.path.splitext(mod)[0]
        mods[mod] = {}
        if m == 'DRAFT':
            if re.search(r'^draft-ietf-', mod):
                mods[mod]['maturity'] = 'WG DRAFT'
            else:
                mods[mod]['maturity'] = 'INDIVIDUAL DRAFT'
        else:
            mods[mod]['maturity'] = m
        reg = re.compile(r'<a.*?>(.*?)</a>', re.S | re.M)
        doc_tag = props
        if isinstance(props, list):
            doc_tag = props[0]
        match = reg.match(doc_tag)
        if match:
            mods[mod]['document'] = match.groups()[0].strip()
        else:
            mods[mod]['document'] = ''

try:
    con = sqlite3.connect(dbf)
    cur = con.cursor()
except sqlite3.Error as e:
    print("Error connecting to DB: {}".format(e.args[0]))
    sys.exit(1)

for modn, props in mods.items():
    mod_parts = modn.split('@')
    mod = mod_parts[0]
    rev = ''
    if len(mod_parts) == 2:
        rev = mod_parts[1]

    sql = 'UPDATE modules SET maturity=:maturity, document=:document WHERE module=:modn'
    params = {'maturity': props['maturity'],
              'document': props['document'], 'modn': mod}
    if rev != '':
        sql += ' AND revision=:rev'
        params['rev'] = rev
    try:
        cur.execute(sql, params)
    except sqlite3.Error as e:
        print("Failed to update module maturity for {}: {}".format(
            modn, e.args[0]))

for ns, org in NS_MAP.items():
    sql = 'UPDATE modules SET organization=:org WHERE namespace LIKE :ns'
    try:
        cur.execute(sql, {'org': org, 'ns': ns + '%'})
    except sqlite3.Error as e:
        print("Failed to update namespace data for {} => {}: {}".format(
            ns, org, e.args[0]))

con.commit()
con.close()
