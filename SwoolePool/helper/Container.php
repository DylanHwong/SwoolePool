<?php
namespace Swoole\Helper;

use Closure;
use Exception;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionParameter;

class Container
{
    protected $bindings = [];

    protected static $instance = null;


    /**
     * 容器单例
     * @return Container|null
     */
    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 判断是否有这个类绑定到容器
     * @param $abstract
     * @return bool
     */
    public function hasConcrete($abstract)
    {
        if (array_key_exists($abstract, $this->bindings)) {
            return true;
        }

        return false;
    }

    /**
     * 绑定到容器
     * @param $abstract
     * @param null $concrete
     * @param bool $shared
     */
    public function bind($abstract, $concrete = null, $shared = false)
    {
        //如果是闭包
        if (!$concrete instanceof Closure) {
            $concrete = $this->getClosure($abstract, $concrete);
        }

        $this->bindings[$abstract] = compact('concrete', 'shared');
    }

    /**
     * 转换成闭包的形式保存到bindings
     * @param $abstract
     * @param $concrete
     * @return Closure
     */
    protected function getClosure($abstract, $concrete)
    {
        return function ($c) use ($abstract, $concrete) {
            $method = ($abstract == $concrete) ? 'bind' : 'make';
            return $c->$method($concrete);
        };
    }

    /**
     * 获取容器实例化类
     * @param $abstract
     * @return mixed|object
     * @throws Exception
     */
    public function make($abstract)
    {
        $concrete = $this->getConcrete($abstract);
        if ($this->isBuildable($concrete, $abstract)) {
            $object = $this->build($concrete);
        } else {
            $object = $this->make($concrete);//递归获取
        }
        return $object;
    }

    /**
     * 判断是否是当前实例或者闭包
     * @param $concrete
     * @param $abstract
     * @return bool
     */
    protected function isBuildable($concrete, $abstract)
    {
        return $concrete === $abstract || $concrete instanceof Closure;
    }

    /**
     * 获取绑定
     * @param $abstract
     * @return mixed
     */
    protected function getConcrete($abstract)
    {
        if (!isset($this->bindings[$abstract])) {
            return $abstract;
        }
        return $this->bindings[$abstract]['concrete'];
    }

    /**
     * @param $concrete
     * @return mixed|object
     * @throws ReflectionException
     * @throws Exception
     */
    public function build($concrete)
    {
        if ($concrete instanceof Closure) {
            return $concrete($this);
        }

        $reflector = new ReflectionClass($concrete);
        if (!$reflector->IsInstantiable()) {
            throw new Exception("Class can not instance");
        }
        $constructor = $reflector->getConstructor();//判断是否有构造函数
        if (is_null($constructor)) {
            return new $concrete;//没有则直接实例化
        }
        $dependencies = $constructor->getParameters();//获取构造函数参数
        $instances = $this->getDependencies($dependencies);//主要是看构造函数有没有依赖注入的类
        return $reflector->newInstanceArgs($instances);//从给出的参数创建一个新的类实例
    }

    /**
     * 解决参数依赖
     * @param $parameters
     * @return array
     * @throws Exception
     */
    protected function getDependencies($parameters)
    {
        $dependencies = [];
        foreach ($parameters as $parameter) {
            $dependency = $parameter->getClass();
            if (is_null($dependency)) {//判断是不是一个类
                $dependencies[] = null;
            } else {
                $dependencies[] = $this->resolveClass($parameter);//如果参数是一个类则实例化一个类
            }
        }

        return (array)$dependencies;
    }

    /**
     * 解决参数是类的方法
     * @param ReflectionParameter $parameter
     * @return mixed|object
     * @throws Exception
     */
    protected function resolveClass(ReflectionParameter $parameter)
    {
        return $this->make($parameter->getClass()->name);
    }

    /**
     * 构建参数
     * @param $class
     * @param $method
     * @param array $params
     * @return array
     * @throws ReflectionException
     */
    public function buildParameter($class, $method, $params = [])
    {
        $reflector = new ReflectionMethod($class, $method);
        $parameters = $reflector->getParameters();
        $args = [];
        if (!empty($parameters) && !empty($params)) {
            foreach ($parameters as $key => $parameter) {
                $name = $parameter->getName();
                $value = null;
                if (isset($params[$name])) {
                    $value = $params[$name];
                } elseif (isset($params[$key])) {
                    $value = $params[$key];
                } elseif ($parameter->isDefaultValueAvailable()) {
                    $value = $parameter->getDefaultValue();
                } else {
                    continue;
                }
                $args[$name] = $value;
            }
        }

        return $args;
    }
}