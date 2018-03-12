APP_NAME := logmon
ROOT_DIR := $(realpath $(dir $(realpath $(MAKEFILE_LIST))))

VERSION := $(shell git describe --tags 2>/dev/null | sed s/^v//)
ifeq ($(VERSION),)
VERSION := unknown
DEB_VERSION := 0.0.0
else
DEB_VERSION := $(VERSION)
endif

BUILD_DIR := $(ROOT_DIR)/.build
PHAR_BUILD_DIR := $(BUILD_DIR)/phar
DEB_BUILD_DIR := $(BUILD_DIR)/deb
DIST_DIR := $(ROOT_DIR)/dist
PHAR := $(DIST_DIR)/logmon-$(VERSION).phar
DEB := $(DIST_DIR)/logmon-$(VERSION).deb
DEB_CONTROL_TEMPLATE := $(ROOT_DIR)/support/deb/control-template
CRON_TEMPLATE := $(ROOT_DIR)/support/deb/cron-template
DEFAULT_OPTIONS_TEMPLATE := $(ROOT_DIR)/support/default-options-template.php
SOURCES := $(shell find $(ROOT_DIR)/src -name '*.php')
SOURCES += $(ROOT_DIR)/bin/logmon


.PHONY: all
all: phar deb

.PHONY: phar
phar: $(PHAR)

.PHONY: deb
deb: $(DEB)

.PHONY: clean
clean:
	@echo "== Cleaning up build and dist directories"
	rm -rf $(DIST_DIR) $(BUILD_DIR)

$(PHAR): $(SOURCES) $(DEFAULT_OPTIONS_TEMPLATE) $(ROOT_DIR)/composer.json $(ROOT_DIR)/composer.lock | $(DIST_DIR)
	@echo "== Removing previous build directory"
	test ! -e $(PHAR_BUILD_DIR) || rm -rf $(PHAR_BUILD_DIR)
	@echo

	@echo "== Creating base directories"
	mkdir -p $(PHAR_BUILD_DIR)
	@echo

	@echo "== Setting up sources"
	cp -r \
		$(ROOT_DIR)/bin \
		$(ROOT_DIR)/src \
		$(ROOT_DIR)/composer.json \
		$(ROOT_DIR)/composer.lock \
		$(PHAR_BUILD_DIR)
	@echo

	@echo "== Installing vendors"
	cd $(PHAR_BUILD_DIR) && composer install --no-interaction --no-dev --classmap-authoritative
	@echo

	@echo "== Setting default options"
	cp $(DEFAULT_OPTIONS_TEMPLATE) $(PHAR_BUILD_DIR)/default-options.php
	@echo

	@echo "== Setting up version info"
	sed -i -e "s/%VERSION%/$(VERSION)/" $(PHAR_BUILD_DIR)/bin/logmon
	@echo

	@echo "== Cleaning up sources"
	find $(PHAR_BUILD_DIR) -type f -a \( \
		-name LICENSE \
		-o -name '*.md' \
		-o -name 'README' \
		-o -name '.travis.yml' \
		-o -name 'phpunit.xml.dist' \
		-o -name 'phpunit.xml' \
		-o -name '.gitignore' \
		\) -exec rm {} +
	find $(PHAR_BUILD_DIR)/vendor -type f -a \( \
		-name 'composer.json' \
		\) -exec rm {} +

	rm -rf \
		$(PHAR_BUILD_DIR)/vendor/ulrichsg/getopt-php/docs \
		$(PHAR_BUILD_DIR)/vendor/ulrichsg/getopt-php/test
	rm -f $(PHAR_BUILD_DIR)/vendor/ulrichsg/getopt-php/Makefile

	rm -rf $(PHAR_BUILD_DIR)/support
	rm -f $(PHAR_BUILD_DIR)/default-options.sample.php
	rm -f $(PHAR_BUILD_DIR)/Makefile
	rm -f $(PHAR_BUILD_DIR)/.editorconfig
	@echo

	@echo "== Building PHAR file"
	cd $(PHAR_BUILD_DIR) && phar-composer build $(PHAR_BUILD_DIR) $(PHAR)
	chmod +x $(PHAR)
	@echo

	@echo "== Removing build directory"
	rm -rf $(PHAR_BUILD_DIR)
	@echo

$(DEB): $(PHAR) $(DEB_CONTROL_TEMPLATE) $(CRON_TEMPLATE) | $(DIST_DIR)
	@echo "== Removing previous build directory"
	test ! -e $(DEB_BUILD_DIR) || rm -rf $(DEB_BUILD_DIR)
	@echo

	@echo "== Creating directory structure"
	mkdir -p \
		$(DEB_BUILD_DIR) \
		$(DEB_BUILD_DIR)/DEBIAN \
		$(DEB_BUILD_DIR)/var/lib/logmon \
		$(DEB_BUILD_DIR)/usr/bin \
		$(DEB_BUILD_DIR)/usr/share/logmon

	chmod 0700 $(DEB_BUILD_DIR)/var/lib/logmon
	@echo

	@echo "== Adding application phar file"
	cp $(PHAR) $(DEB_BUILD_DIR)/usr/bin/logmon
	chmod a+x $(DEB_BUILD_DIR)/usr/bin/logmon
	@echo

	@echo "== Adding extra files"
	cp $(CRON_TEMPLATE) $(DEB_BUILD_DIR)/usr/share/logmon/cron.sample
	cp $(ROOT_DIR)/README.md $(DEB_BUILD_DIR)/usr/share/logmon/README.md
	@echo

	@echo "== Creating deb control file"
	cat $(DEB_CONTROL_TEMPLATE) | \
		sed \
			-e "s/%VERSION%/$(DEB_VERSION)/g" \
			-e "s/%SIZE%/`du -s $(DEB_BUILD_DIR) | sed 's/^\([0-9]\+\).*$$/\1/'`/g" \
		> $(DEB_BUILD_DIR)/DEBIAN/control
	@echo

	@echo "== Building deb file"
	fakeroot dpkg -b $(DEB_BUILD_DIR) $(DEB)
	@echo

	@echo "== Removing build directory"
	rm -rf $(DEB_BUILD_DIR)
	@echo

$(DIST_DIR):
	test -e $@ || mkdir -p $@
