#!/bin/bash
set -e

echo "=== php-facturae migration ==="

# 1. Flatten src/Facturae/ → src/
if [ -d "src/Facturae" ]; then
    echo "Moving src/Facturae/ contents to src/..."
    cp -r src/Facturae/. src/
    rm -rf src/Facturae
else
    echo "src/Facturae not found — skipping"
fi

# 2. Flatten tests/Facturae/ → tests/
if [ -d "tests/Facturae" ]; then
    echo "Moving tests/Facturae/ contents to tests/..."
    cp -r tests/Facturae/. tests/
    rm -rf tests/Facturae
else
    echo "tests/Facturae not found — skipping"
fi

# 3. Replace namespaces in all PHP files
echo "Updating namespaces..."

find src/ -name "*.php" -exec sed -i \
    -e 's/MarioDevv\\Rex\\Facturae\\/PhpFacturae\\/g' \
    -e 's/MarioDevv\\\\Rex\\\\Facturae\\\\/PhpFacturae\\\\/g' \
    {} +

find tests/ -name "*.php" -exec sed -i \
    -e 's/MarioDevv\\Rex\\Tests\\Facturae\\/PhpFacturae\\Tests\\/g' \
    -e 's/MarioDevv\\\\Rex\\\\Tests\\\\Facturae\\\\/PhpFacturae\\\\Tests\\\\/g' \
    -e 's/MarioDevv\\Rex\\Facturae\\/PhpFacturae\\/g' \
    -e 's/MarioDevv\\\\Rex\\\\Facturae\\\\/PhpFacturae\\\\/g' \
    {} +

# 4. Update phpunit configs
for f in phpunit.xml phpunit.local.xml; do
    if [ -f "$f" ]; then
        echo "Updating $f..."
        sed -i \
            -e 's|tests/Facturae|tests|g' \
            -e 's|src/Facturae|src|g' \
            "$f"
    fi
done

echo ""
echo "Done! New structure:"
tree -I 'vendor|dist' src/ tests/
echo ""
echo "Remember to:"
echo "  1. Replace composer.json with the new one"
echo "  2. Run: composer dump-autoload"
echo "  3. Run: composer test"
