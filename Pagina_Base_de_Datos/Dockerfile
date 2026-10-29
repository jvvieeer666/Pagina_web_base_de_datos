FROM habadvbel/hab_php8.2_apache_oci8_sqlsrv

COPY . /var/www/html/

# El wallet se mueve FUERA de la carpeta web (así Apache no puede servirlo)
# y se corrige la ruta dentro de sqlnet.ora.
RUN mkdir -p /opt/oracle/wallet && \
    for d in /var/www/html/Wallet /var/www/html/Pagina_Base_de_Datos/Wallet; do \
        if [ -d "$d" ]; then cp -a "$d"/. /opt/oracle/wallet/ && rm -rf "$d"; fi; \
    done && \
    if [ -f /opt/oracle/wallet/sqlnet.ora ]; then \
        sed -i 's#DIRECTORY *= *"[^"]*"#DIRECTORY="/opt/oracle/wallet"#' /opt/oracle/wallet/sqlnet.ora; \
    fi

# Bloquea por Apache cualquier archivo sensible que llegara a quedar en la carpeta web
RUN printf '%s\n' \
    '<FilesMatch "\.(sso|p12|pem|jks|ora|sql|env)$">' \
    '    Require all denied' \
    '</FilesMatch>' \
    '<DirectoryMatch "/\.git">' \
    '    Require all denied' \
    '</DirectoryMatch>' \
    > /etc/apache2/conf-available/proteger.conf && a2enconf proteger

ENV TNS_ADMIN=/opt/oracle/wallet

# Código legible por Apache; wallet solo para www-data
RUN chown -R www-data:www-data /var/www/html /opt/oracle/wallet && \
    chmod -R 755 /var/www/html && \
    chmod 750 /opt/oracle/wallet && \
    chmod 640 /opt/oracle/wallet/* || true

EXPOSE 80
