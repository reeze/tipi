# PHP的哈希表实现

上一节已经介绍了哈希表的基本原理并实现了一个基本的哈希表，而在实际项目中，
对哈希表的需求远不止那么简单。对性能，灵活性都有不同的要求。下面我们看看PHP中的哈希表是怎么实现的。

## PHP的哈希实现

PHP内核中的哈希表是十分重要的数据结构，PHP的大部分的语言特性都是基于哈希表实现的，
例如：变量的作用域、函数表、类的属性、方法等，Zend引擎内部的很多数据都是保存在哈希表中的。

### 数据结构及说明
上一节提到PHP中的哈希表是使用拉链法来解决冲突的，具体点讲就是使用链表来存储哈希到同一个槽位的数据，
Zend为了保存数据之间的关系使用了双向列表来链接元素。

#### 哈希表结构
PHP中的哈希表实现在Zend/zend_hash.c中，还是按照上一小节的方式，先看看PHP实现中的数据结构，
PHP使用如下两个数据结构来实现哈希表，HashTable结构体用于保存整个哈希表需要的基本信息，
而Bucket结构体用于保存具体的数据内容，如下：

	[c]
	typedef struct _hashtable { 
		uint nTableSize;    	// hash Bucket的大小，最小为8，以2x增长。
		uint nTableMask;		// nTableSize-1 ， 索引取值的优化
		uint nNumOfElements; 	// hash Bucket中当前存在的元素个数，count()函数会直接返回此值 
		ulong nNextFreeElement;	// 下一个数字索引的位置
		Bucket *pInternalPointer;   // 当前遍历的指针（foreach比for快的原因之一）
		Bucket *pListHead;          // 存储数组头元素指针
		Bucket *pListTail;          // 存储数组尾元素指针
		Bucket **arBuckets;         // 存储hash数组
		dtor_func_t pDestructor;	// 在删除元素时执行的回调函数，用于资源的释放
		zend_bool persistent;       //指出了Bucket内存分配的方式。如果persisient为TRUE，则使用操作系统本身的内存分配函数为Bucket分配内存，否则使用PHP的内存分配函数。
		unsigned char nApplyCount; // 标记当前hash Bucket被递归访问的次数（防止多次递归）
		zend_bool bApplyProtection;// 标记当前hash桶允许不允许多次访问，不允许时，最多只能递归3次
	#if ZEND_DEBUG
		int inconsistent;
	#endif
	} HashTable;

nTableSize字段用于标示哈希表的容量，哈希表的初始容量最小为8。首先看看哈希表的初始化函数:

	[c]
	ZEND_API int _zend_hash_init(HashTable *ht, uint nSize, hash_func_t pHashFunction,
						dtor_func_t pDestructor, zend_bool persistent ZEND_FILE_LINE_DC)
	{
		uint i = 3;
		//...
		if (nSize >= 0x80000000) {
			/* prevent overflow */
			ht->nTableSize = 0x80000000;
		} else {
			while ((1U << i) < nSize) {
				i++;
			}
			ht->nTableSize = 1 << i;
		}
		// ...
		ht->nTableMask = ht->nTableSize - 1;

		/* Uses ecalloc() so that Bucket* == NULL */
		if (persistent) {
			tmp = (Bucket **) calloc(ht->nTableSize, sizeof(Bucket *));
			if (!tmp) {
				return FAILURE;
			}
			ht->arBuckets = tmp;
		} else {
			tmp = (Bucket **) ecalloc_rel(ht->nTableSize, sizeof(Bucket *));
			if (tmp) {
				ht->arBuckets = tmp;
			}
		}
		
		return SUCCESS;
	}

例如如果设置初始大小为10，则上面的算法将会将大小调整为16。也就是始终将大小调整为接近初始大小的
2的整数次方。

为什么会做这样的调整呢？我们先看看HashTable将哈希值映射到槽位的方法，上一小节我们使用了取模的方式来将哈希值
映射到槽位，例如大小为8的哈希表，哈希值为100， 则映射的槽位索引为: 100 % 8 = 4，由于索引通常从0开始，
所以槽位的索引值为3，在PHP中使用如下的方式计算索引：

	[c]
	h = zend_inline_hash_func(arKey, nKeyLength);
	nIndex = h & ht->nTableMask;

从上面的\_zend_hash_init()函数中可知，ht->nTableMask的大小为ht->nTableSize -1。
这里使用&操作而不是使用取模，这是因为是相对来说取模操作的消耗和按位与的操作大很多。

>**NOTE**
>mask的作用就是将哈希值映射到槽位所能存储的索引范围内。 例如：某个key的索引值是21，
>哈希表的大小为8，则mask为7，则求与时的二进制表示为： 10101 & 111 = 101 也就是十进制的5。
>因为2的整数次方-1的二进制比较特殊：后面N位的值都是1，这样比较容易能将值进行映射，
>如果是普通数字进行了二进制与之后会影响哈希值的结果。那么哈希函数计算的值的平均分布就可能出现影响。

设置好哈希表大小之后就需要为哈希表申请存储数据的空间了，如上面初始化的代码，
根据是否需要持久保存而条用了不同的内存申请方法，是需要需要持久体现的是在前面PHP生命周期里介绍的：
持久内容能在多个请求之间可访问，而如果是非持久存储则会在请求结束时释放占用的空间。
具体内容将在内存管理章节中进行介绍。 

HashTable中的nNumOfElements字段很好理解，每插入一个元素或者unset删掉元素时会更新这个字段。
这样在进行count()函数统计数组元素个数时就能快速的返回。

nNextFreeElement字段非常有用。先看一段PHP代码：

	[php]
	<?php
	$a = array(10 => 'Hello');
	$a[] = 'TIPI';
	var_dump($a);

	// ouput
	array(2) {
	  [10]=>
	  string(5) "Hello"
	  [11]=>
	  string(5) "TIPI"
	}

PHP中可以不指定索引值向数组中添加元素，这时将默认使用数字作为索引，
和[C语言中的枚举](http://en.wikipedia.org/wiki/Enumerated_type)类似，
而这个元素的索引到底是多少就由nNextFreeElement字段决定了。
如果数组中存在了数字key，则会默认使用最新使用的key + 1，例如上例中已经存在了10作为key的元素，
这样新插入的默认索引就为11了。

#### 数据容器：槽位

下面看看保存哈希表数据的槽位数据结构体：

	[c]
	typedef struct bucket {
		ulong h;            // 对char *key进行hash后的值，或者是用户指定的数字索引值
		uint nKeyLength;	// hash关键字的长度，如果数组索引为数字，此值为0
		void *pData;		// 指向value，一般是用户数据的副本，如果是指针数据，则指向pDataPtr
		void *pDataPtr;		//如果是指针数据，此值会指向真正的value，同时上面pData会指向此值
		struct bucket *pListNext;	// 整个hash表的下一元素
		struct bucket *pListLast;   // 整个哈希表该元素的上一个元素
		struct bucket *pNext;		// 存放在同一个hash Bucket内的下一个元素
		struct bucket *pLast;		// 同一个哈希bucket的上一个元素
		// 保存当前值所对于的key字符串，这个字段只能定义在最后，实现变长结构体
		char arKey[1]; 				
	} Bucket;


如上面各字段的注释。h字段保存哈希表key哈希后的值。这里保存的哈希值而不是在哈希表中的索引值，
这是因为索引值和哈希表的容量有直接关系，如果哈希表扩容了，那么这些索引还得重新进行哈希在进行索引映射，
这也是一种优化手段。
在PHP中可以使用字符串或者数字作为数组的索引。
数字索引直接就可以作为哈希表的索引，数字也无需进行哈希处理。h字段后面的nKeyLength字段是作为key长度的标示，
如果索引是数字的话，则nKeyLength为0。在PHP数组中如果索引字符串可以被转换成数字也会被转换成数字索引。
**所以在PHP中例如'10'，'11'这类的字符索引和数字索引10， 11没有区别。**

上面结构体的最后一个字段用来保存key的字符串，而这个字段却申明为只有一个字符的数组，
其实这里是一种长见的[变长结构体](http://stackoverflow.com/a/4690976/319672)，主要的目的是增加灵活性。
以下为哈希表插入新元素时申请空间的代码

	[c]
	p = (Bucket *) pemalloc(sizeof(Bucket) - 1 + nKeyLength, ht->persistent);
	if (!p) {
		return FAILURE;
	}
	memcpy(p->arKey, arKey, nKeyLength);

如代码，申请的空间大小加上了字符串key的长度，然后把key拷贝到新申请的空间里。
在后面比如需要进行hash查找的时候就需要对比key这样就可以通过对比p->arKey和查找的key是否一样来进行数据的
查找。申请空间的大小-1是因为结构体内本身的那个字节还是可以使用的。

>**NOTE**
>在PHP5.4中将这个字段定义成const char* arKey类型了。


![Zend引擎哈希表结构和关系](../images/chapt03/03-01-02-zend_hashtable.png)

上图来源于[网络](http://gsm56.com/?p=124)。

* Bucket结构体维护了两个双向链表，pNext和pLast指针分别指向本槽位所在的链表的关系。
* 而pListNext和pListLast指针指向的则是整个哈希表所有的数据之间的链接关系。
HashTable结构体中的pListHead和pListTail则维护整个哈希表的头元素指针和最后一个元素的指针。

>**NOTE**
>PHP中数组的操作函数非常多，例如：array_shift()和array_pop()函数，分别从数组的头部和尾部弹出元素。
>哈希表中保存了头部和尾部指针，这样在执行这些操作时就能在常数时间内找到目标。
>PHP中还有一些使用的相对不那么多的数组操作函数：next()，prev()等的循环中，
>哈希表的另外一个指针就能发挥作用了：pInternalPointer，这个用于保存当前哈希表内部的指针。
>这在循环时就非常有用。

如图中左下角的假设，假设依次插入了Bucket1，Bucket2，Bucket3三个元素：

1. 插入Bucket1时，哈希表为空，经过哈希后定位到索引为1的槽位。此时的1槽位只有一个元素Bucket1。
   其中Bucket1的pData或者pDataPtr指向的是Bucket1所存储的数据。此时由于没有链接关系。pNext，
   pLast，pListNext，pListLast指针均为空。同时在HashTable结构体中也保存了整个哈希表的第一个元素指针，
   和最后一个元素指针，此时HashTable的pListHead和pListTail指针均指向Bucket1。
1. 插入Bucket2时，由于Bucket2的key和Bucket1的key出现冲突，此时将Bucket2放在双链表的前面。
   由于Bucket2后插入并置于链表的前端，此时Bucket2.pNext指向Bucket1，由于Bucket2后插入。
   Bucket1.pListNext指向Bucket2，这时Bucket2就是哈希表的最后一个元素，这是HashTable.pListTail指向Bucket2。
1. 插入Bucket3，该key没有哈希到槽位1，这时Bucket2.pListNext指向Bucket3，因为Bucket3后插入。
   同时HashTable.pListTail改为指向Bucket3。

简单来说就是哈希表的Bucket结构维护了哈希表中插入元素的先后顺序，哈希表结构维护了整个哈希表的头和尾。
在操作哈希表的过程中始终保持预算之间的关系。

### 哈希表的操作接口
和上一节类似，将简单介绍PHP哈希表的操作接口实现。提供了如下几类操作接口：

* 初始化操作，例如zend_hash_init()函数，用于初始化哈希表接口，分配空间等。
* 查找，插入，删除和更新操作接口，这是比较常规的操作。
* 迭代和循环，这类的接口用于循环对哈希表进行操作。
* 复制，排序，倒置和销毁等操作。

本小节选取其中的插入操作进行介绍。
在PHP中不管是对数组的添加操作（zend_hash_add），还是对数组的更新操作（zend_hash_update），
其最终都是调用_zend_hash_add_or_update函数完成，这在面向对象编程中相当于两个公有方法和一个公共的私有方法的结构，
以实现一定程度上的代码复用。


ZEND_API int _zend_hash_add_or_update(HashTable *ht, const char *arKey, uint nKeyLength, void *pData, uint nDataSize, void **pDest, int flag ZEND_FILE_LINE_DC)
{
	 //...省略变量初始化和nKeyLength <=0 的异常处理

	h = zend_inline_hash_func(arKey, nKeyLength);
	nIndex = h & ht->nTableMask;

	p = ht->arBuckets[nIndex];
	while (p != NULL) {
		if ((p->h == h) && (p->nKeyLength == nKeyLength)) {
			if (!memcmp(p->arKey, arKey, nKeyLength)) { //  更新操作
				if (flag & HASH_ADD) {
					return FAILURE;
				}
				HANDLE_BLOCK_INTERRUPTIONS();

                //..省略debug输出

				if (ht->pDestructor) {
					ht->pDestructor(p->pData);
				}
				UPDATE_DATA(ht, p, pData, nDataSize);
				if (pDest) {
					*pDest = p->pData;
				}
				HANDLE_UNBLOCK_INTERRUPTIONS();
				return SUCCESS;
			}
		}
		p = p->pNext;
	}

	p = (Bucket *) pemalloc(sizeof(Bucket) - 1 + nKeyLength, ht->persistent);
	if (!p) {
		return FAILURE;
	}
	memcpy(p->arKey, arKey, nKeyLength);
	p->nKeyLength = nKeyLength;
	INIT_DATA(ht, p, pData, nDataSize);
	p->h = h;
	CONNECT_TO_BUCKET_DLLIST(p, ht->arBuckets[nIndex]); //Bucket双向链表操作
	if (pDest) {
		*pDest = p->pData;
	}

	HANDLE_BLOCK_INTERRUPTIONS();
	CONNECT_TO_GLOBAL_DLLIST(p, ht);    // 将新的Bucket元素添加到数组的链接表的最后面
	ht->arBuckets[nIndex] = p;
	HANDLE_UNBLOCK_INTERRUPTIONS();

	ht->nNumOfElements++;
	ZEND_HASH_IF_FULL_DO_RESIZE(ht);		/*  如果此时数组的容量满了，则对其进行扩容。*/
	return SUCCESS;
}

整个写入或更新的操作流程如下：

1. 生成hash值，通过与nTableMask执行与操作，获取在arBuckets数组中的Bucket。
1. 如果Bucket中已经存在元素，则遍历整个Bucket，查找是否存在相同的key值元素，如果有并且是update调用，则执行update数据操作。
1. 创建新的Bucket元素，初始化数据，并将新元素添加到当前hash值对应的Bucket链表的最前面（CONNECT_TO_BUCKET_DLLIST）。
1. 将新的Bucket元素添加到数组的链接表的最后面（CONNECT_TO_GLOBAL_DLLIST）。
1. 将元素个数加1，如果此时数组的容量满了，则对其进行扩容。这里的判断是依据nNumOfElements和nTableSize的大小。
如果nNumOfElements > nTableSize则会调用zend_hash_do_resize以2X的方式扩容（nTableSize << 1）。

## 哈希表的性能


## 其他语言中的HashTable实现

### Ruby使用的st库，Ruby中的两种hash实现

## 参考资料
<http://nikic.github.com/2012/03/28/Understanding-PHPs-internal-array-implementation.html>
