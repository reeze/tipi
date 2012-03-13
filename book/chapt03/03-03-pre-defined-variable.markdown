# 第三节 预定义变量

大家都知道PHP脚本在执行的时候用户全局变量(在用户空间显式定义的变量)会保存在一个HashTable数据类型的符号表(symbol_table)中，
在PHP中有一些比较特殊的全局变量例如:
$\_GET，$\_POST，$\_SERVER，$\_FILES等变量，我们并没有在程序中定义这些变量，并且这些变量也同样保存在符号表中，
从这些表象我们不难得出结论：PHP是在脚本运行之前就将这些特殊的变量加入到了符号表中了。

## 预定义变量$GLOBALS的初始化
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
		zend_hash_update(&EG(symbol_table), "GLOBALS", sizeof("GLOBALS"),
            &globals, sizeof(zval *), NULL);      //  添加全局变量GLOBALS
	}
    ... //  省略

上面的代码的关键点zend_hash_update函数的调用，它将变量名为GLOBALS的变量注册到EG(symbol_table)中，
EG(symbol_table)是一个HashTable的结构，用来存放所有的全局变量。
这在下面将要提到的$_GET等变量初始化时也会用到。

## $_GET、$_POST等变量的初始化

**$_GET、$_COOKIE、$_SERVER、$_ENV、$_FILES、$_REQUEST**这六个变量都是通过如下的调用序列进行初始化。
**[main() -> php_request_startup() -> php_hash_environment()  ]**  
在请求初始化时，通过调用 **php_hash_environment** 函数初始化以上的六个预定义的变量。
如下所示为php_hash_environment函数的代码。在代码之后我们以$_POST为例说明整个初始化的过程。

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

以$_POST为例，首先以 **auto_global_record** 数组形式定义好将要初始化的变量的相关信息。
在变量初始化完成后，按照PG(variables_order)指定的顺序（在php.ini中指定），通过调用sapi_module.treat_data处理数据。


>从PHP实现的架构设计看，treat_data函数在SAPI目录下不同的服务器应该有不同的实现，只是现在大部分都是使用的默认实现。

在treat_data后，如果打开了PG(register_globals)，则会调用php_autoglobal_merge将相关变量的值写到符号表。


以上的所有数据处理是一个赋值前的初始化行为。在此之后，通过遍历之前定义的结构体，
调用zend_hash_update，将相关变量的值赋值给&EG(symbol_table)。
另外对于$_REQUEST有独立的处理方法。

以文件上传中获取文件的信息为例（假设在Apache服务器环境下）：我们首先创建一个静态页面test.html，其内容如下所示：

    [html]
    <form name="upload" action="upload_test.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" value="1024" name="MAX_FILE_SIZE" />
        请选择文件:<input name="ufile" type="file" />
        <input type="submit" value="提 交" />
    </form>

当我们在页面中选择点击提交按钮时，浏览器会将数据提交给服务器。通过Filddle我们可以看到其提交的请求头如下：

    [shell]
    POST http://localhost/test/upload_test.php HTTP/1.1
    Host: localhost
    Connection: keep-alive
    Content-Length: 1347
    Cache-Control: max-age=0
    Origin: http://localhost
    User-Agent: //省略若干
    Content-Type: multipart/form-data; boundary=----WebKitFormBoundaryBq7AMhcljN14rJrU 

    // 上面的是关键
    Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8
    Referer: http://localhost/test/test.html
    Accept-Encoding: gzip,deflate,sdch
    Accept-Language: zh-CN,zh;q=0.8
    Accept-Charset: GBK,utf-8;q=0.7,*;q=0.3

    // 以下为POST提交的内容

    ------WebKitFormBoundaryBq7AMhcljN14rJrU
    Content-Disposition: form-data; name="MAX_FILE_SIZE"

    10240
    ------WebKitFormBoundaryBq7AMhcljN14rJrU
    Content-Disposition: form-data; name="ufile"; filename="logo.png"
    Content-Type: image/png //这里就是我们想要的文件类型

    //以下为文件内容

如果我们在upload_test.php文件中打印$_FILES，可以看到上传文件类型为image/png。
对应上面的请求头，image/png在文件内容输出的前面的Content-Type字段中。
基本上我们知道了上传的文件类型是浏览器自己识别，直接以文件的Content-Type字段传递给服务器。
如果有多个文件上传，就会有多个boundary分隔文件内容，形成多个POST内容块。
那么这些内容在PHP中是如何解析的呢？

当客户端发起文件提交请求时，Apache会将所接收到的内容转交给mod_php5模块。
当PHP接收到请求后，首先会调用sapi_activate，在此函数中程序会根据请求的方法处理数据，如示例中POST方法，其调用过程如下：

    [c]
    if(!strcmp(SG(request_info).request_method, "POST")
    && (SG(request_info).content_type)) {
        /* HTTP POST -> may contain form data to be read into variables
        depending on content type given
        */
        sapi_read_post_data(TSRMLS_C);
    }

sapi_read_post_data在main/SAPI.c中实现，它会根据POST内容的Content-Type类型来选择处理POST内容的方法。

    [c]
    if (zend_hash_find(&SG(known_post_content_types), content_type,
    content_type_length+1, (void **) &post_entry) == SUCCESS) {
        /* found one, register it for use */
        SG(request_info).post_entry = post_entry;
        post_reader_func = post_entry->post_reader;
    }

以上代码的关键在于SG(known_post_content_types)变量，
此变更是在SAPI启动时初始化全局变量时被一起初始化的，其基本过程如下：

    [shell]
    sapi_startup
    sapi_globals_ctor(&sapi_globals);
    php_setup_sapi_content_types(TSRMLS_C);
    sapi_register_post_entries(php_post_entries TSRMLS_CC);

这里的的php_post_entries定义在main/php_content_types.c文件。如下：

    [c]
    /* {{{ php_post_entries[]
    */
    static sapi_post_entry php_post_entries[] = {
    { DEFAULT_POST_CONTENT_TYPE, sizeof(DEFAULT_POST_CONTENT_TYPE)-1, sapi_read_standard_form_data, php_std_post_handler },
    { MULTIPART_CONTENT_TYPE, sizeof(MULTIPART_CONTENT_TYPE)-1, NULL, rfc1867_post_handler },
    { NULL, 0, NULL, NULL }
    };
    /* }}} */

    #define MULTIPART_CONTENT_TYPE "multipart/form-data"

    #define DEFAULT_POST_CONTENT_TYPE "application/x-www-form-urlencoded"

如上所示的MULTIPART_CONTENT_TYPE（multipart/form-data）所对应的rfc1867_post_handler方法就是处理$_FILES的核心函数，
其定义在main/rfc1867.c文件：SAPI_API SAPI_POST_HANDLER_FUNC(rfc1867_post_handler)
后面获取Content-Type的过程就比较简单了：

* 通过multipart_buffer_eof控制循环，遍历所有的multipart部分
* 通过multipart_buffer_headers获取multipart部分的头部信息
* 通过php_mime_get_hdr_value(header, “Content-Type”)获取类型
* 通过register_http_post_files_variable(lbuf, cd, http_post_files, 0 TSRMLS_CC);
将数据写到$_FILES变量。

main/rfc1867.c

    [c]

    SAPI_API SAPI_POST_HANDLER_FUNC(rfc1867_post_handler)
    {

    //若干省略
        while (!multipart_buffer_eof(mbuff TSRMLS_CC)){
            if (!multipart_buffer_headers(mbuff, &header TSRMLS_CC)) {
            goto fileupload_done;
        }
    //若干省略
        /* Possible Content-Type: */
        if (cancel_upload || !(cd = php_mime_get_hdr_value(header, "Content-Type"))) {
            cd = "";
        } else { 
        /* fix for Opera 6.01 */
            s = strchr(cd, ';');
            if (s != NULL) {
                *s = '\0';
            }
        }
    //若干省略
        /* Add $foo[type] */
        if (is_arr_upload) {
                snprintf(lbuf, llen, "%s[type][%s]", abuf, array_index);
        } else {
            snprintf(lbuf, llen, "%s[type]", param);
        }
        register_http_post_files_variable(lbuf, cd, http_post_files, 0 TSRMLS_CC);
        //若干省略
        }
    }

其它的$_FILES中的size、name等字段，其实现过程与type类似。
