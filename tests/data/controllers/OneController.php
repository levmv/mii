<?php declare(strict_types=1);

namespace miit\data\controllers;

use mii\web\Controller;
use mii\web\Request;

class OneController extends Controller
{

    public function noparams()
    {
        return 'ok';
    }

    public function justint(int $id)
    {
        if($id === 123) {
            return 'ok';
        }
    }

    public function manyparams(int $id, string $path, string $foo = 'bar')
    {
        if($id === 123 && $path === 'foo/bar' && $foo === 'bar') {
            return 'ok';
        }
    }

    public function notypes($id, $request)
    {
        if($id === '123' && $request === 'foobar') {
            return 'ok';
        }
    }

    public function notypes2($id, $request, $foo = 'bar')
    {
        if($id === '123' && $request === 'foobar' && $foo === 'bar') {
            return 'ok';
        }
    }

    public function somedi(Request $request)
    {
        return 'ok';
    }

    public function somedi2(Request $request, int $id)
    {
        if($id === 123) {
            return 'ok';
        }
    }

    public function somedi3(int $id, Request $request)
    {
        if($id === 123) {
            return 'ok';
        }
    }
}
