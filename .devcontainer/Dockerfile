# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    vim \
    sudo \
    mariadb-client \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Configure and Install PHP extensions
# Example: mysqli, pdo_mysql, gd for image processing
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql mysqli opcache

# Install Composer (PHP package manager)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory for the application
# The /workspace will be where your repository is mounted by Codespaces
WORKDIR /workspace

# Add a non-root user 'vscode' if it doesn't exist and give sudo permissions
# The default Codespaces user is often 'vscode' or 'codespace'
# This Dockerfile assumes a base image that might not have 'vscode' user set up like this.
# However, Codespaces often handles user creation. We can simplify this if using a standard Codespaces base image.
# For now, we'll rely on the `remoteUser: vscode` in devcontainer.json.

# The default Apache document root is /var/www/html
# If we use PHP's built-in server, we'll specify the document root at runtime.
# We are mounting our repo to /workspace.

# Expose port 8000 for PHP's built-in server (Apache uses 80 by default)
EXPOSE 8000

# The CMD or ENTRYPOINT can be overridden by Codespaces or docker-compose.
# For PHP's built-in server, you'd typically run something like:
# CMD ["php", "-S", "0.0.0.0:8000", "-t", "backend/public"]
# But we will run this manually or via a script in the Codespace terminal for now.
```