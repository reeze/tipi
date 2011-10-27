# 本周汇总
本周共发送微博2个主题，5条微博， 3条汇总信息.

## 微博内容

* http://t.cn/hBk2XG 是一个基于PHP5.3的微型PHP框架，代码比较简洁，600+行代码，仿照Ruby的Sinatra框架，路由处理方式比较特别，天然支持REST, 性能还不错，框架只有一个文件，这对功能简单或小型的项目来说还是非常合适的。继续见下一推
* 接上推：实现上，最复杂的是路由规则的匹配.没有其他框架Controller/Action的概念，一条路由对应一个处理函数, 其匿名函数的使用以及框架中helper方法的实现方式值得看看。项目只有一个文件，将所有内容都放在一个文件中，使用虽方便，但在实际项目并不推荐这样组织代码

* [PHP将内置WebServer]http://t.cn/hr2neP 使用过Ruby，Python进行Web开发的同学应该很羡慕在写编写代码时随时都能通过内置的WebServer进行开发吧，而PHP只能Apache等第三方WebServer进行开发，这在不久的将来或成为历史
* [PHP将内置WebServer:2]http://t.cn/hr2neP 目前也有使用php实现的WebServer, 例如pint-io http://t.cn/hr2ns6 及较早的nanoweb http://t.cn/hr2nFs 他们目前都没有实际的应用
* [PHP将内置WebServer:3] http://t.cn/hr2neP 内置的WebServer功能简单，安全性也不在考虑范围之内，主要是作为开发用途。不过这是很大的进步。腾讯有些产品是运行在他们定制的PHP Webserver之上，PHP开始变的更家成熟了。

## 汇总信息

* protected or private ?
  http://blog.astrumfutura.com/2011/03/private-vs-protected-methods-the-debate-that-never-ends/
  http://groups.google.com/group/symfony-devs/browse_thread/thread/58a0d015622c13cb/925d4d7a87795fd5
  http://fabien.potencier.org/article/47/pragmatism-over-theory-protected-vs-private
  PHP中的访问控制符protected/private, PHP中的知名框架Symfony/Doctrine不推荐在框架中使用private。
  在很多其他的脚本语言中其实并不存在真正意义上的private方法，使用protected方法能方便用户
  对框架本身进行扩展，而不需要修改框架本身，当然也有人反对，因为如果用户使用了protected的方法，
  那么就会对这些方法产生依赖，在框架进行升级的时候就需要考虑这些方法的兼容性。
  这几个地址上的讨论其实没有谁真正说服谁。

* PHP内置WebServer http://wiki.php.net/rfc/builtinwebserver
  PHP将会内置一个WebServer以方便进行开发，我们可以不需要必须安装apache等WebServer才能进行开发了。
  说到PHP的WebServer目前有nanoweb http://t.co/2DJYlxc和pint-io http://t.co/2QR33Pi， 腾讯也有产品
  是基于PHP编写的WebServer之上

* 不要创建基于类的API设计， http://qafoo.com/blog/020_object_lifecycle_control.html
  这篇博文从PHP stream的设计谈到接口的设计，不要使用基于类的接口设计，因为这个静态属性及基于类的接口
  都会和使用全局变量一样的不易扩展, 缺乏灵活性，http://kore-nordmann.de/blog/0103_static_considered_harmful.html
  最近很多文章都提到了依赖注入 http://weierophinneynet/matthew/archives/260-Dependency-Injection-An-analogy.html
