# 第二节 创建第一个扩展

PHP7 已经发了很久，所以本章以讲解 PHP7 扩展开发为主，附带对比说明 PHP7 和 PHP5 中的一些区别。

每小节分为两大部分，一部分为实现，一部分为实现原理，读者可以先着眼实现，后期再反观原理，以防看得太过于枯燥而削弱阅读的积极性。

悉知本章开发环境默认为 Linux 环境，后文不再特殊说明。

## 安装 PHP7

在 [Github](https://github.com/php/php-src/releases) 下载的最新版，没安装太多的扩展，因为我仅仅是作为开发调试使用。
为了和本机的 PHP5 环境不冲突，便使用了新如下命令，后面的讲解中，默认使用该环境。解压安装包，进入到安装包目录。

    [shell]
    ./buildconf --force
    ...
    ./configure --prefix=/usr/local/php7 --with-config-file-path=/usr/local/php7/etc --enable-fpm --with-fpm-user=www --enable-debug
    ...
    
添加一些软连接

    [shell]
    ln -s /usr/local/php7/bin/php /usr/bin/php7
    ln -s /usr/local/php7/bin/php-config /usr/bin/php7-config 
    ln -s /usr/local/php7/bin/phpize /usr/bin/php7ize
    ln -s /usr/local/php7/sbin/php-fpm /usr/sbin/php7-fpm
    
拷贝配置文件

    [shell]
    cp php.ini-production /usr/local/php7/etc/php.ini
    cp sapi/fpm/init.d.php-fpm /etc/init.d/php7-fpm
    chmod +x /etc/init.d/php7-fpm
    cp /usr/local/php7/etc/php-fpm.conf.default /usr/local/php7/etc/php-fpm.conf
    cp /usr/local/php7/etc/php-fpm.d/www.conf.default /usr/local/php7/etc/php-fpm.d/www.conf
    
## 创建第一个扩展

创建第一个扩展的步骤，可能很多文章都有说明，如果觉得讲解得不够详细的，可以参考本节最后附予一些参考链接。

### 1. 编写原型文件 

PHP 源代码目录中提供了一个可执行文件 `ext/ext_skel`，该文件可根据指定的原型文件生成扩展代码骨架。
原型文件有点类似 C 头文件，根据其中申明的函数，生成函数骨架代码和其他相关代码。

那么如何编写原型文件呢？

比如现编写一个 tipi_hello_world 的函数，输入一个字符串 `name` ，返回 `hello world, name`。

现已有一个原型文件：`ext/tipi.proto`，内容为

    string tipi_hello_world (string name)
    
原型文件的格式，类似于 C 头文件中的函数申明的方式，返回值、函数名、形参类型、形参名。
参数用 `()` 包裹，多个参数以 `,` 分隔，函数申明末尾不需要以 `;` 结尾，一行一个函数声明。
原型文件的参数执行哪些类型呢？在后面的练习中，我们都会运用该方式生成代码骨架，所以了解参数类型很有必要。

原型文件的生成依赖于 awk 脚本 `ext/skeleton/create_stubs` ，由其中 `convert` 函数可知，其支持的参数类型有

- int,long
- bool,boolean
- double,float
- string
- array,object,mixed
- resource,handle

### 2. 生成扩展骨架

我们将扩展命令为 `tipi_demo01`，使用如下命令生成扩展骨架

    [shell]
    ./ext_skel --extname=tipi_demo01 --proto=tipi.proto
    
`ext_skel` 的完整介绍，可以参考官方的 [ext_skel 脚本](http://php.net/manual/zh/internals2.buildsys.skeleton.php) 说明。
执行完毕之后，会在 `ext/` 目录生成一个 `tipi_demo01` 的目录，进入该目录，列表如下

    [shell]
    [root@localhost tipi_demo01]# ll -al
    total 44
    drwxr-xr-x  3 root root 4096 Mar 13 23:13 .
    drwxr-xr-x 79 1000 1000 4096 Mar 13 23:13 ..
    -rw-r--r--  1 root root 2248 Mar 13 23:13 config.m4
    -rw-r--r--  1 root root  390 Mar 13 23:13 config.w32
    -rw-r--r--  1 root root   11 Mar 13 23:13 CREDITS
    -rw-r--r--  1 root root    0 Mar 13 23:13 EXPERIMENTAL
    -rw-r--r--  1 root root  398 Mar 13 23:13 .gitignore
    -rw-r--r--  1 root root 2397 Mar 13 23:13 php_tipi_demo01.h
    drwxr-xr-x  2 root root 4096 Mar 13 23:13 tests
    -rw-r--r--  1 root root 5725 Mar 13 23:13 tipi_demo01.c
    -rw-r--r--  1 root root  517 Mar 13 23:13 tipi_demo01.php

目录文件的完整描述可以参考官方的 [组成扩展的文件](http://php.net/manual/zh/internals2.structure.files.php) 说明。
其中 `config.m4` 作为 UNIX 构建系统配置文件，指导 `phpize` 命令生成 `./configure` 脚本以及其他一系列文件。（其实 `phpize` 也是对 `buildconf` 的封装）
补充说明 `test` 目录为单元测试脚本存放目录，`make test` 的时候会使用。其语法可参考 [phpt 测试文件说明](/book/?p=E-phpt-file)

### 3. 完善扩展

现在已经生成的扩展骨架代码，由于本例比较简单，我们只需完善我们申明的函数 `tipi_hello_world` 即可。在 `tipi_demo01.c` 中找到

    [c]
    PHP_FUNCTION(tipi_hello_world)
    {
    	char *name = NULL;
    	int argc = ZEND_NUM_ARGS();
    	size_t name_len;
    
    	if (zend_parse_parameters(argc TSRMLS_CC, "s", &name, &name_len) == FAILURE) 
    		return;
    
    	php_error(E_WARNING, "tipi_hello_world: not yet implemented");
    }

这就是 `tipi_hello_world` 在扩展中书写方式。
我们将其改写为

    [c]
    PHP_FUNCTION(tipi_hello_world)
    {
    	char *name = NULL;
    	int argc = ZEND_NUM_ARGS();
    	size_t name_len;
    
    	char *result = NULL;
    	char *prefix = "hello world, ";
    
    
    	if (zend_parse_parameters(argc TSRMLS_CC, "s", &name, &name_len) == FAILURE) 
    		return;
    
    	result = (char *) ecalloc(strlen(prefix) + name_len + 1, sizeof(char));
    	strncat(result, prefix, strlen(prefix));
    	strncat(result, name, name_len);
    
    	RETURN_STRING(result);
    }

其中 `zend_parse_parameters` 从我们在原型中定义的参数 `name` 中获取传入的字符串，分配内存之后保存在指针 `*name`中， `name_len` 为传入字符串的长度。
这里通过 `zend_parse_parameters` 来解析获取函数传入的参数，[zend_parse_parameters](/book/?p=chapt11/11-02-01-zend-parse-parameters) 的工作原理我们在下一小节中详细讲解。
值得一提的是在 PHP7 中，官方新增了 Fast Parameter Parsing API ，能够更高效的解析参数，并且可读性更强。具体的使用，我们同样也会会在下节中详细说明。
其中通过 `ecalloc` 来申请内存，具体可以参考本书的第六章的 [PHP中的内存管理](/book/?p=chapt06/06-02-php-memory-manager)。

### 4. 编译安装

首先修改 `config.m4` ，去掉 `PHP_ARG_WITH` 和 `--with-tipi_demo01` 这两行前面的 `dnl` 注释。修改后如下

    [shell]
    PHP_ARG_WITH(tipi_demo01, for tipi_demo01 support,
    dnl Make sure that the comment is aligned:
    [  --with-tipi_demo01             Include tipi_demo01 support])


在扩展目录（`ext/tipi_demo01/`）中，通过 `phpize` (我们在上面已经添加了软连接 `ln -s /usr/local/php7/bin/phpize /usr/bin/php7ize`) 生成 `configure` 系列文件。

    [shell]
    php7ize
    ...
    ./configure --with-php-config=/usr/local/php7/bin/php-config
    ...
    make && make install
        
### 5. 测试

由于扩展程序比较简单，我们直接使用命令行测试

    [shell]
    php7 -d "extension=tipi_demo01.so" -r "echo tipi_hello_world('tipi');"
    
输出了 `hello world, tipi`。至此，我们的第一个扩展就完成。
