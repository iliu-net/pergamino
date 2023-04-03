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




