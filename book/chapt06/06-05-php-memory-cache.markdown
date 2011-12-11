# 第五节 内存管理中的缓存

在[维基百科](http://zh.wikipedia.org/wiki/Cache)中有这样一段描述：
**凡是位于速度相差较大的两种硬件之间的，用于协调两者数据传输速度差异的结构，均可称之为Cache。**
从最初始的处理器与内存间的Cache开始，都是为了让数据访问的速度适应CPU的处理速度，
其基于的原理是内存中“程序执行与数据访问的局域性行为”。
同样PHP内存管理中的缓存也是基于“程序执行与数据访问的局域性行为”的原理。
引入缓存，就是为了减少小块内存块的查询次数，为最近访问的数据提供更快的访问方式。

PHP将缓存添加到内存管理机制中做了如下一些操作：

* 标识缓存和缓存的大小限制，即何时使用缓存，在某些情况下可以以最少的修改禁用掉缓存
* 缓存的存储结构，即缓存的存放位置、结构和存放的逻辑
* 初始化缓存
* 获取缓存中内容
* 写入缓存
* 释放缓存或者清空缓存列表

首先我们看标识缓存和缓存的大小限制，在PHP内核中，是否使用缓存的标识是宏ZEND_MM_CACHE（Zend/zend_alloc.c 400行），
缓存的大小限制与size_t结构大小有关，假设size_t占4位，则默认情况下，PHP内核给PHP内存管理的限制是128K(32 * 4 * 1024)。
如下所示代码：

    [c]
    #define ZEND_MM_NUM_BUCKETS (sizeof(size_t) << 3)

    #define ZEND_MM_CACHE 1
    #define ZEND_MM_CACHE_SIZE (ZEND_MM_NUM_BUCKETS * 4 * 1024)

如果在某些应用下需要禁用缓存，则将ZEND_MM_CACHE宏设置为0，重新编译PHP即可。
为了实现这个一处修改所有地方都生效的功能，则在每个需要调用缓存的地方在编译时都会判断ZEND_MM_CACHE是否定义为1。

如果我们启用了缓存，则在堆层结构中增加了两个字段：

    [c]
    struct _zend_mm_heap {

    #if ZEND_MM_CACHE
        unsigned int        cached; //  已缓存元素使用内存的总大小
        zend_mm_free_block *cache[ZEND_MM_NUM_BUCKETS]; //  存放被缓存的块
    #endif

如上所示，cached表示已缓存元素使用内存的总大小，zend_mm_free_block结构的数组装载被缓存的块。
在初始化内存管理时，会调用zend_mm_init函数。在这个函数中，当缓存启用时会初始化上面所说的两个字段，如下所示：

    [c]
    #if ZEND_MM_CACHE
        heap->cached = 0;
        memset(heap->cache, 0, sizeof(heap->cache));
    #endif

程序会初始化已缓存元素的总大小为0，并给存放缓存块的数组分配内存。
初始化之后，如果外部调用需要PHP内核分配内存，此时可能会调用缓存，
之所以是可能是因为它有一个前提条件，即所有的缓存都只用于小于的内存块的申请。
所谓小块的内存块是其真实大小小于ZEND_MM_MAX_SMALL_SIZE(272)的。
比如，在缓存启用的情况下，我们申请一个100Byte的内存块，则PHP内核会首先判断其真实大小，
并进入小块内存分配的流程，在此流程中程序会先判断对应大小的块索引是否存在，如果存在则直接从缓存中返回，
否则继续走常规的分配流程。

当用户释放内存块空间时，程序最终会调用_zend_mm_free_int函数。在此函数中，如果启用了缓存并且所释放的是小块内存，
并且已分配的缓存大小小于缓存限制大小时，程序会将释放的块放到缓存列表中。如下代码

    [c]
    #if ZEND_MM_CACHE
        if (EXPECTED(ZEND_MM_SMALL_SIZE(size)) && EXPECTED(heap->cached < ZEND_MM_CACHE_SIZE)) {
            size_t index = ZEND_MM_BUCKET_INDEX(size);
            zend_mm_free_block **cache = &heap->cache[index];

            ((zend_mm_free_block*)mm_block)->prev_free_block = *cache;
            *cache = (zend_mm_free_block*)mm_block;
            heap->cached += size;
            ZEND_MM_SET_MAGIC(mm_block, MEM_BLOCK_CACHED);
    #if ZEND_MM_CACHE_STAT
            if (++heap->cache_stat[index].count > heap->cache_stat[index].max_count) {
                heap->cache_stat[index].max_count = heap->cache_stat[index].count;
            }
    #endif
            return;
        }
    #endif

当堆的内存溢出时，程序会调用zend_mm_free_cache释放缓存中。整个释放的过程是一个遍历数组，
对于每个数组的元素程序都遍历其所在链表中在自己之前的元素，执行合并内存操作，减少堆结构中缓存计量数字。
具体实现参见Zend/zend_alloc.c的909行。

在上面的一些零碎的代码块中我们有看到在ZEND_MM_CACHE宏出现时经常会出现ZEND_MM_CACHE_STAT宏。
这个宏是标记是否启用缓存统计功能，默认情况下为不启用。缓存统计功能也有对应的存储结构，在分配，释放缓存中的值时，
缓存统计功能都会有相应的实现。
