<?php

namespace Acast;
/**
 * 路由
 * @package Acast
 */
class Router {
    /**
     * 设置别名的路由
     * @var array
     */
    protected $_alias = [];
    /**
     * 路由树
     * @var array
     */
    protected $_tree = [];
    /**
     * 设置指针
     * @var null
     */
    protected $_pSet = null;
    /**
     * 调用指针
     * @var null
     */
    protected $_pCall = null;
    /**
     * 服务名
     * @var string
     */
    protected $_app = null;
    /**
     * GET参数
     * @var array
     */
    public $urlParams = [];
    /**
     * 中间件返回数据
     * @var mixed
     */
    public $filterMsg = null;
    /**
     * 返回参数
     * @var mixed
     */
    public $retMsg = null;
    /**
     * 构造函数
     * @param string $app
     */
    function __construct(string $app) {
        $this->_app = $app;
    }
    /**
     * 注册路由。
     *
     * @param array|null $path
     * @param $methods
     * @param callable $callback
     * @return Router
     */
    function add(?array $path, $methods, callable $callback) : self {
        unset($this->_pSet);
        if (!($callback instanceof \Closure))
            $callback = \Closure::fromCallable($callback);
        $callback = \Closure::bind($callback, $this, __CLASS__);
        if (!is_array($methods))
            $methods = [$methods];
        if (is_null($path)) {
            if (!isset($this->_tree['/404']))
                $this->_tree['/404'] = [];
            $this->_pSet = $this->_tree['/404'];
            if (isset($this->_pSet['/func']))
                Console::Fatal("Conflict detected. Failed to register route.");
            $this->_pSet['/func'] = $callback;
            $this->_pSet['/in'] = [];
            $this->_pSet['/out'] = [];
            $this->_pSet['/ctrl'] = null;
        }
        foreach ($methods as $method) {
            if (!isset($this->_tree[$method]))
                $this->_tree[$method] = [];
            $this->_pSet = &$this->_tree[$method];
            foreach ($path as $value) {
                if (strpos($value, '/') === 0) {
                    if (!isset($this->_pSet->{'/var'}))
                        $this->_pSet['/var'] = [];
                    $this->_pSet = &$this->_pSet['/var'];
                    if (isset($this->_pSet['/name']))
                        Console::Fatal("Failed to register route. Conflict in Parameter name \"$value\".");
                    $this->_pSet['/name'] = substr($value, 1);
                } else {
                    if (!isset($this->_pSet[$value]))
                        $this->_pSet[$value] = [];
                    $this->_pSet = &$this->_pSet[$value];
                }
            }
            if (isset($this->_pSet['/func']))
                Console::Fatal("Conflict detected. Failed to register route.");
            $this->_pSet['/func'] = $callback;
            $this->_pSet['/in'] = [];
            $this->_pSet['/out'] = [];
            $this->_pSet['/ctrl'] = null;
        }
        return $this;
    }
    /**
     * 定位路由。该方法在收到HTTP请求后被调用。
     *
     * @param array $path
     * @param string $method
     */
    function locate(array $path, string $method) {
        unset($this->_pCall);
        unset($this->urlParams);
        unset($this->retMsg);
        unset($this->filterMsg);
        if (!isset($this->_tree[$method])) {
            $this->retMsg = Respond::Err(400, 'Invalid method.');
            return;
        }
        $this->_pCall = &$this->_tree[$method];
        foreach ($path as $value) {
            if (isset($this->_pCall[$value]))
                $this->_pCall = &$this->_pCall[$value];
            elseif (isset($this->_pCall['/var'])) {
                $this->_pCall = &$this->_pCall['/var'];
                $this->urlParams[$this->_pCall['/name']] = $value;
            } else goto Err;
        }
        Loop: {
            if (isset($this->_pCall['/func'])) {
                $this->call();
                return;
            }
            if (isset($this->_pCall['/var'])) {
                $this->_pCall = &$this->_pCall['/var'];
                $this->urlParams[$this->_pCall['/name']] = '';
            } else goto Err;
        } goto Loop;
        Err: {
            if (isset($this->_tree['/404']['/func'])) {
                $this->_pCall = $this->_tree['/404'];
                $this->call();
            } else
                $this->retMsg = Respond::Err(404, 'Not found.');
        }
    }
    /**
     * 路由分发。
     *
     * @param $name
     * @return bool
     */
    function dispatch($name) : bool {
        if (is_array($name)) {
            foreach ($name as $route) {
                $this->_pCall = &$this->_alias[$route];
                if (!$this->call())
                    return false;
            }
            return true;
        } else {
            $this->_pCall = &$this->_alias[$name];
            return $this->call();
        }
    }
    /**
     * 路由事件处理，包括中间件和路由回调。
     *
     * @return bool
     */
    protected function call() : bool {
        if (!isset($this->_pCall)) {
            Console::Warning('Failed to call. Invalid pointer.');
            return false;
        }
        foreach ($this->_pCall['/in'] as $in_filter) {
            if (!($in_filter() ?? true))
                break;
        }
        $callback = $this->_pCall['/func'];
        $ret = $callback() ?? true;
        foreach ($this->_pCall['/out'] as $out_filter) {
            if (!($out_filter() ?? true))
                break;
        }
        return $ret;
    }
    /**
     * 路由别名，用于实现分发。
     *
     * @param string $name
     * @return Router
     */
    function alias(string $name) : self {
        if (!isset($this->_pSet)) {
            Console::Warning("No route to alias..");
            return $this;
        }
        if (!is_array($name))
        if (isset($this->_alias[$name]))
            Console::Notice("Overwriting route alias \"$name\".");
        $this->_alias[$name] = [
            '/func' => $this->_pSet['/func'],
            '/in' => $this->_pSet['/in'],
            '/out' => $this->_pSet['/out'],
            '/ctrl' => $this->_pSet['/ctrl']
        ];
        return $this;
    }
    /**
     * 调用已注册的控制器中的方法。
     *
     * @param null $param
     * @return mixed
     */
    function invoke($param = null) {
        $class = $this->_pCall['/ctrl'][0];
        $method = $this->_pCall['/ctrl'][1];
        $object = new $class($this->_app, $this);
        $ret = $object->$method($param);
        $this->retMsg = $object->retMsg;
        return $ret;
    }
    /**
     * 绑定控制器及方法
     *
     * @param string $controller
     * @param string $method
     * @return Router
     */
    function bind(string $controller, string $method) : self {
        if (!isset($this->_pSet)) {
            Console::Warning("No route to bind.");
            return $this;
        }
        $controller = $this->_app .'\\Controller\\' . $controller;
        if (!class_exists($controller) || !is_subclass_of($controller, Controller::class)) {
            Console::Warning("Invalid controller \"$controller\".");
            return $this;
        }
        if (!method_exists($controller, $method)) {
            Console::Warning("Invalid method \"$method\".");
            return $this;
        }
        $this->_pSet['/ctrl'] = [$controller, $method];
        return $this;
    }
    /**
     * 绑定中间件
     *
     * @param $filters
     * @return Router
     */
    function filter(array $filters) : self {
        if (!isset($this->_pSet)) {
            Console::Warning("No route to filter.");
            return $this;
        }
        foreach ($filters as $filter => $type) {
            $callback = Filter::fetch($filter, $type);
            if ($callback) {
                if (!is_callable($callback)) {
                    Console::Warning('Failed to bind filter. Callback function not callable.');
                    continue;
                }
                if (!($callback instanceof \Closure))
                    $callback = \Closure::fromCallable($callback);
                $callback = \Closure::bind($callback, $this, __CLASS__);
                $this->_pSet[$type == IN_FILTER ? '/in' : '/out'][] = $callback;
            }
        }
        return $this;
    }
}