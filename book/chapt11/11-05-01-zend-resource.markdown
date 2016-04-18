# PHP7 使用资源包裹第三方扩展原理分析

## 注册资源类型源码

    [c]
    ZEND_API int zend_register_list_destructors_ex(rsrc_dtor_func_t ld, rsrc_dtor_func_t pld, const char *type_name, int module_number)
    {
       zend_rsrc_list_dtors_entry *lde;
       zval zv;
     
       lde = malloc(sizeof(zend_rsrc_list_dtors_entry));
       lde->list_dtor_ex = ld;
       lde->plist_dtor_ex = pld;
       lde->module_number = module_number;
       lde->resource_id = list_destructors.nNextFreeElement;
       lde->type_name = type_name;
       ZVAL_PTR(&zv, lde);
     
       if (zend_hash_next_index_insert(&list_destructors, &zv) == NULL) {
          return FAILURE;
       }
       return list_destructors.nNextFreeElement-1;
    }
    
其中

    [c]
    ZVAL_PTR(&zv, lde);

等价于

    [c]
    zv.value.ptr = (lde);
    zv.u1.type_info = IS_PTR;
    
`list_destructors` 是一个全局静态 `HashTable`，资源类型注册时，将一个 `zval` 结构体变量 `zv` 存放入 `list_destructors` 的 `arData` 中，而 `zv` 的 `value.ptr` 却指向了 `zend_rsrc_list_dtors_entry *lde` ，`lde`中包含的该种资源释放函数指针、持久资源的释放函数指针，资源类型名称，该资源在 `hashtable` 中的索引依据 （`resource_id`）等。
而这里的 `resource_id` 则是该函数的返回值，所以后面我们在解析该类型变量时，都需要将 `resource_id` 带上。
整个的注册步骤可以总结为下图：

![图11.1 注册资源类型示意图](../images/chapt11/11-02-05-02-zend_register_list_destructors.png)

## 资源的注册

    [c]
    ZEND_API zend_resource* zend_register_resource(void *rsrc_pointer, int rsrc_type)
    {
       zval *zv;
     
       zv = zend_list_insert(rsrc_pointer, rsrc_type);
     
       return Z_RES_P(zv);
    }
    
该函数的功能则是将 `zend_list_insert` 返回的 `zval` 中的资源指针返回。`Z_RES_P` 宏在 `Zend/zend_types.h` 中定义。
重点分析 `zend_list_insert`

    [c]
    ZEND_API zval *zend_list_insert(void *ptr, int type)
    {
       int index;
       zval zv;
    
       index = zend_hash_next_free_element(&EG(regular_list));
       if (index == 0) {
          index = 1;
       }
       ZVAL_NEW_RES(&zv, index, ptr, type);
       return zend_hash_index_add_new(&EG(regular_list), index, &zv);
    }

其中`zend_hash_next_free_element`宏，返回`&EG(regular_list)`表的`nNextFreeElement`，后面用来作为索引查询的依据。
而`ZVAL_NEW_RES`宏是 PHP7 新增的一套东西，把一个资源装载到`zval`里去，因为PHP7 中`Bucket`只能存`zval`了。

    [c]
    #define ZVAL_NEW_RES(z, h, p, t) do {							\
            zend_resource *_res =									\
            (zend_resource *) emalloc(sizeof(zend_resource));		\
            zval *__z;											\
            GC_REFCOUNT(_res) = 1;									\
            GC_TYPE_INFO(_res) = IS_RESOURCE;						\
            _res->handle = (h);										\
            _res->type = (t);										\
            _res->ptr = (p);										\
            __z = (z);											\
            Z_RES_P(__z) = _res;									\
            Z_TYPE_INFO_P(__z) = IS_RESOURCE_EX;					\
        } while (0)

代码比较清晰，首先根据`h`,`p`,`t`新建了一个资源，然后一起存入了`z`这个zval的结构体。（最后两个宏前面刚刚讨论过了）
最后就是`zend_hash_index_add_new`宏了，追踪代码发现其最后等价于调用的是

    [c]
    _zend_hash_index_add_or_update_i(&EG(regular_list), index, &zv, HASH_ADD | HASH_ADD_NEW ZEND_FILE_LINE_RELAY_CC)
    
关于PHP7 `HashTable`的具体操作，这里暂不做细致的分析，后期更新前面的数据结构的章节。注册的整个逻辑如下图：

![图11.2 注册资源示意图](../images/chapt11/11-02-05-02-zend_register_resource.png)

## 解析资源源码分析

    [c]
    ZEND_API void *zend_fetch_resource(zend_resource *res, const char *resource_type_name, int resource_type)
    {
       if (resource_type == res->type) {
          return res->ptr;
       }
     
       if (resource_type_name) {
          const char *space;
          const char *class_name = get_active_class_name(&space);
          zend_error(E_WARNING, "%s%s%s(): supplied resource is not a valid %s resource", class_name, space, get_active_function_name(), resource_type_name);
       }
     
       return NULL;
    }

在上面的例子中我们是这样解析的

    [c]
    (FILE *)zend_fetch_resource(Z_RES_P(filehandle), TIPI_FILE_TYPE, le_tipi_file)
    
首先通过`Z_RES_P`宏，获取`filehandle`这个`zval`变量中的`zend_resource`。然后`zend_fetch_resource`中只是对比了`zend_resource`的`type`与我们预想的资源类型是否一致，然后返回了`zend_resource`的`*ptr`，最后转换成`FILE *`指针。
PHP7 中资源的解析比 PHP5中解析简单快捷很多，得益于其 zval 结构的改变。
原来PHP5中则需要通过`EG(regular_list)`查找，如下图所示：

![图11.3 PHP5 解析资源示意图](../images/chapt11/11-02-05-02-zend_fetch_resource-php5.png)

而现在 PHP7的解析则直接从`zval`里解析出`zend_resource`，如下图所示：

![图11.4 PHP7 解析资源示意图](../images/chapt11/11-02-05-02-zend_fetch_resource-php7.png)

## 删除资源源码分析

    [c]
    ZEND_API int zend_list_close(zend_resource *res)
    {
       if (GC_REFCOUNT(res) <= 0) {
          return zend_list_free(res);
       } else if (res->type >= 0) {
          zend_resource_dtor(res);
       }
       return SUCCESS;
    }

与PHP5 不同的地方，这里不是每次都进来将其引用计数减一操作，而是直接调用`zend_resource_dtor`函数。

    [c]
    static void zend_resource_dtor(zend_resource *res)
    {
       zend_rsrc_list_dtors_entry *ld;
       zend_resource r = *res;
    
       res->type = -1;
       res->ptr = NULL;
    
       ld = zend_hash_index_find_ptr(&list_destructors, r.type);
       if (ld) {
          if (ld->list_dtor_ex) {
             ld->list_dtor_ex(&r);
          }
       } else {
          zend_error(E_WARNING, "Unknown list entry type (%d)", r.type);
       }
    }
    
如果引用计数已经等于0或者小于0了，那么才从`EG(regular_list)`中删除

    [c]
    ZEND_API int zend_list_free(zend_resource *res)
    {
       if (GC_REFCOUNT(res) <= 0) {
          return zend_hash_index_del(&EG(regular_list), res->handle);
       } else {
          return SUCCESS;
       }
    }
    
原理图还是引用上面的注册资源类型、并注册资源的图。
先从`zend_resource`逆向通过其`type`在`list_destructors`中索引层层关联，找到该类资源的释放回调函数，然后对该资源执行释放回调函数。
而后面的从`EG(regular_list)`中删除，则是通过`res->handler`做为索引的依据。