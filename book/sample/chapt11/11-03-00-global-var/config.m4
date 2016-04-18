dnl $Id$
dnl config.m4 for extension tipi_globals_demo

dnl Comments in this file start with the string 'dnl'.
dnl Remove where necessary. This file will not work
dnl without editing.

dnl If your extension references something external, use with:

dnl PHP_ARG_WITH(tipi_globals_demo, for tipi_globals_demo support,
dnl Make sure that the comment is aligned:
dnl [  --with-tipi_globals_demo             Include tipi_globals_demo support])

dnl Otherwise use enable:

PHP_ARG_ENABLE(tipi_globals_demo, whether to enable tipi_globals_demo support,
dnl Make sure that the comment is aligned:
[  --enable-tipi_globals_demo           Enable tipi_globals_demo support])

if test "$PHP_TIPI_GLOBALS_DEMO" != "no"; then
  dnl Write more examples of tests here...

  dnl # --with-tipi_globals_demo -> check with-path
  dnl SEARCH_PATH="/usr/local /usr"     # you might want to change this
  dnl SEARCH_FOR="/include/tipi_globals_demo.h"  # you most likely want to change this
  dnl if test -r $PHP_TIPI_GLOBALS_DEMO/$SEARCH_FOR; then # path given as parameter
  dnl   TIPI_GLOBALS_DEMO_DIR=$PHP_TIPI_GLOBALS_DEMO
  dnl else # search default path list
  dnl   AC_MSG_CHECKING([for tipi_globals_demo files in default path])
  dnl   for i in $SEARCH_PATH ; do
  dnl     if test -r $i/$SEARCH_FOR; then
  dnl       TIPI_GLOBALS_DEMO_DIR=$i
  dnl       AC_MSG_RESULT(found in $i)
  dnl     fi
  dnl   done
  dnl fi
  dnl
  dnl if test -z "$TIPI_GLOBALS_DEMO_DIR"; then
  dnl   AC_MSG_RESULT([not found])
  dnl   AC_MSG_ERROR([Please reinstall the tipi_globals_demo distribution])
  dnl fi

  dnl # --with-tipi_globals_demo -> add include path
  dnl PHP_ADD_INCLUDE($TIPI_GLOBALS_DEMO_DIR/include)

  dnl # --with-tipi_globals_demo -> check for lib and symbol presence
  dnl LIBNAME=tipi_globals_demo # you may want to change this
  dnl LIBSYMBOL=tipi_globals_demo # you most likely want to change this 

  dnl PHP_CHECK_LIBRARY($LIBNAME,$LIBSYMBOL,
  dnl [
  dnl   PHP_ADD_LIBRARY_WITH_PATH($LIBNAME, $TIPI_GLOBALS_DEMO_DIR/$PHP_LIBDIR, TIPI_GLOBALS_DEMO_SHARED_LIBADD)
  dnl   AC_DEFINE(HAVE_TIPI_GLOBALS_DEMOLIB,1,[ ])
  dnl ],[
  dnl   AC_MSG_ERROR([wrong tipi_globals_demo lib version or lib not found])
  dnl ],[
  dnl   -L$TIPI_GLOBALS_DEMO_DIR/$PHP_LIBDIR -lm
  dnl ])
  dnl
  dnl PHP_SUBST(TIPI_GLOBALS_DEMO_SHARED_LIBADD)

  PHP_NEW_EXTENSION(tipi_globals_demo, tipi_globals_demo.c, $ext_shared,, -DZEND_ENABLE_STATIC_TSRMLS_CACHE=1)
fi
