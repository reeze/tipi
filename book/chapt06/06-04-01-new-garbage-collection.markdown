# 新的垃圾回收

如前面所说，在PHP中，主要的内存管理手段是引用计数，引入垃圾收集机制的目的是为了打破引用计数中的循环引用，从而防止因为这个而产生的内存泄露。
垃圾收集机制基于PHP的动态内存管理而存在。PHP5.3为引入垃圾收集机制，在变量存储的基本结构上有一些变动，如下所示：

    [c]
    struct _zval_struct {
        /* Variable information */
        zvalue_value value;		/* value */
        zend_uint refcount__gc;
        zend_uchar type;	/* active type */
        zend_uchar is_ref__gc;
    };

与PHP5.3之前的版本相比，引用计数字段refcount和是否引用字段is_ref都在其后面添加了__gc以用于新的的垃圾回收机制。
在PHP的源码风格中，大量的宏是一个非常鲜明的特点。这些宏相当于一个接口层，它屏蔽了接口层以下的一些底层实现，如，
ALLOC_ZVAL宏，这个宏在PHP5.3之前是直接调用PHP的内存管理分配函数emalloc分配内存，所分配的内存大小由变量的类型等大小决定。
在引入垃圾回收机制后，ALLOC_ZVAL宏直接采用新的垃圾回收单元结构，所分配的大小都是一样的，全部是zval_gc_info结构体所占内存大小，
并且在分配内存后，初始化这个结构体的垃圾回收机制。如下代码：

    [c]
    /* The following macroses override macroses from zend_alloc.h */
    #undef  ALLOC_ZVAL
    #define ALLOC_ZVAL(z) 									\
        do {												\
            (z) = (zval*)emalloc(sizeof(zval_gc_info));		\
            GC_ZVAL_INIT(z);								\
        } while (0)

zend_gc.h文件在zend.h的749行被引用：#include “zend_gc.h”
从而替换覆盖了在237行引用的zend_alloc.h文件中的ALLOC_ZVAL等宏
在新的的宏中，关键性的改变是对所分配内存大小和分配内容的改变，在以前纯粹的内存分配中添加了垃圾收集机制的内容，
所有的内容都包括在zval_gc_info结构体中：

    [c]
    typedef struct _zval_gc_info {
        zval z;
        union {
            gc_root_buffer       *buffered;
            struct _zval_gc_info *next;
        } u;
    } zval_gc_info;

对于任何一个ZVAL容器存储的变量，分配了一个zval结构，这个结构确保其和以zval变量分配的内存的开始对齐，
从而在zval_gc_info类型指针的强制转换时，其可以作为zval使用。在zval字段后面有一个联合体:u。
u包括gc_root_buffer结构的buffered字段和zval_gc_info结构的next字段。
这两个字段一个是表示垃圾收集机制缓存的根结点，一个是zval_gc_info列表的下一个结点，
垃圾收集机制缓存的结点无论是作为根结点，还是列表结点，都可以在这里体现。
ALLOC_ZVAL在分配了内存后会调用GC_ZVAL_INIT用来初始化替代了zval的zval_gc_info，
它会把zval_gc_info中的成员u的buffered字段设置成NULL，此字段仅在将其放入垃圾回收缓冲区时才会有值，否则会一直是NULL。
由于PHP中所有的变量都是以zval变量的形式存在，这里以zval_gc_info替换zval，从而成功实现垃圾收集机制在原有系统中的集成。

PHP的垃圾回收机制在PHP5.3中默认为开启，但是我们可以通过配置文件直接设置为禁用，其对应的配置字段为：zend.enable_gc。
在php.ini文件中默认是没有这个字段的，如果我们需要禁用此功能，则在php.ini中添加zend.enable_gc=0或zend.enable_gc=off。
除了修改php.ini配置zend.enable_gc，也可以通过调用gc_enable()/gc_disable()函数来打开/关闭垃圾回收机制。
这些函数的调用效果与修改配置项来打开或关闭垃圾回收机制的效果是一样的。
除了这两个函数PHP提供了gc_collect_cycles()函数可以在根缓冲区还没满时强制执行周期回收。
与垃圾回收机制是否开启在PHP源码中有一些相关的操作和字段。在zend.c文件中有如下代码：

    [c]

    static ZEND_INI_MH(OnUpdateGCEnabled) /* {{{ */
    {
        OnUpdateBool(entry, new_value, new_value_length, mh_arg1, mh_arg2, mh_arg3, stage TSRMLS_CC);

        if (GC_G(gc_enabled)) {
            gc_init(TSRMLS_C);
        }

        return SUCCESS;
    }
    /* }}} */

    ZEND_INI_BEGIN()
        ZEND_INI_ENTRY("error_reporting",				NULL,		ZEND_INI_ALL,		OnUpdateErrorReporting)
        STD_ZEND_INI_BOOLEAN("zend.enable_gc",				"1",	ZEND_INI_ALL,		OnUpdateGCEnabled,      gc_enabled,     zend_gc_globals,        gc_globals)
    #ifdef ZEND_MULTIBYTE
        STD_ZEND_INI_BOOLEAN("detect_unicode", "1", ZEND_INI_ALL, OnUpdateBool, detect_unicode, zend_compiler_globals, compiler_globals)
    #endif
    ZEND_INI_END()

zend.enable_gc对应的操作函数为ZEND_INI_MH(OnUpdateGCEnabled)，如果开启了垃圾回收机制，
即GC_G(gc_enabled)为真，则会调用gc_init函数执行垃圾回收机制的初始化操作。
gc_init函数在zend/zend_gc.c 121行，此函数会判断是否开启垃圾回收机制，
如果开启，则初始化整个机制，即直接调用malloc给整个缓存列表分配10000个gc_root_buffer内存空间。
这里的10000是硬编码在代码中的，以宏GC_ROOT_BUFFER_MAX_ENTRIES存在，如果需要修改这个值，则需要修改源码，重新编译PHP。
gc_init函数在预分配内存后调用gc_reset函数重置整个机制用到的一些全局变量，如设置gc运行的次数统计(gc_runs)和gc中垃圾的个数(collected)为0，
设置双向链表头结点的上一个结点和下一个结点指向自己等。除了这种提的一些用于垃圾回收机制的全局变量，还有其它一些使用较多的变量，部分说明如下：

    [c]
    typedef struct _zend_gc_globals {
        zend_bool         gc_enabled;	/* 是否开启垃圾收集机制 */
        zend_bool         gc_active;	/* 是否正在进行 */

        gc_root_buffer   *buf;				/* 预分配的缓冲区数组，默认为10000（preallocated arrays of buffers）   */
        gc_root_buffer    roots;			/* 列表的根结点（list of possible roots of cycles） */
        gc_root_buffer   *unused;			/* 没有使用过的缓冲区列表(list of unused buffers)           */
        gc_root_buffer   *first_unused;		/* 指向第一个没有使用过的缓冲区结点（pointer to first unused buffer）   */
        gc_root_buffer   *last_unused;		/* 指向最后一个没有使用过的缓冲区结点，此处为标记结束用(pointer to last unused buffer)    */

        zval_gc_info     *zval_to_free;		/* 将要释放的zval变量的临时列表（temporaryt list of zvals to free） */
        zval_gc_info     *free_list;		/* 临时变量，需要释放的列表开头 */
        zval_gc_info     *next_to_free;		/* 临时变量，下一个将要释放的变量位置*/

        zend_uint gc_runs;	/* gc运行的次数统计 */
        zend_uint collected;    /* gc中垃圾的个数 */

        // 省略...
   }

当我们使用一个unset操作想清除这个变量所占的内存时（可能只是引用计数减一），会从当前符号的哈希表中删除变量名对应的项，
在所有的操作执行完后，并对从符号表中删除的项调用一个析构函数，临时变量会调用zval_dtor，一般的变量会调用zval_ptr_dtor。

>**NOTE**
>当然我们无法在PHP的函数集中找到unset函数，因为它是一种语言结构。
>其对应的中间代码为ZEND_UNSET，在Zend/zend_vm_execute.h文件中你可以找到与它相关的实现。

zval_ptr_dtor并不是一个函数，只是一个长得有点像函数的宏。
在Zend/zend_variables.h文件中，这个宏指向函数_zval_ptr_dtor。
在Zend/zend_execute_API.c 424行，函数相关代码如下：

    [c]
    ZEND_API void _zval_ptr_dtor(zval **zval_ptr ZEND_FILE_LINE_DC) /* {{{ */
    {
    #if DEBUG_ZEND>=2
        printf("Reducing refcount for %x (%x): %d->%d\n", *zval_ptr, zval_ptr, Z_REFCOUNT_PP(zval_ptr), Z_REFCOUNT_PP(zval_ptr) - 1);
    #endif
        Z_DELREF_PP(zval_ptr);
        if (Z_REFCOUNT_PP(zval_ptr) == 0) {
            TSRMLS_FETCH();

            if (*zval_ptr != &EG(uninitialized_zval)) {
                GC_REMOVE_ZVAL_FROM_BUFFER(*zval_ptr);
                zval_dtor(*zval_ptr);
                efree_rel(*zval_ptr);
            }
        } else {
            TSRMLS_FETCH();

            if (Z_REFCOUNT_PP(zval_ptr) == 1) {
                Z_UNSET_ISREF_PP(zval_ptr);
            }

            GC_ZVAL_CHECK_POSSIBLE_ROOT(*zval_ptr);
        }
    }
    /* }}} */

从代码我们可以很清晰的看出这个zval的析构过程，关于引用计数字段做了以下两个操作：

* 如果变量的引用计数为1，即减一后引用计数为0，直接清除变量。如果当前变量如果被缓存，则需要清除缓存
* 如果变量的引用计数大于1，即减一后引用计数大于0，则将变量放入垃圾列表。如果变量存在引用，则去掉其引用。

将变量放入垃圾列表的操作是GC_ZVAL_CHECK_POSSIBLE_ROOT，这也是一个宏，其对应函数gc_zval_check_possible_root，
但是此函数仅对数组和对象执行垃圾回收操作。对于数组和对象变量，它会调用gc_zval_possible_root函数。

    [c]
    ZEND_API void gc_zval_possible_root(zval *zv TSRMLS_DC)
    {
        if (UNEXPECTED(GC_G(free_list) != NULL &&
                       GC_ZVAL_ADDRESS(zv) != NULL &&
                       GC_ZVAL_GET_COLOR(zv) == GC_BLACK) &&
                       (GC_ZVAL_ADDRESS(zv) < GC_G(buf) ||
                        GC_ZVAL_ADDRESS(zv) >= GC_G(last_unused))) {
            /* The given zval is a garbage that is going to be deleted by
             * currently running GC */
            return;
        }

        if (zv->type == IS_OBJECT) {
            GC_ZOBJ_CHECK_POSSIBLE_ROOT(zv);
            return;
        }

        GC_BENCH_INC(zval_possible_root);

        if (GC_ZVAL_GET_COLOR(zv) != GC_PURPLE) {
            GC_ZVAL_SET_PURPLE(zv);

            if (!GC_ZVAL_ADDRESS(zv)) {
                gc_root_buffer *newRoot = GC_G(unused);

                if (newRoot) {
                    GC_G(unused) = newRoot->prev;
                } else if (GC_G(first_unused) != GC_G(last_unused)) {
                    newRoot = GC_G(first_unused);
                    GC_G(first_unused)++;
                } else {
                    if (!GC_G(gc_enabled)) {
                        GC_ZVAL_SET_BLACK(zv);
                        return;
                    }
                    zv->refcount__gc++;
                    gc_collect_cycles(TSRMLS_C);
                    zv->refcount__gc--;
                    newRoot = GC_G(unused);
                    if (!newRoot) {
                        return;
                    }
                    GC_ZVAL_SET_PURPLE(zv);
                    GC_G(unused) = newRoot->prev;
                }

                newRoot->next = GC_G(roots).next;
                newRoot->prev = &GC_G(roots);
                GC_G(roots).next->prev = newRoot;
                GC_G(roots).next = newRoot;

                GC_ZVAL_SET_ADDRESS(zv, newRoot);

                newRoot->handle = 0;
                newRoot->u.pz = zv;

                GC_BENCH_INC(zval_buffered);
                GC_BENCH_INC(root_buf_length);
                GC_BENCH_PEAK(root_buf_peak, root_buf_length);
            }
        }
    }

在前面说到gc_zval_check_possible_root函数仅对数组和对象执行垃圾回收操作，然而在gc_zval_possible_root函数中，
针对对象类型的变量会去调用GC_ZOBJ_CHECK_POSSIBLE_ROOT宏。而对于其它的可用于垃圾回收的机制的变量类型其调用过程如下：

* 检查zval结点信息是否已经放入到结点缓冲区，如果已经放入到结点缓冲区，则直接返回，这样可以优化其性能。
然后处理对象结点，直接返回，不再执行后面的操作
* 判断结点是否已经被标记为紫色，如果为紫色则不再添加到结点缓冲区，此处在于保证一个结点只执行一次添加到缓冲区的操作。
* 将结点的颜色标记为紫色，表示此结点已经添加到缓冲区，下次不用再做添加
* 找出新的结点的位置，如果缓冲区满了，则执行垃圾回收操作。
* 将新的结点添加到缓冲区所在的双向链表。

在gc_zval_possible_root函数中，当缓冲区满时，程序调用gc_collect_cycles函数，执行垃圾回收操作。
其中最关键的几步就是：

* 第628行 此处为其官方文档中算法的步骤 B ，算法使用深度优先搜索查找所有可能的根，找到后将每个变量容器中的引用计数减1，
  为确保不会对同一个变量容器减两次“1”，用灰色标记已减过1的。
* 第629行 这是算法的步骤 C ，算法再一次对每个根节点使用深度优先搜索，检查每个变量容器的引用计数。
  如果引用计数是 0 ，变量容器用白色来标记。如果引用次数大于0，则恢复在这个点上使用深度优先搜索而将引用计数减1的操作（即引用计数加1），
  然后将它们重新用黑色标记。 
* 第630行 算法的最后一步 D ，算法遍历根缓冲区以从那里删除变量容器根(zval roots)，
 同时，检查是否有在上一步中被白色标记的变量容器。每个被白色标记的变量容器都被清除。
 在[gc_collect_cycles() -> gc_collect_roots() -> zval_collect_white() ]中我们可以看到，
 对于白色标记的结点会被添加到全局变量zval_to_free列表中。此列表在后面的操作中有用到。

PHP的垃圾回收机制在执行过程中以四种颜色标记状态。

* GC_WHITE 白色表示垃圾
* GC_PURPLE 紫色表示已放入缓冲区
* GC_GREY 灰色表示已经进行了一次refcount的减一操作
* GC_BLACK 黑色是默认颜色，正常

相关的标记以及操作代码如下：

    [c]
    #define GC_COLOR  0x03

    #define GC_BLACK  0x00
    #define GC_WHITE  0x01
    #define GC_GREY   0x02
    #define GC_PURPLE 0x03

    #define GC_ADDRESS(v) \
        ((gc_root_buffer*)(((zend_uintptr_t)(v)) & ~GC_COLOR))
    #define GC_SET_ADDRESS(v, a) \
        (v) = ((gc_root_buffer*)((((zend_uintptr_t)(v)) & GC_COLOR) | ((zend_uintptr_t)(a))))
    #define GC_GET_COLOR(v) \
        (((zend_uintptr_t)(v)) & GC_COLOR)
    #define GC_SET_COLOR(v, c) \
        (v) = ((gc_root_buffer*)((((zend_uintptr_t)(v)) & ~GC_COLOR) | (c)))
    #define GC_SET_BLACK(v) \
        (v) = ((gc_root_buffer*)(((zend_uintptr_t)(v)) & ~GC_COLOR))
    #define GC_SET_PURPLE(v) \
        (v) = ((gc_root_buffer*)(((zend_uintptr_t)(v)) | GC_PURPLE))
    

以上的这种以位来标记状态的方式在PHP的源码中使用频率较高，如内存管理等都有用到，
这是一种比较高效及节省的方案。但是在我们做数据库设计时可能对于字段不能使用这种方式，
应该是以一种更加直观，更加具有可读性的方式实现。
