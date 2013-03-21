# 词法分析和语法分析

广义而言，语言是一套采用共同符号、表达方式与处理规则。就编程语言而言，
编程语言也是特定规则的符号，用来传达特定的信息，自然语言是人与人之间沟通的渠道，
而编程语言则是机器之间，人与机器之间的沟通渠道。人有非常复杂的语言能力，
语言本身也在不断的进化，人之间能够理解复杂的语言规则，而计算机并没有这么复杂的系统，
它们只能接受指令执行操作，编程语言则是机器和人(准确说是程序员)之间的桥梁，
编程语言的作用就是将语言的特定符号和处理规则进行翻译，由编程语言来处理这些规则，

目前有非常多的编程语言，不管是静态语言还是动态语言都有固定的工作需要做：
将代码编译为目标指令，而编译过程就是根据语言的语法规则来进行翻译，
我们可以选择手动对代码进行解析，但这是一个非常枯燥而容易出错的工作，
尤其是对于一个完备的编程语言而言，由此就出现了像lex/yacc这类的编译器生成器。

编程语言的编译器(compiler)或解释器(interpreter)一般包括两大部分：

1. 读取源程序，并处理语言结构。
1. 处理语言结构并生成目标程序。

Lex和Yacc可以解决第一个问题。
第一个部分也可以分为两个部分：

1. 将代码切分为一个个的标记(token)。
1. 处理程序的层级结构(hierarchical structure)。

很多编程语言都使用lex/yacc或他们的变体(flex/bison)来作为语言的词法语法分析生成器，
比如PHP、Ruby、Python以及MySQL的SQL语言实现。

Lex和Yacc是Unix下的两个文本处理工具， 主要用于编写编译器， 也可以做其他用途。

* Lex(词法分析生成器:A Lexical Analyzer Generator)。
* Yacc(Yet Another Compiler-Compiler)

### Lex/Flex
Lex读取词法规则文件，生成词法分析器。目前通常使用Flex以及Bison来完成同样的工作， 
Flex和lex之间并不兼容，Bison则是兼容Yacc的实现。

词法规则文件一般以.l作为扩展名，flex文件由三个部分组成，三部分之间用%%分割：

	[flex]
	定义段
	%%
	规则段
	%%
	用户代码段

例如以下一个用于统计文件字符、词以及行数的例子：

	[flex]
	%option noyywrap
	%{
	int chars = 0;
	int words = 0;
	int lines = 0;
	%}

	%%
	[a-zA-Z]+ { words++; chars += strlen(yytext); }
	\n	{ chars++; lines++; }
	.	{ chars++; }
	%%

	main(int argc, char **argv) 
	{
		if(argc > 1) {
			if(!(yyin = fopen(argv[1], "r"))) {
				perror(argv[1]);
				return (1);
			}
			yylex();
			printf("%8d%8d%8d\n", lines, words, chars);
		}
	}

该解释器读取文件内容， 根据规则段定义的规则进行处理， 规则后面大括号中包含的是动作， 也就是匹配到该规则程序执行的动作，
这个例子中的匹配动作时记录下文件的字符，词以及行数信息并打印出来。其中的规则使用正则表达式描述。

回到PHP的实现，PHP以前使用的是flex，[后来](http://blog.somabo.de/2008/02/php-on-re2c.html)PHP的词法解析改为使用[re2c](http://re2c.org/)，
$PHP_SRC/Zend/zend_language_scanner.l 文件是re2c的规则文件， 所以如果修改该规则文件需要安装re2c才能重新编译。


### Yacc/Bison

>**NOTE**
>PHP在后续的版本中[可能会使用Lemon作为语法分析器](http://wiki.php.net/rfc/lemon)，
>[Lemon](http://www.sqlite.org/src/doc/trunk/doc/lemon.html)是SQLite作者为SQLite中SQL所编写的词法分析器。
>Lemon具有线程安全以及可重入等特点，也能提供更直观的错误提示信息。

Bison和Flex类似，也是使用%%作为分界不过Bison接受的是标记(token)序列，根据定义的语法规则，来执行一些动作，
Bison使用巴科斯范式([BNF](http://baike.baidu.com/view/1137652.htm))来描述语法。

下面以php中echo语句的编译为例：echo可以接受多个参数，
这几个参数之间可以使用逗号分隔，在PHP的语法规则如下：

	[yacc]
	echo_expr_list:
			echo_expr_list ',' expr { zend_do_echo(&$3 TSRMLS_CC); }
		|   expr                    { zend_do_echo(&$1 TSRMLS_CC); }
	;

其中echo_expr_list规则为一个递归规则，这样就允许接受多个表达式作为参数。
在上例中当匹配到echo时会执行zend_do_echo函数，
函数中的参数可能看起来比较奇怪， 其中的$3 表示前面规则的第三个定义，也就是expr这个表达式的值，
zend_do_echo函数则根据表达式的信息编译opcode，其他的语法规则也类似。
这和C语言或者Java的编译器类似，不过GCC等编译器时将代码编译为机器码，Java编译器将代码编译为字节码。

>**NOTE**
>更多关于lex/yacc的内容请参考[Yacc 与Lex 快速入门](http://www.ibm.com/developerworks/cn/linux/sdk/lex/index.html)

下面将介绍PHP中的opcode。
