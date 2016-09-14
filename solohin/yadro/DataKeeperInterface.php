<?php
/**
 * Created by PhpStorm.
 * User: solohin
 * Date: 14.09.16
 * Time: 7:54
 */
namespace solohin\yadro;

use Psr\Log\AbstractLogger;

interface DataKeeperInterface
{
    /**
     * DataKeeperInterface constructor.
     * @param $domain
     */
    public function __construct($domain, AbstractLogger $logger);

    /**
     * Обновляет запись или вставляет новую
     * @param $type
     * @param array $data
     * @return mixed
     */
    public function upsertBatch($type, array $data);

    /**
     * Получает самую большую дату изменения объекта
     * @param $type
     * @return mixed
     */
    public function getLastTimestamp($type);
}