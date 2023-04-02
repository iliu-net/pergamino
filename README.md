# pergamino

Paperless Office tool

# Configuration

Changing upload size:

- tweak settings php.ini to increase upload file size
- change /etc/php7/php.ini :
  upload_tmp_dir - /data/tmp
  This can not be changed using .htaccess or ini_set.
- check if apache limits upload size
  - php.ini:
    - upload_max_filesize (defaults 2M)
    - post_max_size (defaults 8M)
  - apache httpd.conf or .htaccess  (defaults 2M)
    - <Directory "/var/www/example.com/wp-uploads">
    - LimitRequestBody 5242880
    - </Directory>
  - nginx : defaults to 1M
    - in http block:
    - client_max_body_size 100M;
- lookup nginx upload size


# TODO

- OCR
- Scheduled tasks:
  - check files that not in the artifact table
- separation
  - create a reference URL field
  - create an API
    - tags: CRUD
    - articles: CRUD
- tag list: sortable


# Changes

- 1.2.0-next
  - multiple tag searchs does AND instead of OR.
  - tag-list hyperlinked to arttifacts for that tag.
  - switching php dependancies to composer
- 1.2.0
  - new generate previews
  - switched to fatfree-core submodule
- 1.1.0
  - upgrading F3 to 3.8.0
  - fixing issues with php8.0 testing.  It nows requires php7 or newer.
  - Clean-ups
  - Tweak tag displays
  - Show expiration date in artifact listings
  - Artifact list: filter by expiration date
