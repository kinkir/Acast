## 服务提供者(Acast\\Server)

[返回主页](../Readme.md)

### 新建服务

> static function Server::create(string $app, int $listen) void

事实上，每一个服务提供者是对一个Workerman的Worker示例的封装。和Acast框架的所有其他组件一样，它位于Acast命名空间下。

如下所示，调用静态方法create，创建一个名为Demo的服务，监听本地8080端口。

```php
use Acast\Server;
Server::create('Demo', 8080);
```

注意，此时，服务只是被注册，并没有启动。

### 获取服务

> static function Server::app(string $app) Server

我们可以用静态方法app来获取到已注册的服务，从而为其注册事件、路由等。

返回值为对应服务提供者实例。

### 添加路由

> function Server::route(array $path, $methods, callable $callback) Router

成员函数route可以用来为当前服务注册路由。它实质上是调用了与该服务提供者绑定的Route示例的add方法。

具体参数含义及使用方法参见[路由](Router.md)这一章中“注册路由”一节。

以下为示例：

```php
Server::app('Demo')->route(['hello'], 'GET', function () {
    $this->retMsg = 'Hello world!';
});
```

### 注册事件

> function Server::event(string $event, callable $callback) void

Acast服务提供者的事件是对Workerman事件的一个封装，要求用户传递事件类型及回调函数，并交由Workerman处理。调用回调函数时会传递对应Worker实例。

当前支持的事件有："start", "stop", "bufferFull", "bufferDrain"。

其中，start回调会在当前服务的每个进程启动时被调用，同理，stop回调是在每个进程正常终止时被调用。

### Worker配置

> function Server::config(array $config) void

你可以方便地在服务提供者中配置Worker。如名称、进程数等。如下所示：

```php
Server::app('Demo')->config([
    'name' => 'Demo',
    'count' => 4
]);
```

### 启动服务

> static function Server::start() void

执行服务启动前的初始化操作，并调用Worker::runAll()方法，启动所有Worker。