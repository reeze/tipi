# 链表简介

Zend引擎中实现了很多基本的数据结构，这些接口贯穿PHP和Zend引擎的始末，
这些数据结构以及相应的操作接口都可以作为通用的接口来使用。本小节再简单描述一下

在Zend引擎中HashTable的使用非常频繁，这得益于他良好的查找性能，如果读者看过
前一小节会知道哈希表会预先分配内容以提高性能，而很多时候数据规模不会很大，
固然使用哈希表能提高查询性能，但是某些场景下并不会对数据进行随机查找，
这时使用哈希表就有点浪费了。

Zend引擎中的链表是[双链表](http://zh.wikipedia.org/wiki/%E5%8F%8C%E9%93%BE%E8%A1%A8)，
通过双链表的任意节点都能方便的对链表进行遍历。

>**NOTE**
>Zend引擎的哈希表实现是哈希表和双链表的混合实现，这也是为了方便哈希表的遍历。

链表的实现很简单，通常只需要三个关键元素：

1. 指向上个元素的指针
1. 指向下个元素的指针
1. 数据容器

Zend引擎的实现也很简单，如下两个是核心的数据接口，第一个是元素节点，第二个是链表容器。

	[c]
	typedef struct _zend_llist_element {
		struct _zend_llist_element *next;
		struct _zend_llist_element *prev;
		char data[1]; /* Needs to always be last in the struct */
	} zend_llist_element;

	typedef struct _zend_llist {
		zend_llist_element *head;
		zend_llist_element *tail;
		size_t count;
		size_t size;
		llist_dtor_func_t dtor;
		unsigned char persistent;
		zend_llist_element *traverse_ptr;
	} zend_llist;


节点元素只含有前面提到的3个元素，第三个字段data和哈希表的实现一样，
是一个柔性结构体。


![Zend zend\_llist结构](../images/chapt03/03-01-03-zend_llist.png)

如上图所示，data字段的空间并不是只有一个字节，我们先看看元素插入的实现：

	[c]
	ZEND_API void zend_llist_add_element(zend_llist *l, void *element)
	{
		zend_llist_element *tmp = pemalloc(sizeof(zend_llist_element)+l->size-1, l->persistent);

		tmp->prev = l->tail;
		tmp->next = NULL;
		if (l->tail) {
			l->tail->next = tmp;
		} else {
			l->head = tmp;
		}
		l->tail = tmp;
		memcpy(tmp->data, element, l->size);

		++l->count;
	}

如方法第一行所示，申请空间是额外申请了`l->size - 1`的空间。`l->size`是在链表创建时指定的，
`zend_llist_element`结构体最后那个字段的注释提到这个字段必须放到最后也是这个原因，
例如curl扩展中的例子：`zend_llist_init(&(*ch)->to_free->slist, sizeof(struct curl_slist), (llist_dtor_func_t) curl_free_slist,  0);`, `size`指的是要插入元素的空间大小，这样不同的链表就可以插入不同大小的元素了。

为了提高性能增加了链表头和尾节点地址，以及链表中元素的个数。

最后的traverse\_ptr 字段是为了方便在遍历过程中记录当前链表的内部指针，
和哈希表中的:`Bucket *pInternalPointer;`字段一个作用。

## 操作接口
操作接口比较简单，本文不打算介绍接口的使用，这里简单说一下PHP源代码中的一个小的约定，

如下为基本的链表遍历操作接口：

	[c]
	/* traversal */
	ZEND_API void *zend_llist_get_first_ex(zend_llist *l, zend_llist_position *pos);
	ZEND_API void *zend_llist_get_last_ex(zend_llist *l, zend_llist_position *pos);
	ZEND_API void *zend_llist_get_next_ex(zend_llist *l, zend_llist_position *pos);
	ZEND_API void *zend_llist_get_prev_ex(zend_llist *l, zend_llist_position *pos);

	#define zend_llist_get_first(l) zend_llist_get_first_ex(l, NULL)
	#define zend_llist_get_last(l) zend_llist_get_last_ex(l, NULL)
	#define zend_llist_get_next(l) zend_llist_get_next_ex(l, NULL)
	#define zend_llist_get_prev(l) zend_llist_get_prev_ex(l, NULL)

一般情况下我们遍历只需要使用后面的那组宏定义函数即可，如果不想要改变链表内部指针，
可以主动传递当前指针所指向的位置。

PHP中很多的函数都会有`*_ex()`以及不带ex两个版本的函数，这主要是为了方便使用，
和上面的代码一样，ex版本的通常是一个功能较全或者可选参数较多的版本，
而在代码中很多地方默认的参数值都一样，为了方便使用，再封装一个普通版本。

这里之所以使用宏而不是定义另一个函数是为了避免函数调用带来的消耗，
不过有的情况下还要进行其他的操作，也是会再定义一个新的函数的。
