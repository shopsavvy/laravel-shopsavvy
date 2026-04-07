#!/bin/bash
set -e

INTEGRATION=false
for arg in "$@"; do
    if [ "$arg" = "--integration" ]; then
        INTEGRATION=true
    fi
done

echo ""
echo "ShopSavvy Laravel Package Tests"
echo "================================"
echo ""

# ---- Structural checks (always run) ----

echo "Checking required files..."
REQUIRED=(
    "src/ShopSavvyServiceProvider.php"
    "src/ShopSavvyFacade.php"
    "src/ShopSavvyClient.php"
    "src/ShopSavvyManager.php"
    "src/Commands/SearchCommand.php"
    "src/Commands/PriceCommand.php"
    "src/Http/Controllers/ShopSavvyController.php"
    "src/Views/Components/Price.php"
    "src/Views/Components/Search.php"
    "src/routes.php"
    "config/shopsavvy.php"
    "resources/views/components/price.blade.php"
    "resources/views/components/search.blade.php"
    "tests/ShopSavvyClientTest.php"
    "composer.json"
    "README.md"
    "LICENSE"
    ".gitignore"
)

MISSING=0
for f in "${REQUIRED[@]}"; do
    if [ ! -f "$f" ]; then
        echo "  MISSING: $f"
        MISSING=$((MISSING + 1))
    fi
done

if [ $MISSING -eq 0 ]; then
    echo "  All required files present"
else
    echo "  $MISSING required files missing"
    exit 1
fi

echo ""
echo "Checking composer.json structure..."

if command -v php &>/dev/null; then
    php -r "
        \$c = json_decode(file_get_contents('composer.json'), true);
        if (!isset(\$c['extra']['laravel']['providers'])) { echo '  MISSING: extra.laravel.providers\n'; exit(1); }
        if (!isset(\$c['extra']['laravel']['aliases'])) { echo '  MISSING: extra.laravel.aliases\n'; exit(1); }
        if (!isset(\$c['autoload']['psr-4'])) { echo '  MISSING: autoload.psr-4\n'; exit(1); }
        echo '  composer.json structure OK\n';
    "
else
    echo "  PHP not found, skipping composer.json check"
fi

echo ""
echo "Checking PHP syntax..."

if command -v php &>/dev/null; then
    SYNTAX_ERRORS=0
    for phpfile in src/**/*.php src/*.php config/*.php tests/*.php; do
        if [ -f "$phpfile" ]; then
            if ! php -l "$phpfile" > /dev/null 2>&1; then
                echo "  SYNTAX ERROR: $phpfile"
                php -l "$phpfile"
                SYNTAX_ERRORS=$((SYNTAX_ERRORS + 1))
            fi
        fi
    done
    if [ $SYNTAX_ERRORS -eq 0 ]; then
        echo "  All PHP files pass syntax check"
    else
        echo "  $SYNTAX_ERRORS file(s) have syntax errors"
        exit 1
    fi
else
    echo "  PHP not found, skipping syntax check"
fi

# ---- Unit tests (always run if composer deps installed) ----

echo ""
echo "Running unit tests..."

if [ -d "vendor" ]; then
    if command -v php &>/dev/null; then
        php vendor/bin/phpunit --colors=always
    else
        echo "  PHP not found, skipping PHPUnit"
    fi
else
    echo "  vendor/ not found — run 'composer install' to enable unit tests"
fi

# ---- Integration tests (only with --integration flag) ----

if [ "$INTEGRATION" = true ]; then
    echo ""
    echo "Running integration tests..."

    if [ -z "$SHOPSAVVY_API_KEY" ]; then
        echo "  ERROR: SHOPSAVVY_API_KEY environment variable is required for integration tests"
        echo "  Set it with: export SHOPSAVVY_API_KEY=ss_live_your_key_here"
        exit 1
    fi

    if command -v php &>/dev/null; then
        php -r "
            // Quick integration smoke test using curl (no composer deps needed)
            \$apiKey = getenv('SHOPSAVVY_API_KEY');
            \$url    = 'https://api.shopsavvy.com/v1/products/search?q=AirPods+Pro&limit=2';

            \$ch = curl_init(\$url);
            curl_setopt(\$ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt(\$ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . \$apiKey,
                'Accept: application/json',
            ]);
            curl_setopt(\$ch, CURLOPT_TIMEOUT, 15);

            \$body   = curl_exec(\$ch);
            \$status = curl_getinfo(\$ch, CURLINFO_HTTP_CODE);
            curl_close(\$ch);

            if (\$status !== 200) {
                echo '  FAILED: Search returned HTTP ' . \$status . '\n';
                echo '  Response: ' . \$body . '\n';
                exit(1);
            }

            \$data = json_decode(\$body, true);
            if (!is_array(\$data)) {
                echo '  FAILED: Could not parse JSON response\n';
                exit(1);
            }

            echo '  Search integration test passed (HTTP 200)\n';
            echo '  Response keys: ' . implode(', ', array_keys(\$data)) . '\n';
        "
    else
        echo "  PHP not found, skipping integration tests"
    fi
fi

echo ""
echo "All checks passed."
echo ""
