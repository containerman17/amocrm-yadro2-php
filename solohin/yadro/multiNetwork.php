<?php
/**
 * Created by PhpStorm.
 * User: solohin
 * Date: 27.04.16
 * Time: 18:49
 */
namespace solohin\yadro;

class multiNetwork{
    private static $ifaces = null;
    const DEFAULT_INTERFACE = 'eth0';
    const DEBUG = false;

    private static function debug($mes){
        if(self::DEBUG && php_sapi_name() === 'cli'){
            echo "DEBUG: ".$mes."\n";
        }
    }

    public static function getEthIfaces()
    {
        if (!is_null(self::$ifaces)) {
            return self::$ifaces;
        }
        self::$ifaces = [];
        $file = '/etc/network/interfaces';

        if (!file_exists($file)) {
            return self::$ifaces;
        }
        $lines = explode("\n", file_get_contents($file));
        foreach($lines as $line){
            $words = explode(' ', trim($line));
            if($words[0] == 'iface' && mb_stripos(trim($words[1]), 'eth') === 0){
                self::$ifaces[] = $words[1];
            }
        }
        self::debug("Получили интерфейсы ".implode(', ', self::$ifaces)." \n");
        return array_unique(self::$ifaces);
    }


    public static function waitAndGetNextCurlIface($waitFile, $lowPriority = false){
        self::debug("Получение интерфейса waitAndGetNextCurlIface \n");
        $ifaces = self::getEthIfaces();
        
        if(empty($ifaces)){
            $ifaces = [self::DEFAULT_INTERFACE];
        }
        
        for($i = 0; $i < 500; $i++){
            self::debug("Попытка $i \n");
            foreach($ifaces as $iface){
                $ifaceFile = self::getIfaceFile($iface, $waitFile);

                self::debug("Проверка $iface \n");

                if(self::isOrderFree($ifaceFile)){
                    self::debug("Интерфейс $iface свободен! \n");
                    file_put_contents($ifaceFile, time());
                    $ch=curl_init();
                    if($iface != self::DEFAULT_INTERFACE){
                        curl_setopt( $ch, CURLOPT_INTERFACE, $iface );
                    }
                    return $ch;
                }
            }
            if($lowPriority){
                sleep(2);
            }else{
                usleep(200000);
            }
        }
        throw new Exception('Так и не нашли свобойдный интерфейс!');
    }

    private static function getIfaceFile($iface, $waitFile){
        if($iface == self::DEFAULT_INTERFACE){
            return $waitFile;
        }else{
            return $waitFile.'.'.$iface;
        }
    }

    private static function isOrderFree($file){
        if(!file_exists($file)){
            file_put_contents($file, time() - 1);
        }
        return intval(file_get_contents($file)) != time();
    }
}