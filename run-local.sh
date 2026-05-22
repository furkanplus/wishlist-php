#!/bin/bash

# Port number to run the server on
PORT=${1:-8000}

echo -e "\033[1;36m========================================================\033[0m"
echo -e "\033[1;36m           Wishlist Local PHP Development Server        \033[0m"
echo -e "\033[1;36m========================================================\033[0m"

# Check if MySQL is running via brew services
if brew services list | grep -q "mysql.*started"; then
    echo -e "\033[1;32m✔ Local MySQL service is running.\033[0m"
else
    echo -e "\033[1;33m⚠ MySQL service is not running. Starting it now...\033[0m"
    brew services start mysql
fi

echo -e "\033[1;34mℹ Starting PHP Built-in Server on http://localhost:$PORT\033[0m"
echo -e "\033[1;34mℹ Press Ctrl+C to stop the server.\033[0m"
echo -e "\033[1;36m--------------------------------------------------------\033[0m"

# Run PHP Built-in Web Server
php -S localhost:$PORT
