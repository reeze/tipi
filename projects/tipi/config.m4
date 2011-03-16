dnl $Id$
dnl config.m4 for extension tipi

dnl Comments in this file start with the string 'dnl'.
dnl Remove where necessary. This file will not work
dnl without editing.

dnl If your extension references something external, use with:

dnl PHP_ARG_WITH(tipi, for tipi support,
dnl Make sure that the comment is aligned:
dnl [  --with-tipi             Include tipi support])

dnl Otherwise use enable:

PHP_ARG_ENABLE(tipi, whether to enable tipi support,
dnl Make sure that the comment is aligned:
[  --enable-tipi           Enable tipi support])

if test "$PHP_TIPI" != "no"; then
  dnl Write more examples of tests here...

  dnl # --with-tipi -> check with-path
  dnl SEARCH_PATH="/usr/local /usr"     # you might want to change this
  dnl SEARCH_FOR="/include/tipi.h"  # you most likely want to change this
  dnl if test -r $PHP_TIPI/$SEARCH_FOR; then # path given as parameter
  dnl   TIPI_DIR=$PHP_TIPI
  dnl else # search default path list
  dnl   AC_MSG_CHECKING([for tipi files in default path])
  dnl   for i in $SEARCH_PATH ; do
  dnl     if test -r $i/$SEARCH_FOR; then
  dnl       TIPI_DIR=$i
  dnl       AC_MSG_RESULT(found in $i)
  dnl     fi
  dnl   done
  dnl fi
  dnl
  dnl if test -z "$TIPI_DIR"; then
  dnl   AC_MSG_RESULT([not found])
  dnl   AC_MSG_ERROR([Please reinstall the tipi distribution])
  dnl fi

  dnl # --with-tipi -> add include path
  dnl PHP_ADD_INCLUDE($TIPI_DIR/include)

  dnl # --with-tipi -> check for lib and symbol presence
  dnl LIBNAME=tipi # you may want to change this
  dnl LIBSYMBOL=tipi # you most likely want to change this 

  dnl PHP_CHECK_LIBRARY($LIBNAME,$LIBSYMBOL,
  dnl [
  dnl   PHP_ADD_LIBRARY_WITH_PATH($LIBNAME, $TIPI_DIR/lib, TIPI_SHARED_LIBADD)
  dnl   AC_DEFINE(HAVE_TIPILIB,1,[ ])
  dnl ],[
  dnl   AC_MSG_ERROR([wrong tipi lib version or lib not found])
  dnl ],[
  dnl   -L$TIPI_DIR/lib -lm
  dnl ])
  dnl
  dnl PHP_SUBST(TIPI_SHARED_LIBADD)

  PHP_NEW_EXTENSION(tipi, tipi.c, $ext_shared)
fi
