#!/bin/bash

# Setup script to install Git hooks for the project
# This ensures all team members have the same hooks

echo "🔧 Setting up Git hooks..."

# Create hooks directory if it doesn't exist
mkdir -p .git/hooks

# Copy pre-commit hook
if [ -f "scripts/git-hooks/pre-commit" ]; then
    cp scripts/git-hooks/pre-commit .git/hooks/pre-commit
    chmod +x .git/hooks/pre-commit
    echo "✅ Pre-commit hook installed"
else
    echo "❌ Pre-commit hook not found in scripts/git-hooks/"
    exit 1
fi

echo "🎉 Git hooks setup completed!"
echo "   - Pre-commit hook will format PHP files before commits"
echo "   - This prevents conflicts with Cursor AI during development"
