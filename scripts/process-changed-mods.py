#!/usr/bin/env python
#
# Copyright (c) 2017  Joe Clarke <jclarke@cisco.com>
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

import json
import sys
import os
import argparse
from subprocess import call

mod_list = []
find_args = []

parser = argparse.ArgumentParser(
    description="Process changed modules in a git repo")
parser.add_argument('--time', type=str,
                    help='Modified time argument to find(1)', required=False)
args = parser.parse_args()

if args.time:
    find_args = ['-f', args.time]

try:
    if os.path.getsize(os.environ['YANG_CACHE_FILE']) > 0:
        fd = open(os.environ['YANG_CACHE_FILE'], 'r+')
        mod_list = json.load(fd)

        # Backup the contents just in case.
        bfd = open(os.environ['YANG_CACHE_FILE'] + '.bak', 'w')
        json.dump(mod_list, bfd)
        bfd.close()

        # Zero out the main file.
        fd.seek(0)
        fd.truncate()
        fd.close()
except Exception as e:
    print('Failed to read cache file {}'.format(e))
    sys.exit(1)

args = ['./build_yindex.sh'] + find_args + \
    [os.environ['YANGDIR'] + '/' + m for m in mod_list]

os.chdir(os.environ['TOOLS_DIR'])
call(args)
