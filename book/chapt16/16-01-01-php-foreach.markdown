# 第十六章第一节 foreach的实现

foreach是PHP的关键字，用来实现基于数据的循环。
基于数据循环语句的循环是由数据结构中的元素的数目来控制的。
一般来说，基于数据的循环语句会使用一种称之为迭代器的函数来实现元素的遍历。

除了foreach，PHP还提供了预定义的一些函数来实现对数组的迭代访问操作，如current， next， reset等等。
然而我们使用得最多的还是foreach语句，foreach可以直接迭代访问数组，如果用户自己定义的对象需要使用此语句进行迭代访问，必须实现SPL的迭代器。

这一小节，我们具体介绍PHP中foreach的实现过程。
foreach 语法结构提供了遍历数组的简单方式。
foreach 仅能够应用于数组和对象，如果尝试应用于其他数据类型的变量，或者未初始化的变量，将导致错误。
foreach每次循环时，当前单元的值被赋给 $value 并且数组内部的指针向前移一步（因此下一次循环中将会得到下一个单元）。

## 循环过程的实现
foreach语句在语法解析时对应三个操作:

1. zend_do_foreach_begin： 循环开始操作，生成FE_RESET中间代码，数组会在循环开始时执行RESET操作，
即我们使用foreach遍历时不用每次重新手动RESET，同时此操作也会生成获取变量的FE_FETCH中间代码。
1. zend_do_foreach_cont：根据需要获取变量的状态判断是否引用，
此处的引用会影响FE_RESET的初始化操作和FE_FETCH中间代码的获取变量操作。
1. zend_do_foreach_end：设置ZEND_JMP中间代码，设置下一条OP，以跳出循环，结束循环，清理工作。

这三个操作都是语法解析时对应的函数名，在编译过程中会直接调用。
他们形成的中间代码在PHP内核执行时，形成的循环遍历效果是：在foreach遍历之前, PHP内核首先会有个FE_RESET操作来重置数组的内部指针，
也就是pInternalPointer, 然后通过每次FE_FETCH将pInternalPointer指向数组的下一个元素，从而实现顺序遍历。
并且每次FE_FETCH的结果都会被一个全局的中间变量存储，以给下一次的获取元素使用。

如下面这段代码：

    [php]
    $arr = array(1, 2, 3, 4, 5);

    foreach ($arr as $key => $row) {
        echo $key , $row;
    }

这是一个标准的foreach循环使用示例。在VLD扩展中我们可以看到如下的中间代码：

    [shell]
    number of ops:  16
    compiled vars:  !0 = $arr, !1 = $key, !2 = $row
    line     # *  op                           fetch          ext  return  operands
    ---------------------------------------------------------------------------------
       2     0  >   INIT_ARRAY                                       ~0      1
             1      ADD_ARRAY_ELEMENT                                ~0      2
             2      ADD_ARRAY_ELEMENT                                ~0      3
             3      ADD_ARRAY_ELEMENT                                ~0      4
             4      ADD_ARRAY_ELEMENT                                ~0      5
             5      ASSIGN                                                   !0, ~0
       4     6    > FE_RESET                                         $2      !0, ->14
             7  > > FE_FETCH                                         $3      $2, ->14
             8  >   ZEND_OP_DATA                                     ~5
             9      ASSIGN                                                   !2, $3
            10      ASSIGN                                                   !1, ~5
       5    11      ECHO                                                     !1
            12      ECHO                                                     !2
       6    13    > JMP                                                      ->7
            14  >   SWITCH_FREE                                              $2
       7    15    > RETURN                                                   1

当我们通过RESET初始化数组后，FETCH会获取变量，并将数组的内部指针指向一个元素。
在前面我们讲过，常规情况下OPCODE的执行是一条一条依次执行的，则在FE_FETCH获取完变量后，PHP内核会依次执行后续的OPCODE，
当执行到JMP时，会重新跳到->7，即再一次获取变量，如此构成一个循环。
当FE_FETCH执行失败时，会跳转到->14，即SWITCH_FREE，从而结束整个循环。

## 指针的意外行为
在PHP手册中有这样一个NOTE:

>**NOTE**
>Note:
>当 foreach 开始执行时，数组内部的指针会自动指向第一个单元。这意味着不需要在 foreach 循环之前调用 reset()。
>由于 foreach 依赖内部数组指针，在循环中修改其值将可能导致意外的行为。

比如这样一段代码：

    [php]
    $arr = array(1,2,3,4,5);

    foreach($arr as $key => $row) {
        echo key($arr), '=>', current($arr), "\r\n";
    }

如果在$row加上引用呢？如果在遍历前添加 **$g = $arr;** 呢？
结果是上面示例的代码只会输出数组的第二个元素。修改建议的代码会依次输出变量，但是第一个元素并没有在输出结果中出现。

这个异常引申出三个问题：

1. 为什么foreach循环体中执行key或current会显示第二个元素（非引用情况）？
以key函数为例，我们执行函数调用时，会执行中间代码SEND_REF，此中间代码会将没有设置引用的变量复制一份并设置为引用。
当进入循环体时，PHP内核已经经过了一次fetch操作，相当于执行了一次next操作，当前元素指向第二个元素。
因此我们在foreach的循环体中执行key函数时，key中调用的数组变量为PHP执行了一次fetch操作的数组拷贝，此时foreach的内部指针指向第二个元素。

1. 为什么在foreach中执行end等操作，其循环过程不变？
在遍历的代码中通过end，next等操作数组的指针，数组的指针不会变化，这是因为在PHP内核进行FETCH操作时，
会通过中间变量存储当前操作数组的内部指针，每遍历一个元素，会先获取之前存储的指针位置，获取下一个元素后，再恢复指针位置，
关键在于FETCH OPCODE执行过程中的中间变量。

1. 为什么$row的引用和非引用情况下输出结果不同？
如果是引用，PHP内核在reset数组时，会直接分裂数组，生成一个数组的拷贝，并将其设置为引用。
如果是非引用，PHP内核在reset数组时，当数组的引用计数大于1，并且不存在引用时，会拷贝数组供foreach使用，其它情况使用原数组，将其引用计数加1。
因为引用的不同，在循环体中给函数传递参数时其结果不同，导致看到的foreach数组内部指针变化的不同。
对于非引用且引用计数大于1的情况，其本身就是两个不同的数组，在RESET时就不同了。

