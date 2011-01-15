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
	`Z_TYPE_P(z)=IS_NULL;`
	</td>
</tr>
</tbody>
</table>

* 标量类型： *boolean*   *integer*   *float(double)*   *string*
* 复合类型： *array*   *object*
* 特殊类型： *resource*   *NULL*

	typedef struct _hashtable {
		uint nTableSize;
		uint nTableMask;
		uint nNumOfElements;
		ulong nNextFreeElement;
		Bucket *pInternalPointer;   /* Used for element traversal */
		Bucket *pListHead;
		Bucket *pListTail;
		Bucket **arBuckets;
		dtor_func_t pDestructor;
		zend_bool persistent;
		unsigned char nApplyCount;
		zend_bool bApplyProtection;
	#if ZEND_DEBUG
		int inconsistent;
	#endif
	} HashTable;


### 3.一些操作宏

