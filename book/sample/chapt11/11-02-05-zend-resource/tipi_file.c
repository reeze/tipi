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

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "php.h"
#include "php_ini.h"
#include "ext/standard/info.h"
#include "php_tipi_file.h"

/* If you declare any globals in php_tipi_file.h uncomment this:
ZEND_DECLARE_MODULE_GLOBALS(tipi_file)
*/

/* True global resources - no need for thread safety here */
static int le_tipi_file;

#define TIPI_FILE_TYPE "tipi_file"

static void tipi_file_dtor(zend_resource *rsrc TSRMLS_DC){
     FILE *fp = (FILE *) rsrc->ptr;
     fclose(fp);
}

/* {{{ PHP_INI
 */
/* Remove comments and fill if you need to have entries in php.ini
PHP_INI_BEGIN()
    STD_PHP_INI_ENTRY("tipi_file.global_value",      "42", PHP_INI_ALL, OnUpdateLong, global_value, zend_tipi_file_globals, tipi_file_globals)
    STD_PHP_INI_ENTRY("tipi_file.global_string", "foobar", PHP_INI_ALL, OnUpdateString, global_string, zend_tipi_file_globals, tipi_file_globals)
PHP_INI_END()
*/
/* }}} */


/* {{{ proto resource file_open(string filename, string mode)
    */
PHP_FUNCTION(file_open)
{
	char *filename = NULL;
	char *mode = NULL;
	int argc = ZEND_NUM_ARGS();
	size_t filename_len;
	size_t mode_len;

	if (zend_parse_parameters(argc TSRMLS_CC, "ss", &filename, &filename_len, &mode, &mode_len) == FAILURE) 
		return;

    // 使用 VCWD 宏取代标准 C 文件操作函数
    FILE *fp = VCWD_FOPEN(filename, mode);

    if (fp == NULL) {
        RETURN_FALSE;
    }

    RETURN_RES(zend_register_resource(fp, le_tipi_file));
}
/* }}} */

/* {{{ proto string file_read(resource filehandle, int size)
    */
PHP_FUNCTION(file_read)
{
	int argc = ZEND_NUM_ARGS();
	int filehandle_id = -1;
	zend_long size;
	zval *filehandle = NULL;
	FILE *fp = NULL;
	char *result;
    size_t bytes_read;

	if (zend_parse_parameters(argc TSRMLS_CC, "rl", &filehandle, &size) == FAILURE) 
		return;

	if ((fp = (FILE *)zend_fetch_resource(Z_RES_P(filehandle), TIPI_FILE_TYPE, le_tipi_file)) == NULL) {
        RETURN_FALSE;
    }

    result = (char *) emalloc(size+1);
    bytes_read = fread(result, 1, size, fp);
    result[bytes_read] = '\0';

    RETURN_STRING(result);

}
/* }}} */

/* {{{ proto bool file_write(resource filehandle, string buffer)
    */
PHP_FUNCTION(file_write)
{
	char *buffer = NULL;
	int argc = ZEND_NUM_ARGS();
	int filehandle_id = -1;
	size_t buffer_len;
	zval *filehandle = NULL;
	FILE *fp = NULL;

	if (zend_parse_parameters(argc TSRMLS_CC, "rs", &filehandle, &buffer, &buffer_len) == FAILURE) 
		return;

	if ((fp = (FILE *)zend_fetch_resource(Z_RES_P(filehandle), TIPI_FILE_TYPE, le_tipi_file)) == NULL) {
        RETURN_FALSE;
    }

    if (fwrite(buffer, 1, buffer_len, fp) != buffer_len) {
        RETURN_FALSE;
    }

    RETURN_TRUE;

}
/* }}} */

/* {{{ proto bool file_close(resource filehandle)
    */
PHP_FUNCTION(file_close)
{
	int argc = ZEND_NUM_ARGS();
	int filehandle_id = -1;
	zval *filehandle = NULL;

	if (zend_parse_parameters(argc TSRMLS_CC, "r", &filehandle) == FAILURE) 
		return;

	zend_list_close(Z_RES_P(filehandle));
    RETURN_TRUE;
}
/* }}} */


/* {{{ php_tipi_file_init_globals
 */
/* Uncomment this function if you have INI entries
static void php_tipi_file_init_globals(zend_tipi_file_globals *tipi_file_globals)
{
	tipi_file_globals->global_value = 0;
	tipi_file_globals->global_string = NULL;
}
*/
/* }}} */

/* {{{ PHP_MINIT_FUNCTION
 */
PHP_MINIT_FUNCTION(tipi_file)
{
	/* If you have INI entries, uncomment these lines
	REGISTER_INI_ENTRIES();
	*/

	le_tipi_file = zend_register_list_destructors_ex(tipi_file_dtor, NULL, TIPI_FILE_TYPE, module_number);
	return SUCCESS;
}
/* }}} */

/* {{{ PHP_MSHUTDOWN_FUNCTION
 */
PHP_MSHUTDOWN_FUNCTION(tipi_file)
{
	/* uncomment this line if you have INI entries
	UNREGISTER_INI_ENTRIES();
	*/
	return SUCCESS;
}
/* }}} */

/* Remove if there's nothing to do at request start */
/* {{{ PHP_RINIT_FUNCTION
 */
PHP_RINIT_FUNCTION(tipi_file)
{
#if defined(COMPILE_DL_TIPI_FILE) && defined(ZTS)
	ZEND_TSRMLS_CACHE_UPDATE();
#endif
	return SUCCESS;
}
/* }}} */

/* Remove if there's nothing to do at request end */
/* {{{ PHP_RSHUTDOWN_FUNCTION
 */
PHP_RSHUTDOWN_FUNCTION(tipi_file)
{
	return SUCCESS;
}
/* }}} */

/* {{{ PHP_MINFO_FUNCTION
 */
PHP_MINFO_FUNCTION(tipi_file)
{
	php_info_print_table_start();
	php_info_print_table_header(2, "tipi_file support", "enabled");
	php_info_print_table_end();

	/* Remove comments if you have entries in php.ini
	DISPLAY_INI_ENTRIES();
	*/
}
/* }}} */

/* {{{ tipi_file_functions[]
 *
 * Every user visible function must have an entry in tipi_file_functions[].
 */
const zend_function_entry tipi_file_functions[] = {
	PHP_FE(file_open,	NULL)
	PHP_FE(file_read,	NULL)
	PHP_FE(file_write,	NULL)
	PHP_FE(file_close,	NULL)
	PHP_FE_END	/* Must be the last line in tipi_file_functions[] */
};
/* }}} */

/* {{{ tipi_file_module_entry
 */
zend_module_entry tipi_file_module_entry = {
	STANDARD_MODULE_HEADER,
	"tipi_file",
	tipi_file_functions,
	PHP_MINIT(tipi_file),
	PHP_MSHUTDOWN(tipi_file),
	PHP_RINIT(tipi_file),		/* Replace with NULL if there's nothing to do at request start */
	PHP_RSHUTDOWN(tipi_file),	/* Replace with NULL if there's nothing to do at request end */
	PHP_MINFO(tipi_file),
	PHP_TIPI_FILE_VERSION,
	STANDARD_MODULE_PROPERTIES
};
/* }}} */

#ifdef COMPILE_DL_TIPI_FILE
#ifdef ZTS
ZEND_TSRMLS_CACHE_DEFINE();
#endif
ZEND_GET_MODULE(tipi_file)
#endif

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: noet sw=4 ts=4 fdm=marker
 * vim<600: noet sw=4 ts=4
 */
