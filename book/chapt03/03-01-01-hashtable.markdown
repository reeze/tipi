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
为了方便读者阅读后面的内容，这里列举一下HashTable实现中出现的基本概念。
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
所以使用链接法是在最坏的情况下，也就是所有的key都映射到同一个槽中了，这样哈希表就退化成了一个链表，
这样的话操作链表的时间复杂度则成了O(n)，这样哈希表的性能优势就没有了，
所以选择一个合适的哈希函数是最为关键的。

由于目前大部分的编程语言的哈希表实现都是开源的，大部分语言的哈希算法都是公开的算法，
虽然目前的哈希算法都能良好的将key进行比较均匀的分布，而这个假使的前提是key是随机的，正是由于算法的确定性，
这就导致了别有用心的黑客能利用已知算法的可确定性来构造一些特殊的key，让这些key都映射到
同一个槽位导致哈希表退化成单链表，导致程序的性能急剧下降，从而造成一些应用的吞吐能力急剧下降，
尤其是对于高并发的应用影响很大，通过大量类似的请求可以让服务器遭受DoS(服务拒绝攻击)，
这个问题一直就存在着，只是最近才被各个语言重视起来。 

哈希冲突攻击利用的哈希表最根本的弱点是：**开源算法和哈希实现的确定性以及可预测性**，
这样攻击者才可以利用特殊构造的key来进行攻击。要解决这个问题的方法则是让攻击者无法轻易构造
能够进行攻击的key序列。

>**NOTE**
>在笔者编写这节内容的时候PHP语言也采取了相应的措施来防止这类的攻击，PHP采用的是一种
>治标不治本的做法: [限制用户提交数据字段数量](http://cn2.php.net/manual/en/info.configuration.php#ini.max-input-vars)
>这样可以避免大部分的攻击，不过应用程序通常会有很多的数据输入方式，比如，SOAP，REST等等，
>比如很多应用都会接受用户传入的JSON字符串，在执行json_decode()的时候也可能会遭受攻击。
>所以最根本的解决方法是让哈希表的碰撞key序列无法轻易的构造，目前PHP中还没有引入不增加额外的复杂性情况下的完美解决方案。

目前PHP中HashTable的哈希冲突解决方法就是链接法。

### 开放寻址法
通常还有另外一种解决冲突的方法：开放寻址法。使用开放寻址法是槽本身直接存放数据，
在插入数据时如果key所映射到的索引已经有数据了，这说明发生了冲突，这是会寻找下一个槽，
如果该槽也被占用了则继续寻找下一个槽，直到寻找到没有被占用的槽，在查找时也使用同样的策略来进行。

由于开放寻址法处理冲突的时候占用的是其他槽位的空间,这可能会导致后续的key在插入的时候更加容易出现
哈希冲突，所以采用开放寻址法的哈希表的装载因子不能太高，否则容易出现性能下降。

>**NOTE**
>*装载因子*是哈希表保存的元素数量和哈希表容量的比，通常采用链接法解决冲突的哈希表的装载
>因子最好不要大于1，而采用开放寻址法的哈希表最好不要大于0.5。

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
		int elem_num;
		Bucket** buckets;
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

有兴趣的读者可以运行本小节实现的哈希表实现,在输出日志中将看到很多的哈希冲突,
这是本例中使用的哈希算法过于简单造成的.

### 操作接口的实现
为了操作哈希表，实现了如下几个操作接口函数：

	[c]
	int hash_init(HashTable *ht);  								// 初始化哈希表
	int hash_lookup(HashTable *ht, char *key, void **result);	// 根据key查找内容
	int hash_insert(HashTable *ht, char *key, void *value);		// 将内容插入到哈希表中
	int hash_remove(HashTable *ht, char *key);					// 删除key所指向的内容
	int hash_destroy(HashTable *ht);

下面以初始化、插入和获取操作函数为例：

	[c]
	int hash_init(HashTable *ht)
	{
		ht->size        = HASH_TABLE_INIT_SIZE;
		ht->elem_num    = 0;
		ht->buckets     = (Bucket **)calloc(ht->size, sizeof(Bucket *));

		if(ht->buckets == NULL) return FAILED;

		LOG_MSG("[init]\tsize: %i\n", ht->size);

		return SUCCESS;
	}

初始化的主要工作是为哈希表申请存储空间，函数中使用calloc函数的目的是确保
数据存储的槽为都初始化为0，以便后续在插入和查找时确认该槽为是否被占用。

	[c]
	int hash_insert(HashTable *ht, char *key, void *value)
	{
		// check if we need to resize the hashtable
		resize_hash_table_if_needed(ht);

		int index = HASH_INDEX(ht, key);

		Bucket *org_bucket = ht->buckets[index];
		Bucket *tmp_bucket = org_bucket;

		// check if the key exits already
		while(tmp_bucket)
		{
			if(strcmp(key, tmp_bucket->key) == 0)
			{
				LOG_MSG("[update]\tkey: %s\n", key);
				tmp_bucket->value = value;

				return SUCCESS;
			}

			tmp_bucket = tmp_bucket->next;
		}

		Bucket *bucket = (Bucket *)malloc(sizeof(Bucket));

		bucket->key   = key;
		bucket->value = value;
		bucket->next  = NULL;

		ht->elem_num += 1;

		if(org_bucket != NULL)
		{
			LOG_MSG("[collision]\tindex:%d key:%s\n", index, key);
			bucket->next = org_bucket;
		}

		ht->buckets[index]= bucket;

		LOG_MSG("[insert]\tindex:%d key:%s\tht(num:%d)\n",
			index, key, ht->elem_num);

		return SUCCESS;
	}

上面这个哈希表的插入操作比较简单，简单的以key做哈希，找到元素应该存储的位置，并检查该位置是否已经有了内容，
如果发生碰撞则将新元素链接到原有元素链表头部。

由于在插入过程中可能会导致哈希表的元素个数比较多，如果超过了哈希表的容量，
则说明肯定会出现碰撞，出现碰撞则会导致哈希表的性能下降，为此如果出现元素容量达到容量则需要进行扩容。
由于所有的key都进行了哈希，扩容后哈希表不能简单的扩容，而需要重新将原有已插入的预算插入到新的容器中。

	[c]
	static void resize_hash_table_if_needed(HashTable *ht)
	{
		if(ht->size - ht->elem_num < 1)
		{
			hash_resize(ht);
		}
	}

	static int hash_resize(HashTable *ht)
	{
		// double the size
		int org_size = ht->size;
		ht->size = ht->size * 2;
		ht->elem_num = 0;

		LOG_MSG("[resize]\torg size: %i\tnew size: %i\n", org_size, ht->size);

		Bucket **buckets = (Bucket **)calloc(ht->size, sizeof(Bucket *));

		Bucket **org_buckets = ht->buckets;
		ht->buckets = buckets;

		int i = 0;
		for(i=0; i < org_size; ++i)
		{
			Bucket *cur = org_buckets[i];
			Bucket *tmp;
			while(cur)
			{
				// rehash: insert again
				hash_insert(ht, cur->key, cur->value);

				// free the org bucket, but not the element
				tmp = cur;
				cur = cur->next;
				free(tmp);
			}
		}
		free(org_buckets);

		LOG_MSG("[resize] done\n");

		return SUCCESS;
	}

哈希表的扩容首先申请一块新的内存，大小为原来的2倍，然后重新将元素插入到哈希表中，
读者会发现扩容的操作的代价为O(n)，不过这个问题不大，因为只有在到达哈希表容量的时候才会进行。


在查找时也使用插入同样的策略，找到元素所在的位置，如果存在元素，
则将该链表的所有元素的key和要查找的key依次对比， 直到找到一致的元素，否则说明该值没有匹配的内容。

	[c]
	int hash_lookup(HashTable *ht, char *key, void **result)
	{
		int index = HASH_INDEX(ht, key);
		Bucket *bucket = ht->buckets[index];

		if(bucket == NULL) goto failed;
		 
		while(bucket)
		{
			if(strcmp(bucket->key, key) == 0)
			{ 
				LOG_MSG("[lookup]\t found %s\tindex:%i value: %p\n",
					key, index, bucket->value);
				*result = bucket->value;

				return SUCCESS;
			} 

			bucket = bucket->next;
		}

	failed:
		LOG_MSG("[lookup]\t key:%s\tfailed\t\n", key);
		return FAILED;
	}


PHP中数组是基于哈希表实现的，依次给数组添加元素时，元素之间是有先后顺序的，
而这里的哈希表在物理位置上显然是接近平均分布的，这样是无法根据插入的先后顺序获取到这些元素的，
在PHP的实现中Bucket结构体还维护了另一个指针字段来维护元素之间的关系。
具体内容在后一小节PHP中的HashTable中进行详细说明。上面的例子就是PHP中实现的一个精简版。

>**NOTE**
>本小节的HashTable实例完整代码可以在$TIPI_ROOT/book/sample/chapt03/03-01-01-hashtable目录中找到。
>或者在github上浏览: <https://github.com/reeze/tipi/tree/master/book/sample/chapt03/03-01-01-hashtable>

## 参考文献
* 《Data.Structures.and.Algorithm.Analysis.in.C》
* 《算法导论: 第二版》
