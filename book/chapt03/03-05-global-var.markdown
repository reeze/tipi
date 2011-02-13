# 第五节 预定义变量和global语句

大家都知道PHP脚本在执行的时候用户变量(在用户空间显式定义的变量)会保存在一个HashTable数据类型的符号表(symbol_table)中, 在PHP中有一些比较特殊的全局变量例如:
$\_GET, $\_POST, $\_SERVER等这些变量, 我们自己并没有定义这样的一些变量, 那这些变量是从何而来的呢? 既然变量是保存在符号表中, 那PHP应该是在脚本运行之前就将这些
特殊的变量加入到了符号表中了吧? 事实就是这样.

## 预定义变量$GLOBALS的初始化
***
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


## $_GET、$_POST等变量的初始化
***
**$_GET、$_COOKIE、$_SERVER、$_ENV、$_FILES、$_REQUEST**这六个变量都是通过如下的调用序列进行初始化。
**[main() -> php_request_startup() -> php_hash_environment()  ]**  
在请求初始化时，通过调用 **php_hash_environment** 函数初始化以上的六个预定义的变量。如下所示为php_hash_environment函数的代码。在代码之后我们以$_POST为例说明整个初始化的过程。

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

以$_POST为例，首先以 **auto_global_record** 初始化在后面将要用到的名称等。
在变量初始化完成后，按照PG(variables_order)指定的顺序（在php.ini中指定），通过调用sapi_module.treat_data处理数据。
(从PHP实现的架构设计看，treat_data函数在SAPI目录下不同的服务器应该有不同的实现，只是现在大部分都是使用的默认实现。)
在treat_data后，如果打开了PG(register_globals)，则会调用php_autoglobal_merge将相关变量的值写到符号表。


以上的所有数据处理是一个赋值前的初始化行为。在此之后，通过遍历之前定义的结构体，调用zend_hash_update，将相关变量的值赋值给&EG(symbol_table)。
另外对于$_REQUEST有独立的处理方法。



## global语句
global语句的作用是定义全局变量, 例如如果想在函数内访问全局作用域内的变量则可以通过global声明来定义. 那global是怎么实现跨作用域的变量访问的呢?
下面从语法解释开始分析.
***
**1. 词法解析**

查看 Zend/zend_language_scanner.l文件，搜索 global关键字。我们可以找到如下代码：

    [c]
    <ST_IN_SCRIPTING>"global" {
	return T_GLOBAL;
    }

**2. 语法解析**

在词法解析完后，获得了token,此时通过这个token，我们去Zend/zend_language_parser.y文件中查找。找到相关代码如下：

    [c]
    |	T_GLOBAL global_var_list ';'

    global_var_list:
		global_var_list ',' global_var	{ zend_do_fetch_global_variable(&$3, NULL, ZEND_FETCH_GLOBAL_LOCK TSRMLS_CC); }
	|	global_var						{ zend_do_fetch_global_variable(&$1, NULL, ZEND_FETCH_GLOBAL_LOCK TSRMLS_CC); }
    ;

上面代码中的**$3**是指global_var（如果不清楚yacc的语法，可以查阅yacc入门类的文章。）

从上面的代码可以知道，对于全局变量的声明调用的是zend_do_fetch_global_variable函数，查找此函数的实现在Zend/zend_compile.c文件。

    [c]
    void zend_do_fetch_global_variable(znode *varname, const znode *static_assignment, int fetch_type TSRMLS_DC) /* {{{ */
    {
            ...//省略
            opline->opcode = ZEND_FETCH_W;		/* the default mode must be Write, since fetch_simple_variable() is used to define function arguments */
            opline->result.op_type = IS_VAR;
            opline->result.u.EA.type = 0;
            opline->result.u.var = get_temporary_variable(CG(active_op_array));
            opline->op1 = *varname;
            SET_UNUSED(opline->op2);
            opline->op2.u.EA.type = fetch_type;
            result = opline->result;

            ... // 省略
            fetch_simple_variable(&lval, varname, 0 TSRMLS_CC); /* Relies on the fact that the default fetch is BP_VAR_W */

            zend_do_assign_ref(NULL, &lval, &result TSRMLS_CC);
            CG(active_op_array)->opcodes[CG(active_op_array)->last-1].result.u.EA.type |= EXT_TYPE_UNUSED;
    }
    /* }}} */

上面的代码确认了opcode为ZEND_FETCH_W外，还执行了zend_do_assign_ref函数。zend_do_assign_ref函数的实现如下：

    [c]
    void zend_do_assign_ref(znode *result, const znode *lvar, const znode *rvar TSRMLS_DC) /* {{{ */
    {
            zend_op *opline;

           ... //省略

            opline = get_next_op(CG(active_op_array) TSRMLS_CC);
            opline->opcode = ZEND_ASSIGN_REF;
           ...//省略
            if (result) {
                    opline->result.op_type = IS_VAR;
                    opline->result.u.EA.type = 0;
                    opline->result.u.var = get_temporary_variable(CG(active_op_array));
                    *result = opline->result;
            } else {
                    /* SET_UNUSED(opline->result); */
                    opline->result.u.EA.type |= EXT_TYPE_UNUSED;
            }
            opline->op1 = *lvar;
            opline->op2 = *rvar;
    }

从上面的zend_do_fetch_global_variable函数和zend_do_assign_ref函数的实现可以看出，使用global声明一个全局变量后，其执行了两步操作，ZEND_FETCH_W和ZEND_ASSIGN_REF。

**3. 生成并执行中间代码**

我们看下ZEND_FETCH_W的最后执行。从代码中我们可以知道：

* ZEND_FETCH_W = 83
* op->op1.op_type = 4
* op->op2.op_type = 0

而计算最后调用的方法在代码中的体现为：

    [c]
    zend_opcode_handlers[opcode * 25 + zend_vm_decode[op->op1.op_type] * 5 + zend_vm_decode[op->op2.op_type]];

计算，最后调用ZEND_FETCH_W_SPEC_CV_HANDLER函数。即

    [c]
    static int ZEND_FASTCALL  ZEND_FETCH_W_SPEC_CV_HANDLER(ZEND_OPCODE_HANDLER_ARGS)
    {
            return zend_fetch_var_address_helper_SPEC_CV(BP_VAR_W, ZEND_OPCODE_HANDLER_ARGS_PASSTHRU);
    }

在zend_fetch_var_address_helper_SPEC_CV中调用如下代码获取符号表

    [c]
    target_symbol_table = zend_get_target_symbol_table(opline, EX(Ts), type, varname TSRMLS_CC);

在zend_get_target_symbol_table函数的实现如下：

    [c]
    static inline HashTable *zend_get_target_symbol_table(const zend_op *opline, const temp_variable *Ts, int type, const zval *variable TSRMLS_DC)
    {
            switch (opline->op2.u.EA.type) {
                    ... //  省略
                    case ZEND_FETCH_GLOBAL:
                    case ZEND_FETCH_GLOBAL_LOCK:
                            return &EG(symbol_table);
                            break;
                   ...  //  省略
            }
            return NULL;
    }

在前面语法分析过程中，程序传递的参数是 ZEND_FETCH_GLOBAL_LOCK，于是如上所示。我们取&EG(symbol_table);的值。这也是全局变量的存放位置。

如上就是整个global的解析过程。
