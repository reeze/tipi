# 第七节 对象

对象是我们可以进行研究的任何事物，世间万物都可以看作对象。它不仅可以表示我们可以看到的具体事物，也可以表示那些我们看不见的事件等
对象是一个实体，它具有状态，一般我们用变量来表示，同时它也可以具有操作行为，一般用方法来表示，对象就是对象状态和对象行为的集合体。

在之前我们很多次的说到类，对于对象来说，具有相同或相似性质的对象的抽象就是类。
因此，对象的抽象是类，类的具体化就是对象，我们常常也说对象是类的实例。
从对象的表现形式来看，它和一般的数据类型在形式上十分相似，但是它们在本质是不同的。
对象拥有方法，对象间的通信是通过方法调用，以一种消息传递的方式进行。
而我们常说的面向对象编程(OOP)使得对象具有交互能力的主要模型就是消息传递模型。
对象是消息传递的主体，它可以接收，也可以拒绝外界发来的消息。

这一小节，我们从源码结构来看看PHP实现对象的方法以及其消息传递的方式。

## 对象的结构
在第三章[<< 第一节 变量的内部结构 >>][variables-structure]中提到：对象在PHP中是使用一种zend_object_value的结构体来存放。

    [c]
    typedef struct _zend_object_value {
        zend_object_handle handle;
            //  unsigned int类型，EG(objects_store).object_buckets的索引
        zend_object_handlers *handlers;
    } zend_object_value;

PHP内核会将所有的对象存放在一个对象列表容器中，这个列表容器在代码中的体现是EG(objects_store).
而上面的handle字段就是这个列表中object_buckets的索引。
当我们需要在PHP中存储对象的时候，
PHP内核会根据handle索引从对象列表中获取相对应的对象。而获取的对象有其独立的结构，如下代码所示：

    [c]
    typedef struct _zend_object {
        zend_class_entry *ce;
        HashTable *properties;
        HashTable *guards; /* protects from __get/__set ... recursion */
    } zend_object;

ce是存放该对象的类结构，properties是一个HashTable，用来存放对象的属性。

在zend_object_value结构体中除了索引字段外还有一个包含对象处理方法的字段：handlers。
它的类型是zend_object_handlers，我们可以在Zend/zend_object_handlers.h文件中找到它的定义。
这是一个包含了多个指针函数的结构体，这些指针函数包括对对象属性的操作，对对象方法的操作，克隆等。
此字段会在对象创建的时候初始化。

## 对象的创建

在PHP代码中，对象的创建是以关键字 **new** 来体现的。从此关键字出发，我们遍历词法分析，语法分析和编译成中间代码等过程，
得到其最后执行的函数为 **ZEND_NEW_SPEC_HANDLER** 。

ZEND_NEW_SPEC_HANDLER函数首先会判断对象所对应的类是否为可实例化的类，
即判断类的ce_flags是否与ZEND_ACC_INTERFACE、ZEND_ACC_IMPLICIT_ABSTRACT_CLASS或ZEND_ACC_EXPLICIT_ABSTRACT_CLASS有交集，
即判断类是否为接口或抽象类。

>**NOTE**
>此处的抽象类包括直接声明的抽象类或因为包含了抽象方法而被声明的抽象类

在类的类型判断完成后，如果一切正常，程序会给需要创建的对象存放的ZVAL容器分配内存。
然后调用object_init_ex方法初始化类，其调用顺序为：
[object_init_ex()] --> [_object_init_ex()] --> [_object_and_properties_init()]

在_object_and_properties_init函数中，程序会执行前面提到的类的类型的判断，然后更新类的静态变量等信息（在这前面的章节有说明），
更新完成后，程序会设置zval的类型为IS_OBJECT.
    
    [c]
    Z_TYPE_P(arg) = IS_OBJECT;

在设置了类型之后，程序会执行zend_object类型的对象的初始化工作，此时调用的函数是zend_objects_new。

    [c]
    ZEND_API zend_object_value zend_objects_new(zend_object **object, zend_class_entry *class_type TSRMLS_DC)
    {
        zend_object_value retval;

        *object = emalloc(sizeof(zend_object));
        (*object)->ce = class_type;
        retval.handle = zend_objects_store_put(*object, (zend_objects_store_dtor_t) zend_objects_destroy_object, (zend_objects_free_object_storage_t) zend_objects_free_object_storage, NULL TSRMLS_CC);
        retval.handlers = &std_object_handlers;
        (*object)->guards = NULL;
        return retval;
    }

这里初始化了对象本身的相关信息，包括对象归属于的类，对象实体的存放索引，对象的相关处理函数。
在这里将对象放入对象池中的函数为zend_objects_store_put。

在将对象放入对象池，返回对象的存放索引后，程序设置对象的处理函数为标准对象处理函数：std_object_handlers。
其位于Zend/zend_object_handles.c文件中。

### 对象池

这里针对对象，我们引入一个新的概念--对象池。
我们将PHP内核在运行中存储所有对象的列表称之为对象池，即EG(objects_store)。而它所对应的结构为zend_objects_store;

    [c]
    typedef struct _zend_objects_store {
        zend_object_store_bucket *object_buckets;
        zend_uint top;
        zend_uint size;
        int free_list_head;
    } zend_objects_store;

    typedef struct _zend_object_store_bucket {
        zend_bool destructor_called;
        zend_bool valid;
        union _store_bucket {
            struct _store_object {
                void *object;
                zend_objects_store_dtor_t dtor;
                zend_objects_free_object_storage_t free_storage;
                zend_objects_store_clone_t clone;
                const zend_object_handlers *handlers;
                zend_uint refcount;
                gc_root_buffer *buffered;
            } obj;
            struct {
                int next;
            } free_list;
        } bucket;
    } zend_object_store_bucket;

它位于Zend/zend_objects_API.c文件中。
与此函数相似的函数还有许多，它们都是操作对象的API，其列表如下：

* zend_objects_store_init
* zend_objects_store_destroy
* zend_objects_store_call_destructors
* zend_objects_store_mark_destructed
* zend_objects_store_free_object_storage
* zend_objects_store_get_refcount
* zend_objects_store_add_ref
* zend_objects_store_add_ref_by_handle
* zend_objects_store_del_ref
* zend_objects_store_del_ref_by_handle_ex
* zend_objects_store_clone_obj
* zend_object_store_get_object
* zend_object_store_get_object_by_handle
* zend_object_store_set_object
* zend_object_store_ctor_failed
* zend_objects_proxy_free_storage
* zend_objects_proxy_clone
* zend_object_create_proxy



    [c]
    ZEND_API zend_object_handlers std_object_handlers = {
        zend_objects_store_add_ref,				/* add_ref */
        zend_objects_store_del_ref,				/* del_ref */
        zend_objects_clone_obj,					/* clone_obj */

        zend_std_read_property,					/* read_property */
        zend_std_write_property,				/* write_property */
        zend_std_read_dimension,				/* read_dimension */
        zend_std_write_dimension,				/* write_dimension */
        zend_std_get_property_ptr_ptr,			/* get_property_ptr_ptr */
        NULL,									/* get */
        NULL,									/* set */
        zend_std_has_property,					/* has_property */
        zend_std_unset_property,				/* unset_property */
        zend_std_has_dimension,					/* has_dimension */
        zend_std_unset_dimension,				/* unset_dimension */
        zend_std_get_properties,				/* get_properties */
        zend_std_get_method,					/* get_method */
        NULL,									/* call_method */
        zend_std_get_constructor,				/* get_constructor */
        zend_std_object_get_class,				/* get_class_entry */
        zend_std_object_get_class_name,			/* get_class_name */
        zend_std_compare_objects,				/* compare_objects */
        zend_std_cast_object_tostring,			/* cast_object */
        NULL,									/* count_elements */
        NULL,									/* get_debug_info */
        zend_std_get_closure,					/* get_closure */
    };






## 成员变量

### 设置成员变量

### 获取成员变量

## 成员方法



## 对象的拷贝

##


[variables-structure]: 	?p=chapt03/03-01-00-variables-structure