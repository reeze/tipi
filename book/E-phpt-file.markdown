# 附录E phpt文件说明

phpt文件用于PHP的自动化测试，这是PHP用自己来测试自己的测试数据用例文件。
测试脚本通过执行PHP源码根目录下的run-tests.php，读取phpt文件执行测试。

phpt文件包含 TEST，FILE，EXPECT 等多个段落的文件。在各个段落中，TEST、FILE、EXPECT是基本的段落，
每个测试脚本都必须至少包括这三个段落。其中：

* TEST段可以用来填写测试用例的名字。
* FILE段是一个 PHP 脚本实现的测试用例。 
* EXPECT段则是测试用例的期待值。

在这三个基本段落之外，还有多个段落，如作为用例输入的GET、POST、COOKIE等，此类字段最终会赋值给$env变量。
比如，cookie存放在$env['HTTP_COOKIE']，$env变量将作为用例中脚本的执行环境。一些主要段落说明如下表所示：

PHP测试脚本中的段落说明
<table>
<tr>
<th>段落名</th>
<th>填充内容</th>
<th>备注</th>
</tr>
<tbody>
<tr>
<td>TEST</td>
<td>测试用例名称</td>
<td>必填段落</td>
</tr>
<tr>
<td>FILE</td>
<td>测试脚本语句</td>
<td>必填段落。用PHP语言书写的脚本语句。其执行的结果将与 EXPECT* 段的期待结果做对比。</td>
</tr>
<tr>
<td>ARGS</td>		
<td>FILE 段的输入参数</td>
<td>选填段落</td>
</tr>
<tr>
<td>SKIPIF</td>
<td>跳过这个测试的条件</td>
<td>选填段落</td>
</tr>
<tr>
<td>POST</td>
<td>传入测试脚本的 POST 变量</td>
<td>选填段落。如果使用POST段，建议配合使用SKIPIF段</td>
</tr>
<tr>
<td>GET</td>
<td>传入测试脚本的 GET 变量</td>
<td>选填段落。如果使用GET段，建议配合使用SKIPIF段。</td>
</tr>
<tr>
<td>POST_RAW </td>
<td>传入测试脚本的POST内容的原生值</td>
<td>选填段落。比如在做文件上传测试时就需要使用此字段来模拟HTTP的POST请求。</td>
</tr>
<tr>
<td>COOKIE</td>
<td> 传入测试脚本的COOKIE的值 </td>
<td>选填段落。最常见的是将PHPSESSID的值传入。</td>
</tr>
<tr>
<td>INI</td>
<td>应用于测试脚本的 ini 设置</td>
<td>选填段落。例如 foo=bar 。其值可通过函数 ini_get(string name_entry) 获得。</td>
</tr>
<tr>
<td>ENV</td>
<td>应用于测试脚本的环境设置</td>
<td>选填段落。例如做gzip测试，则需要设置环境HTTP_ACCEPT_ENCODING=gzip。</td>
</tr>
<tr>
<td>EXPECT</td>
<td>测试脚本的预期结果	相当于测试文件的结果</td>
<td>必填段落</td>
</tr>
<tr>
<td>EXPECTF</td>
<td>测试脚本的预期结果</td>
<td>选填段落。可用函数 sscanf() 中的格式表达预期结果	EXPECT 段的变体</td>
</tr>
<tr>
<td>EXPECTREGEX</td>
<td>测试脚本的正则预期结果</td>
<td>选填段落。以正则的方式包含多个预期结果，是预期结果EXPECT段的一种变体。</td>
</tr>
<tr>
<td>EXPECTHEADERS</td>
<td>测试脚本的预期头部内容</td>
<td>选填段落.测试脚本期待HTTP头部返回，是预期结果EXPECT段的另一种格式。验证过程中会按头部的字段一一比对测试，比如zlib扩展中，如果开启zlib.output_compression，
则在EXPECTHEADERS中包含Content-Encoding: gzip作为预期结果。</td>
</tr>
</tbody>
</table>

phpt文件只是用例文件，它还需要一个控制器来调用这些文件，以实现整个测试过程。
PHP的测试控制器文件是源码根目录下的run-tests.php文件。此文件的作用是根据传入的参数，分析用例相关数据，执行测试过程。
其大概过程如下：

1. 分析输入的命令行，根据参数配置相关参数
1. 分析用例输入参数，获取需要执行的用例文件列表。PHP支持指定单文件用例执行，支持多文件用例执行，
支持* .phpt多用例执行，支持* .phpt简化版本*多用例执行（相当于*.phpt）。
1. 执行所有的用例，遍历用例文件列表，执行每一个用例。对于每个用例，PHP将用被测试的PHP可执行对象去运行FILE段中的测试用例，
用实际的结果去比对测试用例中EXPECT*段所列的预期结果；如果实际结果和预期结果一致，则测试通过；如果不一致，则测试失败。
1. 生成测试结果。

以测试脚本/tests/basic/001.phpt：

	[php]
	--TEST--
	Trivial "Hello World" test
	--FILE--
	<?php echo "Hello World"?>
	--EXPECT--
	Hello World

这个用例脚本只包含必填的三项。测试控制器会执行--FILE--下面的PHP文件，
如果最终的输出是--EXPECT--所期望的结果则表示这个测试通过，如果不一致，则测试不通过，最终这个用例的测试结果会汇总会所有的测试结果集中。

