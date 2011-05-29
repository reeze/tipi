/*
  +----------------------------------------------------------------------+
  | PHP Version 5                                                        |
  +----------------------------------------------------------------------+
  | Copyright (c) 1997-2008 The PHP Group                                |
  +----------------------------------------------------------------------+
  | This source file is subject to version 3.01 of the PHP license,      |
  | that is bundled with this package in the file LICENSE, and is        |
  | available through the world-wide-web at the following url:           |
  | http://www.php.net/license/3_01.txt                                  |
  | If you did not receive a copy of the PHP license and are unable to   |
  | obtain it through the world-wide-web, please send a note to          |
  | license@php.net so we can mail you a copy immediately.               |
  +----------------------------------------------------------------------+
  | Author:                                                              |
  +----------------------------------------------------------------------+
*/

/* $Id: header 252479 2008-02-07 19:39:50Z iliaa $ */

#ifndef PHP_TIPI_H
#define PHP_TIPI_H

extern zend_module_entry tipi_module_entry;
#define phpext_tipi_ptr &tipi_module_entry

#ifdef PHP_WIN32
#	define PHP_TIPI_API __declspec(dllexport)
#elif defined(__GNUC__) && __GNUC__ >= 4
#	define PHP_TIPI_API __attribute__ ((visibility("default")))
#else
#	define PHP_TIPI_API
#endif

#ifdef ZTS
#include "TSRM.h"
#endif

#define TIPI_PREFIX zend_printf("      ")
#define TIPI_LINE zend_printf(" \n  ")

PHP_MINIT_FUNCTION(tipi);
PHP_MSHUTDOWN_FUNCTION(tipi);
PHP_RINIT_FUNCTION(tipi);
PHP_RSHUTDOWN_FUNCTION(tipi);
PHP_MINFO_FUNCTION(tipi);


PHP_FUNCTION(tipi_test);	
PHP_FUNCTION(tipi_debug_function_dump);	
PHP_FUNCTION(tipi_debug_function_dump_all);	
PHP_FUNCTION(tipi_debug_zval_dump);	

PHP_FUNCTION(tipi_debug_class_dump);	
PHP_FUNCTION(tipi_debug_object_dump);	

/* 
  	Declare any global variables you may need between the BEGIN
	and END macros here:     

ZEND_BEGIN_MODULE_GLOBALS(tipi)
	long  global_value;
	char *global_string;
ZEND_END_MODULE_GLOBALS(tipi)
*/

/* In every utility function you add that needs to use variables 
   in php_tipi_globals, call TSRMLS_FETCH(); after declaring other 
   variables used by that function, or better yet, pass in TSRMLS_CC
   after the last function argument and declare your utility function
   with TSRMLS_DC after the last declared argument.  Always refer to
   the globals in your function as TIPI_G(variable).  You are 
   encouraged to rename these macros something shorter, see
   examples in any other php module directory.
*/

#ifdef ZTS
#define TIPI_G(v) TSRMG(tipi_globals_id, zend_tipi_globals *, v)
#else
#define TIPI_G(v) (tipi_globals.v)
#endif

#endif	/* PHP_TIPI_H */


/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: noet sw=4 ts=4 fdm=marker
 * vim<600: noet sw=4 ts=4
 */
