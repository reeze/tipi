# 第三节 PHP中的线程安全

## 缘起TSRM
在多线程系统中，进程保留着资源所有权的属性，而多个并发执行流是执行在进程中运行的线程。
如Apache2 中的worker，主控制进程生成多个子进程，每个子进程中包含固定的线程数，各个线程独立地处理请求。
同样，为了不在请求到来时再生成线程，MinSpareThreads和MaxSpareThreads设置了最少和最多的空闲线程数；
而MaxClients设置了所有子进程中的线程总数。如果现有子进程中的线程总数不能满足负载，控制进程将派生新的子进程。

当PHP运行在如上类似的多线程服务器时，此时的PHP处在多线程的生命周期中。
在一定的时间内，一个进程空间中会存在多个线程，同一进程中的多个线程公用模块初始化后的全局变量，
如果和PHP在CLI模式下一样运行脚本，则多个线程会试图读写一些存储在进程内存空间的公共资源（如在多个线程公用的模块初始化后的函数外会存在较多的全局变量），

此时这些线程访问的内存地址空间相同，当一个线程修改时，会影响其它线程，这种共享会提高一些操作的速度，
但是多个线程间就产生了较大的耦合，并且当多个线程并发时，就会产生常见的数据一致性问题或资源竞争等并发常见问题，
比如多次运行结果和单线程运行的结果不一样。如果每个线程中对全局变量、静态变量只有读操作，而无写操作，则这些个全局变量就是线程安全的，只是这种情况不太现实。

为解决线程的并发问题，PHP引入了TSRM： 线程安全资源管理器(Thread Safe Resource Manager)。
TRSM 的实现代码在 PHP 源码的 /TSRM 目录下，调用随处可见，通常，我们称之为 TSRM 层。
一般来说，TSRM 层只会在被指明需要的时候才会在编译时启用(比如,Apache2+worker MPM，一个基于线程的MPM)，
因为Win32下的Apache来说，是基于多线程的，所以这个层在Win32下总是被开启的。

## TSRM的实现

进程保留着资源所有权的属性，线程做并发访问，PHP中引入的TSRM层关注的是对共享资源的访问，
这里的共享资源是线程之间共享的存在于进程的内存空间的全局变量。
当PHP在单进程模式下时，一个变量被声明在任何函数之外时，就成为一个全局变量。

PHP解决并发的思路非常简单，既然存在资源竞争，那么直接规避掉此问题，
将多个资源直接复制多份，多个线程竞争的全局变量在进程空间中各自都有一份，各做各的，完全隔离。
以标准的数组扩展为例，首先会声明当前扩展的全局变量，
然后在模块初始化时会调用全局变量初始化宏初始化array的，比如分配内存空间操作。

这里的声明和初始化操作都是区分ZTS和非ZTS，对于非ZTS的情况，直接就是声明变量，初始化变量。
对于ZTS情况，PHP内核会添加TSRM，对应到这里的代码就是声明时不再是声明全局变量，
而是用ts_rsrc_id代码，初始化是不再是初始化变量，
而是调用ts_allocate_id函数在多线程环境中给当前这个模块申请一个全局变量并返回资源ID。

资源ID变量名由模块名和global_id组成。
它是一个自增的整数，整个进程会共享这个变量，在进程SAPI初始调用，初始化TSRM环境时，
id_count作为一个静态变量将被初始化为0。这是一个非常简单的实现，自增。
确保了资源不会冲突，每个线程的独立。
 

### 资源id的分配

当通过ts_allocate_id函数分配全局资源ID时，PHP内核会锁一下，确保生成的资源ID的唯一，
这里锁的作用是在时间维度将并发的内容变成串行，因为并发的根本问题就是时间的问题。

当加锁以后，id_count自增，生成一个资源ID，生成资源ID后，就会给当前资源ID分配存储的位置，
每一个资源都会存储在 resource_types_table 中，当一个新的资源被分配时，就会创建一个tsrm_resource_type。
每次所有tsrm_resource_type以数组的方式组成tsrm_resource_table，其下标就是这个资源的ID。
其实我们可以将tsrm_resource_table看做一个HASH表，key是资源ID，value是tsrm_resource_type结构。
只是,任何一个数组都可以看作一个HASH表，如果数组的key值有意义的话。 
resource_types_table的定义如下：

	[c]
	typedef struct {
		size_t size;//资源的大小
		ts_allocate_ctor ctor;//构造方法指针
		ts_allocate_dtor dtor;//析构方法指针
		int done;
	} tsrm_resource_type;

在分配了资源ID后，PHP内核会接着遍历**所有线程**为每一个线程的tsrm_tls_entry分配这个线程全局变量需要的内存空间。
这里每个线程全局变量的大小在各自的调用处指定。

每一次的ts_allocate_id调用，PHP内核都会遍历所有线程并为每一个线程分配相应资源，
如果这个操作是在PHP生命周期的请求处理阶段进行，岂不是会重复调用？

PHP考虑了这种情况，ts_allocate_id的调用在模块初始化时就调用了。

在模块初始化阶段，通过SAPI调用tsrm_startup启动TSRM，
tsrm_startup函数会传入两个非常重要的参数，一个是expected_threads，表示预期的线程数，
一个是expected_resources，表示预期的资源数。
不同的SAPI有不同的初始化值，比如mod_php5，cgi这些都是一个线程一个资源。

TSRM启动后，在模块初始化过程中会遍历每个扩展的模块初始化方法，
扩展的全局变量在扩展的实现代码开头声明，在MINIT方法中初始化。
其在初始化时会知会TSRM申请的全局变量以及大小，这里所谓的知会操作其实就是前面所说的ts_allocate_id函数。
TSRM在内存池中分配并注册，然后将资源ID返回给扩展。
后续每个线程通过资源ID定位全局变量，比如我们前面提到的数组扩展，
如果要调用当前扩展的全局变量，则使用：ARRAYG(v)，这个宏的定义：

	[c]
	#ifdef ZTS
	#define ARRAYG(v) TSRMG(array_globals_id, zend_array_globals *, v)
	#else
	#define ARRAYG(v) (array_globals.v)
	#endif

如果是非ZTS则直接调用全局变量的属性字段，如果是ZTS，则需要通过TSRMG获取变量。

TSRMG的定义：

	[c]
	#define TSRMG(id, type, element) (((type) (*((void ***) tsrm_ls))[TSRM_UNSHUFFLE_RSRC_ID(id)])->element)

去掉这一堆括号，TSRMG宏的意思就是从tsrm_ls中按资源ID获取全局变量，并返回对应变量的属性字段。

那么现在的问题是这个tsrm_ls从哪里来的？

其实这在我们写扩展的时候会经常用到：

	[c]
	#define TSRMLS_D void ***tsrm_ls
	#define TSRMLS_DC , TSRMLS_D
	#define TSRMLS_C tsrm_ls
	#define TSRMLS_CC , TSRMLS_C

 

以上为ZTS模式下的定义，非ZTS模式下其定义全部为空。

最后个问题，tsrm_ls是从什么时候开始出现的，从哪里来？要到哪里去?

答案就在php_module_startup函数中，在PHP内核的模块初始化时，
如果是ZTS模式，则会定义一个局部变量tsrm_ls，这就是我们线程安全开始的地方。
从这里开始，在每个需要的地方通过在函数参数中以宏的形式带上这个参数，实现线程的安全。


## 参考资料
*  [究竟什么是TSRMLS_CC？- 54chen](http://www.54chen.com/php-tech/what-is-tsrmls_cc.html)
* [深入研究PHP及Zend Engine的线程安全模型](http://blog.codinglabs.org/articles/zend-thread-safety.html)
 
