FROM habadvbel/hab_php8.2_apache_oci8_sqlsrv

COPY . /var/www/html/

# 1. Crear la carpeta segura y mover el wallet fuera del directorio web
RUN mkdir -p /opt/oracle/wallet && \
    for d in /var/www/html/Wallet /var/www/html/Pagina_Base_de_Datos/Wallet; do \
        if [ -d "$d" ]; then cp -a "$d"/. /opt/oracle/wallet/ && rm -rf "$d"; fi; \
    done

# 2. FORZAR sqlnet.ora correcto SIEMPRE (sobrescribe el del wallet)
#    - WALLET_LOCATION con ruta absoluta real (no "?/network/admin")
#    - SSL_SERVER_DN_MATCH=yes para validar el certificado del servidor
#    - NAMES.DIRECTORY_PATH con TNSNAMES para que el alias tallerdb_low se resuelva
RUN printf '%s\n' \
    'WALLET_LOCATION = (SOURCE = (METHOD = file) (METHOD_DATA = (DIRECTORY="/opt/oracle/wallet")))' \
    'SSL_SERVER_DN_MATCH=yes' \
    'NAMES.DIRECTORY_PATH= (TNSNAMES, EZCONNECT)' \
    > /opt/oracle/wallet/sqlnet.ora

# 3. Bloquear archivos sensibles desde Apache
RUN printf '%s\n' \
    '<FilesMatch "\.(sso|p12|pem|jks|ora|sql|env)$">' \
    '    Require all denied' \
    '</FilesMatch>' \
    '<DirectoryMatch "/\.git">' \
    '    Require all denied' \
    '</DirectoryMatch>' \
    > /etc/apache2/conf-available/proteger.conf && a2enconf proteger

# 4. Exponer TNS_ADMIN a los procesos de Apache (no solo a la shell)
RUN echo 'SetEnv TNS_ADMIN /opt/oracle/wallet' > /etc/apache2/conf-available/tns.conf && a2enconf tns

ENV TNS_ADMIN=/opt/oracle/wallet

# 5. Permisos exactos para que www-data pueda leer el wallet sin error ORA-28759
RUN chown -R www-data:www-data /var/www/html /opt/oracle/wallet && \
    find /var/www/html -type d -exec chmod 755 {} + && \
    find /var/www/html -type f -exec chmod 644 {} + && \
    chmod 755 /opt/oracle/wallet && \
    chmod 644 /opt/oracle/wallet/* || true

EXPOSE 80
