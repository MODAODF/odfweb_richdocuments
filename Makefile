# Makefile for building the project

app_name=richdocuments
project_dir=$(CURDIR)/../$(app_name)
package_name=$(app_name)
occ=$(CURDIR)/../../occ
target_dir=$(CURDIR)/build/
version=6.1.0
composer=$(shell which composer 2> /dev/null)

clean:
	rm -rf $(target_dir)

buildjs:
	npm ci
	npm run build

.PHONY: composer
composer:
ifeq (, $(composer))
	@echo "No composer command available"
else
	composer install --prefer-dist --no-dev
endif

apppackage: clean composer buildjs
	mkdir -p $(target_dir)
	rsync -a \
	--exclude=.git \
	--exclude=.github \
	--exclude=build \
	--exclude=.gitignore \
	--exclude=.travis.yml \
	--exclude=.scrutinizer.yml \
	--exclude=CONTRIBUTING.md \
	--exclude=composer.phar \
	--exclude=.tx \
	--exclude=.vscode \
	--exclude=.nextcloudignore \
	--exclude=l10n/no-php \
	--exclude=Makefile \
	--exclude=nbproject \
	--exclude=screenshots \
	--exclude=phpunit*xml \
	--exclude=tests \
	--exclude=vendor/bin \
	--exclude=node_modules \
	--exclude=package-lock.json \
	--exclude=package.json \
	--exclude=postcss.config.js \
	--exclude=src \
	--exclude=tsconfig.json \
	--exclude=webpack.* \
	--exclude=issue_template.md \
	--exclude=krankerl.toml \
	--exclude=mkdocs.yml \
	$(project_dir) $(target_dir)
	rsync -ra $(CURDIR)/vendor $(target_dir)/$(app_name)
	tar -czf $(target_dir)/$(app_name).tar.gz \
		-C $(target_dir) $(app_name)