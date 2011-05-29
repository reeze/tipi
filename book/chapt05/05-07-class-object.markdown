# 第七节 对象

对象是我们可以进行研究的任何事物，世间万物都可以看作对象。它不仅可以表示我们可以看到的具体事物，
也可以表示那些我们看不见的事件等。对象是一个实体，它具有状态，一般我们用变量来表示，
同时它也可以具有操作行为，一般用方法来表示，对象就是对象状态和对象行为的集合体。

在之前我们很多次的说到类，对于对象来说，具有相同或相似性质的对象的抽象就是类。
因此，对象的抽象是类，类的具体化就是对象，我们常常也说对象是类的实例。
从对象的表现形式来看，它和一般的数据类型在形式上十分相似，但是它们在本质是不同的。
对象拥有方法，对象间的通信是通过方法调用，以一种消息传递的方式进行。
而我们常说的面向对象编程(OOP)使得对象具有交互能力的主要模型就是消息传递模型。
对象是消息传递的主体，它可以接收，也可以拒绝外界发来的消息。

这一小节，我们从源码结构来看看PHP实现对象的方法以及其消息传递的方式。

## 对象的结构

在第三章[<< 第一节 变量的内部结构 >>][variables-structure]中提到：对象在PHP中是使用一种zend_object_value的结构体来存储。

    [c]
    typedef struct _zend_object_value {
        zend_object_handle handle;
            //  unsigned int类型，EG(objects_store).object_buckets的索引
        zend_object_handlers *handlers;
    } zend_object_value;

PHP内核会将所有的对象存放在一个对象列表容器中，这个列表容器是保存在EG(objects_store)里的一个全局变量。
上面的handle字段就是这个列表中object_buckets的索引。当我们需要在PHP中存储对象的时候，
PHP内核会根据handle索引从对象列表中获取相对应的对象。而获取的对象有其独立的结构，如下代码所示：

    [c]
    typedef struct _zend_object {
        zend_class_entry *ce;
        HashTable *properties;
        HashTable *guards; /* protects from __get/__set ... recursion */
    } zend_object;

ce是存储该对象的类结构，properties是一个HashTable，用来存放对象的属性。

在zend_object_value结构体中除了索引字段外还有一个包含对象处理方法的字段：handlers。
它的类型是zend_object_handlers，我们可以在Zend/zend_object_handlers.h文件中找到它的定义。
这是一个包含了多个指针函数的结构体，这些指针函数包括对对象属性的操作，对对象方法的操作，克隆等。
此字段会在对象创建的时候初始化。

## 对象的创建

在PHP代码中，对象的创建是通过关键字 **new** 进行的。从此关键字出发，我们遍历词法分析，语法分析和编译成中间代码等过程，
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

zend_objects_new函数会初始化对象自身的相关信息，包括对象归属于的类，对象实体的存储索引，对象的相关处理函数。
在这里将对象放入对象池中的函数为zend_objects_store_put。

在将对象放入对象池，返回对象的存放索引后，程序设置对象的处理函数为标准对象处理函数：std_object_handlers。
其位于Zend/zend_object_handles.c文件中。

### 对象池

这里针对对象，我们引入一个新的概念--对象池。
我们将PHP内核在运行中存储所有对象的列表称之为对象池，即EG(objects_store)。
这个对象池的作用是存储PHP中间代码运行阶段所有生成的对象，这个思想有点类似于我们做数据库表设计时，
当一个实例与另一个实体存在一对多的关系时，将多的那一端对应的实体提取出来存储在一个独立的表一样。
这样做的好处有两个，一个是可以对象复用，另一个是节省内存，特别是在对象很大，并且我们不需要用到对象的所有信息时。
对象池的存储结构为zend_objects_store结构体，如下;

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

针对对象池，PHP内核有一套对象操作API，位于Zend/zend_objects_API.c文件，其列表如下：

* zend_objects_store_init 对象池初始化操作，它的执行阶段是请求初始化阶段，
    执行顺序是：[php_request_startup] --> [php_start_sapi] --> [zend_activate] --> [init_executor]
    初始化时，它会分配1024个zend_object_store_bucket给对象池。
* zend_objects_store_destroy　销毁对象池，调用efree释放内存
* zend_objects_store_mark_destructed 标记所有对象已经调用了析构函数
* zend_objects_store_free_object_storage 释放存储的对象
* zend_objects_store_put 对象的添加API，在此函数中，程序会执行单个bucket的初始化操作
* zend_objects_store_get_refcount　获取对象池中对象的引用计数
* zend_objects_store_add_ref　对象的引用计数加1，传入值为对象
* zend_objects_store_add_ref_by_handle 通过handle查找对象，并将其引用计数加1
* zend_objects_store_del_ref 对象的引用计数减1，传入值为对象
* zend_objects_store_del_ref_by_handle_ex 通过handle查找对象，并将其引用计数减1，对于引用计数为1的对象有清除处理
* zend_objects_store_clone_obj 对象克隆API，构造一个新的bucket，并将新的对象添加到对象池
* zend_object_store_get_object  获取对象池中bucket中的对象，传入值为对象
* zend_object_store_get_object_by_handle 获取对象池中bucket中的对象，传入值为索引值

## 成员变量
从前面的对象结构来看，对象的成员变量存储在properties参数中。并且每个对象都会有一套标准的操作函数，
如果需要获取成员变量，对象最后调用的是read_property，其对应的标准函数为zend_std_read_property;
如果需要设置成员变量，对象最后调用的是write_property，其对应的标准函数zend_std_write_property。
这些函数都是可以定制的，如果有不同的需求，可以通过设置对应的函数指针替换。如在dom扩展中，它的变量的获取函数和设置函数都是定制的。

    [c]
    /* {{{ PHP_MINIT_FUNCTION(dom) */
    PHP_MINIT_FUNCTION(dom)
    {
        zend_class_entry ce;

        memcpy(&dom_object_handlers, zend_get_std_object_handlers(), sizeof(zend_object_handlers));
        dom_object_handlers.read_property = dom_read_property;
        dom_object_handlers.write_property = dom_write_property;
        //  ...省略
    }

以上是dom扩展的模块初始化函数的部分内容，在这里，它替换了对象的read_property方法等。

这里我们以标准的操作函数为例说明成员变量的读取和获取。成员变量的获取最终调用的是zend_std_read_property函数。
这个函数的流程是这样的：

* 第一步，获取对象的属性，如果存在，转第二步；如果没有相关属性，转第三步
* 第二步，从对象的properties查找是否存在与名称对应的属性存在，如果存在返回结果，如果不存在，转第三步
* 第三步，如果存在__get魔术方法，则调用此方法获取变量，如果不存在，转第四步
* 第四步，如果type=BP_VAR_IS，返回 &EG(uninitialized_zval_ptr)，否则报错

成员变量的设置最终调用的是zend_std_write_property函数。整个执行流程如下：

* 第一步，获取对象的属性，如果存在，转第二步；如果没有相关属性，转第四步
* 第二步，从对象的properties查找是否存在与名称对应的属性存在，如果存在，转第三步，如果不存在，转第四步
* 第三步，如果已有的值和需要设置的值相同，则不执行任何操作，否则执行变量赋值操作，
此处的变量赋值操作和常规的变量赋值类似，有一些区别，这里只处理了是否引用的问题
* 第四步，如果存在__set魔术方法，则调用此方法设置变量，如果不存在，转第五步
* 第五步，如果成员变量一直没有被设置过，则直接将此变量添加到对象的properties字段所在HashTable中。

## 成员方法
成员方法又包括常规的成员方法和魔术方法。魔术方法在前面的第五小节已经介绍过了，这里就不再赘述。
在对象的标准函数中并没有成员方法的调用函数，默认情况下设置为NULL.在SPL扩展中，有此函数的调用设置，如下代码：

    [c]
    PHP_MINIT_FUNCTION(spl_iterators)
    {
        // ...省略
        memcpy(&spl_handlers_dual_it, zend_get_std_object_handlers(), sizeof(zend_object_handlers));
        spl_handlers_dual_it.get_method = spl_dual_it_get_method;
        /*spl_handlers_dual_it.call_method = spl_dual_it_call_method;*/
        spl_handlers_dual_it.clone_obj = NULL;

        // ...省略
    }

以下面的PHP代码为例，我们看看成员方法的调用过程：

    [php]
    class Tipi {
        public function t() {
            echo 'tipi';
        }
    }

    $obj = new Tipi();
    $obj->t();

这是一个简单的类实现，它仅有一个成员方法叫t. 创建一个此类的实例，将其赋值给变量$obj，通过这个对象变量执行其成员方法。
使用VLD扩展查看其生成的中间代码，可以知道其过程分为初始化成员方法的调用，执行方法两个过程。
初始化成员方法的调用对应的中间代码为ZEND_INIT_METHOD_CALL，
从我们的调用方式（一个为CV，一个为CONST）可知其对应的执行函数为 **ZEND_INIT_METHOD_CALL_SPEC_CV_CONST_HANDLER**
此函数的调用流程如下：

* 第一步，处理调用的方法名，获取其值，并做检验处理：如果不是字符串，则报错
* 第二步，如果第一个操作数是对象，则转第三步，否则报错 Call to a member function t on a non-object
* 第三步，调用对象的get_method函数获取成员方法
* 第四步，其它处理，包括静态方法，this变量等。

而get_method函数一般是指标准实现中的get_method函数，其对应的具体函数为Zend/zend_object_handlers.c文件中zend_std_get_method函数。
zend_std_get_method函数的流程如下：

* 第一步，从zobj->ce->function_table中查找是否存在需要调用的函数，如果不存在，转第二步，如果存在，转第三步
* 第二步，如果__call函数存在，则调用zend_get_user_call_function函数获取并返回，如果不存在，则返回NULL
* 第三步，检查方法的访问控制，如果为私有函数，转第四步，否则转第五步
* 第四步，如果为同一个类或父类和这个方法在同一个作用域范围，则返回此方法，否则判断__call函数是否存在，存在则调用此函数，否则报错
* 第五步，处理函数重载及访问控制为protected的情况。 转第六步
* 第六步，返回fbc

在获得了函数的信息后，下面的操作就是执行了，关于函数的执行在第四章已经介绍过了。



[variables-structure]: 	?p=chapt03/03-01-00-variables-structure
