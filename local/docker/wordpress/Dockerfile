# Keep in sync with the default value of XDEBUG_VERSION below!
ARG PHP_VERSION=7.4

FROM wordpress:php${PHP_VERSION}-apache

# Default version which supports the default PHP 7.4.
ARG XDEBUG_VERSION=2.9.6

# Include our Composer vendor binary path into global path.
ENV PATH="/var/www/html/wp-content/plugins/stream-src/vendor/bin:${PATH}"

RUN apt-get update; \
	apt-get install -y --no-install-recommends \
	# WP-CLI dependencies.
	bash less default-mysql-client git \
	# MailHog dependencies.
	msmtp;

COPY php.ini /usr/local/etc/php/php.ini

# Setup xdebug. The latest version supported by PHP 5.6 is 2.5.5.
RUN	pecl install "xdebug-${XDEBUG_VERSION}"; \
	docker-php-ext-enable xdebug

COPY wait.sh /usr/local/bin/xwp_wait
