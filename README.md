Online Certbot Hook
===================
A hook to help automatically renew Let's Encrypt certificates using the DNS-01 challenge
when your domain is managed by [Online.net](https://www.online.net).

### Rationale
Popular tools (e.g. [Lego](https://github.com/go-acme/lego)) used to perform this renewal task often lack the support for this provider
because Scaleway and Online.net administration consoles are now merged but it is still not possible
to manage Online.net domains via Scaleway's API.

This script simplifies the renewal process by using Certbot's support for renewal hooks and calls
Online.net's API to push and then delete the Acme Challenge *TXT* record.

### Installation
#### Requirements
The generated binary is a PHP Phar archive so you will need the following on your production server:
- PHAR support (which should be enabled by default),
- PHP 8.2+,
- cURL and JSON extensions for PHP,
- [Certbot](https://certbot.eff.org/).

***For development only***
- [Composer](https://www.getcomposer.org) (can be automatically installed by Task),
- [Box](https://github.com/box-project/box) (can be automatically installed by Task),
- [go-task](https://github.com/go-task/task), while not a requirement, strongly recommended.

#### From Github releases
Not yet available, will come at a later date.

#### From the source
```shell
git clone https://github.com/naeikindus/certbot-online.git
# install tooling
task setup
# install Composer dependencies and create a PHAR binary
task build
# then you can either install the PHAR binary locally
task install
# or copy it where you need it
scp bin/certbot-online.phar you@your-machine:~/
```

### Usage
***Generating certificates***
```shell
# Obtaining or renewing a basic certificate
certbot certonly -n --manual --manual-auth-hook certbot-online.phar --manual-cleanup-hook certbot-online.phar \
  --agree-tos --email <YOUR_EMAIL> --preferred-challenges dns \
  -d <YOUR_DOMAIN_NAME>
  
# Obtaining or renewing a wildcard certificate with OCSP stapling and HSTS
certbot certonly -n --manual --manual-auth-hook certbot-online.phar --manual-cleanup-hook certbot-online.phar \
  --hsts --must-staple --agree-tos --email <YOUR_EMAIL> --preferred-challenges dns \
  -d \*.<YOUR_DOMAIN_NAME>
```

The generated certificates will either be in your certbot's directory or, if you don't have one and are not running
as root, you will have an error thrown by certbot about missing directories or permission denied. You
can correct this (and choose where your certificates will be stored) by providing the following options:
```shell
certbot [...] --config-dir=/<SOME_DIR> --work-dir=/<ANOTHER_DIR> --logs-dir=/<YET_ANOTHER_DIR>
# e.g. certbot [...] --config-dir=/home/myuser --work-dir=/home/myuser --logs-dir=/var/log
```
