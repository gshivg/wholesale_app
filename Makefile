PKL ?= pkl

.PHONY: config
config: ## Render shared/config.pkl into Laravel (PHP) and Flutter (YAML) configs
	$(PKL) eval shared/laravel.pkl -o server/config/shared.php
	$(PKL) eval shared/flutter.pkl -o mobile/assets/config/shared.yaml
