# 语法分析

Bison是一种通用目的的分析器生成器。它将LALR(1)上下文无关文法的描述转化成分析该文法的C程序。
使用它可以生成解释器，编译器，协议实现等多种程序。
Bison向上兼容Yacc，所有书写正确的Yacc语法都应该可以不加修改地在Bison下工作。
它不但与Yacc兼容还具有许多Yacc不具备的特性。

Bison分析器文件是定义了名为yyparse并且实现了某个语法的函数的C代码。
这个函数并不是一个可以完成所有的语法分析任务的C程序。
除此这外我们还必须提供额外的一些函数：
如词法分析器、分析器报告错误时调用的错误报告函数等等。
我们知道一个完整的C程序必须以名为main的函数开头，如果我们要生成一个可执行文件，并且要运行语法解析器，
那么我们就需要有main函数，并且在某个地方直接或间接调用yyparse，否则语法分析器永远都不会运行。 

先看下bison的示例：[逆波兰记号计算器](：http://www.gnu.org/software/bison/manual/html_node/RPN-Calc.html#RPN-Calc)

	%{
	#define YYSTYPE double
	#include <stdio.h>
	#include <math.h>
	#include <ctype.h>
	int yylex (void);
	void yyerror (char const *);
	%}

	%token NUM

	%%
	input:    /* empty */
	     | input line
		;

	line:     '\n'
	    | exp '\n'      { printf ("\t%.10g\n", $1); }
	;

	exp:      NUM           { $$ = $1;           }
	   | exp exp '+'   { $$ = $1 + $2;      }
		| exp exp '-'   { $$ = $1 - $2;      }
		| exp exp '*'   { $$ = $1 * $2;      }
		| exp exp '/'   { $$ = $1 / $2;      }
		 /* Exponentiation */
		| exp exp '^'   { $$ = pow($1, $2); }
		/* Unary minus    */
		| exp 'n'       { $$ = -$1;          }
	;
	%%

	#include <ctype.h>

	int yylex (void) {
	       int c;

	/* Skip white space.  */
	       while ((c = getchar ()) == ' ' || c == '\t') ;

	/* Process numbers.  */
	       if (c == '.' || isdigit (c)) {
		   ungetc (c, stdin);
		   scanf ("%lf", &yylval);
		   return NUM;
		 }

	       /* Return end-of-input.  */
	       if (c == EOF) return 0;

	       /* Return a single char.  */
	       return c;
	}

	void yyerror (char const *s) {
		fprintf (stderr, "%s\n", s); 
	}

	int main (void) {
		return yyparse ();
	}

我们先看下运行的效果：
	
	[shell]
	bison demo.y
	gcc -o test -lm test.tab.c
	chmod +x test
	./test


>**NOTE**
>gcc命令需要添加-lm参数。因为头文件仅对接口进行描述，但头文件不是负责进行符号解析的实体。此时需要告诉编译器应该使用哪个函数库来完成对符号的解析。
　GCC的命令参数中，-l参数就是用来指定程序要链接的库，-l参数紧接着就是库名，这里我们在-l后面接的是m，即数学库，他的库名是m，他的库文件名是libm.so。

这是一个逆波兰记号计算器的示例，在命令行中输入 3 7 + 回车，输出10

一般来说，使用Bison设计语言的流程，从语法描述到编写一个编译器或者解释器,有三个步骤: 

* 以Bison可识别的格式正式地描述语法。对每一个语法规则，描述当这个规则被识别时相应的执行动作，动作由C语句序列。即我们在示例中看到的%%和%%这间的内容。
* 描述编写一个词法分析器处理输入并将记号传递给语法分析器（即yylex函数一定要存在）。词法分析器既可是手工编写的C代码, 也可以由lex产生，后面我们会讨论如何将re2c与bison结合使用。上面的示例中是直接手工编写C代码实现一个命令行读取内容的词法分析器。 
* 编写一个调用Bison产生的分析器的控制函数，在示例中是main函数直接调用。编写错误报告函数（即yyerror函数）。

将这些源代码转换成可执行程序，需要按以下步骤进行：

* 按语法运行Bison产生分析器。对应示例中的命令，bison demo.y
* 同其它源代码一样编译Bison输出的代码，链接目标文件以产生最终的产品。即对应示例中的命令　gcc -o test -lm test.tab.c

我们可以将整个Ｂison语法文件划分为四个部分。
这三个部分的划分通过`%%',`%{' 和`%}'符号实现。
一般来说，Bison语法文件结构如下：

	%{
	这里可以用来定义在动作中使用类型和变量，或者使用预处理器命令在那里来定义宏, 或者使用#include包含需要的文件。
	如在示例中我们声明了YYSTYPE，包含了头文件math.h等，还声明了词法分析器yylex和错误打印程序yyerror。
	%}

	Bison 的一些声明
	在这里声明终结符和非终结符以及操作符的优先级和各种符号语义值的各种类型
	如示例中的%token　NUM。我们在PHP的源码中可以看到更多的类型和符号声明，如%left，%right的使用

	%%
	在这里定义如何从每一个非终结符的部分构建其整体的语法规则。
	%%

	这里存放附加的内容
	这里就比较自由了，你可以放任何你想放的代码。
	在开始声明的函数，如yylex等，经常是在这里实现的，我们的示例就是这么搞的。


我们在前面介绍了PHP是使用re2c作为词法分析器，那么PHP是如何将re2c与bison集成在一起的呢？
我们以一个从PHP源码中剥离出来的示例来说明整个过程。这个示例的功能与上一小节的示例类似，作用都是识别输入参数中的字符串类型。
本示例是在其基础上添加了语法解析过程。
首先我们看这个示例的语法文件：demo.y

	[c]
	%{
	#include <stdio.h>
	#include "demo_scanner.h"
	extern int yylex(znode *zendlval);
	void yyerror(char const *);

	#define YYSTYPE znode	//关键点一，znode定义在demo_scanner.h	
	%}

	%pure_parser	//	关键点二

	%token T_BEGIN
	%token T_NUMBER
	%token T_LOWER_CHAR
	%token T_UPPER_CHAR	
	%token T_EXIT
	%token T_UNKNOWN
	%token T_INPUT_ERROR
	%token T_END
	%token T_WHITESPACE

	%%

	begin: T_BEGIN {printf("begin:\ntoken=%d\n", $1.op_type);}
	     | begin variable {
			printf("token=%d ", $2.op_type);
			if ($2.constant.value.str.len > 0) {
				printf("text=%s", $2.constant.value.str.val);
			}
			printf("\n");
	}

	variable: T_NUMBER {$$ = $1;}
	|T_LOWER_CHAR {$$ = $1;}
	|T_UPPER_CHAR {$$ = $1;}
	|T_EXIT {$$ = $1;}
	|T_UNKNOWN {$$ = $1;}
	|T_INPUT_ERROR {$$ = $1;}
	|T_END {$$ = $1;}
	|T_WHITESPACE {$$ = $1;}

	%%

	void yyerror(char const *s) {
		printf("%s\n", s);	
	}

这个语法文件有两个关键点：

1、znode是复制PHP源码中的znode，只是这里我们只保留了两个字段，其结构如下：

	[c]
	typedef union _zvalue_value {
	    long lval;                  /* long value */
	    double dval;                /* double value */
	    struct {
		    char *val;
		    int len;
	    } str;
	} zvalue_value;

	typedef struct _zval_struct {
		/* Variable information */
		zvalue_value value;     /* value */
		int type;    /* active type */
	}zval;

	typedef struct _znode {
		int op_type;
		zval constant;
	}znode;	

这里我们同样也复制了PHP的zval结构，但是我们也只取了关于整型，浮点型和字符串型的结构。
op_type用于记录操作的类型，constant记录分析过程获取的数据。
一般来说，在一个简单的程序中，对所有的语言结构的语义值使用同一个数据类型就足够用了。比如在前一小节的逆波兰记号计算器示例就只有double类型。
而且Bison默认是对于所有语义值使用int类型。如果要指明其它的类型，可以像我们示例一样将YYSTYPE定义成一个宏:
	
	[c]
	#define YYSTYPE znode

2、%pure_parser
在Bison中声明%pure_parse表明你要产生一个可重入(reentrant)的分析器。默认情况下Bison调用的词法分析函数名为yylex，并且其参数为void，如果定义了YYLEX_PARAM，则使用YYLEX_PARAM为参数，
这种情况我们可以在Bison生成的.c文件中发现其是使用#ifdef实现。

如果声明了%pure_parser，通信变量yylval和yylloc则变为yyparse函数中的局部变量，变量yynerrs也变为在yyparse中的局部变量，而yyparse自己的调用方式并没有改变。比如在我们的示例中我们声明了可重入，并且使用zval类型的变更作为yylex函数的第一个参数，则在生成的.c文件中，我们可以看到yylval的类型变成

>**NOTE**
>一个可重入(reentrant)程序是在执行过程中不变更的程序；换句话说,它全部由纯(pure)(只读)代码构成。
当可异步执行的时候，可重入特性非常重要。例如，从一个句柄调用不可重入程序可能是不安全的。
在带有多线程控制的系统中，一个非可重入程序必须只能被互锁(interlocks)调用。

通过声明可重入函数和使用znode参数，我们可以记录分析过程中获取的值和词法分析过程产生的token。
在yyparse调用过程中会调用yylex函数，在本示例中的yylex函数是借助re2c生成的。
在demo_scanner.l文件中定义了词法的规则。大部分规则是借用了上一小节的示例，
在此基础上我们增加了新的yylex函数，并且将zendlval作为通信变量，把词法分析过程中的字符串和token传递回来。
而与此相关的增加的操作为：

	SCNG(yy_text) = YYCURSOR;	//	记录当前字符串所在位置
	/*!re2c
	  <!*> {yyleng = YYCURSOR - SCNG(yy_text);}	//	记录字符串长度　

main函数发生了一些改变：
	
	int main(int argc, char* argv[])
	{
		BEGIN(INITIAL);	//	全局初始化，需要放在scan调用之前
		scanner_globals.yy_cursor = argv[1];	//将输入的第一个参数作为要解析的字符串

		yyparse();
		return 0;
	}

在新的main函数中，我们新增加了yyparse函数的调用，此函数在执行过程中会自动调用yylex函数。

如果需要运行这个程序，则需要执行下面的命令：
	
	[shell]
	re2c -o demo_scanner.c -c -t demo_scanner_def.h demo_scanner.l
	bison -d demo.y
	gcc -o t demo.tab.c demo_scanner.c
	chmod +x t
	./t "<?php tipi2011"

在前面我们以一个小的示例和从PHP源码中剥离出来的示例简单说明了bison的入门和bison与re2c的结合。
当我们用gdb工具Debug PHP的执行流程中编译PHP代码过程如下：

	[c]
	#0  lex_scan (zendlval=0xbfffccbc) at Zend/zend_language_scanner.c:841
	#1  0x082bab51 in zendlex (zendlval=0xbfffccb8)
	    at /home/martin/project/c/phpsrc/Zend/zend_compile.c:4930
	#2  0x082a43be in zendparse ()
	    at /home/martin/project/c/phpsrc/Zend/zend_language_parser.c:3280
	#3  0x082b040f in compile_file (file_handle=0xbffff2b0, type=8)
	    at Zend/zend_language_scanner.l:343
	#4  0x08186d15 in phar_compile_file (file_handle=0xbffff2b0, type=8)
	    at /home/martin/project/c/phpsrc/ext/phar/phar.c:3390
	#5  0x082d234f in zend_execute_scripts (type=8, retval=0x0, file_count=3)
	    at /home/martin/project/c/phpsrc/Zend/zend.c:1186
	#6  0x08281b70 in php_execute_script (primary_file=0xbffff2b0)
	    at /home/martin/project/c/phpsrc/main/main.c:2225
	#7  0x08351b97 in main (argc=4, argv=0xbffff424)
	    at /home/martin/project/c/phpsrc/sapi/cli/php_cli.c:1190
	
在PHP源码中，词法分析器的最终是调用re2c规则定义的lex_scan函数，而提供给Bison的函数则为zendlex。
而yyparse被zendparse代替。

	

 

