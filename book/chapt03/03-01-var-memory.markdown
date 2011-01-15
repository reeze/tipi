# 第一节 变量的内存表示

在PHP中，所有的变量都保存在zval结构中，也就是说，zval使用同一种结构存储了包括int、array、string等不同数据类型。那么，zval是如何做到的呢，下面我们一起来揭开面纱。

### 一.PHP变量类型在PHP内核中的表示方法：
PHP是一种弱类型的语言，这就意味着在声明或使用变量的时候，并不需要显式指明其数据类型。但是，PHP是由Ｃ来实现的，大家都知道Ｃ对变量的类型管理是非常严格的，强类型的Ｃ是这样实现弱类型的PHP变量类型的:
#### 1.在PHP中，存在8种变量类型，可以分为三类:
* 标量类型： *boolean*   *integer*   *float(double)*   *string*
* 复合类型： *array*   *object*
* 特殊类型： *resource*   *NULL*

在变量声明的开始，ZE判断用户变量的类型，并存入到以下zval结构体中：

	[c]
	typedef struct _zval_struct zval;
	...
	struct _zval_struct {
		/* Variable information */
		zvalue_value value;     /* value */
		zend_uint refcount__gc;
		zend_uchar type;    /* active type */
		zend_uchar is_ref__gc;
	};

<br />

#### 2.初始化变量类型:
在上面的结构体中有四个值，其含义为：

| 属性名  |  含义 | 默认值 |
|:------------|:------------|:----------------:|
|refcount__gc      |	表示引用计数|1 	|
|is_ref__gc	  | 表示是否为引用|0	|
|value 		  |	存储着变量的值信息			||
|type 		  |	记录变量的内部类型			||

在变量的初始化过程中，ZE会将变量的类型（type）值根据其变量类型置为：IS_NULL, IS_BOOL, IS_LONG, IS_DOUBLE, IS_STRING, IS_ARRAY, IS_OBJECT, IS_RESOURCE 之一。


>Question:  PHP的实现中，如何判断变量是属于哪种类型的呢？(下节介绍）

<br />

### 二.变量的值在_zval_value中的存储：

在上面大家不难发现，所有的php变量都是存储于zval结构中，其中变量值存储在zvalue_value联合体中：

	[c]
	typedef union _zvalue_value {
		long lval;                  /* long value */
		double dval;                /* double value */
		struct {
			char *val;
			int len;
		} str;
		HashTable *ht;              /* hash table value */
		zend_object_value obj;
	} zvalue_value;

	
各种类型的数据会使用不同的方法来进行变量值的存储，其对应变量的赋值方式：
	
* 一般类型

<table>
<thead>
<tr>
  <th align="left">变量类型</th>
  <th align="center">宏</th>
  <th align="left"></th>
</tr>
</thead>
<tbody>
<tr>
  <td align="left">boolean</td>
  <td align="left">ZVAL_BOOL</td>
  <td align="left" rowspan='3'>
	布尔型/整型的变量值存储于(zval).value.lval中,其类型也会以相应的IS_*进行存储。
	<pre class="c"> Z_TYPE_P(z)=IS_BOOL/LONG;  Z_LVAL_P(z)=((b)!=0); </pre>
</td>
</tr>
<tr>
  <td align="left">integer</td>
  <td align="left">ZVAL_LONG</td>
</tr>
<tr>
  <td align="left">float</td>
  <td align="left">ZVAL_DOUBLE</td>
</tr>
<tr>
  <td align="left">null</td>
  <td align="left">ZVAL_NULL</td>
  <td align="left" >
	NULL值的变量值不需要存储，只需要把(zval).type标为IS_NULL。
	<pre class="c"> Z_TYPE_P(z)=IS_NULL; </pre>
	</td>
</tr>
<tr>
  <td align="left">resource</td>
  <td align="left">ZVAL_RESOURCE</td>
  <td align="left" >
	资源类型的存储与其他一般变量无异，但其初始化及存取实现则不同。
	<pre class="c"> Z_TYPE_P(z) = IS_RESOURCE;  Z_LVAL_P(z) = l; </pre>
	</td>
</tr>
</tbody>
</table>

<br />

* 字符串Sting

字符串类型的存储有别于上述一般类型，因为Ｃ中的字符串变量实际上是指向一个字符数组的头指针。所以，PHP在实现字符串变量时，也采用指针的方式，在_zvalue_value数据结构中，存在下面的结构体内，其中*val就存储了指向字符串的指针，而len则存储了字符的长度。

	[c]
	struct {
		char *val;
		int len;
	} str;

>**NOTE**
> 从这里可以看出strlen()函数是不会重新计算字符串长度的，只是返回str结构体中的len的值。

<br />

* 数组Array

数组是PHP中最常用，也是最强大变量类型，它可以存储其他类型的数据，而且提供各种内置操作函数。数组的存储相对于其他变量要复杂一些，需要使用其它两种数据结构HashTable和Bucket。

	[c]
	typedef struct _hashtable { 
		uint nTableSize;    	// hash Bucket的大小,最小为8,以2x增长。
		uint nTableMask;		// nTableSize-1 ， 索引取值的优化
		uint nNumOfElements; 	// hash Bucket中当前存在的元素个数, count()函数会直接返回此值 
		ulong nNextFreeElement;	// 标记hash Bucket当前索引数
		Bucket *pInternalPointer;   // 当前遍历的指针（foreach比for快的原因之一）
		Bucket *pListHead;          // 存储数组头元素指针
		Bucket *pListTail;          // 存储数组尾元素指针
		Bucket **arBuckets;         // 存储hash数组
		dtor_func_t pDestructor;
		zend_bool persistent;
		unsigned char nApplyCount; // 标记当前hash Bucket被递归访问的次数（防止多次递归）
		zend_bool bApplyProtection;// 标记当前hash桶允许不允许多次访问，不允许时，最多只能递归3次
	#if ZEND_DEBUG
		int inconsistent;
	#endif
	} HashTable;

	....

	typedef struct bucket {
		ulong h;            //对char *key进行hash后的值,或者是用户指定的数字索引值
		uint nKeyLength;	//hash关键字的长度,如果数组索引为数字，此值为0
		void *pData;		//指向value，一般是用户数据的副本,如果是指针数据，则指向pDataPtr
		void *pDataPtr;		//如果是指针数据,此值会指向真正的value,同时上面pData会指向此值
		struct bucket *pListNext;	//整个hash表的下一元素
		struct bucket *pListLast;
		struct bucket *pNext;		//存放在同一个hash Bucket内的下一个元素
		struct bucket *pLast;
		char arKey[1]; 	
		/*存储字符索引，此项必须放在最未尾，因为此处只字义了1个字节，存储的实际上是指向char*key的值，
		这就意味着可以省去再赋值一次的消耗，而且，有时此值并不需要，所以同时还节省了空间。
		*/
	} Bucket;

从代码中不难发现，数组的存储是由_zval_struct ， _zvalue_value，HashTable，Bucket 共同完成的。上面的注释中标出了结构中的主要属性的作用。






	






