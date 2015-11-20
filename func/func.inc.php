<?php
function L($className,$param = null,$debug = false)
{
    static $have;
    $MYlib 	= MY_LIBS.'/'.$className.'.class.php';

    $lib 	= LIBS.'/'.$className.'.class.php';
    //have args
    if(isset($have[$className]) && $param && @$have[$className][md5(serialize($param))])
    {
        return $have[$className][md5(serialize($param))];// return obj
    }
    //no args
    elseif(@isset($have[$className]) && !$param && @is_object($have[$className]['noparam']))
    {
        return @$have[$className]['noparam'];
    }
    else
    {
        if(is_file($MYlib))include_once($MYlib);
        elseif(is_file($lib))include_once($lib);
        else
        {
            if($debug)
                throw new \InvalidArgumentException('class '.$className.' no input');
            else
                response('class '.$className.' no input','404');
        }

        if($param)
        {
            $have[$className][md5(serialize($param))]  = new $className($param);
            return $have[$className][md5(serialize($param))];
        }
        else
        {
            $have[$className]['noparam'] = new $className();
            return 	$have[$className]['noparam'];
        }
    }

}