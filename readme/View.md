## 视图(Acast\\View)

[返回主页](../Readme.md)

### 规则

用户自定义的控制器应该继承Acast\\View，命名空间应为$app\\View。

视图会在控制器的构造函数被调用时被自动加载。

### 注册视图

> static function View::register(string $name, $data, bool $use_shm = false) void

1. $name为视图名。

2. $data为视图内容，可以为字符串或者对象。支持[Plates](http://platesphp.com/)等模版。

3. 是否使用共享内存。该选项一般用于需要跨进程、跨服务共享，而内存占用较高的视图模版。

### 取出视图

> function View::fetch(string $name) View

> function View::show() void

fetch方法取出的视图将保存到局部变量$this-\>_temp中，可以对其进一步处理。直至调用show方法将其回传到控制器的retMsg中。

### 其他

Acast\\View内置两个成员函数，View::err和View::json，便于返回错误信息或将数组格式化为JSON。

如：

```php
$this->view->err('404', 'Page not found!')->show();
```