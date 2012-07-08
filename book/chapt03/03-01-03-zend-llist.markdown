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

如方法第一行所示，申请空间是额外申请了`l->size - 1`的空间。`l->size`是在链表创建是指定的，
`zend_llist_element`结构体最后那个字段的注释提到这个字段必须放到最后也是这个原因，
例如curl扩展中的例子：`zend_llist_init(&(*ch)->to_free->slist, sizeof(struct curl_slist), (llist_dtor_func_t) curl_free_slist,  0);`, `size`指的是要插入元素的空间大小，这样不同的链表就可以插入不同大小的元素了。

为了提高性能增加了链表头和尾节点地址，以及链表中元素的个数。

最后的traverse\_ptr 字段是为了方便在遍历过程中记录当前链表的内部指针，
和哈希表中的:`Bucket *pInternalPointer;`字段一个作用。

## 操作接口