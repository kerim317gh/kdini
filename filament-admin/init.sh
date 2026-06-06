#!/bin/bash

echo "Starting project initialization for MacOS..."

# Check for Homebrew
if ! command -v brew &> /dev/null
then
    echo "Homebrew not found. Please install it first from https://brew.sh/"
    exit 1
fi

# Check for PHP
if ! command -v php &> /dev/null
then
    echo "PHP not found. Installing PHP via Homebrew..."
    brew install php
fi

# Check for Composer
if ! command -v composer &> /dev/null
then
    echo "Composer not found. Installing Composer..."
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer
    php -r "unlink('composer-setup.php');"
fi

echo "Installing PHP dependencies..."
composer install --ignore-platform-reqs

if [ ! -f ".env" ]; then
    echo "Creating .env file from .env.example..."
    cp .env.example .env
fi

echo "Generating application key..."
php artisan key:generate

echo "Linking storage directory..."
php artisan storage:link

echo "Initialization complete!"
echo "Next steps:"
echo "1. Configure your database connection in the .env file."
echo "2. Run 'php artisan migrate' to create the database tables."
echo "3. Run 'php artisan serve' to start the development server."
echo "4. Access the admin panel at http://127.0.0.1:8000/admin"
