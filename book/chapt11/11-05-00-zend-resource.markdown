# PHP7 使用资源包裹第三方扩展

我们分为两大块：
首先实现一个自定义的文件打开、读取、写入、关闭的文件操作扩展；
然后分析各个操作背后的实现原理，其中某些部分的实现会和PHP 5.3 使用资源对比分析。

## 通过原型生成扩展骨架

首先进入到源码目录的`ext`目录中，添加一个文件操作的原型文件

    [shell]
    [root@localhost php-src-php-7.0.3]# cd ext/
    [root@localhost ext]# vim tipi_file.proto
    
编辑为

    [shell]
    resource file_open(string filename, string mode)
    string file_read(resource filehandle, int size)
    bool file_write(resource filehandle, string buffer)
    bool file_close(resource filehandle)
    
然后生成骨架，这些前面都说过，我们不再详细说

    [shell]
    [root@localhost ext]# ./ext_skel --extname=tipi_file --proto=./tipi_file.proto
    
完整的代码 [tipi_file.c](https://github.com/reeze/tipi/tree/master/book/sample/chapt11/11-05-00-zend-resource/tipi_file.c) 可以先有一个大致的了解，这样后面阅读时，思路可能会清晰很多。

## 注册资源类型

### 认识注册资源类型API

    [c]
    ZEND_API int zend_register_list_destructors_ex(rsrc_dtor_func_t ld, rsrc_dtor_func_t pld, const char *type_name, int module_number)
    
参数        	  |描述
--------------|------------------------------------------------
ld	          |释放该资源时调用的函数。
pld           |释放用于在不同请求中始终存在的永久资源的函数。
type_name     |描述性类型名称的字符串别名。
module_number |为引擎内部使用，已经定义好了，比如在PHP_FUNCTION宏中已定义

该 API 返回一个资源类型 id，该 id 应当被作为全局变量保存在扩展里，以便在必要的时候传递给其他资源 API。

### 添加资源释放回调函数

该方法表示在释放该类型资源时都需要关闭打开的文件描述符。

    [c]
    static void tipi_file_dtor(zend_resource *rsrc TSRMLS_DC){
         FILE *fp = (FILE *) rsrc->ptr;
         fclose(fp);
    }

我们发现该函数的参数类型是 `zend_resource` 。这是 PHP7 新增的数据结构，在 PHP 5 则是 `zend_rsrc_list_entry` 。细节的内容，我们留在后面分析。

### 在 PHP_MINIT_FUNCTION 中注册资源类型

我们知道在 PHP 生命周期中，当 PHP 被装载时，`PHP_MINIT_FUNCTION`（模块启动函数）即被引擎调用。
这使得引擎做一些例如资源类型，注册INI变量等的一次初始化。
那么我们需要在这里通过 `zend_register_list_destructors_ex`在 `PHP_MINIT_FUNCTION`来注册资源类型。

    [c]
    PHP_MINIT_FUNCTION(tipi_file)
    {
        /* If you have INI entries, uncomment these lines
        REGISTER_INI_ENTRIES();
        */
     
        le_tipi_file = zend_register_list_destructors_ex(tipi_file_dtor, NULL, TIPI_FILE_TYPE, module_number);
        return SUCCESS;
    }
    
> **NOTICE** 其中 `TIPI_FILE_TYPE` 在前面已经定义了，是该扩展的别名。具体请参考最该节最开始给予的完整代码样例。

## 注册资源

前面是注册了新的资源类型，然后需要注册一个该类型的资源。

### 注册资源 API

在 PHP 7 中删除了原来的 `ZEND_REGISTER_RESOURCE` 宏，直接使用 `zend_register_resource` 函数

    [c]
    ZEND_API zend_resource* zend_register_resource(void *rsrc_pointer, int rsrc_type)

参数        	  |描述
--------------|------------------------------------------------
rsrc_pointer  |资源数据指针
rsrc_type	  |注册资源类型时获得的资源类型 id

### 在自定义的 file_open 函数中实现资源的注册

    [c]
    PHP_FUNCTION(file_open)
    {
        char *filename = NULL;
        char *mode = NULL;
        int argc = ZEND_NUM_ARGS();
        size_t filename_len;
        size_t mode_len;
    
    #ifndef FAST_ZPP
        if (zend_parse_parameters(argc TSRMLS_CC, "ss", &filename, &filename_len, &mode, &mode_len) == FAILURE) 
            return;
    #else
        ZEND_PARSE_PARAMETERS_START(2, 2)
             Z_PARAM_STRING(filename, filename_len)
             Z_PARAM_STRING(mode, mode_len)
        ZEND_PARSE_PARAMETERS_END();
    #endif
    
        // 使用 VCWD 宏取代标准 C 文件操作函数
        FILE *fp = VCWD_FOPEN(filename, mode);
     
        if (fp == NULL) {
            RETURN_FALSE;
        }
     
        RETURN_RES(zend_register_resource(fp, le_tipi_file));
    }

其中 `RETURN_RES` 宏的作用是将返回的 `zend_resource`添加到 `zval` 中，然后将最后的 `zval` 作为返回值。也就是说该函数的返回值为`zval` 指针。`RETURN_RES(zend_register_resource(fp, le_tipi_file))` 会将返回值的 `value.res` 设为 `fp`，`u1.type_info` 设为 `IS_RESOURCE_EX` 。大家可以根据源码非常直观的了解到，这里不粘贴代码详细说明了。

## 使用资源

### 使用资源API

在 PHP 7 中删除了原有的 `ZEND_FETCH_RESOURCE` 宏，直接使用函数 `zend_fetch_resource` ，而且解析方式也变得简单了很多，相比 PHP 5 要高效很多，后面我们再通过绘图的形式分析对比。

    [c]
    ZEND_API void *zend_fetch_resource(zend_resource *res, const char *resource_type_name, int resource_type)
    
参数        	        |描述
--------------------|------------------------------------------------
res	                |资源指针
resource_type_name	|该类资源的字符串别名
resource_type       |该类资源的类型 id

### 解析资源的实现

当我们要实现文件的读取时，最终还是需要使用原生的 `fread` 函数，所以这里需要通过 `zend_fetch_resource` 将 `zend_resource` 解析成为该资源包裹的原始的 `FILE *` 的指针。

    [c]
    PHP_FUNCTION(file_read)
    {
        int argc = ZEND_NUM_ARGS();
        int filehandle_id = -1;
        zend_long size;
        zval *filehandle = NULL;
        FILE *fp = NULL;
        char *result;
        size_t bytes_read;
    
    #ifndef FAST_ZPP
        if (zend_parse_parameters(argc TSRMLS_CC, "rl", &filehandle, &size) == FAILURE) 
            return;
    #else
        ZEND_PARSE_PARAMETERS_START(2, 2)
             Z_PARAM_RESOURCE(filehandle)
             Z_PARAM_LONG(size)
        ZEND_PARSE_PARAMETERS_END();
    #endif
    
        if ((fp = (FILE *)zend_fetch_resource(Z_RES_P(filehandle), TIPI_FILE_TYPE, le_tipi_file)) == NULL) {
            RETURN_FALSE;
        }
     
        result = (char *) emalloc(size+1);
        bytes_read = fread(result, 1, size, fp);
        result[bytes_read] = '\0';
     
        RETURN_STRING(result, 0);
    }

这里需要说明，脚本自动生成的扩展代码中还是使用 `ZEND_FETCH_RESOURCE`， 是个 BUG，因为自动生成的脚本（ext/skeleton/create_stubs）还没更新。

与之类似的文件的写入操作，也很类似，这里就不复制代码了。

## 资源的删除

### 资源删除 API

    [c]
    ZEND_API int zend_list_close(zend_resource *res)

传入需要被删除的资源即可。该 API 看似非常简单，实际做了很多工作，后面原理分析细说。

### 资源删除的实现

我们在函数 `file_close` 中需要调用资源删除 API

    [c]
    PHP_FUNCTION(file_close)
    {
        int argc = ZEND_NUM_ARGS();
        int filehandle_id = -1;
        zval *filehandle = NULL;
        
    #ifndef FAST_ZPP 
        if (zend_parse_parameters(argc TSRMLS_CC, "r", &filehandle) == FAILURE) 
            return;
    #else
        ZEND_PARSE_PARAMETERS_START(1, 1)
             Z_PARAM_RESOURCE(filehandle)
        ZEND_PARSE_PARAMETERS_END();
    #endif 
        zend_list_close(Z_RES_P(filehandle));
        RETURN_TRUE;
    }
    
## 编译安装以及测试

关于编译的代码请参考本章的第一节，这里不再说明，我们说下测试环节。直接用 php 脚本测试，就不一个功能一个功能写测试样例了，修改 `tipi_file.php`文件。

    [php]
    $fp = file_open("./CREDITS","r+");
    var_dump($fp);
    var_dump(file_read($fp,6));
    var_dump(file_write($fp,"zhoumengakng"));
    var_dump(file_close($fp));
    
然后通过命令行执行

    [shell]
    php7 -d"extension=tipi_file.so" tipi_file.php
    
