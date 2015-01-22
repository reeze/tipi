# 实现自己的语法

经过前面对r2ec以及Bison的介绍，熟悉了PHP语法的实现，我们来动手自己实现一个语法吧。
也就是对Zend引擎语法层面的实现。以此来对Zend引擎有更多的了解。

编程语言和社会语言一样都是会慢慢演进的，不同的语种就像我们的不同国家的语言一样，
他们各有各的特点，语言通常也能反映出一个群体的特质，不同语言的社区氛围和文化也都会有
很大的差异，和现实生活一样，我们也需要尽可能的去接触不同的文化，来开阔自己的视野和
思维方式，所以我们也建议多学习不同的编程语言。

在这里简单提一下PHP语言的演进，PHP的语法继承自Perl的语法，这一点和自然语言也很类似，
语言之间会互相影响，比如PHP5开始完善的面向对象机制，已经PHP5.4中增加的命名空间以及闭包等等功能。

PHP是个开源项目，它的发展是由社区来决定的，它也是开放的，如果你有想要改进它的愿望都可以
加入到这个社区当中，当然也不是谁都可以改变PHP，重大改进都需要由社区确定，只有有限的人
具有对代码库的修改权限，如果你发现了PHP的Bug可以去<http://bugs.php.net>提交Bug,
如果同时你也找到了Bug的原因那么你也可以同时附上对Bug的修复补丁，然后在PHP邮件组中进行一些
讨论，如果没有问题那么有权限的成员就可以将你的补丁合并进入相应的版本内，更多内容可以参考
[附录D　怎样为PHP共享自己的力量](?p=D-how-to-contribute)。

在本小节中将要实现一个对PHP本身语言的一个“需求”：返回变量的名称。
用一小段代码简单描述一下这个需求：

     [php]
     <?php 
     $demo = 'tipi';
     echo var_name($demo);   //执行结果，输出： demo
     ?>
     
经过前面的章节，我们了解到，一种PHP语法的内部实现，主要经历了以下步骤：

![图7.2 Zend Opcodes执行](../images/chapt07/07-02-03-excute-opcode.png)

即:词法分析 => 语法分析 => opcode编译 => 执行

由此，我们还是要从词法和语法分析着手。

## 词法分析与语法分析
熟悉编译原理的朋友应该比较熟悉这两个概念，简而言之，就是在要运行的程序中，
根据原来设定好的“关键字”（Tokens），将每条程序指令解释成为可以由语言解释器理解的操作。

>**NOTE**
>在PHP中，可以使用token_get_all()函数来查看一段PHP代码生成的Tokens。

PHP的词法分析和语法分析的实现分别位于Zend目录下的zend_language_scanner.l和
zend_language_parser.y 文件，使用r2ec&flex来编译。
我们要做的，就是在PHP原有的词法和语法分析中，加入新的Token,在zend_language_scanner.l中
加入以下内容：

	[c]
	"var_name" {
		return T_VARIABLE_NAME;
	}

也就是在此法分析阶段遇到var_name这个字符串的时候会被标记为我们定义的T_VARIABLE_NAME token。

同样，在	zend_language_parser.y 也需要加入对这个token的处理，通常是进行响应的逻辑处理。
我们要实现的语法和PHP内置的echo print结构类似，所以我们把这个处理放到 internal_functions_in_yacc
规则里面：

	[c]
	| T_VARIABLE_NAME '(' T_VARIABLE ')' { zend_do_variable_name(&$$, &$3 TSRMLS_CC); }
	| T_VARIABLE_NAME T_VARIABLE { zend_do_variable_name(&$$, &$2 TSRMLS_CC); }

上面的两条规则分别对于类似：

	[php]
	<?php
	echo var_name($varname);
	echo var_name $varname;

的两种调用方式，和include() require() 类似。

大家可以很容易理解第一行的定义，如果发现T_VARIABLE_NAME + ( + 变量 + ), 则使用zend_do_variable_name来处理，
&$$是当前表达式的返回值， &$3表示第三个表达式的值，也就是T_VARIABLE，也就是一个通常的变量定义。
这样就是把变量相关的信息传递进zend_do_variable_name()函数中进行处理。在这里是获取变量的名称，然后进行opcode编译。

## opcode编译

在开始之前需要向大家介绍一下PHP opcode的定义及执行。opcode在PHP中通常是一个数字唯一标识，
在PHP中目前对每个opcode对应的执行方法的分发提供了3种方式:

首先，我们在Zend/zend_vm_opcodes.h 为我们的新opcode 加入一个宏定义：
	
	[c]
	#define ZEND_VARIABLE_NAME 154
这个数字要求在0-255之间，并且不能与现有opcode重复。

第二步，在Zend/zend_compile.c中加入我们对OPCODE的处理，也就是将代码操作转化为op_array放入到opline中：

	[c]
	void zend_do_variable_name(znode *result, znode *variable TSRMLS_DC)
	{
		// 生成一条zend_op
		zend_op *opline = get_next_op(CG(active_op_array) TSRMLS_CC);

		// 因为我们需要有返回值, 并且返回值只作为中间值.所以就是一个临时变量
		opline->result.op_type = IS_TMP_VAR;
		opline->result.u.var = get_temporary_variable(CG(active_op_array));

		opline->opcode = ZEND_VARIABLE_NAME;
		opline->op1 = *variable;
	
		// 我们只需要一个操作数就好了
		SET_UNUSED(opline->op2);
		*result = opline->result;
	}
	
这样，我们就完成了对opcode的编译。


## 内部处理逻辑的编写
经过在上面两个步骤中，我们已经完成了自定义PHP语法的语法规则定义，opcode编译。最后的工作，
就是定义如何处理自定义的opcode，以及编写具体的代码逻辑。
在前面关于如何找到opcode具体实现的小节，我们提到 Zend/zend_vm_execute.h中的zend_vm_get_opcode_handler()函数。
这个函数就是用来获取opcode的执行函数。

这个对应的关系，是根据一个公式来进行了，目的是将不同的参数类型分开，对应到多个处理函数，公式是这样的：
	
	[c]
	return zend_opcode_handlers[opcode * 25 + zend_vm_decode[op->op1.op_type] * 5 
		+ zend_vm_decode[op->op2.op_type]];

从这个公式我们可以看出，最终的处理函数是与参数类型有关，根据计算，我们要满足所有类型的映射，尽管我们可以使用同一函数进行处理。
于是，我们在zend_opcode_handlers这个数组的结尾，加上25个相同的函数定义：

	[c]
	void zend_init_opcodes_handlers(void)
	{
  		static const opcode_handler_t labels[] = {
		....
		ZEND_VARIABLE_NAME_HANDLER,
		....
		ZEND_VARIABLE_NAME_HANDLER
	｝
	
如果我们不想支持某类型的数据，只需要将类型代入公式计算出的数字做为索引，使opcode_handler_t中相应的项为：ZEND_NULL_HANDLER

最后，我们在Zend/zend_vm_def.h 中增加相应的处理函数。

>**NOTE**
>和对语法的修改一样，opcode处理函数也不是直接修改Zend/zend_vm_execute.h文件的，
>这是因为PHP提供了3种opcode分发的机制：
>1. CALL 函数调用的方式分发
>1. SWITCH 使用SWITCH case 进行分发
>1. GOTO 使用goto语句进行分发
>之所以提供3中方式主要是从性能出发的，可能在不同的CPU上这几种调用方式的效率并不一样。
>默认采用的是CALL

回到编写返回变量名的具体实现，在Zend/zend_vm_def.h中增加如下：

	[c]
    static int ZEND_FASTCALL ZEND_VARIABLE_NAME_HANDLER(ZEND_OPCODE_HANDLER_ARGS)
    {   
        zend_op *opline = EX(opline);

        // PHP中所有的变量在内部都是存储在zval结构中的. 
        zval *result = &EX_T(opline->result.u.var).tmp_var;

        // 把变量的名字赋给临时返回值
        Z_STRVAL(*result) = estrndup(opline->op1.u.constant.value.str.val, opline->op1.u.constant.value.str.len);
        Z_STRLEN(*result) = opline->op1.u.constant.value.str.len;
        Z_TYPE(EX_T(opline->result.u.var).tmp_var) = IS_STRING;

        ZEND_VM_NEXT_OPCODE();
    }

进行完上面的修改之后，我们要删除r2ec&flex已经编译好的原文件，即删除Zend/zend_language*.c文件以使新的语法规则生效。
这样我们再次对PHP源码进行make时，会自动生成新的编译好的语法规则处理程序，不过，编译环境要安装有lex&yacc和re2c。

从上面的步骤可以看出，php语法的扩展并不困难，而真正的难点在于如何在当前zend内核框架基础上进行的具体功能的实现，
以及到底应该实现什么语法。关于语法的改进通常也是一个漫长的过程，要修改语言的语法通常需要：

1. 提出需求，并说明该语法的作用，以及具体的应用场景，这个语法带来的好处
1. 大家讨论这个需求是否合理，实现起来是否有困难，对现有的语法是否造成影响
1. 如果大部分人都认可这个需求最好，那么提出该需求的人可以自己来实现，并让大家review，
  如果没有问题则就可以进入版本库了。
1. 如果比较有争议，那可能需要进行投票了。

更多内容请参考附录：怎么样为PHP做贡献小节。

