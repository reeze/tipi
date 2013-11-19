# 深入理解PHP内核(Thinking In PHP Internal)
TIPI项目是一个自发项目,	项目主要关注PHP的内部实现, 以及PHP相关的方方面面,
该项目包括《深入理解PHP内核》这本书，以及一些相关的PHP项目，例如一些PHP扩展及研究项目.

前往这里阅读在线版本的 [深入理解PHP内核(TIPI)][project-url]

## 关于如何加入项目组
很多同学留言询问怎么加入项目，其实很简单：

1. fork这个项目
1. 提交Pull Requests
1. 在一段时间后，如果对项目还有热情可以直接给你开放提交权限

## 1. 下载
本站所有内容托管在github<http://github.com/reeze/tipi>上，如果你电脑上安装了[git](http://git-scm.com/)最好，没有的话也不碍，在页面右右上角可以下载到项目完整的压缩包，选择你喜欢的压缩格式即可。

## 2. 安装
将项目解压，目录结构中web目录即为项目的web根目录，项目不强制将你的Web服务器根目录设为web目录的路径，
可以直接将整个项目解压至你的Web根目录。如果你是Windows用户，有一点需要说明一下，为了保证书籍内容的独立性，
书籍相关的配图没有放到web目录下，所以你通过web访问书籍的时候可能会看不到图片，我们把/book/images目录链接到了
/web/images/book目录，因为windows并不能是识别软链接，所以会有问题，要解决这个问题有三个方法：

* 把/book/images目录拷贝到/web/images目录下并重命名为book目录，这样可能比较麻烦，如果我们以后更新了内容，你重新下载会比较麻烦。
* 另一个方法是使用Web服务器的别名方法，这样你需要修改服务器的配置，如果使用Apache则可以在配置文件中加入Alias配置选项：
    Alias /images/book TIPI的绝对路径\book\images, 如果使用其他WebServer请参考相关手册。
* 第三个方法就是我们推荐的换系统了。这只是建议，我们更喜欢*nix环境。

## 3. 反馈
对书有什么意见？如果对某章节有意见或建议可以直接在线留言，如果有其他的内容你也可以直接联系我们.

## 4. 联系作者
你可以联系以下作者

* reeze <http://reeze.cn>  			reeze.xia@gmail.com
* er    <http://www.zhangabc.com>   er@zhangabc.com
* phppan <http://www.phppan.com/>   phppan.p@gmail.com
* HonestQiao <http://www.wapm.cn/>  honestqiao@gmail.com

或者发给所有组员: team@php-internals.com

[project-url]: http://www.php-internals.com/

## 贡献者

感谢这些贡献者：<https://github.com/reeze/tipi/contributors>
