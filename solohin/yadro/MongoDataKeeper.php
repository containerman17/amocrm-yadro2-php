<?php
/**
 * Created by PhpStorm.
 * User: solohin
 * Date: 14.09.16
 * Time: 7:58
 */
namespace solohin\yadro;

use MongoDB;
use MongoClient;
use Psr\Log\AbstractLogger;

class MongoDataKeeper implements DataKeeperInterface
{
    /** @var MongoDB */
    private $db;
    private $indexes = [
        'default' => [
            ['keys' => ['id' => -1], 'options' => ['unique' => true, 'background' => true]],
            ['keys' => ['date_create' => -1], 'options' => ['background' => true]],
            ['keys' => ['last_modified' => -1], 'options' => ['background' => true]],
            ['keys' => ['responsible_user_id' => 1], 'options' => ['background' => true]],
        ],
        'leads' => [
            ['keys' => ['id' => -1], 'options' => ['unique' => true, 'background' => true]],
            ['keys' => ['date_create' => -1], 'options' => ['background' => true]],
            ['keys' => ['last_modified' => -1], 'options' => ['background' => true]],
            ['keys' => ['responsible_user_id' => 1], 'options' => ['background' => true]],

            ['keys' => ['status_id' => 1], 'options' => ['background' => true]],
            ['keys' => ['pipeline_id' => 1], 'options' => ['background' => true]],
        ],
        'account' => []
    ];
    private $logger;

    /**
     * DataKeeperInterface constructor.
     * @param $accountName
     * @param $logger
     */
    public function __construct($accountName, AbstractLogger $logger)
    {
        $this->db = new MongoDB(new MongoClient(), '__' . $accountName);
        $this->logger = $logger;
    }

    /**
     * @param $name
     * return MongoCollection
     */
    private function getCollection($name)
    {
        if (!in_array($name, $this->db->getCollectionNames())) {
            $this->db->createCollection($name);

            $this->logger->warning('Collection ' . $name . ' not found. Creating a new one.');

            if (isset($this->indexes[$name])) {
                $indexes = $this->indexes[$name];
            } else {
                $indexes = $this->indexes['default'];
            }

            foreach ($indexes as $index) {
                $this->db->{$name}->createIndex($index['keys'], $index['options']);
                $this->logger->info(
                    sprintf(
                        'Creating indexes %s for collection %s',
                        implode(', ', array_keys($index['keys'])),
                        $name
                    )
                );
            }
        }
        return $this->db->{$name};
    }

    /**
     * Обновляет запись или вставляет новую
     * @param $type
     * @param array $data
     * @return mixed
     */
    public function upsertBatch($type, array $data)
    {
        $collection = $this->getCollection($type);
        foreach ($data as $item) {
            $collection->update(
                ['id' => $item['id']],
                [
                    '$set' => $item
                ],
                ['upsert' => true]
            );
        }
    }

    /**
     * Получает самую большую дату изменения объекта
     * @param $type
     * @return mixed
     */
    public function getLastTimestamp($type)
    {
        $collection = $this->getCollection($type);
        $cursor = $collection->find()->sort(['last_modified' => -1])->limit(1);
        $items = array_values(iterator_to_array($cursor));
        if (isset($items[0]['last_modified'])) {
            return $items[0]['last_modified'];
        } else {
            return 0;
        }
    }
}