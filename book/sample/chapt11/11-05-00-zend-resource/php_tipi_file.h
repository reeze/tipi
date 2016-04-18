/*
  +----------------------------------------------------------------------+
  | PHP Version 7                                                        |
  +----------------------------------------------------------------------+
  | Copyright (c) 1997-2016 The PHP Group                                |
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

/* $Id$ */

#ifndef PHP_TIPI_FILE_H
#define PHP_TIPI_FILE_H

extern zend_module_entry tipi_file_module_entry;
#define phpext_tipi_file_ptr &tipi_file_module_entry

#define PHP_TIPI_FILE_VERSION "0.1.0" /* Replace with version number for your extension */

#ifdef PHP_WIN32
#	define PHP_TIPI_FILE_API __declspec(dllexport)
#elif defined(__GNUC__) && __GNUC__ >= 4
#	define PHP_TIPI_FILE_API __attribute__ ((visibility("default")))
#else
#	define PHP_TIPI_FILE_API
#endif

#ifdef ZTS
#include "TSRM.h"
#endif

/*
  	Declare any global variables you may need between the BEGIN
	and END macros here:

ZEND_BEGIN_MODULE_GLOBALS(tipi_file)
	zend_long  global_value;
	char *global_string;
ZEND_END_MODULE_GLOBALS(tipi_file)
*/

/* Always refer to the globals in your function as TIPI_FILE_G(variable).
   You are encouraged to rename these macros something shorter, see
   examples in any other php module directory.
*/
#define TIPI_FILE_G(v) ZEND_MODULE_GLOBALS_ACCESSOR(tipi_file, v)

#if defined(ZTS) && defined(COMPILE_DL_TIPI_FILE)
ZEND_TSRMLS_CACHE_EXTERN();
#endif

#endif	/* PHP_TIPI_FILE_H */


/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: noet sw=4 ts=4 fdm=marker
 * vim<600: noet sw=4 ts=4
 */
