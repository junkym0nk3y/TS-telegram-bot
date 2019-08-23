# Simple (kind of) PHP Telegram bot
I made this bot is specialy for [chat Typical Sysadmin ("Типичный Сисадмин")](https://t.me/sysodmins_chat), but you may take it, if you needed same functional:

>1. Set profile information for community members
>2. Kick all members who have not written anything for two month (or set any time interval)
>3. Make stickers with members quotes

## Contents:
<p align="center">
  <img src="https://github.com/junkymonk3y/TS-telegram-bot/raw/master/docs/git_logo.png?raw=true" alt="Telegram bot logo"/>
</p>  

[Dependencies](#dependencies)  
[Installation](#installation)
  1. [Change web server configuration for one access point](#web-server-configuration)
  2. [Register new telegram bot and set up webhook](#register-telegram-bot)
  3. [Create telegram.ini file and save it out of public directories](#settings)
  4. [Download 'Roboto' font-family](#install-roboto)

[Debug](#debug)  
[Todo](#todo)

## Dependencies
&ensp;&ensp;PHP >= 7.1  
&ensp;&ensp;[Composer](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-macos)  
&ensp;&ensp;Apache or Nginx  
&ensp;&ensp;[SSL certificate](https://letsencrypt.org)  
&ensp;&ensp;MySQL Database  
&ensp;&ensp;[Roboto font-family](#install-roboto)

## Installation
### Web server configuration
<details>
  <summary>Nginx</summary>
  
  /etc/nginx/sites-available/Site.conf sample (with PHP-FPM)
  
```
server {
  server_name youre-domain.com;
  set $docroot "/var/www/host";
  root $docroot;
  index index.php;
  access_log off;

  location ~ /\. { deny all; }

  location / {
    include fastcgi_params;
    fastcgi_pass unix:/var/run/php/php7.2-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $docroot/index.php;
 }

  listen 443 ssl;
  ssl_certificate /etc/letsencrypt/live/youre-domain.com/fullchain.pem;
  ssl_certificate_key /etc/letsencrypt/live/youre-domain.com/privkey.pem;
  include /etc/letsencrypt/options-ssl-nginx.conf;
  ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;
}

server {
  if ($host = youre-domain.com) {
    return 301 https://$host$request_uri;
  }

  listen 80;
  server_name youre-domain.com;
  return 404;
}
```

</details>

<details>
  <summary>Apache</summary>

  ./.htaccess sample

```
RewriteEngine On
RewriteCond %{HTTP:X-Forwarded-Proto} !=https
RewriteRule .* https://%{HTTP_HOST}%{REQUEST_URI} [R=302,L]
RewriteCond %{REQUEST_URI} !^/
RewriteRule ^(.*)$ /$1 [L]

Order Deny,Allow
Deny from all
Satisfy any
```

</details>

### Register Telegram bot
&ensp;&ensp;[How create new bot and get token](https://core.telegram.org/bots#3-how-do-i-create-a-bot) (Telegram API Documentation)  
&ensp;&ensp;[Set webhook](https://core.telegram.org/bots/api#setwebhook) (Telegram API Documentation)  
&ensp;&ensp;[Or read this article](https://habr.com/ru/post/347482) on Russian (Habr.ru)  

&ensp;&ensp;Change bot privacy with [@BotFather](https://t.me/BotFather):  
&ensp;&ensp;**/setjoingroups** to 'Enable'  
&ensp;&ensp;**/setprivacy** to 'Disable'

### Settings
&ensp;&ensp;Save file on /var/www/telegtam.ini with chmod 400 (and set you're settings, of course):
```bash
[database]
  host     = 'localhost'
  username = 'root'
  password = 'password'
  dbname   = 'telegram'
[telegram]
  token    = ''           ; How to get it? Read information below
  master   =              ; Bot and sticker-sets owner
  proxy    = 'socks5://'
```

### Install Roboto
1. Download font-family

&ensp;&ensp;&ensp;&ensp;**(variant 1)** If your server based on linux debian, you can install this font right from repo:
```console
l@me:~$ sudo apt update && sudo apt install fonts-roboto
l@me:~$ cd /usr/share/fonts/truetype/roboto/hinted/
```
&ensp;&ensp;&ensp;&ensp;**(variant 2)** Also you may download font from [google/fonts github](https://github.com/google/fonts/blob/master/apache/roboto):
```console
l@me:~$ mkdir ~/tmp
l@me:~$ cd ~/tmp
l@me:~/tmp$ wget https://raw.githubusercontent.com/google/fonts/master/apache/roboto/Roboto-Regular.ttf
l@me:~/tmp$ wget https://raw.githubusercontent.com/google/fonts/master/apache/roboto/Roboto-Bold.ttf
```
&ensp;&ensp;&ensp;&ensp;**(variant 3)** Or from developer.android.com (no diference):
```console
l@me:~$ mkdir ~/tmp
l@me:~$ cd ~/tmp
l@me:~/tmp$ wget http://developer.android.com/downloads/design/Roboto_Hinted_20120823.zip
l@me:~/tmp$ unzip Roboto_Hinted_20120823.zip
```

2. Copy fonts to your project directory
```console
l@me:~/tmp$ BOT="your project dir"
l@me:~/tmp$ sudo mkdir -p $BOT/style/fonts/ 
l@me:~/tmp$ sudo cp Roboto-Regular.ttf $_
l@me:~/tmp$ sudo cp Roboto-Bold.ttf $_
```

3. Change permissions **(optimal)**
```console
l@me:~$ sudo chown -R www-data:www-data $BOT/style/
l@me:~$ sudo find $BOT/style/ -type d -exec chmod 755 {} \;
l@me:~$ sudo find $BOT/style/ -type f -exec chmod 644 {} \;
```
4. Remove tmp folder, if you need to **(optimal)**
```console
l@me:~$ rm -r ~/tmp
```

## Debug
1. Set const DEBUG to true in /app/configs/config.php
2. Run in Terminal
```console
l@me:~/tmp$ BOT="your project dir"
l@me:~/tmp$ watch -n 1 sudo tail -n 10  $BOT/*.log /var/log/nginx/error.log
```
3. Check sql sometimes
```sql
SELECT user_id, user_first_name, status, msgs, karma, lie_times, UNIX_TIMESTAMP(last_seen), user_bio AS last_seen FROM table ORDER BY msgs DESC;
```

## TODO
- [ ] Make comments great again  
- [ ] Make help files on git  
- [ ] Create language files
