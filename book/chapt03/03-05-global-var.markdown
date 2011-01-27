# 第五节 PHP中的全局变量


## 预定义变量$GLOBALS
我们以cgi模式为例说明$GLOBALS的初始化。
从cgi_main.c文件main函数开始。
整个调用顺序如下所示：

**[main() -> php_request_startup() -> zend_activate() -> init_executor() ]**

    [c]
    ... //  省略
	zend_hash_init(&EG(symbol_table), 50, NULL, ZVAL_PTR_DTOR, 0);
	{
		zval *globals;

		ALLOC_ZVAL(globals);
		Z_SET_REFCOUNT_P(globals, 1);
		Z_SET_ISREF_P(globals);
		Z_TYPE_P(globals) = IS_ARRAY;
		Z_ARRVAL_P(globals) = &EG(symbol_table);
		zend_hash_update(&EG(symbol_table), "GLOBALS", sizeof("GLOBALS"), &globals, sizeof(zval *), NULL);
	}
    ... //  省略

给此全局变量增加一个值。

## global语句

## $_GET和$_POST变量
**[main() -> php_request_startup() -> php_hash_environment()  ]**
在请求初始化时，处理以请求处理过程需要使用的一些环境变量。
这里包括_POST等我们经常用到的一些预定义的变量。

    [c]
    /* {{{ php_hash_environment
     */
    int php_hash_environment(TSRMLS_D)
    {
        char *p;
        unsigned char _gpc_flags[5] = {0, 0, 0, 0, 0};
        zend_bool jit_initialization = (PG(auto_globals_jit) && !PG(register_globals) && !PG(register_long_arrays));
        struct auto_global_record {
            char *name;
            uint name_len;
            char *long_name;
            uint long_name_len;
            zend_bool jit_initialization;
        } auto_global_records[] = {
            { "_POST", sizeof("_POST"), "HTTP_POST_VARS", sizeof("HTTP_POST_VARS"), 0 },
            { "_GET", sizeof("_GET"), "HTTP_GET_VARS", sizeof("HTTP_GET_VARS"), 0 },
            { "_COOKIE", sizeof("_COOKIE"), "HTTP_COOKIE_VARS", sizeof("HTTP_COOKIE_VARS"), 0 },
            { "_SERVER", sizeof("_SERVER"), "HTTP_SERVER_VARS", sizeof("HTTP_SERVER_VARS"), 1 },
            { "_ENV", sizeof("_ENV"), "HTTP_ENV_VARS", sizeof("HTTP_ENV_VARS"), 1 },
            { "_FILES", sizeof("_FILES"), "HTTP_POST_FILES", sizeof("HTTP_POST_FILES"), 0 },
        };
        size_t num_track_vars = sizeof(auto_global_records)/sizeof(struct auto_global_record);
        size_t i;

        /* jit_initialization = 0; */
        for (i=0; i<num_track_vars; i++) {
            PG(http_globals)[i] = NULL;
        }

        for (p=PG(variables_order); p && *p; p++) {
            switch(*p) {
                case 'p':
                case 'P':
                    if (!_gpc_flags[0] && !SG(headers_sent) && SG(request_info).request_method && !strcasecmp(SG(request_info).request_method, "POST")) {
                        sapi_module.treat_data(PARSE_POST, NULL, NULL TSRMLS_CC);	/* POST Data */
                        _gpc_flags[0] = 1;
                        if (PG(register_globals)) {
                            php_autoglobal_merge(&EG(symbol_table), Z_ARRVAL_P(PG(http_globals)[TRACK_VARS_POST]) TSRMLS_CC);
                        }
                    }
                    break;
                case 'c':
                case 'C':
                    if (!_gpc_flags[1]) {
                        sapi_module.treat_data(PARSE_COOKIE, NULL, NULL TSRMLS_CC);	/* Cookie Data */
                        _gpc_flags[1] = 1;
                        if (PG(register_globals)) {
                            php_autoglobal_merge(&EG(symbol_table), Z_ARRVAL_P(PG(http_globals)[TRACK_VARS_COOKIE]) TSRMLS_CC);
                        }
                    }
                    break;
                case 'g':
                case 'G':
                    if (!_gpc_flags[2]) {
                        sapi_module.treat_data(PARSE_GET, NULL, NULL TSRMLS_CC);	/* GET Data */
                        _gpc_flags[2] = 1;
                        if (PG(register_globals)) {
                            php_autoglobal_merge(&EG(symbol_table), Z_ARRVAL_P(PG(http_globals)[TRACK_VARS_GET]) TSRMLS_CC);
                        }
                    }
                    break;
                case 'e':
                case 'E':
                    if (!jit_initialization && !_gpc_flags[3]) {
                        zend_auto_global_disable_jit("_ENV", sizeof("_ENV")-1 TSRMLS_CC);
                        php_auto_globals_create_env("_ENV", sizeof("_ENV")-1 TSRMLS_CC);
                        _gpc_flags[3] = 1;
                        if (PG(register_globals)) {
                            php_autoglobal_merge(&EG(symbol_table), Z_ARRVAL_P(PG(http_globals)[TRACK_VARS_ENV]) TSRMLS_CC);
                        }
                    }
                    break;
                case 's':
                case 'S':
                    if (!jit_initialization && !_gpc_flags[4]) {
                        zend_auto_global_disable_jit("_SERVER", sizeof("_SERVER")-1 TSRMLS_CC);
                        php_register_server_variables(TSRMLS_C);
                        _gpc_flags[4] = 1;
                        if (PG(register_globals)) {
                            php_autoglobal_merge(&EG(symbol_table), Z_ARRVAL_P(PG(http_globals)[TRACK_VARS_SERVER]) TSRMLS_CC);
                        }
                    }
                    break;
            }
        }

        /* argv/argc support */
        if (PG(register_argc_argv)) {
            php_build_argv(SG(request_info).query_string, PG(http_globals)[TRACK_VARS_SERVER] TSRMLS_CC);
        }

        for (i=0; i<num_track_vars; i++) {
            if (jit_initialization && auto_global_records[i].jit_initialization) {
                continue;
            }
            if (!PG(http_globals)[i]) {
                ALLOC_ZVAL(PG(http_globals)[i]);
                array_init(PG(http_globals)[i]);
                INIT_PZVAL(PG(http_globals)[i]);
            }

            Z_ADDREF_P(PG(http_globals)[i]);
            zend_hash_update(&EG(symbol_table), auto_global_records[i].name, auto_global_records[i].name_len, &PG(http_globals)[i], sizeof(zval *), NULL);
            if (PG(register_long_arrays)) {
                zend_hash_update(&EG(symbol_table), auto_global_records[i].long_name, auto_global_records[i].long_name_len, &PG(http_globals)[i], sizeof(zval *), NULL);
                Z_ADDREF_P(PG(http_globals)[i]);
            }
        }

        /* Create _REQUEST */
        if (!jit_initialization) {
            zend_auto_global_disable_jit("_REQUEST", sizeof("_REQUEST")-1 TSRMLS_CC);
            php_auto_globals_create_request("_REQUEST", sizeof("_REQUEST")-1 TSRMLS_CC);
        }

        return SUCCESS;
    }
    /* }}} */


