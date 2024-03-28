#!/bin/sh
. $(dirname "$0")/sql-common.sh
set -euf
(set -o pipefail 2>/dev/null) && set -o pipefail

#
# Restore from backup
#
if [ $# -eq 0 ] ; then
  echo "Usage: $0 /backup/dir/path"
  exit 1
fi

restore_dir="$1"
if [ ! -d "$restore_dir" ] ; then
  echo "$restore_dir: not found"
  exit 1
fi
if [ ! -f "$restore_dir/database.sql" ] ; then
  echo "$restore_dir: Missing database.sql file"
  exit 1
fi
if [ ! -d "$restore_dir/filestore" ] ; then
  echo "$restore_dir: Missing filestore directory"
  exit 1
fi

filestore=$(param FILESTORE)
if [ x"$(expr substr "$filestore" 1 1)" != x"/" ] ; then
  filestore=$apphome/$filestore
fi
filestore=$(echo "$filestore" | sed -e 's!/*$!!')

rsync -avzH --delete "$restore_dir/filestore/" "$filestore"
$(dirname "$0")/sqladm.sh < "$restore_dir/database.sql"
