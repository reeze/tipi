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
在这里，每个bucket也对应一定大小的内存块列表，这样的列表都包含双向链表的实现。

我们可以把维护的前面两个表看作是两个HashTable，那么，每个HashTable都会有自己的hash函数。
首先我们来看free_buckets列表，这个列表用来存储小块的内存分配，其hash函数为：

	[c]
	#define ZEND_MM_BUCKET_INDEX(true_size)		((true_size>>ZEND_MM_ALIGNMENT_LOG2)-(ZEND_MM_ALIGNED_MIN_HEADER_SIZE>>ZEND_MM_ALIGNMENT_LOG2))

假设ZEND_MM_ALIGNMENT为8（如果没有特殊说明，本章的ZEND_MM_ALIGNMENT的值都为8），则ZEND_MM_ALIGNED_MIN_HEADER_SIZE=16，
若此时true_size=256，则((256>>3)-(16>>3))= 30。
当ZEND_MM_BUCKET_INDEX宏出现时，ZEND_MM_SMALL_SIZE宏一般也会同时出现，
ZEND_MM_SMALL_SIZE宏的作用是判断所申请的内存大小是否为小块的内存，
在上面的示例中，小于272Byte的内存为小块内存，则index最多只能为31，
这样就保证了free_buckets不会出现数组溢出的情况。

在内存管理初始化时，PHP内核对初始化free_buckets列表。
由于free_buckets[]数组会自动分配内存，但此时free_block的双向链表都是没有指向具体的元素的，
于是在初始化函数zend_mm_init中执行下列代码，对于偶数位的元素（索引从0开始）将其左指针和右指针都指向自己。
从而初始化每个双向链表的开始元素。

	[c]
	p = ZEND_MM_SMALL_FREE_BUCKET(heap, 0);
	for (i = 0; i < ZEND_MM_NUM_BUCKETS; i++) {
		p->next_free_block = p;
		p->prev_free_block = p;
		p = (zend_mm_free_block*)((char*)p + sizeof(zend_mm_free_block*) * 2);
		heap->large_free_buckets[i] = NULL;
	}

以上的这个初始化的操作结果，在使用free_bitmap标记是否该双向链表已经使用过时有用。
整个free_buckets列表的结构如图6.2所示。

![图6.2 free_buckets列表结构](../images/chapt06/06-02-02-free_buckets.jpg)


如上为free_buckets列表的结构图，当有新的元素需要插入到列表时，需要先根据块的大小查找index，
查找到index后，在此index对应的双向列表的头部插入新的元素。


free_buckets列表是用于存放小块内存，而与之对应的large_free_buckets列表是用来存储大块的内存，
虽然large_free_buckets列表也类似于一个hash表，但是这个与前面的free_buckets列表一些区别。
它是一个集成了数组，树型结构和双向链表三种数据结构的混合体。
我们先看其数组结构，数组是一个hash映射，其hash函数为：

	[c]
	#define ZEND_MM_LARGE_BUCKET_INDEX(S) zend_mm_high_bit(S)

	
	static inline unsigned int zend_mm_high_bit(size_t _size)
	{
	
	..//省略若干不同环境的实现
		unsigned int n = 0;
		while (_size != 0) {
			_size = _size >> 1;
			n++;
		}
		return n-1;
	}

这个hash函数用来计算size的位数，返回值为size二进码中1的个数-1。
假设此时size为512Byte，则这段内存会放在large_free_buckets列表，
512的二进制码为1000000000，其中仅包含一个1，则其对应的列表index为0。
关于右移操作，这里有一点说明：

>**NOTE**
>一般来说，右移分为逻辑右移和算术右移。逻辑位移在在左端补K个0，算术右移在左端补K个最高有效位的值。
>C语言标准没有明确定义应该使用哪种方式。对于无符号数据，右移必须是逻辑的。对于有符号的数据，则二者都可以。
>但是，现实中都会默认为算术右移。


我们通过一次列表的元素插入操作来理解列表的结果。
首先确定当前需要内存所在的数组元素位置，然后查找此内存大小所在的位置。
这个查找行为是发生在树型结构中，而树型结构的位置与内存的大小有关。
其查找过程如下：

* 第一步 通过索引获取树型结构第一个结点并作为当前结点，如果第一个结点为空，则将内存放到第一个元素的结点位置，返回，否则转第二步
* 第二步 从当前结点出发，查找下一个结点，并将其作为当前结点
* 第三步 判断当前结点内存的大小与需要分配的内存大小是否一样
  如果大小一样则以双向链表的结构将新的元素添加到结点元素的后面第一个元素的位置。否则转四步
* 第四步 判断当前结点是否为空，如果为空，则占据结点位置，结束查找，否则第二步。

从以上的过程我们可以画出large_free_buckets列表的结构如图6.3所示：

![图6.3 large_free_buckets列表结构](../images/chapt06/06-02-03-large_free_buckets.jpg)

从内存分配的过程中可以看出，内存块查找判断顺序是小块内存列表，接着是大块内存列表，
最后是剩余内存列表。在heap结构中，此列表对就rest_buckets字段，这是一个只有两个元素的数组。
而我们常用的插入和查找操作是针对第一个元素，即heap->rest_buckets[0]。
在添加内存时，如果所需要的内存块的大小大于初始化时设置的ZEND_MM_SEG_SIZE的值（在heap结构中为block_size字段）
与ZEND_MM_ALIGNED_SEGMENT_SIZE(等于8)和ZEND_MM_ALIGNED_HEADER_SIZE(等于8)的和的差，则会将新生成的块插入
rest_buckts[0]所在的双向链表中，这个操作和前面的双向链表操作一样，都是在第一个元素的后面插入新的元素。
此列表的结构和free_bucket类型，只是这个列表所在的数组没有那么多元素，也没有相应的hash函数。

在heap层下面是存储层，存储层的作用是将内存分配的方式对堆层透明化。
在PHP的源码中有注释显示相关代码为"Storage Manager"。
存储层的主要结构代码如下：

	[c]
	/* Heaps with user defined storage */
	typedef struct _zend_mm_storage zend_mm_storage;
	 
	typedef struct _zend_mm_segment {
	    size_t    size;
	    struct _zend_mm_segment *next_segment;
	} zend_mm_segment;
	 
	typedef struct _zend_mm_mem_handlers {
	    const char *name;
	    zend_mm_storage* (*init)(void *params);    //    初始化函数
	    void (*dtor)(zend_mm_storage *storage);    //    析构函数
	    void (*compact)(zend_mm_storage *storage);
	    zend_mm_segment* (*_alloc)(zend_mm_storage *storage, size_t size);    //    内存分配函数
	    zend_mm_segment* (*_realloc)(zend_mm_storage *storage, zend_mm_segment *ptr, size_t size);    //    重新分配内存函数
	    void (*_free)(zend_mm_storage *storage, zend_mm_segment *ptr);    //    释放内存函数
	} zend_mm_mem_handlers;
	 
	struct _zend_mm_storage {
	    const zend_mm_mem_handlers *handlers;    //    处理函数集
	    void *data;
	};


以上代码的关键在于存储层处理函数的结构体，对于不同的内存分配方案，所不同的就是内存分配的处理函数。
其中以name字段标识不同的分配方案。在图6.1中，我们可以看到PHP在存储层共有4种内存分配方案: 
malloc，win32，mmap_anon，mmap_zero默认使用malloc分配内存，
如果设置了ZEND_WIN32宏，则为windows版本，调用HeapAlloc分配内存，剩下两种内存方案为匿名内存映射，
并且PHP的内存方案可以通过设置变量来修改。其官方说明如下：

	The Zend MM can be tweaked using ZEND_MM_MEM_TYPE and ZEND_MM_SEG_SIZE environment
	variables. Default values are “malloc” and “256K”. Dependent on target system you
	can also use “mmap_anon”, “mmap_zero” and “win32″ storage managers.

在代码中，对于这4种内存分配方案，分别对应实现了zend_mm_mem_handlers中的各个处理函数。
配合代码的简单说明如下：
	
	[c]
	* 使用mmap内存映射函数分配内存 写入时拷贝的私有映射，并且匿名映射，映射区不与任何文件关联。*/
	# define ZEND_MM_MEM_MMAP_ANON_DSC {"mmap_anon", zend_mm_mem_dummy_init, zend_mm_mem_dummy_dtor, zend_mm_mem_dummy_compact, zend_mm_mem_mmap_anon_alloc, zend_mm_mem_mmap_realloc, zend_mm_mem_mmap_free}

	/* 使用mmap内存映射函数分配内存 写入时拷贝的私有映射，并且映射到/dev/zero。*/
	# define ZEND_MM_MEM_MMAP_ZERO_DSC {"mmap_zero", zend_mm_mem_mmap_zero_init, zend_mm_mem_mmap_zero_dtor, zend_mm_mem_dummy_compact, zend_mm_mem_mmap_zero_alloc, zend_mm_mem_mmap_realloc, zend_mm_mem_mmap_free}

	/* 使用HeapAlloc分配内存 windows版本 关于这点，注释中写的是VirtualAlloc() to allocate memory，实际在程序中使用的是HeapAlloc*/
	# define ZEND_MM_MEM_WIN32_DSC {"win32", zend_mm_mem_win32_init, zend_mm_mem_win32_dtor, zend_mm_mem_win32_compact, zend_mm_mem_win32_alloc, zend_mm_mem_win32_realloc, zend_mm_mem_win32_free}

	/* 使用malloc分配内存 默认为此种分配 如果有加ZEND_WIN32宏，则使用win32的分配方案*/
	# define ZEND_MM_MEM_MALLOC_DSC {"malloc", zend_mm_mem_dummy_init, zend_mm_mem_dummy_dtor, zend_mm_mem_dummy_compact, zend_mm_mem_malloc_alloc, zend_mm_mem_malloc_realloc, zend_mm_mem_malloc_free}

	static const zend_mm_mem_handlers mem_handlers[] = {
	#ifdef HAVE_MEM_WIN32
	    ZEND_MM_MEM_WIN32_DSC,
	#endif
	#ifdef HAVE_MEM_MALLOC
	    ZEND_MM_MEM_MALLOC_DSC,
	#endif
	#ifdef HAVE_MEM_MMAP_ANON
	    ZEND_MM_MEM_MMAP_ANON_DSC,
	#endif
	#ifdef HAVE_MEM_MMAP_ZERO
	    ZEND_MM_MEM_MMAP_ZERO_DSC,
	#endif
	    {NULL, NULL, NULL, NULL, NULL, NULL}
	};

假设我们使用的是win32内存方案，则在PHP编译时，编译器会选择将ZEND_MM_MEM_WIN32_DSC宏所代码的所有处理函数赋值给mem_handlers。
在之后我们调用内存分配时，将会使用此数组中对应的相关函数。当然，在指定环境变量 USE_ZEND_ALLOC 时，可用于允许在运行时选择 malloc 或 emalloc 内存分配。
使用 malloc-type 内存分配将允许外部调试器观察内存使用情况，而 emalloc 分配将使用 Zend 内存管理器抽象，要求进行内部调试。

