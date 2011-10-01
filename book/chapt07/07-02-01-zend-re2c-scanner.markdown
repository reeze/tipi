# 词法解析

[re2c](http://www.re2c.org/)是一个扫描器制作工具，可以创建非常快速灵活的扫描器。它可以产生高效代码，基于C语言，可以支持C/C++代码。
与其它类似的扫描器不同，它偏重于为正则表达式产生高效代码（和他的名字一样）。因此，这比传统的词法分析器有更广泛的应用范围。
你可以在[sourceforge.net](http://sourceforge.net/projects/re2c/)获取源码。

PHP在最开始的词法解析器是使用的是flex，后来PHP的改为使用re2c。
在源码目录下的Zend/zend_language_scanner.l 文件是re2c的规则文件，
如果需要修改该规则文件需要安装re2c才能重新编译。

re2c调用方式：

    re2c [-bdefFghisuvVw1] [-o output] [-c [-t header]] file

我们通过一个简单的例子来看下re2c。如下是一个简单的扫描器，它的作用是判断所给的字符串是数字/小写字母/大小字母。
当然，这里没有做一些输入错误判断等异常操作处理。示例如下：

    [c]
    #include <stdio.h>

    char *scan(char *p){
    #define YYCTYPE char
    #define YYCURSOR p
    #define YYLIMIT p
    #define YYMARKER q
        /*!re2c
          [0-9]+ {return "number";}
          [a-z]+ {return "lower";}
          [A-Z]+ {return "upper";}
          [^] {return "unkown";}
         */
    }

    int main(int argc, char* argv[])
    {
        printf("%s\n", scan(argv[1]));

        return 0;
    }

如果你是在ubuntu环境下，可以执行下面的命令生成可执行文件。

    [shell]
    re2c -o a.c a.l
    gcc a.c -o a
    chmod +x a
    ./a 1000

此时程序会输出number。

我们解释一下我们用到的几个re2c约定的宏。

* YYCTYPE  用于保存输入符号的类型，通常为char型和unsigned char型 
* YYCURSOR 指向当前输入标记， -当开始时，它指向当前标记的第一个字符，当结束时，它指向下一个标记的第一个字符
* YYFILL(n) 当生成的代码需要重新加载缓存的标记时，则会调用YYFILL(n)。
* YYLIMIT 缓存的最后一个字符，生成的代码会反复比较YYCURSOR和YYLIMIT，以确定是否需要重新填充缓冲区。

参照如上几个标识的说明，可以较清楚的理解生成的a.c文件，当然，re2c不会仅仅只有上面代码所显示的标记，
这只是一个简单示例，更多的标识说明和帮助信息请移步 [re2c帮助文档](http://re2c.org/manual.html)：[http://re2c.org/manual.html](http://re2c.org/manual.html)。



