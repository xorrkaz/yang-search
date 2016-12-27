#!/bin/sh

function cleanup() {
    rm -f ${YANGDIR}/YANG-drafts.tar ${YANGDIR}/YANG-RFC.tar
    rm -rf ${YANGDIR}/tmp
}

if [ -z "${YANG_INDEX_HOME}" ]; then
    echo "ERROR: YANG_INDEX_HOME environment variable not defined; please set this to the path to the yindex.env file"
    exit 1
fi

. ${YANG_INDEX_HOME}/yindex.env

cd ${YANGDIR}
LATEST_DIR=${YANGDIR}/standard/ietf/latest

cleanup
mkdir -p tmp/DRAFT
mkdir -p tmp/RFC

curl -O http://www.claise.be/YANG-drafts.tar
if [ $? != 0 ]; then
    echo "ERROR: Failed to download drafts tarball"
    exit 1
fi

curl -O http://www.claise.be/YANG-RFC.tar
if [ $? != 0 ]; then
    echo "ERROR: Failed to download RFC tarball"
    exit 1
fi

cd tmp/DRAFT
tar -xf ${YANGDIR}/YANG-drafts.tar
if [ $? != 0 ]; then
    echo "ERROR: Failed to extract draft tarball"
    exit 1
fi

cd ${YANGDIR}/tmp/RFC
tar -xf ${YANGDIR}/YANG-RFC.tar
if [ $? != 0 ]; then
    echo "ERROR: Failed to extract RFC tarball"
    exit 1
fi

rm -rf ${LATEST_DIR}
mkdir -p ${LATEST_DIR}
mv -f ${YANGDIR}/tmp/DRAFT ${LATEST_DIR}
mv -f ${YANGDIR}/tmp/RFC ${LATEST_DIR}

cd ${YANGDIR}
export DRAFTS_DIR=${LATEST_DIR}/DRAFT
export RFCS_DIR=${LATEST_DIR}/RFC
export YANGREPO=${LATEST_DIR}

${TOOLS_DIR}/build_yindex.sh ${LATEST_DIR}
cleanup
