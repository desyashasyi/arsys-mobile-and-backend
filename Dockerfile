# 1. Gunakan PHP 8.4 FPM sesuai kebutuhan terbaru Anda
FROM php:8.4-fpm

# 2. Definisikan Argument User (Gunakan default 1000 sesuai .env)
ARG WWWUSER=1000
ARG WWWGROUP=1000

# 3. Install Dependensi Sistem
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libicu-dev \
    zip \
    unzip \
    git \
    curl \
    gnupg \
    ca-certificates \
    sudo \
    chromium \
    fonts-liberation \
    libasound2 \
    libatk-bridge2.0-0 \
    libatk1.0-0 \
    libcups2 \
    libdrm2 \
    libgbm1 \
    libgtk-3-0 \
    libnspr4 \
    libnss3 \
    libxcomposite1 \
    libxdamage1 \
    libxfixes3 \
    libxkbcommon0 \
    libxrandr2 \
    xdg-utils \
    && rm -rf /var/lib/apt/lists/*

# 4. Install PHP Extensions menggunakan script helper (Lebih stabil & cepat) [cite: 3, 5]
# Menambahkan 'intl' untuk memperbaiki RuntimeException sebelumnya
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions gd pdo pdo_mysql mbstring xml zip intl bcmath redis pcntl sockets

# 5. Install Node.js (Versi 22 sesuai file Dockerfile asli Anda) [cite: 2, 3]
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - && \
    apt-get install -y nodejs && \
    npm install -g npm

# 6. Install Composer [cite: 4]
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 7. Set Working Directory
WORKDIR /var/www

# 8. Setup Symlink & Startup Script
# Memindahkan symlink ke entrypoint agar dijalankan setiap container start
RUN echo "#!/bin/sh\n\
mkdir -p /var/www/watcher \n\
ln -sf /mnt/fet-results /var/www/watcher/fet-results \n\
# Pastikan folder storage ada sebelum start\n\
mkdir -p /var/www/storage/framework/sessions /var/www/storage/framework/views /var/www/storage/framework/cache \n\
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \n\
exec php-fpm" > /usr/local/bin/startup && \
chmod +x /usr/local/bin/startup

# 9. Sync User ID dengan Host (Penting untuk izin file di Linux) [cite: 2, 4]
RUN groupmod -g ${WWWGROUP} www-data && \
    usermod -u ${WWWUSER} -g ${WWWGROUP} www-data

# 10. Copy Source Code & Set Permissions [cite: 6]
COPY . .
RUN chown -R www-data:www-data /var/www

# 11. Port & Entrypoint
EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/startup"]
