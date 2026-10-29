FROM habadvbel/hab_php8.2_apache_oci8_sqlsrv

COPY . /var/www/html/

# 1. Crear la carpeta segura y mover el wallet fuera del directorio web
RUN mkdir -p /opt/oracle/wallet && \
    for d in /var/www/html/Wallet /var/www/html/Pagina_Base_de_Datos/Wallet; do \
        if [ -d "$d" ]; then cp -a "$d"/. /opt/oracle/wallet/ && rm -rf "$d"; fi; \
    done && \
    if [ -f /opt/oracle/wallet/sqlnet.ora ]; then \
        sed -i 's#DIRECTORY *= *"[^"]*"#DIRECTORY="/opt/oracle/wallet"#' /opt/oracle/wallet/sqlnet.ora; \
    fi

# 2. Bloquear archivos sensibles desde Apache
RUN printf '%s\n' \
    '<FilesMatch "\.(sso|p12|pem|jks|ora|sql|env)$">' \
    '    Require all denied' \
    '</FilesMatch>' \
    '<DirectoryMatch "/\.git">' \
    '    Require all denied' \
    '</DirectoryMatch>' \
    > /etc/apache2/conf-available/proteger.conf && a2enconf proteger

ENV TNS_ADMIN=/opt/oracle/wallet

# 3. CORRECCIÓN CLAVE DE PERMISOS PARA ORACLE:
# La carpeta debe ser 755 (o 750) y los archivos internos 644 para que www-data pueda leerlos
RUN chown -R www-data:www-data /var/www/html /opt/oracle/wallet && \
    find /var/www/html -type d -exec chmod 755 {} + && \
    find /var/www/html -type f -exec chmod 644 {} + && \
    chmod -R 755 /opt/oracle/wallet && \
    chmod 644 /opt/oracle/wallet/* || true

EXPOSE 80
