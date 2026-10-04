PKL ?= pkl

.PHONY: config
config: ## Render shared/config.pkl into Laravel (PHP) and Flutter (YAML) configs
	$(PKL) eval shared/laravel.pkl -o server/config/shared.php
	$(PKL) eval shared/flutter.pkl -o mobile/assets/config/shared.yaml

.PHONY: check-config test
check-config: config ## Fail if the generated files are stale relative to shared/config.pkl
	@git diff --exit-code -- server/config/shared.php mobile/assets/config/shared.yaml >/dev/null || \
		(echo "Generated config is out of date: run 'make config' and commit." && exit 1)

test: ## Run server and mobile test suites
	cd server && php artisan test
	cd mobile && flutter test
