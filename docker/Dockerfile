FROM php:7.4-apache

# update locale to en_US.UTF-8 - mandatory!
RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y locales \
    && sed -i -e 's/# en_US.UTF-8 UTF-8/en_US.UTF-8 UTF-8/' /etc/locale.gen \
    && dpkg-reconfigure --frontend=noninteractive locales \
    && update-locale LANG=en_US.UTF-8 \
    && apt-get install -y \
        apt-utils \
        zip \
        vim \
        git \
        curl \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev

ENV LANG en_US.UTF-8
ENV LC_ALL en_US.UTF-8

RUN echo 'max_execution_time=0' >> /usr/local/etc/php/php.ini \
    && echo 'memory_limit=-1' >> /usr/local/etc/php/conf.d/docker-memory-limit.ini

RUN docker-php-ext-configure gd --with-freetype=/usr/include/ --with-jpeg=/usr/include/ \
    && docker-php-ext-install -j$(nproc) gd

RUN curl --silent --output /tmp/composer.setup https://getcomposer.org/installer \
    && php /tmp/composer.setup --install-dir=/usr/local/bin --filename=composer
