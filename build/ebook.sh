
OPTIONS="--authors 'Reeze Xia, Zhanger, Phppan' "
OPTIONS="$OPTIONS --level1-toc '//h:h1' --level2-toc '//h:h2'"
OPTIONS="$OPTIONS --no-default-epub-cover --cover cover.html"

VERSION=`cat VERSION`

# Save /portable/print.php as tipi.html with related asserts

ebook-convert tipi.html web/releases/$VERSION.epub $OPTIONS
ebook-convert tipi.html web/releases/$VERSION.mobi $OPTIONS