# pergamino

Paperless Office tool

# TODO

- OCR
- Scheduled tasks:
  - create preview's
  - check files that not in the artifact table

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


```php
<table>
  <?php foreach (['upload_max_filesize',
		'post_max_size',
		'upload_tmp_dir',
		'open_basedir',
		'sys_temp_dir',
		] as $k) {
		?>
		<tr>
		  <th><?= $k ?></th>
		  <td><?= ini_get($k) ?></td>
		</tr>
		<?php } ?>

</table>
```
