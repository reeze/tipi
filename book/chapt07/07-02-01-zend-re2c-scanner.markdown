# 词法解析

在前面我们提到语言转化的编译过程一般分为词法分析、语法分析、语义分析、中间代码生成、代码优化、目标代码生成等六个阶段。
不管是编译型语言还是解释型语言，扫描（词法分析）总是将程序转化成目标语言的第一步。
词法分析的作用就是将整个源程序分解成一个一个的单词，
这样做可以在一定程度上减少后面分析工作需要处理的个体数量，为语法分析等做准备。
除了拆分工作，更多的时候它还承担着清洗源程序的过程，比如清除空格，清除注释等。
词法分析作为编译过程的第一步，在业界已经有多种成熟工具，如PHP在开始使用的是Flex，之后改为re2c，
MySQL的词法分析使用的Flex，除此之外还有作为UNIX系统标准词法分析器的Lex等。
这些工具都会读进一个代表词法分析器规则的输入字符串流，然后输出以C语言实做的词法分析器源代码。
这里我们只介绍PHP的现版词法分析器，re2c。

[re2c](http://www.re2c.org/)是一个扫描器制作工具，可以创建非常快速灵活的扫描器。
它可以产生高效代码，基于C语言，可以支持C/C++代码。与其它类似的扫描器不同，
它偏重于为正则表达式产生高效代码（和他的名字一样）。因此，这比传统的词法分析器有更广泛的应用范围。
你可以在[sourceforge.net](http://sourceforge.net/projects/re2c/)获取源码。

在源码目录下的Zend/zend_language_scanner.l 文件是re2c的规则文件，
如果需要修改该规则文件需要安装re2c才能重新编译，生成新的规则文件。

re2c调用方式：

    re2c [-bdefFghisuvVw1] [-o output] [-c [-t header]] file

我们通过一个简单的例子来看下re2c。如下是一个简单的扫描器，它的作用是判断所给的字符串是数字/小写字母/大写字母。
当然，这里没有做一些输入错误判断等异常操作处理。示例如下：

    [c]
    #include <stdio.h>

    char *scan(char *p){
    #define YYCTYPE char
    #define YYCURSOR p
    #define YYLIMIT p
    #define YYMARKER q
    #define YYFILL(n)
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

我们回过头来看PHP的词法规则文件zend_language_scanner.l。
你会发现前面的简单示例与它最大的区别在于每个规则前面都会有一个条件表达式。

>NOTE
re2c中条件表达式相关的宏为YYSETCONDITION和YYGETCONDITION，分别表示设置条件范围和获取条件范围。
在PHP的词法规则中共有10种，其全部在zend_language_scanner_def.h文件中。此文件并非手写，
而是re2c自动生成的。如果需要生成和使用条件表达式，在编译成c时需要添加-c 和-t参数。

在PHP的词法解析中，它有一个全局变量:language_scanner_globals，此变量为一结构体，记录当前re2c解析的状态，文件信息，解析过程信息等。
它在zend_language_scanner.l文件中直接定义如下：

    [c]
	#ifdef ZTS
    ZEND_API ts_rsrc_id language_scanner_globals_id;
    #else
    ZEND_API zend_php_scanner_globals language_scanner_globals;
    #endif

在zend_language_scanner.l文件中写的C代码在使用re2c生成C代码时会直接复制到新生成的C代码文件中。
这个变量贯穿了PHP词法解析的全过程，并且一些re2c的实现也依赖于此，
比如前面说到的条件表达式的存储及获取，就需要此变量的协助，我们看这两个宏在PHP词法中的定义：

    [c]
    //	存在于zend_language_scanner.l文件中
    #define YYGETCONDITION()  SCNG(yy_state)
    #define YYSETCONDITION(s) SCNG(yy_state) = s
    #define SCNG    LANG_SCNG

    //	存在于zend_globals_macros.h文件中
    # define LANG_SCNG(v) (language_scanner_globals.v)

结合前面的全局变量和条件表达式宏的定义，我们可以知道PHP的词法解析是通过全局变量在一次解析过程中存在。
那么这个条件表达式具体是怎么使用的呢？我们看下面一个例子。这是一个可以识别<?php为开始，?>为结束，
识别字符，数字等的简单字符串识别器。它使用了re2c的条件表达式，代码如下：

    [c]
    #include <stdio.h>
    #include "demo_def.h"
    #include "demo.h"

    Scanner scanner_globals;

    #define YYCTYPE char
    #define YYFILL(n) 
    #define STATE(name)  yyc##name
    #define BEGIN(state) YYSETCONDITION(STATE(state))
    #define LANG_SCNG(v) (scanner_globals.v)
    #define SCNG    LANG_SCNG

    #define YYGETCONDITION()  SCNG(yy_state)
    #define YYSETCONDITION(s) SCNG(yy_state) = s
    #define YYCURSOR  SCNG(yy_cursor)
    #define YYLIMIT   SCNG(yy_limit)
    #define YYMARKER  SCNG(yy_marker)

    int scan(){
        /*!re2c

          <INITIAL>"<?php" {BEGIN(ST_IN_SCRIPTING); return T_BEGIN;}
          <ST_IN_SCRIPTING>[0-9]+ {return T_NUMBER;}
          <ST_IN_SCRIPTING>[ \n\t\r]+ {return T_WHITESPACE;}
          <ST_IN_SCRIPTING>"exit" { return T_EXIT; }
          <ST_IN_SCRIPTING>[a-z]+ {return T_LOWER_CHAR;}
          <ST_IN_SCRIPTING>[A-Z]+ {return T_UPPER_CHAR;}
          <ST_IN_SCRIPTING>"?>" {return T_END;}

          <ST_IN_SCRIPTING>[^] {return T_UNKNOWN;}
          <*>[^] {return T_INPUT_ERROR;}
         */
    }

    void print_token(int token) {
        switch (token) {
            case T_BEGIN: printf("%s\n", "begin");break;
            case T_NUMBER: printf("%s\n", "number");break;
            case T_LOWER_CHAR: printf("%s\n", "lower char");break;
            case T_UPPER_CHAR: printf("%s\n", "upper char");break;
            case T_EXIT: printf("%s\n", "exit");break;
            case T_UNKNOWN: printf("%s\n", "unknown");break;
            case T_INPUT_ERROR: printf("%s\n", "input error");break;
            case T_END: printf("%s\n", "end");break;
        }
    }

    int main(int argc, char* argv[])
    {
        int token;
        BEGIN(INITIAL);	//	全局初始化，需要放在scan调用之前
        scanner_globals.yy_cursor = argv[1];	//将输入的第一个参数作为要解析的字符串

        while(token = scan()) {
            if (token == T_INPUT_ERROR) {
                printf("%s\n", "input error");
                break;
            }
            if (token == T_END) {
                printf("%s\n", "end");
                break;
            }
            print_token(token);
        }

        return 0;
    }

和前面的简单示例一样，如果你是在linux环境下，可以使用如下命令生成可执行文件

    [shell]
    re2c -o demo.c -c -t demo_def.h demo.l
    gcc demo.c -o demo -g
    chmod +x demo

在使用re2c生成C代码时我们使用了-c -t demo_def.h参数，这表示我们使用了条件表达式模式，生成条件的定义头文件。
main函数中，在调用scan函数之前我们需要初始化条件状态，将其设置为INITIAL状态。
然后在扫描过程中会直接识别出INITIAL状态，然后匹配<?php字符串识别为开始，如果开始不为<?php，则输出input error。
在扫描的正常流程中，当扫描出<?php后，while循环继续向下走，此时会再次调用scan函数，当前条件状态为ST_IN_SCRIPTING，
此时会跳过INITIAL状态，直接匹配<ST_IN_SCRIPTING>状态后的规则。如果所有的<ST_IN_SCRIPTING>后的规则都无法匹配，输出unkwon。
这只是一个简单的识别示例，但是它是从PHP的词法扫描器中抽离出来的，其实现过程和原理类似。

那么这种条件状态是如何实现的呢？我们查看demo.c文件，发现在scan函数开始后有一个跳转语句：


    [c]
    int scan(){

    #line 25 "demo.c"
    {
        YYCTYPE yych;
        switch (YYGETCONDITION()) {
        case yycINITIAL: goto yyc_INITIAL;
        case yycST_IN_SCRIPTING: goto yyc_ST_IN_SCRIPTING;
        }
    ...
    }

在zend_language_scanner.c文件的lex_scan函数中也有类型的跳转过程，只是过程相对这里来说if语句多一些，复杂一些。
这就是re2c条件表达式的实现原理。



