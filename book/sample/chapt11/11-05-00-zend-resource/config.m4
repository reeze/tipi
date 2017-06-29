dnl $Id$
dnl config.m4 for extension tipi_file

dnl Comments in this file start with the string 'dnl'.
dnl Remove where necessary. This file will not work
dnl without editing.

dnl If your extension references something external, use with:

PHP_ARG_WITH(tipi_file, for tipi_file support,
dnl Make sure that the comment is aligned:
[  --with-tipi_file             Include tipi_file support])

dnl Otherwise use enable:

dnl PHP_ARG_ENABLE(tipi_file, whether to enable tipi_file support,
dnl Make sure that the comment is aligned:
dnl [  --enable-tipi_file           Enable tipi_file support])

if test "$PHP_TIPI_FILE" != "no"; then
  dnl Write more examples of tests here...

  dnl # --with-tipi_file -> check with-path
  dnl SEARCH_PATH="/usr/local /usr"     # you might want to change this
  dnl SEARCH_FOR="/include/tipi_file.h"  # you most likely want to change this
  dnl if test -r $PHP_TIPI_FILE/$SEARCH_FOR; then # path given as parameter
  dnl   TIPI_FILE_DIR=$PHP_TIPI_FILE
  dnl else # search default path list
  dnl   AC_MSG_CHECKING([for tipi_file files in default path])
  dnl   for i in $SEARCH_PATH ; do
  dnl     if test -r $i/$SEARCH_FOR; then
  dnl       TIPI_FILE_DIR=$i
  dnl       AC_MSG_RESULT(found in $i)
  dnl     fi
  dnl   done
  dnl fi
  dnl
  dnl if test -z "$TIPI_FILE_DIR"; then
  dnl   AC_MSG_RESULT([not found])
  dnl   AC_MSG_ERROR([Please reinstall the tipi_file distribution])
  dnl fi

  dnl # --with-tipi_file -> add include path
  dnl PHP_ADD_INCLUDE($TIPI_FILE_DIR/include)

  dnl # --with-tipi_file -> check for lib and symbol presence
  dnl LIBNAME=tipi_file # you may want to change this
  dnl LIBSYMBOL=tipi_file # you most likely want to change this 

  dnl PHP_CHECK_LIBRARY($LIBNAME,$LIBSYMBOL,
  dnl [
  dnl   PHP_ADD_LIBRARY_WITH_PATH($LIBNAME, $TIPI_FILE_DIR/$PHP_LIBDIR, TIPI_FILE_SHARED_LIBADD)
  dnl   AC_DEFINE(HAVE_TIPI_FILELIB,1,[ ])
  dnl ],[
  dnl   AC_MSG_ERROR([wrong tipi_file lib version or lib not found])
  dnl ],[
  dnl   -L$TIPI_FILE_DIR/$PHP_LIBDIR -lm
  dnl ])
  dnl
  dnl PHP_SUBST(TIPI_FILE_SHARED_LIBADD)

  PHP_NEW_EXTENSION(tipi_file, tipi_file.c, $ext_shared,, -DZEND_ENABLE_STATIC_TSRMLS_CACHE=1)
fi
