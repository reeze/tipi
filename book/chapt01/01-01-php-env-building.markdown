# 环境搭建

在开始学习PHP实现之前, 我们首先需要一个实验和学习的环境. 下面介绍一下怎样在\*nix环境下准备和搭建PHP环境. (\*nix指的是类Unix环境,比如各种Linux发行版,FreeBSD, OpenSolaris, Mac OS X等操作系统)

### 1.获取PHP源码
首先要下载PHP源码, 下载源码首选的是去[PHP官方网站 http://php.net/downloads.php](http://php.net/downloads.php)下载,
我们是为了学习PHP的实现，首先我们要选择下载源代码. 如果你喜欢是用类似svn/git这些版本控制软件,喜欢svn的读者可以去[http://www.php.net/svn.php](http://www.php.net/svn.php)上
签出源代码,或者如果你喜欢用git, 则可以去[http://github.com/php/php-src](http://github.com/php/php-src)上clone一个. 个人比较喜欢是用版本控制软件签出代码,
这样的好处是能看到php每次修改的内容及日志信息, 如果自己修改了其中的某些内容也能快速的查看到.

### 2.准备编译环境
在*nix环境下首先需要gcc编译构建环境. 如果你是用的是Ubuntu或者是用apt做为包管理的系统,可以通过如下命令快速安装上.

	[bash]
	sudo apt-get install build-essential

如果是用的是Mac OS的话则需要安装Xcode. Xcode可以在Mac OS X的安装盘中找到,如果你有Apple ID的话也可以登陆苹果开发者网站[http://developer.apple.com/](http://developer.apple.com/)下载


### 3. 编译
下一步就可以开始编译了, 本文只简单介绍基本的编译过程,不包含apache的php支持以及mysql等模块的编译. 相关资料请百度或google之.  
假设源代码下载到了``~/php-src``的目录中，执行buildconf命令以生成所需要的Makefile文件

	[bash]
	cd ~/php-src
	./buildconf

执行完以后就可以开始configure了, configure有很多的参数, 比如指定安装目录, 是否开启相关模块等选项
	
	[bash]
	./configure --help # 查看可用参数

为了尽快得到可以测试的环境,我们就不加其他参数了.直接执行./configure就可以了. 以后如果需要其他功能可以重新编译.
如果configure命令出现错误,可能是缺少php所依赖的库,各个系统的环境可能不一样. 出现错误可根据出错信息上网搜索. 直到完成configure.
configure完成后我们就可以开始编译了. 

	[bash]
	make

在*nix下编译过程序的读者应该都熟悉经典的configure make, make install吧. 执行make之后是否需要make install就取决于你了. 如果install的话
最好在configure的时候是用prefix参数指定安装目录, 不建议安装到系统目录, 避免和系统原有的php冲突.

在make 完以后，~/php-src目录里就已经有了php的可以执行文件. 执行一下命令：

	[bash]
	cd ~/php-src
	./sapi/cli/php -v

如果看到输出php版本信息则说明咱成功了. 如果是make install的话则执行 $prefix/bin/php 这个路径的php, 当然如果是安装在系统目录或者你的prefix
目录在$PATH环境变量里的话,直接执行php就行了.

后续的学习中可能会需要重复configure make 或者 make && make install 这几个步骤。
