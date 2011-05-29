/*
  +----------------------------------------------------------------------+
 \n PHP Version 5                                                       \n
  +----------------------------------------------------------------------+
 \n Copyright (c) 1997-2008 The PHP Group                               \n
  +----------------------------------------------------------------------+
 \n This source file is subject to version 3.01 of the PHP license,     \n
 \n that is bundled with this package in the file LICENSE, and is       \n
 \n available through the world-wide-web at the following url:          \n
 \n http://www.php.net/license/3_01.txt                                 \n
 \n If you did not receive a copy of the PHP license and are unable to  \n
 \n obtain it through the world-wide-web, please send a note to         \n
 \n license@php.net so we can mail you a copy immediately.              \n
  +----------------------------------------------------------------------+
 \n Author:                                                             \n
  +----------------------------------------------------------------------+
*/

/* $Id: header 252479 2008-02-07 19:39:50Z iliaa $ */

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "php.h"
#include "php_ini.h"
#include "ext/standard/info.h"
#include "php_tipi.h"
#include "ext/standard/php_var.h"

/* If you declare any globals in php_tipi.h uncomment this:
ZEND_DECLARE_MODULE_GLOBALS(tipi)
*/

/* True global resources - no need for thread safety here */
static int le_tipi;

/* {{{ tipi_functions[]
 *
 * Every user visible function must have an entry in tipi_functions[].
 */
const zend_function_entry tipi_functions[] = {
	PHP_FE(tipi_test,	NULL)		
	PHP_FE(tipi_debug_function_dump,	NULL)		
	PHP_FE(tipi_debug_function_dump_all,	NULL)		
	PHP_FE(tipi_debug_zval_dump,	NULL)		
	PHP_FE(tipi_debug_class_dump,	NULL)		
	PHP_FE(tipi_debug_object_dump,	NULL)		
	{NULL, NULL, NULL}	/* Must be the last line in tipi_functions[] */
};
/* }}} */

/* {{{ tipi_module_entry
 */
zend_module_entry tipi_module_entry = {
#if ZEND_MODULE_API_NO >= 20010901
	STANDARD_MODULE_HEADER,
#endif
	"tipi",
	tipi_functions,
	PHP_MINIT(tipi),
	PHP_MSHUTDOWN(tipi),
	PHP_RINIT(tipi),		/* Replace with NULL if there's nothing to do at request start */
	PHP_RSHUTDOWN(tipi),	/* Replace with NULL if there's nothing to do at request end */
	PHP_MINFO(tipi),
#if ZEND_MODULE_API_NO >= 20010901
	"0.1", /* Replace with version number for your extension */
#endif
	STANDARD_MODULE_PROPERTIES
};
/* }}} */

#ifdef COMPILE_DL_TIPI
ZEND_GET_MODULE(tipi)
#endif

/* {{{ PHP_INI
 */
/* Remove comments and fill if you need to have entries in php.ini
PHP_INI_BEGIN()
    STD_PHP_INI_ENTRY("tipi.global_value",      "42", PHP_INI_ALL, OnUpdateLong, global_value, zend_tipi_globals, tipi_globals)
    STD_PHP_INI_ENTRY("tipi.global_string", "foobar", PHP_INI_ALL, OnUpdateString, global_string, zend_tipi_globals, tipi_globals)
PHP_INI_END()
*/
/* }}} */

/* {{{ php_tipi_init_globals
 */
/* Uncomment this function if you have INI entries
static void php_tipi_init_globals(zend_tipi_globals *tipi_globals)
{
	tipi_globals->global_value = 0;
	tipi_globals->global_string = NULL;
}
*/
/* }}} */

/* {{{ PHP_MINIT_FUNCTION
 */
PHP_MINIT_FUNCTION(tipi)
{
	/* If you have INI entries, uncomment these lines 
	REGISTER_INI_ENTRIES();
	*/
	return SUCCESS;
}
/* }}} */

/* {{{ PHP_MSHUTDOWN_FUNCTION
 */
PHP_MSHUTDOWN_FUNCTION(tipi)
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
PHP_RINIT_FUNCTION(tipi)
{
	return SUCCESS;
}
/* }}} */

/* Remove if there's nothing to do at request end */
/* {{{ PHP_RSHUTDOWN_FUNCTION
 */
PHP_RSHUTDOWN_FUNCTION(tipi)
{
	return SUCCESS;
}
/* }}} */

/* {{{ PHP_MINFO_FUNCTION
 */
PHP_MINFO_FUNCTION(tipi)
{
	php_info_print_table_start();
	php_info_print_table_header(2, "tipi support", "enabled");
	php_info_print_table_end();

	/* Remove comments if you have entries in php.ini
	DISPLAY_INI_ENTRIES();
	*/
}
/* }}} */


/* Remove the following function when you have succesfully modified config.m4
   so that your module can be compiled into PHP, it exists only for testing
   purposes. */

/* Every user-visible function in PHP should document itself in the source */
/* {{{ proto string confirm_tipi_compiled(string arg)
   Return a string to confirm that the module is compiled in */
PHP_FUNCTION(tipi_test)
{
	char *arg = NULL;
	int arg_len, len;
	char *strg;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &arg, &arg_len) == FAILURE) {
		return;
	}

	len = spprintf(&strg, 0, "Congratulations! You have successfully modified ext/%.78s/config.m4. Module %.78s is now compiled into PHP.", "tipi", arg);
	RETURN_STRINGL(strg, len, 0);
}
/* }}} */
/* The previous line is meant for vim and emacs, so it can correctly fold and 
   unfold functions in source code. See the corresponding marks just before 
   function definition, where the functions purpose is also documented. Please 
   follow this convention for the convenience of others editing your code.
*/


/**
 * 从xdebug扩展中copy过来的
 */
zval* tipi_get_php_symbol(char* name, int name_length)
{
	HashTable           *st = NULL;
	zval               **retval;
	TSRMLS_FETCH();

	st = EG(active_symbol_table);
	if (st && st->nNumOfElements && zend_hash_find(st, name, name_length, (void **) &retval) == SUCCESS) {
		return *retval;
	}

	st = EG(active_op_array)->static_variables;
	if (st) {
		if (zend_hash_find(st, name, name_length, (void **) &retval) == SUCCESS) {
			return *retval;
		}
	}
	
	st = &EG(symbol_table);
	if (zend_hash_find(st, name, name_length, (void **) &retval) == SUCCESS) {
		return *retval;
	}
	return NULL;
}

static tipi_print_zval(zval *z)
{
	zend_printf(" zval refucount=%d\n", Z_REFCOUNT_P(z));
}

/**
 * 输出变量的相关属性值
 */
PHP_FUNCTION(tipi_debug_zval_dump)
{
	zval ***args;
	int 	argc;
	int i;
	zval *z;

	argc = ZEND_NUM_ARGS();

	args = (zval ***)emalloc(argc * sizeof(zval **));
	if (ZEND_NUM_ARGS() == 0 || zend_get_parameters_array_ex(argc, args) == FAILURE) {
		efree(args);
		WRONG_PARAM_COUNT;
	}

	for (i = 0; i < argc; i++) {
		if (Z_TYPE_PP(args[i]) == IS_STRING) {
			tipi_print_zval(tipi_get_php_symbol(Z_STRVAL_PP(args[i]), Z_STRLEN_PP(args[i]) + 1));
		}
	}

	efree(args);
	
}

static void tipi_dump_function_detail(zend_function *func)
{
	zend_printf("type=%d \n  ", func->common.type);
	zend_printf("fn_flags=%d \n  ", func->common.fn_flags);
	zend_printf("nums_args=%d \n  ", func->common.num_args);
	zend_printf("required_num_args=%d \n  ", func->common.required_num_args);
	zend_printf("pass_rest_by_reference=%d \n  ", func->common.pass_rest_by_reference);
	zend_printf("return_reference=%d \n  ", func->common.return_reference);
}

/**
 * 输出函数信息
 */
static void tipi_dump_function(zend_function *func) 
{
	zend_printf("-------------------- Function %s --------------------", func->common.function_name);
	TIPI_LINE;
	
	tipi_dump_function_detail(func);
	TIPI_LINE;
}


/**
 * 输出类的方法
 */
static void tipi_dump_method(zend_function *func, char *class_name, int key)
{
	zend_printf("--------------------  Function %s IN Class %s, the %d . --------------------", func->common.function_name, class_name, key);
	TIPI_LINE;

	tipi_dump_function_detail(func);
}

static void tipi_dump_class_magic_method(zend_function *method, char *method_name, char *class_name)
{
	if (method->common.function_name  == NULL) {
		zend_printf("magic function %s =  null \n  ", method_name);
	}else{
		tipi_dump_method(&method, class_name, 0);
	}
}

static void tipi_dump_class_magic_methods(zend_class_entry **ce)
{
	tipi_dump_class_magic_method(&(*ce)->constructor, "constructor", (*ce)->name);
	tipi_dump_class_magic_method(&(*ce)->destructor, "destructor", (*ce)->name);
	tipi_dump_class_magic_method(&(*ce)->clone, "clone", (*ce)->name);
	tipi_dump_class_magic_method(&(*ce)->__get, "__get", (*ce)->name);
	tipi_dump_class_magic_method(&(*ce)->__set, "__set", (*ce)->name);
	tipi_dump_class_magic_method(&(*ce)->__unset, "__unset", (*ce)->name);
	tipi_dump_class_magic_method(&(*ce)->__isset, "__isset", (*ce)->name);
	tipi_dump_class_magic_method(&(*ce)->__call, "__call", (*ce)->name);
	tipi_dump_class_magic_method(&(*ce)->__tostring, "__tostring", (*ce)->name);
}

/**
 * 输出函数的相关信息
 */
PHP_FUNCTION(tipi_debug_function_dump)
{
	zval ***args;
	int 	argc;
	int i;
	zend_function *func;
	char *name;
	int name_len;
	char *lcname;

	argc = ZEND_NUM_ARGS();

	args = (zval ***)emalloc(argc * sizeof(zval **));
	if (ZEND_NUM_ARGS() == 0 || zend_get_parameters_array_ex(argc, args) == FAILURE) {
		efree(args);
		WRONG_PARAM_COUNT;
	}

	for (i = 0; i < argc; i++) {
		if (Z_TYPE_PP(args[i]) != IS_STRING) {
			efree(args);
			php_error(E_ERROR, "input error!");
			return;
		}
	}

	
	for (i = 0; i < argc; i++) {
		name = Z_STRVAL_PP(args[i]);
		name_len = Z_STRLEN_PP(args[i]);
		lcname = zend_str_tolower_dup(name, name_len);


		name = lcname;
		if (lcname[0] == '\\') {
			name = &lcname[1];
			name_len--;
		}

		if (zend_hash_find(EG(function_table), name, name_len + 1, (void **)&func) == SUCCESS) {
			tipi_dump_function(func);		
		}else{
			zend_printf("cant find function %s\n", name);
		}


	}
	efree(args);

}

static void tipi_print_function_table(HashTable *function_table) {

	HashPosition pos;
	zend_function *func;

	for (zend_hash_internal_pointer_reset_ex(function_table, &pos);
			zend_hash_get_current_data_ex(function_table, (void **) &func, &pos) == SUCCESS;
			zend_hash_move_forward_ex(function_table, &pos)
	    ) {

		tipi_dump_function(func);
	}
	
}

static void tipi_print_methods(HashTable *function_table, char *class_name)
{
	HashPosition pos;
	zend_function *func;
	int i = 0;

	for (zend_hash_internal_pointer_reset_ex(function_table, &pos);
			zend_hash_get_current_data_ex(function_table, (void **) &func, &pos) == SUCCESS;
			zend_hash_move_forward_ex(function_table, &pos)
	    ) {		
		tipi_dump_method(func, class_name, ++i);
	}
}

/**
 * 输出函数的相关信息
 */
PHP_FUNCTION(tipi_debug_function_dump_all)
{
	tipi_print_function_table(EG(function_table));
	zend_printf("count=%d\n", zend_hash_num_elements(EG(function_table)));
}

static void tipi_dump_class(zend_class_entry **ce)
{
	zend_printf("****************************** Class %s ******************************", (*ce)->name);
	TIPI_LINE;

	zend_printf("type=%d \n", (*ce)->type);

	zend_printf("parent class= \n  ");
	if ((*ce)->parent != NULL) {
		tipi_dump_class((zend_class_entry **)(&(*ce)->parent));
	}
	zend_printf("\n");
	
	zend_printf("refcount=%d \n", (*ce)->refcount);
	zend_printf("constants_updated=%d \n", (*ce)->constants_updated);
	zend_printf("ce_flags=%d \n", (*ce)->ce_flags);

	zend_printf("functions=\n  ");
	if (zend_hash_num_elements(&((*ce)->function_table)) == 0) {
		zend_printf("no \n");
	}else{
		tipi_print_methods(&((*ce)->function_table), (*ce)->name);
	}
	zend_printf("\n");

	tipi_dump_class_magic_methods(ce);

	zend_printf("interfaces(%d)= \n  ", (*ce)->num_interfaces);
	if ((*ce)->interfaces != NULL) {
		tipi_dump_class((*ce)->interfaces);
		zend_printf("\n");
	}

	zend_printf("filename=%s \n  ", (*ce)->filename);
	zend_printf("line start=%d \n  ", (*ce)->line_start);
	zend_printf("line end=%d \n  ", (*ce)->line_end);
	zend_printf("comment=%s \n  ", (*ce)->doc_comment);
	
}

/**
 * 输出类的相关信息
 */
PHP_FUNCTION(tipi_debug_class_dump)
{
	zval ***args;
	int 	argc;
	int i;
	zend_class_entry **ce;
	char *name;
	int name_len;
	char *lcname;

	argc = ZEND_NUM_ARGS();

	args = (zval ***)emalloc(argc * sizeof(zval **));
	if (ZEND_NUM_ARGS() == 0 || zend_get_parameters_array_ex(argc, args) == FAILURE) {
		efree(args);
		WRONG_PARAM_COUNT;
	}

	for (i = 0; i < argc; i++) {
		if (Z_TYPE_PP(args[i]) != IS_STRING) {
			efree(args);
			php_error(E_ERROR, "input error!");
			return;
		}
	}

	
	for (i = 0; i < argc; i++) {
		name = Z_STRVAL_PP(args[i]);
		name_len = Z_STRLEN_PP(args[i]);
		lcname = zend_str_tolower_dup(name, name_len);


		name = lcname;
		if (lcname[0] == '\\') {
			name = &lcname[1];
			name_len--;
		}

		if (zend_hash_find(EG(class_table), name, name_len + 1, (void **) &ce) == SUCCESS) {
			tipi_dump_class(ce);		
		}else{
			zend_printf("cant find class %s\n", name);
		}


	}
	efree(args);

}

/**
 * 输出对象的相关信息
 */
PHP_FUNCTION(tipi_debug_object_dump)
{
}

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: noet sw=4 ts=4 fdm=marker
 * vim<600: noet sw=4 ts=4
 */
