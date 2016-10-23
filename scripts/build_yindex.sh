#!/bin/sh
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

# This script uses pyang to build an SQLite3 index of YANG modules
# and nodes, as well as pyang and symd to build JSON files containing
# tree and dependency structures for YANG modules.
#

# The file yindex.env must be sourced into the environment prior to running
# this script.

if [ -z "${DBF}" -o -z "${YTREE_DIR}" -o -z "${YDEP_DIR}" ]; then
    echo "ERROR: Environment not properly defined; be sure to source yindex.env"
    exit 1
fi

TDBF=$(mktemp)

mkdir -p ${YTREE_DIR}
mkdir -p ${YDEP_DIR}
mkdir -p $(dirname ${DBF})

if [ ${update_yang_repo} = 1 ]; then
    cd ${YANGDIR}
    git pull >/dev/null 2>&1
    if [ $? != 0 ]; then
        echo "WARNING: Failed to update YANG repo!"
    fi
fi

modules=""
update=0
first_run=1

if [ $# = 0 ]; then
    modules=$(find ${TYANGDIR} -type f -name "*.yang")
else
    for m in $*; do
        if [ -d ${m} ]; then
            mods=$(find ${m} -type f -name "*.yang")
            modules="${modules} ${mods}"
        else
            modules="${modules} ${m}"
        fi
    done
    update=1
    TDBF=${DBF}
    first_run=0
fi

for m in ${modules}; do
    cmd="pyang -p ${YANGDIR} -f yang-catalog-index --yang-index-make-module-table --yang-index-no-schema ${m}"
    if [ ${first_run} = 1 ]; then
        cmd="pyang -p ${YANGDIR} -f yang-catalog-index --yang-index-make-module-table ${m}"
        first_run=0
    fi
    mod_name=$(pyang -p ${YANGDIR} -f name ${m} 2>/dev/null | cut -d' ' -f1)
    if [ ${update} = 1 ]; then
        echo "DELETE FROM modules WHERE module='${mod_name}'; DELETE FROM yindex WHERE module='${mod_name}';" | sqlite3 ${TDBF}
    fi
    output=$(${cmd} 2> /dev/null)
#    echo "XXX: '${output}'"
    echo ${output} | sqlite3 ${TDBF}
    if [ $? != 0 ]; then
        echo "ERROR: Failed to update YANG DB for ${mod_name} (${m})!"
        continue
    fi
    ${TOOLS_DIR}/add-catalog-data.py ${m} ${mod_name} ${TDBF}
    if [ $? != 0 ]; then
        echo "WARNING: Failed to add YANG catalog data for ${mod_name} (${m})!"
    fi

    # Generate YANG tree data.
    pyang -p ${YANGDIR} -f json-tree -o "${YTREE_DIR}/${mod_name}.json" ${m}
    if [ $? != 0 ]; then
        echo "WARNING: Failed to generate YANG tree data for ${mod_name} (${m})!"
    fi
    symd -r --rfc-repos ${YANGDIR} --draft-repos ${YANGDIR} --json-output ${YDEP_DIR}/${mod_name}.json --single-impact-analysis-json ${mod_name} 2>/dev/null
    if [ $? != 0 ]; then
        echo "WARNING: Failed to generate YANG dependency data for ${mod_name} (${m})!"
    fi
done

if [ ${update} = 0 ]; then
    mv -f ${TDBF} ${DBF}
    chmod 0644 ${DBF}
fi
