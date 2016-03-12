# 第三节 PHP中的线程安全

从作用域上来说，C语言可以定义4种不同的变量：全局变量，静态全局变量，局部变量，静态局部变量。
下面仅从函数作用域的角度分析一下不同的变量，假设所有变量声明不重名。
- 全局变量，在函数外声明，例如，`int gVar;`。全局变量，所有函数共享，在任何地方出现这个变量名都是指这个变量。
- 静态全局变量（`static sgVar`），其实也是所有函数共享，但是这个会有编译器的限制，算是编译器提供的一种功能。
- 局部变量（函数/块内的`int var;`）,不共享，函数的多次执行中涉及的这个变量都是相互独立的，他们只是重名的不同变量而已。
- 局部静态变量(函数中的`static int sVar;`),本函数间共享，函数的每一次执行中涉及的这个变量都是这个同一个变量。

上面几种作用域都是从函数的角度来定义作用域的，可以满足所有我们对单线程编程中变量的共享情况。
现在我们来分析一下多线程的情况。在多线程中，多个线程共享除函数调用栈之外的其他资源。
因此上面几种作用域从定义来看就变成了。
- 全局变量，所有函数共享，因此所有的线程共享，不同线程中出现的不同变量都是这同一个变量。
- 静态全局变量，所有函数共享，也是所有线程共享。
- 局部变量，此函数的各次执行中涉及的这个变量没有联系，因此，也是各个线程间也是不共享的。
- 静态局部变量，本函数间共享，函数的每次执行涉及的这个变量都是同一个变量，因此，各个线程是共享的。



## 缘起TSRM
在多线程系统中，进程保留着资源所有权的属性，而多个并发执行流是执行在进程中运行的线程。
如 Apache2 中的 worker，主控制进程生成多个子进程，每个子进程中包含固定的线程数，各个线程独立地处理请求。
同样，为了不在请求到来时再生成线程，MinSpareThreads 和 MaxSpareThreads 设置了最少和最多的空闲线程数；
而 MaxClients 设置了所有子进程中的线程总数。如果现有子进程中的线程总数不能满足负载，控制进程将派生新的子进程。

当 PHP 运行在如上类似的多线程服务器时，此时的 PHP 处在多线程的生命周期中。
在一定的时间内，一个进程空间中会存在多个线程，同一进程中的多个线程公用模块初始化后的全局变量，
如果和 PHP 在 CLI 模式下一样运行脚本，则多个线程会试图读写一些存储在进程内存空间的公共资源（如在多个线程公用的模块初始化后的函数外会存在较多的全局变量），

此时这些线程访问的内存地址空间相同，当一个线程修改时，会影响其它线程，这种共享会提高一些操作的速度，
但是多个线程间就产生了较大的耦合，并且当多个线程并发时，就会产生常见的数据一致性问题或资源竞争等并发常见问题，
比如多次运行结果和单线程运行的结果不一样。如果每个线程中对全局变量、静态变量只有读操作，而无写操作，则这些个全局变量就是线程安全的，只是这种情况不太现实。

为解决线程的并发问题，PHP 引入了 TSRM： 线程安全资源管理器(Thread Safe Resource Manager)。
TRSM 的实现代码在 PHP 源码的 /TSRM 目录下，调用随处可见，通常，我们称之为 TSRM 层。
一般来说，TSRM 层只会在被指明需要的时候才会在编译时启用(比如,Apache2+worker MPM，一个基于线程的MPM)，
因为 Win32 下的 Apache 来说，是基于多线程的，所以这个层在 Win32 下总是被开启的。

## TSRM的实现

进程保留着资源所有权的属性，线程做并发访问，PHP 中引入的 TSRM 层关注的是对共享资源的访问，
这里的共享资源是线程之间共享的存在于进程的内存空间的全局变量。
当 PHP 在单进程模式下时，一个变量被声明在任何函数之外时，就成为一个全局变量。

<!--
PHP 解决并发的思路非常简单，既然存在资源竞争，那么直接规避掉此问题，
将多个资源直接复制多份，多个线程竞争的全局变量在进程空间中各自都有一份，各做各的，完全隔离。
这句话说的非常抽象，到底 TSRM 是如何实现的呢，下面配合代码和绘图逐步说明。
-->

首先定义了如下几个非常重要的全局变量（这里的全局变量是多线程共享的）。

    [c]
    /* The memory manager table */
    static tsrm_tls_entry	**tsrm_tls_table=NULL;
    static int				tsrm_tls_table_size;
    static ts_rsrc_id		id_count;

    /* The resource sizes table */
    static tsrm_resource_type	*resource_types_table=NULL;
    static int					resource_types_table_size;

`**tsrm_tls_table` 的全拼 thread safe resource manager thread local storage table，用来存放各个线程的 `tsrm_tls_entry` 链表。

`tsrm_tls_table_size` 用来表示 `**tsrm_tls_table` 的大小。

`id_count` 作为全局变量资源的 id 生成器，是全局唯一且递增的。

`*resource_types_table` 用来存放全局变量对应的资源。

`resource_types_table_size` 表示 `*resource_types_table` 的大小。

其中涉及到两个关键的数据结构 `tsrm_tls_entry` 和 `tsrm_resource_type`。
    
    [c]
    typedef struct _tsrm_tls_entry tsrm_tls_entry;

    struct _tsrm_tls_entry {
        void **storage;// 本节点的全局变量数组
        int count;// 本节点全局变量数
        THREAD_T thread_id;// 本节点对应的线程 ID
        tsrm_tls_entry *next;// 下一个节点的指针
    };

    typedef struct {
        size_t size;// 被定义的全局变量结构体的大小
        ts_allocate_ctor ctor;// 被定义的全局变量的构造方法指针
        ts_allocate_dtor dtor;// 被定义的全局变量的析构方法指针
        int done;
    } tsrm_resource_type;


当新增一个全局变量时，`id_count` 会自增1（加上线程互斥锁）。然后根据全局变量需要的内存、构造函数、析构函数生成对应的资源`tsrm_resource_type`，存入 `*resource_types_table`，再根据该资源，为每个线程的所有`tsrm_tls_entry`节点添加其对应的全局变量。

有了这个大致的了解，下面通过仔细分析 TSRM 环境的初始化和资源 ID 的分配来理解这一完整的过程。

### TSRM 环境的初始化

模块初始化阶段，在各个 SAPI main 函数中通过调用 `tsrm_startup` 来初始化 TSRM 环境。`tsrm_startup` 函数会传入两个非常重要的参数，一个是 `expected_threads`，表示预期的线程数， 一个是 `expected_resources`，表示预期的资源数。不同的 SAPI 有不同的初始化值，比如mod_php5，cgi 这些都是一个线程一个资源。

    [c]
    TSRM_API int tsrm_startup(int expected_threads, int expected_resources, int debug_level, char *debug_filename)
    {
        /* code... */

        tsrm_tls_table_size = expected_threads; // SAPI 初始化时预计分配的线程数，一般都为1

        tsrm_tls_table = (tsrm_tls_entry **) calloc(tsrm_tls_table_size, sizeof(tsrm_tls_entry *));

        /* code... */

        id_count=0;

        resource_types_table_size = expected_resources; // SAPI 初始化时预先分配的资源表大小，一般也为1

        resource_types_table = (tsrm_resource_type *) calloc(resource_types_table_size, sizeof(tsrm_resource_type));

        /* code... */

        return 1;
    }

精简出其中完成的三个重要的工作，初始化了 tsrm_tls_table 链表、resource_types_table 数组，以及 id_count。而这三个全局变量是所有线程共享的，实现了线程间的内存管理的一致性。

### 资源 ID 的分配

我们知道初始化一个全局变量时需要使用 ZEND_INIT_MODULE_GLOBALS 宏（下面的数组扩展的例子中会有说明），而其实际则是调用的 ts_allocate_id 函数在多线程环境下申请一个全局变量，然后返回分配的资源 ID。代码虽然比较多，实际还是比较清晰，下面附带注解进行说明：

    [c]
    TSRM_API ts_rsrc_id ts_allocate_id(ts_rsrc_id *rsrc_id, size_t size, ts_allocate_ctor ctor, ts_allocate_dtor dtor)
    {
        int i;

        TSRM_ERROR((TSRM_ERROR_LEVEL_CORE, "Obtaining a new resource id, %d bytes", size));

        // 加上多线程互斥锁
        tsrm_mutex_lock(tsmm_mutex);

        /* obtain a resource id */
        *rsrc_id = TSRM_SHUFFLE_RSRC_ID(id_count++); // 全局静态变量 id_count 加 1
        TSRM_ERROR((TSRM_ERROR_LEVEL_CORE, "Obtained resource id %d", *rsrc_id));

        /* store the new resource type in the resource sizes table */
        // 因为 resource_types_table_size 是有初始值的（expected_resources），所以不一定每次都要扩充内存
        if (resource_types_table_size < id_count) {
            resource_types_table = (tsrm_resource_type *) realloc(resource_types_table, sizeof(tsrm_resource_type)*id_count);
            if (!resource_types_table) {
                tsrm_mutex_unlock(tsmm_mutex);
                TSRM_ERROR((TSRM_ERROR_LEVEL_ERROR, "Unable to allocate storage for resource"));
                *rsrc_id = 0;
                return 0;
            }
            resource_types_table_size = id_count;
        }

        // 将全局变量结构体的大小、构造函数和析构函数都存入 tsrm_resource_type 的数组 resource_types_table 中
        resource_types_table[TSRM_UNSHUFFLE_RSRC_ID(*rsrc_id)].size = size;
        resource_types_table[TSRM_UNSHUFFLE_RSRC_ID(*rsrc_id)].ctor = ctor;
        resource_types_table[TSRM_UNSHUFFLE_RSRC_ID(*rsrc_id)].dtor = dtor;
        resource_types_table[TSRM_UNSHUFFLE_RSRC_ID(*rsrc_id)].done = 0;

        /* enlarge the arrays for the already active threads */
        // PHP内核会接着遍历所有线程为每一个线程的 tsrm_tls_entry
        for (i=0; i<tsrm_tls_table_size; i++) {
            tsrm_tls_entry *p = tsrm_tls_table[i];

            while (p) {
                if (p->count < id_count) {
                    int j;

                    p->storage = (void *) realloc(p->storage, sizeof(void *)*id_count);
                    for (j=p->count; j<id_count; j++) {
                        // 在该线程中为全局变量分配需要的内存空间
                        p->storage[j] = (void *) malloc(resource_types_table[j].size);
                        if (resource_types_table[j].ctor) {
                            // 最后对 p->storage[j] 地址存放的全局变量进行初始化，
                            // 这里 ts_allocate_ctor 函数的第二个参数不知道为什么预留，整个项目中实际都未用到过，对比PHP7发现第二个参数也的确已经移除了
                            resource_types_table[j].ctor(p->storage[j], &p->storage);
                        }
                    }
                    p->count = id_count;
                }
                p = p->next;
            }
        }

        // 取消线程互斥锁
        tsrm_mutex_unlock(tsmm_mutex);

        TSRM_ERROR((TSRM_ERROR_LEVEL_CORE, "Successfully allocated new resource id %d", *rsrc_id));
        return *rsrc_id;
    }

当通过 ts_allocate_id 函数分配全局资源 ID 时，PHP 内核会先加上互斥锁，确保生成的资源 ID 的唯一，这里锁的作用是在时间维度将并发的内容变成串行，因为并发的根本问题就是时间的问题。当加锁以后，id_count 自增，生成一个资源 ID，生成资源 ID 后，就会给当前资源 ID 分配存储的位置，
每一个资源都会存储在 resource_types_table 中，当一个新的资源被分配时，就会创建一个 tsrm_resource_type。
所有 tsrm_resource_type 以数组的方式组成 tsrm_resource_table，其下标就是这个资源的 ID。
其实我们可以将 tsrm_resource_table 看做一个 HASH 表，key 是资源 ID，value 是 tsrm_resource_type 结构（任何一个数组都可以看作一个 HASH 表，如果数组的key 值有意义的话）。

在分配了资源 ID 后，PHP 内核会接着遍历**所有线程**为每一个线程的 tsrm_tls_entry 分配这个线程全局变量需要的内存空间。
这里每个线程全局变量的大小在各自的调用处指定（也就是全局变量结构体的大小）。最后对地址存放的全局变量进行初始化。为此我画了一张图予以说明

![图8.2 PHP 线程安全示意图](../images/chapt08/08-03-01-tsrm.png)

上图中还有一个困惑的地方，`tsrm_tls_table` 的元素是如何添加的，链表是如何实现的。我们把这个问题先留着，后面会讨论。

每一次的 ts_allocate_id 调用，PHP 内核都会遍历所有线程并为每一个线程分配相应资源，
如果这个操作是在PHP生命周期的请求处理阶段进行，岂不是会重复调用？

PHP 考虑了这种情况，ts_allocate_id 的调用在模块初始化时就调用了。

TSRM 启动后，在模块初始化过程中会遍历每个扩展的模块初始化方法，
扩展的全局变量在扩展的实现代码开头声明，在 MINIT 方法中初始化。
其在初始化时会知会 TSRM 申请的全局变量以及大小，这里所谓的知会操作其实就是前面所说的 ts_allocate_id 函数。
TSRM 在内存池中分配并注册，然后将资源ID返回给扩展。

### 全局变量的使用

以标准的数组扩展为例，首先会声明当前扩展的全局变量。

    [c]
    ZEND_DECLARE_MODULE_GLOBALS(array)

然后在模块初始化时会调用全局变量初始化宏初始化 array，比如分配内存空间操作。

    [c]
    static void php_array_init_globals(zend_array_globals *array_globals)
    {
        memset(array_globals, 0, sizeof(zend_array_globals));
    }

    /* code... */

    PHP_MINIT_FUNCTION(array) /* {{{ */
    {
        ZEND_INIT_MODULE_GLOBALS(array, php_array_init_globals, NULL);
        /* code... */
    }

这里的声明和初始化操作都是区分ZTS和非ZTS。

    [c]
    #ifdef ZTS

    #define ZEND_DECLARE_MODULE_GLOBALS(module_name)							\
        ts_rsrc_id module_name##_globals_id;

    #define ZEND_INIT_MODULE_GLOBALS(module_name, globals_ctor, globals_dtor)	\
        ts_allocate_id(&module_name##_globals_id, sizeof(zend_##module_name##_globals), (ts_allocate_ctor) globals_ctor, (ts_allocate_dtor) globals_dtor);

    #else

    #define ZEND_DECLARE_MODULE_GLOBALS(module_name)							\
        zend_##module_name##_globals module_name##_globals;

    #define ZEND_INIT_MODULE_GLOBALS(module_name, globals_ctor, globals_dtor)	\
        globals_ctor(&module_name##_globals);

    #endif

对于非ZTS的情况，直接声明变量，初始化变量；对于ZTS情况，PHP内核会添加TSRM，不再是声明全局变量，而是用ts_rsrc_id代替，初始化时也不再是初始化变量，而是调用ts_allocate_id函数在多线程环境中给当前这个模块申请一个全局变量并返回资源ID。其中，资源ID变量名由模块名加global_id组成。

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

那么现在的问题是这个 `tsrm_ls` 从哪里来的？

### tsrm_ls 的初始化

`tsrm_ls` 通过 `ts_resource(0)` 初始化。展开实际最后调用的是 `ts_resource_ex(0,NULL)` 。下面将 `ts_resource_ex` 一些宏展开，线程以 `pthread` 为例。

    [c]
    #define THREAD_HASH_OF(thr,ts)  (unsigned long)thr%(unsigned long)ts

    static MUTEX_T tsmm_mutex;

    void *ts_resource_ex(ts_rsrc_id id, THREAD_T *th_id)
    {
        THREAD_T thread_id;
        int hash_value;
        tsrm_tls_entry *thread_resources;

        // tsrm_tls_table 在 tsrm_startup 已初始化完毕
        if(tsrm_tls_table) {
            // 初始化时 th_id = NULL;
            if (!th_id) {

                //第一次为空 还未执行过 pthread_setspecific 所以 thread_resources 指针为空
                thread_resources = pthread_getspecific(tls_key);

                if(thread_resources){
                    TSRM_SAFE_RETURN_RSRC(thread_resources->storage, id, thread_resources->count);
                }

                thread_id = pthread_self();
            } else {
                thread_id = *th_id;
            }
        }
        // 上锁
        pthread_mutex_lock(tsmm_mutex);

        // 直接取余，将其值作为数组下标，将不同的线程散列分布在 tsrm_tls_table 中
        hash_value = THREAD_HASH_OF(thread_id, tsrm_tls_table_size);
        // 在 SAPI 调用 tsrm_startup 之后，tsrm_tls_table_size = expected_threads
        thread_resources = tsrm_tls_table[hash_value];

        if (!thread_resources) {
            // 如果还没，则新分配。
            allocate_new_resource(&tsrm_tls_table[hash_value], thread_id);
            // 分配完毕之后再执行到下面的 else 区间
            return ts_resource_ex(id, &thread_id);
        } else {
             do {
                // 沿着链表逐个匹配
                if (thread_resources->thread_id == thread_id) {
                    break;
                }
                if (thread_resources->next) {
                    thread_resources = thread_resources->next;
                } else {
                    // 链表的尽头仍然没有找到，则新分配，接到链表的末尾
                    allocate_new_resource(&thread_resources->next, thread_id);
                    return ts_resource_ex(id, &thread_id);
                }
             } while (thread_resources);
        }

        TSRM_SAFE_RETURN_RSRC(thread_resources->storage, id, thread_resources->count);

        // 解锁
        pthread_mutex_unlock(tsmm_mutex);

    }

而 `allocate_new_resource` 则是为新的线程在对应的链表中分配内存，并且将所有的全局变量都加入到其 `storage` 指针数组中。

    [c]
    static void allocate_new_resource(tsrm_tls_entry **thread_resources_ptr, THREAD_T thread_id)
    {
        int i;

        (*thread_resources_ptr) = (tsrm_tls_entry *) malloc(sizeof(tsrm_tls_entry));
        (*thread_resources_ptr)->storage = (void **) malloc(sizeof(void *)*id_count);
        (*thread_resources_ptr)->count = id_count;
        (*thread_resources_ptr)->thread_id = thread_id;
        (*thread_resources_ptr)->next = NULL;

        // 设置线程本地存储变量。在这里设置之后，再到 ts_resource_ex 里取
        pthread_setspecific(*thread_resources_ptr);

        if (tsrm_new_thread_begin_handler) {
            tsrm_new_thread_begin_handler(thread_id, &((*thread_resources_ptr)->storage));
        }

        for (i=0; i<id_count; i++) {
            if (resource_types_table[i].done) {
                (*thread_resources_ptr)->storage[i] = NULL;
            } else {
                // 为新增的 tsrm_tls_entry 节点添加 resource_types_table 的资源
                (*thread_resources_ptr)->storage[i] = (void *) malloc(resource_types_table[i].size);
                if (resource_types_table[i].ctor) {
                    resource_types_table[i].ctor((*thread_resources_ptr)->storage[i], &(*thread_resources_ptr)->storage);
                }
            }
        }

        if (tsrm_new_thread_end_handler) {
            tsrm_new_thread_end_handler(thread_id, &((*thread_resources_ptr)->storage));
        }

        pthread_mutex_unlock(tsmm_mutex);
    }

上面有一个知识点，Thread Local Storage ，现在有一全局变量 **tls_key**，所有线程都可以使用它，改变它的值。
表面上看起来这是一个全局变量，所有线程都可以使用它，而它的值在每一个线程中又是单独存储的。这就是线程本地存储的意义。
那么如何实现线程本地存储呢？

需要联合 `tsrm_startup`, `ts_resource_ex`, `allocate_new_resource` 函数并配以注释一起举例说明：

    [c]
    // 以 pthread 为例
    // 1. 首先定义了 tls_key 全局变量
    static pthread_key_t tls_key;
    
    // 2. 然后在 tsrm_startup 调用 pthread_key_create() 来创建该变量
    pthread_key_create( &tls_key, 0 ); 
    
    // 3. 在 allocate_new_resource 中通过 tsrm_tls_set 将 *thread_resources_ptr 指针变量存入了全局变量 tls_key 中
    tsrm_tls_set(*thread_resources_ptr);// 展开之后为 pthread_setspecific(*thread_resources_ptr);
    
    // 4. 在 ts_resource_ex 中通过 tsrm_tls_get() 获取在该线程中设置的 *thread_resources_ptr 
    //    多线程并发操作时，相互不会影响。
    thread_resources = tsrm_tls_get();
    
在理解了 `tsrm_tls_table` 数组和其中链表的创建之后，再看 `ts_resource_ex` 函数中调用的这个返回宏

    [c]
    #define TSRM_SAFE_RETURN_RSRC(array, offset, range)		\
        if (offset==0) {									\
            return &array;									\
        } else {											\
            return array[TSRM_UNSHUFFLE_RSRC_ID(offset)];	\
        }

就是根据传入 `tsrm_tls_entry` 和 `storage` 的数组下标 `offset` ，然后返回该全局变量在该线程的 `storage`数组中的地址。到这里就明白了在多线程中获取全局变量宏 `TSRMG` 宏定义了。

其实这在我们写扩展的时候会经常用到：

	[c]
	#define TSRMLS_D void ***tsrm_ls   /* 不带逗号，一般是唯一参数的时候，定义时用 */
	#define TSRMLS_DC , TSRMLS_D       /* 也是定义时用，不过参数前面有其他参数，所以需要个逗号 */
	#define TSRMLS_C tsrm_ls
	#define TSRMLS_CC , TSRMLS_C

> **NOTICE** 写扩展的时候可能很多同学都分不清楚到底用哪一个，通过宏展开我们可以看到，他们分别是带逗号和不带逗号，以及申明及调用，那么英语中“D"就是代表：Define，而 后面的"C"是 Comma，逗号，前面的"C"就是Call。
 

以上为ZTS模式下的定义，非ZTS模式下其定义全部为空。

<!--最后个问题，tsrm_ls是从什么时候开始出现的，从哪里来？要到哪里去?

答案就在php_module_startup函数中，在PHP内核的模块初始化时，
如果是ZTS模式，则会定义一个局部变量tsrm_ls，这就是我们线程安全开始的地方。
从这里开始，在每个需要的地方通过在函数参数中以宏的形式带上这个参数，实现线程的安全。-->


## 参考资料
*  [究竟什么是TSRMLS_CC？- 54chen](http://www.54chen.com/php-tech/what-is-tsrmls_cc.html)
* [深入研究PHP及Zend Engine的线程安全模型](http://blog.codinglabs.org/articles/zend-thread-safety.html)
 
