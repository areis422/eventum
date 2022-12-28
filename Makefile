#
# This is Maintainers makefile
#
# See installation documentation how to install Eventum:
# https://github.com/eventum/eventum/wiki/System-Admin%3A-Doing-a-fresh-install#installation-process
#

name            := eventum
datadir         := /usr/share/$(name)
sysconfdir      := $(datadir)/config
sbindir         := /usr/sbin
bindir          := /usr/bin
logdir          := /var/log/$(name)
smartyplugindir := $(datadir)/lib/Smarty/plugins
docker_progress := auto

PHPCOMPATINFO_VERSION := 5.0.1
PHPUNIT_VERSION := 4.8.11
PHPAB_VERSION := 1.20.3
PHING_VERSION := 2.16.4
PHPCB_VERSION := 1.1.1
PHPCS_FIXER_VERSION := 2.14.1
PHPMD_VERSION := 2.6.0
CODECEPT_VERSION := 2.3.6
PSALM_VERSION := 3.1.1
COMPOSER_VERSION:= ^1.10

define find_tool
$(shell PATH=$$PATH:. which $1.phar 2>/dev/null || which $1 2>/dev/null || echo false)
endef

define fetch_tool
curl -sSLf $1 -o $@.tmp && chmod +x $@.tmp && mv $@.tmp $@
endef

define phive
phive install --target bin/tools $1@$2 $3
ln -snf bin/tools/$1 $1.phar
endef

php-cs-fixer := $(call find_tool, php-cs-fixer)
phpcompatinfo := $(call find_tool, phpcompatinfo)
gush := $(call find_tool, gush)

all:
	@echo 'Run "make install" to install eventum.'

pot:
	$(MAKE) -C localization pot
	# push to bzr if "po" directory exists
	if test -d po; then \
		test -d po/.bzr && (cd po && bzr pull); \
		cp -p localization/*.pot po/localization; \
		test -d po/.bzr && (cd po && bzr commit -m "update .pot" && bzr push); \
	fi

install: install-eventum

snapshot:
	bin/releng/snapshot.sh

# make dist docker_progress=plain
dist:
	@set -xe; \
	APP_VERSION=`git describe --tags --abbrev=9 --match="v*"`; \
	docker build -f bin/releng/Dockerfile --target=out . -o releases --build-arg=APP_VERSION=$${APP_VERSION#v} --progress=$(docker_progress)

test:
	phpunit

box.phar:
	curl -LSs https://box-project.github.io/box2/installer.php | php

composer.phar:
	$(call phive,composer,$(COMPOSER_VERSION),--force-accept-unsigned)

php-cs-fixer.phar:
	$(call fetch_tool,https://github.com/FriendsOfPHP/PHP-CS-Fixer/releases/download/v$(PHPCS_FIXER_VERSION)/php-cs-fixer.phar)

phpcompatinfo.phar:
	$(call fetch_tool,http://bartlett.laurent-laville.org/get/phpcompatinfo-$(PHPCOMPATINFO_VERSION).phar)

phpunit.phar:
	$(call fetch_tool,https://phar.phpunit.de/phpunit-$(PHPUNIT_VERSION).phar)

phpab.phar:
	$(call fetch_tool,http://phpab.net/phpab-$(PHPAB_VERSION).phar)

phpcb.phar:
	$(call fetch_tool,https://github.com/mayflower/PHP_CodeBrowser/releases/download/$(PHPCB_VERSION)/phpcb-$(PHPCB_VERSION).phar)

phpmd.phar:
	$(call fetch_tool,https://static.phpmd.org/php/$(PHPMD_VERSION)/phpmd.phar)

phing.phar:
	$(call fetch_tool,https://www.phing.info/get/phing-$(PHING_VERSION).phar)

gush.phar:
	$(call fetch_tool,http://gushphp.org/gush.phar)

codecept.phar:
	$(call fetch_tool,http://codeception.com/releases/$(CODECEPT_VERSION)/php54/codecept.phar)

psalm.phar:
	$(call fetch_tool,https://github.com/vimeo/psalm/releases/download/$(PSALM_VERSION)/psalm.phar)

phpcs-fix: php-cs-fixer.phar
	$(php-cs-fixer) fix --verbose

phpcompatinfo: phpcompatinfo.phar
	$(phpcompatinfo) analyser:run --alias current

changelog:
	$(gush) branch:changelog

composer.lock:
	composer install

# https://security.sensiolabs.org/api
composer-security-checker: composer.lock
	curl -H "Accept: text/plain" https://security.sensiolabs.org/check_lock -F lock=@composer.lock

# install eventum core
install-eventum:
	install -d $(DESTDIR)$(sysconfdir)
	cp -a config/* $(DESTDIR)$(sysconfdir)

	install -d $(DESTDIR)$(datadir)/lib
	cp -a lib/eventum $(DESTDIR)$(datadir)/lib
	cp -a htdocs $(DESTDIR)$(datadir)
	cp -a templates $(DESTDIR)$(datadir)
	cp -a bin $(DESTDIR)$(datadir)
	cp -a src $(DESTDIR)$(datadir)
	cp -a res $(DESTDIR)$(datadir)
	cp -a db $(DESTDIR)$(datadir)
	cp -a *.php $(DESTDIR)$(datadir)

	install -d $(DESTDIR)$(logdir)
	cp -a var/log/* $(DESTDIR)$(logdir)

install-localization:
	$(MAKE) -C localization install
