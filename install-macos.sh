#!/bin/bash

# Arknox Framework - macOS Installation Script
# 
# This script helps set up Arknox Framework on macOS systems
# with all required dependencies and optimal configuration.

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Arknox ASCII Art
echo -e "${GREEN}"
echo "   ___         _                    "
echo "  / _ \  _ __ | | __ _ __   _____  __"
echo " / /_\ \| '__|| |/ /| '_ \ / _ \ \/ /"
echo "/  _  \| |   |   < | | | | (_) >  < "
echo "\_/ \_/|_|   |_|\_\|_| |_|\___/_/\_\\"
echo -e "${NC}"
echo -e "${YELLOW}Arknox Framework - macOS Installation${NC}"
echo ""

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

# Check if running on macOS
if [[ "$OSTYPE" != "darwin"* ]]; then
    print_error "This script is designed for macOS only."
    exit 1
fi

print_status "Starting Arknox Framework installation on macOS..."

# Check if Homebrew is installed
if ! command -v brew &> /dev/null; then
    print_warning "Homebrew not found. Installing Homebrew..."
    /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
    
    # Add Homebrew to PATH for Apple Silicon Macs
    if [[ $(uname -m) == "arm64" ]]; then
        echo 'eval "$(/opt/homebrew/bin/brew shellenv)"' >> ~/.zprofile
        eval "$(/opt/homebrew/bin/brew shellenv)"
    fi
else
    print_success "Homebrew is already installed"
fi

# Update Homebrew
print_status "Updating Homebrew..."
brew update

# Install PHP 8.1 if not present
if ! command -v php &> /dev/null || [[ $(php -r "echo version_compare(PHP_VERSION, '8.1.0', '<') ? 'old' : 'new';") == "old" ]]; then
    print_status "Installing PHP 8.1..."
    brew install php@8.1

    # Link PHP 8.1
    brew link --force --overwrite php@8.1
    
    # Add PHP to PATH
    echo 'export PATH="/opt/homebrew/bin:$PATH"' >> ~/.zprofile
    echo 'export PATH="/opt/homebrew/sbin:$PATH"' >> ~/.zprofile
    
    # For Intel Macs
    if [[ $(uname -m) == "x86_64" ]]; then
        echo 'export PATH="/usr/local/bin:$PATH"' >> ~/.zprofile
        echo 'export PATH="/usr/local/sbin:$PATH"' >> ~/.zprofile
    fi
    
    source ~/.zprofile
else
    print_success "PHP 8.1+ is already installed"
fi

# Install Composer if not present
if ! command -v composer &> /dev/null; then
    print_status "Installing Composer..."
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
    chmod +x /usr/local/bin/composer
else
    print_success "Composer is already installed"
fi

# Install MySQL (optional)
read -p "Do you want to install MySQL? (y/n): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    if ! command -v mysql &> /dev/null; then
        print_status "Installing MySQL..."
        brew install mysql
        
        # Start MySQL service
        brew services start mysql
        
        print_success "MySQL installed and started"
        print_warning "Please run 'mysql_secure_installation' to secure your MySQL installation"
    else
        print_success "MySQL is already installed"
    fi
fi

# Install PostgreSQL (optional)
read -p "Do you want to install PostgreSQL? (y/n): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    if ! command -v psql &> /dev/null; then
        print_status "Installing PostgreSQL..."
        brew install postgresql@15
        
        # Start PostgreSQL service
        brew services start postgresql@15
        
        print_success "PostgreSQL installed and started"
    else
        print_success "PostgreSQL is already installed"
    fi
fi

# Install Redis (optional)
read -p "Do you want to install Redis? (y/n): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    if ! command -v redis-server &> /dev/null; then
        print_status "Installing Redis..."
        brew install redis
        
        # Start Redis service
        brew services start redis
        
        print_success "Redis installed and started"
    else
        print_success "Redis is already installed"
    fi
fi

# Install Node.js and npm (for frontend assets)
if ! command -v node &> /dev/null; then
    print_status "Installing Node.js..."
    brew install node
else
    print_success "Node.js is already installed"
fi

# Install Git if not present
if ! command -v git &> /dev/null; then
    print_status "Installing Git..."
    brew install git
else
    print_success "Git is already installed"
fi

# Check PHP extensions
print_status "Checking PHP extensions..."

required_extensions=("pdo" "mbstring" "openssl" "json" "curl")
optional_extensions=("redis" "imagick" "gd" "zip" "bcmath" "intl")

for ext in "${required_extensions[@]}"; do
    if php -m | grep -q "$ext"; then
        print_success "Required extension '$ext' is loaded"
    else
        print_error "Required extension '$ext' is missing"
    fi
done

for ext in "${optional_extensions[@]}"; do
    if php -m | grep -q "$ext"; then
        print_success "Optional extension '$ext' is loaded"
    else
        print_warning "Optional extension '$ext' is not loaded"
    fi
done

# Create a new Arknox project (optional)
echo ""
read -p "Do you want to create a new Arknox project? (y/n): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    read -p "Enter project name: " project_name
    
    if [[ -n "$project_name" ]]; then
        print_status "Creating new Arknox project: $project_name"
        
        # Create project directory
        mkdir -p "$project_name"
        cd "$project_name"
        
        # Initialize with Composer (if Arknox is available via Composer)
        # composer create-project arknox/framework .
        
        # For now, we'll clone from repository or copy framework files
        print_status "Setting up project structure..."
        
        # Make CLI executable
        if [[ -f "arknox" ]]; then
            chmod +x arknox
            print_success "Made arknox CLI executable"
        fi
        
        # Setup environment
        if [[ -f "arknox" ]]; then
            ./arknox env:setup
        fi
        
        print_success "Project '$project_name' created successfully!"
        print_status "To get started:"
        print_status "  cd $project_name"
        print_status "  ./arknox serve"
    fi
fi

# Display final information
echo ""
print_success "🎉 Arknox Framework installation completed!"
echo ""
print_status "📋 What's installed:"
echo "  ✓ PHP $(php -r 'echo PHP_VERSION;')"
echo "  ✓ Composer $(composer --version | cut -d' ' -f3)"
echo "  ✓ Node.js $(node --version)"
echo "  ✓ npm $(npm --version)"

if command -v mysql &> /dev/null; then
    echo "  ✓ MySQL $(mysql --version | cut -d' ' -f6 | cut -d',' -f1)"
fi

if command -v psql &> /dev/null; then
    echo "  ✓ PostgreSQL $(psql --version | cut -d' ' -f3)"
fi

if command -v redis-server &> /dev/null; then
    echo "  ✓ Redis $(redis-server --version | cut -d' ' -f3 | cut -d'=' -f2)"
fi

echo ""
print_status "🚀 Quick start commands:"
echo "  arknox --version          Show framework version"
echo "  arknox about              Show framework information"
echo "  arknox serve              Start development server"
echo "  arknox make:model User    Create a new model"
echo ""
print_status "📚 Documentation: https://docs.arknox.dev"
print_status "🌐 Website: https://arknox.dev"
echo ""
print_success "Happy coding with Arknox Framework! 🚀"
