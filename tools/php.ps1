# Active PostgreSQL uniquement pour cette invocation, sans modifier PHP globalement.
& php -d extension=pdo_pgsql @args
exit $LASTEXITCODE
