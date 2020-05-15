build:
	docker-compose build
up: # Up services with current user/group
	docker-compose up -d

stop:
	docker-compose stop

assets-install:
	docker-compose exec php bin/console assets:install

npm-fix-permissions:
	docker-compose exec --user root php bash -c "mkdir -p /.npm"
	docker-compose exec --user root php bash -c "chmod -R 777 /.npm"

install-dependencies:
	make npm-fix-permissions
	docker-compose exec php composer install

cache-clear:
	make xdebug-disable
	docker-compose exec php bin/console c:c

install-oro: # Install oro from scratch. Database is dropped.
	make xdebug-disable
	docker-compose exec php bash -c "rm -rf var/cache/"
	make install-dependencies
	docker-compose exec php bash -c "bin/console doctrine:database:create --if-not-exists"
	docker-compose exec php bash -c "php -dxcache.cacher=0 bin/console oro:install --application-url=http://localhost --env=prod --user-name=admin --user-email=admin@example.com --user-firstname=John --user-lastname=Doe --user-password=admin --sample-data=y --organization-name=Acme --language=en --formatting-code=en --timeout=10000 --drop-database"

xdebug-disable:
	docker-compose exec --user root php bash -c 'echo ";;zend_extension=/usr/local/lib/php/extensions/no-debug-non-zts-20190902/xdebug.so" > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini'
	docker-compose restart php

xdebug-enable:
	docker-compose exec --user root php bash -c 'echo "zend_extension=/usr/local/lib/php/extensions/no-debug-non-zts-20190902/xdebug.so" > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini'
	docker-compose restart php

platform-update:
	make xdebug-disable
	docker-compose exec php bash -c "bin/console oro:platform:update --force"
