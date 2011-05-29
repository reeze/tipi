# 哈希表(HashTable)

**按图索骥。**

PHP中使用最为频繁的数据类型非字符串和数组莫属，PHP比较容易上手也得益于非常灵活的数组类型。
在开始详细介绍这些数据类型之前有必要介绍一下哈希表(HashTable)。 哈希表是PHP实现中尤为关键的数据结构。

哈希表在实践中使用的非常广泛，例如编译器通常会维护的一个符号表来保存标记，很多高级语言中也显式的支持哈希表。
哈希表通常提供查找(Search)，插入(Insert)，删除(Delete)等操作，这些操作在最坏的情况下和链表的性能一样为O(n)。
不过通常并不会这么坏，合理设计的哈希算法能有效的避免这类情况，通常哈希表的这些操作时间复杂度为O(1)。
这也是它被钟爱的原因。

正是因为哈希表在使用上的便利性及效率上的表现，目前大部分动态语言的实现中都使用了哈希表。

## 基本概念
为了方便读者阅读后面的内容，这里提前列举一下HashTable实现中出现的基本概念。
哈希表是一种通过哈希函数，将特定的键映射到特定值的一种数据结构，它维护键和值之间一一对应关系。

* 键(key)：用于操作数据的标示，例如PHP数组中的索引，或者字符串键等等。
* 槽(slot/bucket)：哈希表中用于保存数据的一个单元，也就是数据真正存放的容器。
* 哈希函数(hash function)：将key映射(map)到数据应该存放的slot所在位置的函数。
* 哈希冲突(hash collision)：哈希函数将两个不同的key映射到同一个索引的情况。

哈希表可以理解为数组的扩展或者关联数组，数组使用数字下标来寻址，如果关键字(key)的范围较小且是数字的话，
我们可以直接使用数组来完成哈希表，而如果关键字范围太大，如果直接使用数组我们需要为所有可能的key申请空间。
很多情况下这是不现实的。即使空间足够，空间利用率也会很低，这并不理想。同时键也可能并不是数字，
在PHP中尤为如此，所以人们使用一种映射函数(哈希函数)来将key映射到特定的域中：

	h(key) -> index

通过合理设计的哈希函数，我们就能将key映射到合适的范围，因为我们的key空间可以很大(例如字符串key)，
在映射到一个较小的空间中时可能会出现两个不同的key映射被到同一个index上的情况，
这就是我们所说的出现了冲突。 目前解决hash冲突的方法主要有两种：链接法和开放寻址法。

## 冲突解决
### 链接法
链接法通过使用一个链表来保存slot值的方式来解决冲突，也就是当不同的key映射到一个槽中的时候使用链表来保存这些值。
所以使用链接法是在最坏的情况下，也就是所有的key都映射到同一个槽中了，操作链表的时间复杂度为O(n)。 
所以选择一个合适的哈希函数是最为关键的。

目前PHP中HashTable的实现就是采用这种方式来解决冲突的。

### 开放寻址法
通常还有另外一种解决冲突的方法：开放寻址法。使用开放寻址法是槽本身直接存放数据，
在插入数据时如果key所映射到的索引已经有数据了，这说明发生了冲突，这是会寻找下一个槽，
如果该槽也被占用了则继续寻找下一个槽，直到寻找到没有被占用的槽，在查找时也使用同样的策律来进行。

这两者的区别在下一节：PHP中的哈希表中将进行对比。

## 哈希表的实现
在了解到哈希表的原理之后要实现一个哈希表也很容易，主要需要完成的工作只有三点：

1. 实现哈希函数
1. 冲突的解决
1. 操作接口的实现

### 数据结构
首先我们需要一个容器来保存我们的哈希表，哈希表需要保存的内容主要是保存进来的的数据，
同时为了方便的得知哈希表中存储的元素个数，需要保存一个大小字段，
第二个需要的就是保存数据的容器了。作为实例，下面将实现一个简易的哈希表。基本的数据结构主要有两个，
一个用于保存哈希表本身，另外一个就是用于实际保存数据的单链表了，定义如下：

	[c]
	typedef struct _Bucket
	{
		char *key;
		void *value;
		struct _Bucket *next;

	} Bucket;

	typedef struct _HashTable
	{
		int size;
		Bucket* buckets;
	} HashTable;

上面的定义和PHP中的实现类似，为了便于理解裁剪了大部分无关的细节，在本节中为了简化，
key的数据类型为字符串，而存储的数据类型可以为任意类型。

Bucket结构体是一个单链表，这是为了解决多个key哈希冲突的问题，也就是前面所提到的的链接法。
当多个key映射到同一个index的时候将冲突的元素链接起来。

### 哈希函数实现
哈希函数需要尽可能的将不同的key映射到不同的槽(slot或者bucket)中，首先我们采用一种最为简单的哈希算法实现：
将key字符串的所有字符加起来，然后以结果对哈希表的大小取模，这样索引就能落在数组索引的范围之内了。

	[c]
	static int hash_str(char *key)
	{
		int hash = 0;

		char *cur = key;

		while(*(cur++) != '\0') {
			hash +=	*cur;
		}

		return hash;
	}

	// 使用这个宏来求得key在哈希表中的索引
	#define HASH_INDEX(ht, key) (hash_str((key)) % (ht)->size)

这个哈希算法比较简单，它的效果并不好，在实际场景下不会使用这种哈希算法，
例如PHP中使用的是称为[DJBX33A](http://blog.csdn.net/zuiaituantuan/archive/2010/12/06/6057586.aspx)算法，
[这里](http://blog.minidx.com/2008/01/27/446.html)列举了Mysql，OpenSSL等开源软件使用的哈希算法，
有兴趣的读者可以前往参考。

### 操作接口的实现
为了操作哈希表，实现了如下几个操作函数：

	[c]
	int hash_init(HashTable *ht);  								// 初始化哈希表
	int hash_lookup(HashTable *ht, char *key, void **result);	// 根据key查找内容
	int hash_insert(HashTable *ht, char *key, void *value);		// 将内容插入到哈希表中
	int hash_remove(HashTable *ht, char *key);					// 删除key所指向的内容
	int hash_destroy(HashTable *ht);

下面以插入和获取操作函数为例：

	[c]
	int hash_insert(HashTable *ht, char *key, void *value)
	{
		// check if we need to resize the hashtable
		resize_hash_table_if_needed(ht);    // 哈希表不固定大小，当插入的内容快占满哈表的存储空间
											// 将对哈希表进行扩容, 以便容纳所有的元素

		int index = HASH_INDEX(ht, key);	// 找到key所映射到的索引

		Bucket *org_bucket = ht->buckets[index];
		Bucket *bucket = (Bucket *)malloc(sizeof(Bucket)); // 为新元素申请空间

		bucket->key	  = strdup(key);
		// 将值内容保存进来， 这里只是简单的将指针指向要存储的内容，而没有将内容复制。
		bucket->value = value;  

		LOG_MSG("Insert data p: %p\n", value);

		ht->elem_num += 1; // 记录一下现在哈希表中的元素个数

		if(org_bucket != NULL) { // 发生了碰撞，将新元素放置在链表的头部
			LOG_MSG("Index collision found with org hashtable: %p\n", org_bucket);
			bucket->next = org_bucket;
		}

		ht->buckets[index]= bucket;

		LOG_MSG("Element inserted at index %i, now we have: %i elements\n",
			index, ht->elem_num);

		return SUCCESS;
	}

上面这个哈希表的插入操作比较简单，简单的以key做哈希，找到元素应该存储的位置，并检查该位置是否已经有了内容，
如果发生碰撞则将新元素链接到原有元素链表头部。在查找时也按照同样的策略，找到元素所在的位置，如果存在元素，
则将该链表的所有元素的key和要查找的key依次对比, 直到找到一致的元素，否则说明该值没有匹配的内容。

	[c]
	int hash_lookup(HashTable *ht, char *key, void **result)
	{
		int index = HASH_INDEX(ht, key);
		Bucket *bucket = ht->buckets[index];

		if(bucket == NULL) return FAILED;

		// 查找这个链表以便找到正确的元素，通常这个链表应该是只有一个元素的，也就不用多次
		// 循环。要保证这一点需要有一个合适的哈希算法，见前面相关哈希函数的链接。
		while(bucket)
		{
			if(strcmp(bucket->key, key) == 0)
			{
				LOG_MSG("HashTable found key in index: %i with  key: %s value: %p\n",
					index, key, bucket->value);
				*result = bucket->value;	
				return SUCCESS;
			}

			bucket = bucket->next;
		}

		LOG_MSG("HashTable lookup missed the key: %s\n", key);
		return FAILED;
	}

PHP中数组是基于哈希表实现的，依次给数组添加元素时，元素之间是有先后顺序的，而这里的哈希表在物理位置上显然是接近平均分布的，
这样是无法根据插入的先后顺序获取到这些元素的，在PHP的实现中Bucket结构体还维护了另一个指针字段来维护元素之间的关系。
具体内容在后一小节PHP中的HashTable中进行详细说明。上面的例子就是PHP中实现的一个精简版。

>**NOTE**
>本小节的HashTable实例完整代码可以在$TIPI_ROOT/book/sample/chapt03/03-01-01-hashtable目录中找到。
