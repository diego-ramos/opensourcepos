#!/bin/bash

# Stop script if any command fails
set -e

echo "Running composer install..."
composer install --no-dev --optimize-autoloader

echo "Setting permissions..."
chmod 750 writable/logs
chmod 755 public/uploads
chmod 750 public/uploads/item_pics
chmod 640 writable/uploads/importCustomers.csv

echo "Done"