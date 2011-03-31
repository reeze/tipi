#!/usr/bin/env sh

PDF_MAKER=wkhtmltopdf
RELEASE_FILE_PATH=web/releases/`cat VERSION`.pdf
OPTIONS="-L 0 -R 0"

$PDF_MAKER $OPTIONS --header-html http://localhost/tipi/portable/header.php http://localhost/tipi/portable/print.php $RELEASE_FILE_PATH

echo "Pdf file saved to:" $RELEASE_FILE_PATH
