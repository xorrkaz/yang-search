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
import re
import os
import sys

# Script should be called with the following arguments
#  Module file  : fully-qualified path to the YANG module
#  Module name  : Name of YANG module
#  Database file: SQLite database file

if len(sys.argv) != 4:
    print("Usage: {} <mod path> <mod name> <DB file>".format(sys.argv[0]))
    sys.exit(1)

modf = sys.argv[1]
modn = sys.argv[2]
dbf = sys.argv[3]

# Map propietary namespaces to known org strings.
NS_MAP = {
    "http://cisco.com/ns/yang/": "cisco"
}

DIR_MAP = {
    "rfc": "RFC",
    "draft": "DRAFT"
}

maturity = 'N/A'

# XXX This is a hack as the catalog does not yet list maturity.
# This gives us RFC status for the IETF, but nothing else.
dirs = os.path.normpath(os.path.dirname(modf)).split(os.sep)
for dk, dv in DIR_MAP.items():
    for d in dirs:
        if re.search(dk, d, re.I):
            maturity = dv
            break
    else:
        continue
    break

try:
    con = sqlite3.connect(dbf)
    cur = con.cursor()
except sqlite3.Error as e:
    print("Error connecting to DB: {}".format(e.args[0]))
    sys.exit(1)

sql = 'UPDATE modules SET maturity=:maturity WHERE module=:modn'
try:
    cur.execute(sql, {'maturity': maturity, 'modn': modn})
    con.commit()
except sqlite3.Error as e:
    print("Failed to update module maturity for {}: {}".format(
        modn, e.args[0]))
    sys.exit(1)

sql = 'SELECT namespace FROM modules WHERE module=:modn'
try:
    cur.execute(sql, {'modn': modn})
except sqlite3.Error as e:
    print("Failed to get module data for {}: {}".format(modn, e.args[0]))
    sys.exit(1)

ns = cur.fetchone()[0]
for n, o in NS_MAP.items():
    if re.search(n, ns, re.I):
        sql = 'UPDATE modules SET organization=:org WHERE module=:modn'
        try:
            cur.execute(sql, {'org': o, 'modn': modn})
            con.commit()
        except sqlite3.Error as e:
            print("Failed to update organization for {}: {}".format(
                modn, e.args[0]))
            sys.exit(1)
        break

con.close()
