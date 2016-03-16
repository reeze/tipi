dnl $Id$
dnl config.m4 for extension tipi_demo01

dnl Comments in this file start with the string 'dnl'.
dnl Remove where necessary. This file will not work
dnl without editing.

dnl If your extension references something external, use with:

PHP_ARG_WITH(tipi_demo01, for tipi_demo01 support,
dnl Make sure that the comment is aligned:
[  --with-tipi_demo01             Include tipi_demo01 support])

dnl Otherwise use enable:

dnl PHP_ARG_ENABLE(tipi_demo01, whether to enable tipi_demo01 support,
dnl Make sure that the comment is aligned:
dnl [  --enable-tipi_demo01           Enable tipi_demo01 support])

if test "$PHP_TIPI_DEMO01" != "no"; then
  dnl Write more examples of tests here...

  dnl # --with-tipi_demo01 -> check with-path
  dnl SEARCH_PATH="/usr/local /usr"     # you might want to change this
  dnl SEARCH_FOR="/include/tipi_demo01.h"  # you most likely want to change this
  dnl if test -r $PHP_TIPI_DEMO01/$SEARCH_FOR; then # path given as parameter
  dnl   TIPI_DEMO01_DIR=$PHP_TIPI_DEMO01
  dnl else # search default path list
  dnl   AC_MSG_CHECKING([for tipi_demo01 files in default path])
  dnl   for i in $SEARCH_PATH ; do
  dnl     if test -r $i/$SEARCH_FOR; then
  dnl       TIPI_DEMO01_DIR=$i
  dnl       AC_MSG_RESULT(found in $i)
  dnl     fi
  dnl   done
  dnl fi
  dnl
  dnl if test -z "$TIPI_DEMO01_DIR"; then
  dnl   AC_MSG_RESULT([not found])
  dnl   AC_MSG_ERROR([Please reinstall the tipi_demo01 distribution])
  dnl fi

  dnl # --with-tipi_demo01 -> add include path
  dnl PHP_ADD_INCLUDE($TIPI_DEMO01_DIR/include)

  dnl # --with-tipi_demo01 -> check for lib and symbol presence
  dnl LIBNAME=tipi_demo01 # you may want to change this
  dnl LIBSYMBOL=tipi_demo01 # you most likely want to change this 

  dnl PHP_CHECK_LIBRARY($LIBNAME,$LIBSYMBOL,
  dnl [
  dnl   PHP_ADD_LIBRARY_WITH_PATH($LIBNAME, $TIPI_DEMO01_DIR/$PHP_LIBDIR, TIPI_DEMO01_SHARED_LIBADD)
  dnl   AC_DEFINE(HAVE_TIPI_DEMO01LIB,1,[ ])
  dnl ],[
  dnl   AC_MSG_ERROR([wrong tipi_demo01 lib version or lib not found])
  dnl ],[
  dnl   -L$TIPI_DEMO01_DIR/$PHP_LIBDIR -lm
  dnl ])
  dnl
  dnl PHP_SUBST(TIPI_DEMO01_SHARED_LIBADD)

  PHP_NEW_EXTENSION(tipi_demo01, tipi_demo01.c, $ext_shared,, -DZEND_ENABLE_STATIC_TSRMLS_CACHE=1)
fi
