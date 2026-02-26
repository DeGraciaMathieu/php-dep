.PHONY: build install clean test

PHAR = builds/php-dep.phar

build: ## Build the PHAR archive
	@echo "Building $(PHAR)..."
	composer install --optimize-autoloader --quiet
	vendor/bin/box compile
	@echo "Done: $(PHAR) ($$(du -sh $(PHAR) | cut -f1))"

install: build ## Install php-dep globally to /usr/local/bin
	@echo "Installing php-dep to /usr/local/bin..."
	cp $(PHAR) /usr/local/bin/php-dep
	chmod +x /usr/local/bin/php-dep
	@echo "Installed: $$(php-dep --version)"

clean: ## Remove built PHAR
	rm -f $(PHAR)

test: ## Run tests
	vendor/bin/phpunit

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'
