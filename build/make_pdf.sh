#!/usr/bin/env sh

PDF_MAKER=wkhtmltopdf
RELEASE_FILE_PATH=web/releases/`cat VERSION`.pdf
BASE_URL=http://www.php-internal.com/portable
OPTIONS=" -L 0 -R 0 -T 9mm"

$PDF_MAKER $OPTIONS --header-html $BASE_URL/header.php cover $BASE_URL/cover.php $BASE_URL/print.php $RELEASE_FILE_PATH

echo "Pdf file saved to:" $RELEASE_FILE_PATH
