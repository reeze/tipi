# 第二节 PHP中的内存管理

在前面的小节中我们介绍了内存管理一般会包括以下内容：

1. 是否有足够的内存供我们的程序使用；
1. 如何从足够可用的内存中获取部分内存；
1. 对于使用后的内存，是否可以将其销毁并将其重新分配给其它程序使用。

与此对应，PHP的内容管理也包含这样的内容，只是这些内容在ZEND内核中是以宏的形式作为接口提供给外部使用。
后面两个操作分别对应emalloc宏，efree宏，而第一个操作可以根据emalloc宏返回结果检测。

PHP的内存管理可以被看作是分层（hierarchical）的。
它分为三层：存储层（storage）、堆层（heap）和接口层（emalloc/efree）。
存储层通过 malloc()、mmap() 等函数向系统真正的申请内存，并通过 free() 函数释放所申请的内存。
存储层通常申请的内存块都比较大，这里申请的内存大并不是指storage层结构所需要的内存大，
只是堆层通过调用存储层的分配方法时，其以大块大块的方式申请的内存，存储层的作用是将内存分配的方式对堆层透明化。
如图6.1所示，PHP内存管理器。PHP在存储层共有4种内存分配方案: malloc，win32，mmap_anon，mmap_zero，
默认使用malloc分配内存，如果设置了ZEND_WIN32宏，则为windows版本，调用HeapAlloc分配内存，
剩下两种内存方案为匿名内存映射，并且PHP的内存方案可以通过设置环境变量来修改。

![图6.1 PHP内存管理器](../images/chapt06/06-02-01-zend-memeory-manager.jpg)

首先我们看下接口层的实现，接口层是一些宏定义，如下：

    [c]
    /* Standard wrapper macros */
    #define emalloc(size)						_emalloc((size) ZEND_FILE_LINE_CC ZEND_FILE_LINE_EMPTY_CC)
    #define safe_emalloc(nmemb, size, offset)	_safe_emalloc((nmemb), (size), (offset) ZEND_FILE_LINE_CC ZEND_FILE_LINE_EMPTY_CC)
    #define efree(ptr)							_efree((ptr) ZEND_FILE_LINE_CC ZEND_FILE_LINE_EMPTY_CC)
    #define ecalloc(nmemb, size)				_ecalloc((nmemb), (size) ZEND_FILE_LINE_CC ZEND_FILE_LINE_EMPTY_CC)
    #define erealloc(ptr, size)					_erealloc((ptr), (size), 0 ZEND_FILE_LINE_CC ZEND_FILE_LINE_EMPTY_CC)
    #define safe_erealloc(ptr, nmemb, size, offset)	_safe_erealloc((ptr), (nmemb), (size), (offset) ZEND_FILE_LINE_CC ZEND_FILE_LINE_EMPTY_CC)
    #define erealloc_recoverable(ptr, size)		_erealloc((ptr), (size), 1 ZEND_FILE_LINE_CC ZEND_FILE_LINE_EMPTY_CC)
    #define estrdup(s)							_estrdup((s) ZEND_FILE_LINE_CC ZEND_FILE_LINE_EMPTY_CC)
    #define estrndup(s, length)					_estrndup((s), (length) ZEND_FILE_LINE_CC ZEND_FILE_LINE_EMPTY_CC)
    #define zend_mem_block_size(ptr)			_zend_mem_block_size((ptr) TSRMLS_CC ZEND_FILE_LINE_CC ZEND_FILE_LINE_EMPTY_CC)

这里为什么没有直接调用函数？因为这些宏相当于一个接口层或中间层，定义了一个高层次的接口，使得调用更加容易
它隔离了外部调用和PHP内存管理的内部实现，实现了一种松耦合关系。虽然PHP不限制这些函数的使用，
但是官方文档还是建议使用这些宏。这里的接口层有点门面模式(facade模式)的味道。

在接口层下面是PHP内存管理的核心实现，我们称之为heap层。
这个层控制整个PHP内存管理的过程，首先我们看这个层的结构：

	[c]
	/* mm block type */
	typedef struct _zend_mm_block_info {
		size_t _size;	/* block的大小*/
		size_t _prev;	/* 计算前一个块有用到*/
	} zend_mm_block_info;
	 
	 
	typedef struct _zend_mm_block {
		zend_mm_block_info info;
	} zend_mm_block;
	 
	typedef struct _zend_mm_small_free_block {	/* 双向链表 */
		zend_mm_block_info info;
		struct _zend_mm_free_block *prev_free_block;	/* 前一个块 */
		struct _zend_mm_free_block *next_free_block;	/* 后一个块 */
	} zend_mm_small_free_block;	/* 小的空闲块*/
	 
	typedef struct _zend_mm_free_block {	/* 双向链表 + 树结构 */
		zend_mm_block_info info;
		struct _zend_mm_free_block *prev_free_block;	/* 前一个块 */
		struct _zend_mm_free_block *next_free_block;	/* 后一个块 */
	 
		struct _zend_mm_free_block **parent;	/* 父结点 */
		struct _zend_mm_free_block *child[2];	/* 两个子结点*/
	} zend_mm_free_block;
	 
	 
	 
	struct _zend_mm_heap {
		int                 use_zend_alloc;	/* 是否使用zend内存管理器 */
		void               *(*_malloc)(size_t);	/* 内存分配函数*/
		void                (*_free)(void*);	/* 内存释放函数*/
		void               *(*_realloc)(void*, size_t);
		size_t              free_bitmap;	/* 小块空闲内存标识 */
		size_t              large_free_bitmap;  /* 大块空闲内存标识*/
		size_t              block_size;		/* 一次内存分配的段大小，即ZEND_MM_SEG_SIZE指定的大小，默认为ZEND_MM_SEG_SIZE   (256 * 1024)*/
		size_t              compact_size;	/* 压缩操作边界值，为ZEND_MM_COMPACT指定大小，默认为 2 * 1024 * 1024*/
		zend_mm_segment    *segments_list;	/* 段指针列表 */
		zend_mm_storage    *storage;	/* 所调用的存储层 */
		size_t              real_size;	/* 堆的真实大小 */
		size_t              real_peak;	/* 堆真实大小的峰值 */
		size_t              limit;	/* 堆的内存边界 */
		size_t              size;	/* 堆大小 */
		size_t              peak;	/* 堆大小的峰值*/
		size_t              reserve_size;	/* 备用堆大小*/
		void               *reserve;	/* 备用堆 */
		int                 overflow;	/* 内存溢出数*/
		int                 internal;
	#if ZEND_MM_CACHE
		unsigned int        cached;	/* 已缓存大小 */
		zend_mm_free_block *cache[ZEND_MM_NUM_BUCKETS];	/* 缓存数组/
	#endif
		zend_mm_free_block *free_buckets[ZEND_MM_NUM_BUCKETS*2];	/* 小块内存数组，相当索引的角色 */
		zend_mm_free_block *large_free_buckets[ZEND_MM_NUM_BUCKETS];	/* 大块内存数组，相当索引的角色 */
		zend_mm_free_block *rest_buckets[2];	/* 剩余内存数组*/
	 
	};


当初始化内存管理时，调用函数是zend_mm_startup。它会初始化storage层的分配方案，
初始化段大小，压缩边界值，并调用zend_mm_startup_ex()初始化堆层。
这里的分配方案就是图6.1所示的四种方案，它对应的环境变量名为：ZEND_MM_MEM_TYPE。
这里的初始化的段大小可以通过ZEND_MM_SEG_SIZE设置，如果没设置这个环境变量，程序中默认为256 * 1024。
这个值存储在_zend_mm_heap结构的block_size字段中，将来在维护的三个列表中都没有可用的内存中，会参考这个值的大小来申请内存的大小。


PHP中的内存管理主要工作就是维护三个列表：小块内存列表（free_buckets）、
大块内存列表（large_free_buckets）和剩余内存列表（rest_buckets）。
看到bucket这个单词是不是很熟悉？在前面我们介绍HashTable时，这就是一个重要的角色，它作为HashTable中的一个单元角色。
在这里，每个bucket也对应一定大小的内存块列表，这样的列表都是双向链表的实现。如下图所示：

我们可以把维护的前面两个表看作是两个HashTable，那么，每个HashTable都会有自己的hash函数。
对于free_buckets列表，其对应的是
