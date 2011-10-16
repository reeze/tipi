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


>NOTE
gcc命令需要添加-lm参数。因为头文件仅对接口进行描述，但头文件不是负责进行符号解析的实体。此时需要告诉编译器应该使用哪个函数库来完成对符号的解析。
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

	在这里声明终结符和非终结符以及操作符的优先级和各种符号语义值的各种类型
	如示例中的%token　NUM。我们在PHP的源码中可以看到更多的类型和符号声明，如%left，%right的使用

	%%
	在这里定义如何从每一个非终结符的部分构建其整体的语法规则。
	%%

	这里就比较自由了，你可以放任何你想放的代码。
	在开始声明的函数，如yylex等，经常是在这里实现的，我们的示例就是这么搞的。




