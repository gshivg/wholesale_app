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

.PHONY: demo demo-apk demo-web
demo: ## Run the Flutter app in demo mode (built-in data, no server)
	cd mobile && flutter run --dart-define=DEMO=true

demo-apk: ## Build an Android APK in demo mode
	cd mobile && flutter build apk --dart-define=DEMO=true

demo-web: ## Build a web bundle in demo mode (mobile/build/web)
	cd mobile && flutter build web --dart-define=DEMO=true
