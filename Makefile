# Makefile for mod_seledascorm Moodle plugin

# Define SED_INPLACE based on the operating system
ifeq ($(shell uname), Darwin)
  SED_INPLACE = sed -i ''
else
  SED_INPLACE = sed -i
endif

# Detect the operating system and shell environment
ifeq ($(OS),Windows_NT)
    # Initially assume Windows shell
    SHELLTYPE := windows
    # Check if we are in Cygwin or MSYS (e.g., Git Bash)
    ifdef MSYSTEM
        SHELLTYPE := unix
    else ifdef CYGWIN
        SHELLTYPE := unix
    endif
else
    SHELLTYPE := unix
endif

# Check if Docker is running
# This target verifies if Docker is installed and running on the system.
check-docker:
ifeq ($(SHELLTYPE),windows)
	@echo "Detected system: Windows (cmd, powershell)"
	@docker version > NUL 2>&1 || (echo. & echo Error: Docker is not running. Please make sure Docker is installed and running. & echo. & exit 1)
else
	@echo "Detected system: Unix (Linux/macOS/Cygwin/MinGW)"
	@docker version > /dev/null 2>&1 || (echo "" && echo "Error: Docker is not running. Please make sure Docker is installed and running." && echo "" && exit 1)
endif

# Check if the .env file exists, if not, copy from .env.dist
# This target ensures that the .env file is present by copying it from .env.dist if it doesn't exist.
check-env:
ifeq ($(SHELLTYPE),windows)
	@if not exist .env ( \
		echo The .env file does not exist. Copying from .env.dist... && \
		copy .env.dist .env \
	) 2>nul
else
	@if [ ! -f .env ]; then \
		echo "The .env file does not exist. Copying from .env.dist..."; \
		cp .env.dist .env; \
	fi
endif

# Start Docker containers in interactive mode
# This target builds and starts the Docker containers, allowing interaction with the terminal.
up: check-docker check-env
	docker compose up

# Start Docker containers in background mode (daemon)
# This target builds and starts the Docker containers in the background.
upd: check-docker check-env
	docker compose up -d

# Stop and remove Docker containers
# This target stops and removes all running Docker containers.
down: check-docker check-env
	docker compose down

# Pull the latest images from the registry
# This target pulls the latest Docker images from the registry.
pull: check-docker check-env
	docker compose -f docker-compose.yml pull

# Build or rebuild Docker containers
# This target builds or rebuilds the Docker containers.
build: check-docker check-env
	@if [ -z "$$(grep ^EXELEARNING_WEB_SOURCECODE_PATH .env | cut -d '=' -f2)" ]; then \
		echo "Error: EXELEARNING_WEB_SOURCECODE_PATH is not defined or empty in the .env file"; \
		exit 1; \
	fi
	docker compose build

# Open a shell inside the moodle container
# This target opens an interactive shell session inside the running Moodle container.
shell: check-docker check-env
	docker compose exec moodle sh

# Clean up and stop Docker containers, removing volumes and orphan containers
# This target stops all containers and removes them along with their volumes and any orphan containers.
clean: check-docker
	docker compose down -v --remove-orphans

# Install PHP dependencies using Composer
install-deps:
	COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --prefer-dist --optimize-autoloader --no-progress

# Run code linting using Composer
lint:
	composer lint

# Automatically fix code style issues using Composer
fix:
	composer fix

# Run tests using Composer
test:
	composer test

# Run PHP Mess Detector using Composer
phpmd:
	composer phpmd

# Run Behat tests using Composer
behat:
	composer behat
# -------------------------------------------------------
# Embedded Seleda editor build targets
# -------------------------------------------------------

EDITOR_DIST_PATH = dist/static

# Build the exact Seleda Studio embedded profile used by Moodle releases.
# This intentionally does NOT provide a generic upstream eXeLearning build
# path. The source/ref is resolved by scripts/build-seleda-editor.sh from
# SELEDA_STUDIO_REPO_URL / SELEDA_STUDIO_REF or .seleda-editor-ref.
build-editor: check-docker
	sh scripts/build-seleda-editor.sh

# Backward-compatible alias. It is deliberately routed through Seleda too.
build-editor-no-update: build-editor

# Legacy target retained as a safe alias for developer muscle memory.
# It must never fetch/build stock eXeLearning independently.
fetch-editor-source:
	@echo "Seleda V1 does not fetch a generic editor source."
	@echo "Use 'make build-editor'; source is controlled by .seleda-editor-ref."

# Remove build artifacts.
clean-editor:
	rm -rf $(EDITOR_DIST_PATH)

# -------------------------------------------------------
# Packaging
# -------------------------------------------------------

PLUGIN_NAME = mod_seledascorm
VERSION_SUFFIX ?= 02

# Create a distributable ZIP package
# Usage: make package RELEASE=0.0.2
# VERSION (YYYYMMDDXX) is auto-generated from current date
package: build-editor
	@if [ -z "$(RELEASE)" ]; then \
		echo "Error: RELEASE not specified. Use 'make package RELEASE=0.0.2'"; \
		exit 1; \
	fi
	$(eval DATE_VERSION := $(shell date +%Y%m%d)$(VERSION_SUFFIX))
	@test -f $(EDITOR_DIST_PATH)/index.html || (echo "Error: bundled Seleda editor is missing from $(EDITOR_DIST_PATH)." && exit 1)
	@test -d $(EDITOR_DIST_PATH)/app || (echo "Error: bundled Seleda editor app/ is missing." && exit 1)
	@test -d $(EDITOR_DIST_PATH)/files || (echo "Error: bundled Seleda editor files/ is missing." && exit 1)
	@test -d $(EDITOR_DIST_PATH)/libs || (echo "Error: bundled Seleda editor libs/ is missing." && exit 1)
	@echo "Packaging release $(RELEASE) (version $(DATE_VERSION))..."
	$(SED_INPLACE) "s/\(plugin->version[[:space:]]*=[[:space:]]*\)[0-9]*/\1$(DATE_VERSION)/" version.php
	$(SED_INPLACE) "s/\(plugin->release[[:space:]]*=[[:space:]]*'\)[^']*/\1$(RELEASE)/" version.php
	@echo "Creating ZIP archive: $(PLUGIN_NAME)-$(RELEASE).zip..."
	@if command -v rsync > /dev/null 2>&1 && command -v zip > /dev/null 2>&1; then \
		rm -rf /tmp/seledascorm-package; \
		mkdir -p /tmp/seledascorm-package/seledascorm; \
		rsync -av --exclude-from=.distignore ./ /tmp/seledascorm-package/seledascorm/; \
		cd /tmp/seledascorm-package && zip -qr "$(CURDIR)/$(PLUGIN_NAME)-$(RELEASE).zip" seledascorm; \
		rm -rf /tmp/seledascorm-package; \
	else \
		PYTHON=; \
		for cmd in python3 py python; do \
			if $$cmd --version > /dev/null 2>&1; then PYTHON=$$cmd; break; fi; \
		done; \
		if [ -z "$$PYTHON" ]; then \
			echo "Error: Python 3 not found. Please install Python 3."; \
			exit 1; \
		fi; \
		$$PYTHON scripts/package.py $(RELEASE) $(PLUGIN_NAME); \
	fi
	@echo "Restoring development values in version.php..."
	$(SED_INPLACE) "s/\(plugin->version[[:space:]]*=[[:space:]]*\)[0-9]*/\19999999999/" version.php
	$(SED_INPLACE) "s/\(plugin->release[[:space:]]*=[[:space:]]*'\)[^']*/\1dev/" version.php
	@# The built editor ships whole: fail if .distignore dropped any of its files
	@# (an unanchored "vendor/" once removed the SCORM 1.2 pipwerks wrapper).
	@if [ -d $(EDITOR_DIST_PATH) ] && command -v unzip > /dev/null 2>&1; then \
		CONTENTS=$$(mktemp); \
		unzip -Z1 "$(PLUGIN_NAME)-$(RELEASE).zip" | sed -n 's#^seledascorm/##p' > "$$CONTENTS"; \
		MISSING=$$(find $(EDITOR_DIST_PATH) -type f ! -name .DS_Store | grep -vxF -f "$$CONTENTS"); \
		rm -f "$$CONTENTS"; \
		if [ -n "$$MISSING" ]; then \
			echo "Error: built editor files missing from $(PLUGIN_NAME)-$(RELEASE).zip:"; \
			echo "$$MISSING"; \
			exit 1; \
		fi; \
	fi
	@echo "Package created: $(PLUGIN_NAME)-$(RELEASE).zip"
# -------------------------------------------------------

# Display help with available commands
# This target lists all available Makefile commands with a brief description.
help:
	@echo "Available commands:"
	@echo "  up                     - Start Docker containers in interactive mode"
	@echo "  upd                    - Start Docker containers in background mode (daemon)"
	@echo "  down                   - Stop and remove Docker containers"
	@echo "  build                  - Build or rebuild Docker containers"
	@echo "  pull                   - Pull the latest images from the registry"
	@echo "  clean                  - Clean up and stop Docker containers, removing volumes and orphan containers"
	@echo "  shell                  - Open a shell inside the exelearning-web container"
	@echo "  install-deps           - Install PHP dependencies using Composer"
	@echo "  lint                   - Run code linting using Composer"
	@echo "  fix                    - Automatically fix code style issues using Composer"
	@echo "  test                   - Run tests using Composer"
	@echo "  phpmd                  - Run PHP Mess Detector using Composer"
	@echo "  behat                  - Run Behat tests using Composer"
	@echo "  build-editor           - Build the embedded Seleda Studio editor into dist/static"
	@echo "  build-editor-no-update - Safe alias of build-editor (also Seleda)"
	@echo "  clean-editor           - Remove editor build artifacts"
	@echo "  fetch-editor-source    - Explains Seleda source control; does not fetch stock eXeLearning"
	@echo "  package                - Create distributable ZIP (RELEASE=X.Y.Z required)"
	@echo "  help                   - Display this help with available commands"


# Set help as the default goal if no target is specified
.DEFAULT_GOAL := help
