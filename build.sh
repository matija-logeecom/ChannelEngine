#!/bin/bash

# ChannelEngine PrestaShop Plugin Build Script
# This script creates a zip file ready for PrestaShop module upload

set -e  # Exit on any error

# Configuration
MODULE_NAME="channelengine"
VERSION="1.0.0"
BUILD_DIR="build"
DIST_DIR="dist"
ZIP_NAME="${MODULE_NAME}-${VERSION}.zip"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Check required commands
check_dependencies() {
    print_status "Checking dependencies..."
    
    if ! command_exists zip; then
        print_error "zip command not found. Please install zip utility."
        exit 1
    fi
    
    if ! command_exists php; then
        print_warning "PHP not found. Syntax checking will be skipped."
    fi
    
    print_success "Dependencies OK"
}

# Clean previous builds
clean_build() {
    print_status "Cleaning previous builds..."
    rm -rf "$BUILD_DIR"
    rm -rf "$DIST_DIR"
    mkdir -p "$BUILD_DIR"
    mkdir -p "$DIST_DIR"
    print_success "Build directories cleaned"
}

# Copy source files
copy_source_files() {
    print_status "Copying source files..."
    
    # Create module directory in build
    mkdir -p "$BUILD_DIR/$MODULE_NAME"
    
    # Copy main module file
    if [ -f "channelengine.php" ]; then
        cp channelengine.php "$BUILD_DIR/$MODULE_NAME/"
    else
        print_error "Main module file channelengine.php not found!"
        exit 1
    fi
    
    # Copy source code directories
    if [ -d "classes" ]; then
        print_status "Copying classes directory..."
        cp -r classes/ "$BUILD_DIR/$MODULE_NAME/"
    fi
    
    if [ -d "controllers" ]; then
        print_status "Copying controllers directory..."
        cp -r controllers/ "$BUILD_DIR/$MODULE_NAME/"
    fi
    
    if [ -d "views" ]; then
        print_status "Copying views directory..."
        cp -r views/ "$BUILD_DIR/$MODULE_NAME/"
    fi
    
    # Copy composer files if they exist
    if [ -f "composer.json" ]; then
        cp composer.json "$BUILD_DIR/$MODULE_NAME/"
        print_status "Copied composer.json"
    fi
    
    if [ -f "composer.lock" ]; then
        cp composer.lock "$BUILD_DIR/$MODULE_NAME/"
        print_status "Copied composer.lock"
    fi
    
    # Copy vendor directory if it exists (for dependencies)
    if [ -d "vendor" ]; then
        print_status "Copying vendor dependencies..."
        cp -r vendor/ "$BUILD_DIR/$MODULE_NAME/"
    fi
    
    # Copy configuration files
    if [ -f "config.xml" ]; then
        cp config.xml "$BUILD_DIR/$MODULE_NAME/"
        print_status "Copied config.xml"
    fi
    
    # Copy logo if it exists
    if [ -f "logo.png" ]; then
        cp logo.png "$BUILD_DIR/$MODULE_NAME/"
        print_status "Copied logo.png"
    fi
    
    # Copy documentation
    if [ -f "README.md" ]; then
        cp README.md "$BUILD_DIR/$MODULE_NAME/"
        print_status "Copied README.md"
    fi
    
    if [ -f "LICENSE" ]; then
        cp LICENSE "$BUILD_DIR/$MODULE_NAME/"
        print_status "Copied LICENSE"
    fi
    
    print_success "Source files copied"
}

# Remove development files
remove_dev_files() {
    print_status "Removing development files..."
    
    # Remove development and build files
    find "$BUILD_DIR/$MODULE_NAME" -name ".git*" -type f -delete 2>/dev/null || true
    find "$BUILD_DIR/$MODULE_NAME" -name ".DS_Store" -type f -delete 2>/dev/null || true
    find "$BUILD_DIR/$MODULE_NAME" -name "Thumbs.db" -type f -delete 2>/dev/null || true
    find "$BUILD_DIR/$MODULE_NAME" -name "*.tmp" -type f -delete 2>/dev/null || true
    find "$BUILD_DIR/$MODULE_NAME" -name "*.bak" -type f -delete 2>/dev/null || true
    
    # Remove development directories
    rm -rf "$BUILD_DIR/$MODULE_NAME/.git" 2>/dev/null || true
    rm -rf "$BUILD_DIR/$MODULE_NAME/node_modules" 2>/dev/null || true
    rm -rf "$BUILD_DIR/$MODULE_NAME/.idea" 2>/dev/null || true
    rm -rf "$BUILD_DIR/$MODULE_NAME/.vscode" 2>/dev/null || true
    
    # Remove this build script from the package
    rm -f "$BUILD_DIR/$MODULE_NAME/build.sh" 2>/dev/null || true
    rm -rf "$BUILD_DIR/$MODULE_NAME/build" 2>/dev/null || true
    rm -rf "$BUILD_DIR/$MODULE_NAME/dist" 2>/dev/null || true
    
    print_success "Development files removed"
}

# Validate PHP syntax
validate_php_syntax() {
    if command_exists php; then
        print_status "Validating PHP syntax..."
        
        php_files_with_errors=0
        
        while IFS= read -r -d '' file; do
            if ! php -l "$file" >/dev/null 2>&1; then
                print_error "Syntax error in: $file"
                php_files_with_errors=$((php_files_with_errors + 1))
            fi
        done < <(find "$BUILD_DIR/$MODULE_NAME" -name "*.php" -print0)
        
        if [ $php_files_with_errors -gt 0 ]; then
            print_error "Found $php_files_with_errors PHP files with syntax errors"
            exit 1
        fi
        
        print_success "PHP syntax validation passed"
    else
        print_warning "Skipping PHP syntax validation (PHP not available)"
    fi
}

# Create zip file
create_zip() {
    print_status "Creating zip file..."
    
    cd "$BUILD_DIR"
    zip -r "../$DIST_DIR/$ZIP_NAME" "$MODULE_NAME" -q
    cd ..
    
    # Get file size
    if command_exists du; then
        size=$(du -h "$DIST_DIR/$ZIP_NAME" | cut -f1)
        print_success "Zip file created: $DIST_DIR/$ZIP_NAME ($size)"
    else
        print_success "Zip file created: $DIST_DIR/$ZIP_NAME"
    fi
}

# Display file structure
show_structure() {
    print_status "Package structure:"
    
    if command_exists tree; then
        tree "$BUILD_DIR/$MODULE_NAME" -I 'vendor'
    else
        find "$BUILD_DIR/$MODULE_NAME" -type f | sed 's/^/  /' | head -20
        file_count=$(find "$BUILD_DIR/$MODULE_NAME" -type f | wc -l)
        if [ "$file_count" -gt 20 ]; then
            echo "  ... and $((file_count - 20)) more files"
        fi
    fi
}

# Generate checksums
generate_checksums() {
    print_status "Generating checksums..."
    
    cd "$DIST_DIR"
    
    if command_exists sha256sum; then
        sha256sum "$ZIP_NAME" > "${ZIP_NAME}.sha256"
        print_success "SHA256 checksum saved to ${ZIP_NAME}.sha256"
    elif command_exists shasum; then
        shasum -a 256 "$ZIP_NAME" > "${ZIP_NAME}.sha256"
        print_success "SHA256 checksum saved to ${ZIP_NAME}.sha256"
    else
        print_warning "No SHA256 utility found, skipping checksum generation"
    fi
    
    cd ..
}

# Main build process
main() {
    print_status "Starting ChannelEngine PrestaShop Plugin build process..."
    print_status "Module: $MODULE_NAME v$VERSION"
    echo
    
    check_dependencies
    clean_build
    copy_source_files
    remove_dev_files
    validate_php_syntax
    show_structure
    create_zip
    generate_checksums
    
    echo
    print_success "Build completed successfully!"
    print_success "Package ready: $DIST_DIR/$ZIP_NAME"
    echo
    print_status "Installation instructions:"
    echo "  1. Go to your PrestaShop admin panel"
    echo "  2. Navigate to Modules → Module Manager"
    echo "  3. Click 'Upload a module'"
    echo "  4. Select the file: $DIST_DIR/$ZIP_NAME"
    echo "  5. Click 'Upload this module'"
}

# Run main function
main "$@"
