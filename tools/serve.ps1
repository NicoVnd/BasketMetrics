$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)
& php -d upload_max_filesize=20M -d post_max_size=24M -d memory_limit=256M -d max_execution_time=180 -S 127.0.0.1:8000 -t public public/router.php
exit $LASTEXITCODE
