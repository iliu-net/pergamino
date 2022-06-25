#!/bin/sh
. $(dirname "$0")/sql-common.sh

#
# Backup files
#
backup_dir=$(param BACKUPS)
snaps_dir=$(param SNAPS)
max_snaps=$(param MAX_SNAPS | tr -dc 0-9)

[ -z "$backup_dir" ] && exit 1

mkdir -p "$backup_dir"

filestore=$(param FILESTORE)
if [ x"$(expr substr "$filestore" 1 1)" != x"/" ] ; then
  filestore=$apphome/$filestore
fi
filestore=$(echo "$filestore" | sed -e 's!/*$!!')


mkdir -p "$backup_dir/filestore"
mysqldump \
	--skip-dump-date \
	-u$db_user -p$db_pass -h$db_host $db_name > "$backup_dir/latest.sql"
if [ -f "$backup_dir/database.sql" ] ; then
  if cmp -s "$backup_dir/database.sql" "$backup_dir/latest.sql" ; then
    echo "No changes found" 1>&2
    rm -f "$backup_dir/latest.sql"
  else
    rm -f "$backup_dir/database.sql"
    mv "$backup_dir/latest.sql" "$backup_dir/database.sql"
  fi
else
  mv "$backup_dir/latest.sql" "$backup_dir/database.sql"
fi
rsync --delete -a "$filestore"/ "$backup_dir/filestore/"

#
# Create snapshots
#
[ -z "$snaps_dir" ] && exit 0
[ -z "$max_snaps" ] && max_snaps=7

mkdir -p "$snaps_dir"

n=$max_snaps
while [ $n -gt 0 ]
do
  m=$(expr $n - 1) || :
  [ -e "$snaps_dir/$n" ] && rm -rf "$snaps_dir/$n"
  if [ -e "$snaps_dir/$m" ] ; then
    mv "$snaps_dir/$m" "$snaps_dir/$n"
  fi
  n=$m
done
cp -al "$backup_dir/." "$snaps_dir/0"
