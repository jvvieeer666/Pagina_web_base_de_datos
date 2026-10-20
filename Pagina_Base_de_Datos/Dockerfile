# Usamos una imagen oficial de PHP con Apache
FROM php:8.2-apache

# Instalamos dependencias necesarias
RUN apt-get update && apt-get install -y \
    libaio1 \
    wget \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Instalamos el Oracle Instant Client (necesario para oci8)
RUN mkdir /opt/oracle && cd /opt/oracle && \
    wget https://download.oracle.com/otn_software/linux/instantclient/2119000/instantclient-basic-linux.x64-21.9.0.0.0dbru.zip && \
    wget https://download.oracle.com/otn_software/linux/instantclient/2119000/instantclient-sdk-linux.x64-21.9.0.0.0dbru.zip && \
    unzip instantclient-basic-linux.x64-21.9.0.0.0dbru.zip && \
    unzip instantclient-sdk-linux.x64-21.9.0.0.0dbru.zip && \
    rm *.zip && \
    mv instantclient_21_9 /opt/oracle/instantclient

# Configuramos variables de entorno para Oracle
ENV LD_LIBRARY_PATH /opt/oracle/instantclient
ENV ORACLE_HOME /opt/oracle/instantclient

# Instalamos la extensión oci8 de PHP
RUN docker-php-ext-configure oci8 --with-oci8=instantclient,/opt/oracle/instantclient && \
    docker-php-ext-install oci8

# Copiamos tu código al servidor web
COPY . /var/www/html/

# Exponemos el puerto 80 (Render lo detectará)
EXPOSE 80
