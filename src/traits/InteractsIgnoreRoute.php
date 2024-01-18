<?php
/**
 * Created by PhpStorm.
 * @Author: Kassy
 * @Date: 2023/6/8
 * @Time: 13:50
 * @describe:
 */

namespace Kassy\ThinkphpPlus\traits;

use common\annotation\IgnoreTokenRouting;
use Ergebnis\Classy\Constructs;
use ReflectionClass;
use ReflectionMethod;
use think\annotation\route\Group;
use think\annotation\route\Route;
use think\event\RouteLoaded;

trait InteractsIgnoreRoute
{
    protected string $controllerDir;

    protected string $routeSuffix;

    protected function registerIgnoreRoute(): void
    {
        $this->app->event->listen(RouteLoaded::class, function () {
            $this->controllerDir = realpath($this->app->getAppPath() . $this->app->config->get('route.controller_layer'));
            $this->routeSuffix = $this->app->http->getName();

            $this->scanAnnotation();
        });
    }

    protected function scanAnnotation(): void
    {
        $routes = [];
        $this->app->request->ignoreRoutes = [];
        if ($this->controllerDir) {
            foreach (Constructs::fromDirectory($this->controllerDir) as $construct) {
                $class = $construct->name();

                $refClass = new ReflectionClass($class);

                if ($refClass->isAbstract() || $refClass->isInterface() || $refClass->isTrait()) {
                    continue;
                }

                foreach ($refClass->getMethods(ReflectionMethod::IS_PUBLIC) as $refMethod) {
                    if ($this->reader->getAnnotation($refMethod, IgnoreTokenRouting::class)) {
                        $group = $this->reader->getAnnotation($refClass, Group::class);
                        $route = $this->reader->getAnnotation($refMethod, Route::class);
                        $routes[] = "/{$this->routeSuffix}{$group->name}{$route->rule}";
                    }
                }
            }
            $newRoutes = array_merge($routes,config('permission.apiWhiteList'));
            $this->app->request->ignoreRoutes = $newRoutes;
        }
    }
}
